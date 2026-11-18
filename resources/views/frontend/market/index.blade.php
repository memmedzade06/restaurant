@extends('frontend.layouts.app')
@section('title', 'Hədiyyə marketi - 50 Qəpik')

@php
    $fmt = fn ($p) => \App\Services\Referrals::format((float) $p);
    $authUrl = auth()->check() ? route('verify.telegram') : route('login');
@endphp

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Hədiyyə marketi</h1>
        <p>Xallarınızı hədiyyəyə çevirin — kupon dərhal hesabınıza düşür</p>
    </div>
</section>

<section class="section pt-4 account-page">
    <div class="container" style="max-width: 1000px">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>

        @if ($bought)
            {{-- yenicə alınmış hədiyyə: kupon kodu --}}
            <div class="acc-card market-bought mb-4" role="status">
                <span class="eyebrow">Təbriklər 🎉</span>
                <h3>{{ $bought->prize_label }}</h3>
                <div class="prize-code">{{ $bought->code }}</div>
                <p class="small text-muted mb-0">Kodu restoranda ofisianta göstərin. {{ $bought->expires_at->timezone('Asia/Baku')->format('d.m.Y') }} tarixinədək etibarlıdır.
                    Kod <a href="{{ route('prizes') }}">Hədiyyələrim</a> səhifəsində də saxlanılır.</p>
            </div>
        @endif
        @if (session('market_error'))
            <div class="alert-soft mb-4" role="alert"><i class="fa fa-exclamation-triangle me-2"></i>{{ session('market_error') }}</div>
        @endif

        {{-- balans --}}
        <div class="acc-card market-balance mb-4">
            @if ($verified)
                <div class="points-balance small-ball">
                    <span class="points-num">{{ $fmt($balance) }}</span>
                    <span class="points-label">xal</span>
                </div>
                <div>
                    <h5 class="mb-1">Balansınız: {{ $fmt($balance) }} xal</h5>
                    <p class="mb-0 text-muted">Xal qazanmaq üçün profilinizdəki linki və ya QR kodu dostlarınızla paylaşın.
                        <a href="{{ route('account') }}#xallar" class="fw-bold">Xal qazan <i class="fa fa-arrow-right ms-1"></i></a></p>
                </div>
            @else
                <div class="acc-ico big"><i class="fa fa-lock"></i></div>
                <div>
                    <h5 class="mb-1">Hədiyyə almaq üçün daxil olun</h5>
                    <p class="mb-0 text-muted">Xallar yalnız Telegram ilə təsdiqlənmiş hesablarda toplanır.
                        @auth <a href="{{ route('verify.telegram') }}" class="fw-bold">Nömrənizi təsdiqləyin</a>
                        @else <a href="{{ route('login') }}" class="fw-bold">Daxil olun</a> və ya <a href="{{ route('register') }}" class="fw-bold">qeydiyyatdan keçin</a>
                        @endauth
                    </p>
                </div>
            @endif
        </div>

        {{-- hədiyyələr --}}
        <div class="market-grid">
            @foreach ($items as $item)
                @php
                    $cost = (float) $item['cost'];
                    $enough = $verified && $balance >= $cost;
                @endphp
                <div class="market-item {{ $enough ? '' : 'locked' }}">
                    <div class="market-ico"><i class="fa {{ $item['icon'] }}"></i></div>
                    <h5>{{ $item['label'] }}</h5>
                    <p>{{ $item['desc'] }}</p>
                    <div class="market-price"><i class="fa fa-star me-1"></i>{{ $fmt($cost) }} xal</div>
                    @if ($enough)
                        <form method="POST" action="{{ route('market.buy', $item['key']) }}"
                              onsubmit="return confirm({{ \Illuminate\Support\Js::from($item['label'] . ' — ' . $fmt($cost) . ' xal balansınızdan çıxılacaq. Davam edək?') }})">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100"><i class="fa fa-gift me-2"></i>Xalla al</button>
                        </form>
                    @elseif ($verified)
                        <button type="button" class="btn btn-ghost-dark w-100" disabled>{{ $fmt($cost - $balance) }} xal çatmır</button>
                    @else
                        <a href="{{ $authUrl }}" class="btn btn-ghost-dark w-100">Daxil olun</a>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="small text-muted mt-4 mb-0"><i class="fa fa-info-circle me-1"></i>Alınan hədiyyə kupon kodu kimi verilir və {{ (int) config('market.code_valid_days', 30) }} gün etibarlıdır.
            Hədiyyədən istifadə üçün minimal masa hesabı <b>30 AZN</b> olmalıdır. Xallar geri qaytarılmır.</p>
    </div>
</section>
@endsection
