@extends('frontend.layouts.app')
@section('title', 'Oyunlar - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">50 Qəpik</span>
        <h1>Oyunlar</h1>
        <p>Yeməyinizi gözləyərkən əylənin — hədiyyələr və xallar qazanın</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="row g-4">
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
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('games.wheel') }}" class="game-card">
                    <div class="game-art wheel-art">
                        {{-- oyundakı çarxın kiçik surəti: sektorlar config('games.wheel.segments')-dən gəlir --}}
                        @php
                            $segs = config('games.wheel.segments');
                            $n = count($segs);
                            $R = 90; $C = 100;
                            $pt = fn ($deg, $r) => [round($C + $r * cos(deg2rad($deg)), 2), round($C + $r * sin(deg2rad($deg)), 2)];
                        @endphp
                        <svg class="wheel-art-svg" viewBox="0 0 200 200" aria-hidden="true">
                            <circle cx="100" cy="100" r="97" fill="#E9A23B"/>
                            <g class="wa-spin">
                                @foreach ($segs as $i => $s)
                                    @php
                                        $mid = -90 + $i * 360 / $n;
                                        [$x0, $y0] = $pt($mid - 180 / $n, $R);
                                        [$x1, $y1] = $pt($mid + 180 / $n, $R);
                                        [$tx, $ty] = $pt($mid, $R * 0.6);
                                        $words = explode(' ', $s['label']);
                                        $lines = mb_strlen($s['label']) > 12 && count($words) > 1
                                            ? [implode(' ', array_slice($words, 0, (int) ceil(count($words) / 2))), implode(' ', array_slice($words, (int) ceil(count($words) / 2)))]
                                            : [$s['label']];
                                    @endphp
                                    <path d="M100 100 L{{ $x0 }} {{ $y0 }} A{{ $R }} {{ $R }} 0 0 1 {{ $x1 }} {{ $y1 }} Z" fill="{{ $s['color'] }}" stroke="#fff" stroke-width="1.2"/>
                                    <text transform="translate({{ $tx }} {{ $ty }}) rotate({{ $mid + 180 }})" text-anchor="middle" font-size="9" font-weight="800" fill="#fff">
                                        @foreach ($lines as $l => $line)
                                            <tspan x="0" dy="{{ $l === 0 ? (count($lines) > 1 ? -2.5 : 3) : 10 }}">{{ $line }}</tspan>
                                        @endforeach
                                    </text>
                                @endforeach
                                @for ($i = 0; $i < $n * 2; $i++)
                                    @php [$dx, $dy] = $pt(-90 + $i * 180 / $n, 93.5); @endphp
                                    <circle cx="{{ $dx }}" cy="{{ $dy }}" r="2.2" fill="#fff"/>
                                @endfor
                            </g>
                            <circle cx="100" cy="100" r="24" fill="#E9A23B" stroke="#fff" stroke-width="3"/>
                            <text x="100" y="103.5" text-anchor="middle" font-size="9.5" font-weight="900" fill="#fff">FIRLAT</text>
                            <path d="M91 -2 H109 L100 14 Z" fill="#1e0b0b"/>
                        </svg>
                    </div>
                    <div class="game-body">
                        <h4>Bəxt çarxı</h4>
                        <p>Çarxı fırladın: endirimlər və hədiyyə qəlyan qazanın.</p>
                        <span class="game-go">Fırlat <i class="fa fa-arrow-right ms-1"></i></span>
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
        </div>

        <div class="row g-4 mt-1">
            <div class="col-md-6">
                <div class="side-card h-100">
                    <h5><i class="fa fa-trophy me-2 text-primary"></i>Nərd — bu günün liderləri</h5>
                    @include('frontend.games._leaders', ['leaders' => $leaders])
                    @include('frontend.games._nard_winners', ['winners' => $lastWinners])
                </div>
            </div>
            <div class="col-md-6">
                <div class="side-card h-100">
                    <h5><i class="fa fa-trophy me-2 text-primary"></i>Stəkan qülləsi liderləri</h5>
                    @include('frontend.games._leaders', ['leaders' => $stackLeaders])
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
