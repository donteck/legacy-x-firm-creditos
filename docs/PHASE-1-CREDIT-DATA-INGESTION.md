# CreditOS Phase 1 — Credit Data Ingestion, Bureau Connections & Report Inspector

_Last updated: 2026-09-03_

## Purpose

Phase 1 establishes the foundation for bringing consumer credit data into CreditOS in a controlled, auditable, provider-neutral way.

The client must be able to choose between two entry paths from the start:

1. **Upload My Report** — PDF, JSON, or CSV.
2. **Connect My Credit Data** — Experian, Equifax, TransUnion, or an approved 3-bureau provider.

Both paths must feed the same CreditOS Credit Data Gateway and normalized data model.

---

## End-to-End Phase 1 Flow

```text
CLIENT CREATES / SIGNS INTO CREDITOS
        |
        v
IDENTITY + CONSENT + ONBOARDING
        |
        v
CHOOSE CREDIT DATA METHOD
   |                         |
   |                         |
   v                         v
UPLOAD REPORT            CONNECT CREDIT DATA
PDF / JSON / CSV         Experian / Equifax /
                         TransUnion / 3-Bureau Provider
   |                         |
   +-----------+-------------+
               |
               v
      CREDIT DATA GATEWAY
               |
               v
      PARSING + NORMALIZATION
               |
               v
      CREDITOS REPORT INSPECTOR
               |
               v
      CLIENT / STAFF VERIFICATION
               |
               v
      REPORT HISTORY + VERSIONS
               |
               v
      READY FOR PHASE 2
      3-BUREAU INTELLIGENCE
```

---

## Phase 1A — Secure Report Import

### Current production foundation

CreditOS Core supports authenticated report imports through:

- `POST /wp-json/creditos/v1/reports/import`
- `GET /wp-json/creditos/v1/reports`
- `GET /wp-json/creditos/v1/reports/{id}`
- `POST /wp-json/creditos/v1/reports/{id}/normalized`

Current accepted formats:

- PDF
- JSON
- CSV

Current size limit:

- 25 MB per upload

Current source choices:

- 3-Bureau / Combined Report
- Experian
- Equifax
- TransUnion

### Current normalized domains

CreditOS stores structured records for:

- Tradelines
- Collections
- Inquiries
- Personal information

### Important security limitation

Current WordPress media uploads are marked with `_creditos_private = 1`, but this metadata alone does **not** guarantee that the underlying file cannot be accessed directly by URL. Production hardening must move sensitive reports to truly protected storage with authenticated delivery, access logging, encryption strategy, and retention controls.

---

## Phase 1B — Parser & Normalization Engine

### Goal

Convert imported report data into the normalized CreditOS schema so downstream modules do not depend on the original report format or provider.

### Parser adapter architecture

Recommended interface:

```text
CreditOS_Report_Parser_Interface
    |- CreditOS_JSON_Parser
    |- CreditOS_CSV_Parser
    |- CreditOS_PDF_Parser
    |- Future Provider Payload Parsers
```

Each parser should return the same normalized payload structure.

### Parsing strategy

1. Prefer direct text / structured extraction from PDFs.
2. Use provider-specific layout adapters where needed.
3. Avoid OCR unless the PDF truly contains only image data.
4. Validate extracted values before saving.
5. Store parser status and parser errors.
6. Preserve source attribution to the original bureau/report/provider.

### Required normalized fields

#### Tradelines

- Bureau
- Creditor name
- Masked account number
- Account type
- Open date
- Balance
- Credit limit
- Past due
- Payment status
- Account status
- Date reported
- Responsibility
- Remarks

#### Collections

- Bureau
- Collector
- Original creditor
- Balance
- Assigned date
- Status

#### Inquiries

- Bureau
- Creditor
- Inquiry type
- Inquiry date

#### Personal information

- Bureau
- Information type
- Reported value
- Status

### Phase 1B remaining work

- Production-grade PDF parser
- CSV parser and mapping UI
- Parser job queue
- Parser error handling
- Duplicate report detection
- Validation and correction workflow
- Report source versioning
- Tests

---

## Phase 1C — CreditOS Report Inspector™

The CreditOS Report Inspector is the interactive workspace for inspecting the actual credit report and the structured data CreditOS extracted from it.

### Product principle

The report should not simply disappear into an automated analysis engine. The client and authorized staff should be able to inspect, verify, understand, and act on the data.

### Desired layout

```text
+--------------------------------------------------------------+
| CREDITOS REPORT INSPECTOR                                    |
+----------------------------+---------------------------------+
| Original Report Viewer     | Structured Credit Data          |
| PDF / provider rendering   | Account details                 |
|                            | Bureau source                    |
| Highlighted report area    | Review flags                    |
|                            | AI explanation                  |
+----------------------------+---------------------------------+
| Experian | Equifax | TransUnion | Combined                   |
+--------------------------------------------------------------+
```

### Inspector capabilities

- Interactive original report viewer
- Side-by-side structured data
- Bureau tabs: Experian, Equifax, TransUnion, Combined
- Account-level inspection
- Collections inspection
- Inquiry inspection
- Personal information inspection
- Click-to-highlight source area when technically available
- Report source attribution
- Review status
- Staff/client notes
- Evidence attachments
- Audit history
- Create review/dispute workflow from an item
- AI explanation in plain language
- Future legal-review handoff after a factual issue has been identified

### Suggested item review states

- Verified
- Needs Review
- Potential Inaccuracy
- Missing Evidence
- Client Confirmation Needed
- Staff Review Needed
- Ignore / No Action

### Real-time meaning in CreditOS

