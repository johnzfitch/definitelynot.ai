#!/bin/bash
set -euo pipefail

API_URL=${API_URL:-"http://localhost/api/clean.php"}
PASS=0
FAIL=0

echo "🚀 Cosmic Text Linter - Smoke Test v2.2.1"
echo "=========================================="

run_test() {
  local name="$1"
  local payload="$2"
  local expect="$3"
  echo -n "$name... "
  local response
  if ! response=$(curl -sS -X POST "$API_URL" -H 'Content-Type: application/json' -d "$payload"); then
    echo "✗ CURL ERROR"
    ((FAIL++))
    return
  fi
  if echo "$response" | grep -q "$expect"; then
    echo "✓ PASS"
    ((PASS++))
  else
    echo "✗ FAIL"
    echo "    Response: $response"
    ((FAIL++))
  fi
}

run_test "Test 1: Zero-width removal" '{"text":"Hello\u200Bworld","mode":"safe"}' '"invisibles_removed":'
run_test "Test 2: Mode selection" '{"text":"Test","mode":"aggressive"}' '"mode":"aggressive"'
run_test "Test 3: Empty input rejection" '{"text":"","mode":"safe"}' '"error"'
run_test "Test 4: Smart quote normalization" '{"text":"\u201cHello\u201d","mode":"safe"}' '"text": "\"Hello\"'
run_test "Test 5: Advisory detection" '{"text":"Hello\u200Bworld","mode":"safe"}' '"had_default_ignorables": true'

echo ""
echo "=========================================="
echo "Results: $PASS passed, $FAIL failed"
if [ "$FAIL" -eq 0 ]; then
  echo "🎉 All tests passed!"
  exit 0
else
  echo "⚠️  Some tests failed"
  exit 1
fi
