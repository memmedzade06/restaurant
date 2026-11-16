@extends('frontend.layouts.app')
@section('title', 'Hesabım - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Hesabım</span>
        <h1>Salam, {{ auth()->user()->name }}!</h1>
        <p>Qeydiyyatınız təsdiqlənib</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="auth-card">
            <ul class="account-list">
                <li><span>Ad</span><b>{{ auth()->user()->name }}</b></li>
                <li><span>Telefon</span><b>{{ \App\Support\Phone::format(auth()->user()->phone) }} <i class="fa fa-check-circle text-success ms-1" title="Telegram ilə təsdiqlənib"></i></b></li>
                <li><span>E-poçt</span><b>{{ auth()->user()->email }}</b></li>
                <li><span>Qeydiyyat tarixi</span><b>{{ auth()->user()->created_at->format('d.m.Y') }}</b></li>
            </ul>

            @php
                // nərd: yalnız dostla oyunlar; nəticələr hər gecə silinir, qalib olduğu günlər qalır
                $nardToday = \App\Services\NardLeaderboard::myToday(auth()->id());
                $nardWins = \App\Models\NardDailyWinner::where('user_id', auth()->id())->count();
                $prizes = \App\Models\WheelSpin::where('user_id', auth()->id())->whereNotNull('code')->latest('id')->take(5)->get();
            @endphp

            <h5 class="account-section-title"><i class="fa fa-dice me-2 text-primary"></i>Nərd (dostla oyun)</h5>
            <ul class="account-list">
                <li><span>Bu gün</span><b>{{ $nardToday['points'] }} xal · {{ $nardToday['wins'] }} qələbə · {{ $nardToday['games'] }} oyun</b></li>
                <li><span>Günün qalibi olub</span><b>{{ $nardWins }} dəfə</b></li>
            </ul>

            @php
                $stackBest = (int) \App\Models\GameResult::where('user_id', auth()->id())->where('game', 'stack')->max('points');
            @endphp
            <h5 class="account-section-title"><i class="fa fa-layer-group me-2 text-primary"></i>Stəkan qülləsi</h5>
            <ul class="account-list">
                <li><span>Rekord</span><b>{{ $stackBest }} xal</b></li>
            </ul>

            @if (auth()->user()->is_admin)
                {{-- Yalnız admin: qeydiyyatdan keçmiş istifadəçilər --}}
                @php
                    $usersTotal = \App\Models\User::count();
                    $usersVerified = \App\Models\User::whereNotNull('phone_verified_at')->count();
                    $usersToday = \App\Models\User::where('created_at', '>=', now('Asia/Baku')->startOfDay()->setTimezone(config('app.timezone')))->count();
                    $usersOnline = \App\Models\User::where('last_seen_at', '>=', now()->subSeconds(\App\Models\User::ONLINE_SECONDS))->count();
                    $latestUsers = \App\Models\User::latest('id')->take(10)->get(['id', 'name', 'phone', 'phone_verified_at', 'created_at', 'last_seen_at']);
                @endphp
                <h5 class="account-section-title d-flex align-items-center"><i class="fa fa-users me-2 text-primary"></i>İstifadəçilər
                    <a href="{{ route('admin.users') }}" class="ms-auto small fw-bold">Hamısına bax <i class="fa fa-arrow-right ms-1"></i></a></h5>
                <ul class="account-list">
                    <li><span>Cəmi qeydiyyat</span><b>{{ $usersTotal }}</b></li>
                    <li><span>Telegram ilə təsdiqlənib</span><b>{{ $usersVerified }}</b></li>
                    <li><span>Bu gün qeydiyyat</span><b>{{ $usersToday }}</b></li>
                    <li><span>İndi saytda</span><b>{{ $usersOnline }}</b></li>
                </ul>
                <p class="small text-muted mb-2">Son qeydiyyatdan keçənlər:</p>
                <ul class="account-list account-users mb-4">
                    @foreach ($latestUsers as $u)
                        <li>
                            <span>
                                {{ $u->name }}
                                <small class="d-block">{{ \App\Support\Phone::format($u->phone) ?: '—' }} · {{ $u->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</small>
                            </span>
                            <b class="{{ $u->phone_verified_at ? 'text-success' : 'text-muted' }}" title="{{ $u->phone_verified_at ? 'Telegram ilə təsdiqlənib' : 'Təsdiqlənməyib' }}">
                                <i class="fa {{ $u->phone_verified_at ? 'fa-check-circle' : 'fa-clock' }}"></i>
                            </b>
                        </li>
                    @endforeach
                </ul>
                <div class="account-admin">
                    <a href="{{ route('admin.users') }}"><span><i class="fa fa-users me-2"></i>Bütün istifadəçilər</span><i class="fa fa-arrow-right"></i></a>
                    <a href="{{ route('admin.prizes') }}"><span><i class="fa fa-gift me-2"></i>Hədiyyə kodları</span><i class="fa fa-arrow-right"></i></a>
                </div>
            @endif

            <h5 class="account-section-title d-flex align-items-center"><i class="fa fa-gift me-2 text-primary"></i>Hədiyyələrim
                <a href="{{ route('prizes') }}" class="ms-auto small fw-bold">Hamısına bax <i class="fa fa-arrow-right ms-1"></i></a></h5>
            @if ($prizes->isEmpty())
                <p class="text-muted mb-4">Hələ hədiyyə yoxdur.</p>
            @else
                <ul class="prize-cards mb-4">
                    @foreach ($prizes as $p)
                        @include('frontend.prizes._card', ['p' => $p])
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-wine w-100 py-3"><i class="fa fa-sign-out-alt me-2"></i>Çıxış</button>
            </form>
        </div>
    </div>
</section>

@if (session('registered'))
    {{-- Qeydiyyat təsdiqlənəndən sonra bir dəfə görünür, 5 saniyəyə özü bağlanır --}}
    <div class="modal fade" id="registeredModal" tabindex="-1" aria-labelledby="registeredTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content success-modal">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button>
                <div class="success-ico"><i class="fa fa-check"></i></div>
                <h3 id="registeredTitle">Qeydiyyatınız təsdiqləndi!</h3>
                <p>Telefon nömrəniz Telegram vasitəsilə uğurla təsdiqləndi. 50 Qəpik ailəsinə xoş gəlmisiniz!</p>
                <button type="button" class="btn btn-primary px-5 py-2" data-bs-dismiss="modal">Oldu</button>
                <span class="success-bar"></span>
            </div>
        </div>
    </div>
@endif
@endsection

@section('js')
@if (session('registered'))
<script>
    (function () {
        const el = document.getElementById('registeredModal');
        const modal = new bootstrap.Modal(el);
        modal.show();
        const timer = setTimeout(() => modal.hide(), 5000);
        el.addEventListener('hidden.bs.modal', () => clearTimeout(timer));
    })();
</script>
@endif
@endsection
