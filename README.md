# MediCare Health Group

A medical benefits platform for a Third Party Administrator, with separate React and Laravel applications. Employees access their own medical records, HR manages employees within its company, and Helpdesk processes submissions across client companies.

## Architecture

```text
frontend_react/    React + TypeScript + Vite + MUI
backend_laravel/   Existing MediCare Laravel 13 + MySQL + JWT API
base-image/        Senior-provided Docker runtime reference
docs/              Senior-provided learning runbook and integration notes
compose.yaml      Local MySQL, migration and nginx/PHP-FPM services
.gitlab-ci.yml    Frontend checks, backend tests and secret detection
```

The frontend structure and theme are adapted from the senior-provided PHPdemo template. Business logic, authentication contracts, models and migrations come from the existing MediCare backend. This repository preserves that backend's Git history and is independent of the original development directory.

## Learning references

The senior-provided build and operations runbook is preserved in `docs/runbook`. The `base-image` directory contains the PHP-FPM, nginx and Supervisor Docker runtime reference. Read [integration notes](docs/INTEGRATION-NOTES.md) before applying template commands: the references describe different authentication contracts and deployment resources. The copied base-image CI and cloud deployment are not activated. The backend Dockerfile builds PHP 8.5 runtime, dev, build and prod stages directly, reusing the reference configuration without pulling a separately published custom base image.

## Integration checkpoint

- Email/password login uses the unchanged `access_token` response.
- Sign out captures the JWT, clears the browser session, then revokes the captured JWT through Laravel. A failed revocation is reported on the login page.
- `/api/me` restores the authenticated user's profile and role.
- Employee overview displays annual benefits, personal visits and medications.
- HR manages own-company employees, reviews visits/medications and assigns the current-year benefit plan.
- Helpdesk reviews pending visits across client companies and approves or rejects submissions.
- Existing backend APIs retain visit submission, annual plan assignment and approval/rejection functionality.
- React visit submission, plan assignment and approval/rejection forms use those same protected APIs.
- Visits support diagnosis, sick leave dates, visit time, doctor details and multiple medications.
- Visit records support ascending and descending sorting.
- Legacy Blade pages and web routes have been removed; React provides browser pages.
- JWT persistence uses localStorage, matching the Demo.
- Controllers coordinate HTTP responses, FormRequests validate input/roles, and Services handle scoped queries and business operations.
- Swagger documents all existing APIs, with email login and JWT Bearer authorization.

Only approved visits count toward annual usage. Medication costs are included in visit covered amounts and are not deducted twice. Each employee has one plan assignment per calendar year; changing plans preserves that year's usage. Display/input use whole TWD and storage uses integer cents.

## Local development

Requirements: Docker Engine and Compose, Node 24 and pnpm as pinned in the frontend manifest. The host backend `.env` remains independent; Compose injects its own database and keys into the containers.

Run commands from the repository root. Keep the existing ignored `.env.docker` with `APP_KEY`, `JWT_SECRET`, `DB_PASSWORD` and `MYSQL_ROOT_PASSWORD`. Do not regenerate existing keys or print resolved Compose configuration.

```bash
# Build the dev stage, not the final production stage.
docker build --target dev -f backend_laravel/Dockerfile -t medicare-backend:dev .
```

For a fresh checkout only, generate `.env.docker` if it does not exist:

```bash
if [ ! -e .env.docker ]; then
  docker run --rm --entrypoint php medicare-backend:dev -r '
  echo "APP_KEY=base64:".base64_encode(random_bytes(32)).PHP_EOL;
  echo "JWT_SECRET=".bin2hex(random_bytes(32)).PHP_EOL;
  echo "DB_PASSWORD=".bin2hex(random_bytes(24)).PHP_EOL;
  echo "MYSQL_ROOT_PASSWORD=".bin2hex(random_bytes(24)).PHP_EOL;
  ' > .env.docker
fi
```

Install backend dependencies into the mounted project directory before starting services:

```bash
docker compose --env-file .env.docker run --rm --no-deps backend composer install --no-interaction --prefer-dist
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker up -d --build --remove-orphans
docker compose --env-file .env.docker ps -a
```

