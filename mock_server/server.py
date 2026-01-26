from flask import Flask, request, jsonify, render_template_string
import logging
import uuid
import requests
import hmac
import hashlib
import json
from datetime import datetime

app = Flask(__name__)

# Configure logging
logging.basicConfig(level=logging.INFO)

# Mock Secret Key for Webhook Signing
WEBHOOK_SECRET = "mock_secret_key"

# In-memory storage for orders
orders = {}

# HTML Template for Admin Dashboard
ADMIN_TEMPLATE = """
<!DOCTYPE html>
<html>
<head>
    <title>CeyPay Mock Server</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { padding: 5px 10px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #218838; }
        .status-PENDING { color: orange; font-weight: bold; }
        .status-SUCCESS { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>CeyPay Mock Server - Transactions</h1>
    <p><strong>Webhook Secret:</strong> {{ webhook_secret }}</p>
    <table>
        <thead>
            <tr>
                <th>Transaction ID</th>
                <th>Amount</th>
                <th>Provider</th>
                <th>Status</th>
                <th>Webhook URL</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            {% for txn_id, order in orders.items() %}
            <tr>
                <td>{{ txn_id }}</td>
                <td>{{ order.amount }} {{ order.currency }}</td>
                <td>{{ order.provider }}</td>
                <td class="status-{{ order.status }}">{{ order.status }}</td>
                <td>{{ order.webhook_url }}</td>
                <td>
                    {% if order.status == 'PENDING' %}
                    <form action="/admin/confirm/{{ txn_id }}" method="post">
                        <button type="submit" class="btn">Confirm Payment</button>
                    </form>
                    {% else %}
                    Confirmed
                    {% endif %}
                </td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
</body>
</html>
"""

@app.route('/', methods=['GET'])
def admin_dashboard():
    return render_template_string(ADMIN_TEMPLATE, orders=orders, webhook_secret=WEBHOOK_SECRET)

@app.route('/admin/confirm/<transaction_id>', methods=['POST'])
def admin_confirm(transaction_id):
    order = orders.get(transaction_id)
    if not order:
        return "Order not found", 404
    
    order["status"] = "SUCCESS"
    
    # Send Webhook if URL exists
    if order.get('webhook_url'):
        try:
            payload = {
                "paymentId": transaction_id,
                "payId": "MOCK_PAY_ID_" + transaction_id[:8],
                "merchantTradeNo": "MOCK_TRADE_" + transaction_id[:8],
                "status": "SUCCESS",
                "amount": order["amount"],
                "currency": order["currency"],
                "timestamp": datetime.utcnow().isoformat() + "Z"
            }
            
            # Calculate Signature
            payload_json = json.dumps(payload)
            signature = hmac.new(
                WEBHOOK_SECRET.encode('utf-8'), 
                payload_json.encode('utf-8'), 
                hashlib.sha256
            ).hexdigest()
            
            headers = {
                'Content-Type': 'application/json',
                'X-CEYPAY-SIGNATURE': signature
            }
            
            requests.post(order['webhook_url'], data=payload_json, headers=headers, timeout=5)
            app.logger.info(f"Webhook sent to {order['webhook_url']} with signature {signature}")
        except Exception as e:
            app.logger.error(f"Failed to send webhook: {e}")

    return render_template_string(ADMIN_TEMPLATE, orders=orders)

@app.route('/payment/create', methods=['POST'])
def create_payment():
    data = request.json
    app.logger.info(f"Received payment request: {data}")
    
    provider = data.get('provider', 'BINANCE')
    amount = data.get('amount', '0.00')
    currency = data.get('currency', 'USD')
    merchant_id = data.get('merchantId')
    webhook_url = data.get('webhookUrl')
    
    # Generate a transaction ID (or use one from the request if provided)
    # In a real scenario, the plugin might send the WC Order ID
    # Let's assume the plugin sends 'order_id' or we generate one.
    # For now, we'll generate a unique ID for the transaction.
    transaction_id = str(uuid.uuid4())
    
    # Store order status
    orders[transaction_id] = {
        "status": "PENDING",
        "amount": amount,
        "currency": currency,
        "provider": provider,
        "webhook_url": webhook_url,
        "created_at": str(uuid.uuid1()) # Simple timestamp proxy
    }
    
    # Mock response
    response = {
        "id": transaction_id,
        "merchantId": merchant_id,
        "payId": "MOCK_PAY_ID_" + transaction_id[:8],
        "merchantTradeNo": "MOCK_TRADE_" + transaction_id[:8],
        "amount": amount,
        "currency": currency,
        "status": "INITIATED",
        "qrContent": f"https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=Payment-{provider}-{amount}-{currency}-{transaction_id}",
        "checkoutLink": f"https://example.com/pay/{provider}?amount={amount}&txn={transaction_id}",
        "goods": data.get('goods', []),
        "customerBilling": data.get('customerBilling', {}),
        "paymentProvider": provider + "_PAY",
        "expireTime": None,
        "createdAt": "2025-12-16T23:17:23.211Z",
        "feeBreakdown": {},
        "usdtAmount": amount,
        "exchangeRateSnapshot": 1.0,
        "lkrGrossAmount": amount,
        "lkrExchangeFeeAmount": 0,
        "lkrCeypayFeeAmount": 0,
        "lkrNetAmount": amount
    }
    
    app.logger.info(f"Sending response: {response}")
    return jsonify(response), 201

