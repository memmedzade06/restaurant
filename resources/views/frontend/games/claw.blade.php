@extends('frontend.layouts.app')
@section('title', 'Hədiyyə Kranı - 50 Qəpik Oyunlar')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Hədiyyə Kranı</h1>
        <p>Çəngəli düz tuşlayın, şirniyyatı tutub çıxışa daşıyın!</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>
        <div class="row g-4">
            <div class="col-lg-7 col-xl-6">
                <div id="claw" class="claw-game" data-debug="{{ app()->environment('local') && config('app.debug') ? 1 : 0 }}">
                    <div class="st-stage cl-stage">
                        <canvas aria-label="Hədiyyə Kranı oyunu"></canvas>

                        <div class="st-start cl-start">
                            <div class="st-start-box">
                                <i class="fa fa-hand-pointer"></i>
                                <b>Başlamaq üçün toxunun</b>
                                <span>Oxlarla çəngəli sağa-sola və irəli-geri sürün, <kbd>Boşluq</kbd> ilə tutun</span>
                            </div>
                        </div>

                        <div class="st-result cl-result" hidden>
                            <div class="nb-result-box">
                                <h3 class="cl-title"></h3>
                                <div class="st-score cl-score">0</div>
                                <p class="cl-note"></p>
                                <div class="st-choice">
                                    <button type="button" class="btn btn-primary px-5 cl-again">Yenidən oyna</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- maşının idarə paneli --}}
                    <div class="cl-deck">
                        <div class="cl-stats">
                            <div class="cl-chip"><span>Cəhd</span><b class="cl-attempts">5</b></div>
                            <div class="cl-chip"><span>Xal</span><b class="cl-points">0</b></div>
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
                    <div class="side-card">
                        <h5><i class="fa fa-user-circle me-2 text-primary"></i>Sizin rekord</h5>
                        <p class="mb-0"><b class="st-best cl-best">0</b> xal <span class="text-muted small">(bu cihazda)</span></p>
                    </div>

                    <div class="side-card">
                        <h5><i class="fa fa-book me-2 text-primary"></i>Necə oynanır?</h5>
                        <div class="rules-note"><i class="fa fa-info-circle me-2"></i>Bu oyun hələlik əyləncə üçündür — hədiyyə vermir.</div>
                        <ul class="rules">
                            <li>Hər oyunda <b>5 cəhdiniz</b> var, hər cəhdə <b>15 saniyə</b>. Vaxt bitənə qədər çəngəli salmasanız, oyun bitir — <b>Yenidən başla</b> ilə təzədən başlayırsınız.</li>
                            <li>Çəngəl <b>sağa-sola</b> (◀ ▶) və <b>irəli-geri</b> (▲ ▼) gedir. Klaviaturada <kbd>←</kbd> <kbd>→</kbd> <kbd>↑</kbd> <kbd>↓</kbd> və ya şüşəyə toxunaraq da sürə bilərsiniz.</li>
                            <li>Şirniyyatın üstündəki <b>qızılı halqa</b> çəngəlin düşəcəyi yeri göstərir — dərinliyi ona baxıb tutuşdurun.</li>
                            <li><b>TUT</b> (və ya <kbd>Boşluq</kbd>) basın — çəngəl enir, şirniyyatı tutub ön sol küncdəki çıxışa aparır.</li>
                            <li>Şirniyyatın düz ortasından tutun — kənarından tutsanız, yolda sürüşüb düşə bilər. Başqalarının altında qalanı çıxarmaq çətindir.</li>
                        </ul>
                        <div class="cl-legend">
                            <div><span class="cl-dot" style="background:linear-gradient(90deg,#c62a2a,#f7b6c8,#8fc98a)"></span>Konfet, Lolipop, Pişmaniye, Rəngli lokum — <b>1 xal</b></div>
                            <div><span class="cl-dot" style="background:#f0d8a4;border:1px solid #c9831f"></span>Şəkərbura, Paxlava, Badambura, Nabat, Mütəkkə, Kətə — <b>2 xal</b></div>
                            <div><span class="cl-dot" style="background:#d9973a"></span>Qoğal (ağırdır) — <b>3 xal</b></div>
                            <div><span class="cl-dot" style="background:#b5301f"></span>Şəki halvası (nadir) — <b>4 xal</b></div>
                            <div><span class="cl-dot" style="background:radial-gradient(circle at 35% 35%,#fff3b0,#f2c23a 55%,#b5780f)"></span>Qızıl “50 Qəpik” sikkəsi (cəmi bir dənədir!) — <b>5 xal</b></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script src="{{ asset('js/games/claw.js') }}?v=7"></script>
@endsection
