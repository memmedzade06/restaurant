@extends('frontend.layouts.app')
@section('title', 'Qeydiyyat - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">50 Qəpik</span>
        <h1>Qeydiyyat</h1>
        <p>Hesabınızı Telegram və ya e-poçt ilə təsdiqləyin</p>
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
                    <label for="password">Şifrə</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required class="@error('password') is-invalid @enderror">
                    <small class="hint">Ən azı 8 simvol. Yazdığınızı görmək üçün <i class="fa fa-eye"></i> işarəsinə basın.</small>
                    @error('password') <small class="err">{{ $message }}</small> @enderror
                </div>

                @php($method = old('method', 'telegram'))
                <div class="field">
                    <label>Hesabı necə təsdiqləyək?</label>
                    <div class="method-choice" role="radiogroup">
                        <label class="method-opt">
                            <input type="radio" name="method" value="telegram" @checked($method !== 'email')>
                            <span class="method-card"><i class="fab fa-telegram-plane m-tg"></i><b>Telegram ilə</b><small>Bir toxunuşla</small></span>
                        </label>
                        <label class="method-opt">
                            <input type="radio" name="method" value="email" @checked($method === 'email')>
                            <span class="method-card"><i class="fa fa-envelope m-mail"></i><b>E-poçt ilə</b><small>Məktubdakı düymə ilə</small></span>
                        </label>
                    </div>
                    @error('method') <small class="err">{{ $message }}</small> @enderror
                </div>

                <div class="field" data-method="telegram" @if ($method === 'email') hidden @endif>
                    <label for="phone">Mobil nömrə</label>
                    <div class="input-icon">
                        <i class="fa fa-phone-alt"></i>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="050 123 45 67" autocomplete="tel" inputmode="tel" class="@error('phone') is-invalid @enderror">
                    </div>
                    <small class="hint">Telegram hesabınızın bağlı olduğu nömrəni yazın.</small>
                    @error('phone') <small class="err">{{ $message }}</small> @enderror
                </div>

                <div class="field" data-method="email" @if ($method !== 'email') hidden @endif>
                    <label for="email">E-poçt</label>
                    <div class="input-icon">
                        <i class="fa fa-envelope"></i>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="ad@gmail.com" autocomplete="email" inputmode="email" class="@error('email') is-invalid @enderror">
                    </div>
                    <small class="hint">Bu ünvana təsdiq məktubu göndəriləcək.</small>
                    @error('email') <small class="err">{{ $message }}</small> @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 mt-2">Qeydiyyatdan keç <i class="fa fa-arrow-right ms-2"></i></button>

                <p class="auth-switch">Artıq hesabınız var? <a href="{{ route('login') }}">Daxil olun</a></p>
            </form>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
    // Təsdiq üsulu: Telegram — nömrə sahəsi, e-poçt — e-poçt sahəsi
    (function () {
        const radios = document.querySelectorAll('input[name="method"]');
        const sync = (focus) => {
            const m = document.querySelector('input[name="method"]:checked').value;
            document.querySelectorAll('[data-method]').forEach(el => { el.hidden = el.dataset.method !== m; });
            if (focus) document.getElementById(m === 'email' ? 'email' : 'phone').focus();
        };
        radios.forEach(r => r.addEventListener('change', () => sync(true)));
        sync(false);
    })();
</script>
@endsection
