@extends('frontend.layouts.app')
@section('title', 'Çarx oyunu - 50 Qəpik Oyunlar')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Çarx oyunu</h1>
        <p>Çarxı düz vaxtında dayandırın — {{ $attempts }} cəhdiniz var</p>
    </div>
</section>

@php
    $authUrl = auth()->check() ? route('verify.telegram') : route('register');
    $wheelSegments = collect($segments)->map(fn ($s) => [
        'key' => $s['key'], 'label' => $s['label'], 'short' => $s['short'] ?? null,
        'color' => $s['color'], 'deg' => round($s['deg'], 4),
    ])->values();
@endphp

<section class="section pt-4 games-scene">
    <div class="container">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>
        <div class="row g-5 align-items-center">
            <div class="col-lg-7">
                <div id="wheel" class="wheel"
                     data-segments='@json($wheelSegments)'
                     data-closed='@json($closed)'
                     data-spin-url="{{ route('games.wheel.spin') }}"
                     data-stop-url="{{ route('games.wheel.stop') }}"
                     data-claim-url="{{ route('games.wheel.claim') }}"
                     data-auth-url="{{ $authUrl }}"
                     data-verified="{{ $verified ? 1 : 0 }}"
                     data-attempts="{{ $attempts }}"
                     data-state='@json($state)'>
                    {{-- Çarxın arxasında dairəvi Azərbaycan xalçası: buta haşiyəsi, göllər, ziqzaq zolaq.
                         Çarx (radiusu ~130) xalçanın mərkəzini örtür — naxışlar ondan kənarda görünür. --}}
                    @php
                        $polar = fn ($deg, $r) => [round($r * cos(deg2rad($deg - 90)), 2), round($r * sin(deg2rad($deg - 90)), 2)];
                    @endphp
                    <svg class="wheel-carpet" viewBox="-200 -200 400 400" aria-hidden="true">
                        <defs>
                            <radialGradient id="wcGlow" r=".5">
                                <stop offset=".55" stop-color="#fff3df" stop-opacity=".9"/>
                                <stop offset="1" stop-color="#f3e2c8" stop-opacity="0"/>
                            </radialGradient>
                            <path id="wcButa" d="M0 -9 C6 -9 9 -3 7 3 C5 8 -1 10 -4 7 C-7 4 -5 -1 0 0 C3 1 3 -4 0 -9 Z"/>
                            <g id="wcGol">
                                <path d="M0 -15 L6 -6 L15 0 L6 6 L0 15 L-6 6 L-15 0 L-6 -6 Z" fill="#7E1A1A" fill-opacity=".3"/>
                                <path d="M0 -8 L8 0 L0 8 L-8 0 Z" fill="#E9A23B" fill-opacity=".55"/>
                                <circle r="2.4" fill="#7E1A1A" fill-opacity=".55"/>
                            </g>
                        </defs>

                        {{-- arxadakı yumşaq işıq --}}
                        <circle r="200" fill="url(#wcGlow)"/>

                        {{-- xarici haşiyə: bordo zolaq, içində butalar --}}
                        <circle r="194" fill="#7E1A1A" fill-opacity=".13"/>
                        <circle r="194" fill="none" stroke="#C9831F" stroke-opacity=".55" stroke-width="2"/>
                        <circle r="174" fill="#fbf1e2"/>
                        <circle r="174" fill="none" stroke="#C9831F" stroke-opacity=".55" stroke-width="2"/>
                        @for ($i = 0; $i < 24; $i++)
                            @php [$x, $y] = $polar($i * 15, 184); @endphp
                            <use href="#wcButa" transform="translate({{ $x }} {{ $y }}) rotate({{ $i * 15 + ($i % 2 ? 90 : -90) }}) scale(.9)" fill="#C9831F" fill-opacity=".5"/>
                        @endfor

                        {{-- orta sahə: göllər halqası --}}
                        <circle r="172" fill="#E9A23B" fill-opacity=".09"/>
                        @for ($i = 0; $i < 12; $i++)
                            @php [$x, $y] = $polar($i * 30 + 15, 156); @endphp
                            <use href="#wcGol" transform="translate({{ $x }} {{ $y }}) rotate({{ $i * 30 + 15 }})"/>
                            @php [$x, $y] = $polar($i * 30, 160); @endphp
                            <circle cx="{{ $x }}" cy="{{ $y }}" r="2.2" fill="#C9831F" fill-opacity=".55"/>
                        @endfor

                        {{-- çarxın kənarında ziqzaq zolaq --}}
                        <circle r="141" fill="none" stroke="#7E1A1A" stroke-opacity=".35" stroke-width="1.5"/>
                        @php
                            $zig = [];
                            for ($i = 0; $i <= 96; $i++) { $zig[] = implode(',', $polar($i * 3.75, $i % 2 ? 133 : 139)); }
                        @endphp
                        <polyline points="{{ implode(' ', $zig) }}" fill="none" stroke="#C9831F" stroke-opacity=".6" stroke-width="1.4" stroke-linejoin="round"/>
                    </svg>

                    <div class="wheel-pointer"></div>
                    <svg class="wheel-svg" viewBox="-110 -110 220 220" aria-hidden="true"></svg>
                    <button type="button" class="wheel-btn" id="wheelSpin">Fırlat</button>

                    {{-- Çarx dayananda nəticə çarxın üzərində modal kimi göstərilir (telefonda panel aşağıda qalır) --}}
                    <div class="wheel-modal" id="wheelModal" hidden>
                        <div class="wheel-modal-card" role="dialog" aria-modal="true" aria-label="Nəticə">
                            <button type="button" class="wheel-modal-close" aria-label="Bağla">&times;</button>
                            <div class="wheel-modal-body wheel-result" aria-live="polite"></div>
                        </div>
                    </div>
                    <button type="button" class="wheel-reopen" id="wheelReopen" hidden><i class="fa fa-gift me-2"></i>Nəticəni göstər</button>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="side-card wheel-panel">
                    <div class="attempts" id="wheelAttempts" aria-live="polite"></div>

                    {{-- Nəticə və seçim düymələri JS ilə doldurulur --}}
                    <div id="wheelResult" class="wheel-result" hidden></div>

                    <div id="wheelIntro" data-game-rules="Çarx oyunu">
                        <h4>Necə işləyir?</h4>
                        <div class="rules-note"><i class="fa fa-info-circle me-2"></i>Hədiyyədən istifadə üçün minimal masa hesabı <b>30 AZN</b> olmalıdır.</div>
                        <ul class="rules">
                            <li><b>Fırlat</b> basın — çarx dayanmadan fırlanır. <b>Dayan</b> basın (və ya çarxa toxunun) — göstəricinin altındakı sektor sizindir. Burada şans yox, <b>dəqiqlik</b> qərar verir.</li>
                            <li>Dayandırmaq üçün <b>{{ config('games.wheel.max_seconds') }} saniyəniz</b> var. Vaxt bitsə, çarx özü dayanır, amma hədiyyə verilmir — cəhd <b>uğursuz</b> sayılır.</li>
                            <li>Hədiyyə nə qədər böyükdürsə, sektoru o qədər <b>dardır</b>. Çarxın sürəti də sabit deyil — gah artır, gah azalır, bəzən isə çarx yavaşlayıb <b>əks tərəfə</b> fırlanır. Diqqətlə izləyin!</li>
                            <li>Sizin <b>{{ $attempts }} cəhdiniz</b> var. "Fırlat" basan kimi cəhd sayılır; hər növbəti cəhddə çarx <b>daha sürətli</b> fırlanır.</li>
                            <li>Hər cəhddən sonra seçin: <b>Hədiyyəni götür</b> və ya <b>yenidən fırlat</b>. Yenidən fırladanda əvvəlki nəticə itir — risk sizindir!</li>
                            <li>Böyük hədiyyələrin (50%, 25%, qəlyan) gündəlik sayı məhduddur — bitəndə sektor gün sonunadək <b>bağlanır</b>.</li>
                            <li>Hədiyyəni götürəndə <b>kod</b> yaranır — restoranda ofisianta göstərin. Endirim <b>hazırda oturduğunuz masanın hesabına</b> tətbiq olunur: kod {{ config('games.wheel.code_valid_minutes') }} dəqiqə etibarlıdır, sonra və ya istifadə olunanda qüvvədən düşür. Bütün kodlarınız: <a href="{{ route('prizes') }}">Hədiyyələrim</a>.</li>
                            {{-- Hədiyyə Kranı müvəqqəti bağlı olanda bu qayda göstərilmir --}}
                            @if (config('games.claw.enabled'))
                            <li>Gündə yalnız <b>bir oyunda</b> hədiyyə götürmək olar — Çarx oyununda və ya Hədiyyə Kranında. Hədiyyə götürdükdən sonra hər iki oyunu əyləncə üçün istədiyiniz qədər oynaya bilərsiniz, amma həmin gün yeni hədiyyə verilmir.</li>
                            @else
                            <li>Hədiyyə götürdükdən sonra çarxı əyləncə üçün istədiyiniz qədər fırlada bilərsiniz, amma həmin gün yeni hədiyyə verilmir.</li>
                            @endif
                        </ul>
                        @unless ($verified)
                            <div class="alert-soft mt-3">
                                Qonaq kimi yalnız <b>sınaq</b> fırlatması edə bilərsiniz.
                                Real hədiyyə üçün
                                @auth <a href="{{ route('verify.telegram') }}">profilinizi təsdiqləyin</a>
                                @else <a href="{{ route('register') }}">qeydiyyatdan keçin</a> və ya <a href="{{ route('login') }}">daxil olun</a>
                                @endauth.
                            </div>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script src="{{ asset('js/games/wheel.js') }}?v=19"></script>
@endsection
