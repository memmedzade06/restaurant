{{-- Admin panelin menyusu. $active: prizes | users | reservations | instagram --}}
@php($igPending = \App\Models\InstagramFollow::where('status', \App\Models\InstagramFollow::PENDING)->count())
<div class="admin-nav">
    <a href="{{ route('admin.prizes') }}" @class(['active' => $active === 'prizes'])><i class="fa fa-gift me-1"></i>Hədiyyə kodları</a>
    <a href="{{ route('admin.users') }}" @class(['active' => $active === 'users'])><i class="fa fa-users me-1"></i>İstifadəçilər</a>
    <a href="{{ route('admin.reservations') }}" @class(['active' => $active === 'reservations'])><i class="fa fa-chair me-1"></i>Rezervasiyalar</a>
    <a href="{{ route('admin.instagram') }}" @class(['active' => $active === 'instagram'])><i class="fab fa-instagram me-1"></i>Instagram @if ($igPending)<span class="badge bg-danger ms-1">{{ $igPending }}</span>@endif</a>
</div>
