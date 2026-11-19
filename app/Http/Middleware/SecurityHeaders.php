<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brauzerə təhlükəsizlik başlıqları:
 *  - X-Frame-Options: sayt başqa saytın içində (iframe) açılmasın — gizli klik oğurluğuna (clickjacking) qarşı;
 *  - X-Content-Type-Options: brauzer faylın növünü "təxmin" etməsin (məs. şəkli skript kimi işlətməsin);
 *  - Strict-Transport-Security: sayt yalnız HTTPS ilə açılsın (yalnız HTTPS sorğusunda göndərilir);
 *  - Referrer-Policy: başqa saytlara keçəndə səhifənin tam ünvanı (tokenlər və s.) ötürülməsin;
 *  - Permissions-Policy: sayt kamera, mikrofon, yer məlumatı istəmir.
 * PHP versiyası (X-Powered-By) də gizlədilir.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        $headers->remove('X-Powered-By');
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
