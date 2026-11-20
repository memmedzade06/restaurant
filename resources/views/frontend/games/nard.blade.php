@extends('frontend.layouts.app')
@section('title', 'Nərd - 50 Qəpik Oyunlar')

@php
    $canSave = auth()->check() && auth()->user()->isVerified();
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
            <p>Saytdakı dostunuzu dəvət edin — hər gün qaliblər seçilir. Kompüterlə məşq edə bilərsiniz.</p>
        @endif
    </div>
</section>

<section class="section pt-4 games-scene">
    <div class="container">
        @include('frontend.games._topbar')
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
                            <p class="mb-2">Rəqib: <b>{{ $opponentName }}</b>. Gedişlər dərhal rəqibə görünür, geri almaq olmur.</p>
                            <p class="mb-0 small text-muted">Oyunu sona qədər oynasanız qələbə günün cədvəlinə <b>{{ config('games.nard.points_win') }} xal</b>, mars <b>{{ config('games.nard.points_mars') }} xal</b> yazır.</p>
                        </div>
                        <div class="side-card">
                            <h5><i class="fa fa-user-circle me-2 text-primary"></i>Bu gün</h5>
                            <p class="mb-0">{{ $myStats['wins'] }} qələbə · {{ $myStats['points'] }} xal · {{ $myStats['games'] }} oyun</p>
                        </div>
                    @elseif ($canSave)
                        <div class="side-card">
                            <h5><i class="fa fa-user-circle me-2 text-primary"></i>Bu gün</h5>
                            <p id="nbMyStats" class="mb-0">{{ $myStats['wins'] }} qələbə · {{ $myStats['points'] }} xal · {{ $myStats['games'] }} oyun</p>
                            <p class="mb-0 mt-1 small text-muted">Xal yalnız dostla oyunda hesablanır.</p>
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
                            <h5>Dostlarınızla oynayın</h5>
                            <p>Dostlarınızı dəvət edib xal toplamaq və günün qalibi olmaq üçün daxil olun və ya qeydiyyatdan keçin.</p>
                            <a href="{{ auth()->check() ? route('verify.telegram') : route('register') }}" class="btn btn-primary w-100 mb-2">{{ auth()->check() ? 'Profili təsdiqlə' : 'Qeydiyyat' }}</a>
                            @guest <a href="{{ route('login') }}" class="btn btn-ghost-dark w-100">Daxil ol</a> @endguest
                        </div>
                    @endif

                    <div class="side-card">
                        <h5><i class="fa fa-trophy me-2 text-primary"></i>Bu günün liderləri</h5>
                        @include('frontend.games._leaders', ['leaders' => $leaders])
                        @include('frontend.games._nard_winners', ['winners' => $lastWinners])
                    </div>

                    {{-- qaydalar pəncərəsi: nərd səhifəsinə girəndə; dostla canlı oyunda (rəqib gözləyir) göstərilmir --}}
                    <div class="side-card" @unless (isset($match)) data-game-rules="Uzun nərd" @endunless>
                        <h5><i class="fa fa-book me-2 text-primary"></i>Qısa qaydalar</h5>
                        <div class="rules-note"><i class="fa fa-info-circle me-2"></i>Minimal masa hesabı <b>20 AZN</b> olmalıdır.</div>
                        <ul class="rules">
                            <li>Xal <b>yalnız dostla oyunda</b> verilir: oyun sona qədər oynanmalıdır, təslim olmaq və ya çıxmaq xal vermir. Eyni oyunçu ilə gündə {{ config('games.nard.pair_daily_limit') }} oyun xal gətirir.</li>
                            <li>Günün qalibləri:
                                @foreach (config('games.nard.daily_prizes') as $place => $p)
                                    <b>{{ $place }}-{{ $place === 3 ? 'cü' : 'ci' }} yer — {{ $p['label'] }}</b>{{ $loop->last ? '.' : ',' }}
                                @endforeach</li>
                            <li>Hər gecə saat 00:00-da (Bakı vaxtı) nəticələr silinir və yeni gün sıfırdan başlayır.</li>
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
<script src="{{ asset('js/games/nard.js') }}?v=11"></script>
@endsection
