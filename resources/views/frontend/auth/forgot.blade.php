@extends('frontend.layouts.app')
@section('title', 'Şifrəmi unutdum - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Şifrə bərpası</span>
        <h1>Şifrəmi unutdum</h1>
        <p>Nömrə ilə — Telegram vasitəsilə, e-poçt ilə — məktub vasitəsilə bərpa edin</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card">
            <ol class="pw-steps">
                <li class="active"><span>1</span>Hesab</li>
                <li><span>2</span>Təsdiq</li>
                <li><span>3</span>Yeni şifrə</li>
            </ol>

            <form method="POST" action="{{ route('password.request') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="login">Qeydiyyatdakı nömrə və ya e-poçt</label>
                    <div class="input-icon">
                        <i class="fa fa-user"></i>
                        <input id="login" name="login" type="text" value="{{ old('login') }}" placeholder="050 123 45 67 və ya ad@gmail.com" autocomplete="username" required autofocus class="@error('login') is-invalid @enderror">
                    </div>
                    @error('login') <small class="err">{{ $message }}</small> @enderror
                    <small class="hint">Nömrə ilə qeydiyyatdan keçmisinizsə — Telegram, e-poçt ilə keçmisinizsə — e-poçtunuza göndərilən məktub təsdiqləyəcək.</small>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 mt-2">Davam et <i class="fa fa-arrow-right ms-2"></i></button>

                <p class="auth-switch">Şifrəni xatırladınız? <a href="{{ route('login') }}">Daxil olun</a></p>
            </form>
        </div>
    </div>
</section>
@endsection
