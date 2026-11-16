{{-- Nərd: dünənin qalibləri və aldıqları endirim --}}
@if ($winners->isNotEmpty())
    <div class="nard-winners">
        <h6><i class="fa fa-medal me-2"></i>Dünənin qalibləri</h6>
        <ol>
            @foreach ($winners as $w)
                <li class="{{ auth()->id() === $w->user_id ? 'me' : '' }}">
                    <span class="medal">{{ ['', '🥇', '🥈', '🥉'][$w->place] ?? $w->place }}</span>
                    <span class="who">{{ $w->user?->short_name ?? 'Oyunçu' }}<small>{{ config("games.nard.daily_prizes.{$w->place}.label") }}</small></span>
                    <span class="pts">{{ (int) $w->points }} xal</span>
                </li>
            @endforeach
        </ol>
        @if ($winners->contains('user_id', auth()->id()))
            <p class="nw-me mb-0">🎉 Təbriklər, siz qaliblərdəsiniz! Endirim kodunuz <a href="{{ route('prizes') }}">Hədiyyələrim</a> səhifəsindədir.</p>
        @endif
    </div>
@endif
