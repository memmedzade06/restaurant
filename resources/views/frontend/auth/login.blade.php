@extends('frontend.layouts.app')
@section('title', 'Giriş - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Xoş gəlmisiniz</span>
        <h1>Giriş</h1>
        <p>Nömrəniz və ya e-poçtunuz və şifrənizlə daxil olun</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card">
            @if (session('status'))
                <div class="alert-ok"><i class="fa fa-check-circle me-2"></i>{{ session('status') }}</div>
            @endif

            @include('frontend.partials.google-button', ['noDivider' => true])
            @if (\App\Http\Controllers\Auth\GoogleAuthController::enabled())
                <p class="google-note"><i class="fa fa-info-circle me-1"></i>Əvvəl <b>nömrə ilə</b> qeydiyyatdan keçmisinizsə, əvvəlcə nömrənizlə daxil olun və <b>Hesabım</b> səhifəsində Google hesabınızı bağlayın — yoxsa yeni hesab açılar.</p>
                <div class="auth-or"><span>və ya</span></div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                {{-- Nömrə və ya e-poçt (arxada admin istifadəçi adı ilə də daxil ola bilir) --}}
                <div class="field">
                    <label for="login">Mobil nömrə və ya e-poçt</label>
                    <div class="input-icon">
                        <i class="fa fa-user"></i>
                        <input id="login" name="login" type="text" value="{{ old('login') }}" placeholder="050 123 45 67 və ya ad@gmail.com" autocomplete="username" required class="@error('login') is-invalid @enderror">
                    </div>
                    @error('login') <small class="err">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <div class="label-row">
                        <label for="password">Şifrə</label>
                        <a href="{{ route('password.request') }}" class="forgot-link">Şifrəmi unutdum</a>
                    </div>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <label class="check">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}> Məni xatırla
                </label>

                <button type="submit" class="btn btn-primary w-100 py-3 mt-3">Daxil ol <i class="fa fa-arrow-right ms-2"></i></button>

                <p class="auth-switch">Hesabınız yoxdur? <a href="{{ route('register') }}">Qeydiyyatdan keçin</a></p>
            </form>
        </div>
    </div>
</section>
@endsection
