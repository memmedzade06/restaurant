@extends('frontend.layouts.app')
@section('title', 'Admin · Instagram - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Admin panel</span>
        <h1>Instagram izləmə</h1>
        <p>İstifadəçilər Instagram adını göndərir — izləyicilərdə yoxlayın və təsdiqləyin (<b>+{{ \App\Services\Referrals::format($reward) }} xal</b>, bir dəfə)</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        @include('frontend.admin._nav', ['active' => 'instagram'])

        @foreach (['success' => 'success', 'error' => 'danger'] as $key => $cls)
            @if (session($key))
                <div class="alert alert-{{ $cls }} admin-alert">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="side-card">
            <div class="d-flex flex-wrap align-items-center gap-2">
                @foreach (['pending' => 'Gözləyir', 'approved' => 'Təsdiqlənib', 'rejected' => 'Rədd edilib'] as $key => $label)
                    <a href="{{ route('admin.instagram', $key === 'pending' ? [] : ['status' => $key]) }}"
                       class="btn btn-sm {{ $status === $key ? 'btn-wine' : 'btn-outline-secondary' }}">{{ $label }} <span class="ms-1">({{ $counts[$key] ?? 0 }})</span></a>
                @endforeach
                <a href="{{ config('social.instagram.url') }}followers/" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary ms-auto">
                    <i class="fab fa-instagram me-1"></i>{{ '@' . config('social.instagram.handle') }} izləyiciləri</a>
            </div>
            @if ($status === 'pending')
                <p class="small text-muted mt-3 mb-0"><i class="fa fa-info-circle me-1"></i>Yoxlamaq üçün: profilə keçin və ya restoranın izləyicilər siyahısında adı axtarın. İzləyirsə — <b>Təsdiqlə</b> (xal dərhal yazılır), izləmirsə — <b>Rədd et</b> (istifadəçi adı düzəldib yenidən göndərə bilər).</p>
            @endif
        </div>

        <div class="side-card">
            @if ($follows->isEmpty())
                <p class="text-muted mb-0">{{ $status === 'pending' ? 'Gözləyən sorğu yoxdur.' : 'Siyahı boşdur.' }}</p>
            @else
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead>
                            <tr><th>Instagram</th><th>İstifadəçi</th><th>Göndərilib</th>
                                @if ($status !== 'pending')<th>Baxıb</th>@endif
                                <th><span class="visually-hidden">Əməliyyat</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($follows as $f)
                                <tr>
                                    <td><a href="{{ $f->profileUrl() }}" target="_blank" rel="noopener" class="fw-bold"><i class="fab fa-instagram me-1"></i>{{ '@' . $f->username }}</a></td>
                                    <td>
                                        @if ($f->user)
                                            <a href="{{ route('admin.users.edit', $f->user) }}">{{ $f->user->name }}</a>
                                            <small class="d-block text-muted">{{ $f->user->phone ? \App\Support\Phone::format($f->user->phone) : $f->user->email }}</small>
                                        @else
                                            <span class="text-muted">silinmiş istifadəçi</span>
                                        @endif
                                    </td>
                                    <td>{{ $f->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</td>
                                    @if ($status !== 'pending')
                                        <td>{{ $f->reviewer?->name ?? '—' }}
                                            <small class="d-block text-muted">{{ $f->reviewed_at?->timezone('Asia/Baku')->format('d.m.Y H:i') }}@if ($f->status === 'approved' && $f->points) · +{{ \App\Services\Referrals::format($f->points) }} xal @endif</small></td>
                                    @endif
                                    <td class="text-end text-nowrap">
                                        @if ($f->status === 'pending')
                                            <form method="POST" action="{{ route('admin.instagram.approve', $f) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-check me-1"></i>Təsdiqlə</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.instagram.reject', $f) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-times me-1"></i>Rədd et</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $follows->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
