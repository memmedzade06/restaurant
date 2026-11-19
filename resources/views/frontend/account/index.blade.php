@extends('frontend.layouts.app')
@section('title', 'Hesabım - 50 Qəpik')

@php
    $me = auth()->user();

    // Dəvət sistemi: link/QR ilə qeydiyyatdan keçib nömrəsini təsdiqləyən hər kəs üçün xal
    $refLink = \App\Services\Referrals::link($me);
    $fmt = fn ($p) => \App\Services\Referrals::format((float) $p);
    $refLevels = \App\Services\Referrals::levels();
    $refPoints = $refLevels[0] ?? 1;
    // səviyyələr üzrə: 1-ci — birbaşa dəvətləriniz, 2-ci — onların dəvətləri, 3-cü — onlarınkı
    $levelIds = [$me->id];
    $refStats = [];
    foreach ($refLevels as $i => $pts) {
        $levelIds = $levelIds ? \App\Models\User::whereIn('referred_by', $levelIds)->pluck('id')->all() : [];
        $done = $levelIds ? \App\Models\User::whereIn('id', $levelIds)->whereNotNull('referral_rewarded_at')->count() : 0;
        $refStats[] = ['points' => $pts, 'done' => $done];
    }
    $refWaiting = $me->referrals()->whereNull('referral_rewarded_at')->count();
    $refRecent = $me->referrals()->latest('id')->take(10)->get(['id', 'name', 'created_at', 'referral_rewarded_at']);
    $levelNames = ['Dəvət etdiyiniz dostlar', 'Dostlarınızın dəvət etdikləri', 'Onların dəvət etdikləri'];

    // Oyunlar. Nərd: yalnız dostla oyunlar; nəticələr hər gecə silinir, qalib olduğu günlər qalır
    $nardToday = \App\Services\NardLeaderboard::myToday($me->id);
    $nardWins = \App\Models\NardDailyWinner::where('user_id', $me->id)->count();
    $stackBest = (int) \App\Models\GameResult::where('user_id', $me->id)->where('game', 'stack')->max('points');
    $prizes = \App\Models\WheelSpin::where('user_id', $me->id)->whereNotNull('code')->latest('id')->take(5)->get();
@endphp

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Hesabım</span>
        <h1>Salam, {{ $me->name }}!</h1>
        <p>Qeydiyyatınız təsdiqlənib</p>
    </div>
</section>