CreditOS should support two forms of real-time inspection:

1. **Interactive real-time inspection after import** — as parsing completes, the client can inspect the report and normalized data immediately.
2. **Live provider refresh later** — once authorized bureau/provider integrations are approved and configured, refreshed data can flow into the same Inspector without requiring another PDF upload.

---

## Phase 1D — Verification & Corrections

Before Phase 2 analysis, the client or authorized specialist must be able to verify the imported data.

Required functions:

- Edit incorrectly parsed values
- Confirm account matching
- Confirm source bureau
- Correct dates and balances
- Mark parser confidence/review status
- Add staff/client notes
- Preserve original extracted value when edited
- Audit every change

No correction should overwrite the audit history of the original import.

---

## Phase 1E — Report History & Versioning

Each report import or provider refresh should create a historical snapshot.

Track:

- Report ID
- Client ID
- Source bureau/provider
- Source type: upload or connection
- Report date
- Import/sync date
- File/payload format
- Parser status
- Version
- Checksum or provider reference
- Processing errors
- User who initiated import
- Current review state

This history will later support change detection and monitoring.

---

## Phase 1F — Handoff to Phase 2

A report is considered Phase-2-ready only when:

- Data has been imported or received from an authorized provider
- Parsing/normalization completed
- Required validation passed
- Client/staff review is complete enough for comparison
- Bureau attribution is known
- Report version is preserved

Phase 2 will then perform:

- 3-bureau account matching
- Bureau-to-bureau comparisons
- Discrepancy flags
- Utilization calculations
- Issue prioritization
- Next Best Action recommendations

A discrepancy is a **review flag**, not an automatic dispute.

---

## Credit Bureau / Provider Connection Option

The client experience must show the connection option from the beginning, even before production bureau APIs are enabled.

### Client choices

- Connect Experian
- Connect Equifax
- Connect TransUnion
- Connect an approved 3-bureau provider

### Provider-neutral architecture

```text
CreditOS Credit Data Connection Gateway
    |
    +-- Experian Adapter
    +-- Equifax Adapter
    +-- TransUnion Adapter
    +-- 3-Bureau Provider Adapter
    +-- Future Approved Provider Adapter
```

### Connection status lifecycle

Suggested statuses:

- Not Connected
- Provider Setup Required
- Authorization Required
- Connecting
- Connected
- Syncing
- Synced
- Action Required
- Expired
- Disconnected
- Failed

### Connection record should store

- Client ID
- Provider
- Bureau
- Connection status
- Consent status
- Authorization timestamp
- Provider customer/reference ID
- Last sync time
- Last successful sync time
- Error state
- Created/updated timestamps
- Audit trail

### Important compliance rule

CreditOS must **not** scrape bureau portals or automatically log into AnnualCreditReport.com or other bureau consumer portals using a client's credentials.

Production connections must use authorized access, approved providers, appropriate consumer authorization, permissible-purpose controls where applicable, secure credentials/tokens, and provider-required onboarding/certification.

Until a provider is enabled, connection buttons should clearly display **Provider Setup Required** rather than pretending the connection is live.

---

## Unified Credit Data Gateway

Whether data comes from a file or an API/provider connection, it must enter through the same abstraction layer.

```text
PDF / CSV / JSON
       |
       v
File Import Adapter ----+
                        |
Experian Adapter -------|
Equifax Adapter --------|--> CreditOS Credit Data Gateway
TransUnion Adapter -----|            |
3-Bureau Adapter -------+            v
                              Normalized CreditOS Data Model
```

Gateway responsibilities:

- Authorization and consent checks
- Provider authentication
- Source attribution
- Input validation
- Schema mapping
- Normalization
- Duplicate detection
- Report versioning
- Error handling
- Account matching preparation
- Import/sync audit logging

---

## Human Review Rule

CreditOS must not automatically treat every detected difference or negative item as a dispute.

The safe workflow is:

```text
Data received
  -> normalized
  -> inspected
  -> potential issue identified
  -> facts/evidence reviewed
  -> client/staff approval
  -> downstream dispute/legal workflow when appropriate
```

---

## Phase 1 Production Checklist

- [x] Credit Report Import Center page
- [x] PDF/JSON/CSV upload option
- [x] Bureau/source selector
- [x] Core report database tables
- [x] Normalized tradeline/collection/inquiry/personal-info tables
- [x] Report history endpoint
- [x] JSON normalization foundation
- [x] Connection option designed from the start
- [x] Provider-neutral credit data gateway foundation
- [x] Experian / Equifax / TransUnion / 3-bureau connection choices
- [ ] Production provider credentials and approvals
- [ ] Experian production adapter
- [ ] Equifax production adapter
- [ ] TransUnion production adapter
- [ ] Approved 3-bureau production adapter
- [ ] Production PDF parser
- [ ] CSV mapping engine
- [ ] Private/encrypted report storage hardening
- [ ] Malware scanning
- [ ] Background parser queue
- [ ] Report Inspector UI
- [ ] Click-to-highlight source mapping
- [ ] Verification/correction workflow
- [ ] Report versioning UI
- [ ] Security and acceptance tests

---

## Next Build Sequence

```text
1B — Parser & Normalization Engine
1C — CreditOS Report Inspector™
1D — Verification & Corrections
1E — Report History & Versioning
1F — Phase 2 Readiness
```

The bureau connection gateway remains part of Phase 1 from the start, while actual production bureau/provider activation depends on approved access and credentials.

---

## Product Rule

**Every credit data source should enter CreditOS through one controlled gateway, become one normalized data model, remain traceable to its source, and be inspectable by the client or authorized staff before high-impact action is taken.**
