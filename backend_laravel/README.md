# MediCare backend

This Laravel application provides the MediCare API. React owns the browser interface in `frontend_react`; the backend has no separate Vite application.

Follow the [project README](../README.md) for Docker Compose startup, migrations, frontend development, tests, and Swagger documentation. The backend runs through nginx and PHP-FPM, with Supervisor managing both processes.

Controllers coordinate HTTP requests and responses, Form Requests validate input, and Services handle business operations.
