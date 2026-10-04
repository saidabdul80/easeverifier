<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connect {{ $client->name }} to EaseVerifier</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <style>
        :root { color-scheme: light; font-family: "Instrument Sans", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; color: #18251d; background: #f2f6f3; }
        .page { min-height: 100vh; display: grid; place-items: center; padding: 32px 18px; }
        .panel { width: min(100%, 510px); overflow: hidden; border: 1px solid #d9e3dc; border-radius: 8px; background: #fff; box-shadow: 0 20px 60px rgba(17, 57, 34, .12); }
        .brand { height: 7px; background: #08783f; }
        .content { padding: 34px; }
        .logo { display: block; width: 62px; height: 62px; margin: 0 auto 20px; object-fit: contain; }
        h1 { margin: 0; font-size: 26px; line-height: 1.25; text-align: center; letter-spacing: 0; }
        .lead { margin: 10px auto 26px; color: #68756d; text-align: center; line-height: 1.55; }
        .account, .notice { border: 1px solid #dce5df; border-radius: 7px; padding: 16px; }
        .label { margin: 0 0 5px; color: #6b776f; font-size: 13px; font-weight: 600; text-transform: uppercase; }
        .email { margin: 0; font-weight: 700; overflow-wrap: anywhere; }
        .company { margin: 4px 0 0; color: #68756d; font-size: 14px; }
        .permissions { margin: 22px 0; }
        .permissions h2 { margin: 0 0 10px; font-size: 15px; }
        .permissions ul { margin: 0; padding: 0; list-style: none; }
        .permissions li { position: relative; margin: 10px 0; padding-left: 22px; color: #4f5e55; font-size: 14px; line-height: 1.45; }
        .permissions li::before { content: ""; position: absolute; left: 2px; top: 7px; width: 8px; height: 8px; border-radius: 50%; background: #16a765; }
        .notice { margin-bottom: 24px; border-color: #f1d38d; color: #725516; background: #fff9e9; font-size: 14px; line-height: 1.5; }
        .actions { display: grid; grid-template-columns: 1fr 1.35fr; gap: 12px; }
        button { width: 100%; min-height: 46px; border: 1px solid #cbd7cf; border-radius: 7px; padding: 0 18px; font: inherit; font-weight: 700; cursor: pointer; transition: transform .15s ease, box-shadow .15s ease, background .15s ease; }
        button:hover { transform: translateY(-1px); }
        button:focus-visible { outline: 3px solid rgba(8, 120, 63, .24); outline-offset: 2px; }
        .deny { color: #314139; background: #fff; }
        .connect { border-color: #08783f; color: #fff; background: #08783f; box-shadow: 0 8px 20px rgba(8, 120, 63, .18); }
        .connect:hover { background: #066735; }
        button:disabled { cursor: wait; opacity: .72; transform: none; }
        .fine-print { margin: 18px 0 0; color: #7c8981; font-size: 12px; line-height: 1.5; text-align: center; }
        @media (max-width: 520px) { .content { padding: 26px 20px; } .actions { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main class="page">
    <section class="panel" aria-labelledby="oauth-title">
        <div class="brand"></div>
        <div class="content">
            <img src="/easeverifier-logo.png" alt="EaseVerifier" class="logo">
            <h1 id="oauth-title">Connect {{ $client->name }} to EaseVerifier</h1>
            <p class="lead">Review the account access below before you continue.</p>

            <div class="account">
                <p class="label">Signed in as</p>
                <p class="email">{{ $user->email }}</p>
                @if($user->customer?->company_name)
                    <p class="company">{{ $user->customer->company_name }}</p>
                @endif
            </div>

            <div class="permissions">
                <h2>{{ $client->name }} will be able to:</h2>
                <ul>
                    <li>View your services, prices, wallet balance, and verification history.</li>
                    <li>Submit identity and examination-result verifications after your confirmation.</li>
                    <li>Purchase result checker PINs after your confirmation.</li>
                </ul>
            </div>

            <div class="notice">
                Paid tools debit your EaseVerifier wallet. The AI client should show the charge and ask you to confirm before each paid action.
            </div>

            <div class="actions">
                <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="deny">Deny</button>
                </form>

                <form method="POST" action="{{ route('passport.authorizations.approve') }}" id="authorize-form">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="connect" id="authorize-button">Connect account</button>
                </form>
            </div>

            <p class="fine-print">You can disconnect this account from the ChatGPT plugin settings at any time.</p>
        </div>
    </section>
</main>
<script>
    document.getElementById('authorize-form').addEventListener('submit', function () {
        const button = document.getElementById('authorize-button');
        button.disabled = true;
        button.textContent = 'Connecting...';
    });
</script>
</body>
</html>
