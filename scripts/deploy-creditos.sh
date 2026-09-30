#!/usr/bin/env bash
set -euo pipefail

REPO="/home/legacyx/creditos-github"
LIVE="/home/legacyx/web/creditos.legacyxfirm.us/public_html"
BACKUPS="/home/legacyx/creditos-deploy-backups"

MODE="${1:-dry-run}"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$BACKUPS/$STAMP"

cd "$REPO"

echo "======================================"
echo " CreditOS Safe Deployment"
echo " Commit: $(git rev-parse --short HEAD)"
echo " Mode:   $MODE"
echo "======================================"

echo
echo "[1/5] Running validation..."
"$REPO/scripts/validate-creditos.sh"

if [[ "$MODE" == "dry-run" ]]; then
    echo
    echo "[2/5] DRY RUN — Theme changes:"
    rsync -avnc --delete \
      "$REPO/wp-content/themes/creditos/" \
      "$LIVE/wp-content/themes/creditos/"

    echo
    echo "[3/5] DRY RUN — Plugin changes:"
    rsync -avnc --delete \
      --exclude='vendor/' \
      --exclude='composer.lock' \
      "$REPO/wp-content/plugins/creditos-core/" \
      "$LIVE/wp-content/plugins/creditos-core/"

    echo
    echo "======================================"
    echo "✓ DRY RUN COMPLETE"
    echo "Nothing was changed on production."
    echo "======================================"
    exit 0
fi

if [[ "$MODE" != "deploy" ]]; then
    echo "Usage:"
    echo "  $0 dry-run"
    echo "  $0 deploy"
    exit 1
fi

echo
echo "[2/5] Creating production backup..."
mkdir -p "$BACKUP/wp-content/themes"
mkdir -p "$BACKUP/wp-content/plugins"

cp -a "$LIVE/wp-content/themes/creditos" \
      "$BACKUP/wp-content/themes/"

cp -a "$LIVE/wp-content/plugins/creditos-core" \
      "$BACKUP/wp-content/plugins/"

echo "✓ Backup: $BACKUP"

echo
echo "[3/5] Deploying..."
rsync -av --delete \
  "$REPO/wp-content/themes/creditos/" \
  "$LIVE/wp-content/themes/creditos/"

rsync -av --delete \
  --exclude='vendor/' \
  --exclude='composer.lock' \
  "$REPO/wp-content/plugins/creditos-core/" \
  "$LIVE/wp-content/plugins/creditos-core/"

echo
echo "[4/5] Validating production..."

find "$LIVE/wp-content/themes/creditos" \
     "$LIVE/wp-content/plugins/creditos-core" \
     -type f -name '*.php' -print0 |
while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
done

find "$LIVE/wp-content/themes/creditos" \
     "$LIVE/wp-content/plugins/creditos-core" \
     -type f -name '*.js' -print0 |
while IFS= read -r -d '' file; do
    node --check "$file" >/dev/null
done

cd "$LIVE"
wp core is-installed
wp cache flush

echo
echo "[5/5] Checking website..."
HTTP="$(curl -L -s -o /dev/null -w '%{http_code}' \
'https://creditos.legacyxfirm.us/account-inspector/?report=3&tradeline=1261')"

if [[ "$HTTP" != "200" ]]; then
    echo "Health check FAILED: HTTP $HTTP"
    echo "Rolling back..."

    rm -rf "$LIVE/wp-content/themes/creditos"
    rm -rf "$LIVE/wp-content/plugins/creditos-core"

    cp -a "$BACKUP/wp-content/themes/creditos" \
          "$LIVE/wp-content/themes/"

    cp -a "$BACKUP/wp-content/plugins/creditos-core" \
          "$LIVE/wp-content/plugins/"

    wp cache flush

    echo "✓ Rollback completed"
    exit 1
fi

echo
echo "======================================"
echo "✓ CREDITOS DEPLOYMENT SUCCESSFUL"
echo "HTTP: $HTTP"
echo "Backup: $BACKUP"
echo "======================================"
