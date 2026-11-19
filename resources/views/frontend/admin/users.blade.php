@extends('frontend.layouts.app')
@section('title', 'Admin · İstifadəçilər - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Admin panel</span>
        <h1>İstifadəçilər</h1>
        <p>Qeydiyyatdan keçmiş bütün istifadəçilər — cəmi {{ $total }}</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="admin-nav">
            <a href="{{ route('admin.prizes') }}"><i class="fa fa-gift me-1"></i>Hədiyyə kodları</a>
            <a href="{{ route('admin.users') }}" class="active"><i class="fa fa-users me-1"></i>İstifadəçilər</a>
        </div>

        @foreach (['success' => 'success', 'error' => 'danger'] as $key => $cls)
            @if (session($key))
                <div class="alert alert-{{ $cls }} admin-alert">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="side-card">
            <form method="GET" action="{{ route('admin.users') }}" class="admin-search">
                <div class="admin-search-input">
                    <i class="fa fa-search"></i>
                    <input type="text" name="q" value="{{ $q }}" placeholder="Ad, telefon və ya e-poçt" autocomplete="off">
                </div>
                @if ($filter)<input type="hidden" name="filter" value="{{ $filter }}">@endif
                <button class="btn btn-primary px-4">Axtar</button>
            </form>
        </div>

        <div class="side-card">
            <div class="admin-tabs">
                <a href="{{ route('admin.users', array_filter(['q' => $q])) }}" class="{{ $filter ? '' : 'active' }}">Hamısı <span>{{ $total }}</span></a>
                @foreach (\App\Http\Controllers\Admin\UserController::FILTERS as $key => $label)
                    <a href="{{ route('admin.users', array_filter(['q' => $q, 'filter' => $key])) }}" class="{{ $filter === $key ? 'active' : '' }}">{{ $label }} <span>{{ $counts[$key] }}</span></a>
                @endforeach
            </div>

            @if ($users->isEmpty())
                <p class="text-muted mb-0">İstifadəçi tapılmadı.</p>
            @else
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead>
                            <tr><th>Ad</th><th>Telefon</th><th>Xallar</th><th>Qeydiyyat</th><th>Son aktivlik</th><th>Hədiyyələr</th><th>Oyunlar</th><th><span class="visually-hidden">Əməliyyat</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $u)
                                @php($online = $u->last_seen_at && $u->last_seen_at->gt(now()->subSeconds(\App\Models\User::ONLINE_SECONDS)))
                                <tr>
                                    <td>
                                        <b>{{ $u->name }}</b>
                                        @if ($u->is_admin)<span class="status-pill status-used ms-1">admin</span>@endif
                                    </td>
                                    <td>
                                        {{ \App\Support\Phone::format($u->phone) ?: ($u->email ?: '—') }}
                                        <br><small class="{{ $u->isVerified() ? 'text-success' : 'text-muted' }}">
                                            @if ($u->phone_verified_at)
                                                <i class="fab fa-telegram-plane me-1"></i>Telegram ilə təsdiqlənib
                                            @elseif ($u->google_id)
                                                <i class="fab fa-google me-1"></i>Google ilə təsdiqlənib
                                            @elseif ($u->email_verified_at)
                                                <i class="fa fa-envelope me-1"></i>E-poçt ilə təsdiqlənib
                                            @else
                                                təsdiqlənməyib
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        {{-- dəvət xalları (balans) — Hədiyyə marketində hədiyyəyə çevrilir --}}
                                        @if ($u->points > 0)
                                            <span class="user-points"><i class="fa fa-star"></i>{{ \App\Services\Referrals::format((float) $u->points) }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>{{ $u->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</td>
                                    <td>
                                        @if ($online)
                                            <span class="online-dot"></span> indi saytda
                                        @elseif ($u->last_seen_at)
                                            {{ $u->last_seen_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($u->prizes_count)
                                            <a href="{{ route('admin.prizes', ['q' => preg_replace('/\D+/', '', (string) $u->phone)]) }}">{{ $u->prizes_count }} kod</a>
                                            @if ($u->active_prizes_count)<br><small class="text-success">{{ $u->active_prizes_count }} aktiv</small>@endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>
                                            Stəkan rekordu: <b>{{ (int) $u->stack_best }}</b>
                                            @if (config('games.nard.enabled'))
                                                <br>Nərd bu gün: <b>{{ (int) $u->nard_today }} xal</b>
                                            @endif
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        @unless ($u->is_admin || $u->id === auth()->id())
                                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                                  onsubmit="return confirm({{ \Illuminate\Support\Js::from($u->name . ' (' . (\App\Support\Phone::format($u->phone) ?: ($u->email ?: $u->username)) . ') birdəfəlik silinsin? Onun hədiyyə kodları və oyun nəticələri də silinəcək. Bunu geri qaytarmaq olmur.') }})">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-user-delete" title="İstifadəçini sil" aria-label="{{ $u->name }} — sil"><i class="fa fa-trash-alt"></i></button>
                                            </form>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $users->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
