@if ($leaders->isEmpty())
    <p class="text-muted mb-0">Hələ heç kim xal toplamayıb. İlk olun!</p>
@else
    <ol class="leaders">
        @foreach ($leaders as $i => $row)
            @php
                $parts = preg_split('/\s+/u', trim($row->user->name ?? 'Oyunçu'));
                $display = $parts[0] . (isset($parts[1]) ? ' ' . mb_substr($parts[1], 0, 1) . '.' : '');
            @endphp
            <li class="{{ auth()->id() === $row->user_id ? 'me' : '' }}">
                <span class="pos">{{ $i + 1 }}</span>
                <span class="who">{{ $display }}</span>
                <span class="pts">{{ (int) $row->points }} xal</span>
            </li>
        @endforeach
    </ol>
@endif
