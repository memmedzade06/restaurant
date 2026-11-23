{{--
    $unit: nəticənin vahidi — Nərddə "xal", Stəkan qülləsində "stəkan".
    $initial: əvvəlcə neçə nəfər görünsün (qalanları "Hamısını göstər" ilə açılır); boş — hamısı.
    $decimals: xal kəsrli göstərilsin (0,25) — Xal liderləri üçün.
--}}
@php
    $unit = $unit ?? 'xal';
    $initial = $initial ?? null;
    $decimals = $decimals ?? false;
    $more = $initial && $leaders->count() > $initial;
@endphp
@if ($leaders->isEmpty())
    <p class="text-muted mb-0">{{ $unit === 'stəkan' ? 'Hələ heç kim oynamayıb.' : 'Hələ heç kim xal toplamayıb.' }} İlk olun!</p>
@else
    <ol class="leaders">
        @foreach ($leaders as $i => $row)
            <li class="{{ auth()->id() === $row->user_id ? 'me' : '' }}" @if ($more && $i >= $initial) hidden data-more @endif>
                <span class="pos">{{ $i + 1 }}</span>
                <span class="who">{{ $row->user?->short_name ?? 'Oyunçu' }}</span>
                <span class="pts">{{ $decimals ? \App\Services\Referrals::format((float) $row->points) : (int) $row->points }} {{ $unit }}</span>
            </li>
        @endforeach
    </ol>
    @if ($more)
        <button type="button" class="leaders-more" aria-expanded="false"
                data-show="Hamısını göstər ({{ $leaders->count() }})" data-hide="Az göstər">
            <i class="fa fa-chevron-down me-1"></i><span>Hamısını göstər ({{ $leaders->count() }})</span>
        </button>
        @once
            <script>
                // liderlər: "Hamısını göstər" — gizli sətirləri açır / bağlayır
                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('.leaders-more');
                    if (!btn) return;
                    var open = btn.getAttribute('aria-expanded') !== 'true';
                    btn.previousElementSibling.querySelectorAll('[data-more]').forEach(function (li) { li.hidden = !open; });
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    btn.querySelector('span').textContent = open ? btn.dataset.hide : btn.dataset.show;
                });
            </script>
        @endonce
    @endif
@endif
