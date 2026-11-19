@extends('frontend.layouts.app')
@section('title', 'Stəkan qülləsi - 50 Qəpik Oyunlar')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Stəkan qülləsi</h1>
        <p>Armudu stəkanları üst-üstə düzün — qüllə aşmasın!</p>
    </div>
</section>

@php
    $canSave = auth()->check() && auth()->user()->isVerified();
@endphp

<section class="section pt-4 games-scene">
    <div class="container">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>
        <div class="row g-4">
            <div class="col-lg-7 col-xl-6">
                <div id="stack" class="stack-game"
                     data-can-save="{{ $canSave ? 1 : 0 }}"
                     data-save-url="{{ route('games.stack.result') }}"
                     data-start-url="{{ route('games.stack.start') }}"
                     data-prize-url="{{ route('games.stack.prize') }}"
                     data-prizes-url="{{ route('prizes') }}"
                     data-prizes='@json($prizes)'
                     data-prize-left="{{ $prizeLeft ?? '' }}"
                     data-debug="{{ app()->environment('local') && config('app.debug') ? 1 : 0 }}"
                     data-weather='@json($weather)'
                     data-weather-url="{{ route('games.stack.weather') }}"
                     data-logo="{{ asset('img/games/stack-logo.png') }}"
                     data-music="{{ asset('audio/stack-music.mp3') }}?v=2"
                     data-cloth="{{ asset('img/games/stack-cloth.webp') }}?v=1"
                     data-bg-photo="{{ asset('img/games/stack-bg.jpg') }}?v=6"
                     data-register-url="{{ auth()->check() ? route('verify.telegram') : route('register') }}"
                     data-bonus='@json($bonus)'
                     data-best="{{ $best ?? 0 }}">
                    <div class="st-stage">
                        <canvas aria-label="Stəkan qülləsi oyunu"></canvas>

                        <button type="button" class="st-sound" title="Səsi bağla" aria-label="Səsi bağla"><i class="fa fa-volume-up"></i></button>
                        <button type="button" class="st-full" title="Böyük ekran" aria-label="Böyük ekran"><i class="fa fa-expand"></i></button>

                        <div class="st-start">
                            <div class="st-start-box">
                                <i class="fa fa-hand-pointer"></i>
                                <b>Başlamaq üçün toxunun</b>
                                <span>Stəkan düşsün deyə ekrana toxunun və ya <kbd>Boşluq</kbd> basın</span>
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
                                <h3 class="st-title"></h3>
                                <div class="st-score">0</div>
                                <p class="st-note"></p>
                                <div class="st-prize" hidden></div>
                                <div class="st-choice">
                                    <button type="button" class="btn btn-primary st-resume" hidden><i class="fa fa-play me-2"></i>Davam et</button>
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
                            <p class="mb-0"><b id="stBest" class="st-best">{{ $best }}</b> xal</p>
                        </div>
                    @else
                        <div class="side-card side-cta">
                            <h5>Rekordunuz saxlanılsın?</h5>
                            <p>Nəticəniz liderlər cədvəlinə düşsün deyə daxil olun və ya qeydiyyatdan keçin.</p>
                            <a href="{{ auth()->check() ? route('verify.telegram') : route('register') }}" class="btn btn-primary w-100 mb-2">{{ auth()->check() ? 'Profili təsdiqlə' : 'Qeydiyyat' }}</a>
                            @guest <a href="{{ route('login') }}" class="btn btn-ghost-dark w-100">Daxil ol</a> @endguest
                        </div>
                    @endif

                    <div class="side-card">
                        <h5><i class="fa fa-trophy me-2 text-primary"></i>Liderlər</h5>
                        @include('frontend.games._leaders', ['leaders' => $leaders])
                    </div>

                    <div class="side-card" data-game-rules="Stəkan qülləsi">
                        <h5><i class="fa fa-book me-2 text-primary"></i>Necə oynanır?</h5>
                        <div class="rules-note"><i class="fa fa-info-circle me-2"></i>Minimal masa hesabı <b>10 AZN</b> olmalıdır.</div>
                        <ul class="rules">
                            <li>Yuxarıda stəkan sağa-sola yellənir.</li>
                            <li>Toxunun — stəkan düşüb qüllənin üstünə düşür.</li>
                            <li>Hər stəkan <b>1 xal</b> verir.</li>
                            @if ($bonus['points'] > 0)
                                <li><b>{{ $bonus['score'] }} stəkan</b> — balansınıza <b>+{{ \App\Services\Referrals::format($bonus['points']) }} xal bonus</b> (gündə {{ $bonus['per_day'] }} dəfə)</li>
                            @endif
                            @foreach ($prizes as $min => $p)
                                @if ($loop->last)
                                    <li><b>{{ $min }} xal — Finiş! 🏁</b> <b>{{ $p['label'] }}</b></li>
                                @else
                                    <li><b>{{ $min }} xal</b> — <b>{{ $p['label'] }}</b></li>
                                @endif
                            @endforeach
                            {{-- hədiyyə seçimi bir dəfə izah olunur (hər hədiyyədə təkrarlanmır) --}}
                            <li>Hədiyyə xalına çatanda seçin: <b>«Hədiyyəni götür»</b> — kod verilir və yeni oyun başlayır; <b>davam etsəniz</b> — növbəti hədiyyəyə şans qazanırsınız, amma qüllə aşsa, hədiyyə yanır. Finişdə hədiyyəni götürdükdən sonra rekord üçün oynamağa davam edə bilərsiniz.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script src="{{ asset('js/games/stack.js') }}?v=104"></script>
@endsection
