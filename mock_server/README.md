# Mock CeyPay API Server

This is a simple Python Flask server to mock the CeyPay API for testing the WordPress plugin.

## Prerequisites
- Python 3.x installed (for Python version).
- PHP installed (for PHP version).

## How to Run (Python)
1.  Double-click `run_mock_server.bat` 
    OR 
    Run the following commands in a terminal:
    ```bash
    pip install -r requirements.txt
    python server.py
    ```
2.  The server will start on `http://localhost:5000`.

## How to Run (PHP)
1.  Double-click `run_mock_server_php.bat`
    OR
    Run the following command in a terminal:
    ```bash
    php -S 0.0.0.0:5000 -t . index.php
    ```
2.  The server will start on `http://localhost:5000`.

## Configuring the Plugin
1.  Go to your WordPress Admin > WooCommerce > Settings > Payments > CeyPay.
2.  Change the **API URL** to:
    *   `http://host.docker.internal:5000` (If WordPress is running in Docker)
    *   `http://localhost:5000` (If WordPress is running locally on XAMPP/WAMP)
3.  Save changes.

## API Endpoints

### 1. Admin Dashboard
*   **URL:** `GET /`
*   **Description:** View all transactions and manually confirm payments.
*   **Usage:** Open `http://localhost:5000/` in your browser.

### 2. API Logs Viewer
*   **URL:** `GET /logs`
*   **Description:** View live API request/response logs with auto-refresh (PHP version only).
*   **Usage:** Open `http://localhost:5000/logs` in your browser.

### 3. Create Payment Transaction
*   **URL:** `POST /payment/create`
*   **Description:** Creates a new payment transaction and returns QR code and checkout link.
*   **Request Body:**
    ```json
    {
        "merchantId": "your_merchant_id",
        "amount": "100.00",
        "currency": "LKR",
        "provider": "BYBIT",
        "webhookUrl": "http://your-site.com/wc-api/WC_Gateway_CeyPay",
        "goods": [
            {
                "name": "Product Name",
                "description": "Product Description",
                "mccCode": "5818"
            }
        ],
        "customerBilling": {
            "firstName": "John",
            "lastName": "Doe",
            "city": "Colombo",
            "email": "john@example.com",
            "phone": "+94771234567"
        }
    }
    ```
*   **Response:**
    ```json
    {
        "id": "txn_abc123",
        "merchantId": "your_merchant_id",
        "payId": "MOCK_PAY_ID_abc123",
        "merchantTradeNo": "MOCK_TRADE_abc123",
        "amount": "100.00",
        "currency": "LKR",
        "status": "INITIATED",
        "qrContent": "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=...",
        "checkoutLink": "http://localhost:5000/pay-ui/txn_abc123",
        "paymentProvider": "BYBIT_PAY",
        "feeBreakdown": {
            "grossAmountUSDT": 0.326,
            "exchangeFeePercentage": 0.5,
            "exchangeFeeAmountUSDT": 0.00163,
            "ceypayFeePercentage": 1,
            "ceypayFeeAmountUSDT": 0.00326,
            "totalFeesUSDT": 0.00489,
            "netAmountUSDT": 0.32111,
            "grossAmountLKR": 100.00,
            "exchangeFeeAmountLKR": 0.50,
            "ceypayFeeAmountLKR": 1.00,
            "totalFeesLKR": 1.50,
            "netAmountLKR": 98.50
        },
        "usdtAmount": 0.32111,
        "exchangeRateSnapshot": 306.5
    }
    ```

### 4. Check Payment Status
*   **URL:** `GET /payment/<transaction_id>`
*   **Description:** Checks the status of a specific transaction.
*   **Response:**
    ```json
    {
        "status": "PENDING" | "SUCCESS",
        "transactionId": "txn_abc123"
    }
    ```

### 5. Get Webhook Public Key
*   **URL:** `GET /merchant/webhook-public-key`
*   **Description:** Returns the public key for verifying webhook signatures.
*   **Response:**
    ```json
    {
        "publicKey": "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAJrQLj5P/89iXES9+vFgrIy29clF9CC/oPPsw3c5D0bs=\n-----END PUBLIC KEY-----"
    }
    ```

### 6. Regenerate Webhook Secret
*   **URL:** `POST /merchant/webhook-secret/regenerate`
*   **Description:** Generates a new webhook secret (for testing purposes).
*   **Response:**
    ```json
    {
        "secret": "new_secret_abc123"
    }
    ```

### 7. Admin Confirm Payment
*   **URL:** `POST /admin/confirm/<transaction_id>`
*   **Description:** Marks transaction as SUCCESS and sends a webhook notification to the `webhookUrl` provided during creation.
*   **Usage:** Used by the Admin Dashboard "Confirm Payment" buttons.

### 8. Payment UI (Customer View)
*   **URL:** `GET /pay-ui/<transaction_id>`
*   **Description:** Customer-facing page to simulate payment confirmation.
*   **Usage:** This is the link sent to customers in the QR code and deep link.

### 9. Process Payment UI
*   **URL:** `POST /pay-ui/<transaction_id>`
*   **Description:** Processes payment confirmation from customer UI and triggers webhook.

### 10. Debug Telegram
*   **URL:** `POST /debug/telegram`
*   **Description:** Sends debug messages to configured Telegram bot for testing.
*   **Request Body:**
    ```json
    {
        "message": "Test message"
    }
    ```
