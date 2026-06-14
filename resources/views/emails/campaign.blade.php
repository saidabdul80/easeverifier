<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payload['subject'] }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#10233b;">
    <div style="max-width:640px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:16px; padding:40px 32px; box-shadow:0 10px 30px rgba(16,35,59,0.08);">
            <p style="margin:0 0 24px; font-size:12px; letter-spacing:0.12em; text-transform:uppercase; color:#4f6b8a;">
                {{ $payload['support_name'] ?? config('app.name') }}
            </p>

            @if(!empty($payload['heading']))
                <h1 style="margin:0 0 20px; font-size:28px; line-height:1.2; color:#10233b;">
                    {{ $payload['heading'] }}
                </h1>
            @endif

            <div style="font-size:16px; line-height:1.75; color:#31475f;">
                {!! nl2br(e($payload['body'])) !!}
            </div>

            @if(!empty($payload['cta_label']) && !empty($payload['cta_url']))
                <div style="margin-top:28px;">
                    <a href="{{ $payload['cta_url'] }}"
                        style="display:inline-block; background:#0b63f6; color:#ffffff; text-decoration:none; padding:14px 22px; border-radius:10px; font-weight:600;">
                        {{ $payload['cta_label'] }}
                    </a>
                </div>
            @endif

            <div style="margin-top:32px; padding-top:24px; border-top:1px solid #d8e2ef; font-size:13px; color:#5f748c;">
                <p style="margin:0 0 8px;">Need help? Contact us at {{ $payload['support_email'] }}</p>
                <p style="margin:0;">This email was sent by {{ $payload['support_name'] ?? config('app.name') }}.</p>
            </div>
        </div>
    </div>
</body>
</html>
