# MySQL setup

This project is already configured for MySQL 8.4 with InnoDB, `utf8mb4`, and
`READ COMMITTED` isolation. Docker Compose creates both `app` and `app_test` on
the first startup, grants them to the application user, and persists them in the
named `database_data` volume.

The application and worker use the Doctrine MySQL URL defined in `compose.yaml`.
The PHP images install `pdo_mysql`, and the migration rejects non-MySQL
platforms to avoid silently creating a schema with different locking semantics.

Start and verify the configured stack with:

```console
docker compose up --build --wait
docker compose exec php php bin/console doctrine:schema:validate
```
