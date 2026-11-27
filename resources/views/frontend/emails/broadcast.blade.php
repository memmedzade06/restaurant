<!doctype html>
<html lang="az">
<head><meta charset="utf-8"><title>50 Qəpik</title></head>
<body style="margin:0;padding:24px;background:#f6efe7;font-family:Arial,Helvetica,sans-serif;color:#2b1a12;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border-radius:18px;padding:32px;">
                <tr><td>
                    <p style="margin:0 0 6px;font-size:13px;letter-spacing:2px;text-transform:uppercase;color:#e9a23b;font-weight:bold;">50 Qəpik</p>
                    <h1 style="margin:0 0 16px;font-size:22px;">Salam, {{ $user->name }}!</h1>
                    @if (trim((string) $broadcast->body) !== '')
                        <div style="margin:0 0 24px;font-size:15px;line-height:1.6;">{!! nl2br(e($broadcast->body)) !!}</div>
                    @endif
                    @if ($broadcast->attachment_path)
                        <p style="margin:0 0 24px;font-size:14px;line-height:1.5;color:#5a4a40;">
                            📎 {{ $broadcast->attachment_name }}
                            @if ($fileAttached)
                                — məktuba əlavə olunub.
                            @else
                                — fayl böyükdür, saytda <a href="{{ $messagesUrl }}" style="color:#7a1420;">Mesajlar</a> bölməsində baxa bilərsiniz.
                            @endif
                        </p>
                    @endif
                    @if ($linkUrl)
                        <p style="margin:0 0 24px;text-align:center;">
                            <a href="{{ $linkUrl }}" style="display:inline-block;padding:14px 36px;background:#e9a23b;color:#fff;font-size:16px;font-weight:bold;text-decoration:none;border-radius:999px;">{{ $linkLabel }} →</a>
                        </p>
                    @endif
                    <p style="margin:0;font-size:12px;color:#8a7a70;line-height:1.5;">Bu məktub 50 Qəpik saytında qeydiyyatınız olduğu üçün göndərilib.@if ($inChat) Mesajı saytda <a href="{{ $messagesUrl }}" style="color:#7a1420;">Mesajlar</a> bölməsində də görə bilərsiniz.@endif</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
