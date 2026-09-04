# CreditOS Production Application Layer

## Version 0.2.0

CreditOS now has a first production WordPress application layer instead of only static HTML prototypes.

### Theme

- `front-page.php` carries the premium CreditOS marketing and onboarding experience.
- `page-dashboard.php` is an authenticated CreditOS application dashboard.
- Front and dashboard CSS are separated under `assets/css/`.
- Front and dashboard behavior is separated under `assets/js/`.
- The theme automatically creates/assigns a `/dashboard/` page when activated.
- Logged-in users can persist onboarding to the CreditOS REST API.
- Logged-out onboarding selections are held locally and can be resumed after authentication.
- Staff mode is shown only to users with a CreditOS staff capability or administrator privileges.

### Core plugin

CreditOS Core 0.2.0 adds a repository/service layer and authenticated REST endpoints.

REST endpoints:

- `GET /wp-json/creditos/v1/onboarding`
- `POST /wp-json/creditos/v1/onboarding`
- `GET /wp-json/creditos/v1/dashboard`

All current endpoints require a logged-in WordPress user and use WordPress REST nonces in the browser.

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

The existing tables are upgraded through `dbDelta()` when the CreditOS Core version changes.

### Security model

- The dashboard redirects anonymous visitors to WordPress authentication.
- Client profiles are mapped to WordPress user IDs.
- Staff-only presentation is capability-controlled.
- Onboarding writes require authentication and consent.
- Sensitive bureau/report integrations are not implemented yet and must use authorized providers rather than scraping.
- Billing table support is present, but Stripe payment processing is not enabled until API credentials/webhooks are configured.

### Next production modules

1. Secure media/document upload endpoint and private delivery controls.
2. Full task CRUD and roadmap progression APIs.
3. Dispute CRUD, rounds, letters, evidence, response tracking, and human approval workflow.
4. Stripe checkout/customer portal/webhooks.
5. CreditOS AI provider integration with audit logs and approval gates.
6. Authorized credit-data gateway/provider adapters.
