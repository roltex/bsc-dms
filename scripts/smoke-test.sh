#!/usr/bin/env bash
# Post-deploy smoke tests for EFES DMS on Azure.
set -euo pipefail

BASE_URL="${1:-https://app-efes-dms-prod.azurewebsites.net}"
BASE_URL="${BASE_URL%/}"

pass=0
fail=0

check() {
  local name="$1"
  local url="$2"
  local expect="${3:-200}"
  local method="${4:-GET}"
  local status
  if [ "$method" = "POST" ]; then
    status=$(curl -sS -o /tmp/smoke-body.json -w "%{http_code}" -X POST "$url" -H "Accept: application/json" -H "Content-Type: application/json" || echo "000")
  else
    status=$(curl -sS -o /tmp/smoke-body.json -w "%{http_code}" "$url" || echo "000")
  fi
  if [ "$status" = "$expect" ]; then
    echo "PASS  $name ($status)"
    pass=$((pass + 1))
  else
    echo "FAIL  $name (expected $expect, got $status)"
    fail=$((fail + 1))
  fi
}

echo "Smoke tests against $BASE_URL"
echo "---"

check "Health API" "$BASE_URL/api/health" 200
check "Laravel up" "$BASE_URL/up" 200
check "SPA index" "$BASE_URL/" 200
check "Login API (422 without body)" "$BASE_URL/api/login" 422 POST

echo "---"
echo "Passed: $pass  Failed: $fail"

if [ -f /tmp/smoke-body.json ]; then
  echo "Health response:"
  cat /tmp/smoke-body.json
  echo ""
fi

if [ "$fail" -gt 0 ]; then
  exit 1
fi

echo "All smoke tests passed."
