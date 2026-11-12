@extends('frontend.layouts.app')
@section('title', 'Oyunlar - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">50 Qəpik</span>
        <h1>Oyunlar</h1>
        <p>Yeməyinizi gözləyərkən əylənin — hədiyyələr və xallar qazanın</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('games.nard') }}" class="game-card">
                    <div class="game-art nard-art">
                        <span class="dice-a">⚅</span><span class="dice-b">⚂</span>
                    </div>
                    <div class="game-body">
                        <h4>Uzun nərd</h4>
                        <p>Kompüterə qarşı klassik uzun nərd. Qələbələr rekordlar cədvəlinə düşür.</p>
                        <span class="game-go">Oyna <i class="fa fa-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('games.wheel') }}" class="game-card">
                    <div class="game-art wheel-art"><i class="fa fa-gift"></i></div>
                    <div class="game-body">
                        <h4>Bəxt çarxı</h4>
                        <p>Çarxı fırladın: endirimlər və hədiyyə qəlyan qazanın.</p>
                        <span class="game-go">Fırlat <i class="fa fa-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('games.stack') }}" class="game-card">
                    <div class="game-art stack-art">
                        <span class="mini-glass g1"></span><span class="mini-glass g2"></span><span class="mini-glass g3"></span>
                    </div>
                    <div class="game-body">
                        <h4>Stəkan qülləsi</h4>
                        <p>Armudu stəkanları üst-üstə düzün — qüllə aşmasın! Ən hündür qüllə rekordlara düşür.</p>
                        <span class="game-go">Oyna <i class="fa fa-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-md-6">
                <div class="side-card h-100">
                    <h5><i class="fa fa-trophy me-2 text-primary"></i>Nərd liderləri</h5>
                    @include('frontend.games._leaders', ['leaders' => $leaders])
                </div>
            </div>
            <div class="col-md-6">
                <div class="side-card h-100">
                    <h5><i class="fa fa-trophy me-2 text-primary"></i>Stəkan qülləsi liderləri</h5>
                    @include('frontend.games._leaders', ['leaders' => $stackLeaders])
                </div>
            </div>
        </div>
        @guest
            <p class="small text-muted mt-3 mb-0 text-center">Xallarınızın saxlanması üçün <a href="{{ route('register') }}">qeydiyyatdan keçin</a>.</p>
        @endguest
    </div>
</section>
@endsection