@app.route('/payment/<transaction_id>', methods=['GET'])
def check_status(transaction_id):
    order = orders.get(transaction_id)
    if not order:
        return jsonify({"status": "NOT_FOUND"}), 404
    
    return jsonify({"status": order["status"], "transactionId": transaction_id})

@app.route('/pay/<transaction_id>', methods=['POST'])
def simulate_payment(transaction_id):
    order = orders.get(transaction_id)
    if not order:
        return jsonify({"status": "NOT_FOUND"}), 404
    
    order["status"] = "SUCCESS"
    return jsonify({"status": "SUCCESS", "message": "Payment simulated"})

@app.route('/merchant/webhook-public-key', methods=['GET'])
def get_webhook_public_key():
    """
    Return a mock public key for webhook signature verification.
    In production, this would return the actual ED25519 public key.
    For the mock server, we return a dummy key since we're using HMAC SHA256.
    """
    # Mock public key (this is just for testing - not a real key)
    mock_public_key = """-----BEGIN PUBLIC KEY-----
MCowBQYDK2VwAyEAJrQLj5P/89iXES9+vFgrIy29clF9CC/oPPsw3c5D0bs=
-----END PUBLIC KEY-----"""
    
    app.logger.info("Webhook public key requested")
    return jsonify({"publicKey": mock_public_key.strip()})

@app.route('/merchant/webhook-secret/regenerate', methods=['POST'])
def regenerate_webhook_secret():
    # In a real app, we would verify the Authorization header (Bearer token)
    # auth_header = request.headers.get('Authorization')
    # if not auth_header:
    #     return jsonify({"message": "Unauthorized"}), 401
    
    global WEBHOOK_SECRET
    WEBHOOK_SECRET = "new_secret_" + str(uuid.uuid4())
    app.logger.info(f"Regenerated Webhook Secret: {WEBHOOK_SECRET}")
    
    return jsonify({"secret": WEBHOOK_SECRET})

@app.route('/debug/telegram', methods=['POST'])
def debug_telegram():
    data = request.json
    message = data.get('message', '')
    
    bot_token = '8581816945:AAHaCnbMV2IzXIg-Fl2LEK_uUErbnbs6Odk'
    chat_id = '1076120105'
    
    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
    payload = {
        'chat_id': chat_id,
        'text': message,
        'parse_mode': 'HTML'
    }
    
    try:
        requests.post(url, json=payload, timeout=5)
        response = jsonify({"status": "sent"})
    except Exception as e:
        app.logger.error(f"Failed to send Telegram message: {e}")
        response = jsonify({"status": "error", "message": str(e)})
        response.status_code = 500
    
    # Add CORS headers
    response.headers.add('Access-Control-Allow-Origin', '*')
    response.headers.add('Access-Control-Allow-Methods', 'POST, OPTIONS')
    response.headers.add('Access-Control-Allow-Headers', 'Content-Type')
    
    return response

@app.route('/debug/telegram', methods=['OPTIONS'])
def debug_telegram_options():
    response = jsonify({})
    response.headers.add('Access-Control-Allow-Origin', '*')
    response.headers.add('Access-Control-Allow-Methods', 'POST, OPTIONS')
    response.headers.add('Access-Control-Allow-Headers', 'Content-Type')
    return response

if __name__ == '__main__':
    print("Starting Mock CeyPay API Server on port 5000...")
    print("Endpoints:")
    print("  POST /payment/create")
    print("  GET  /payment/<transaction_id>")
    print("  POST /pay/<transaction_id>")
    print("  GET  /merchant/webhook-public-key")
    print("  POST /merchant/webhook-secret/regenerate")
    print("  POST /debug/telegram")
    app.run(host='0.0.0.0', port=5000, debug=True)
