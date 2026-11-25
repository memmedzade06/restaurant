@extends('frontend.layouts.app')
@section('title', 'Admin · Mesajlar - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Admin panel</span>
        <h1>Mesajlar</h1>
        <p>İstifadəçilərin sualları — söhbəti açıb cavab yazın</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        @include('frontend.admin._nav', ['active' => 'support'])

        <div class="side-card">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('admin.support') }}" class="btn btn-sm {{ $filter === 'all' ? 'btn-wine' : 'btn-outline-secondary' }}">Hamısı</a>
                <a href="{{ route('admin.support.broadcast') }}" class="btn btn-sm btn-wine ms-auto order-last"><i class="fa fa-bullhorn me-1"></i>Hamıya mesaj</a>
                <a href="{{ route('admin.users') }}" class="btn btn-sm btn-primary order-last" title="İstifadəçilər siyahısında ✉ düyməsini basın"><i class="fa fa-plus me-1"></i>Yeni mesaj</a>
                <a href="{{ route('admin.support', ['filter' => 'unread']) }}" class="btn btn-sm {{ $filter === 'unread' ? 'btn-wine' : 'btn-outline-secondary' }}">
                    Oxunmamış <span class="ms-1">({{ $unreadThreads }})</span></a>
            </div>
        </div>

        <div class="side-card">
            @if ($threads->isEmpty())
                <p class="text-muted mb-0">{{ $filter === 'unread' ? 'Oxunmamış mesaj yoxdur.' : 'Hələ yazışma yoxdur.' }}</p>
                <p class="small text-muted mt-2 mb-0"><i class="fa fa-info-circle me-1"></i>İstifadəçiyə özünüz yazmaq üçün: <a href="{{ route('admin.users') }}">İstifadəçilər</a> siyahısında <i class="fa fa-comment-dots"></i> düyməsini basın.</p>
            @else
                <div class="support-threads">
                    @foreach ($threads as $t)
                        @php($u = $users[$t->user_id] ?? null)
                        @php($m = $last[$t->last_id] ?? null)
                        <a href="{{ route('admin.support.show', $t->user_id) }}" @class(['support-thread', 'unread' => $t->unread > 0])>
                            <span class="support-thread-ico"><i class="fa fa-user"></i></span>
                            <span class="support-thread-main">
                                <span class="support-thread-top">
                                    <b>{{ $u?->name ?? 'Silinmiş istifadəçi' }}</b>
                                    <small>{{ $m?->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</small>
                                </span>
                                <span class="support-thread-text">
                                    @if ($m?->from_admin)<i class="fa fa-reply me-1"></i>@endif{{ \Illuminate\Support\Str::limit($m?->body, 110) }}
                                </span>
                                @if ($u)
                                    <small class="text-muted">{{ $u->phone ? \App\Support\Phone::format($u->phone) : $u->email }}</small>
                                @endif
                            </span>
                            @if ($t->unread > 0)
                                <span class="badge bg-danger">{{ $t->unread }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="mt-3">{{ $threads->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
