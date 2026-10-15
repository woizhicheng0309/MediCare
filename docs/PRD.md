# Product Requirements Document

**Product:** MediCare Health Group
**Baseline:** Implemented MVP, reviewed 2026-10-15

This document describes the current product scope and expected user outcomes. [System requirements](SRS.md) define the API and implementation contracts; the [ERD](ERD.md) describes stored data and relationships. Deployment instructions and demonstration accounts are in the [README](../README.md).

## 1. Product context

MediCare Health Group is a fictional Third Party Administrator (TPA) that manages medical benefits for client companies. Employees use benefits provided by their employer, client-company HR manages employee benefit assignments, and the TPA Helpdesk reviews submitted clinic visits.

The product brings these tasks into one workflow: an employee submits a visit, Helpdesk records a decision, and the employee and authorized HR can see the resulting medical record. Annual benefit usage is derived from approved visits so that the displayed balance reflects the review outcome.

The public deployment uses fictional accounts and records to demonstrate the workflow. It is not an operational claims-payment or clinical-care service.

## 2. Problem and goals

| User problem | Product goal | Observable outcome |
| --- | --- | --- |
| Employees need to know their available medical benefits. | Show the current annual plan, approved usage and remaining balance. | An employee can sign in and read a consistent benefit summary. |
| Visit information and medication breakdowns need a reviewable record. | Capture visit submissions and optional medical details together. | A valid submission appears as pending, with its medication records attached. |
| HR needs company-scoped employee information. | Provide medical-record lookup and current-year plan assignment within its own company. | HR can work with its employees but cannot select another company's employee or plan. |
| TPA Helpdesk needs a common queue across client companies. | Provide pending-visit review with a recorded reviewer and decision time. | Each pending visit can be approved or rejected once through the application. |
| Changes need verification before public release. | Separate dev validation from stable prod demonstration. | A version can be tested in dev and promoted to prod without copying the dev database. |

No numerical performance or usage targets have been established for this MVP. Completion is assessed through the acceptance scenarios below, automated regression checks and documented manual deployment checks.

## 3. Users and access scope

| Persona | Organization | Main tasks | Data scope |
| --- | --- | --- | --- |
| Employee | A client company, such as Microprogram or Horizon | Read benefits and personal visits; submit visits and medication details. | Own records. |
| HR | A client company | List employees and plans; inspect employee medical records; assign the current-year plan. | Employees and plans belonging to the HR user's company. |
| Helpdesk | MediCare, the TPA operator | Read the pending queue; approve or reject submissions. | Pending visits across all client companies. |

Helpdesk does not require a client-assignment mechanism in this MVP. Authorization is checked by the backend; changing the page or submitting a different record identifier does not grant access to another company's HR records.

## 4. Current MVP scope

### 4.1 Sign-in and sign-out

- Users sign in with an existing email and password.
- Successful sign-in opens the interface for the user's role.
- Protected operations require a valid JWT.
- Sign-out revokes the current token and clears the browser's saved session information.
- Repeated login requests are rate-limited.
- Account creation and initial sample data are provisioned separately; public self-registration is not available.

### 4.2 Employee benefits and visit records

- Show the current calendar year's plan name, annual limit, approved usage and remaining balance in TWD.
- Show a clear message when no current-year assignment exists.
- List the authenticated user's visits, including status and any medication details.
- Submit a visit with required clinic name, visit date and covered amount.
- Optionally record visit time, doctor name/title, diagnosis and sick leave dates.
- Optionally include medication names, dosage, instructions and covered amounts.
- Show validation feedback, request progress, success messages and empty states.
- Support ascending and descending sorting of displayed visit records.

Clinic, doctor and diagnosis values are entered as text. The MVP does not validate them against a panel-provider directory, medical classification or prescription system.

### 4.3 Client-company HR

- List employees belonging to the HR user's company.
- List that company's benefit plans and annual limits.
- Select an employee and read the employee's visit and medication records, including submitted medical details.
- Assign or replace that employee's benefit plan for the current calendar year.
- Preserve already approved usage when the plan changes.

HR does not approve visits. Company-wide dashboards and aggregate medical spending reports are future work.

### 4.4 TPA Helpdesk

- Show pending submissions across client companies, with employee/company context and medication details.
- Confirm an approve or reject decision in the interface before saving it.
- Record the reviewing Helpdesk user and the decision time on the server.
- Remove a processed submission from the pending queue after refresh.
- Reject another review of an already processed visit without changing the original decision or audit fields.

The MVP has a pending-review queue, not a dedicated searchable history of completed Helpdesk reviews. Employees and authorized HR can still read processed visits in their record views.

## 5. Core workflows

### Workflow A: Submit and review a visit

1. Employee signs in and opens the benefits page.
2. Employee enters the visit and any medication breakdown.
3. The application validates the submission and stores it as pending under that employee.
4. Helpdesk signs in, reads the pending submission and confirms an approval or rejection.
5. The application stores the decision, reviewer and review time together.
6. Employee reloads the record and benefit summary; approved coverage contributes to usage for the visit's calendar year.

