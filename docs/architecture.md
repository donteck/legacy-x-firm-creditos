# CreditOS Architecture

## Guiding rule

WordPress provides authentication, routing, administration, and the application shell. CreditOS domain logic lives in custom plugins. The theme is presentation only.

## Modules

- `creditos-core`: organizations, clients, roles, permissions, roadmap engine, tasks, audit logs, shared services.
- `creditos-personal`: Personal CreditOS 7-step workflow.
- `creditos-business`: Business CreditOS 7-step workflow.
- Future modules: disputes, AI, automation, integrations, billing.

## Data model strategy

Use dedicated custom database tables for structured/high-volume CreditOS data. Avoid using posts/postmeta as the primary store for credit reports, tradelines, disputes, or audit events.

## Deployment model

```text
GitHub main
   ↓
GitHub Actions / deploy script
   ↓
HestiaCP server
   ↓
WordPress at creditos.legacyxfirm.us
```

A staging site should be added before production automation is enabled.
