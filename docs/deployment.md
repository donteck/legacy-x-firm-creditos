# CreditOS Deployment Notes

## Current Hosting Plan

- Parent site `legacyxfirm.us` remains on ResellerClub hosting.
- CreditOS will run as a separate subdomain on the HestiaCP server.
- Planned production URL: `creditos.legacyxfirm.us`.
- GitHub repository: `donteck/legacy-x-firm-creditos`.
- WordPress will be used for the application shell and administration.
- Core CreditOS logic should live in custom plugins; the theme should control presentation only.

## Current Blocker

HestiaCP currently reports:

> Error: DNS record for creditos.legacyxfirm.us doesn't exist

This means the `creditos` subdomain is not yet pointing to the Hestia server.

## DNS Work To Complete Later

The DNS zone for `legacyxfirm.us` is managed through the current ResellerClub setup.

Create this DNS record where the domain DNS is managed:

- Type: `A`
- Host/Name: `creditos`
- Value: `<HESTIA_PUBLIC_IPV4>`
- TTL: default/automatic

Do not change the existing root-domain, `www`, MX/email, or nameserver records just to add CreditOS.

The desired routing is:

```text
legacyxfirm.us                -> existing ResellerClub website
creditos.legacyxfirm.us       -> HestiaCP server
```

## How To Find The Hestia Public IP

From SSH on the Hestia server:

```bash
curl -4 ifconfig.me
```

Use the returned public IPv4 address as the value of the `creditos` A record.

The public address should not be a private address such as `127.0.0.1`, `10.x.x.x`, or `192.168.x.x`.

## Hestia Setup After DNS Resolves

1. Add `creditos.legacyxfirm.us` as a Web Domain in HestiaCP.
2. Enable SSL / Let's Encrypt.
3. Install WordPress for the CreditOS site.
4. Connect/deploy the GitHub repository.
5. Activate the CreditOS plugins in this order:
   - CreditOS Core
   - CreditOS Personal
   - CreditOS Business
6. Activate the CreditOS WordPress theme.
7. Verify database tables are created successfully.
8. Test the dashboard and both 7-step roadmap modules.

## Recommended Staging Setup

Before production development grows, add a staging environment such as:

`staging-creditos.legacyxfirm.us`

Test significant code changes on staging before promoting them to production.

## Deployment Architecture

```text
GitHub
   |
   v
HestiaCP Server
   |
   v
WordPress
   |
   +-- CreditOS Core
   +-- CreditOS Personal
   +-- CreditOS Business
   +-- CreditOS Theme
   |
   v
creditos.legacyxfirm.us
```

## Next Technical Milestone

Once DNS and WordPress are working, build the interactive CreditOS dashboard and the 7 + 7 roadmap engine before adding deeper bureau integrations, dispute automation, AI, and funding workflows.
