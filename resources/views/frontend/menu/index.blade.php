@extends('frontend.layouts.app')
@section('title', 'Menyu - 50 Qəpik')

@section('content')

<section class="page-hero">
    <div class="container">
        <span class="eyebrow">50 Qəpik</span>
        <h1>Menyu</h1>
        <p>Kateqoriya seçin və sevdiyiniz yeməyi tapın</p>
    </div>
</section>

@php
    $categories = [
        1 => ['Səhər yeməyi', 'https://img.icons8.com/external-justicon-flat-justicon/64/external-breakfast-hotel-essentials-justicon-flat-justicon.png'],
        2 => ['Şorbalar', 'https://img.icons8.com/external-chloe-kerismaker/64/external-Soup-autumn-chloe-kerismaker.png'],
        3 => ['Qazan yeməkləri', 'https://img.icons8.com/external-icongeek26-flat-icongeek26/64/external-lunch-food-levitation-icongeek26-flat-icongeek26.png'],
        4 => ['Xəmir yeməkləri', 'https://img.icons8.com/external-icongeek26-linear-colour-icongeek26/64/external-Dough-recipes-and-ingredients-icongeek26-linear-colour-icongeek26.png'],
        5 => ['Salatlar', 'https://img.icons8.com/external-wanicon-lineal-color-wanicon/64/external-salad-cooking-wanicon-lineal-color-wanicon.png'],
        6 => ['Çay dəstgahı', 'https://img.icons8.com/cotton/64/tea-1.png'],
        7 => ['İçkilər', 'https://img.icons8.com/cotton/64/cola.png'],
        8 => ['Qəlyan seti', 'https://img.icons8.com/arcade/64/hookah.png'],
    ];
@endphp

<section class="section pt-4">
    <div class="container">
        <div class="cat-bar" role="tablist">
            @foreach ($categories as $id => [$name, $icon])
                <button type="button" class="cat-btn {{ $loop->first ? 'active' : '' }}" data-category="{{ $id }}" role="tab">
                    <img src="{{ $icon }}" alt="">{{ $name }}
                </button>
            @endforeach
        </div>

        <div id="menuItems" class="row g-4"></div>
    </div>
</section>

@endsection

@section('js')
<script>
    (function () {
        const box = document.getElementById('menuItems');
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const imgBase = @json(asset('img/items')) + '/';

        function skeleton() {
            box.innerHTML = Array.from({length: 6}, () => '<div class="col-lg-6"><div class="skeleton"></div></div>').join('');
        }

        function render(items) {
            if (!items.length) {
                box.innerHTML = '<div class="col-12 menu-empty">Bu kateqoriyada hələ yemək yoxdur.</div>';
                return;
            }
            box.innerHTML = items.map((it, i) => `
                <div class="col-lg-6">
                    <div class="menu-item" style="animation-delay:${i * 40}ms">
                        <img src="${imgBase + encodeURIComponent(it.image || '')}" alt="${esc(it.name)}" loading="lazy">
                        <div class="info">
                            <h5>${esc(it.name)}</h5>
                            ${it.description ? `<small>${esc(it.description)}</small>` : ''}
                        </div>
                        <span class="price">${Number(it.price).toFixed(2)} AZN</span>
                    </div>
                </div>`).join('');
        }

        function load(id) {
            skeleton();
            $.getJSON(@json(route('get_items_by_category')), {data: id})
                .done(render)
                .fail(() => box.innerHTML = '<div class="col-12 menu-empty">Menyu yüklənmədi, səhifəni yeniləyin.</div>');
        }

        document.querySelectorAll('.cat-btn').forEach(btn => btn.addEventListener('click', () => {
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.toggle('active', b === btn));
            btn.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'});
            load(btn.dataset.category);
        }));

        load(1);
    })();
</script>
@endsection
