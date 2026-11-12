@extends('frontend.layouts.app')
@section('title', 'Giriş - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Xoş gəlmisiniz</span>
        <h1>Giriş</h1>
        <p>Telefon nömrəniz və ya e-poçtunuzla daxil olun</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card">
            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="login">Telefon və ya e-poçt</label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" placeholder="050 123 45 67" autocomplete="username" required class="@error('login') is-invalid @enderror">
                    @error('login') <small class="err">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="password">Şifrə</label>
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
