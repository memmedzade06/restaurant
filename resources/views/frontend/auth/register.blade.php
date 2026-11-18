@extends('frontend.layouts.app')
@section('title', 'Qeydiyyat - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">50 Qəpik</span>
        <h1>Qeydiyyat</h1>
        <p>Nömrəniz Telegram vasitəsilə təsdiqlənəcək</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card">
            @if ($referrer ?? null)
                <div class="alert-soft mb-3"><i class="fa fa-user-friends me-2"></i>Sizi <b>{{ $referrer->short_name }}</b> dəvət edib — xoş gəlmisiniz!</div>
            @endif
            <form method="POST" action="{{ route('register') }}" novalidate>
                @csrf

                <div class="field">
                    <label for="name">Ad, soyad</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required class="@error('name') is-invalid @enderror">
                    @error('name') <small class="err">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="phone">Mobil nömrə</label>
                    <div class="input-icon">
                        <i class="fa fa-phone-alt"></i>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="050 123 45 67" autocomplete="tel" inputmode="tel" required class="@error('phone') is-invalid @enderror">
                    </div>
                    <small class="hint">Telegram hesabınızın bağlı olduğu nömrəni yazın.</small>
                    @error('phone') <small class="err">{{ $message }}</small> @enderror
                </div>

                <div class="field">
                    <label for="password">Şifrə</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required class="@error('password') is-invalid @enderror">
                    <small class="hint">Ən azı 8 simvol. Yazdığınızı görmək üçün <i class="fa fa-eye"></i> işarəsinə basın.</small>
                    @error('password') <small class="err">{{ $message }}</small> @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 mt-2">Qeydiyyatdan keç <i class="fa fa-arrow-right ms-2"></i></button>

                <p class="auth-switch">Artıq hesabınız var? <a href="{{ route('login') }}">Daxil olun</a></p>
            </form>
        </div>
    </div>
</section>
@endsection
