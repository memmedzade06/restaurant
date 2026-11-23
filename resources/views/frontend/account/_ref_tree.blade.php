{{--
    Dəvət piramidası: kim sizin link/QR ilə qeydiyyatdan keçib, onlar kimləri dəvət edib və s.
    $tree — \App\Services\Referrals::tree($user); $fmt — xal formatı.
--}}
@php
    $total = array_sum(array_column($tree['levels'], 'count'));
@endphp

{{-- yuxarıdan aşağı genişlənən piramida: siz → 1-ci → 2-ci → 3-cü səviyyə --}}
<div class="ref-pyramid" role="list" aria-label="Dəvət piramidası">
    <div class="ref-tier ref-tier-me" role="listitem" style="--w: 34%">
        <i class="fa fa-crown me-2"></i>Siz
    </div>
    @foreach ($tree['levels'] as $i => $lv)
        <div class="ref-tier {{ $lv['count'] ? '' : 'is-empty' }}" role="listitem" style="--w: {{ 34 + ($i + 1) * 22 }}%">
            <span class="ref-tier-name">{{ \App\Http\Controllers\Frontend\QuizController::ordinal($i + 1) }} səviyyə</span>
            <b>{{ $lv['count'] }} nəfər</b>
            @if ($lv['earned'] > 0)<em>+{{ $fmt($lv['earned']) }} xal</em>@endif
        </div>
    @endforeach
</div>

@if ($total === 0)
    <p class="acc-note mb-0 text-center">Hələ heç kim sizin linkinizlə qeydiyyatdan keçməyib. Linki və ya QR kodu paylaşın — dəvət etdikləriniz burada görünəcək.</p>
@else
    {{-- ağac: hər dostu açanda onun dəvət etdikləri görünür --}}
    <ul class="ref-tree">
        @include('frontend.account._ref_nodes', ['nodes' => $tree['nodes']])
    </ul>
    @if ($tree['truncated'])
        <p class="small text-muted mt-2 mb-0">Siyahı çox böyükdür — yalnız ilk dəvətlər göstərilir, saylar isə tamdır.</p>
    @endif
    <p class="ref-legend small text-muted mt-3 mb-0">
        <span><i class="fa fa-check-circle text-success"></i> profilini təsdiqləyib — xal yazılıb</span>
        <span><i class="fa fa-clock"></i> təsdiq gözləyir — təsdiqləyəndə xal yazılacaq</span>
    </p>
@endif