<section class="section pt-4 account-page">
    <div class="container">
        <div class="row g-4">
            @if ($me->is_admin)
                {{-- Yalnız admin: idarəetmə paneli (yuxarı menyuda link yoxdur — buradan keçilir) --}}
                @php
                    $usersTotal = \App\Models\User::count();
                    $usersVerified = \App\Models\User::verified()->count();
                    $usersToday = \App\Models\User::where('created_at', '>=', now('Asia/Baku')->startOfDay()->setTimezone(config('app.timezone')))->count();
                    $usersOnline = \App\Models\User::where('last_seen_at', '>=', now()->subSeconds(\App\Models\User::ONLINE_SECONDS))->count();
                    $prizesActive = \App\Models\WheelSpin::prizes(\App\Models\WheelSpin::ACTIVE)->count();
                @endphp
                <div class="col-12">
                    <div class="acc-card acc-admin-card">
                        <div class="acc-head"><span class="acc-ico"><i class="fa fa-user-shield"></i></span><h5>İdarəetmə</h5></div>
                        <div class="account-admin">
                            <a href="{{ route('admin.prizes') }}"><span><i class="fa fa-gift me-2"></i>Hədiyyə kodları <small class="acc-badge">{{ $prizesActive }} aktiv</small></span><i class="fa fa-arrow-right"></i></a>
                            <a href="{{ route('admin.users') }}"><span><i class="fa fa-users me-2"></i>İstifadəçilər <small class="acc-badge">{{ $usersTotal }}</small></span><i class="fa fa-arrow-right"></i></a>
                        </div>
                        <div class="acc-stats four mt-3 mb-0">
                            <div><b>{{ $usersTotal }}</b><span>Cəmi qeydiyyat</span></div>
                            <div><b>{{ $usersVerified }}</b><span>Təsdiqlənib</span></div>
                            <div><b>{{ $usersToday }}</b><span>Bu gün qeydiyyat</span></div>
                            <div><b>{{ $usersOnline }}</b><span>İndi saytda</span></div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Sol: şəxsi məlumatlar və çıxış --}}
            <div class="col-lg-4">
                <div class="acc-card acc-profile">
                    <div class="acc-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</div>
                    <h4 class="acc-name">{{ $me->name }}</h4>
                    <span class="acc-verified"><i class="fa fa-check-circle me-1"></i>{{ $me->phone_verified_at ? 'Telegram ilə təsdiqlənib' : ($me->google_id ? 'Google ilə təsdiqlənib' : 'E-poçt ilə təsdiqlənib') }}</span>
                    <ul class="account-list acc-details">
                        @if ($me->phone)
                            <li><span><i class="fa fa-phone-alt me-2"></i>Telefon</span><b>{{ \App\Support\Phone::format($me->phone) }}</b></li>
                        @endif
                        @if ($me->email)
                            <li><span><i class="fa fa-envelope me-2"></i>E-poçt</span><b>{{ $me->email }}</b></li>
                        @endif
                        @if ($me->google_id)
                            <li><span><i class="fab fa-google me-2"></i>Google</span><b class="text-success"><i class="fa fa-check me-1"></i>bağlıdır</b></li>
                        @endif
                        <li><span><i class="fa fa-calendar-alt me-2"></i>Qeydiyyat</span><b>{{ $me->created_at->format('d.m.Y') }}</b></li>
                    </ul>

                    @if (session('status'))
                        <div class="alert-ok text-start"><i class="fa fa-check-circle me-2"></i>{{ session('status') }}</div>
                    @endif
                    @error('google') <div class="alert-soft text-start mb-3">{{ $message }}</div> @enderror

                    {{-- Google bağlanmayıbsa: sonra "Google ilə davam et" eyni hesaba aparsın deyə --}}
                    @if (! $me->google_id && \App\Http\Controllers\Auth\GoogleAuthController::enabled())
                        <div class="acc-google">
                            @include('frontend.partials.google-button', ['href' => route('account.google'), 'label' => 'Google hesabını bağla', 'dividerFirst' => null, 'noDivider' => true])
                            <small class="hint">Bağlasanız, növbəti dəfə <b>"Google ilə davam et"</b> ilə də bu hesaba daxil ola bilərsiniz.</small>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-wine w-100 py-2"><i class="fa fa-sign-out-alt me-2"></i>Çıxış</button>
                    </form>
                </div>
            </div>

            {{-- Sağ: xallar və dəvət --}}
            <div class="col-lg-8">
                <div class="acc-card" id="xallar">
                    <div class="acc-head"><span class="acc-ico"><i class="fa fa-star"></i></span><h5>Xallar</h5>
                        <a href="{{ route('market') }}" class="acc-more"><i class="fa fa-gift me-1"></i>Hədiyyə marketi <i class="fa fa-arrow-right ms-1"></i></a></div>
                    <div class="points-box">
                        <div class="points-balance">
                            <span class="points-num">{{ $fmt($me->points) }}</span>
                            <span class="points-label">xal</span>
                        </div>
                        <ul class="account-list points-levels">
                            @foreach ($refStats as $i => $s)
                                <li><span>{{ $levelNames[$i] ?? ($i + 1) . '-ci səviyyə' }} <small>({{ $fmt($s['points']) }} xal)</small></span>
                                    <b>{{ $s['done'] }} nəfər @if ($s['done'])<em>+{{ $fmt($s['done'] * $s['points']) }}</em>@endif</b></li>
                            @endforeach
                            @if ($refWaiting)
                                <li><span>Təsdiq gözləyir</span><b>{{ $refWaiting }} nəfər</b></li>
                            @endif
                        </ul>
                    </div>
                </div>

                <div class="acc-card mt-4">
                    <div class="acc-head"><span class="acc-ico"><i class="fa fa-user-plus"></i></span><h5>Dostlarını dəvət et</h5></div>
                    <p class="acc-note">Linki və ya QR kodu paylaşın. Kimsə onunla qeydiyyatdan keçib profilini təsdiqləyəndə balansınıza <b>{{ $fmt($refPoints) }} xal</b> əlavə olunur.
                        @if (count($refLevels) > 1)
                            Dostunuz da öz linkini paylaşsa, onun dəvət etdiyi hər kəs üçün sizə <b>{{ $fmt($refLevels[1]) }} xal</b>@if (count($refLevels) > 2), onların dəvət etdikləri üçün isə <b>{{ $fmt($refLevels[2]) }} xal</b>@endif gəlir.
                        @endif
                    </p>
                    <div class="ref-share">
                        <div class="ref-qr" id="refQr" data-link="{{ $refLink }}" role="img" aria-label="Dəvət linkinizin QR kodu"></div>
                        <div class="ref-side">
                            <label for="refLink" class="ref-label">Dəvət linkiniz</label>
                            <div class="ref-link">
                                <input type="text" id="refLink" value="{{ $refLink }}" readonly>
                                <button type="button" class="btn btn-primary" id="refCopy"><i class="fa fa-copy me-1"></i>Kopyala</button>
                            </div>
                            <div class="ref-actions">
                                <button type="button" class="btn btn-ghost-dark" id="refShare" hidden><i class="fa fa-share-alt me-2"></i>Paylaş</button>
                                <button type="button" class="btn btn-ghost-dark" id="refDownload"><i class="fa fa-download me-2"></i>QR kodu yüklə</button>
                            </div>
                        </div>
                    </div>

                    @if ($refRecent->isNotEmpty())
                        <h6 class="acc-sub">Son dəvət etdikləriniz</h6>
                        <ul class="account-list ref-list">
                            @foreach ($refRecent as $r)
                                <li><span>{{ $r->short_name }} · {{ $r->created_at->timezone('Asia/Baku')->format('d.m.Y') }}</span>
                                    @if ($r->referral_rewarded_at)
                                        <b class="text-success">+{{ $fmt($refPoints) }} xal</b>
                                    @else
                                        <b class="text-muted">təsdiq gözləyir</b>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Oyunlar --}}
            <div class="col-lg-5">
                <div class="acc-card h-100">
                    <div class="acc-head"><span class="acc-ico"><i class="fa fa-gamepad"></i></span><h5>Oyunlar</h5>
                        <a href="{{ route('games.index') }}" class="acc-more">Oyna <i class="fa fa-arrow-right ms-1"></i></a></div>
                    <h6 class="acc-sub"><i class="fa fa-dice me-2"></i>Nərd (dostla oyun) — bu gün</h6>
                    <div class="acc-stats">
                        <div><b>{{ $nardToday['points'] }}</b><span>xal</span></div>
                        <div><b>{{ $nardToday['wins'] }}</b><span>qələbə</span></div>
                        <div><b>{{ $nardToday['games'] }}</b><span>oyun</span></div>
                        <div><b>{{ $nardWins }}</b><span>dəfə günün qalibi</span></div>
                    </div>
                    <h6 class="acc-sub"><i class="fa fa-layer-group me-2"></i>Stəkan qülləsi</h6>
                    <div class="acc-stats">
                        <div class="wide"><b>{{ $stackBest }}</b><span>xal — rekordunuz</span></div>
                    </div>
                </div>
            </div>

            {{-- Hədiyyələrim --}}
            <div class="col-lg-7">
                <div class="acc-card h-100">
                    <div class="acc-head"><span class="acc-ico"><i class="fa fa-gift"></i></span><h5>Hədiyyələrim</h5>
                        <a href="{{ route('prizes') }}" class="acc-more">Hamısına bax <i class="fa fa-arrow-right ms-1"></i></a></div>
                    @if ($prizes->isEmpty())
                        <p class="acc-empty"><i class="fa fa-gift"></i>Hələ hədiyyə yoxdur. Oyunlarda qazandığınız kodlar burada görünəcək.</p>
                    @else
                        <ul class="prize-cards mb-0">
                            @foreach ($prizes as $p)
                                @include('frontend.prizes._card', ['p' => $p])
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

        </div>
    </div>
</section>

@endsection

@section('js')
{{-- QR kod brauzerdə yaradılır (link heç bir kənar xidmətə göndərilmir) --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script src="{{ asset('js/referral.js') }}?v=1"></script>
@endsection
