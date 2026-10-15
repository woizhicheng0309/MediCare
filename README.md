# MediCare Health Group

A medical benefits platform for a Third Party Administrator, with separate React and Laravel applications. Employees access their own medical records, HR manages employees within its company, and Helpdesk processes submissions across client companies.

## Live demo

| Environment | URL | Purpose |
| --- | --- | --- |
| Dev | [medicare-dev.duckdns.org](https://medicare-dev.duckdns.org) | Validate changes before release |
| Prod | [medicare-prod.duckdns.org](https://medicare-prod.duckdns.org) | Stable public demonstration |

Both environments contain fictional sample accounts. The shared demonstration password is `LearningDemo!2026`.

| Account | Email | Role | Company |
| --- | --- | --- | --- |
| Alex Chen | `alex@microprogram.employee` | Employee | Microprogram |
| Jamie Lee | `jamie@horizon.employee` | Employee | Horizon |
| Morgan Lin | `morgan@microprogram.hr` | HR | Microprogram |
| Taylor Wong | `taylor@medicare.helpdesk` | Helpdesk | MediCare, the TPA operator |

Demo submissions and reviews write to the selected environment's database. Dev and prod data are independent; changes to sample records are not copied between them.

## Architecture

```text
frontend_react/    React + TypeScript + Vite + MUI
backend_laravel/   Laravel 13 + MySQL + JWT API
base-image/        PHP-FPM, nginx and Supervisor configuration
docs/              Product requirements and technical documentation
compose.yaml      Local MySQL, migration and nginx/PHP-FPM services
.gitlab-ci.yml    Checks, image publishing and Cloud Run deployments
```

The React frontend consumes the Laravel JWT API. Controllers coordinate HTTP requests, FormRequests validate input and authorization, and Services handle business operations and scoped queries. MySQL stores company, benefit, visit and medication records.

### Relationship loading

Eloquent models define the one-to-many relationship between visits and medications. Employee and HR visit lists use `with('medications')` to eager load medication records. The Helpdesk pending list uses `with(['user.company', 'medications'])` to also load each employee and company.

Eloquent retrieves the visits, fetches related records in batches, and matches them to their parent models using foreign keys. This avoids a separate medication query for every visit when reading the loaded relationships. See [Employee visits](backend_laravel/app/Services/VisitService.php), [HR employee visits](backend_laravel/app/Services/HrEmployeeService.php) and [Helpdesk pending visits](backend_laravel/app/Services/HelpdeskVisitService.php). This describes the implemented query strategy; query-count and performance measurements have not been recorded.

## Project documentation

[Product requirements](docs/PRD.md) define users, workflows and acceptance criteria. [System requirements](docs/SRS.md) document API contracts, authorization and validation. The [ERD and data dictionary](docs/ERD.md) describe database relationships and constraints. The `base-image` directory contains PHP-FPM, nginx and Supervisor configuration used by the backend Dockerfile. The root `.gitlab-ci.yml` defines validation, image publishing and Cloud Run deployments.

## Features

- Email/password login returns a JWT as `access_token`.
- Sign out captures the JWT, clears the browser session, then revokes the captured JWT through Laravel. A failed revocation is reported on the login page.
- `/api/me` restores the authenticated user's profile and role.
- Employee overview displays annual benefits, personal visits and medications.
- HR manages own-company employees, reviews visits/medications and assigns the current-year benefit plan.
- Helpdesk reviews pending visits across client companies and approves or rejects submissions.
- React forms submit visits, assign benefit plans and review submissions through protected APIs.
- Visits support diagnosis, sick leave dates, visit time, doctor details and multiple medications.
- Visit records support ascending and descending sorting.
- React provides browser pages; Laravel exposes the application API.
- JWT persistence uses localStorage.
- Controllers coordinate HTTP responses, FormRequests validate input/roles, and Services handle scoped queries and business operations.
- Swagger documents the application APIs, with email login and JWT Bearer authorization.

Only approved visits count toward annual usage. Medication costs are included in visit covered amounts and are not deducted twice. Each employee has one plan assignment per calendar year; changing plans preserves that year's usage. Display/input use whole TWD and storage uses integer cents.

## Local development

Requirements: Docker Engine and Compose, Node 24 and pnpm as pinned in the frontend manifest. The host backend `.env` remains independent; Compose injects its own database and keys into the containers.

Run commands from the repository root. Keep the ignored `.env.docker` with `APP_KEY`, `JWT_SECRET`, `DB_PASSWORD` and `MYSQL_ROOT_PASSWORD`. Do not regenerate existing keys or print resolved Compose configuration.

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

Compose starts MySQL, waits for its health check, runs the one-shot migration service, then starts the backend after migration succeeds. Local initialization also runs repeatable sample seeders. Deployment migrations do not run sample seeders; cloud demo data was initialized explicitly in each environment. A migration failure blocks backend startup; inspect it with `docker compose --env-file .env.docker logs migration`.

nginx listens inside the backend container on port 8080, serves public files and forwards Laravel requests to PHP-FPM through a Unix socket. Supervisor manages both processes. Backend source and vendor are bind-mounted at `/var/www`, so source changes do not require image rebuilds. Runtime/Dockerfile changes do require rebuilding.

In another terminal, run Vite on the Mac:

```bash
cd frontend_react
pnpm install --frozen-lockfile
pnpm dev
```

Open http://127.0.0.1:5174. Vite forwards `/api` and `/docs` to http://127.0.0.1:8003. Swagger UI is available at http://127.0.0.1:5174/api/doc; it loads its OpenAPI document through `/docs`. Vite runs on the host rather than as a Compose service.

MySQL uses `medicare_docker` inside the Compose network. The `mysql_data` volume persists database records across container restarts.

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

## GCP deployment

The application is deployed in project `medicare-19980309`, with Cloud Run, Cloud SQL and Artifact Registry in `asia-east1` (Taiwan). The load balancer is a global external Application Load Balancer.

```mermaid
flowchart LR
    Browser[Browser] --> DNS[DuckDNS dev / prod domains]
    DNS --> LB["Global external Application Load Balancer<br/>HTTPS and managed certificate"]
    LB -->|Host + API path| Backend["Cloud Run Laravel<br/>medicare-dev / medicare-prod"]
    LB -->|Host + other paths| Frontend["Cloud Run React / nginx<br/>medicare-frontend-dev / medicare-frontend-prod"]
    Backend --> SQL[("Cloud SQL MySQL<br/>medicare_dev / medicare_prod")]
    Secrets[Secret Manager] --> Backend
    Registry[Artifact Registry apps] --> Backend
    Registry --> Frontend
```

| Resource | Configuration |
| --- | --- |
| Load balancer | `medicare-lb`, reserved IPv4 `136.110.151.54`, HTTPS port 443, HTTP-to-HTTPS redirect |
| TLS certificate | Google-managed `medicare-cert`, covering both DuckDNS domains |
| Container registry | `asia-east1-docker.pkg.dev/medicare-19980309/apps`, backend and frontend images tagged with the full Git commit SHA |
| Database | Cloud SQL `medicare-mysql`, MySQL 8.4, separate `medicare_dev` and `medicare_prod` databases and database users |
| Backend identity | `backend-runtime`, with Cloud SQL Client and access to the relevant Secret Manager secrets |
| Frontend identity | `frontend-runtime` |
| Runtime secrets | Separate database passwords, Laravel application keys and JWT secrets for dev and prod |
| Cloud Run services | Request-based billing, minimum 0 and maximum 1 instance per service, 1 CPU and 512 MiB memory |
| Ingress | Internal + Load Balancing for all four services; external requests use the load balancer |
| Optional services | Cloud CDN disabled; no Cloud Armor policies configured |

The backend connects through the Cloud SQL Unix socket using its runtime service account. Secret values are supplied by Secret Manager, not embedded in images. Each backend has its own `APP_URL` set to its environment's HTTPS domain. React uses same-origin `/api` requests.

Routing first selects the environment by hostname, then selects the application by path:

| Host | Paths | Backend service | Cloud Run service |
| --- | --- | --- | --- |
| `medicare-dev.duckdns.org` | `/api`, `/api/*`, `/docs`, `/docs/*`, `/up` | `medicare-dev-backend` | `medicare-dev` |
| `medicare-dev.duckdns.org` | All other paths | `medicare-dev-frontend` | `medicare-frontend-dev` |
| `medicare-prod.duckdns.org` | `/api`, `/api/*`, `/docs`, `/docs/*`, `/up` | `medicare-prod-backend` | `medicare-prod` |
| `medicare-prod.duckdns.org` | All other paths | `medicare-prod-frontend` | `medicare-frontend-prod` |

Each backend service uses a serverless network endpoint group pointing to its Cloud Run service. Unmatched hosts use `medicare-prod-frontend` as the global default.

### Migration jobs

`medicare-dev-migration-job` and `medicare-prod-migration-job` use the backend image and their respective database settings. Each runs one task, with a five-minute timeout and zero retries.

The container command is `/bin/sh`, and its argument is `/var/www/db-migrate-seed.sh`. The shell executes the script, which runs `php artisan migrate --force` as `www-data`, then exits. Laravel tracks completed migrations, so later deployments execute only pending migrations.

Both cloud environments use `APP_ENV=production` and `APP_DEBUG=false`. The script seeds automatically only when `APP_ENV=local`; dev/prod demo accounts were initialized separately with `php /var/www/artisan db:seed --force`. Ordinary deployment does not reset demo records.

### CI/CD release workflow

```text
Feature branch push -> checks and image build validation
Protected main push / merge -> automatic image publishing -> automatic dev deployment
Verify dev -> manually run prod-deploy in the same pipeline -> production release
```

The pipeline runs frontend lint/build, backend PHP syntax checks, Composer validation, backend tests and secret detection. Image publishing and deployment jobs run only on protected `main`.

`backend-image-publish` and `frontend-image-publish` build and push commit-tagged images. `dev-deploy` waits for both publish jobs, updates and executes the dev migration job, waits for it to succeed, then updates the backend and frontend services. A migration failure stops the remaining deployment commands.

`prod-deploy` waits for that pipeline's dev deployment and requires a manual action. It uses the same commit-tagged images, runs the prod migration job, then updates the prod backend and frontend. Promotion does not rebuild the images or copy the dev database. Deployments are serialized separately for dev and prod with GitLab `resource_group`.

CI authenticates as `gitlab-ci` using the protected, masked and hidden `GCP_SERVICE_ACCOUNT_KEY` GitLab variable. The Base64-encoded credential is decoded into a temporary file for deployment and removed when the job exits. CI has Artifact Registry Writer on `apps`, Cloud Run Developer on the four services and two migration jobs, and Service Account User on both runtime service accounts.

To release a tested version, open its `main` pipeline in GitLab, verify `dev-deploy` succeeded, exercise the dev website, then run `prod-deploy`. Until production release is triggered, the pipeline can show **Blocked** because it is waiting for the manual job.

## Validation

```bash
cd frontend_react
pnpm lint
pnpm build
```

Run backend tests with `cd backend_laravel && php artisan test`. Tests retain SQLite in memory. The root GitLab pipeline runs these checks before publishing and deploying on protected `main`.

Runtime checks:

```bash
curl -fsS http://127.0.0.1:8003/ping
curl -fsS http://127.0.0.1:8003/up
docker compose --env-file .env.docker exec backend supervisorctl status
```

Verify login, Employee submission, HR visibility and Helpdesk approval through React. Medication amounts must remain included in visit coverage rather than deducted twice. The API uses Controller/Service/FormRequest separation and is documented with Swagger.

### Cloud validation checkpoint — 2026-10-14

[Pipeline #2946658940](https://gitlab.com/woizhicheng0309/medicare/-/pipelines/2946658940), for commit `30a2ccecea619c595f87a468bb6fe27a28902fb8`, passed all 11 jobs, including automatic dev deployment and manual prod deployment.

The following deployment checks were completed:

- Dev and prod HTTPS homepages and `/up` endpoints returned HTTP 200 after release.
- Direct `run.app` access returned HTTP 404 after ingress was restricted.
- Employee, HR and Helpdesk accounts logged in successfully in dev; prod sample login was also verified.
- An Employee submitted a TWD 600 Central Clinic visit in dev. After approval, annual usage was TWD 600 and the remaining Plus balance was TWD 17,400.
- HR saw Alex's approved visit within Microprogram and did not see Horizon employee Jamie in its employee list.
- After CI/CD deployment, dev retained the approved visit and balance; prod retained its separate initial TWD 18,000 balance.

These are manual cloud smoke checks, separate from the automated backend test suite. Demo records can change as visitors exercise the application.

## API documentation

Open http://127.0.0.1:5174/api/doc through Vite, or http://127.0.0.1:8003/api/doc directly. The JSON specification is served at `/docs`. It is generated on the first specification request and then reused. Edit `backend_laravel/app/OpenApi/ApiDocumentation.php` when changing an API contract, then regenerate:

```bash
docker compose --env-file .env.docker exec backend php artisan l5-swagger:generate
```

Use the login operation with your development credentials, copy its `access_token` into Swagger's Authorize dialog, and try protected endpoints. Authorization storage in Swagger is disabled by default; credentials and real tokens are not embedded in the specification. Write operations change the connected database, including visit submission and review.

API documentation uses L5 Swagger with PHP attributes for API definitions and JWT Bearer authorization. Swagger UI is served by Laravel; React provides the application pages.

Trace a submission: `StoreVisitRequest` validates employee access and input, `VisitController` calls `VisitService`, and the service saves the visit/medications in one transaction through their Models. The Controller returns the JSON response. Review follows the same separation and retains row locking, pending-status checks and server-generated audit fields.

## Planned improvements

Redis query caching is planned for later evaluation and is not implemented. The initial candidate is the company-scoped benefit-plan list, with a short expiration time. Start locally and verify cache hits, expiration, company isolation and database fallback before evaluating a cloud rollout. Benefit balances and the Helpdesk pending list retain their current direct database queries.
