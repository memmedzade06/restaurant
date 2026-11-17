@extends('frontend.layouts.app')
@section('title', 'Yeni şifrə - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Şifrə bərpası</span>
        <h1>Yeni şifrə təyin edin</h1>
        <p>{{ \App\Support\Phone::format($phone) }}</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card">
            <ol class="pw-steps">
                <li class="done"><span><i class="fa fa-check"></i></span>Nömrə</li>
                <li class="done"><span><i class="fa fa-check"></i></span>Telegram</li>
                <li class="active"><span>3</span>Yeni şifrə</li>
            </ol>

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="password">Yeni şifrə</label>
                    <div class="input-icon">
                        <i class="fa fa-lock"></i>
                        <input id="password" name="password" type="password" autocomplete="new-password" required autofocus class="@error('password') is-invalid @enderror">
                    </div>
                    @error('password') <small class="err">{{ $message }}</small> @enderror
                    <small class="hint">Ən azı 8 simvol.</small>
                </div>

                <div class="field">
                    <label for="password_confirmation">Yeni şifrəni təkrarlayın</label>
                    <div class="input-icon">
                        <i class="fa fa-lock"></i>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 mt-2">Şifrəni yadda saxla <i class="fa fa-check ms-2"></i></button>
            </form>
        </div>
    </div>
</section>
@endsection