```text
Employee submission -> Pending -> Helpdesk approval -> Approved -> Included in annual usage
                             \-> Helpdesk rejection -> Rejected -> Excluded from annual usage
```

### Workflow B: Assign or change an annual plan

1. HR signs in and selects an employee from its own company.
2. HR selects one of that company's existing benefit plans.
3. The application creates or updates the current-year assignment.
4. Employee reloads the benefit summary; the plan limit changes while that year's approved visit usage is preserved.

### Workflow C: Validate and release a version

1. A feature branch passes the configured checks and image-build validation.
2. A merge to protected `main` publishes commit-tagged images and automatically deploys dev after its migration succeeds.
3. The developer verifies the dev workflow and existing records.
4. A manual prod deployment uses the same pipeline's images, runs the prod migration and updates the prod services.

Dev and prod have separate application data and secrets. A release updates software; it does not copy test submissions between databases or automatically reseed sample records.

## 6. Business rules

| ID | Rule |
| --- | --- |
| BR-01 | A new visit belongs to the authenticated employee and always starts as `pending`; a submitted owner or status cannot override these values. |
| BR-02 | Only Helpdesk can change a pending visit to `approved` or `rejected`. The current workflow does not reopen a completed review. |
| BR-03 | An approvable visit must belong to a user. The reviewer and review time come from the server. |
| BR-04 | Annual usage sums the employee's approved visit covered amounts whose visit dates fall in the current application calendar year. Pending and rejected visits do not contribute. |
| BR-05 | Remaining balance equals the assigned plan's annual limit minus approved annual usage. |
| BR-06 | Displayed and submitted amounts are whole TWD; persistent monetary values use integer cents. |
| BR-07 | Medication amounts are breakdowns already included in the visit amount. Their sum cannot exceed the visit covered amount and must not be deducted again. |
| BR-08 | A visit may omit medications; a submission may contain at most 50 medication records. The visit and medications are saved together. |
| BR-09 | Sick leave is optional. If supplied, both start and end dates are required and the end cannot precede the start. Visit time is optional and represents clinic local time. |
| BR-10 | Each user has at most one benefit assignment per calendar year. HR assigns only existing plans and employees from its own company. |
| BR-11 | Changing the current-year plan does not reset usage or create a second assignment for that user/year. |

The current application uses UTC to determine the current year. It does not prevent submission or approval above the plan limit, so the calculated remaining balance can be negative. Coverage eligibility and limit enforcement require a separate product decision before implementation.

## 7. Acceptance scenarios

These criteria describe required behavior; they do not imply that every scenario has automated test coverage. See the SRS verification section for current evidence and gaps.

| ID | Scenario | Expected result |
| --- | --- | --- |
| AC-01 | A provisioned user signs in and later signs out. | The role interface opens; the signed-out token can no longer call protected APIs. |
| AC-02 | An employee submits a TWD 600 visit under a TWD 18,000 plan with no prior approved usage. | The visit is pending; usage remains TWD 0 and balance remains TWD 18,000. |
| AC-03 | Helpdesk approves that visit. | Status becomes approved, reviewer/time are stored, usage becomes TWD 600 and balance TWD 17,400. |
| AC-04 | Helpdesk rejects a different pending visit. | Its covered amount does not increase approved usage. |
| AC-05 | A processed visit is reviewed again. | The request fails as a conflict; the previous decision and audit fields remain unchanged. |
| AC-06 | An employee submits TWD 850 coverage with TWD 300 medication coverage. | Usage after approval increases by TWD 850, not TWD 1,150. |
| AC-07 | Medication totals exceed visit coverage, or only one sick leave date is supplied. | Validation fails and no visit or medication records from the submission are created. |
| AC-08 | HR from Microprogram lists employees and requests a Horizon employee's visits or plan assignment. | Only Microprogram employees appear; the out-of-company record operation is refused. |
| AC-09 | HR changes an employee's plan during the current year. | The employee has one assignment for that year and previously approved usage is unchanged. |
| AC-10 | An employee supplies another user's ID and an approved status while submitting a visit. | The stored owner remains the authenticated employee and status remains pending. |
| AC-11 | A release completes after records already exist in dev and prod. | The websites remain usable and each database retains its own records. |

## 8. Boundaries and future work

The implemented MVP does not include:

- Public registration, password reset, account administration or employee onboarding screens.
- Visit editing/deletion, review reversal, rejection reasons or a full change-history log.
- Payment settlement, attachments, full claims adjudication or guarantee-letter generation.
- Panel clinic/hospital administration, diagnosis catalogs or medication classification management.
- HR aggregate dashboards, spending exports or automated medical-leave reports.
- Server-side record pagination/search, notifications, scheduled resets or a review-history screen.

The public demo uses shared fictional data. Backup restoration, alerting, performance/load verification and repeatable demo reset operations remain operational follow-up work; no measured availability or capacity target has been established.

Future features should preserve employee ownership, HR company scope and the distinction between TPA Helpdesk and client-company HR. The current MVP supports a complete public demonstration; follow-up work can be scheduled independently of portfolio and application preparation.
