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
    $refTree = \App\Services\Referrals::tree($me); // dəvət piramidası: kim sizin linkinizlə gəlib, onlar kimi gətirib
    $levelNames = ['Dəvət etdiyiniz dostlar', 'Dostlarınızın dəvət etdikləri', 'Onların dəvət etdikləri'];

    // Oyunlar. Nərd: yalnız dostla oyunlar; nəticələr hər gecə silinir, qalib olduğu günlər qalır
    $nardToday = \App\Services\NardLeaderboard::myToday($me->id);
    $nardWins = \App\Models\NardDailyWinner::where('user_id', $me->id)->count();
    $stackBest = (int) \App\Models\GameResult::where('user_id', $me->id)->where('game', 'stack')->max('points');
    // aktiv və istifadə olunmuş kuponlar; vaxtı keçmiş (istifadə olunmamış) göstərilmir
    $prizes = \App\Models\WheelSpin::where('user_id', $me->id)->whereNotNull('code')
        ->where(fn ($q) => $q->whereNotNull('redeemed_at')->orWhereNull('expires_at')->orWhere('expires_at', '>', now()))
        ->latest('id')->take(5)->get();
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
            {{-- Bildirişlər (məs. Google hesabı bağlandı) — profil kartı aşağıda olduğu üçün yuxarıda göstərilir --}}
            @if (session('status') || $errors->has('google'))
                <div class="col-12">
                    @if (session('status'))
                        <div class="alert-ok text-start mb-0"><i class="fa fa-check-circle me-2"></i>{{ session('status') }}</div>
                    @endif
                    @error('google') <div class="alert-soft text-start mb-0">{{ $message }}</div> @enderror
                </div>
            @endif

            {{-- Xallar: profilə girəndə ən yuxarıda --}}
            <div class="col-12">
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

                    {{-- xal tarixçəsi: balans necə yığılıb --}}
                    <h6 class="acc-sub" id="tarixce"><i class="fa fa-history me-2"></i>Xal tarixçəsi</h6>
                    @include('frontend.account._points_history', ['history' => \App\Services\Points::history($me, 30)])
                </div>
            </div>

            @if ($me->is_admin)
                {{-- Yalnız admin: idarəetmə paneli (yuxarı menyuda link yoxdur — buradan keçilir) --}}
                @php
                    $usersTotal = \App\Models\User::count();
                    $usersVerified = \App\Models\User::verified()->count();
                    $usersToday = \App\Models\User::where('created_at', '>=', now('Asia/Baku')->startOfDay()->setTimezone(config('app.timezone')))->count();
                    $usersOnline = \App\Models\User::where('last_seen_at', '>=', now()->subSeconds(\App\Models\User::ONLINE_SECONDS))->count();
                    $prizesActive = \App\Models\WheelSpin::prizes(\App\Models\WheelSpin::ACTIVE)->count();
                    $reservationsToday = \App\Models\Reservation::active()->whereDate('date', now('Asia/Baku')->toDateString())->count();
                @endphp
                <div class="col-12">
                    <div class="acc-card acc-admin-card">
                        <div class="acc-head"><span class="acc-ico"><i class="fa fa-user-shield"></i></span><h5>İdarəetmə</h5></div>
                        <div class="account-admin">
                            <a href="{{ route('admin.prizes') }}"><span><i class="fa fa-gift me-2"></i>Hədiyyə kodları <small class="acc-badge">{{ $prizesActive }} aktiv</small></span><i class="fa fa-arrow-right"></i></a>
                            <a href="{{ route('admin.users') }}"><span><i class="fa fa-users me-2"></i>İstifadəçilər <small class="acc-badge">{{ $usersTotal }}</small></span><i class="fa fa-arrow-right"></i></a>
                            <a href="{{ route('admin.reservations') }}"><span><i class="fa fa-chair me-2"></i>Rezervasiyalar <small class="acc-badge">{{ $reservationsToday }} bu gün</small></span><i class="fa fa-arrow-right"></i></a>
                            <a href="{{ route('admin.instagram') }}"><span><i class="fab fa-instagram me-2"></i>Instagram izləmə <small class="acc-badge">{{ \App\Models\InstagramFollow::where('status', 'pending')->count() }} gözləyir</small></span><i class="fa fa-arrow-right"></i></a>
                            <a href="{{ route('admin.support') }}"><span><i class="fa fa-comments me-2"></i>Mesajlar <small class="acc-badge">{{ \App\Models\SupportMessage::unreadForAdmin()->distinct()->count('user_id') }} oxunmamış</small></span><i class="fa fa-arrow-right"></i></a>
                        </div>
                        <div class="acc-stats four mt-3 mb-0">
                            <div><b>{{ $usersTotal }}</b><span>Cəmi qeydiyyat</span></div>
                            <div><b>{{ $usersVerified }}</b><span>Təsdiqlənib</span></div>
                            <div><b>{{ $usersToday }}</b><span>Bu gün qeydiyyat</span></div>
                            <div><b>{{ $usersOnline }}</b><span>İndi saytda</span></div>
                        </div>
                    </div>
                </div>
            @elseif ($me->isStaff())
                {{-- Ofisiant: rezervasiyalara baxa bilər (ləğv etmək — yalnız admin) --}}
                @php($reservationsToday = \App\Models\Reservation::active()->whereDate('date', now('Asia/Baku')->toDateString())->count())
                <div class="col-12">
                    <div class="acc-card acc-admin-card">
                        <div class="acc-head"><span class="acc-ico"><i class="fa fa-concierge-bell"></i></span><h5>Ofisiant</h5></div>
                        <div class="account-admin">
                            <a href="{{ route('admin.reservations') }}"><span><i class="fa fa-chair me-2"></i>Rezervasiyalar <small class="acc-badge">{{ $reservationsToday }} bu gün</small></span><i class="fa fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Sol: ad günü hədiyyəsi (ofisiant və adminə göstərilmir — onda sağ sütun tam en olur) --}}
            @php($bdShow = \App\Support\Birthday::status($me)['state'] !== \App\Support\Birthday::NOT_ELIGIBLE)
            @if ($bdShow)
                <div class="col-lg-4 acc-col">
                    @include('frontend.account._birthday')
                </div>
            @endif

            {{-- Sağ: Instagram və dəvət --}}
            <div class="{{ $bdShow ? 'col-lg-8' : 'col-12' }} acc-col">
                {{-- Instagram səhifəmizi izlə: adını yazır, admin yoxlayıb təsdiqləyəndə bir dəfəlik xal --}}
                @if ($me->earnsGamePoints())
                    @php($ig = \App\Models\InstagramFollow::where('user_id', $me->id)->first())
                    @php($igReward = \App\Models\InstagramFollow::reward())
                    <div class="acc-card mt-4 ig-card" id="instagram">
                        <div class="acc-head"><span class="acc-ico ig-ico"><i class="fab fa-instagram"></i></span><h5>Instagram-da izlə — xal qazan</h5></div>
                        @if ($ig && $ig->status === \App\Models\InstagramFollow::APPROVED)
                            <div class="alert-ok mb-0"><i class="fa fa-check-circle me-2"></i>Təşəkkürlər! <b>{{ '@' . $ig->username }}</b> təsdiqləndi, balansınıza <b>+{{ $fmt($ig->points) }} xal</b> yazıldı.</div>
                        @elseif ($ig && $ig->status === \App\Models\InstagramFollow::PENDING)
                            <div class="ig-pending"><i class="fa fa-hourglass-half me-2"></i>Sorğunuz göndərilib: <b>{{ '@' . $ig->username }}</b>. Admin izləməni yoxlayan kimi <b>+{{ $fmt($igReward) }} xal</b> balansınıza yazılacaq.</div>
                        @else
                            <p class="acc-note">Instagram səhifəmizi izləyin, sonra öz Instagram adınızı yazın. Admin izləməni yoxlayıb təsdiqləyəndə balansınıza <b>+{{ $fmt($igReward) }} xal</b> yazılır (bir dəfə).</p>
                            @if ($ig && $ig->status === \App\Models\InstagramFollow::REJECTED)
                                {{-- rədd xəbərdarlığı 1 dəqiqə göstərilir, sonra silinir və bu brauzerdə bir daha çıxmır --}}
                                <div class="alert-soft mb-3 ig-reject" id="igReject" data-key="igRejectSeen:{{ $ig->id }}:{{ $ig->reviewed_at?->timestamp }}"><i class="fa fa-times-circle me-2"></i><b>{{ '@' . $ig->username }}</b> izləyicilərimiz arasında tapılmadı. Səhifəni izlədiyinizə və adı düzgün yazdığınıza əmin olub yenidən göndərin.</div>
                                <script>
                                    (function () {
                                        var el = document.getElementById('igReject'), key = el.dataset.key;
                                        var seen = function () { try { return localStorage.getItem(key); } catch (e) { return null; } };
                                        if (seen()) { el.remove(); return; }
                                        setTimeout(function () {
                                            try { localStorage.setItem(key, '1'); } catch (e) {}
                                            el.classList.add('is-hiding');
                                            setTimeout(function () { el.remove(); }, 500);
                                        }, 60000);
                                    })();
                                </script>
                            @endif
                            @error('instagram') <div class="alert-soft mb-3">{{ $message }}</div> @enderror
                            <div class="ig-steps">
                                <a href="{{ config('social.instagram.url') }}" target="_blank" rel="noopener" class="btn btn-ig"><i class="fab fa-instagram me-2"></i>1. {{ '@' . config('social.instagram.handle') }} səhifəsini izlə</a>
                                <form method="POST" action="{{ route('account.instagram') }}" class="ig-form">
                                    @csrf
                                    <label for="igUser" class="visually-hidden">Instagram adınız</label>
                                    <div class="ig-input"><span>@</span><input type="text" id="igUser" name="username" value="{{ old('username', $ig?->username) }}" placeholder="instagram_adiniz" maxlength="60" autocapitalize="none" autocomplete="off" spellcheck="false" required></div>
                                    <button type="submit" class="btn btn-primary">2. Göndər</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="acc-card mt-4" id="devet">
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
                </div>

                <div class="acc-card mt-4" id="piramida">
                    <div class="acc-head"><span class="acc-ico"><i class="fa fa-sitemap"></i></span><h5>Dəvət piramidam</h5></div>
                    <p class="acc-note">Sizin link və ya QR kodunuzla qeydiyyatdan keçənlər və onların dəvət etdikləri. Adın yanına basanda onun dəvət etdikləri açılır.</p>
                    @include('frontend.account._ref_tree', ['tree' => $refTree, 'fmt' => $fmt])
                </div>
            </div>

            {{-- Oyunlar --}}
            <div class="col-lg-5">
                <div class="acc-card h-100">
                    <div class="acc-head"><span class="acc-ico"><i class="fa fa-gamepad"></i></span><h5>Oyunlar</h5>
                        <a href="{{ route('games.index') }}" class="acc-more">Oyna <i class="fa fa-arrow-right ms-1"></i></a></div>
                    @if (config('games.nard.enabled'))
                        <h6 class="acc-sub"><i class="fa fa-dice me-2"></i>Nərd (dostla oyun) — bu gün</h6>
                        <div class="acc-stats">
                            <div><b>{{ $nardToday['points'] }}</b><span>xal</span></div>
                            <div><b>{{ $nardToday['wins'] }}</b><span>qələbə</span></div>
                            <div><b>{{ $nardToday['games'] }}</b><span>oyun</span></div>
                            <div><b>{{ $nardWins }}</b><span>dəfə günün qalibi</span></div>
                        </div>
                    @endif
                    <h6 class="acc-sub"><i class="fa fa-layer-group me-2"></i>Stəkan qülləsi</h6>
                    <div class="acc-stats">
                        <div class="wide"><b>{{ $stackBest }}</b><span>stəkan — rekordunuz</span></div>
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
                    @php($usedCount = \App\Models\WheelSpin::prizes(\App\Models\WheelSpin::USED)->where('user_id', $me->id)->count())
                    @if ($usedCount)
                        <div class="prize-history-links">
                            <a href="{{ route('prizes') }}#istifade-olunub"><i class="fa fa-check-circle me-1"></i>İstifadə olunmuş kuponlar <b>{{ $usedCount }}</b></a>
                        </div>
                    @endif
                </div>
            </div>


            {{-- Şəxsi məlumatlar və çıxış — ən aşağıda --}}
            <div class="col-lg-6 mx-auto">
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
        </div>
    </div>
</section>

@endsection

@section('js')
{{-- QR kod brauzerdə yaradılır (link heç bir kənar xidmətə göndərilmir) --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script src="{{ asset('js/referral.js') }}?v=1"></script>
@endsection
