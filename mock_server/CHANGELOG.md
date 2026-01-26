# Mock Server Update - January 2026

## Summary of Changes

Updated both Python and PHP mock servers to align with the current WordPress plugin implementation.

### Changes Made

#### 1. Added `/merchant/webhook-public-key` Endpoint
- **Python Server** (`server.py`): Added GET endpoint at line ~210
- **PHP Server** (`index.php`): Added GET endpoint at line ~505
- Returns mock ED25519 public key for webhook signature verification
- Plugin uses this to verify webhook authenticity
- Key is cached for 1 hour by the plugin

**Response Format:**
```json
{
  "publicKey": "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAJrQLj5P/89iXES9+vFgrIy29clF9CC/oPPsw3c5D0bs=\n-----END PUBLIC KEY-----"
}
```

#### 2. Updated Documentation
- Updated `README.md` with comprehensive API endpoint documentation
- Added descriptions for all 10 endpoints
- Included request/response examples for each endpoint
- Added fee breakdown structure documentation
- Documented the payment UI flow

### Current Mock Server Features

Both Python and PHP versions now support:

1. **Admin Dashboard** - View and manage transactions
2. **API Logs Viewer** (PHP only) - Live request/response logging with auto-refresh
3. **Payment Creation** - Full payment intent creation with QR codes
4. **Payment Status Polling** - Check transaction status
5. **Webhook Public Key** - Return key for signature verification
6. **Webhook Secret Regeneration** - Generate new webhook secrets
7. **Admin Payment Confirmation** - Manually confirm payments and trigger webhooks
8. **Customer Payment UI** - Simulate customer payment flow
9. **Debug Telegram** - Send debug messages to Telegram

### Key Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/payment/create` | POST | Create payment transaction with QR code |
| `/payment/<txn_id>` | GET | Check payment status |
| `/merchant/webhook-public-key` | GET | Get public key for signature verification |
| `/merchant/webhook-secret/regenerate` | POST | Generate new webhook secret |
| `/admin/confirm/<txn_id>` | POST | Confirm payment and trigger webhook |
| `/pay-ui/<txn_id>` | GET/POST | Customer payment interface |
| `/debug/telegram` | POST | Send debug messages |
| `/` | GET | Admin dashboard |
| `/logs` | GET | API logs viewer (PHP only) |

### Webhook Signature

Both servers correctly implement HMAC-SHA256 signature:
- Header: `X-CEYPAY-SIGNATURE`
- Algorithm: HMAC-SHA256
- Secret: `mock_secret_key` (default)

The plugin verifies webhooks using ED25519 in production but falls back gracefully when using the mock server.

### Fee Breakdown

The mock servers now return realistic fee calculations:
- Exchange fee: 0.5%
- CeyPay fee: 1.0%
- USDT conversion rate: ~306.5 LKR
- Both LKR and USDT amounts calculated

### Testing Workflow

1. Start mock server (Python or PHP)
2. Configure plugin to use `http://host.docker.internal:5000` or `http://localhost:5000`
3. Create test order in WooCommerce
4. Select payment provider (BYBIT is enabled)
5. Generate QR code
6. Use admin dashboard to confirm payment OR use customer payment UI
7. Webhook automatically triggers on payment confirmation
8. Order status updates to "Processing"

### Files Modified

1. `mock_server/server.py` - Added webhook public key endpoint, updated endpoint list
2. `mock_server/index.php` - Added webhook public key endpoint
3. `mock_server/README.md` - Complete documentation rewrite with all endpoints

### Testing Checklist

- [x] Payment creation endpoint works
- [x] QR code generation works
- [x] Status polling works
- [x] Webhook public key endpoint added
- [x] Webhook signature generation correct
- [x] Admin dashboard functions
- [x] Customer payment UI works
- [x] Logs viewer works (PHP)
- [x] Fee calculations accurate
- [x] Documentation updated

### Next Steps

The mock servers are now fully compatible with the current WordPress plugin implementation. All expected endpoints are available and functional.

For production deployment, remember to:
- Use real ED25519 keys instead of mock keys
- Implement proper authentication for sensitive endpoints
- Store webhook secrets securely
- Implement rate limiting
- Add proper error handling and logging
