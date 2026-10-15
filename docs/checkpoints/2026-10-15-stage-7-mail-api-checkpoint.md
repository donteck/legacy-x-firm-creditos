# CreditOS QA & Architecture Checkpoint — 2026-10-15

## Current position

CreditOS has reached **Stage 7 — Delivery & Tracking** using the isolated synthetic QA fixture.

Validated workflow:

1. Review — Potential Inaccuracy
2. Evidence — accepted synthetic evidence
3. Case — Case Candidate #1 / Internal Case #1
4. Draft — Draft Version 1, recipient EXPERIAN
5. Legal & Compliance — Validated
6. Final Approval — Approved
7. Delivery & Tracking — unlocked/current stage

QA fixture:
- Report #5
- Tradeline #1290
- Case Candidate #1
- Internal Case #1
- Synthetic account: CREDITOS QA TEST ACCOUNT / EXPERIAN / TESTXXXX
- Test data only; not a real consumer account.

## Stage 5 findings

Legal Review backend was inspected in `class-creditos-legal-review.php`. Validation requires:
- `legal_references`
- `authority_verification`
- `reviewer_notes`
- `review_status=validated`

The backend preserves Legal Review as a separate human gate and does not itself approve or send a dispute.

The Account Inspector front-end in `wp-content/themes/creditos/assets/js/reports.js` was also inspected. The tradeline Legal Review form binds the three fields independently and posts them separately. The final QA run successfully reached Validated. No production code change was made from the earlier suspected field-binding issue because inspection did not substantiate cross-wiring.

Production-hardening item: Legal validation currently accepts any non-empty legal reference. Replace this eventually with structured authority metadata (citation/source, source URL or identifier, reviewer verification record, timestamp) rather than relying on arbitrary free text.

## Stage 6 result

Final Approval is separate from Legal validation. QA reached `Approved`. Approval itself must never mean that mail was transmitted.

## Stage 7 — next work

Stage 7 is the next active development/QA checkpoint.

Existing lifecycle:
**Prepare → Record Send → Track → Follow Response**

Next session:
1. Finish Stage 7 state-machine QA without sending the synthetic letter.
2. Confirm preparation, send-status, tracking/reference, delivery confirmation, and response-deadline safeguards.
3. Do not record `Sent` unless a real provider/transmission event exists.
4. After Stage 7 QA, build the Mail Provider API layer.

## Mail API architecture direction

Target flow:

**Approved Draft → Final immutable PDF → Mail Provider Adapter → USPS-capable provider → Provider Job ID → Tracking → Delivery Confirmation → Response Deadline → Response/Outcome**

Architecture requirements:
- Approval and mailing remain separate actions.
- Never mark a communication Sent from a UI click alone.
- A provider must accept the mailing and return a real provider/job identifier before CreditOS records transmission.
- Preserve the exact approved draft revision and final PDF associated with each mailing.
- Store provider, method, timestamps, tracking/reference ID, delivery status, confirmation, and response deadline.
- Design a provider adapter/interface so CreditOS is not hard-coded to one vendor.
- Evaluate Lob and other USPS-capable APIs later.
- Keep synthetic QA isolated and never transmit the QA dispute.

## Deployment checkpoint

Last confirmed live application commit before this documentation checkpoint:
`a7fd89bfb269cce10c1870e47b909357c4de4670` — `fix: fall back to candidate recipient in draft form`

Safe deployment remains:
```bash
cd /home/legacyx/creditos-github && git pull origin main
cd /home/legacyx/creditos-github && ./scripts/deploy-creditos.sh dry-run
cd /home/legacyx/creditos-github && ./scripts/deploy-creditos.sh deploy
```

Manual deployment validation remains authoritative until CI is restored.

## Resume point

When development resumes: **start at Stage 7 Delivery & Tracking QA, then Mail API integration.**
