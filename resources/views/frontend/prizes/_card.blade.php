{{-- Bir hədiyyə kartı (oyunçu səhifəsi) --}}
@php($st = $p->status())
<li class="prize-item prize-{{ $st }}">
    <div class="prize-info">
        <b>{{ $p->prize_label }} <small class="muted fw-normal">· {{ $p->gameLabel() }}</small></b>
        <span class="muted">
            <i class="fa fa-clock"></i> {{ $p->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}
            @if ($st === 'active')
                · {{ $p->expires_at?->timezone('Asia/Baku')->format('d.m.Y') }} tarixinədək
            @elseif ($st === 'used')
                · {{ $p->redeemed_at->timezone('Asia/Baku')->format('d.m.Y H:i') }} istifadə olunub
            @else
                · {{ $p->expires_at?->timezone('Asia/Baku')->format('d.m.Y') }} vaxtı bitib
            @endif
        </span>
    </div>
    <div class="prize-side">
        <code>{{ $p->code }}</code>
        <span class="prize-badge">{{ $st === 'used' ? 'Qüvvədən düşüb' : $p->statusLabel() }}</span>
    </div>
</li>
