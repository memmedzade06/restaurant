<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Restoran - 50 Qəpik')</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="50 Qəpik — Elmlərdə milli və Avropa mətbəxi, çay dəstgahı və qəlyan seti. Dadlı yeməklər, sərfəli qiymətlər.">

    <link href="{{ asset('img/logo.png') }}" rel="icon">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Pacifico&family=Playfair+Display:ital,wght@0,600;0,700;1,700&display=swap" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <link href="{{ asset('lib/animate/animate.min.css') }}" rel="stylesheet">
    <link href="{{ asset('lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('css/custom.css') }}?v=8" rel="stylesheet">
    <link href="{{ asset('css/games.css') }}?v=32" rel="stylesheet">
    @yield('css')
</head>

<body>
<div class="container-xxl bg-white p-0">

    {{-- Naviqasiya --}}
    <nav class="navbar navbar-expand-lg navbar-dark site-nav px-4 px-lg-5">
        <a href="{{ route('home') }}" class="navbar-brand p-0">
            <img src="{{ asset('img/logo.png') }}" alt="50 Qəpik">
            <span class="brand-name">50 Qəpik<small>Elmlər</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-label="Menyu">
            <span class="fa fa-bars"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarCollapse">
            <div class="navbar-nav ms-auto align-items-lg-center">
                <a href="{{ route('home') }}" class="nav-item nav-link {{ request()->routeIs('home') ? 'active' : '' }}">Ana səhifə</a>
                <a href="{{ route('menu') }}" class="nav-item nav-link {{ request()->routeIs('menu') ? 'active' : '' }}">Menyu</a>
                <a href="{{ route('games.index') }}" class="nav-item nav-link {{ request()->routeIs('games.*') ? 'active' : '' }}">Oyunlar</a>
                <a href="{{ route('contact') }}" class="nav-item nav-link {{ request()->routeIs('contact') ? 'active' : '' }}">Əlaqə</a>
                @if (auth()->user()?->is_admin)
                    <a href="{{ route('admin.prizes') }}" class="nav-item nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}"><i class="fa fa-gift me-1"></i>Hədiyyələr</a>
                @endif
            </div>
            @auth
                <a href="{{ route('account') }}" class="nav-user"><i class="fa fa-user-circle"></i>{{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}</a>
            @else
                <a href="{{ route('login') }}" class="nav-user"><i class="fa fa-user-circle"></i>Daxil ol</a>
            @endauth
            <a href="tel:+994517171565" class="btn btn-primary nav-cta"><i class="fa fa-phone-alt me-2"></i>Zəng et</a>
        </div>
    </nav>

    @yield('content')

    {{-- Footer --}}
    <footer class="site-footer">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5">
                    <div class="foot-logo">
                        <img src="{{ asset('img/logo.png') }}" alt="50 Qəpik">
                        <span class="brand-name">50 Qəpik<small>Elmlər</small></span>
                    </div>
                    <p class="mb-0">Hər zövqə uyğun menyu: səhər yeməyindən qazan yeməklərinə, çay dəstgahından qəlyan setinə qədər.</p>
                    <div class="socials">
                        <a href="https://www.instagram.com/50qepik_elmler/" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="tel:+994517171565" aria-label="Zəng et"><i class="fa fa-phone-alt"></i></a>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <h5>Səhifələr</h5>
                    <ul>
                        <li><a href="{{ route('home') }}">Ana səhifə</a></li>
                        <li><a href="{{ route('menu') }}">Menyu</a></li>
                        <li><a href="{{ route('games.index') }}">Oyunlar</a></li>
                        <li><a href="{{ route('contact') }}">Əlaqə</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-4">
                    <h5>Əlaqə</h5>
                    <ul>
                        <li><i class="fa fa-phone-alt"></i><a href="tel:+994517171565">+994 51 717 15 65</a></li>
                        <li><i class="fab fa-instagram"></i><a href="https://www.instagram.com/50qepik_elmler/" target="_blank" rel="noopener">@50qepik_elmler</a></li>
                        <li><i class="fa fa-map-marker-alt"></i>Elmlər, Bakı</li>
                    </ul>
                </div>
            </div>
            <div class="copy d-flex flex-wrap justify-content-between gap-2">
                <span>&copy; {{ date('Y') }} 50 Qəpik. Bütün hüquqlar qorunur.</span>
                <a href="{{ route('menu') }}">Menyuya bax <i class="fa fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </footer>

    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top" aria-label="Yuxarı"><i class="bi bi-arrow-up"></i></a>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('lib/wow/wow.min.js') }}"></script>
<script src="{{ asset('lib/easing/easing.min.js') }}"></script>
<script src="{{ asset('lib/waypoints/waypoints.min.js') }}"></script>
<script src="{{ asset('lib/counterup/counterup.min.js') }}"></script>
<script src="{{ asset('lib/owlcarousel/owl.carousel.min.js') }}"></script>
<script src="{{ asset('js/main.js') }}"></script>
@auth
<script>
    // "İndi saytda": səhifə açıq olduqca serverə siqnal göndəririk
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var ping = function () {
            fetch('{{ route('presence.ping') }}', {
                method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' }
            }).catch(function () {});
        };
        ping();
        setInterval(ping, 25000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) ping(); });
        window.addEventListener('pagehide', function () {
            var data = new FormData();
            data.append('_token', token);
            navigator.sendBeacon && navigator.sendBeacon('{{ route('presence.leave') }}', data);
        });
    })();
</script>
@if (auth()->user()->hasVerifiedPhone())
{{-- Nərd dəvətləri: saytın istənilən səhifəsində gələn dəvət görünür, qəbul olunan dəvət oyuna aparır --}}
<div id="nardInbox" class="nard-inbox" aria-live="polite"
     data-url="{{ route('games.nard.inbox') }}"
     data-accept="{{ route('games.nard.invite.accept', '__ID__') }}"
     data-decline="{{ route('games.nard.invite.decline', '__ID__') }}"></div>
<script src="{{ asset('js/games/nard-inbox.js') }}?v=2"></script>
@endif
@endauth
@yield('js')
</body>

</html>
