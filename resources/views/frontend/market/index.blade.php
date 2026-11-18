@extends('frontend.layouts.app')
@section('title', 'Hədiyyə marketi - 50 Qəpik')

@php
    $fmt = fn ($p) => \App\Services\Referrals::format((float) $p);
    $authUrl = auth()->check() ? route('verify.telegram') : route('login');
    $levels = \App\Services\Referrals::levels();
    $limitReached = $verified && $left <= 0;
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
    <div class="container">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>

        <div class="row g-4">
            {{-- Sol: balans və hədiyyələr --}}
            <div class="col-lg-8">
                @if ($bought)
                    {{-- yenicə alınmış hədiyyə: kupon kodu --}}
                    <div class="acc-card market-bought mb-4" role="status">
                        <span class="eyebrow">Təbriklər 🎉</span>
                        <h3>{{ $bought->prize_label }}</h3>
                        <div class="prize-code">{{ $bought->code }}</div>
                        <p class="small text-muted mb-0">Kodu restoranda ofisianta göstərin. {{ $bought->expires_at->timezone('Asia/Baku')->format('d.m.Y H:i') }} tarixinədək etibarlıdır.
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
                        <div class="flex-grow-1">
                            <h5 class="mb-1">Balansınız: {{ $fmt($balance) }} xal</h5>
                            <p class="mb-2 text-muted">Xal qazanmaq üçün profilinizdəki linki və ya QR kodu dostlarınızla paylaşın.
                                <a href="{{ route('account') }}#xallar" class="fw-bold">Xal qazan <i class="fa fa-arrow-right ms-1"></i></a></p>
                            <span class="market-left {{ $limitReached ? 'is-out' : '' }}">
                                <i class="fa fa-gift me-1"></i>Bu gün: <b>{{ $left }}</b> / {{ $perDay }} hədiyyə qalıb
                            </span>
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
                        <div class="market-item {{ $enough && ! $limitReached ? '' : 'locked' }}">
                            <div class="market-ico"><i class="fa {{ $item['icon'] }}"></i></div>
                            <h5>{{ $item['label'] }}</h5>
                            <p>{{ $item['desc'] }}</p>
                            <div class="market-price"><i class="fa fa-star me-1"></i>{{ $fmt($cost) }} xal</div>
                            @if (! $verified)
                                <a href="{{ $authUrl }}" class="btn btn-ghost-dark w-100">Daxil olun</a>
                            @elseif ($limitReached)
                                <button type="button" class="btn btn-ghost-dark w-100" disabled>Bu günün limiti bitib</button>
                            @elseif ($enough)
                                <form method="POST" action="{{ route('market.buy', $item['key']) }}"
                                      onsubmit="return confirm({{ \Illuminate\Support\Js::from($item['label'] . ' — ' . $fmt($cost) . ' xal balansınızdan çıxılacaq. Davam edək?') }})">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100"><i class="fa fa-gift me-2"></i>Xalla al</button>
                                </form>
                            @else
                                <button type="button" class="btn btn-ghost-dark w-100" disabled>{{ $fmt($cost - $balance) }} xal çatmır</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Sağ: qaydalar --}}
            <div class="col-lg-4">
                <div class="side-card market-rules">
                    <h5><i class="fa fa-book me-2 text-primary"></i>Qaydalar</h5>
                    <div class="rules-note"><i class="fa fa-info-circle me-2"></i>Hədiyyədən istifadə üçün minimal masa hesabı <b>{{ (int) config('market.min_bill', 10) }} AZN</b> olmalıdır.</div>
                    <ul class="rules">
                        <li>Gündə ən çox <b>{{ $perDay }} hədiyyə</b> almaq olar.</li>
                        <li>Hədiyyə seçəndə qiyməti <b>xal balansınızdan çıxılır</b> və sizə <b>kupon kodu</b> verilir.</li>
                        <li>Kodu restoranda <b>ofisianta göstərin</b>. Hər kod bir dəfə istifadə olunur.</li>
                        @php
                            $days = max(1, (int) config('market.code_valid_days', 1));
                        @endphp
                        <li>Kupon <b>{{ $days === 1 ? '1 gün (24 saat)' : $days . ' gün' }}</b> etibarlıdır — alındığı andan hesablanır, sonra qüvvədən düşür. Bütün kodlarınız: <a href="{{ route('prizes') }}">Hədiyyələrim</a>.</li>
                        <li>Xallar <b>geri qaytarılmır</b> və başqasına ötürülmür.</li>
                        @php
                            $earn = ['hər dost üçün', 'onun dəvət etdikləri üçün', 'onların dəvət etdikləri üçün'];
                            $earnParts = [];
                            foreach ($levels as $i => $p) {
                                $earnParts[] = e($earn[$i] ?? (($i + 1) . '-ci səviyyə üçün')) . ' <b>' . e($fmt($p)) . ' xal</b>';
                            }
                        @endphp
                        <li>Xal qazanmaq: dəvət linkiniz ilə qeydiyyatdan keçib nömrəsini təsdiqləyən {!! implode(', ', $earnParts) !!}.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
