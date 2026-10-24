#!/usr/bin/env bash
# Sends one signed request to the ShopMate partner API, like a retailer's system would.
#
# Usage:
#   PARTNER_KEY=pk_... PARTNER_SECRET=sk_... scripts/partner-request.sh GET  http://127.0.0.1/api/partner/v1/offers
#   PARTNER_KEY=pk_... PARTNER_SECRET=sk_... scripts/partner-request.sh PUT  http://127.0.0.1/api/partner/v1/offers '{"offers":[{"product":"some-slug","price":18999}]}'
#
# Optional: HOST_HEADER=shopmate.1-2-3-4.sslip.io when calling 127.0.0.1 on the server itself.
#
# Signed text (5 lines, joined with \n): METHOD, PATH (+query), TIMESTAMP, NONCE, sha256(body)
# Signature: hex HMAC-SHA256 of that text with the partner secret.
set -euo pipefail

METHOD="${1:?method (GET or PUT)}"
URL="${2:?full URL}"
BODY="${3:-}"
: "${PARTNER_KEY:?set PARTNER_KEY}"
: "${PARTNER_SECRET:?set PARTNER_SECRET}"

# Path plus query string, exactly as it will be sent.
PATH_AND_QUERY="/${URL#*://*/}"
TIMESTAMP="$(date +%s)"
NONCE="$(openssl rand -hex 16)"
BODY_HASH="$(printf '%s' "$BODY" | openssl dgst -sha256 -hex | awk '{print $NF}')"
CANONICAL="$(printf '%s\n%s\n%s\n%s\n%s' "$METHOD" "$PATH_AND_QUERY" "$TIMESTAMP" "$NONCE" "$BODY_HASH")"
SIGNATURE="$(printf '%s' "$CANONICAL" | openssl dgst -sha256 -hmac "$PARTNER_SECRET" -hex | awk '{print $NF}')"

ARGS=(-sS -X "$METHOD" "$URL"
  -H "Accept: application/json"
  -H "X-Partner-Key: $PARTNER_KEY"
  -H "X-Partner-Timestamp: $TIMESTAMP"
  -H "X-Partner-Nonce: $NONCE"
  -H "X-Partner-Signature: $SIGNATURE"
  -w '\nHTTP %{http_code}\n')
if [ -n "${HOST_HEADER:-}" ]; then ARGS+=(-H "Host: $HOST_HEADER"); fi
if [ -n "$BODY" ]; then ARGS+=(-H "Content-Type: application/json" --data-binary "$BODY"); fi

curl "${ARGS[@]}"
