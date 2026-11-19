@extends('frontend.layouts.app')
@section('title', 'Oyunlar - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">50 Qəpik</span>
        <h1>Oyunlar</h1>
        <p>Yeməyinizi gözləyərkən əylənin — hədiyyələr və xallar qazanın</p>
        @auth
            @if (auth()->user()->isVerified())
                {{-- Balans: dəvət və oyun bonuslarından toplanan xallar (Hədiyyə marketində hədiyyəyə çevrilir) --}}
                <a href="{{ route('market') }}" class="hero-points" title="Hədiyyə marketinə keç">
                    <span class="hero-points-ico"><i class="fa fa-star"></i></span>
                    <span class="hero-points-text">Balansınız: <b>{{ \App\Services\Referrals::format((float) auth()->user()->points) }} xal</b></span>
                    <span class="hero-points-go">Hədiyyə marketi <i class="fa fa-arrow-right ms-1"></i></span>
                </a>
            @endif
        @endauth
    </div>
</section>

<section class="section pt-4 games-scene">
    <div class="container">
        <div class="row g-4">
            {{-- Hədiyyə marketi ən əvvəldə, oyunlardan fərqli: tam enli üfüqi banner --}}
            <div class="col-12">
                <a href="{{ route('market') }}" class="game-card market-feature">
                    <div class="game-art market-art">
                        {{-- hədiyyə qutusu: qapağı yellənir, ətrafında xal ulduzları --}}
                        <svg class="market-art-svg" viewBox="0 0 180 160" aria-hidden="true">
                            <g class="ma-stars" fill="#E9A23B">
                                <path d="M30 40 l3 7 7 1 -5 5 1 7 -6 -4 -6 4 1 -7 -5 -5 7 -1z"/>
                                <path d="M148 30 l2.4 5.6 5.6 .8 -4 4 .8 5.6 -4.8 -3.2 -4.8 3.2 .8 -5.6 -4 -4 5.6 -.8z"/>
                                <path d="M152 100 l2 4.6 4.6 .7 -3.3 3.3 .7 4.6 -4 -2.6 -4 2.6 .7 -4.6 -3.3 -3.3 4.6 -.7z"/>
                                <path d="M22 108 l2 4.6 4.6 .7 -3.3 3.3 .7 4.6 -4 -2.6 -4 2.6 .7 -4.6 -3.3 -3.3 4.6 -.7z"/>
                            </g>
                            <rect x="50" y="78" width="80" height="66" rx="6" fill="#7E1A1A"/>
                            <rect x="84" y="78" width="12" height="66" fill="#E9A23B"/>
                            <g class="ma-lid">
                                <rect x="44" y="62" width="92" height="20" rx="5" fill="#B3261E"/>
                                <rect x="84" y="62" width="12" height="20" fill="#E9A23B"/>
                                <path d="M90 62 C78 40 58 46 70 58 C76 63 86 62 90 62 Z" fill="#E9A23B"/>
                                <path d="M90 62 C102 40 122 46 110 58 C104 63 94 62 90 62 Z" fill="#E9A23B"/>
                            </g>
                        </svg>
                    </div>
                    <div class="game-body">
                        <span class="market-feature-tag"><i class="fa fa-star me-1"></i>Xallarınızı xərcləyin</span>
                        <h4>Hədiyyə marketi</h4>
                        <p>Oyunlarda və dəvətlərlə topladığınız xalları hədiyyəyə çevirin: çay, mürəbbə, qəlyan və endirim kuponları.</p>
                        <span class="game-go market-feature-go">Hədiyyə seç <i class="fa fa-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('games.stack') }}" class="game-card">
                    <div class="game-art stack-art">
                        {{-- oyundakı kimi: rəsm fonunda xalça süfrəli masa, üstündə qüllə və yellənən stəkan --}}
                        <svg class="stack-art-svg" viewBox="0 0 320 190" preserveAspectRatio="xMidYMax meet" aria-hidden="true">
                            <defs>
                                <path id="saGlassShape" d="M12.5 72 C2.1 69.8 0 46.1 8.8 30.2 C11.4 24.5 9.9 10.1 3.1 0 A22.9 3.5 0 0 0 48.9 0 C42.1 10.1 40.6 24.5 43.2 30.2 C52 46.1 49.9 69.8 39.5 72 Z"/>
                                <clipPath id="saGlassClip"><use href="#saGlassShape"/></clipPath>
                                <linearGradient id="saTea" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#cd2a0a" stop-opacity=".92"/>
                                    <stop offset=".55" stop-color="#a01204"/>
                                    <stop offset="1" stop-color="#620602"/>
                                </linearGradient>
                                <linearGradient id="saTopShade" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#000" stop-opacity=".4"/>
                                    <stop offset="1" stop-color="#fff3dc" stop-opacity=".12"/>
                                </linearGradient>
                                <clipPath id="saTopClip"><path d="M58 150 H262 L284 166 H36 Z"/></clipPath>
                                <clipPath id="saFrontClip"><path d="M36 166 H284 L290 190 H30 Z"/></clipPath>
                                {{-- bir armudu stəkan: dib (26, 72) nöqtəsindədir --}}
                                <g id="saGlass">
                                    <use href="#saGlassShape" fill="rgba(255,255,255,.22)"/>
                                    <g clip-path="url(#saGlassClip)">
                                        <rect x="0" y="18.7" width="52" height="54" fill="url(#saTea)"/>
                                        <rect x="0" y="18.7" width="52" height="1.6" fill="rgba(255,190,120,.7)"/>
                                    </g>
                                    <use href="#saGlassShape" fill="none" stroke="#fff" stroke-width="2"/>
                                    <path d="M26 14.4 C27.8 20 37 22 34.8 36 C37 50 27.8 52 26 57.6 C24.2 52 15 50 17.2 36 C15 22 24.2 20 26 14.4 Z" fill="none" stroke="#f2c35a" stroke-width="1.4"/>
                                    <path d="M26 22.5 C27 26 31.6 28 30.5 36 C31.6 44 27 46 26 49.5 C25 46 20.4 44 21.5 36 C20.4 28 25 26 26 22.5 Z" fill="none" stroke="#f2c35a" stroke-width=".8"/>
                                    <circle cx="26" cy="11" r="1.6" fill="#f2c35a"/><circle cx="26" cy="61" r="1.6" fill="#f2c35a"/>
                                    <ellipse cx="26" cy="0" rx="22.9" ry="3.5" fill="rgba(255,255,255,.2)" stroke="#E9A23B" stroke-width="2.2"/>
                                    <ellipse cx="26" cy="72" rx="14.6" ry="4" fill="#E9A23B"/>
                                    <path d="M8.8 62.6 Q5.2 48.2 11.4 31.7" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="3" stroke-linecap="round"/>
                                </g>
                            </defs>

                            {{-- masa: perspektivdə üst səth və aşağı sallanan xalça süfrə --}}
                            <ellipse cx="160" cy="188" rx="140" ry="6" fill="rgba(0,0,0,.4)"/>
                            <image href="{{ asset('img/games/stack-cloth.webp') }}" x="20" y="120" width="280" height="140" preserveAspectRatio="xMidYMid slice" clip-path="url(#saTopClip)"/>
                            <path d="M58 150 H262 L284 166 H36 Z" fill="url(#saTopShade)"/>
                            <image href="{{ asset('img/games/stack-cloth.webp') }}" x="20" y="110" width="280" height="140" preserveAspectRatio="xMidYMid slice" clip-path="url(#saFrontClip)"/>
                            <path d="M36 166 H284 L290 190 H30 Z" fill="rgba(0,0,0,.22)"/>
                            <path d="M36 166 H284" stroke="#d9a237" stroke-width="2.5"/>
                            <path d="M36 166 L58 150 H262 L284 166" fill="none" stroke="rgba(217,162,55,.6)"/>

                            {{-- nəlbəki və qüllə --}}
                            <ellipse cx="162" cy="160" rx="28" ry="5.5" fill="rgba(0,0,0,.35)"/>
                            <ellipse cx="160" cy="158" rx="26" ry="5" fill="rgba(235,240,245,.6)" stroke="#E9A23B" stroke-width="1.6"/>
                            <use href="#saGlass" transform="translate(143.9 113.4) scale(.62)"/>
                            <use href="#saGlass" transform="translate(146.5 68.8) scale(.62)"/>

                            {{-- yellənən stəkan və onun düşəcəyi yer --}}
                            <g class="sa-swing">
                                <line x1="160" y1="52" x2="160" y2="64" stroke="rgba(255,255,255,.45)" stroke-dasharray="3 4"/>
                                <use href="#saGlass" transform="translate(143.9 7.4) scale(.62)"/>
                            </g>
                        </svg>
                    </div>
                    <div class="game-body">
                        <h4>Stəkan qülləsi</h4>
                        <p>Armudu stəkanları üst-üstə düzün — qüllə aşmasın! Ən hündür qüllə rekordlara düşür.</p>
                        <span class="game-go">Oyna <i class="fa fa-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('games.wheel') }}" class="game-card">
                    <div class="game-art wheel-art">
                        {{-- oyundakı çarxın kiçik surəti: sektorlar config('games.wheel.segments')-dən gəlir --}}
                        @php
                            $segs = array_values(config('games.wheel.segments'));
                            $n = count($segs);
                            $sizeSum = array_sum(array_map(fn ($s) => (float) ($s['size'] ?? 1), $segs)) ?: 1;
                            $R = 90; $C = 100;
                            $edge = -90 - (float) ($segs[0]['size'] ?? 1) * 180 / $sizeSum; // 0-cı sektorun ortası yuxarıda
                            $edges = [];
                            $pt = fn ($deg, $r) => [round($C + $r * cos(deg2rad($deg)), 2), round($C + $r * sin(deg2rad($deg)), 2)];
                        @endphp
                        <svg class="wheel-art-svg" viewBox="0 0 200 200" aria-hidden="true">
                            <circle cx="100" cy="100" r="97" fill="#E9A23B"/>
                            <g class="wa-spin">
                                @foreach ($segs as $i => $s)
                                    @php
                                        // sektorun eni çarxdakı kimi (config: size) — böyük hədiyyələr dar
                                        $deg = (float) ($s['size'] ?? 1) * 360 / $sizeSum;
                                        $edges[] = $edge;
                                        $mid = $edge + $deg / 2;
                                        [$x0, $y0] = $pt($edge, $R);
                                        [$x1, $y1] = $pt($edge + $deg, $R);
                                        $edge += $deg;
                                        [$tx, $ty] = $pt($mid, $R * 0.6);
                                        $arc = $R * 0.6 * deg2rad($deg);
                                        $text = $arc < 20 ? ($s['short'] ?? $s['label']) : $s['label'];
                                        $words = explode(' ', $text);
                                        $lines = mb_strlen($text) > 12 && count($words) > 1
                                            ? [implode(' ', array_slice($words, 0, (int) ceil(count($words) / 2))), implode(' ', array_slice($words, (int) ceil(count($words) / 2)))]
                                            : [$text];
                                        $fs = round(min(9, $arc * 0.75), 2);
                                    @endphp
                                    <path d="M100 100 L{{ $x0 }} {{ $y0 }} A{{ $R }} {{ $R }} 0 {{ $deg > 180 ? 1 : 0 }} 1 {{ $x1 }} {{ $y1 }} Z" fill="{{ $s['color'] }}" stroke="#fff" stroke-width="1.2"/>
                                    <text transform="translate({{ $tx }} {{ $ty }}) rotate({{ $mid + 180 }})" text-anchor="middle" font-size="{{ $fs }}" font-weight="800" fill="#fff">
                                        @foreach ($lines as $l => $line)
                                            <tspan x="0" dy="{{ $l === 0 ? (count($lines) > 1 ? -2.5 : 3) : 10 }}">{{ $line }}</tspan>
                                        @endforeach
                                    </text>
                                @endforeach
                                @foreach ($edges as $e)
                                    @php [$dx, $dy] = $pt($e, 93.5); @endphp
                                    <circle cx="{{ $dx }}" cy="{{ $dy }}" r="2.2" fill="#fff"/>
                                @endforeach
                            </g>
                            <circle cx="100" cy="100" r="24" fill="#E9A23B" stroke="#fff" stroke-width="3"/>
                            <text x="100" y="103.5" text-anchor="middle" font-size="9.5" font-weight="900" fill="#fff">FIRLAT</text>
                            <path d="M91 -2 H109 L100 14 Z" fill="#1e0b0b"/>
                        </svg>
                    </div>
                    <div class="game-body">
                        <h4>Çarx oyunu</h4>
                        <p>Çarxı düz vaxtında dayandırın: hədiyyə böyüdükcə sektor daralır.</p>
                        <span class="game-go">Oyna <i class="fa fa-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
            {{-- Nərd müvəqqəti gizlədilə bilər — geri açmaq üçün .env: NARD_ENABLED=true --}}
            @if (config('games.nard.enabled'))
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('games.nard') }}" class="game-card">
                    <div class="game-art nard-art">
                        {{-- oyundakı lövhənin kiçik surəti: başlanğıc düzülüşü və fırlanan zərlər --}}
                        <div class="na-board">
                            <svg viewBox="0 0 300 200" aria-hidden="true">
                                <defs>
                                    <linearGradient id="naWood" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="#8a5228"/><stop offset="1" stop-color="#6b3b1c"/>
                                    </linearGradient>
                                    <radialGradient id="naW" cx=".35" cy=".3" r=".75"><stop offset="0" stop-color="#fffdf7"/><stop offset="1" stop-color="#e9dcc3"/></radialGradient>
                                    <radialGradient id="naB" cx=".35" cy=".3" r=".75"><stop offset="0" stop-color="#a83232"/><stop offset="1" stop-color="#4a0e0e"/></radialGradient>
                                </defs>
                                <rect width="300" height="200" rx="10" fill="#4a250f"/>
                                <rect x="6" y="6" width="288" height="188" rx="6" fill="url(#naWood)"/>
                                @for ($i = 0; $i < 12; $i++)
                                    @php
                                        $x = $i < 6 ? 10 + $i * 22.5 : 156 + ($i - 6) * 22.5;
                                        $dark = $i % 2 === 0;
                                    @endphp
                                    {{-- yuxarı və aşağı sıranın üçbucaqları (rənglər növbələşir) --}}
                                    <path d="M{{ $x + 1 }} 10 H{{ $x + 21.5 }} L{{ $x + 11.25 }} 88 Z" fill="{{ $dark ? '#3a0d0d' : '#e8c99b' }}" opacity=".9"/>
                                    <path d="M{{ $x + 1 }} 190 H{{ $x + 21.5 }} L{{ $x + 11.25 }} 112 Z" fill="{{ $dark ? '#e8c99b' : '#3a0d0d' }}" opacity=".9"/>
                                @endfor
                                <rect x="147" y="10" width="6" height="80" rx="3" fill="#4a250f"/>
                                <rect x="147" y="110" width="6" height="80" rx="3" fill="#4a250f"/>
                                {{-- başlar: ağ daşlar sağ yuxarıda, qırmızılar sol aşağıda, hərəsi 15 --}}
                                @for ($k = 0; $k < 5; $k++)
                                    <circle cx="279.25" cy="{{ 20 + $k * 18 }}" r="10" fill="url(#naW)" stroke="#b79a6a" stroke-width="1.2"/>
                                    <circle cx="21.25" cy="{{ 180 - $k * 18 }}" r="10" fill="url(#naB)" stroke="#2a0606" stroke-width="1.2"/>
                                @endfor
                                <text x="279.25" y="95" text-anchor="middle" font-size="8" font-weight="900" fill="#1E0B0B">15</text>
                                <text x="21.25" y="111" text-anchor="middle" font-size="8" font-weight="900" fill="#fff">15</text>
                            </svg>
                            {{-- zərlər: bir neçə saniyədən bir fırlanıb yeni rəqəm göstərir --}}
                            <div class="na-dice">
                                <span class="na-die"></span><span class="na-die"></span><span class="na-double" hidden>×4</span>
                            </div>
                        </div>
                    </div>
                    <div class="game-body">
                        <h4>Uzun nərd</h4>
                        <p>Dostunuzu dəvət edib oynayın — hər gün ən çox xal toplayanlar endirim qazanır. Kompüterlə də məşq edə bilərsiniz.</p>
                        <span class="game-go">Oyna <i class="fa fa-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
            @endif
            {{-- Hədiyyə Kranı müvəqqəti bağlıdır — geri açmaq üçün .env: CLAW_ENABLED=true --}}
            @if (config('games.claw.enabled'))
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('games.claw') }}" class="game-card">
                    <div class="game-art claw-art">
                        {{-- kiçik kran maşını: zolaqlı tente, şüşədə şirniyyatlar, sağa-sola gedən çəngəl --}}
                        <svg class="claw-art-svg" viewBox="0 0 160 200" aria-hidden="true">
                            <rect x="4" y="4" width="152" height="192" rx="14" fill="#7E1A1A"/>
                            <rect x="14" y="10" width="132" height="26" rx="7" fill="#3A0D0D" stroke="#E9A23B" stroke-width="2"/>
                            <text x="80" y="28" text-anchor="middle" font-family="Playfair Display, Georgia, serif" font-weight="700" font-size="13" fill="#E9A23B">50 Qəpik</text>
                            <rect x="12" y="50" width="136" height="110" fill="#3a1512" stroke="#E9A23B" stroke-width="3"/>
                            @for ($i = 0; $i < 8; $i++)
                                <path d="M{{ 6 + $i * 18.5 }} 38 h18.5 v8 a9.25 9.25 0 0 1 -18.5 0 Z" fill="{{ $i % 2 ? '#FBF6EF' : '#E9A23B' }}"/>
                            @endfor
                            <rect x="12" y="62" width="136" height="3" fill="#C9831F"/>
                            {{-- rəngli hədiyyə şarları --}}
                            @foreach ([[62, 147, '#e63946'], [88, 148, '#3a86ff'], [114, 147, '#ffbe0b'], [138, 146, '#06d6a0'], [75, 126, '#8338ec'], [101, 126, '#ff006e'], [126, 125, '#2a9d8f']] as [$bx, $by, $bc])
                                <circle cx="{{ $bx }}" cy="{{ $by }}" r="11.5" fill="{{ $bc }}"/>
                                <path d="M{{ $bx - 11 }} {{ $by + 1 }} a11 3.5 0 0 0 22 0" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="1"/>
                                <ellipse cx="{{ $bx - 4 }}" cy="{{ $by - 5 }}" rx="4" ry="2" fill="rgba(255,255,255,.8)" transform="rotate(-30 {{ $bx - 4 }} {{ $by - 5 }})"/>
                            @endforeach
                            <rect x="44" y="118" width="3" height="42" fill="rgba(255,255,255,.3)"/>
                            {{-- çəngəl --}}
                            <g class="ca-claw">
                                <rect x="88" y="58" width="18" height="9" rx="3" fill="#E9A23B"/>
                                <line x1="97" y1="66" x2="97" y2="88" stroke="#d8c6a8" stroke-width="1.5"/>
                                <rect x="90" y="86" width="14" height="9" rx="3" fill="#E9A23B"/>
                                <path d="M93 95 l-7 9 l3 8 M101 95 l7 9 l-3 8" fill="none" stroke="#d9d2c4" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </g>
                            <rect x="12" y="160" width="136" height="3" fill="#E9A23B"/>
                            <rect x="18" y="168" width="34" height="16" rx="4" fill="#1E0B0B" stroke="#E9A23B" stroke-width="1.5"/>
                            <circle cx="122" cy="176" r="8" fill="#c62a2a" stroke="#f6cf6b" stroke-width="2"/>
                            <circle cx="100" cy="176" r="5" fill="#1E0B0B" stroke="#E9A23B" stroke-width="1.5"/>
                        </svg>
                    </div>
                    <div class="game-body">
                        <h4>Hədiyyə Kranı</h4>
                        <p>Rəngli şarı tutub çıxışa salın — içindən endirim, çay və ya qəlyan çıxsın! Gündə 3 cəhd.</p>
                        <span class="game-go">Oyna <i class="fa fa-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
            @endif
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('games.quiz') }}" class="game-card">
                    <div class="game-art qz-art">
                        {{-- test ekranı: sual, A/B/C variantları, 5 saniyəlik taymer, canlar — "B" (düz cavab) yaşıl yanır --}}
                        <svg class="qz-art-svg" viewBox="0 0 260 170" aria-hidden="true">
                            {{-- arxada üzən sual işarələri --}}
                            <text class="qz2-float" x="14" y="56" font-size="30">?</text>
                            <text class="qz2-float qz2-float-b" x="230" y="136" font-size="26">?</text>
                            <text class="qz2-float qz2-float-c" x="232" y="40" font-size="18">!</text>

                            {{-- test kartı, yuxarıda bayraq rəngləri --}}
                            <clipPath id="qz2Card"><rect x="40" y="8" width="180" height="156" rx="16"/></clipPath>
                            <rect x="40" y="8" width="180" height="156" rx="16" fill="#fffaf0"/>
                            <g clip-path="url(#qz2Card)">
                                <rect x="40" y="8" width="60" height="5" fill="#00b5e2"/>
                                <rect x="100" y="8" width="60" height="5" fill="#ef3340"/>
                                <rect x="160" y="8" width="60" height="5" fill="#509e2f"/>
                            </g>

                            {{-- başlıq: sual nömrəsi, canlar, taymer --}}
                            <text x="54" y="30" font-size="9" fill="#9a8478" letter-spacing=".6">SUAL 3 / 10</text>
                            <g fill="#e0414f" transform="translate(54 36) scale(.42)">
                                <path d="M10 18 C-4 8 2 -2 10 4 C18 -2 24 8 10 18Z"/>
                                <path transform="translate(26 0)" d="M10 18 C-4 8 2 -2 10 4 C18 -2 24 8 10 18Z"/>
                                <path transform="translate(52 0)" fill="#eadfd3" d="M10 18 C-4 8 2 -2 10 4 C18 -2 24 8 10 18Z"/>
                            </g>
                            <circle cx="198" cy="32" r="14" fill="none" stroke="#f3ebe2" stroke-width="4"/>
                            <circle class="qz2-timer" cx="198" cy="32" r="14" fill="none" stroke="#e9a23b" stroke-width="4" stroke-linecap="round" transform="rotate(-90 198 32)"/>
                            <text x="198" y="36.5" font-size="12" text-anchor="middle" fill="#7E1A1A">5</text>

                            {{-- sual --}}
                            <text x="54" y="70" font-size="13" fill="#2b1a12">Azərbaycanın paytaxtı?</text>

                            {{-- variantlar --}}
                            <rect x="54" y="80" width="152" height="22" rx="11" fill="#f5ece2"/>
                            <circle cx="66" cy="91" r="7" fill="#fff" stroke="#eadfd3"/>
                            <text x="66" y="94.5" font-size="9" text-anchor="middle" fill="#7E1A1A">A</text>
                            <text x="80" y="95" font-size="11" fill="#5a4a44">Gəncə</text>

                            <rect class="qz2-right" x="54" y="107" width="152" height="22" rx="11"/>
                            <circle class="qz2-right-key" cx="66" cy="118" r="7"/>
                            <text class="qz2-right-letter" x="66" y="121.5" font-size="9" text-anchor="middle">B</text>
                            <text x="80" y="122" font-size="11" fill="#2b1a12">Bakı</text>
                            <path class="qz2-check" d="M186 118 l4 4 l8 -9" fill="none" stroke="#1e9e57" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>

                            <rect x="54" y="134" width="152" height="22" rx="11" fill="#f5ece2"/>
                            <circle cx="66" cy="145" r="7" fill="#fff" stroke="#eadfd3"/>
                            <text x="66" y="148.5" font-size="9" text-anchor="middle" fill="#7E1A1A">C</text>
                            <text x="80" y="149" font-size="11" fill="#5a4a44">Şəki</text>

                            {{-- toxunuş --}}
                            <g class="qz2-tap">
                                <circle cx="150" cy="118" r="9" fill="#e9a23b" opacity=".35"/>
                                <circle cx="150" cy="118" r="4" fill="#e9a23b"/>
                            </g>
                        </svg>
                    </div>
                    <div class="game-body">
                        <span class="qz-card-tag"><i class="fa fa-list-ol me-1"></i>Test · 3 variant · 5 saniyə</span>
                        <h4>Bilik Yarışı</h4>
                        <p>Azərbaycanı nə qədər tanıyırsınız? Hər suala {{ (int) config('quiz.seconds', 5) }} saniyə, mərhələ keçdikcə artan xallar: <b>+{{ \App\Http\Controllers\Frontend\QuizController::pointsLabel() }}, +{{ \App\Services\Referrals::format(\App\Http\Controllers\Frontend\QuizController::stageReward(2)) }}…</b></p>
                        <span class="game-go">Oyna <i class="fa fa-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-4 mt-1">
            @if (config('games.nard.enabled'))
            <div class="col-md-6">
                <div class="side-card h-100">
                    <h5><i class="fa fa-trophy me-2 text-primary"></i>Nərd — bu günün liderləri</h5>
                    @include('frontend.games._leaders', ['leaders' => $leaders])
                    @include('frontend.games._nard_winners', ['winners' => $lastWinners])
                </div>
            </div>
            @endif
            <div class="{{ config('games.nard.enabled') ? 'col-md-6' : 'col-12' }}">
                <div class="side-card h-100">
                    <h5><i class="fa fa-trophy me-2 text-primary"></i>Stəkan qülləsi liderləri</h5>
                    @include('frontend.games._leaders', ['leaders' => $stackLeaders, 'unit' => 'stəkan'])
                </div>
            </div>
        </div>

        <div class="side-card mt-4 mb-0" id="online-box" data-url="{{ route('games.online') }}">
            <h5 class="d-flex align-items-center">
                <span class="online-dot me-2"></span>İndi saytda
                <span class="online-count ms-2" id="online-count">{{ $onlineCount }}</span>
            </h5>
            <p class="text-muted mb-0 {{ $onlineUsers->isEmpty() ? '' : 'd-none' }}" id="online-empty">Hazırda saytda heç kim yoxdur.</p>
            <ul class="online-users" id="online-list">
                @foreach ($onlineUsers as $u)
                    <li class="{{ auth()->id() === $u->id ? 'me' : '' }}"><span class="online-dot"></span>{{ $u->short_name }}</li>
                @endforeach
                @if ($onlineCount > $onlineUsers->count())
                    <li class="more">+{{ $onlineCount - $onlineUsers->count() }} nəfər</li>
                @endif
            </ul>
        </div>
        @guest
            <p class="small text-muted mt-3 mb-0 text-center">Xallarınızın saxlanması üçün <a href="{{ route('register') }}">qeydiyyatdan keçin</a>.</p>
        @endguest
    </div>
