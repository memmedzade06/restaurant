@extends('frontend.layouts.app')
@section('title', 'E-poçtunuzu yoxlayın - Şifrə bərpası - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Şifrə bərpası</span>
        <h1>E-poçtunuzu yoxlayın</h1>
        <p>{{ $email }}</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card text-center">
            <ol class="pw-steps">
                <li class="done"><span><i class="fa fa-check"></i></span>Hesab</li>
                <li class="active"><span>2</span>E-poçt</li>
                <li><span>3</span>Yeni şifrə</li>
            </ol>

            <div class="tg-icon mail-icon"><i class="fa fa-envelope-open-text"></i></div>

            <ol class="tg-steps">
                <li><b>{{ $email }}</b> ünvanına məktub göndərdik — e-poçtunuzu açın.</li>
                <li>Məktubdakı <b>🔐 Yeni şifrə təyin et</b> düyməsini basın.</li>
                <li>Açılan səhifədə yeni şifrənizi yazın.</li>
            </ol>

            <p class="hint mb-0">Məktubu görmürsünüzsə, <b>Spam</b> qovluğuna baxın. Link {{ \App\Services\EmailPasswordReset::TTL_MINUTES }} dəqiqə etibarlıdır.</p>

            <p class="auth-switch"><a href="{{ route('password.request') }}">Yenidən göndər</a> · <a href="{{ route('login') }}">Girişə qayıt</a></p>
        </div>
    </div>
</section>
@endsection
