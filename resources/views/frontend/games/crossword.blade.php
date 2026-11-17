@extends('frontend.layouts.app')
@section('title', 'Krossvord - 50 Qəpik Oyunlar')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Krossvord</h1>
        <p>{{ $practice ? 'Məşq krossvordu' : 'Günün krossvordu — ' . $dateLabel }}</p>
    </div>
</section>

<section class="section pt-4 games-scene">
    <div class="container">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>

        <div id="crossword" class="cw" data-puzzle='@json($puzzle)' data-key="{{ $practice ? 'p' . $puzzle['seed'] : 'd' . $puzzle['seed'] }}">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="cw-board">
                        <div class="cw-top">
                            <div class="cw-timer"><i class="fa fa-clock"></i> <span class="cw-time">0:00</span></div>
                            <div class="cw-progress"><span class="cw-filled">0</span> / <span class="cw-total">0</span> söz</div>
                        </div>

                        <div class="cw-current" aria-live="polite">
                            <b class="cw-current-n"></b><span class="cw-current-clue">Xanaya toxunun</span>
                        </div>

                        <div class="cw-grid-wrap">
                            <div class="cw-grid" role="grid" aria-label="Krossvord şəbəkəsi"></div>
                        </div>

                        {{-- telefonda Azərbaycan hərfləri olmaya bilər --}}
                        <div class="cw-letters" aria-label="Xüsusi hərflər">
                            @foreach (['Ə', 'Ğ', 'I', 'İ', 'Ö', 'Ü', 'Ş', 'Ç'] as $l)
                                <button type="button" data-letter="{{ $l }}">{{ $l }}</button>
                            @endforeach
                            <button type="button" class="cw-del" data-letter="" aria-label="Sil"><i class="fa fa-backspace"></i></button>
                        </div>

                        <div class="cw-actions">
                            <button type="button" class="btn btn-primary cw-check"><i class="fa fa-check me-1"></i>Yoxla</button>
                            <button type="button" class="btn btn-ghost-dark cw-reveal-letter"><i class="fa fa-lightbulb me-1"></i>Hərfi aç</button>
                            <button type="button" class="btn btn-ghost-dark cw-reveal-word"><i class="fa fa-eye me-1"></i>Sözü aç</button>
                            <button type="button" class="btn btn-ghost-dark cw-clear"><i class="fa fa-eraser me-1"></i>Təmizlə</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="side-card cw-clues">
                        <h5><i class="fa fa-arrows-alt-h me-2 text-primary"></i>Üfüqi</h5>
                        <ol class="cw-list" data-dir="across"></ol>
                        <h5 class="mt-3"><i class="fa fa-arrows-alt-v me-2 text-primary"></i>Şaquli</h5>
                        <ol class="cw-list" data-dir="down"></ol>
                    </div>

                    <div class="side-card">
                        <h5><i class="fa fa-book me-2 text-primary"></i>Necə oynanır?</h5>
                        <ul class="rules">
                            <li>Xanaya toxunun və yazın — hərf avtomatik növbəti xanaya keçir.</li>
                            <li>Eyni xanaya yenidən toxunsanız, istiqamət dəyişir (üfüqi ↔ şaquli). Klaviaturada oxlarla da gəzə bilərsiniz.</li>
                            <li><b>Yoxla</b> səhv hərfləri qırmızı göstərir. Çətinlikdə <b>Hərfi aç</b> və ya <b>Sözü aç</b> kömək edir.</li>
                            <li>Hər gün yeni krossvord! Gözləmək istəmirsinizsə, <a href="#" class="cw-new">başqa krossvord</a> həll edin.</li>
                        </ul>
                        @if ($practice)
                            <a href="{{ route('games.crossword') }}" class="btn btn-ghost-dark w-100 mt-2">Günün krossvorduna qayıt</a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="cw-done" hidden>
                <div class="nb-result-box">
                    <span class="eyebrow">Təbriklər! 🎉</span>
                    <h3>Krossvord həll olundu</h3>
                    <p class="cw-done-note mb-3"></p>
                    <div class="st-choice">
                        <a href="#" class="btn btn-primary cw-new">Başqa krossvord</a>
                        <button type="button" class="btn btn-ghost-dark cw-done-close">Bağla</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script src="{{ asset('js/games/crossword.js') }}?v=2"></script>
@endsection
