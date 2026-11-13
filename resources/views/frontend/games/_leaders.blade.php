@if ($leaders->isEmpty())
    <p class="text-muted mb-0">Hələ heç kim xal toplamayıb. İlk olun!</p>
@else
    <ol class="leaders">
        @foreach ($leaders as $i => $row)
            <li class="{{ auth()->id() === $row->user_id ? 'me' : '' }}">
                <span class="pos">{{ $i + 1 }}</span>
                <span class="who">{{ $row->user?->short_name ?? 'Oyunçu' }}</span>
                <span class="pts">{{ (int) $row->points }} xal</span>
            </li>
        @endforeach
    </ol>
@endif
