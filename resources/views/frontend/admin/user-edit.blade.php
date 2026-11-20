@extends('frontend.layouts.app')
@section('title', 'Admin · ' . $user->name . ' - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Admin panel</span>
        <h1>{{ $user->name }}</h1>
        <p>İstifadəçi tipini dəyişin</p>
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
    </div>
</section>
@endsection
