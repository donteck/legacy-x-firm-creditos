# CreditOS Deployment Notes

## Current Hosting Plan

- Parent site `legacyxfirm.us` remains on ResellerClub hosting.
- CreditOS will run as a separate subdomain on the HestiaCP server.
- Planned production URL: `creditos.legacyxfirm.us`.
- GitHub repository: `donteck/legacy-x-firm-creditos`.
- WordPress is the application shell and administration layer.
- Core CreditOS logic lives in custom plugins; the theme controls presentation.

## GitHub → Hestia Connection

The repository now includes an automated production deployment workflow:

- `.github/workflows/deploy-hestia.yml`
- `scripts/deploy-hestia.sh`

The deployment validates PHP syntax, tests the SSH connection, and then deploys only the CreditOS theme and CreditOS plugins. It does not overwrite WordPress core or unrelated plugins/themes.

### Required GitHub Actions Secrets

Create these repository secrets in GitHub under **Settings → Secrets and variables → Actions**:

- `HESTIA_HOST` — Hestia server public IPv4 address or resolvable hostname.
- `HESTIA_USER` — the Hestia account that owns `creditos.legacyxfirm.us`.
- `HESTIA_SSH_KEY` — the private SSH key whose public key is authorized for that Hestia user.
- `HESTIA_PORT` — SSH port, normally `22` unless your server uses another port.
- `HESTIA_PATH` — exact WordPress document root for CreditOS, for example `/home/<HESTIA_USER>/web/creditos.legacyxfirm.us/public_html`.

The deploy script refuses to deploy unless `HESTIA_PATH` ends in `/public_html` and contains an existing `wp-content` directory.

### Recommended SSH Key Setup

Run this while logged in as the Hestia user that owns the CreditOS web domain:

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -t ed25519 -C "github-actions-creditos" -f ~/.ssh/github-actions-creditos -N ""
cat ~/.ssh/github-actions-creditos.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
cat ~/.ssh/github-actions-creditos
```

Copy the complete private key shown by the final command into the GitHub repository secret `HESTIA_SSH_KEY`. Do not commit the private key to the repository.

### Test the Server Path

On Hestia, confirm the CreditOS WordPress path before enabling deployment:

```bash
ls -la /home/<HESTIA_USER>/web/creditos.legacyxfirm.us/public_html
ls -la /home/<HESTIA_USER>/web/creditos.legacyxfirm.us/public_html/wp-content
```

Use that exact `public_html` path for the `HESTIA_PATH` GitHub secret.

### Deployment Behavior

A deployment is triggered when CreditOS files change on `main`, or manually from **GitHub → Actions → Deploy CreditOS to Hestia → Run workflow**.

The workflow performs these stages:

1. Checkout `main`.
2. Run `php -l` against all tracked PHP files in `wp-content`.
3. Configure the private SSH key securely on the runner.
4. Add the Hestia server host key to `known_hosts`.
5. Test passwordless SSH.
6. Verify the remote WordPress `wp-content` directory exists.
7. Deploy `wp-content/themes/creditos/`.
8. Deploy `creditos-core`, `creditos-personal`, and `creditos-business` plugins.
9. Verify the deployed theme and Core plugin files exist.

`rsync --delete` is scoped only to the four CreditOS destinations, so files in unrelated WordPress folders are not touched.

## DNS

The DNS zone for `legacyxfirm.us` is managed through the current ResellerClub setup.

Create this record where the domain DNS is managed:

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

To find the Hestia public IP from SSH:

```bash
curl -4 ifconfig.me
```

The address should not be a private address such as `127.0.0.1`, `10.x.x.x`, or `192.168.x.x`.

## Hestia Setup

1. Add `creditos.legacyxfirm.us` as a Web Domain in HestiaCP.
2. Enable SSL / Let's Encrypt after DNS resolves.
3. Install WordPress for CreditOS.
4. Confirm the exact `public_html` path.
5. Add the GitHub Actions SSH public key to the owning Hestia user's `~/.ssh/authorized_keys`.
6. Add all five GitHub repository secrets listed above.
7. Run the `Deploy CreditOS to Hestia` workflow manually for the first deployment.
8. Activate the CreditOS plugins in this order:
   - CreditOS Core
   - CreditOS Personal
   - CreditOS Business
9. Activate the CreditOS theme.
10. Verify database tables are created successfully.
11. Test the front page, onboarding, dashboard, links, roles, tasks, disputes, and mobile navigation.

## Recommended Staging Setup

Before production development grows further, add a staging environment such as:

`staging-creditos.legacyxfirm.us`

Test significant code changes on staging before promoting them to production.

## Deployment Architecture

```text
GitHub main
   |
   v
GitHub Actions
   |
   | SSH + rsync
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

## Current Deployment Gate

The GitHub side of the Hestia connection is prepared. The first live deployment requires the real Hestia host, owning user, authorized SSH key, SSH port, and exact WordPress `public_html` path to be added as GitHub Actions secrets.
