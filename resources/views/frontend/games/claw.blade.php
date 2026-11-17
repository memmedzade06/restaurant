@extends('frontend.layouts.app')
@section('title', 'Hədiyyə Kranı - 50 Qəpik Oyunlar')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Hədiyyə Kranı</h1>
        <p>Rəngli şarı tutun — içindən hədiyyə çıxsın!</p>
    </div>
</section>

<section class="section pt-4 games-scene">
    <div class="container">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>
        <div class="row g-4">
            <div class="col-lg-7 col-xl-6">
                <div id="claw" class="claw-game"
                     data-debug="{{ app()->environment('local') && config('app.debug') ? 1 : 0 }}"
                     data-verified="{{ $verified ? 1 : 0 }}"
                     data-state='@json($state)'
                     data-start-url="{{ route('games.claw.start') }}"
                     data-open-url="{{ route('games.claw.open') }}"
                     data-claim-url="{{ route('games.claw.claim') }}"
                     data-prizes-url="{{ route('prizes') }}"
                     data-logo="{{ asset('img/games/stack-logo.png') }}"
                     data-register-url="{{ auth()->check() ? route('verify.telegram') : route('register') }}">
                    <div class="st-stage cl-stage">
                        <canvas aria-label="Hədiyyə Kranı oyunu"></canvas>
                        <button type="button" class="st-full cl-full" title="Tam ekran" aria-label="Tam ekran"><i class="fa fa-expand"></i></button>

                        <div class="st-start cl-start">
                            <div class="st-start-box">
                                <i class="fa fa-hand-pointer"></i>
                                <b>Başlamaq üçün toxunun</b>
                                <span>Oxlarla kranı sağa-sola və irəli-geri sürün, <kbd>Boşluq</kbd> ilə tutun</span>
                            </div>
                        </div>

                        {{-- şar açılanda, hədiyyə götürüləndə və s. — məzmunu JS yazır --}}
                        <div class="st-result cl-result" hidden>
                            <div class="nb-result-box"></div>
                        </div>
                    </div>

                    {{-- maşının idarə paneli --}}
                    <div class="cl-deck">
                        <div class="cl-stats">
                            <div class="cl-chip"><span>{{ $verified ? 'Cəhd' : 'Sınaq' }}</span><b class="cl-attempts">{{ $verified ? ($state['attemptsLeft'] ?? 0) : 3 }}</b></div>
                            <div class="cl-chip"><span>Vaxt</span><b class="cl-time">15</b></div>
                        </div>
                        <div class="cl-controls">
                            {{-- istiqamət düymələri: sağa-sola və irəli (arxaya) - geri (önə) --}}
                            <div class="cl-pad">
                                <button type="button" class="cl-arrow cl-up" data-dz="1" aria-label="İrəli (arxaya)"><i class="fa fa-chevron-up"></i></button>
                                <button type="button" class="cl-arrow cl-left" data-dx="-1" aria-label="Sola"><i class="fa fa-chevron-left"></i></button>
                                <button type="button" class="cl-arrow cl-down" data-dz="-1" aria-label="Geri (önə)"><i class="fa fa-chevron-down"></i></button>
                                <button type="button" class="cl-arrow cl-right" data-dx="1" aria-label="Sağa"><i class="fa fa-chevron-right"></i></button>
                            </div>
                            <button type="button" class="cl-drop" aria-label="Tut">TUT</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 col-xl-6">
                <div class="game-side">
                    @unless ($verified)
                        <div class="side-card side-cta">
                            <h5>Real hədiyyə qazanın!</h5>
                            <p>İndi <b>sınaq</b> rejimindəsiniz — şardan çıxan hədiyyə üçün kod verilmir. Qeydiyyatdan keçib nömrənizi Telegram ilə təsdiqləyin: gündə {{ $attempts }} cəhd, real kod.</p>
                            <a href="{{ auth()->check() ? route('verify.telegram') : route('register') }}" class="btn btn-primary w-100 mb-2">{{ auth()->check() ? 'Nömrəni təsdiqlə' : 'Qeydiyyat' }}</a>
                            @guest <a href="{{ route('login') }}" class="btn btn-ghost-dark w-100">Daxil ol</a> @endguest
                        </div>
                    @endunless

                    @if ($state['blockedBy'] ?? null)
                        {{-- bu gün Bəxt çarxında hədiyyə götürülüb: oynamaq olar, götürmək olmaz --}}
                        <div class="rules-note prize-block"><i class="fa fa-exclamation-triangle me-2"></i>{{ $state['blockedMessage'] }}</div>
                    @elseif (($state['funReason'] ?? null) === 'attempts')
                        <div class="rules-note prize-block"><i class="fa fa-exclamation-triangle me-2"></i>Bu günün cəhdləri bitdi — əyləncə üçün istədiyiniz qədər oynaya bilərsiniz, amma hədiyyə verilmir. Sabah yenə {{ $attempts }} cəhdiniz olacaq.</div>
                    @endif

                    <div class="side-card">
                        <h5><i class="fa fa-book me-2 text-primary"></i>Necə oynanır?</h5>
                        <div class="rules-note"><i class="fa fa-info-circle me-2"></i>Hədiyyədən istifadə üçün minimal masa hesabı <b>30 AZN</b> olmalıdır.</div>
                        <ul class="rules">
                            <li>Gündə <b>{{ $attempts }} cəhdiniz</b> var, hər cəhdə <b>15 saniyə</b>. Vaxt bitsə, cəhd sayılmır — <b>Yenidən başla</b> ilə davam edirsiniz.</li>
                            <li>Kran <b>sağa-sola</b> (◀ ▶) və <b>irəli-geri</b> (▲ ▼) gedir. Klaviaturada <kbd>←</kbd> <kbd>→</kbd> <kbd>↑</kbd> <kbd>↓</kbd> və ya şüşəyə toxunaraq da sürə bilərsiniz.</li>
                            <li>Şarın üstündəki <b>qızılı halqa</b> kranın düşəcəyi yeri göstərir. <b>TUT</b> (və ya <kbd>Boşluq</kbd>) basın — kran şarı tutub ön sol küncdəki çıxışa aparır.</li>
                            <li>Şarın düz ortasından tutun — kənarından tutsanız, yolda sürüşüb düşə bilər.</li>
                            <li>Çıxışa düşən şar açılır və içindən <b>təsadüfi hədiyyə</b> çıxır (bəzən şar boş olur). <b>Hədiyyəni götür</b> seçsəniz, kod yaranır və bu günün oyunu bitir. <b>Davam et</b> seçsəniz, əvvəlki hədiyyə itir — risk sizindir!</li>
                            <li>Kod <b>{{ config('games.claw.code_valid_minutes') }} dəqiqə</b> etibarlıdır — ofisianta göstərin. Bütün kodlarınız: <a href="{{ route('prizes') }}">Hədiyyələrim</a>.</li>
                            <li>Gündə yalnız <b>bir oyunda</b> hədiyyə götürmək olar — Bəxt çarxında və ya Hədiyyə Kranında. Hədiyyə götürdükdən sonra hər iki oyunu əyləncə üçün istədiyiniz qədər oynaya bilərsiniz, amma həmin gün yeni hədiyyə verilmir.</li>
                        </ul>
                    </div>

                    {{-- şarlardan çıxa bilən hədiyyələr — config('games.claw.prizes')-dən (boş şar siyahıda deyil) --}}
                    <div class="side-card">
                        <h5><i class="fa fa-gift me-2 text-primary"></i>Şarların içində</h5>
                        <ul class="cl-odds">
                            @foreach ($prizes as $label)
                                <li><span>🎁 {{ $label }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script src="{{ asset('js/games/claw.js') }}?v=22"></script>
@endsection
