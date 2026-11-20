{{-- Oyun səhifəsinin yuxarısı: "Oyunlara qayıt" + diqqətçəkən qızılı "Hədiyyə marketi" düyməsi (xalları hədiyyəyə çevirmək) --}}
<div class="games-topbar">
    <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>
    <a href="{{ route('market') }}" class="market-link">
        <i class="fa fa-gift"></i>
        <span>Hədiyyə marketi</span>
        @auth
            <b class="market-link-balance"><i class="fa fa-star"></i>{{ \App\Services\Referrals::format((float) auth()->user()->points) }}</b>
        @endauth
    </a>
</div>
