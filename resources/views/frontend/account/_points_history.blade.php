{{--
    Xal tarixçəsi: balans necə yığılıb. $history — \App\Services\Points::history($user).
    $admin = true (admin panel): tam adlar, kupon kodları, düzəlişi edən admin, bərpa olunmuş qeydlər.
--}}
@php
    $admin = $admin ?? false;
    $f = fn ($p) => \App\Services\Referrals::format(abs((float) $p));
    $sign = fn ($p) => $p > 0 ? '+' : ($p < 0 ? '−' : '');
@endphp

@if ($history['count'] === 0 && ! $history['before'])
    <p class="acc-note mb-0">Hələ xal hərəkəti yoxdur. Oyunlarda mərhələ keçəndə, dost dəvət edəndə və ya hədiyyə alanda burada görünəcək.</p>
@else
    {{-- mənbələr üzrə yekun --}}
    <div class="ph-sum">
        @foreach ($history['totals'] as $src => $t)
            <span class="ph-chip {{ $t['total'] < 0 ? 'minus' : '' }}">
                <i class="{{ \App\Models\PointLog::iconClass($src) }}"></i>{{ \App\Models\PointLog::SOURCES[$src][0] }}
                <b>{{ $sign($t['total']) }}{{ $f($t['total']) }}</b>
            </span>
        @endforeach
        @if ($history['before'])
            <span class="ph-chip old" title="Tarixçə saxlanmağa başlamazdan əvvəl qazanılmış xallar (əsasən oyunlardan)">
                <i class="fa fa-history"></i>Əvvəlki xallar <b>{{ $sign($history['before']) }}{{ $f($history['before']) }}</b>
            </span>
        @endif
    </div>

    <ul class="ph-list">
        @foreach ($history['logs'] as $log)
            <li class="ph-row">
                <span class="ph-ico src-{{ $log->source }}"><i class="{{ $log->icon() }}"></i></span>
                <span class="ph-what">
                    <b>{{ $log->sourceLabel() }}</b>
                    <small>{{ $log->describe($admin) }}@if ($admin && $log->restored) · <em>bərpa olunub</em>@endif</small>
                </span>
                <span class="ph-right">
                    <b class="ph-amt {{ $log->amount < 0 ? 'minus' : 'plus' }}">{{ $sign($log->amount) }}{{ $f($log->amount) }}</b>
                    <small>{{ $log->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }}</small>
                </span>
            </li>
        @endforeach
    </ul>
    @if ($history['count'] > $history['logs']->count())
        <p class="small text-muted mt-2 mb-0">Son {{ $history['logs']->count() }} hərəkət göstərilir (cəmi {{ $history['count'] }}).</p>
    @endif
    @if ($history['before'])
        <p class="small text-muted mt-2 mb-0"><i class="fa fa-history me-1"></i>"Əvvəlki xallar" — tarixçə saxlanmağa başlamazdan əvvəl qazanılmış xallardır (əsasən oyunlardan).</p>
    @endif
@endif
