@extends('frontend.layouts.app')
@section('title', 'Şifrəmi unutdum - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Şifrə bərpası</span>
        <h1>Şifrəmi unutdum</h1>
        <p>Telegram ilə təsdiqləyin və yeni şifrə təyin edin</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card">
            <ol class="pw-steps">
                <li class="active"><span>1</span>Nömrə</li>
                <li><span>2</span>Telegram</li>
                <li><span>3</span>Yeni şifrə</li>
            </ol>

            <form method="POST" action="{{ route('password.request') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="phone">Qeydiyyatdakı telefon nömrəniz</label>
                    <div class="input-icon">
                        <i class="fa fa-phone"></i>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="050 123 45 67" autocomplete="tel" required autofocus class="@error('phone') is-invalid @enderror">
                    </div>
                    @error('phone') <small class="err">{{ $message }}</small> @enderror
                    <small class="hint">Sonrakı addımda Telegram botumuz bu nömrənin sizə aid olduğunu təsdiqləyəcək.</small>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 mt-2">Davam et <i class="fa fa-arrow-right ms-2"></i></button>

                <p class="auth-switch">Şifrəni xatırladınız? <a href="{{ route('login') }}">Daxil olun</a></p>
            </form>
        </div>
    </div>
</section>
@endsection
