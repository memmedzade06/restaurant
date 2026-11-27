@extends('frontend.layouts.app')
@section('title', 'Admin · ' . $user->name . ' - Mesajlar - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Admin panel · Mesajlar</span>
        <h1>{{ $user->name }}</h1>
        <p>{{ $user->phone ? \App\Support\Phone::format($user->phone) : $user->email }}</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        @include('frontend.admin._nav', ['active' => 'support'])

        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="{{ route('admin.support') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Bütün söhbətlər</a>
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="fa fa-user me-1"></i>İstifadəçi profili</a>
                </div>
                <div class="side-card">
                    @include('frontend.support._chat', [
                        'postUrl' => route('admin.support.reply', $user),
                        'pollUrl' => route('admin.support.poll', $user),
                        'empty' => 'Bu istifadəçi ilə yazışma yoxdur.',
                        'placeholder' => 'Cavabınızı yazın…',
                        'attach' => true,
                    ])
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

