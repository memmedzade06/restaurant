@extends('frontend.layouts.app')
@section('title', 'Bilik Yarışı - 50 Qəpik Oyunlar')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Bilik Yarışı</h1>
        <p>Azərbaycanı nə qədər tanıyırsınız? Hər suala {{ $quiz['seconds'] }} saniyə!</p>
    </div>
</section>

<section class="section pt-4 games-scene">
    <div class="container">
        @include('frontend.games._topbar')

        <div class="row g-4">
            <div class="col-lg-7">
                <div id="quiz" class="qz" data-quiz='@json($quiz)'>
                    {{-- Başlanğıc --}}
                    <div class="qz-screen qz-intro">
                        <div class="qz-badge"><i class="fa fa-question"></i></div>
                        <h3>Hazırsınız?</h3>
                        <p>Hər mərhələdə <b>{{ $quiz['perStage'] }} sual</b>. Hər suala <b>{{ $quiz['seconds'] }} saniyə</b> vaxt var.<br>
                            <b>{{ $quiz['lives'] }} səhv</b> etsəniz, oyun bitir.</p>
                        @if ($quiz['stagePoints'] > 0)
                            <p class="qz-earn"><i class="fa fa-star me-1"></i>{{ $quiz['pointsFromLabel'] }} mərhələdən: <b>+{{ \App\Services\Referrals::format($quiz['stagePoints']) }} xal</b>@if ($quiz['stageStep'] > 0), hər növbəti mərhələdə <b>+{{ \App\Services\Referrals::format($quiz['stageStep']) }}</b> çox @endif
                                {{-- ofisiant və admin xal almır, amma onlara xəbərdarlıq göstərilmir --}}
                                @if ($quiz['staff'])
                                @elseif ($quiz['canEarn'] && $quiz['pointsLeft'] <= 0)
                                    <br><small>Bu günün xal limiti bitib — sabah yenə qazanın.</small>
                                @elseif (! $quiz['canEarn'])
                                    <br><small><a href="{{ $quiz['registerUrl'] }}">{{ auth()->check() ? 'Profilinizi təsdiqləyin' : 'Qeydiyyatdan keçin' }}</a> ki, xal qazanasınız.</small>
                                @endif
                            </p>
                        @endif
                        <p class="qz-record" hidden></p>
                        @if ($quiz['resumeStage'])
                            {{-- təsdiqdən sonra qayıdıb: yarımçıq oyunu davam etdirmək olar --}}
                            <button type="button" class="btn btn-primary qz-resume px-5 py-3 mb-2">{{ $quiz['resumeStage'] }}-{{ [1 => 'ci', 2 => 'ci', 3 => 'cü', 4 => 'cü', 5 => 'ci', 6 => 'cı', 7 => 'ci', 8 => 'ci', 9 => 'cu'][$quiz['resumeStage'] % 10] ?? 'cu' }} mərhələdən davam et <i class="fa fa-forward ms-2"></i></button>
                            <button type="button" class="btn btn-ghost-dark qz-start px-4 py-2">Yeni oyun</button>
                        @else
                            <button type="button" class="btn btn-primary qz-start px-5 py-3">Başla <i class="fa fa-play ms-2"></i></button>
                        @endif
                    </div>

                    {{-- Sual --}}
                    <div class="qz-screen qz-play" hidden>
                        <div class="qz-top">
                            <span class="qz-stage">1-ci mərhələ</span>
                            <span class="qz-count">Sual 1 / {{ $quiz['perStage'] }}</span>
                            <span class="qz-lives" aria-label="Qalan can"></span>
                        </div>
                        <div class="qz-timer" aria-hidden="true"><span class="qz-timer-bar"></span></div>
                        <div class="qz-time-left" aria-live="off"></div>
                        <h3 class="qz-question" aria-live="polite"></h3>
                        <div class="qz-options"></div>
                        <p class="qz-feedback" aria-live="polite"></p>
                    </div>

                    {{-- Mərhələ bitdi --}}
                    <div class="qz-screen qz-stage-done" hidden>
                        <div class="qz-badge qz-badge-ok"><i class="fa fa-flag-checkered"></i></div>
                        <h3 class="qz-stage-title"></h3>
                        <p class="qz-stage-note"></p>
                        <button type="button" class="btn btn-primary qz-next-stage px-5 py-3">Növbəti mərhələ <i class="fa fa-arrow-right ms-2"></i></button>
                        {{-- təsdiqlənməmiş oyunçu: davam etmək üçün profil təsdiqi --}}
                        <div class="qz-verify" hidden>
                            @if ($quiz['isGuest'])
                                <a href="{{ $quiz['registerUrl'] }}" class="btn btn-primary px-5 py-3">Qeydiyyatdan keç <i class="fa fa-user-plus ms-2"></i></a>
                                <p class="mt-2 mb-0 small">Hesabınız var? <a href="{{ $quiz['loginUrl'] }}">Daxil olun</a></p>
                            @else
                                <a href="{{ $quiz['registerUrl'] }}" class="btn btn-primary px-5 py-3">Profili təsdiqlə <i class="fa fa-check ms-2"></i></a>
                            @endif
                        </div>
                    </div>

                    {{-- Oyun bitdi --}}
                    <div class="qz-screen qz-over" hidden>
                        <div class="qz-badge qz-badge-end"><i class="fa fa-trophy"></i></div>
                        <h3 class="qz-over-title">Oyun bitdi</h3>
                        <p class="qz-over-note"></p>
                        <button type="button" class="btn btn-primary qz-again px-5 py-3">Yenidən oyna <i class="fa fa-redo ms-2"></i></button>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="side-card" data-game-rules="Bilik Yarışı">
                    <h5><i class="fa fa-book me-2 text-primary"></i>Necə oynanır?</h5>
                    <ul class="rules">
                        <li>Azərbaycanın tarixi, coğrafiyası, mədəniyyəti və mətbəxinə aid suallar verilir.</li>
                        <li>Hər sualın <b>3 cavabı</b> var, yalnız biri düzdür.</li>
                        <li>Hər suala <b>{{ $quiz['seconds'] }} saniyə</b> vaxt var — vaxt bitsə, cavab səhv sayılır.</li>
                        <li>Bir mərhələ <b>{{ $quiz['perStage'] }} sualdır</b>. <b>{{ $quiz['lives'] }} səhv</b> etsəniz, oyun bitir.</li>
                        <li>{{ $quiz['perStage'] }} sualı keçəndə növbəti mərhələ başlayır, canlar yenilənir. Birinci mərhələ asandır, sonra suallar getdikcə çətinləşir.</li>
                        <li>İlk <b>{{ $quiz['guestStages'] }} mərhələni</b> hamı oynaya bilər. Davam etmək üçün <b>profilinizi təsdiqləməlisiniz</b>.</li>
                        @if ($quiz['stagePoints'] > 0)
                            <li>Xal <b>{{ $quiz['pointsFromLabel'] }} mərhələdən</b> başlayır: həmin mərhələdən sonra hər keçilən mərhələ üçün balansınıza xal yazılır və mərhələ artdıqca xal da artır:
                                @for ($s = $quiz['pointsFrom']; $s < $quiz['pointsFrom'] + 4; $s++)
                                    <b>{{ \App\Http\Controllers\Frontend\QuizController::ordinal($s) }} — +{{ \App\Services\Referrals::format(\App\Http\Controllers\Frontend\QuizController::stageReward($s)) }}</b>{{ $s < $quiz['pointsFrom'] + 3 ? ',' : ' …' }}
                                @endfor
                                (gündə ən çox <b>{{ \App\Services\Referrals::format($quiz['maxPerDay']) }} xal</b>). Xalları Hədiyyə marketində hədiyyəyə çevirə bilərsiniz.</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script src="{{ asset('js/games/quiz.js') }}?v=9"></script>
@endsection