Compose starts MySQL, waits for its health check, runs the one-shot migration service, then starts the backend after migration succeeds. Local initialization also runs the existing repeatable sample seeders. Production migration never runs these sample seeders. A migration failure blocks backend startup; inspect it with `docker compose --env-file .env.docker logs migration`.

nginx listens inside the backend container on port 8080, serves public files and forwards Laravel requests to PHP-FPM through a Unix socket. Supervisor manages both processes. Backend source and vendor are bind-mounted at `/var/www`, so source changes do not require image rebuilds. Runtime/Dockerfile changes do require rebuilding.

In another terminal, run Vite on the Mac:

```bash
cd frontend_react
pnpm install --frozen-lockfile
pnpm dev
```

Open http://127.0.0.1:5174. Vite forwards `/api` and `/docs` to http://127.0.0.1:8003. Swagger UI is available at http://127.0.0.1:5174/api/doc; it loads its OpenAPI document through `/docs`. There is no Vite service in Compose. `--remove-orphans` removes the previous frontend container during the transition.

MySQL uses `medicare_docker` inside the Compose network. The existing `mysql_data` volume and Compose project name are preserved. It remains separate from the host `medicare_integration` database.

```bash
# Stop while preserving the database volume.
docker compose --env-file .env.docker down
# Restart; the initialization job runs before the backend.
docker compose --env-file .env.docker up -d
```

`down -v` deletes the database volume. Do not use it for ordinary restarts.

## Production image

```bash
docker build --target prod -f backend_laravel/Dockerfile -t medicare-backend:prod .
```

The same Dockerfile provides runtime, dev, build and prod stages. The build stage installs dependencies without dev packages and caches routes/views. The final stage contains nginx, PHP-FPM and application code, without Composer or Xdebug. Local credentials and generated files are excluded from the root build context. The startup hook caches configuration from runtime environment variables in production and skips optimization locally.

The production migration command uses the same application image with a separate job:

```bash
sh /var/www/db-migrate-seed.sh
```

Cloud resources, image publishing, proxy trust and CI deployment wiring remain separate upcoming checkpoints. No cloud deployment is activated by these local changes.

## Validation

```bash
cd frontend_react
pnpm lint
pnpm build
```

Run backend tests with `cd backend_laravel && php artisan test`. Tests retain SQLite in memory. The root GitLab pipeline runs frontend checks, backend PHP syntax/Composer validation, backend tests and secret detection.

Runtime checks:

```bash
curl -fsS http://127.0.0.1:8003/ping
curl -fsS http://127.0.0.1:8003/up
docker compose --env-file .env.docker exec backend supervisorctl status
```

Verify login, Employee submission, HR visibility and Helpdesk approval through React. Medication amounts must remain included in visit coverage rather than deducted twice. Controller/Service/FormRequest separation and Swagger documentation now cover the existing API checkpoint.

## API documentation

Open http://127.0.0.1:5174/api/doc through Vite, or http://127.0.0.1:8003/api/doc directly. The JSON specification is served at `/docs`. Like the Demo, it is generated on the first specification request and then reused. Edit `backend_laravel/app/OpenApi/ApiDocumentation.php` when changing an API contract, then regenerate:

```bash
docker compose --env-file .env.docker exec backend php artisan l5-swagger:generate
```

Use the login operation with your development credentials, copy its `access_token` into Swagger's Authorize dialog, and try protected endpoints. Authorization storage in Swagger is disabled by default; credentials and real tokens are not embedded in the specification. Write operations change the connected database, including visit submission and review.

The project uses the same L5 Swagger integration as the Demo, with a Laravel 13-compatible release and PHP attributes for API definitions. Its own documentation view comes from the package; the removed legacy login/benefit Blade pages are not restored.

Trace a submission: `StoreVisitRequest` validates employee access and input, `VisitController` calls `VisitService`, and the service saves the visit/medications in one transaction through their Models. The Controller returns the existing JSON contract. Review follows the same separation and retains row locking, pending-status checks and server-generated audit fields.
