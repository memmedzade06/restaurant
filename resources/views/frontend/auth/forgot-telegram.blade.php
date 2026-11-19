@extends('frontend.layouts.app')
@section('title', 'Telegram ilə təsdiq - Şifrə bərpası - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Şifrə bərpası</span>
        <h1>Telegram ilə təsdiqləyin</h1>
        <p>{{ \App\Support\Phone::format($phone) }}</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card text-center">
            <ol class="pw-steps">
                <li class="done"><span><i class="fa fa-check"></i></span>Hesab</li>
                <li class="active"><span>2</span>Telegram</li>
                <li><span>3</span>Yeni şifrə</li>
            </ol>

            <div class="tg-icon"><i class="fab fa-telegram-plane"></i></div>

            <ol class="tg-steps">
                <li>Aşağıdakı düyməni basın — Telegram açılacaq.</li>
                <li>Telegramda <b>Start</b> basın. Lazım olsa, <b>✅ Təsdiqlə</b> düyməsini də basın.</li>
                <li>Bu səhifə özü yeni şifrə formuna keçəcək.</li>
            </ol>

            @if ($botReady)
                <a href="{{ $link }}" target="_blank" rel="noopener" class="btn btn-telegram w-100 py-3">
                    <i class="fab fa-telegram-plane me-2"></i>Telegram ilə təsdiqlə
                </a>
            @else
                {{-- .env: TELEGRAM_BOT_TOKEN, TELEGRAM_BOT_USERNAME --}}
                <div class="alert-soft">Telegram ilə təsdiq hazırda işləmir. Bir az sonra yenidən cəhd edin.</div>
            @endif

            <p class="tg-wait"><span class="dot"></span> Təsdiq gözlənilir…</p>
            <p class="hint mb-0">Link {{ \App\Services\TelegramPasswordReset::TTL_MINUTES }} dəqiqə etibarlıdır.</p>

            <p class="auth-switch"><a href="{{ route('password.request') }}">Başqa nömrə yaz</a></p>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
    // Bot təsdiqləyəndə səhifə özü yeni şifrə formuna keçir; vaxt keçsə — yenidən nömrə səhifəsinə
    (function () {
        const url = @json(route('password.status'));
        const tick = () => fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
            .then(r => r.json())
            .then(d => { if (d.confirmed || d.expired) location.href = d.redirect; else setTimeout(tick, 3000); })
            .catch(() => setTimeout(tick, 6000));
        setTimeout(tick, 3000);
    })();
</script>
@endsection
