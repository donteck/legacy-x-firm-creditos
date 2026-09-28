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

printf 'Checking private report storage prerequisites...\n'
"${SSH[@]}" "$REMOTE" "UPLOADS='$HESTIA_PATH/wp-content/uploads'; test -d \"\$UPLOADS\" || mkdir -p \"\$UPLOADS\"; test -w \"\$UPLOADS\" || { echo 'WordPress uploads directory is not writable'; exit 1; }"

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

printf 'Verifying report-storage filesystem policy...\n'
"${SSH[@]}" "$REMOTE" "test -w '$HESTIA_PATH/wp-content/uploads' || { echo 'CreditOS report storage verification failed'; exit 1; }"

printf 'Auditing web-server report-source isolation...\n'
"${SSH[@]}" "$REMOTE" "if command -v nginx >/dev/null 2>&1; then echo 'nginx detected; direct static upload isolation requires an explicit Hestia/nginx deny rule or non-public report storage'; fi; if command -v apache2 >/dev/null 2>&1 || command -v httpd >/dev/null 2>&1; then echo 'Apache detected; verify that upstream nginx cannot bypass any Apache-only protection'; fi"

printf 'Checking for an explicit CreditOS report isolation marker...\n'
"${SSH[@]}" "$REMOTE" "MARKER='$HESTIA_PATH/../.creditos-report-isolation-verified'; if test -f \"\$MARKER\"; then echo 'CreditOS report isolation marker present'; else echo 'WARNING: CreditOS direct static report isolation has not been verified on this host'; fi"

printf 'Auditing public upload-tree exposure for CreditOS report artifacts...\n'
"${SSH[@]}" "$REMOTE" "FOUND=0; for F in \$(find '$HESTIA_PATH/wp-content/uploads' -type f -perm 0600 2>/dev/null | head -n 20); do FOUND=1; case \"\$F\" in '$HESTIA_PATH/wp-content/uploads/'*) ;; *) echo 'ERROR: unexpected report candidate path'; exit 1;; esac; done; if test \"\$FOUND\" = 1; then echo 'Private-permission upload candidates exist under the public web root; server-level denial/non-public storage is still required'; else echo 'No 0600 upload candidates found during deployment audit'; fi"

printf 'Provisioning CreditOS private report storage outside public_html...\n'
"${SSH[@]}" "$REMOTE" "PRIVATE_ROOT=\$(dirname '$HESTIA_PATH')/creditos-private; case \"\$PRIVATE_ROOT\" in '$HESTIA_PATH'/*) echo 'ERROR: private storage resolves beneath public_html'; exit 1;; esac; mkdir -p \"\$PRIVATE_ROOT/reports\" || { echo 'ERROR: unable to provision CreditOS private storage'; exit 1; }; chmod 700 \"\$PRIVATE_ROOT\" \"\$PRIVATE_ROOT/reports\" || { echo 'ERROR: unable to secure CreditOS private storage'; exit 1; }; test -w \"\$PRIVATE_ROOT/reports\" || { echo 'ERROR: CreditOS private report storage is not writable'; exit 1; }; echo 'CreditOS non-public report storage is provisioned and writable'"

printf 'CreditOS deployment completed successfully.\n'
