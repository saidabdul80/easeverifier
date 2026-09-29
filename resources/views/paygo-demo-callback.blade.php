<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Payment received - EaseVerifier</title>
        <style>
            body { display: grid; min-height: 100vh; margin: 0; place-items: center; background: #f3f6f4; color: #17221c; font-family: Arial, sans-serif; }
            main { max-width: 420px; padding: 32px; border: 1px solid #dfe7e2; border-radius: 8px; background: #fff; text-align: center; }
            h1 { margin: 0 0 8px; color: #0f3e20; font-size: 24px; }
            p { margin: 0; color: #5f6f65; line-height: 1.6; }
        </style>
    </head>
    <body>
        <main>
            <h1>Payment received</h1>
            <p>EaseVerifier is confirming the Paystack test transaction. You can close this window.</p>
        </main>
        <script>
            window.setTimeout(function () {
                if (window.parent !== window) {
                    window.parent.postMessage('easeverifier-paystack-demo-returned', window.location.origin);
                    return;
                }

                window.close();
            }, 500);
        </script>
    </body>
</html>
