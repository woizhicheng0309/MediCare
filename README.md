# MediCare Health Group

A medical benefits platform for a Third Party Administrator, with separate React and Laravel applications. Employees access their own medical records, HR manages employees within its company, and Helpdesk processes submissions across client companies.

## Architecture

```text
frontend_react/    React + TypeScript + Vite + MUI
backend_laravel/   Existing MediCare Laravel 13 + MySQL + JWT API
base-image/        Senior-provided Docker runtime reference
docs/              Senior-provided learning runbook and integration notes
.gitlab-ci.yml    Frontend lint/build and backend syntax/dependency checks
```

The frontend structure and theme are adapted from the senior-provided PHPdemo template. Business logic, authentication contracts, models and migrations come from the existing MediCare backend. This repository preserves that backend's Git history and is independent of the original development directory.

## Learning references

The senior-provided build and operations runbook is preserved in `docs/runbook`. The `base-image` directory contains the PHP-FPM, nginx and Supervisor Docker runtime reference. Read [integration notes](docs/INTEGRATION-NOTES.md) before applying template commands: the references describe different authentication contracts and deployment resources. Neither Docker deployment nor the copied base-image CI is activated by this integration.

## Integration checkpoint

- Email/password login uses the unchanged `access_token` response.
- `/api/me` restores the authenticated user's profile and role.
- Employee overview displays annual benefits, personal visits and medications.
- HR overview displays own-company employees and benefit plans.
- Helpdesk overview displays pending visits across client companies.
- Existing backend APIs retain visit submission, annual plan assignment and approval/rejection functionality.
- React forms for those write operations are the next UI checkpoint.

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

A fresh checkout requires `composer install`, a local `.env`, application/JWT keys, a MySQL database and `php artisan migrate`. The independent local database has a snapshot of the original sample accounts and medical records; a reproducible product seeder is still future work. No credentials or local database files belong in Git.

## Validation

```bash
cd frontend_react
pnpm lint
pnpm build
```

The root GitLab pipeline is configured for frontend checks and backend PHP syntax/Composer validation. Cloud deployment is not configured. Original template GCP resource names and deployment pipelines have not been activated.

## Remaining work

React visit submission, HR medical-record review/plan assignment and Helpdesk decision forms; backend token-revoking logout; reproducible seed data; automated authorization coverage; deployment setup. Legacy backend Blade routes are retained during integration and require cleanup before deployment.
