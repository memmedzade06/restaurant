@extends('frontend.layouts.app')
@section('title', 'Telegram ilə təsdiq - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Son addım</span>
        <h1>Nömrənizi təsdiqləyin</h1>
        <p>{{ \App\Support\Phone::format($user->phone) }}</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card text-center">
            <div class="tg-icon"><i class="fab fa-telegram-plane"></i></div>

            <ol class="tg-steps">
                <li>Aşağıdakı düyməni basın — Telegram açılacaq.</li>
                <li>Botda <b>Start</b>, sonra <b>✅ Təsdiqlə</b> düyməsini basın.</li>
                <li>Bu səhifə özü yenilənəcək.</li>
            </ol>

            @if ($botReady)
                <a href="{{ $link }}" target="_blank" rel="noopener" class="btn btn-telegram w-100 py-3">
                    <i class="fab fa-telegram-plane me-2"></i>Telegram ilə təsdiqlə
                </a>
            @else
                <div class="alert-soft">Telegram bot hələ qoşulmayıb (.env: TELEGRAM_BOT_TOKEN, TELEGRAM_BOT_USERNAME).</div>
            @endif

            <p class="tg-wait" id="tgWait"><span class="dot"></span> Təsdiq gözlənilir…</p>
            <p class="hint mb-0">Link {{ \App\Services\TelegramVerification::TOKEN_TTL_MINUTES }} dəqiqə etibarlıdır. Vaxtı keçsə, səhifəni yeniləyin.</p>

            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn-link-plain">Başqa nömrə ilə qeydiyyat — çıxış</button>
            </form>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
    // Bot nömrəni təsdiqləyəndə səhifə özü hesaba keçir
    (function () {
        const url = @json(route('verify.telegram.status'));
        const tick = () => fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
            .then(r => r.json())
            .then(d => { if (d.verified) location.href = d.redirect; else setTimeout(tick, 3000); })
            .catch(() => setTimeout(tick, 6000));
        setTimeout(tick, 3000);
    })();
</script>
@endsection
