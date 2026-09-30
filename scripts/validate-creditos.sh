#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "======================================"
echo " CreditOS Pre-Deployment Validation"
echo "======================================"

echo
echo "[1/3] Checking PHP..."

while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null || {
        echo "FAILED PHP: $file"
        exit 1
    }
done < <(find wp-content/themes/creditos wp-content/plugins/creditos-core \
    -type f -name '*.php' -print0)

echo "✓ PHP syntax valid"

echo
echo "[2/3] Checking JavaScript..."

while IFS= read -r -d '' file; do
    node --check "$file" >/dev/null || {
        echo "FAILED JS: $file"
        exit 1
    }
done < <(find wp-content/themes/creditos wp-content/plugins/creditos-core \
    -type f -name '*.js' -print0)

echo "✓ JavaScript syntax valid"

echo
echo "[3/3] Repository status..."
git rev-parse --short HEAD
git status --short

echo
echo "======================================"
echo "✓ CREDITOS VALIDATION PASSED"
echo "======================================"
