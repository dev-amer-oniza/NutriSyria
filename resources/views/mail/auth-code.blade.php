{{-- Inline styles only: mail clients ignore stylesheets. Colours from the site identity. --}}
<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f0e9;font-family:'IBM Plex Sans Arabic',Tahoma,Arial,sans-serif;color:#1f1f1f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f0e9;padding:32px 16px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fbf9f5;border:1px solid #ddd4c6;border-radius:20px;">
            <tr><td style="padding:28px 32px 0;direction:ltr;text-align:right;">
                <span style="font-family:Sora,Arial,sans-serif;font-size:20px;font-weight:600;color:#0f3d32;">NutriSyria</span>
            </td></tr>
            <tr><td dir="rtl" style="padding:20px 32px 8px;text-align:right;font-size:16px;line-height:28px;">
                <p style="margin:0 0 8px;">مرحباً {{ $name }}،</p>
                <p style="margin:0;color:#56615a;">{{ $intro }}</p>
            </td></tr>
            <tr><td align="center" style="padding:20px 32px;">
                <div style="display:inline-block;padding:14px 28px;background:#dfe6dc;border-radius:12px;font-family:Sora,'Courier New',monospace;font-size:34px;font-weight:600;letter-spacing:8px;color:#0f3d32;direction:ltr;">{{ $code }}</div>
            </td></tr>
            <tr><td dir="rtl" style="padding:0 32px 28px;text-align:right;font-size:14px;line-height:24px;color:#56615a;">
                <p style="margin:0;">الرمز صالح لمدة {{ $minutes }} دقائق. إن لم تطلبه، تجاهل هذه الرسالة ولن يتغيّر شيء في حسابك.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
