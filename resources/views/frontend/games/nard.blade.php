@extends('frontend.layouts.app')
@section('title', 'Nərd - 50 Qəpik Oyunlar')

@php
    $canSave = auth()->check() && auth()->user()->hasVerifiedPhone();
    $online = isset($match); // iki nəfərlik oyun (dəvətlə)
@endphp

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Uzun nərd</h1>
        @if ($online)
            <p>Dostla oyun: <b>{{ $opponentName }}</b> ilə</p>
        @else
            <p>Kompüterə qarşı oynayın və ya saytdakı dostunuzu dəvət edin</p>
        @endif
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>
        <div class="row g-4">
            <div class="col-xl-9">
                {{-- nard-stage: böyük ekran rejimində bütün ekranı tutan qat, lövhə onun ortasında qalır --}}
                <div class="nard-stage">
                <div id="nard" class="nard {{ $online ? 'is-online' : '' }}"
                     data-can-save="{{ $canSave && ! $online ? 1 : 0 }}"
                     data-save-url="{{ route('games.nard.result') }}"
                     data-register-url="{{ auth()->check() ? route('verify.telegram') : route('register') }}"
                     @if ($online)
                         data-match-url="{{ route('games.nard.match.state', $match) }}"
                         data-roll-url="{{ route('games.nard.match.roll', $match) }}"
                         data-move-url="{{ route('games.nard.match.move', $match) }}"
                         data-resign-url="{{ route('games.nard.match.resign', $match) }}"
                         data-opp-name="{{ $opponentName }}"
                     @endif>
                    <div class="nb-top-bar">
                        <div class="nb-player ai"><i class="nb-chk b"></i> {{ $online ? $opponentName : 'Kompüter' }}@if ($online)<span class="nb-online" title="Rəqib səhifədədir"></span>@endif <span class="nb-off-count">çıxarıb: <b id="nbOffAi">0</b>/15</span></div>
                        <button type="button" class="nb-full" title="Böyük ekran" aria-label="Böyük ekran"><i class="fa fa-expand"></i></button>
                    </div>

                    <div class="nb-wrap">
                        <div class="nb-board"></div>
                        {{-- Zərlər lövhənin üstündə, iki sıranın arasında göstərilir --}}
                        <div class="nb-dice" aria-live="polite"></div>
                        <button type="button" class="nb-tray nb-tray-human" aria-label="Daşı çıxar">
                            <i class="fa fa-sign-out-alt"></i><span>Çıxar</span>
                        </button>
                        <div class="nb-result" hidden>
                            <div class="nb-result-box">
                                <h3 class="nb-result-title"></h3>
                                <p class="nb-result-note"></p>
                                @if ($online)
                                    <a href="{{ route('games.nard') }}" class="btn btn-primary px-5">Nərdə qayıt</a>
                                @else
                                    <button type="button" class="btn btn-primary px-5 nb-again">Yenidən oyna</button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="nb-bottom-bar">
                        <div class="nb-player human"><i class="nb-chk w"></i> Siz <span class="nb-off-count">çıxarıb: <b id="nbOffHuman">0</b>/15</span></div>
                        <div class="nb-actions">
                            <button type="button" id="nbUndo" class="btn btn-ghost-dark" title="Bu növbədəki son gedişi geri al" @if ($online) hidden @endif><i class="fa fa-undo"></i><span class="d-none d-sm-inline ms-2">Geri al</span></button>
                            <button type="button" id="nbRoll" class="btn btn-primary"><i class="fa fa-dice me-2"></i>Zər at</button>
                        </div>
                    </div>
                    <p class="nb-status" aria-live="polite"></p>
                </div>
                </div>
            </div>

            <div class="col-xl-3">
                <div class="game-side">
                    @if ($online)
                        <div class="side-card">
                            <h5><i class="fa fa-user-friends me-2 text-primary"></i>Dostla oyun</h5>
                            <p class="mb-0">Rəqib: <b>{{ $opponentName }}</b>. Gedişlər dərhal rəqibə görünür, geri almaq olmur. Dostla oyun xal vermir.</p>
                        </div>
                    @elseif ($canSave)
                        <div class="side-card">
                            <h5><i class="fa fa-user-circle me-2 text-primary"></i>Sizin nəticələr</h5>
                            <p id="nbMyStats" class="mb-0">{{ $myStats['wins'] }} qələbə · {{ $myStats['points'] }} xal · {{ $myStats['games'] }} oyun</p>
                        </div>
                        {{-- Dostla oyna: saytdakı oyunçular, dəvət göndərmək --}}
                        <div class="side-card" id="nardFriends"
                             data-players-url="{{ route('games.nard.players') }}"
                             data-invite-url="{{ route('games.nard.invite') }}"
                             data-cancel-url="{{ route('games.nard.invite.cancel', '__ID__') }}">
                            <h5><i class="fa fa-user-friends me-2 text-primary"></i>Dostla oyna</h5>
                            <div class="np-wait" hidden></div>
                            <p class="text-muted small np-empty mb-0">Hazırda saytda başqa oyunçu yoxdur.</p>
                            <ul class="nard-players"></ul>
                        </div>
                    @else
                        <div class="side-card side-cta">
                            <h5>Xallarınız saxlanılsın?</h5>
                            <p>Qələbələriniz rekordlar cədvəlinə düşsün və dostlarınızla oynaya biləsiniz deyə daxil olun və ya qeydiyyatdan keçin.</p>
                            <a href="{{ auth()->check() ? route('verify.telegram') : route('register') }}" class="btn btn-primary w-100 mb-2">{{ auth()->check() ? 'Nömrəni təsdiqlə' : 'Qeydiyyat' }}</a>
                            @guest <a href="{{ route('login') }}" class="btn btn-ghost-dark w-100">Daxil ol</a> @endguest
                        </div>
                    @endif

                    <div class="side-card">
                        <h5><i class="fa fa-trophy me-2 text-primary"></i>Liderlər</h5>
                        @include('frontend.games._leaders', ['leaders' => $leaders])
                    </div>

                    <div class="side-card">
                        <h5><i class="fa fa-book me-2 text-primary"></i>Qısa qaydalar</h5>
                        <ul class="rules">
                            <li>Daşlar saat əqrəbi istiqamətində, başdan evə doğru gedir.</li>
                            <li>Rəqibin daşı olan xanaya getmək olmaz, vurmaq yoxdur.</li>
                            <li>Hər növbədə başdan yalnız bir daş götürülür.</li>
                            <li>Bütün daşlar evə (sağ-aşağı) çatanda çıxarmaq olar.</li>
                            <li>Rəqib heç daş çıxarmamış qalib gəlsəniz — <b>mars</b>, ikiqat xal.</li>
                        </ul>
                    </div>
                    <button type="button" id="nbRestart" class="btn btn-ghost-dark w-100">
                        @if ($online)<i class="fa fa-flag me-2"></i>Təslim ol @else<i class="fa fa-redo me-2"></i>Yeni oyun @endif
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script src="{{ asset('js/games/nard.js') }}?v=8"></script>
@endsection
