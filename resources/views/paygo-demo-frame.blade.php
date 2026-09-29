<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Paystack test checkout</title>
        <style>
            html, body { min-height: 100%; margin: 0; background: #eef3f6; }
            body { display: grid; place-items: center; color: #52616a; font-family: Arial, sans-serif; }
            p { margin: 0; font-size: 14px; }
        </style>
        <script src="https://js.paystack.co/v2/inline.js"></script>
    </head>
    <body>
        <p id="checkout-status">Opening Paystack test checkout...</p>
        <script>
            window.addEventListener('load', function () {
                if (typeof window.PaystackPop !== 'function') {
                    document.getElementById('checkout-status').textContent = 'Unable to load Paystack checkout.';
                    return;
                }

                const checkout = new window.PaystackPop();
                checkout.resumeTransaction(@json($accessCode));
            });
        </script>
    </body>
</html>
