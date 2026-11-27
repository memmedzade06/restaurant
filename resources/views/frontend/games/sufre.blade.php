@extends('frontend.layouts.app')
@section('title', 'Süfrəni doldur - 50 Qəpik Oyunlar')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Süfrəni doldur</h1>
        <p>Sinini sağa-sola aparın, süfrə nemətlərini tutun, közərmiş kömürdən qaçın!</p>
    </div>
</section>

@php
    $canSave = auth()->check() && auth()->user()->isVerified();
    $g = \App\Services\SufreGame::class;
    // oyunun düsturları serverdəki hakimlə eyni olsun deyə sabitlər oradan gəlir
    $consts = [
        'W' => $g::WIDTH, 'H' => $g::HEIGHT, 'startY' => $g::START_Y, 'catchY' => $g::CATCH_Y,
        'half' => $g::BASKET_HALF, 'halfMin' => $g::HALF_MIN, 'shrinkFrom' => $g::SHRINK_FROM, 'shrinkRate' => $g::SHRINK_RATE, 'lives' => $g::LIVES, 'maxCombo' => $g::MAX_COMBO, 'comboStep' => $g::COMBO_STEP,
        'values' => $g::VALUES, 'sweetValues' => $g::SWEET_VALUES, 'maxSpeed' => (float) config('games.sufre.max_speed'),
        'maxSeconds' => (float) config('games.sufre.max_seconds'),
    ];
@endphp

<section class="section pt-4 games-scene">
    <div class="container">
        @include('frontend.games._topbar')
        <div class="row g-4">
            <div class="col-lg-7 col-xl-6">
                <div id="sufre" class="stack-game sufre-game"
                     data-can-save="{{ $canSave ? 1 : 0 }}"
                     data-start-url="{{ route('games.sufre.start') }}"
                     data-save-url="{{ route('games.sufre.result') }}"
                     data-prize-url="{{ route('games.sufre.prize') }}"
                     data-prizes-url="{{ route('prizes') }}"
                     data-prizes='@json($prizes)'
                     data-prize-left="{{ $prizeLeft ?? '' }}"
                     data-register-url="{{ auth()->check() ? route('verify.telegram') : route('register') }}"
                     data-bonus='@json($bonus)'
                     data-best="{{ $best ?? 0 }}"
                     data-cloth="{{ asset('img/games/stack-cloth.webp') }}?v=1"
                     data-debug="{{ app()->environment('local') && config('app.debug') ? 1 : 0 }}"
                     data-consts='@json($consts)'>
                    <div class="st-stage">
                        <canvas aria-label="Süfrəni doldur oyunu"></canvas>

                        <button type="button" class="st-full" title="Böyük ekran" aria-label="Böyük ekran"><i class="fa fa-expand"></i></button>

                        <div class="st-start">
                            <div class="st-start-box">
                                <i class="fa fa-hand-pointer"></i>
                                <b>Başlamaq üçün toxunun</b>
                                <span>Sinini barmaqla, siçanla və ya <kbd>←</kbd> <kbd>→</kbd> düymələri ilə aparın</span>
                            </div>
                        </div>

                        <div class="st-offer" hidden>
                            <div class="nb-result-box">
                                <span class="eyebrow">Təbriklər! 🎉</span>
                                <h3 class="st-offer-title"></h3>
                                <div class="st-choice">
                                    <button type="button" class="btn btn-primary st-accept"><i class="fa fa-gift me-2"></i><span>Hədiyyəni götür</span></button>
                                    <button type="button" class="btn btn-ghost-dark st-continue"><i class="fa fa-play me-2"></i><span>Davam et</span></button>
                                </div>
                                <p class="st-offer-note small mb-0"></p>
                            </div>
                        </div>

                        <div class="st-result" hidden>
                            <div class="nb-result-box">
                                <h3 class="st-title">Oyun bitdi</h3>
                                <div class="st-score">0</div>
                                <p class="st-note"></p>
                                <div class="st-prize" hidden></div>
                                <div class="st-choice">
                                    <button type="button" class="btn btn-primary px-5 st-again">Yenidən oyna</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 col-xl-6">
                <div class="game-side">
                    @if ($canSave)
                        <div class="side-card">
                            <h5><i class="fa fa-user-circle me-2 text-primary"></i>Sizin rekord</h5>
                            <p class="mb-0"><b id="sfBest" class="st-best">{{ $best }}</b> xal</p>
                            @if ($rank)
                                <p class="mb-0 small text-muted">Reytinqdə yeriniz: <b>{{ $rank }}</b></p>
                            @endif
                        </div>
                    @else
                        <div class="side-card side-cta">
                            <h5>Rekordunuz saxlanılsın?</h5>
                            <p>Nəticəniz liderlər cədvəlinə düşsün və xal bonusu qazanasınız deyə daxil olun və ya qeydiyyatdan keçin.</p>
                            <a href="{{ auth()->check() ? route('verify.telegram') : route('register') }}" class="btn btn-primary w-100 mb-2">{{ auth()->check() ? 'Profili təsdiqlə' : 'Qeydiyyat' }}</a>
                            @guest <a href="{{ route('login') }}" class="btn btn-ghost-dark w-100">Daxil ol</a> @endguest
                        </div>
                    @endif

                    <div class="side-card">
                        <h5><i class="fa fa-trophy me-2 text-primary"></i>Liderlər</h5>
                        @include('frontend.games._leaders', ['leaders' => $leaders, 'unit' => 'xal', 'initial' => 5])
                    </div>

                    <div class="side-card" data-game-rules="Süfrəni doldur">
                        <h5><i class="fa fa-book me-2 text-primary"></i>Necə oynanır?</h5>
                        <div class="rules-note"><i class="fa fa-info-circle me-2"></i>Minimal masa hesabı <b>{{ \App\Models\WheelSpin::minBill('sufre') }} AZN</b> olmalıdır.</div>
                        <ul class="rules">
                            <li>Oyun <b>{{ (int) config('games.sufre.max_seconds') }} saniyə</b> davam edir.</li>
                            <li><b>Közərmiş kömürə diqqət!</b> Kömür siniyə düşən kimi oyun bitir.</li>
                            @if ($bonus['tiers'])
                                <li>Xal bonusu:
                                    @foreach ($bonus['tiers'] as $t)
                                        <b>{{ $t['score'] }} xal — +{{ \App\Services\Referrals::format($t['points']) }} xal</b>@if (! $loop->last), @endif
                                    @endforeach
                                </li>
                            @endif
                            @foreach ($prizes as $min => $p)
                                <li><b>{{ $min }} xal</b> — <b>{{ $p['label'] }}</b></li>
                            @endforeach
                            <li>Hədiyyə xalına çatanda oyun dayanır: <b>«Hədiyyəni götür»</b> — kod verilir və oyun bitir; <b>davam etsəniz</b> — növbəti hədiyyəyə şans qazanırsınız, amma kömür siniyə düşsə, hədiyyə yanır. Gündə {{ (int) config('games.sufre.prizes_per_day', 1) }} hədiyyə.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script src="{{ asset('js/games/sufre-art.js') }}?v=4"></script>
<script src="{{ asset('js/games/sufre.js') }}?v=17"></script>
@endsection
