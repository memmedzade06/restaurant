<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="utf-8">
    @php($gaId = config('services.google_analytics.id'))
    {{-- Google Analytics: yalnız real saytda; admin özü baxanda statistikaya düşmür --}}
    @if ($gaId && ! app()->environment('local') && ! auth()->user()?->is_admin)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($gaId) }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($gaId));
        </script>
    @endif
    <title>@yield('title', 'Restoran - 50 Qəpik')</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        {{-- hədiyyə kodunun QR kodu (prize-qr.js): admin oxudub açanda kod istifadə olunur --}}
        <meta name="prize-qr-url" content="{{ route('admin.prizes.qr', '__CODE__') }}">
    @endauth
    <meta name="description" content="50 Qəpik — Elmlərdə milli və Avropa mətbəxi, çay dəstgahı və qəlyan seti. Dadlı yeməklər, sərfəli qiymətlər.">

    {{-- İkonlar: Google axtarışı /favicon.ico və 48-in misli ölçülü kvadrat ikon istəyir --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="48x48">
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-96.png') }}?v=2" sizes="96x96">
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-192.png') }}?v=2" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}?v=2">
    <meta name="theme-color" content="#7a1420">
    <meta property="og:site_name" content="50 Qəpik">
    @if (request()->routeIs('home'))
        {{-- Google axtarışında sayt adı "50qepik.com" yox, "50 Qəpik" göstərilsin --}}
        <script type="application/ld+json">{"@@context":"https://schema.org","@@type":"WebSite","name":"50 Qəpik","alternateName":"50 Qəpik Elmlər","url":"{{ url('/') }}/"}</script>
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Pacifico&family=Playfair+Display:ital,wght@0,600;0,700;1,700&display=swap" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <link href="{{ asset('lib/animate/animate.min.css') }}" rel="stylesheet">
    <link href="{{ asset('lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('css/custom.css') }}?v=57" rel="stylesheet">
    <link href="{{ asset('css/games.css') }}?v=62" rel="stylesheet">
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
                <a href="{{ route('reservations') }}" class="nav-item nav-link {{ request()->routeIs('reservations') ? 'active' : '' }}">Rezervasiya</a>
                <a href="{{ route('games.index') }}" class="nav-item nav-link {{ request()->routeIs('games.*') ? 'active' : '' }}">Oyunlar</a>
                <a href="{{ route('contact') }}" class="nav-item nav-link {{ request()->routeIs('contact') ? 'active' : '' }}">Əlaqə</a>
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

    {{-- Təsdiqlənməmiş profil: hər səhifədə aşağıda xəbərdarlıq (təsdiq səhifələrinin özündə yox) --}}
    @auth
        @if (! auth()->user()->isVerified() && ! request()->routeIs('verify.*'))
            <div class="verify-bar" id="verifyBar" role="status" hidden>
                <i class="fa fa-exclamation-circle verify-bar-ico"></i>
                <div class="verify-bar-text">
                    <b>Profiliniz təsdiqlənməyib.</b>
                    <span>Xal, hədiyyə və liderlər cədvəli üçün profilinizi təsdiqləyin.</span>
                </div>
                <a href="{{ route(auth()->user()->verificationRoute()) }}" class="btn btn-primary verify-bar-btn">Profili təsdiqlə</a>
                <button type="button" class="verify-bar-close" aria-label="Bağla">&times;</button>
            </div>
            <script>
                (function () {
                    var bar = document.getElementById('verifyBar');
                    try { if (sessionStorage.getItem('verifyBarClosed')) return; } catch (e) {}
                    bar.hidden = false;
                    document.body.classList.add('has-verify-bar');
                    bar.querySelector('.verify-bar-close').addEventListener('click', function () {
                        bar.hidden = true;
                        document.body.classList.remove('has-verify-bar');
                        try { sessionStorage.setItem('verifyBarClosed', '1'); } catch (e) {}
                    });
                })();
            </script>
        @endif
    @endauth

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
                        <li><a href="{{ route('reservations') }}">Masa rezervasiyası</a></li>
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
                <span>&copy; {{ date('Y') }} 50 Qəpik. Bütün hüquqlar qorunur. · <a href="{{ route('privacy') }}">Məxfilik siyasəti</a></span>
                <a href="{{ route('menu') }}">Menyuya bax <i class="fa fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </footer>

    {{-- "yuxarı" düyməsi oyunlarda və hədiyyə marketində yoxdur — oyunun/piştaxtanın düymələrinin üstünə düşür --}}
    @unless (request()->routeIs('games.*', 'market'))
        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top" aria-label="Yuxarı"><i class="bi bi-arrow-up"></i></a>
    @endunless
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('lib/wow/wow.min.js') }}"></script>
<script src="{{ asset('lib/easing/easing.min.js') }}"></script>
<script src="{{ asset('lib/waypoints/waypoints.min.js') }}"></script>
<script src="{{ asset('lib/counterup/counterup.min.js') }}"></script>
<script src="{{ asset('lib/owlcarousel/owl.carousel.min.js') }}"></script>
<script src="{{ asset('js/main.js') }}?v=2"></script>
<script src="{{ asset('js/games/rules.js') }}?v=3"></script>{{-- oyun səhifələrində əvvəlcə qaydalar pəncərəsi --}}
<script src="{{ asset('js/password-toggle.js') }}?v=1"></script>{{-- şifrə sahələrində göz düyməsi --}}
@include('frontend.partials.registered-modal')
@include('frontend.partials.points-market-modal')
@include('frontend.partials.earn-more')
{{-- Adminə yaz: sağ aşağı küncdə söhbət pəncərəsi (və Mesajlar səhifələrindəki söhbət) --}}
@include('frontend.support._widget')
<script src="{{ asset('js/support-chat.js') }}?v=3"></script>
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
@if (auth()->user()->isVerified() && config('games.nard.enabled'))
{{-- Nərd dəvətləri: saytın istənilən səhifəsində gələn dəvət görünür, qəbul olunan dəvət oyuna aparır --}}
<div id="nardInbox" class="nard-inbox" aria-live="polite"
     data-url="{{ route('games.nard.inbox') }}"
     data-accept="{{ route('games.nard.invite.accept', '__ID__') }}"
     data-decline="{{ route('games.nard.invite.decline', '__ID__') }}"></div>
<script src="{{ asset('js/games/nard-inbox.js') }}?v=2"></script>
@endif
@endauth
@yield('js')
@auth
    {{-- hədiyyə kodlarının altında QR kod — yalnız daxil olmuş istifadəçidə (kod onlarda olur) --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js" defer></script>
    <script src="{{ asset('js/prize-qr.js') }}?v=2" defer></script>
@endauth
</body>

</html>
