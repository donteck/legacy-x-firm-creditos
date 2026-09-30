# CreditOS Main Architecture

Last updated: September 30, 2026

CreditOS is the Legacy X Firm credit operating system. This document records the current high-level architecture and implementation position.

## Status Color Code

- 🟢 **Green — Built / Complete:** Core architecture is implemented and functioning.
- 🟡 **Yellow — Active / In Progress:** Built or substantially built, but currently being validated, hardened, or completed.
- 🔵 **Blue — Upcoming / Planned:** Planned future architecture that has not yet entered active implementation.
- ⚪ **White — Final / Later Phase:** Reserved for final production, QA, or later-stage completion work.

> **Current main-architecture zone:** 🟡 **#8–#11 — Account/Tradeline Inspector → Discrepancy/Evidence → Case Management.**
> **Current detailed workflow position:** 🟡 **Stage 3 — CASE.**

## Main Architecture Status

| # | Architecture | Status |
|---|---|---|
| 1 | Core Platform / WordPress Foundation | 🟢 Built |
| 2 | Authentication + Client/Staff Roles | 🟡 Built; permissions are being hardened |
| 3 | Client Portal / Dashboard | 🟢 Built |
| 4 | Client Onboarding / Profile | 🟢 Built |
| 5 | Credit Report Intake — PDF/import architecture | 🟢 Built |
| 6 | Credit Report Parsing / Normalization | 🟢 Built |
| 7 | Report Inspector | 🟢 Built |
| 8 | Account / Tradeline Inspector | 🟡 **CURRENT ZONE** — active development; core workflow substantially built |
| 9 | Collections Inspector | 🟡 Substantially built; parity/hardening remains |
| 10 | Discrepancy / Evidence Engine | 🟡 Built; currently being hardened |
| 11 | Case Management Engine | 🟡 **CURRENT — STAGE 3 CASE**; currently being validated |
| 12 | Dispute Drafting Engine | 🟡 Core built; hardening follows Case |
| 13 | Legal / U.S. Code Review Layer | 🟡 Core workflow built; deeper intelligence remains |
| 14 | Human Approval / Compliance Gate | 🟡 Core built; validation remains |
| 15 | Delivery / Mailing / Tracking | 🟡 Internal lifecycle built; real provider automation remains |
| 16 | Bureau/Creditor Response Tracking | 🟡 Core built; hardening remains |
| 17 | Outcome / Resolution Engine | 🟡 Core built; hardening remains |
| 18 | Multi-round Dispute Lifecycle | 🔵 Advanced work ahead |
| 19 | Bureau Direct Connections / Data Sync | 🔵 Future integration |
| 20 | AI Credit Analysis + Legal Intelligence | 🔵 Major advanced phase |
| 21 | Automation / Rules Engine | 🔵 Major advanced phase |
| 22 | Client Notifications / Communications | 🔵 Advanced integration |
| 23 | Billing / Subscription / Payments | 🔵 Future phase |
| 24 | Staff Operations / CRM / Analytics | 🔵 Advanced phase |
| 25 | Security / Audit / Compliance Hardening | 🟡 In progress across current development; final pass later |
| 26 | Production QA / Launch Readiness | ⚪ Final phase |

## Current Development Position

Current focus is the Account / Tradeline Inspector and the Review → Evidence → Case transition.

The seven-stage account workflow is:

1. Review
2. Evidence
3. Case
4. Draft
5. Legal
6. Approval
7. Delivery

Post-delivery response and outcome tracking follows Stage 7.

### Current stage

Stages 1 and 2 have been built and hardened. Stage 3 exists and is being permission- and safeguard-validated before deeper work continues.

Current pipeline:

`Import → Inspector → Review → Evidence → Case → Draft → Legal → Approval → Delivery → Outcome`

Current position:

`Review [complete] → Evidence [complete/hardened] → Case [current]`

## Confirmed safeguards

- Potential Inaccuracy requires factual discrepancy information rather than treating an adverse account status by itself as proof of inaccuracy.
- Evidence review/acceptance is staff-controlled through administrator or `creditos_manage_disputes` capability.
- Clients retain self-service evidence upload/view behavior while staff controls evidence adjudication.
- Case Candidate readiness requires Potential Inaccuracy, a documented discrepancy, and at least one Accepted evidence document.
- Case preparation is separate from dispute drafting.
- Draft, Legal, Final Approval, Delivery, and Response/Outcome are separate workflow gates.
- Delivery records are intended to reflect real delivery actions rather than claiming transmission that did not occur.
- Response/outcome records are intended to capture actual responses/results rather than infer outcomes.

## Role direction

### Client
Self-service capabilities include own profile, onboarding, and documents/evidence workflows. Clients should not self-approve evidence.

### CreditOS Specialist
Includes operational capabilities such as client management, roadmaps/tasks, dispute management, and staff dashboard access.

### Administrator
Full administrative access.

## Immediate next implementation target

Finish Stage 3 authorization and safeguards:

1. Confirm who can view Case readiness.
2. Confirm who can create an Internal Case Candidate.
3. Keep backend permissions authoritative.
4. Validate Case Preparation permissions.
5. Then continue hardening Stage 4 Draft, Stage 5 Legal, Stage 6 Approval, and Stage 7 Delivery without rebuilding already-existing engines.

## Architecture principle

CreditOS should maintain a controlled evidence-based workflow:

`Credit Data → Human Review → Evidence → Internal Case → Draft → Legal Validation → Final Approval → Real Delivery → Response/Outcome`

Each stage must preserve auditability, factual grounding, role separation, and explicit gates before downstream actions.


## Deployment Safety & Automation

CreditOS production deployment now uses a guarded GitHub → Hestia workflow.

### Current deployment chain

`Push to main → GitHub Actions validation → Hestia deployment → production`

The existing `.github/workflows/deploy-hestia.yml` workflow was hardened rather than creating a second competing deployment mechanism.

### Validation gates before production

Before the Hestia deployment job can run, GitHub Actions now performs:

1. Repository checkout.
2. PHP 7.4 setup.
3. CreditOS Composer dependency installation.
4. PHP syntax validation.
5. Node.js 20 setup.
6. JavaScript syntax validation with `node --check` across the CreditOS theme and core plugin.
7. CreditOS parser smoke test.
8. Hestia deployment only after the validation job succeeds.

This specifically prevents malformed JavaScript from being deployed automatically. It addresses the class of failure that previously caused the Account Inspector to remain at “Loading account…” after a syntax error in `reports.js`.

### Server-side deployment safeguards

The repository also contains:

- `scripts/validate-creditos.sh` — pre-deployment PHP and JavaScript validation.
- `scripts/deploy-creditos.sh` — guarded deployment with production backup, validation, WordPress checks, cache flush, HTTP health check, and rollback handling.
- Production Composer `vendor/` dependencies are preserved during deployment rather than deleted by repository synchronization.

A controlled deployment using the server-side deployment script completed successfully before the GitHub workflow hardening.

### Deployment safety commits

- `ff05f66` — Restore stable Account Inspector JavaScript.
- `7c0b012` — Add CreditOS pre-deployment validation.
- `71eaa5a` — Add safe CreditOS deployment with validation and rollback.
- `5520668` — Add JavaScript validation to the existing GitHub → Hestia workflow so deployment is blocked when JavaScript syntax fails.

### Deployment architecture principle

Production changes must not bypass validation:

`Code Change → GitHub → PHP/JS Validation → Smoke Test → Hestia Deploy → Production Verification`

A failed validation must stop deployment before production is changed.
