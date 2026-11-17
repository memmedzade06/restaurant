@extends('frontend.layouts.app')
@section('title', 'Admin · Hədiyyə kodları - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Admin panel</span>
        <h1>Hədiyyə kodları</h1>
        <p>Oyunçunun kodunu yoxlayın və istifadə olundu kimi qeyd edin</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="admin-nav">
            <a href="{{ route('admin.prizes') }}" class="active"><i class="fa fa-gift me-1"></i>Hədiyyə kodları</a>
            <a href="{{ route('admin.users') }}"><i class="fa fa-users me-1"></i>İstifadəçilər</a>
        </div>

        @foreach (['success' => 'success', 'error' => 'danger'] as $key => $cls)
            @if (session($key))
                <div class="alert alert-{{ $cls }} admin-alert">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="side-card">
            <form method="GET" action="{{ route('admin.prizes') }}" class="admin-search">
                <div class="admin-search-input">
                    <i class="fa fa-search"></i>
                    <input type="text" name="q" value="{{ $q }}" placeholder="Kod (QP-AB12CD) və ya telefon" autofocus autocomplete="off">
                </div>
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <button class="btn btn-primary px-4">Axtar</button>
            </form>

            @if ($q !== '')
                @if ($exact)
                    @php($st = $exact->status())
                    <div class="code-check code-{{ $st }}">
                        <div>
                            <code>{{ $exact->code }}</code>
                            <h4>{{ $exact->prize_label }} <small class="text-muted fs-6">· {{ $exact->gameLabel() }}</small></h4>
                            <span class="muted">
                                {{ $exact->user?->name }} · {{ \App\Support\Phone::format($exact->user?->phone) }}
                                · {{ $exact->expires_at?->timezone('Asia/Baku')->format('d.m.Y H:i') }} tarixinədək
                            </span>
                        </div>
                        @if ($st === 'active')
                            <form method="POST" action="{{ route('admin.prizes.redeem', $exact) }}" onsubmit="return confirm('{{ $exact->code }} kodu istifadə olundu kimi qeyd edilsin? Bunu geri qaytarmaq olmur.')">
                                @csrf
                                <button class="btn btn-wine px-4 py-2"><i class="fa fa-check me-2"></i>İstifadə olundu</button>
                            </form>
                        @else
                            <span class="status-pill status-{{ $st }}">{{ $exact->statusLabel() }}{{ $st === 'used' ? ' · ' . $exact->redeemed_at->timezone('Asia/Baku')->format('d.m.Y H:i') : '' }}</span>
                        @endif
                    </div>
                @elseif ($prizes->isEmpty())
                    <div class="alert alert-warning mt-3 mb-0">«{{ $q }}» üzrə kod tapılmadı.</div>
                @endif
            @endif
        </div>

        <div class="side-card">
            <div class="admin-tabs">
                <a href="{{ route('admin.prizes', array_filter(['q' => $q])) }}" class="{{ $status ? '' : 'active' }}">Hamısı</a>
                @foreach (\App\Models\WheelSpin::STATUS_LABELS as $key => $label)
                    {{-- istifadə olunmuş və vaxtı keçmiş kuponlar bazadan silinir — boş sekmələri göstərmirik --}}
                    @continue($key !== \App\Models\WheelSpin::ACTIVE && ! $counts[$key] && $status !== $key)
                    <a href="{{ route('admin.prizes', array_filter(['q' => $q, 'status' => $key])) }}" class="{{ $status === $key ? 'active' : '' }}">{{ $label }} <span>{{ $counts[$key] }}</span></a>
                @endforeach
            </div>

            @if ($prizes->isEmpty())
                <p class="text-muted mb-0">Kod yoxdur.</p>
            @else
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead>
                            <tr><th>Kod</th><th>Hədiyyə</th><th>Oyunçu</th><th>Götürülüb</th><th>Etibarlıdır</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($prizes as $p)
                                @php($st = $p->status())
                                <tr class="row-{{ $st }}">
                                    <td><code>{{ $p->code }}</code></td>
                                    <td>{{ $p->prize_label }}<br><small class="text-muted">{{ $p->gameLabel() }}</small></td>
                                    <td>{{ $p->user?->name }}<br><small class="text-muted">{{ \App\Support\Phone::format($p->user?->phone) }}</small></td>
                                    <td>{{ $p->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</td>
                                    <td>{{ $p->expires_at?->timezone('Asia/Baku')->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <span class="status-pill status-{{ $st }}">{{ $p->statusLabel() }}</span>
                                        @if ($st === 'used')
                                            <br><small class="text-muted">{{ $p->redeemed_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}{{ $p->redeemer ? ' · ' . $p->redeemer->name : '' }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($st === 'active')
                                            <form method="POST" action="{{ route('admin.prizes.redeem', $p) }}" onsubmit="return confirm('{{ $p->code }} kodu istifadə olundu kimi qeyd edilsin? Bunu geri qaytarmaq olmur.')">
                                                @csrf
                                                <button class="btn btn-sm btn-wine">İstifadə olundu</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $prizes->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
