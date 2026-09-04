# CreditOS Production Application Layer

## Version 0.2.0

CreditOS now has a first production WordPress application layer instead of only static HTML prototypes.

### Theme

- `front-page.php` carries the premium CreditOS marketing and onboarding experience.
- `page-dashboard.php` is an authenticated CreditOS client/staff application dashboard.
- Front and dashboard CSS are separated under `assets/css/`.
- Front and dashboard behavior is separated under `assets/js/`.
- The theme automatically creates/assigns a `/dashboard/` page when activated.
- Logged-in users can persist onboarding to the CreditOS REST API.
- Logged-out onboarding selections are held locally and are persisted after authentication.
- Mobile navigation is generated from the live WordPress front-page navigation.
- Staff mode is shown only to users with a CreditOS staff capability or administrator privileges.
- Dashboard quick actions can create real task and draft dispute records through the REST API.

### Core plugin

CreditOS Core 0.2.0 adds a repository/service layer and authenticated REST endpoints.

REST endpoints:

- `GET /wp-json/creditos/v1/onboarding`
- `POST /wp-json/creditos/v1/onboarding`
- `GET /wp-json/creditos/v1/dashboard`
- `POST /wp-json/creditos/v1/roadmaps`
- `POST /wp-json/creditos/v1/tasks`
- `POST|PUT|PATCH /wp-json/creditos/v1/tasks/{id}`
- `POST /wp-json/creditos/v1/disputes`
- `POST /wp-json/creditos/v1/documents`
- `POST /wp-json/creditos/v1/billing`
- `GET /wp-json/creditos/v1/staff/clients`

All current application endpoints require a logged-in WordPress user. The browser sends WordPress REST nonces. Staff and billing routes add capability checks.

### Database

The schema now includes:

- organizations
- clients
- businesses
- onboarding
- roadmap progress
- tasks
- disputes
- dispute items
- documents
- billing accounts
- notifications
- audit logs

The existing tables are upgraded through `dbDelta()` when the CreditOS Core version changes. Application writes update timestamps explicitly rather than relying on automatic MySQL timestamp changes.

### Connected application records

The first production layer now supports:

- creating/mapping a CreditOS client record to a WordPress user
- saving onboarding journey, goals, consent version, and completion
- reading dashboard records for tasks, disputes, documents, notifications, roadmaps, and billing
- updating 7-step roadmap progress
- creating and updating tasks
- creating draft dispute review records
- attaching an existing authorized WordPress media attachment to the CreditOS document vault
- storing billing-provider/account status records for later Stripe synchronization
- listing client records for authorized staff
- logging important writes in the CreditOS audit log

### Security model

- The dashboard redirects anonymous visitors to WordPress authentication.
- Client profiles are mapped to WordPress user IDs.
- Staff-only presentation and staff client listing are capability-controlled.
- Existing CreditOS roles are synchronized with the capabilities introduced in v0.2.0.
- Onboarding writes require authentication and consent.
- Document registration validates that the WordPress attachment exists and belongs to the client unless the caller has staff privileges.
- New dispute records begin in `draft` status so a human review can occur before correspondence is sent.
- Sensitive bureau/report integrations are not implemented yet and must use authorized providers rather than scraping.
- Billing database support is present, but Stripe charging, Checkout, Customer Portal, and webhooks are not enabled until credentials and webhook validation are configured.

### Next production modules

1. Private document upload/download delivery controls beyond WordPress media registration.
2. Full staff-to-client assignment and staff editing of a selected client workspace.
3. Dispute rounds, letter versions, evidence, responses, deadlines, and approval workflow.
4. Stripe Checkout, subscriptions, Customer Portal, invoices, and signed webhook synchronization.
5. CreditOS AI provider integration with audit logs and human approval gates.
6. Authorized personal/business credit-data gateway and provider adapters.
7. Notification delivery through email and approved SMS integrations.