</section>
@endsection

@section('js')
<script>
    // "İndi saytda" siyahısını səhifəni yeniləmədən hər 15 saniyədən bir yeniləyirik
    (function () {
        var box = document.getElementById('online-box');
        var list = document.getElementById('online-list');
        var count = document.getElementById('online-count');
        var empty = document.getElementById('online-empty');

        function render(data) {
            count.textContent = data.count;
            empty.classList.toggle('d-none', data.users.length > 0);
            list.innerHTML = '';
            data.users.forEach(function (u) {
                var li = document.createElement('li');
                if (u.me) li.className = 'me';
                var dot = document.createElement('span');
                dot.className = 'online-dot';
                li.appendChild(dot);
                li.appendChild(document.createTextNode(u.name));
                list.appendChild(li);
            });
            if (data.count > data.users.length) {
                var more = document.createElement('li');
                more.className = 'more';
                more.textContent = '+' + (data.count - data.users.length) + ' nəfər';
                list.appendChild(more);
            }
        }

        function refresh() {
            if (document.hidden) return;
            fetch(box.dataset.url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) { if (data) render(data); })
                .catch(function () {});
        }

        setInterval(refresh, 15000);
        document.addEventListener('visibilitychange', refresh);
    })();

    // Nərd kartı: zərlər bir neçə saniyədən bir fırlanıb yeni rəqəm göstərir (kartın üstünə gələndə dərhal)
    (function () {
        var dice = document.querySelectorAll('.na-die');
        var dbl = document.querySelector('.na-double');
        if (dice.length !== 2) return;
        var PIPS = {1: [5], 2: [1, 9], 3: [1, 5, 9], 4: [1, 3, 7, 9], 5: [1, 3, 5, 7, 9], 6: [1, 3, 4, 6, 7, 9]};
        var face = function (el, v) {
            var html = '';
            for (var i = 1; i <= 9; i++) html += '<i class="' + (PIPS[v].indexOf(i) >= 0 ? 'on' : '') + '"></i>';
            el.innerHTML = html;
        };
        var rnd = function () { return 1 + Math.floor(Math.random() * 6); };
        var rolling = false;

        function roll() {
            if (rolling || document.hidden) return;
            rolling = true;
            dbl.hidden = true;
            dice.forEach(function (d) { d.classList.add('spin'); });
            var n = 0;
            var t = setInterval(function () {
                dice.forEach(function (d) { face(d, rnd()); });
                if (++n < 9) return;
                clearInterval(t);
                var a = rnd(), b = rnd();
                face(dice[0], a); face(dice[1], b);
                dice.forEach(function (d) { d.classList.remove('spin'); });
                dbl.hidden = a !== b; // cüt düşəndə "×4"
                rolling = false;
            }, 80);
        }

        face(dice[0], 6); face(dice[1], 3);
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        setInterval(roll, 3200);
        var card = dice[0].closest('.game-card');
        card && card.addEventListener('mouseenter', roll);
    })();
</script>
@endsection
