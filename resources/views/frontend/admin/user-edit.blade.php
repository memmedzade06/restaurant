@extends('frontend.layouts.app')
@section('title', 'Admin · ' . $user->name . ' - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Admin panel</span>
        <h1>{{ $user->name }}</h1>
        <p>İstifadəçi tipini və xal balansını dəyişin</p>
    </div>
</section>

@php
    $self = $user->id === auth()->id();
    $hints = [
        'guest' => 'Qeydiyyatdan keçən hər kəs — oyun oynayır, hədiyyə qazanır.',
        'waiter' => 'Restoranın ofisiantı.',
        'admin' => 'Admin panelə girişi var: istifadəçilər və hədiyyə kodları.',
    ];
@endphp

<section class="section pt-4">
    <div class="container" style="max-width: 640px">
        <a href="{{ route('admin.users') }}" class="back-link"><i class="fa fa-arrow-left"></i> İstifadəçilərə qayıt</a>

        @if (session('error'))
            <div class="alert alert-danger admin-alert">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert alert-success admin-alert">{{ session('success') }}</div>
        @endif

        <div class="side-card">
            <dl class="user-edit-info">
                <dt>Telefon / e-poçt</dt>
                <dd>{{ \App\Support\Phone::format($user->phone) ?: ($user->email ?: '—') }}</dd>
                <dt>Qeydiyyat</dt>
                <dd>{{ $user->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</dd>
                <dt>Hazırkı tip</dt>
                <dd><span class="user-type user-type-{{ $user->type }}">{{ $user->type_label }}</span></dd>
            </dl>

            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')
                <fieldset class="user-type-options" @disabled($self)>
                    <legend class="h5 mb-3">İstifadəçi tipi</legend>
                    @foreach (\App\Models\User::TYPES as $key => $label)
                        <label class="user-type-option">
                            <input type="radio" name="type" value="{{ $key }}" @checked(old('type', $user->type) === $key)>
                            <span>
                                <b>{{ $label }}</b>
                                <small>{{ $hints[$key] ?? '' }}</small>
                            </span>
                        </label>
                    @endforeach
                </fieldset>
                @error('type')<p class="text-danger small mt-2 mb-0">{{ $message }}</p>@enderror

                @if ($self)
                    <p class="small text-muted mt-3 mb-0">Öz tipinizi dəyişə bilməzsiniz.</p>
                @else
                    <button type="submit" class="btn btn-primary mt-3 px-4">Yadda saxla</button>
                @endif
            </form>
        </div>

        {{-- ad günü: istifadəçi tarixi bir dəfə yazır, səhv yazılıbsa admin düzəldir --}}
        <div class="side-card mt-4" id="ad-gunu">
            <h5 class="mb-1"><i class="fa fa-birthday-cake me-2 text-primary"></i>Ad günü</h5>
            <p class="small text-muted mb-3">
                @if ($user->birth_date)
                    Doğum tarixi: <b>{{ $user->birth_date->format('d.m.Y') }}</b> (yazılıb: {{ $user->birth_date_set_at?->timezone('Asia/Baku')->format('d.m.Y') ?? '—' }})
                    @if ($user->birthday_coupon_year) · {{ $user->birthday_coupon_year }}-ci ilin ad günü kuponu götürülüb @endif
                @else
                    Doğum tarixi yazılmayıb.
                @endif
            </p>
            <form method="POST" action="{{ route('admin.users.birthday', $user) }}" class="d-flex flex-wrap gap-2 align-items-center">
                @csrf
                @method('PUT')
                <input type="date" name="birth_date" class="form-control" style="max-width: 200px" value="{{ old('birth_date', $user->birth_date?->toDateString()) }}" max="{{ now('Asia/Baku')->subDay()->toDateString() }}">
                <button type="submit" class="btn btn-primary px-4">Yadda saxla</button>
            </form>
            @error('birth_date') <div class="alert alert-danger admin-alert mt-2 mb-0">{{ $message }}</div> @enderror
            <p class="small text-muted mt-2 mb-0">Boş saxlasanız, tarix silinir. Düzəliş kuponun "30 gün əvvəl yazılıb" qaydasını sıfırlamır.</p>
        </div>

        {{-- xal balansı: yeni balans yaz, əlavə et və ya çıx --}}
        @php $mode = old('mode', 'add'); @endphp
        <div class="side-card mt-4" id="xal">
            <h5 class="mb-1"><i class="fa fa-star me-2 text-primary"></i>Xal balansı</h5>
            <p class="points-now">Hazırda: <b>{{ \App\Services\Referrals::format((float) $user->points) }} xal</b></p>

            @if (session('points_error'))
                <div class="alert alert-danger admin-alert">{{ session('points_error') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.users.points', $user) }}" class="points-form">
                @csrf
                @method('PUT')
                <div class="points-modes" role="radiogroup" aria-label="Necə dəyişək">
                    <label><input type="radio" name="mode" value="add" @checked($mode === 'add')><span><i class="fa fa-plus"></i> Əlavə et</span></label>
                    <label><input type="radio" name="mode" value="sub" @checked($mode === 'sub')><span><i class="fa fa-minus"></i> Çıx</span></label>
                    <label><input type="radio" name="mode" value="set" @checked($mode === 'set')><span><i class="fa fa-equals"></i> Yeni balans</span></label>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-sm-4">
                        <label class="form-label small fw-bold" for="pointsAmount">Xal</label>
                        <input type="text" inputmode="decimal" id="pointsAmount" name="amount" class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}" placeholder="məs. 2.5" required autocomplete="off">
                    </div>
                    <div class="col-sm-8">
                        <label class="form-label small fw-bold" for="pointsReason">Səbəb <span class="text-muted fw-normal">(istəyə görə)</span></label>
                        <input type="text" id="pointsReason" name="reason" class="form-control" maxlength="200"
                               value="{{ old('reason') }}" placeholder="məs. Turnir mükafatı">
                    </div>
                </div>
                @error('amount')<p class="text-danger small mt-2 mb-0">{{ $message }}</p>@enderror
                @error('mode')<p class="text-danger small mt-2 mb-0">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-primary mt-3 px-4">Xalı yadda saxla</button>
                <p class="small text-muted mt-2 mb-0">Balans mənfiyə düşə bilməz. Dəyişiklik loga yazılır.</p>
            </form>
        </div>

        {{-- xal tarixçəsi: balans necə yığılıb --}}
        <div class="side-card mt-4" id="tarixce">
            <h5 class="mb-3"><i class="fa fa-history me-2 text-primary"></i>Xal tarixçəsi</h5>
            @include('frontend.account._points_history', ['history' => $history, 'admin' => true])
        </div>

        {{-- dəvət piramidası: bu istifadəçinin link/QR ilə gətirdiyi təsdiqlənmiş profillər --}}
        <div class="side-card mt-4" id="piramida">
            <h5 class="mb-1"><i class="fa fa-sitemap me-2 text-primary"></i>Dəvət piramidası</h5>
            <p class="small text-muted mb-3">{{ $user->name }} adlı istifadəçinin linki və ya QR kodu ilə qeydiyyatdan keçib profilini <b>təsdiqləyənlər</b>, onların dəvət etdikləri və s. Ada basanda həmin istifadəçinin səhifəsi açılır.</p>
            @include('frontend.account._ref_tree', ['tree' => $tree, 'fmt' => fn ($p) => \App\Services\Referrals::format((float) $p), 'admin' => true, 'owner' => $user])
        </div>
    </div>
</section>
@endsection
