# MediCare React Frontend

React, TypeScript, Vite and MUI frontend for MediCare Health Group. The layout, theme, authentication context and API service organization are adapted from the senior-provided PHPdemo architecture. Medical features and API contracts come from the existing MediCare backend.

Use Node 24 and the pnpm version specified in package.json:

```bash
pnpm install --frozen-lockfile
pnpm dev
```

Open http://127.0.0.1:5174. Vite proxies `/api` to the independent Laravel backend at http://127.0.0.1:8002. To use another backend, set `BACKEND_URL` when starting Vite.

The current checkpoint includes email/password login, authenticated profile loading, Employee benefit/visit/medication display, HR employee/plan lists, and Helpdesk pending visit lookup. Forms for visit submission, HR assignment and Helpdesk decisions are the next integration steps. Sign-out currently clears the browser session token; backend token revocation is a future endpoint.

```bash
pnpm lint
pnpm build
```
