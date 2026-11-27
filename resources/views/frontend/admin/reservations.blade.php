@extends('frontend.layouts.app')
@section('title', 'Admin · Rezervasiyalar - 50 Qəpik')

@section('css')
<link href="{{ asset('css/reservations.css') }}?v=1" rel="stylesheet">
@endsection

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">{{ auth()->user()->is_admin ? 'Admin panel' : 'Ofisiant' }}</span>
        <h1>Rezervasiyalar</h1>
        <p>{{ $date ? \Carbon\Carbon::parse($date)->format('d.m.Y') . ' tarixinə olan' : 'Bu gündən sonrakı bütün' }} masa rezervasiyaları</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        {{-- ofisiant yalnız rezervasiyaları görür: admin menyusu və ləğv düyməsi ona göstərilmir --}}
        @if (auth()->user()->is_admin)
            @include('frontend.admin._nav', ['active' => 'reservations'])
        @else
            <div class="admin-nav">
                <a href="{{ route('admin.prizes') }}"><i class="fa fa-gift me-1"></i>Hədiyyə kodları</a>
                <a href="{{ route('admin.reservations') }}" class="active"><i class="fa fa-chair me-1"></i>Rezervasiyalar</a>
            </div>
        @endif

        @foreach (['success' => 'success', 'error' => 'danger'] as $key => $cls)
            @if (session($key))
                <div class="alert alert-{{ $cls }} admin-alert">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="side-card">
            <form method="GET" action="{{ route('admin.reservations') }}" class="d-flex flex-wrap align-items-center gap-2">
                <label class="rv-day">
                    <i class="fa fa-calendar-alt"></i><span>Tarix:</span>
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
                </label>
                <a href="{{ route('admin.reservations', ['date' => $today]) }}" class="btn btn-sm {{ $date === $today ? 'btn-wine' : 'btn-outline-secondary' }}">Bu gün</a>
                <a href="{{ route('admin.reservations') }}" class="btn btn-sm {{ $date === '' ? 'btn-wine' : 'btn-outline-secondary' }}">Hamısı (gələcək)</a>
            </form>
        </div>

        <div class="side-card">
            @if ($reservations->isEmpty())
                <p class="text-muted mb-0">Rezervasiya yoxdur.</p>
            @else
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead>
                            <tr><th>Tarix</th><th>Saat</th><th>Masa</th><th>Ad, soyad</th><th>Telefon</th><th>Yazılıb</th><th><span class="visually-hidden">Əməliyyat</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($reservations as $r)
                                <tr class="rv-status-{{ $r->status }}">
                                    <td>{{ $r->date->format('d.m.Y') }}</td>
                                    <td><b>{{ $r->time }}</b></td>
                                    <td>{{ $r->table_name }}</td>
                                    <td>
                                        <b>{{ $r->name }}</b>
                                        @if ($r->user)<br><small class="text-muted"><i class="fa fa-user me-1"></i>hesab: {{ $r->user->name }}</small>@endif
                                    </td>
                                    <td><a href="tel:{{ $r->phone }}">{{ \App\Support\Phone::format($r->phone) }}</a></td>
                                    <td><small>{{ $r->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</small></td>
                                    <td class="text-end text-nowrap">
                                        @if ($r->status === \App\Models\Reservation::STATUS_ACTIVE && auth()->user()->is_admin)
                                            <form method="POST" action="{{ route('admin.reservations.cancel', $r) }}"
                                                  onsubmit="return confirm({{ \Illuminate\Support\Js::from($r->name . ' — ' . $r->table_name . ', ' . $r->date->format('d.m.Y') . ' ' . $r->time . ' rezervasiyası ləğv olunsun?') }})">
                                                @csrf
                                                <button type="submit" class="btn-user-delete" title="Ləğv et" aria-label="{{ $r->name }} — rezervasiyanı ləğv et"><i class="fa fa-times"></i></button>
                                            </form>
                                        @elseif ($r->status !== \App\Models\Reservation::STATUS_ACTIVE)
                                            <small class="text-muted">ləğv olunub</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $reservations->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
