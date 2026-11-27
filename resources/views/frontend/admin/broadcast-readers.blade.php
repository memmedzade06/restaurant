@extends('frontend.layouts.app')
@section('title', 'Admin · Kim oxuyub - Hamıya mesaj - 50 Qəpik')

@php
    $who = fn ($u) => $u ? ($u->phone ? \App\Support\Phone::format($u->phone) : $u->email) : '';
    $total = $read->count() + $unread->count();
@endphp

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Admin panel · Hamıya mesaj</span>
        <h1>Kim oxuyub</h1>
        <p>{{ $broadcast->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }} tarixli mesaj — sayt söhbəti</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        @include('frontend.admin._nav', ['active' => 'support'])
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <a href="{{ route('admin.support.broadcast') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="fa fa-arrow-left me-1"></i>Hamıya mesaj</a>

                <div class="side-card mb-4">
                    <div class="broadcast-body mb-3">{{ \Illuminate\Support\Str::limit($broadcast->body, 300) ?: '(mətn yoxdur)' }}</div>
                    <div class="acc-stats mb-0" style="grid-template-columns: repeat(3, 1fr);">
                        <div><b>{{ $read->count() }}</b><span>Oxuyub</span></div>
                        <div><b>{{ $unread->count() }}</b><span>Oxumayıb</span></div>
                        <div><b>{{ $total ? (int) round($read->count() * 100 / $total) : 0 }}%</b><span>Oxunma</span></div>
                    </div>
                    <p class="small text-muted mt-3 mb-0"><i class="fa fa-info-circle me-1"></i>Mesaj istifadəçi saytda söhbəti (profildə "Mesajlar" və ya küncdəki pəncərə) açanda "oxunub" sayılır. Telegram və e-poçtda oxunmanı bilmək mümkün deyil.</p>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="side-card h-100">
                            <h5 class="mb-3"><i class="fa fa-check-double me-2 text-success"></i>Oxuyub ({{ $read->count() }})</h5>
                            @forelse ($read as $m)
                                <div class="readers-row">
                                    <a href="{{ route('admin.support.show', $m->user_id) }}">{{ $m->user?->name ?? 'silinmiş istifadəçi' }}</a>
                                    <small class="text-muted d-block">{{ $who($m->user) }} · {{ $m->read_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</small>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Hələ heç kim oxumayıb.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="side-card h-100">
                            <h5 class="mb-3"><i class="fa fa-check me-2 text-muted"></i>Oxumayıb ({{ $unread->count() }})</h5>
                            @forelse ($unread as $m)
                                <div class="readers-row">
                                    <a href="{{ route('admin.support.show', $m->user_id) }}">{{ $m->user?->name ?? 'silinmiş istifadəçi' }}</a>
                                    <small class="text-muted d-block">{{ $who($m->user) }}</small>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Hamı oxuyub 🎉</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
