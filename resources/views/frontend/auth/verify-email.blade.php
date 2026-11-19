@extends('frontend.layouts.app')
@section('title', 'E-poçt ilə təsdiq - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Son addım</span>
        <h1>E-poçtunuzu təsdiqləyin</h1>
        <p>{{ $user->email }}</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card text-center">
            <div class="tg-icon mail-icon"><i class="fa fa-envelope-open-text"></i></div>

            @if (session('status'))
                <div class="alert-ok"><i class="fa fa-check-circle me-2"></i>{{ session('status') }}</div>
            @endif
            @if ($sendFailed || session('error'))
                <div class="alert-soft mb-3">Məktub göndərilmədi. Bir az sonra "Məktubu yenidən göndər" düyməsini basın.</div>
            @endif

            <ol class="tg-steps">
                <li><b>{{ $user->email }}</b> ünvanına məktub göndərdik — e-poçtunuzu açın.</li>
                <li>Məktubdakı <b>✔ Təsdiqlə</b> düyməsini basın.</li>
                <li>Bu səhifə özü yenilənəcək.</li>
            </ol>

            <p class="tg-wait"><span class="dot"></span> Təsdiq gözlənilir…</p>
            <p class="hint mb-0">Məktubu görmürsünüzsə, <b>Spam</b> qovluğuna baxın. Link {{ \App\Services\EmailVerification::LINK_TTL_MINUTES }} dəqiqə etibarlıdır.</p>

            <form method="POST" action="{{ route('verify.email.resend') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn-link-plain" id="resendBtn" data-wait="{{ $resendWait }}" @disabled($resendWait > 0)>Məktubu yenidən göndər</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="btn-link-plain">Başqa e-poçt ilə qeydiyyat — çıxış</button>
            </form>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
    (function () {
        // E-poçtdakı düymə basılanda (hətta başqa cihazda) səhifə özü hesaba keçir
        const url = @json(route('verify.email.status'));
        const tick = () => fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
            .then(r => r.json())
            .then(d => { if (d.verified) location.href = d.redirect; else setTimeout(tick, 3000); })
            .catch(() => setTimeout(tick, 6000));
        setTimeout(tick, 3000);

        // "Yenidən göndər" üçün geri sayım
        const btn = document.getElementById('resendBtn');
        let wait = parseInt(btn.dataset.wait, 10) || 0;
        const label = btn.textContent;
        const count = () => {
            if (wait <= 0) { btn.disabled = false; btn.textContent = label; return; }
            btn.textContent = label + ' (' + wait + ' san.)';
            wait--;
            setTimeout(count, 1000);
        };
        count();
    })();
</script>
@endsection
