@extends('frontend.layouts.app')

@section('content')

{{-- Hero --}}
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="hero-eyebrow animated fadeInDown"><i class="fa fa-utensils"></i> Elmlərdə dadlı ünvan</span>
                <h1 class="animated slideInLeft">Dadlı yeməklərimizdən <span>həzz alın</span></h1>
                <p class="lead animated fadeIn">Səhər yeməyindən qazan yeməklərinə, çay dəstgahından qəlyan setinə qədər. Hər zövqə uyğun menyu, sərfəli qiymətlər.</p>
                <div class="hero-actions animated fadeInUp">
                    <a href="{{ route('menu') }}" class="btn btn-primary"><i class="fa fa-book-open me-2"></i>Menyuya bax</a>
                    <a href="tel:+994517171565" class="btn btn-ghost"><i class="fa fa-phone-alt me-2"></i>Masa sifariş et</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-plate">
                    <img class="img-fluid" src="{{ asset('img/duyu.png') }}" alt="Plov">
                    <div class="hero-badge">
                        <i class="fa fa-fire-alt"></i>
                        <div>Hər gün təzə<small>öz mətbəximizdə bişirilir</small></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Üstünlüklər --}}
<section class="features">
    <div class="container">
        <div class="row g-4">
            @foreach ([
                ['fa-drumstick-bite', 'Milli mətbəx', 'Plov, dolma, qutab, piti — evdəki kimi dadlı.'],
                ['fa-leaf', 'Təzə məhsullar', 'Hər gün təzə ərzaqla, öz mətbəximizdə hazırlanır.'],
                ['fa-mug-hot', 'Çay dəstgahı', 'Mürəbbə və şirniyyatla tam çay süfrəsi.'],
                ['fa-smoking', 'Qəlyan seti', 'Rahat atmosferdə dostlarla xoş vaxt.'],
            ] as $i => [$icon, $title, $text])
                <div class="col-6 col-lg-3 wow fadeInUp" data-wow-delay="{{ 0.1 + $i * 0.1 }}s">
                    <div class="feature">
                        <div class="ico"><i class="fa {{ $icon }}"></i></div>
                        <h5>{{ $title }}</h5>
                        <p>{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Haqqımızda --}}
<section class="section">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <div class="about-grid">
                    <img class="wow zoomIn" data-wow-delay="0.1s" src="{{ asset('img/outside.jpg') }}" alt="Restoranın xarici görünüşü">
                    <img class="wow zoomIn" data-wow-delay="0.3s" src="{{ asset('img/inside.jpeg') }}" alt="Restoranın zalı">
                    <img class="wow zoomIn" data-wow-delay="0.5s" src="{{ asset('img/duyu2.PNG') }}" alt="Plov">
                    <img class="wow zoomIn" data-wow-delay="0.7s" src="{{ asset('img/set.png') }}" alt="Yemək seti">
                </div>
            </div>
            <div class="col-lg-6">
                <span class="eyebrow">Haqqımızda</span>
                <h2 class="mb-4 mt-2">Restoranımıza <i class="fa fa-utensils text-primary mx-1"></i> xoş gəlmisiniz</h2>
                <p class="mb-3">50 Qəpik sizin sevəcəyiniz ən dadlı təamları hazırlayır. Milli yeməklərimizi ənənəvi reseptlərlə, sevgi ilə bişiririk.</p>
                <p class="mb-4">Ailəvi nahar, dostlarla görüş və ya iş yeməyi — hər zövqə uyğun menyu ilə xidmətinizdəyik.</p>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="stat"><b data-toggle="counter-up">15</b><span>İllik<strong>təcrübə</strong></span></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="stat"><b data-toggle="counter-up">15</b><span>Təcrübəli<strong>personal</strong></span></div>
                    </div>
                </div>
                <a href="{{ route('menu') }}" class="btn btn-wine py-3 px-5">Menyuya keçid <i class="fa fa-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>
</section>

{{-- Seçmə yeməklər --}}
@if ($featured->isNotEmpty())
<section class="section pt-0">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Menyudan</span>
            <h2>Qonaqlarımızın sevimliləri</h2>
            <p>Mətbəximizin ən çox sifariş edilən yeməklərindən bəziləri.</p>
        </div>
        <div class="row g-4">
            @foreach ($featured as $i => $item)
                <div class="col-sm-6 col-lg-4 wow fadeInUp" data-wow-delay="{{ 0.1 + ($i % 3) * 0.1 }}s">
                    <div class="dish-card">
                        <div class="dish-img">
                            <img src="{{ asset('img/items/' . $item->image) }}" alt="{{ $item->name }}" loading="lazy">
                        </div>
                        <div class="dish-body">
                            <div>
                                <h5>{{ $item->name }}</h5>
                                @if ($item->description)
                                    <p class="dish-desc">{{ $item->description }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="text-center mt-5">
            <a href="{{ route('menu') }}" class="btn btn-primary py-3 px-5">Bütün menyu <i class="fa fa-arrow-right ms-2"></i></a>
        </div>
    </div>
</section>
@endif

{{-- Zəng bandı --}}
<section class="pb-2">
    <div class="container">
        <div class="cta-band wow fadeInUp">
            <div>
                <h2>Masanızı öncədən rezerv edin</h2>
                <p>Ad günü, ailəvi məclis və ya dostlarla görüş üçün masanızı saytda seçin və ya bizə zəng edin.</p>
            </div>
            <div class="cta-actions">
                <a href="{{ route('reservations') }}" class="btn btn-primary"><i class="fa fa-chair me-2"></i>Masa rezerv et</a>
                <a href="tel:+994517171565" class="btn btn-ghost"><i class="fa fa-phone-alt me-2"></i>+994 51 717 15 65</a>
            </div>
        </div>
    </div>
</section>

@endsection
