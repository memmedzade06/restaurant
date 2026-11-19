<!doctype html>
<html lang="az">
<head><meta charset="utf-8"><title>Şifrənin bərpası</title></head>
<body style="margin:0;padding:24px;background:#f6efe7;font-family:Arial,Helvetica,sans-serif;color:#2b1a12;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#fff;border-radius:18px;padding:32px;">
                <tr><td>
                    <p style="margin:0 0 6px;font-size:13px;letter-spacing:2px;text-transform:uppercase;color:#e9a23b;font-weight:bold;">50 Qəpik</p>
                    <h1 style="margin:0 0 16px;font-size:22px;">Salam, {{ $user->name }}!</h1>
                    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">Şifrənizi bərpa etmək istədiniz. Yeni şifrə təyin etmək üçün aşağıdakı düyməni basın:</p>
                    <p style="margin:0 0 24px;text-align:center;">
                        <a href="{{ $url }}" style="display:inline-block;padding:14px 36px;background:#e9a23b;color:#fff;font-size:16px;font-weight:bold;text-decoration:none;border-radius:999px;">🔐 Yeni şifrə təyin et</a>
                    </p>
                    <p style="margin:0 0 8px;font-size:13px;color:#8a7a70;line-height:1.5;">Düymə işləməsə, bu linki brauzerdə açın:</p>
                    <p style="margin:0 0 20px;font-size:12px;line-height:1.4;word-break:break-all;"><a href="{{ $url }}" style="color:#7a1420;">{{ $url }}</a></p>
                    <p style="margin:0;font-size:13px;color:#8a7a70;line-height:1.5;">Link {{ $ttl }} dəqiqə etibarlıdır və bir dəfə işləyir. Bunu siz istəməmisinizsə, məktubu nəzərə almayın — şifrəniz dəyişməyəcək.</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
