{{-- Bir hədiyyə kartı (oyunçu səhifəsi) --}}
@php($st = $p->status())
<li class="prize-item prize-{{ $st }}">
    <div class="prize-info">
        <b>{{ $p->prize_label }} <small class="muted fw-normal">· {{ $p->gameLabel() }}</small></b>
        <span class="muted">
            <i class="fa fa-clock"></i> {{ $p->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}
            @if ($st === 'active')
                götürülüb
            @elseif ($st === 'used')
                · {{ $p->redeemed_at->timezone('Asia/Baku')->format('d.m.Y H:i') }} istifadə olunub
            @else
                · {{ $p->expires_at?->timezone('Asia/Baku')->format('d.m.Y H:i') }} vaxtı bitib
            @endif
        </span>
        @if ($st === 'active' && $p->expires_at)
            {{-- etibarlılıq müddəti: böyük və diqqətçəkən; geri sayım prize-qr.js-dədir --}}
            @php($exp = $p->expires_at->timezone('Asia/Baku'))
            <div class="prize-expiry" data-expires="{{ $p->expires_at->toIso8601String() }}">
                <i class="fa fa-hourglass-half"></i>
                <div>
                    <span class="prize-expiry-label">Etibarlıdır</span>
                    <b>{{ $exp->isToday() ? 'bu gün' : $exp->format('d.m.Y') }} {{ $exp->format('H:i') }}-dək</b>
                    <span class="prize-expiry-left" hidden></span>
                </div>
            </div>
        @endif
    </div>
    <div class="prize-side">
        <code @if ($st === 'active') data-prize-qr="{{ $p->code }}" @endif>{{ $p->code }}</code>
        <span class="prize-badge">{{ $st === 'used' ? 'Qüvvədən düşüb' : $p->statusLabel() }}</span>
    </div>
</li>
