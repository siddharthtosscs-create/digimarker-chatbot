#!/bin/bash
# DigiMarker Chat API Test Script
# Usage: ./test_api.sh https://yourdomain.com

DOMAIN="${1:-http://localhost}"
API_URL="${DOMAIN}/api/chat.php"
DIAGNOSTIC_URL="${DOMAIN}/api/diagnostic.php"

echo "=========================================="
echo "DigiMarker Chat API Test"
echo "=========================================="
echo "Domain: $DOMAIN"
echo ""

# Test 1: Diagnostic Endpoint
echo "Test 1: Diagnostic Endpoint"
echo "URL: $DIAGNOSTIC_URL"
echo "----------------------------------------"
curl -s "$DIAGNOSTIC_URL" | python3 -m json.tool 2>/dev/null || curl -s "$DIAGNOSTIC_URL"
echo ""
echo ""

# Test 2: API Health Check (OPTIONS)
echo "Test 2: CORS Preflight (OPTIONS)"
echo "URL: $API_URL"
echo "----------------------------------------"
curl -s -X OPTIONS "$API_URL" -H "Origin: $DOMAIN" -v 2>&1 | grep -E "(< HTTP|< Access-Control)"
echo ""
echo ""

# Test 3: API Request
echo "Test 3: API Request (POST)"
echo "URL: $API_URL"
echo "Payload: {\"question\":\"What is DigiMarker?\"}"
echo "----------------------------------------"
RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -H "Origin: $DOMAIN" \
  -d '{"question":"What is DigiMarker?"}')

echo "$RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$RESPONSE"
echo ""
echo ""

# Test 4: Error Handling
echo "Test 4: Error Handling (Empty Question)"
echo "URL: $API_URL"
echo "Payload: {\"question\":\"\"}"
echo "----------------------------------------"
ERROR_RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{"question":""}')

echo "$ERROR_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$ERROR_RESPONSE"
echo ""
echo ""

echo "=========================================="
echo "Test Complete"
echo "=========================================="

