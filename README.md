# Inventory Reservation API

A small, concurrency-safe JSON API for temporarily reserving limited product stock. It supports atomic multi-product reservations, safe request retries, automatic expiry, and optional confirm/release lifecycle operations.

The service uses PHP 8.3+, Symfony 7.4 LTS, Doctrine ORM 3, MySQL 8.4, FrankenPHP, and Docker Compose.

## Run locally

Prerequisite: Docker with Compose v2.

```bash
docker compose up --build --wait
```

The startup entrypoint installs dependencies, waits for MySQL, and runs migrations. The API is then available at `https://localhost`. The local TLS certificate is self-signed, so the examples use `curl -k`.

Check that the API and database are ready:

```bash
curl -k https://localhost/health
```

Expected response:

```json
{"status":"ok"}
```

Interactive Swagger UI is available at `https://localhost/api/doc`. The generated OpenAPI 3 document is available as JSON at `https://localhost/api/doc.json`. Both routes are public because authentication is outside this challenge's scope; Swagger UI assets are served locally rather than from a third-party CDN.

Stop the application without deleting its database:

```bash
docker compose down --remove-orphans
```

To also remove local database data, add `--volumes`.

## API

All request and response bodies are JSON. Validation and business errors use an RFC 9457-style `application/problem+json` response.

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/api/products` | Create a product with initial stock |
| `GET` | `/api/products` | List products and current available stock |
| `GET` | `/api/products/{id}` | Get one product and current available stock |
| `POST` | `/api/reservations` | Atomically reserve one or more products |
| `GET` | `/api/reservations/{id}` | Get reservation details |
| `POST` | `/api/reservations/{id}/confirm` | Mark an active reservation as sold |
| `POST` | `/api/reservations/{id}/release` | Return an active reservation to availability |
| `GET` | `/api/doc` | Interactive Swagger UI |
| `GET` | `/api/doc.json` | Generated OpenAPI 3 document |

`confirm` and `release` are small lifecycle additions beyond the three core challenge operations.

### 1. Create products

```bash
curl -k -X POST https://localhost/api/products \
  -H 'Content-Type: application/json' \
  -d '{"sku":"LAPTOP-001","name":"Laptop","stock":10}'
```

```json
{
  "id": "019c...",
  "sku": "LAPTOP-001",
  "name": "Laptop",
  "stock": 10,
  "available_stock": 10,
  "created_at": "2026-08-13T12:00:00.000000Z"
}
```

Create a mouse in the same way, then retain both returned product IDs.

### 2. Reserve multiple products

Every reservation request requires an `Idempotency-Key`. A UUID or another high-entropy, checkout-attempt identifier is recommended. Retrying the same logical request with the same key returns the original reservation and includes `Idempotency-Replayed: true`. Reusing a key with different input returns `409 Conflict`.

`ttl_seconds` must be between 1 and 3600. A request may contain 1 to 100 distinct products.

```bash
curl -ki -X POST https://localhost/api/reservations \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: checkout-attempt-7f58aab1' \
  -d '{
    "ttl_seconds": 900,
    "items": [
      {"product_id":"<laptop-id>","quantity":2},
      {"product_id":"<mouse-id>","quantity":1}
    ]
  }'
```

Successful requests return `201 Created`:

```json
{
  "id": "019c...",
  "status": "active",
  "expires_at": "2026-08-13T12:15:00.000000Z",
  "created_at": "2026-08-13T12:00:00.000000Z",
  "updated_at": "2026-08-13T12:00:00.000000Z",
  "items": [
    {
      "product_id": "019c...",
      "sku": "LAPTOP-001",
      "name": "Laptop",
      "quantity": 2
    }
  ]
}
```

If any requested item lacks stock, the transaction creates no reservation and holds no products:

```json
{
  "type": "/problems/insufficient-stock",
  "title": "Insufficient stock. No inventory was reserved.",
  "status": 409,
  "shortages": [
    {
      "product_id": "019c...",
      "sku": "MONITOR-001",
      "requested": 1,
      "available": 0
    }
  ]
}
```

### 3. View or transition a reservation

```bash
curl -k https://localhost/api/reservations/<reservation-id>

curl -k -X POST https://localhost/api/reservations/<reservation-id>/confirm

curl -k -X POST https://localhost/api/reservations/<reservation-id>/release
```

An active reservation stops holding inventory as soon as its database timestamp elapses. The local `expiry-worker` also materializes its status as `expired` every five seconds; reads materialize the status immediately as well. A confirmed reservation remains sold, while a released or expired reservation no longer affects availability.

## Tests

The suite uses a separate `app_test` database. Create and migrate it once, then run PHPUnit:

```bash
docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction --all-or-nothing
docker compose exec -e XDEBUG_MODE=off php php bin/phpunit
```

The tests verify:

- atomic all-or-nothing behavior;
- idempotent replay and conflicting key reuse;
- expiry returning inventory immediately;
- confirm and release transitions;
- request validation and problem responses;
- eight real parallel processes competing for one unit without overselling;
- simultaneous retries with one idempotency key creating exactly one reservation.
- generated OpenAPI contract and locally served Swagger UI.

Useful additional checks:

```bash
docker compose exec php composer validate --strict
docker compose exec php php bin/console lint:container
docker compose exec php php bin/console doctrine:migrations:up-to-date --env=test
```

## Project layout

```text
src/Controller/       thin HTTP endpoints using argument-mapping attributes
src/Dto/              immutable request DTOs and validation attributes
src/Entity/           Doctrine ORM entities and reservation state transitions
src/Http/              API errors and custom header argument mapping
src/Repository/       Doctrine ORM/DQL persistence queries
src/Service/          application orchestration and transaction boundaries
src/Command/          expiry cleanup worker command
migrations/           MySQL/InnoDB schema and constraints
tests/Functional/     API and parallel concurrency tests
DECISIONS.md          architecture, trade-offs, and limitations
```

See [DECISIONS.md](DECISIONS.md) for the consistency model and design rationale.
