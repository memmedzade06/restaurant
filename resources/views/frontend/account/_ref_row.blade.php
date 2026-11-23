{{-- Ağacda bir nəfər: ad, qeydiyyat tarixi, statusu, sizə gətirdiyi xal, onun dəvət etdiklərinin sayı --}}
<span class="ref-row">
    <span class="ref-avatar lvl-{{ $n['level'] }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($n['name'], 0, 1)) }}</span>
    <span class="ref-who">
        <b>{{ $n['name'] }}</b>
        <small>{{ $n['date']->timezone('Asia/Baku')->format('d.m.Y') }}@if ($kids) · {{ $kids }} dəvət @endif</small>
    </span>
    @if ($n['verified'])
        <span class="ref-status ok" title="Profilini təsdiqləyib"><i class="fa fa-check-circle"></i>@if ($n['points'] > 0) +{{ \App\Services\Referrals::format($n['points']) }} xal @else təsdiqlənib @endif</span>
    @else
        <span class="ref-status wait" title="Profilini hələ təsdiqləməyib"><i class="fa fa-clock"></i> gözləyir</span>
    @endif
</span>
