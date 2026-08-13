# Technical decisions

## Scope

The implementation covers the three required operations: adding products and initial stock, atomically reserving one or more products, and retrieving a reservation. It also includes small `confirm` and `release` operations so every state named in the challenge has a concrete lifecycle. Authentication, checkout, and payment remain out of scope.

## MySQL is the consistency boundary

All application instances share one MySQL/InnoDB primary. A reservation transaction:

1. serializes requests with the same idempotency key;
2. locks every requested product row using `SELECT ... FOR UPDATE` in sorted UUID order;
3. calculates stock already held or sold;
4. verifies every requested item; and
5. inserts the reservation and all items before one commit.

Doctrine ORM pessimistic write locks produce the required `SELECT ... FOR UPDATE` behavior. The product locks make the stock check and reservation write indivisible for competing requests. Sorting the locks gives transactions a common lock order and avoids the usual multi-product deadlock. A failure or exception rolls back every item, which provides the required all-or-nothing behavior.

The Compose database is explicitly configured for `READ COMMITTED`. This is sufficient because every code path that allocates inventory follows the same product-row locking protocol. A serializable isolation level would add abort/retry overhead without improving this invariant.

## Availability is derived, not a mutable counter

`products.stock` records initial sellable stock. Current allocation is the sum of:

- every `confirmed` reservation item; and
- every `active` reservation item whose `expires_at` is still in the future.

Available stock is initial stock minus that allocation. This avoids a fragile denormalized `available_stock` counter that every expire, release, and confirm path would have to update exactly once. The trade-off is a more expensive aggregate query. For this focused service, correctness and auditability are preferable. At larger scale I would introduce an inventory ledger or a carefully maintained aggregate after measuring query load.

Database check constraints provide a second line of defense for non-negative stock, positive quantities, and valid statuses. Foreign keys prevent orphaned reservation items.

## Retry safety

`POST /api/reservations` requires an `Idempotency-Key`. The key and a SHA-256 hash of normalized request input are stored with the reservation.

Symfony Lock's Doctrine DBAL store serializes simultaneous retries across all application instances using the shared MySQL `lock_keys` table. The lock is acquired before the reservation transaction and released afterward. Once acquired, the service returns the stored reservation for an identical request or `409 Conflict` if the same key is reused with different input. If the first attempt rolls back, no idempotency key is stored and the next retry may proceed. The reservation's unique idempotency-key constraint remains a database-level backstop.

Keys currently share the lifetime of reservations. A production retention policy would archive old reservations and retain a smaller idempotency record for at least the documented retry window.

## Expiry and time

Reservation deadlines are generated through Symfony's injectable `ClockInterface` and normalized to UTC. Allocation is evaluated by portable Doctrine DQL using the database's `CURRENT_TIMESTAMP`, so elapsed active reservations stop holding inventory even if no worker has run. Application and database hosts must therefore keep their clocks synchronized, as is standard in a production deployment; the provided containers run in UTC.

The `app:expire-reservations` worker materializes the visible `expired` status, and reservation reads do the same for the requested row. The Docker development stack runs the worker every five seconds. This split means delayed cleanup can make the stored status briefly stale, but can never keep inventory unavailable or permit overselling.

## Reservation state machine

```text
active -> confirmed
active -> released
active -> expired (when the database deadline elapses)
```

Confirmed inventory is considered sold and remains unavailable. Released and expired inventory becomes available. Confirm and release are idempotent when repeated in their resulting state; other invalid transitions return `409 Conflict`. An elapsed reservation cannot be confirmed.

## API choices

- UUIDv7 identifiers are generated in the application. They are globally unique and roughly time-sortable without a database sequence bottleneck.
- JSON payloads are mapped to immutable DTOs with Symfony's `#[MapRequestPayload]`; validation lives on DTO properties rather than in controllers. A small `#[MapIdempotencyKey]` resolver provides the same typed boundary for the required header. Route identifiers use `#[MapEntity]`, so controllers receive typed ORM entities rather than handling request or persistence details.
- Reservation TTL is limited to 1–3600 seconds and each request to 100 distinct products. These limits bound lock duration and query size.
- Errors use JSON problem responses with stable problem types and machine-readable details.
- NelmioApiDocBundle generates an OpenAPI 3 contract from PHP attributes and the request/response DTO schemas. Swagger UI is served at `/api/doc`, uses local bundle assets, and documents the required idempotency header, lifecycle responses, validation limits, and error media types.
- Product list/get operations expose `available_stock` for demonstration and verification, though only creation was required.
- Migrations run automatically when the application container starts. Production deployments should run migrations as a separate release step before scaling new instances.

## Persistence boundaries

Products, reservations, and reservation items are Doctrine ORM entities. Services contain business orchestration and transaction boundaries but no SQL, DQL, or query builders. Repositories own all persistence queries and use the ORM query builder/DQL for entity lookup, allocation aggregates, pessimistic locking, and expiry updates.

There are no platform-specific queries in the application services or reservation repositories. Cross-instance idempotency locking is delegated to Symfony Lock's Doctrine DBAL store, which encapsulates its persistence details. The health probe's portable `SELECT 1` remains isolated in `HealthCheckRepository` because it intentionally verifies a live database round trip rather than loading an entity.

## Verification

Functional tests exercise API validation, atomic failure, expiry, lifecycle transitions, and retry behavior against MySQL 8.4. Concurrency tests start eight independent PHP processes at the same instant: one proves that only one contender can purchase a single unit, and another proves that simultaneous retries create one reservation. Database constraints, container linting, Composer validation, and migration checks run in CI.

## Assumptions and limitations

- MySQL 8.4 with InnoDB is a required system component, and a single logical primary is the source of truth. Multi-primary or cross-region writes would need a different consistency design.
- Initial stock is immutable through the API, as allowed by the challenge. There is no replenishment, cancellation of a confirmed sale, or adjustment ledger.
- Guest identity is intentionally absent. Idempotency keys are global, so clients should generate high-entropy values.
- Product listing has no pagination because it is convenience functionality for this small exercise.
- There is no rate limiting, authentication, metrics backend, or distributed tracing; those are deployment/platform concerns outside the requested scope.

With more time, I would add an auditable stock-adjustment ledger, metrics for lock wait time and reservation outcomes, structured request tracing, idempotency-retention cleanup, and sustained contention/load tests.
