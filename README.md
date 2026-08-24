# Legacy X Firm Credit Operating Solutions (CreditOS)

**CreditOS** is the WordPress-based personal and business credit technology platform of Legacy X Firm.

> Personal & Business Credit Intelligence, Management & Automation.

## Platform

- Production: `creditos.legacyxfirm.us`
- Hosting: HestiaCP
- CMS / Application shell: WordPress
- Source control: GitHub
- Database: MariaDB / MySQL

## Product model

CreditOS is built around the **7 + 7 Method**:

### Personal CreditOS
1. Credit Foundation
2. 3-Bureau Credit Analysis
3. Credit Accuracy & Health Review
4. Dispute & Correction
5. Credit Optimization
6. Credit Building & Strengthening
7. Personal Funding Readiness

### Business CreditOS
1. Business Foundation
2. Business Fundability
3. Business Credit Bureau Setup
4. Vendor Credit / Net Terms
5. Revolving Business Credit
6. Business Credit Strengthening
7. Business Funding Readiness

## Architecture

Core business logic belongs in custom plugins. Presentation belongs in the CreditOS theme.

```text
wp-content/
├── plugins/
│   ├── creditos-core/
│   ├── creditos-personal/
│   └── creditos-business/
└── themes/
    └── creditos/
```

## Development phases

1. CreditOS Core
2. 7 + 7 Roadmap Engine
3. Personal CreditOS
4. Business CreditOS
5. Credit Data Engine
6. Dispute Center
7. CreditOS AI
8. Funding Readiness
9. Automation
10. Billing / SaaS expansion

## Security principle

CreditOS handles sensitive financial information. Security, permissions, audit logging, authorization, and compliance controls are treated as first-class product requirements.
