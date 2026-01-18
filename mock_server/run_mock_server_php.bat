@echo off
echo Starting CeyPay Mock Server (PHP) on port 5000...
echo Endpoints:
echo   POST /qrgen
echo   GET  /status/<transaction_id>
echo   POST /admin/confirm/<transaction_id>
php -S 0.0.0.0:5000 -t . index.php
pause