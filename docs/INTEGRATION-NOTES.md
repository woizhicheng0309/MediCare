# Integration notes

The senior-provided `docs/runbook` and `base-image` directories are preserved as learning references. The source PHPdemo remains unchanged.

## What each directory does

- `docs/runbook`: step-by-step architecture, development, GitLab, deployment and operations material.
- `base-image`: Docker definitions for PHP-FPM, nginx and Supervisor, plus development settings and smoke-test examples. It supplies a reusable runtime environment; it does not contain MediCare business logic.
- `backend_laravel`: the user's existing Laravel 13 medical-benefit application.
- `frontend_react`: the adapted React application connected to that backend.

## Read the references alongside the current application

The original runbook describes Laravel 12, a different JWT package, name-based login and template cloud resources. MediCare retains Laravel 13, php-open-source-saver/jwt-auth, email/password login, access_token responses and its existing role/company authorization. Current local ports and setup are documented in the root README.

Copying these folders does not build or deploy a container. The root GitLab pipeline does not include the copied base-image pipeline. Docker integration, cloud resource names and deployment procedures must be adapted and verified as a separate learning step before they are used.

Preserve the learning material. Finish end-to-end workflow verification before further template cleanup. Future implementation should proceed in small explained steps that the user can understand and reproduce.
