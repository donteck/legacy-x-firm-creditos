#!/usr/bin/env bash
set -euo pipefail

: "${HESTIA_HOST:?HESTIA_HOST is required}"
: "${HESTIA_USER:?HESTIA_USER is required}"
: "${HESTIA_PATH:?HESTIA_PATH is required}"

PORT="${HESTIA_PORT:-22}"
SSH_KEY="${SSH_KEY_PATH:-$HOME/.ssh/creditos_hestia}"
REMOTE="${HESTIA_USER}@${HESTIA_HOST}"

if [[ "$HESTIA_PATH" != */public_html ]]; then
  echo "Refusing deployment: HESTIA_PATH must end with /public_html"
  exit 1
fi

SSH=(ssh -i "$SSH_KEY" -p "$PORT" -o BatchMode=yes)
RSYNC_SSH="ssh -i $SSH_KEY -p $PORT -o BatchMode=yes"

printf 'Checking remote WordPress path...\n'
"${SSH[@]}" "$REMOTE" "test -d '$HESTIA_PATH/wp-content' || { echo 'Remote wp-content directory not found'; exit 1; }"

printf 'Creating CreditOS destination directories...\n'
"${SSH[@]}" "$REMOTE" "mkdir -p '$HESTIA_PATH/wp-content/themes/creditos' '$HESTIA_PATH/wp-content/plugins/creditos-core' '$HESTIA_PATH/wp-content/plugins/creditos-personal' '$HESTIA_PATH/wp-content/plugins/creditos-business'"

printf 'Deploying CreditOS theme...\n'
rsync -az --delete \
  -e "$RSYNC_SSH" \
  wp-content/themes/creditos/ \
  "$REMOTE:$HESTIA_PATH/wp-content/themes/creditos/"

printf 'Deploying CreditOS Core plugin...\n'
rsync -az --delete \
  -e "$RSYNC_SSH" \
  wp-content/plugins/creditos-core/ \
  "$REMOTE:$HESTIA_PATH/wp-content/plugins/creditos-core/"

printf 'Deploying CreditOS Personal plugin...\n'
rsync -az --delete \
  -e "$RSYNC_SSH" \
  wp-content/plugins/creditos-personal/ \
  "$REMOTE:$HESTIA_PATH/wp-content/plugins/creditos-personal/"

printf 'Deploying CreditOS Business plugin...\n'
rsync -az --delete \
  -e "$RSYNC_SSH" \
  wp-content/plugins/creditos-business/ \
  "$REMOTE:$HESTIA_PATH/wp-content/plugins/creditos-business/"

printf 'Verifying deployed files...\n'
"${SSH[@]}" "$REMOTE" "test -f '$HESTIA_PATH/wp-content/themes/creditos/style.css' && test -f '$HESTIA_PATH/wp-content/plugins/creditos-core/creditos-core.php'"

printf 'CreditOS deployment completed successfully.\n'
