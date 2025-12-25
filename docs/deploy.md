# Deployment Guide (Single VPS)

This document outlines how to run the API stack in production on a single VPS using Docker Compose with the production configuration.

## Prerequisites
- Docker and Docker Compose installed on the host.
- A domain pointed at the VPS for HTTPS termination.
- An `.env` file under `api/` with production values and no shared secrets committed to source control.

## Environment variable checklist
Set these variables in `api/.env` (or export them before running Compose):

- `APP_KEY` – generated via `php artisan key:generate --show`.
- `APP_URL` – public HTTPS URL (e.g., `https://api.example.com`).
- `APP_ENV=production`, `APP_DEBUG=false`.
- Database: `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- Cache/queue: `REDIS_HOST=redis`, `REDIS_PORT=6379`, `REDIS_PASSWORD`.
- Queue/session/cache drivers are wired for Redis by default in `compose.prod.yaml`.
- OSRM: `OSRM_BASE_URL=http://osrm:5000` (internal service name).

## Building and running the production stack
1. From the repository root, ensure `api/.env` contains the production values above.
2. Build and start the stack: `make prod-up`.
3. Tail logs across services (default stack is `dev`; switch to production): `STACK=prod make logs`.
4. Run database migrations after the services are healthy: `STACK=prod make migrate`.
5. Stop the stack when needed: `make prod-down`.

The production stack uses:
- `web` (nginx) in front of the `app` PHP-FPM container.
- Dedicated `queue` and `scheduler` workers from the same application image.
- Postgres and Redis with named volumes (`pgsql-data`, `redis-data`), plus `app-storage` for Laravel storage and `osrm-data` for routing assets.

## HTTPS reverse proxy
Terminate TLS with a reverse proxy that forwards to the web container (port 80 by default). Example Caddyfile on the host:

```
api.example.com {
    reverse_proxy localhost:80
    log {
        output file /var/log/caddy/api.example.com.log
    }
}
```

For nginx on the host, proxy `https://api.example.com` to `http://127.0.0.1:80` and include `proxy_set_header Host $host;`.

If you need to run the proxy inside Docker, publish `APP_HTTP_PORT` to a non-privileged host port (e.g., `APP_HTTP_PORT=8080 make prod-up`) and bind your TLS terminator to that port.

## Backups and data durability
- **Postgres:** periodic `pg_dump` from the host, e.g.,
  `docker compose -f api/compose.prod.yaml exec pgsql pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" > backup.sql`.
- **Redis:** for simple use, trigger a save with `docker compose -f api/compose.prod.yaml exec redis redis-cli -a "$REDIS_PASSWORD" save` and back up the `redis-data` volume.
- **OSRM data:** persists in the `osrm-data` volume; back it up if you want to avoid re-downloading on rebuilds.
- **Laravel storage:** user uploads/logs live in the `app-storage` volume; back it up alongside the database.

Run restores by piping the backup files into the corresponding service with `docker compose -f api/compose.prod.yaml exec`.
