@extends('frontend.layouts.app')
@section('title', 'Hədiyyə marketi - 50 Qəpik')

@php
    $fmt = fn ($p) => \App\Services\Referrals::format((float) $p);
    $authUrl = auth()->check() ? route('verify.telegram') : route('login');
    $levels = \App\Services\Referrals::levels();
    $limitReached = $verified && $smallLeft <= 0;
    $isSmall = fn ($it) => (float) $it['cost'] <= $smallMax;
    // adi hədiyyə gündəlik limitdən 2 pay, kiçik hədiyyə (smallMax xala qədər) 1 pay aparır
    $fits = fn ($it) => $verified && $smallLeft >= ($isSmall($it) ? 1 : 2);
    $img = fn ($it) => ! empty($it['image']) && is_file(public_path($it['image'])) ? asset($it['image']) : null;
@endphp

@section('content')
{{-- telefonda küncdəki söhbət düyməsi piştaxtanın düymələrini örtür — marketdə gizlədilir --}}
<style>@media (max-width: 767.98px) { .chat-widget, .chat-fab, .chat-fab-label { display: none !important; } }</style>
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Hədiyyə marketi</h1>
        <p>Xallarınızı hədiyyəyə çevirin — kupon dərhal hesabınıza düşür</p>
    </div>
</section>

