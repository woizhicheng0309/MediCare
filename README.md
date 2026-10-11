# MediCare Health Group

A medical benefits platform for a Third Party Administrator, with separate React and Laravel applications. Employees access their own medical records, HR manages employees within its company, and Helpdesk processes submissions across client companies.

## Architecture

```text
frontend_react/    React + TypeScript + Vite + MUI
backend_laravel/   Existing MediCare Laravel 13 + MySQL + JWT API
base-image/        Senior-provided Docker runtime reference
docs/              Senior-provided learning runbook and integration notes
compose.yaml      Local React, Laravel and MySQL integration
.gitlab-ci.yml    Frontend checks, backend tests and secret detection
```

The frontend structure and theme are adapted from the senior-provided PHPdemo template. Business logic, authentication contracts, models and migrations come from the existing MediCare backend. This repository preserves that backend's Git history and is independent of the original development directory.

## Learning references

The senior-provided build and operations runbook is preserved in `docs/runbook`. The `base-image` directory contains the PHP-FPM, nginx and Supervisor Docker runtime reference. Read [integration notes](docs/INTEGRATION-NOTES.md) before applying template commands: the references describe different authentication contracts and deployment resources. The copied base-image CI and cloud deployment are not activated. Local Docker integration uses the root compose.yaml and a separate PHP 8.5 backend image.

## Integration checkpoint

- Email/password login uses the unchanged `access_token` response.
- Sign out revokes the current JWT through Laravel before clearing the browser session.
- `/api/me` restores the authenticated user's profile and role.
- Employee overview displays annual benefits, personal visits and medications.
- HR manages own-company employees, reviews visits/medications and assigns the current-year benefit plan.
- Helpdesk reviews pending visits across client companies and approves or rejects submissions.
- Existing backend APIs retain visit submission, annual plan assignment and approval/rejection functionality.
- React visit submission, plan assignment and approval/rejection forms use those same protected APIs.
- Visits support diagnosis, sick leave dates, visit time, doctor details and multiple medications.
- Visit records support ascending and descending sorting.
- Legacy unauthenticated visit submission routes have been removed.

Only approved visits count toward annual usage. Medication costs are included in visit covered amounts and are not deducted twice. Each employee has one plan assignment per calendar year; changing plans preserves that year's usage. Display/input use whole TWD and storage uses integer cents.

## Local development

Requirements: PHP 8.3+ (the local environment uses PHP 8.5), Composer, MySQL, Node 24 and pnpm as pinned in the frontend manifest.

The independent backend uses `medicare_integration`, separate from the original `medicare_learning` database. Its local keys and database configuration stay in the ignored backend `.env`.

Start the backend:

```bash
cd backend_laravel
php artisan serve --host=127.0.0.1 --port=8002
```

In another terminal, start React:

```bash
cd frontend_react
pnpm dev
```

Open http://127.0.0.1:5174. Vite forwards `/api` requests to http://127.0.0.1:8002.

A fresh checkout requires `composer install`, a local `.env`, application/JWT keys, a MySQL database and `php artisan migrate`. DemoSeeder and BenefitSeeder provide reproducible sample companies, accounts, benefit plans and annual assignments. Run php artisan db:seed only against an intended development database. No credentials or local database files belong in Git.

## Validation

```bash
cd frontend_react
pnpm lint
pnpm build
```

Run backend tests with `cd backend_laravel && php artisan test`. The root GitLab pipeline runs frontend checks, backend PHP syntax/Composer validation, automated backend tests and secret detection. Cloud deployment is not configured. Original template GCP resource names and deployment pipelines have not been activated.

## Remaining work

Production container configuration, hosting configuration and deployment verification. Local Docker integration is documented below; it uses development servers and is not a production deployment.


## Local Docker integration

Requirements: Docker Engine and Docker Compose. Run commands from the repository root.

Generate an ignored `.env.docker` once with independent `APP_KEY` (a base64-encoded 32-byte key), `JWT_SECRET`, `DB_PASSWORD` and `MYSQL_ROOT_PASSWORD` values. Keep this file between restarts; do not commit it or print resolved Compose configuration containing these secrets.

```bash
docker build -t medicare-backend:local ./backend_laravel
# Use the backend image to generate local credentials without printing them.
# Run this only when .env.docker does not already exist.
docker run --rm medicare-backend:local php -r '
echo "APP_KEY=base64:".base64_encode(random_bytes(32)).PHP_EOL;
echo "JWT_SECRET=".bin2hex(random_bytes(32)).PHP_EOL;
echo "DB_PASSWORD=".bin2hex(random_bytes(24)).PHP_EOL;
echo "MYSQL_ROOT_PASSWORD=".bin2hex(random_bytes(24)).PHP_EOL;
' > .env.docker

git check-ignore .env.docker
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps
# Initialize the isolated development database on first setup.
docker compose --env-file .env.docker exec backend php artisan migrate --seed
```

Open http://localhost:5175. Vite proxies `/api` to the backend service. The backend is also available at http://localhost:8003. MySQL is accessible inside the Compose network, uses `medicare_docker`, and persists in a named volume separate from the host database. These ports avoid the existing host development servers.

Backend code is copied into the image, so rebuild after code changes. The frontend uses the existing Dockerfile's build stage with a Vite command override. Both services use development servers for this checkpoint.

User-verified acceptance: Employee submits a TWD 850 visit with medication breakdowns of TWD 300 and 200; HR can read it; Helpdesk approves it; annual usage increases by TWD 850 only. The record remains after `down` followed by `up -d`.

```bash
# Stop containers while preserving database data.
docker compose --env-file .env.docker down
# Restart with the same credentials and database volume.
docker compose --env-file .env.docker up -d
```

`down -v` deletes the database volume. Ordinary restarts do not need another seed run.
