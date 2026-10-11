# Integration notes

The senior-provided `docs/runbook` and `base-image` directories are preserved as learning references. The source PHPdemo remains unchanged.

## What each directory does

- `docs/runbook`: step-by-step architecture, development, GitLab, deployment and operations material.
- `base-image`: Docker definitions for PHP-FPM, nginx and Supervisor, plus development settings and smoke-test examples. It supplies a reusable runtime environment; it does not contain MediCare business logic.
- `backend_laravel`: the user's existing Laravel 13 medical-benefit application.
- `frontend_react`: the adapted React application connected to that backend.

## Read the references alongside the current application

The original runbook describes Laravel 12, a different JWT package, name-based login and template cloud resources. MediCare retains Laravel 13, php-open-source-saver/jwt-auth, email/password login, access_token responses and its existing role/company authorization. Current local ports and setup are documented in the root README.

Copying these folders does not build or deploy a container. The root GitLab pipeline does not include the copied base-image pipeline. Local Docker now follows the Demo runtime and startup sequence: nginx/PHP-FPM/Supervisor, a bind-mounted backend, a separate migration job, and Vite on the Mac. The monorepo builds runtime stages in one backend Dockerfile instead of consuming a published custom base image. Cloud resource names and deployment procedures still require separate confirmation and verification.

Preserve the learning material. Finish end-to-end workflow verification before further template cleanup. Future implementation should proceed in small explained steps that the user can understand and reproduce.

## Current learning handoff

The React workflows now match the existing Laravel API checkpoint: Employee visit submission and benefit/medication lookup; HR employee medical-record review and annual plan assignment; Helpdesk pending review and approval/rejection. Laravel controllers, models, business rules and API response contracts remain the user's existing implementation.

Trace one action to learn the flow: `MedicalWorkflows.tsx` collects a form, `services/api/medical.ts` selects the API endpoint, `services/api/auth.ts` adds the JWT, a Laravel route calls its controller, and the controller reads or saves through the model. React then refreshes the displayed records.

Future product features should be built with the user in small explained steps. Cloud deployment remains a later learning milestone. JWT storage now matches the Demo localStorage approach. Logout captures the token before clearing local state so backend revocation is not skipped. Legacy Blade pages have been removed. Controller/Service/FormRequest alignment and Swagger now cover the existing APIs. Swagger UI uses `/api/doc` and its generated specification uses `/docs`, matching the Demo routes. Email login and SQLite tests remain intentional differences.