<section class="section pt-4 account-page">
    <div class="container">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>

        <div class="row g-4">
            {{-- Sol: balans və hədiyyələr --}}
            <div class="col-lg-8">
                @if ($bought)
                    {{-- yenicə alınmış hədiyyə: kupon kodu (2 kiçik hədiyyə birlikdə alınanda da bir kod) --}}
                    <div class="acc-card market-bought mb-4" role="status">
                        <span class="eyebrow">Təbriklər 🎉</span>
                        @foreach ($bought as $b)
                            <h3 @unless ($loop->first) class="mt-3" @endunless>{{ $b->prize_label }}</h3>
                            <div class="prize-code">{{ $b->code }}</div>
                        @endforeach
                        @php $b0 = $bought->first(); @endphp
                        <p class="claimed-at"><i class="fa fa-clock me-1"></i>Götürülüb: <b>{{ $b0->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</b></p>
                        <p class="claimed-at coupon-valid"><i class="fa fa-hourglass-half me-1"></i>Etibarlıdır: <b>{{ $b0->expires_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}-dək</b></p>
                        @if ($minBill = \App\Models\WheelSpin::minBill('market'))
                            <div class="coupon-min-bill"><i class="fa fa-receipt"></i><span>Minimal masa hesabı</span><b>{{ $minBill }} AZN</b></div>
                        @endif
                        <p class="small text-muted mb-0">{{ $bought->count() > 1 ? 'Kodları' : 'Kodu' }} restoranda ofisianta göstərin.
                            {{ $bought->count() > 1 ? 'Kodlar' : 'Kod' }} <a href="{{ route('prizes') }}">Hədiyyələrim</a> səhifəsində də saxlanılır.</p>
                    </div>
                @endif
                @if (session('market_error'))
                    <div class="alert-soft mb-4" role="alert"><i class="fa fa-exclamation-triangle me-2"></i>{{ session('market_error') }}</div>
                @endif

                {{-- balans --}}
                <div class="acc-card market-balance mb-4">
                    @if ($verified)
                        <div class="points-balance small-ball">
                            <span class="points-num">{{ $fmt($balance) }}</span>
                            <span class="points-label">xal</span>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1">Balansınız: {{ $fmt($balance) }} xal</h5>
                            <p class="mb-2 text-muted">Xal qazanmaq üçün profilinizdəki linki və ya QR kodu dostlarınızla paylaşın.
                                <a href="{{ route('account') }}#xallar" class="fw-bold">Xal qazan <i class="fa fa-arrow-right ms-1"></i></a></p>
                            <span class="market-left {{ $limitReached ? 'is-out' : '' }}">
                                @if ($left === 0 && $smallLeft > 0)
                                    <i class="fa fa-gift me-1"></i>Bu gün: {{ $fmt($smallMax) }} xala qədər <b>{{ $smallLeft }}</b> hədiyyə qalıb
                                @else
                                    <i class="fa fa-gift me-1"></i>Bu gün: <b>{{ $left }}</b> / {{ $perDay }} hədiyyə qalıb
                                @endif
                            </span>
                        </div>
                    @else
                        <div class="acc-ico big"><i class="fa fa-lock"></i></div>
                        <div>
                            <h5 class="mb-1">Hədiyyə almaq üçün daxil olun</h5>
                            <p class="mb-0 text-muted">Xallar yalnız təsdiqlənmiş hesablarda toplanır.
                                @auth <a href="{{ route('verify.telegram') }}" class="fw-bold">Profilinizi təsdiqləyin</a>
                                @else <a href="{{ route('login') }}" class="fw-bold">Daxil olun</a> və ya <a href="{{ route('register') }}" class="fw-bold">qeydiyyatdan keçin</a>
                                @endauth
                            </p>
                        </div>
                    @endif
                </div>

                {{-- hədiyyələr: rəf (piştaxta) + seçilən hədiyyə üçün kassa --}}
                @php
                    $canBuy = fn ($it) => $fits($it) && $balance >= (float) $it['cost'];
                    $first = collect($items)->first($canBuy) ?? collect($items)->first();
                    $selectedKey = $first['key'] ?? null;
                @endphp
                @if ($cat)
                    {{-- kateqoriyalar arasında keçid --}}
                    <nav class="shop-nav" aria-label="Kateqoriyalar">
                        <a href="{{ route('market') }}#shop" class="shop-back"><i class="fa fa-th-large me-2"></i>Kateqoriyalar</a>
                        <div class="shop-chips">
                            @foreach ($categories as $c)
                                <a href="{{ route('market', ['kat' => $c['key']]) }}#shop" class="shop-chip {{ $c['key'] === $cat['key'] ? 'is-active' : '' }}"
                                   @if ($c['key'] === $cat['key']) aria-current="page" @endif>{{ $c['label'] }}</a>
                            @endforeach
                        </div>
                    </nav>
                @endif
                <div class="shop {{ $cat ? '' : 'is-cats' }}" id="shop"
                     data-small-left="{{ (int) $smallLeft }}" data-balance="{{ (float) $balance }}" data-verified="{{ $verified ? 1 : 0 }}"
                     data-buy-url="{{ route('market.buy', '__KEY__') }}" data-small-max="{{ $fmt($smallMax) }}">
                    <div class="shop-awning" aria-hidden="true"></div>
                    <div class="shop-sign"><span class="sign-board">
                        @if ($cat)<i class="fa fa-store me-2"></i>{{ $cat['label'] }} <small>istədiyinizi seçin{{ collect($items)->filter($isSmall)->count() > 1 ? ' — ' . $fmt($smallMax) . ' xala qədər olanlardan 2 dənə' : '' }}</small>
                        @else<i class="fa fa-store me-2"></i>Hədiyyə marketi <small>kateqoriya seçin</small>@endif
                    </span></div>

                    @unless ($cat)
                        {{-- əvvəlcə kateqoriyalar: daxil olanda məhsullar rəfdə görünür --}}
                        <div class="shop-cabinet">
                            <div class="shop-shelves">
                                @foreach ($categories as $c)
                                    <a href="{{ route('market', ['kat' => $c['key']]) }}#shop" class="shelf-item shelf-cat {{ $c['featured'] ? 'is-featured' : '' }}">
                                        <span class="shelf-tag">{{ $c['count'] }} məhsul</span>
                                        <span class="shelf-prod {{ $img($c) ? 'has-img' : '' }}">
                                            @if ($img($c))<img src="{{ $img($c) }}" alt="" loading="lazy">@else<i class="fa {{ $c['featured'] ? 'fa-gift' : 'fa-utensils' }}"></i>@endif
                                            @if ($c['featured'])<span class="shelf-ribbon"><i class="fa fa-star"></i></span>@endif
                                        </span>
                                        <span class="shelf-board" aria-hidden="true"></span>
                                        <span class="shelf-name">{{ $c['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        <div class="shop-counter">
                            <div class="counter-top" aria-hidden="true"></div>
                            <div class="counter-front">
                                <div class="counter-pane counter-hint">
                                    <div class="counter-prod"><i class="fa fa-hand-pointer"></i></div>
                                    <div class="counter-info">
                                        <h5>Kateqoriya seçin</h5>
                                        <p>Rəfdən bir kateqoriyaya daxil olun, sonra istədiyiniz hədiyyəni seçib xalla alın.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                    <div class="shop-cabinet">
                    <div class="shop-shelves" role="group" aria-label="Hədiyyələr">
                        @foreach ($items as $item)
                            @php $sel = $item['key'] === $selectedKey; @endphp
                            <button type="button" class="shelf-item {{ $canBuy($item) ? '' : 'locked' }} {{ $sel ? 'is-selected' : '' }} {{ $isSmall($item) ? 'is-small' : '' }}"
                                    data-key="{{ $item['key'] }}" data-cost="{{ (float) $item['cost'] }}" data-label="{{ $item['label'] }}"
                                    aria-pressed="{{ $sel ? 'true' : 'false' }}">
                                <span class="shelf-tag"><i class="fa fa-star"></i>{{ $fmt($item['cost']) }}</span>
                                <span class="shelf-prod {{ $img($item) ? 'has-img' : '' }}">
                                    @if ($img($item))<img src="{{ $img($item) }}" alt="" loading="lazy">@else<i class="fa {{ $item['icon'] }}"></i>@endif
                                    @if ($isSmall($item))<span class="shelf-qty" hidden>×2</span>@endif
                                </span>
                                <span class="shelf-board" aria-hidden="true"></span>
                                <span class="shelf-name">{{ $item['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    </div>

                    {{-- piştaxta: seçilmiş hədiyyə --}}
                    <div class="shop-counter">
                        <div class="counter-top" aria-hidden="true"></div>
                        <div class="counter-front">
                        @foreach ($items as $item)
                            @php
                                $cost = (float) $item['cost'];
                                $enough = $verified && $balance >= $cost;
                            @endphp
                            <div class="counter-pane" data-key="{{ $item['key'] }}" @if ($item['key'] !== $selectedKey) hidden @endif>
                                <div class="counter-prod {{ $img($item) ? 'has-img' : '' }}">
                                    @if ($img($item))<img src="{{ $img($item) }}" alt="{{ $item['label'] }}" loading="lazy">@else<i class="fa {{ $item['icon'] }}"></i>@endif
                                </div>
                                <div class="counter-info">
                                    <h5>{{ $item['label'] }}</h5>
                                    <p>{{ $item['desc'] ?: $cat['label'] }}</p>
                                </div>
                                <div class="counter-buy">
                                    @if ($isSmall($item))
                                        {{-- kiçik hədiyyədən 2 ədəd götürmək olar: + basanda say 2 olur --}}
                                        <div class="qty-step" role="group" aria-label="Say">
                                            <button type="button" class="qty-minus" aria-label="Azalt">&minus;</button>
                                            <span class="qty-num">1</span>
                                            <button type="button" class="qty-plus" aria-label="Artır">+</button>
                                        </div>
                                    @endif
                                    <div class="counter-price"><i class="fa fa-star me-1"></i>{{ $fmt($cost) }} xal</div>
                                    @if (! $verified)
                                        <a href="{{ $authUrl }}" class="btn btn-ghost-dark w-100">Daxil olun</a>
                                    @elseif ($limitReached)
                                        <button type="button" class="btn btn-ghost-dark w-100" disabled>Bu günün limiti bitib</button>
                                    @elseif (! $fits($item))
                                        <button type="button" class="btn btn-ghost-dark w-100" disabled>Bu gün yalnız {{ $fmt($smallMax) }} xala qədər</button>
                                    @elseif ($enough)
                                        <form method="POST" action="{{ route('market.buy', $item['key']) }}" class="buy-form"
                                              data-label="{{ $item['label'] }}" data-cost="{{ $fmt($cost) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-primary w-100"><i class="fa fa-gift me-2"></i>Xalla al</button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-ghost-dark w-100" disabled>{{ $fmt($cost - $balance) }} xal çatmır</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                            {{-- 2 kiçik hədiyyə birlikdə seçiləndə (aşağıdakı skript doldurur) --}}
                            <div class="counter-pane counter-pair" hidden>
                                <div class="counter-prod"><i class="fa fa-gifts"></i></div>
                                <div class="counter-info">
                                    <h5 class="pair-names"></h5>
                                    <p class="pair-note"></p>
                                </div>
                                <div class="counter-buy">
                                    <div class="qty-step" role="group" aria-label="Say">
                                            <button type="button" class="qty-minus" aria-label="Azalt">&minus;</button>
                                            <span class="qty-num">2</span>
                                            <button type="button" class="qty-plus" aria-label="Artır">+</button>
                                        </div>
                                    <div class="counter-price"><i class="fa fa-star me-1"></i><span class="pair-total"></span> xal</div>
                                    <form method="POST" class="pair-form buy-form" action="">
                                        @csrf
                                        <input type="hidden" name="also" value="">
                                        <button type="submit" class="btn btn-primary w-100"><i class="fa fa-gift me-2"></i>2 hədiyyəni al</button>
                                    </form>
                                    <button type="button" class="btn btn-ghost-dark w-100 pair-off" disabled hidden></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endunless
                </div>
            </div>

            {{-- Sağ: qaydalar --}}
            <div class="col-lg-4">
                <div class="side-card market-rules" @if ($showRules) data-game-rules="Hədiyyə marketi" data-rules-ok="Tanış oldum, hədiyyə seç" @endif>{{-- qaydalar pəncərəsi yalnız ilk girişdə (MarketController::index) --}}
                    <h5><i class="fa fa-book me-2 text-primary"></i>Qaydalar</h5>
                    <div class="rules-note"><i class="fa fa-info-circle me-2"></i>Hədiyyədən istifadə üçün minimal masa hesabı <b>{{ (int) config('market.min_bill', 10) }} AZN</b> olmalıdır.</div>
                    <ul class="rules">
                        <li>Gündə ən çox <b>{{ $perDay }} hədiyyə</b> almaq olar.</li>
                        <li><b>{{ $fmt($smallMax) }} xala qədər</b> olan hədiyyələrdən 1 hədiyyənin yerinə <b>2 dənə</b> seçə bilərsiniz — rəfdə ikisinə də klikləyin, eyni hədiyyədən 2 ədəd üçün isə <b>+</b> düyməsini basın.</li>
                        <li>Hədiyyə seçəndə qiyməti <b>xal balansınızdan çıxılır</b> və sizə <b>kupon kodu</b> verilir.</li>
                        <li>Kodu restoranda <b>ofisianta göstərin</b>. Hər kod bir dəfə istifadə olunur.</li>
                        @php
                            $days = max(1, (int) config('market.code_valid_days', 1));
                        @endphp
                        <li>Kupon <b>{{ $days === 1 ? '1 gün (24 saat)' : $days . ' gün' }}</b> etibarlıdır — alındığı andan hesablanır, sonra qüvvədən düşür. Bütün kodlarınız: <a href="{{ route('prizes') }}">Hədiyyələrim</a>.</li>
                        <li>Xallar <b>geri qaytarılmır</b> və başqasına ötürülmür.</li>
                        @php
                            $earn = ['hər dost üçün', 'onun dəvət etdikləri üçün', 'onların dəvət etdikləri üçün'];
                            $earnParts = [];
                            foreach ($levels as $i => $p) {
                                $earnParts[] = e($earn[$i] ?? (($i + 1) . '-ci səviyyə üçün')) . ' <b>' . e($fmt($p)) . ' xal</b>';
                            }
                        @endphp
                        <li>Xal qazanmaq: dəvət linkiniz ilə qeydiyyatdan keçib profilini təsdiqləyən {!! implode(', ', $earnParts) !!}.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
@if ($cat && $verified)
    {{-- alışın təsdiqi (brauzerin "OK / Cancel" pəncərəsi əvəzinə) --}}
    <div class="modal fade" id="buyConfirm" tabindex="-1" aria-labelledby="buyConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content success-modal">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button>
                <div class="success-ico points-ico"><i class="fa fa-gift"></i></div>
                <h3 id="buyConfirmTitle">Hədiyyəni təsdiqləyin</h3>
                <p><b class="bc-name"></b><br>Balansınızdan <b class="bc-cost"></b> çıxılacaq, kupon kodu dərhal verilir.</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <button type="button" class="btn btn-primary px-4 py-2 bc-ok"><i class="fa fa-check me-2"></i>Təsdiqlə</button>
                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal">Ləğv et</button>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('js')
<script>
(function () {
    // aktiv kateqoriya düyməsi görünsün
    var chip = document.querySelector('.shop-chip.is-active');
    if (chip) chip.parentNode.scrollLeft = chip.offsetLeft - chip.parentNode.offsetLeft - 12;

    var shop = document.getElementById('shop');
    if (!shop) return;
    var items = shop.querySelectorAll('button.shelf-item');
    var panes = shop.querySelectorAll('.counter-pane[data-key]');
    var pair = shop.querySelector('.counter-pair');
    var counter = shop.querySelector('.shop-counter');
    var picked = [];
    items.forEach(function (b) { if (b.classList.contains('is-selected')) picked.push(b); });

    // kiçik hədiyyələrdən (data-small-max xala qədər) 2 dənə seçmək olar: ikinci kiçik hədiyyəyə klikləyəndə o da seçilir,
    // eyni hədiyyədən 2 ədəd üçün piştaxtadakı "+" basılır ("−" — yenə 1 ədəd);
    // iki fərqli hədiyyədən birinə klikləyəndə o seçimdən çıxır; adi hədiyyəyə klikləyəndə yalnız o seçilir
    function click(btn) {
        var small = btn.classList.contains('is-small');
        var i = picked.indexOf(btn);
        if (picked.length === 2 && picked[0] === btn && picked[1] === btn) return;
        else if (picked.length === 1 && picked[0] === btn) return;
        else if (i >= 0) picked.splice(i, 1);
        else if (small && picked.length === 1 && picked[0].classList.contains('is-small')) picked.push(btn);
        else if (small && picked.length === 2) picked = [picked[1], btn];
        else picked = [btn];
        render();
    }
    function fmt(n) { return String(Math.round(n * 100) / 100).replace('.', ','); }
    function render() {
        var same = picked.length === 2 && picked[0] === picked[1];
        items.forEach(function (b) {
            var on = picked.indexOf(b) >= 0;
            b.classList.toggle('is-selected', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            var q = b.querySelector('.shelf-qty');
            if (q) q.hidden = !(same && picked[0] === b);
        });
        var two = picked.length === 2;
        panes.forEach(function (p) { p.hidden = two || p.dataset.key !== picked[0].dataset.key; });
        if (pair) {
            pair.hidden = !two;
            pair.querySelector('.qty-step').hidden = !same; // iki fərqli hədiyyədə say düyməsi yoxdur
            if (two) {
                var total = parseFloat(picked[0].dataset.cost) + parseFloat(picked[1].dataset.cost);
                pair.querySelector('.pair-names').textContent = same ? '2 x ' + picked[0].dataset.label : picked[0].dataset.label + ' + ' + picked[1].dataset.label;
                pair.querySelector('.pair-note').textContent = (same ? '2 ədəd' : '2 hədiyyə') + ' — ikisi üçün bir kupon kodu verilir';
                pair.querySelector('.pair-total').textContent = fmt(total);
                var form = pair.querySelector('.pair-form');
                form.action = shop.dataset.buyUrl.replace('__KEY__', encodeURIComponent(picked[0].dataset.key));
                form.querySelector('[name=also]').value = picked[1].dataset.key;
                var balance = parseFloat(shop.dataset.balance || '0');
                var why = shop.dataset.verified !== '1' ? 'Daxil olun'
                    : parseInt(shop.dataset.smallLeft, 10) < 2 ? (parseInt(shop.dataset.smallLeft, 10) === 1 ? 'Bu gün yalnız 1 hədiyyə qalıb' : 'Bu günün limiti bitib')
                    : balance < total ? fmt(total - balance) + ' xal çatmır' : '';
                form.hidden = !!why;
                var off = pair.querySelector('.pair-off');
                off.hidden = !why;
                off.textContent = why;
            }
        }
        counter.classList.remove('bump');
        void counter.offsetWidth;
        counter.classList.add('bump');
    }
    // "Xalla al" — əvvəlcə təsdiq pəncərəsi (brauzerin confirm-i əvəzinə), "Təsdiqlə" basılanda forma göndərilir
    var modalEl = document.getElementById('buyConfirm');
    var pending = null;
    shop.querySelectorAll('form.buy-form').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!modalEl || !window.bootstrap) return; // pəncərə açılmasa, alış adi qaydada olur
            e.preventDefault();
            pending = f;
            var isPair = f.classList.contains('pair-form');
            modalEl.querySelector('.bc-name').textContent = isPair ? pair.querySelector('.pair-names').textContent : f.dataset.label;
            modalEl.querySelector('.bc-cost').textContent = (isPair ? pair.querySelector('.pair-total').textContent : f.dataset.cost) + ' xal';
            var ok = modalEl.querySelector('.bc-ok');
            ok.disabled = false;
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show(); // saytda Bootstrap 5.0.0 — getOrCreateInstance yoxdur
        });
    });
    if (modalEl) modalEl.querySelector('.bc-ok').addEventListener('click', function () {
        if (!pending) return;
        this.disabled = true; // iki dəfə basılmasın
        pending.submit();
    });
    items.forEach(function (btn) { btn.addEventListener('click', function () { click(btn); }); });
    // say: "+" — eyni hədiyyədən 2 ədəd, "−" — 1 ədəd (ən çox 2)
    shop.querySelectorAll('.qty-plus').forEach(function (b) {
        b.addEventListener('click', function () { if (picked.length === 1) { picked = [picked[0], picked[0]]; render(); } });
    });
    shop.querySelectorAll('.qty-minus').forEach(function (b) {
        b.addEventListener('click', function () { if (picked.length === 2) { picked = [picked[0]]; render(); } });
    });
})();
</script>
@endsection
