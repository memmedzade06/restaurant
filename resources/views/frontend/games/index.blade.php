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

        <div class="side-card mt-4 mb-0" id="online-box" data-url="{{ route('games.online') }}">
            <h5 class="d-flex align-items-center">
                <span class="online-dot me-2"></span>İndi saytda
                <span class="online-count ms-2" id="online-count">{{ $onlineCount }}</span>
            </h5>
            <p class="text-muted mb-0 {{ $onlineUsers->isEmpty() ? '' : 'd-none' }}" id="online-empty">Hazırda saytda heç kim yoxdur.</p>
            <ul class="online-users" id="online-list">
                @foreach ($onlineUsers as $u)
                    <li class="{{ auth()->id() === $u->id ? 'me' : '' }}"><span class="online-dot"></span>{{ $u->short_name }}</li>
                @endforeach
                @if ($onlineCount > $onlineUsers->count())
                    <li class="more">+{{ $onlineCount - $onlineUsers->count() }} nəfər</li>
                @endif
            </ul>
        </div>
        @guest
            <p class="small text-muted mt-3 mb-0 text-center">Xallarınızın saxlanması üçün <a href="{{ route('register') }}">qeydiyyatdan keçin</a>.</p>
        @endguest
    </div>
</section>
@endsection

@section('js')
<script>
    // "İndi saytda" siyahısını səhifəni yeniləmədən hər 15 saniyədən bir yeniləyirik
    (function () {
        var box = document.getElementById('online-box');
        var list = document.getElementById('online-list');
        var count = document.getElementById('online-count');
        var empty = document.getElementById('online-empty');

        function render(data) {
            count.textContent = data.count;
            empty.classList.toggle('d-none', data.users.length > 0);
            list.innerHTML = '';
            data.users.forEach(function (u) {
                var li = document.createElement('li');
                if (u.me) li.className = 'me';
                var dot = document.createElement('span');
                dot.className = 'online-dot';
                li.appendChild(dot);
                li.appendChild(document.createTextNode(u.name));
                list.appendChild(li);
            });
            if (data.count > data.users.length) {
                var more = document.createElement('li');
                more.className = 'more';
                more.textContent = '+' + (data.count - data.users.length) + ' nəfər';
                list.appendChild(more);
            }
        }

        function refresh() {
            if (document.hidden) return;
            fetch(box.dataset.url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) { if (data) render(data); })
                .catch(function () {});
        }

        setInterval(refresh, 15000);
        document.addEventListener('visibilitychange', refresh);
    })();
</script>
@endsection
