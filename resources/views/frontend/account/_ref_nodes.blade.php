{{-- Dəvət ağacının düyünləri (özünü çağırır: hər dostun altında onun dəvət etdikləri) --}}
@foreach ($nodes as $n)
    <li class="ref-node lvl-{{ $n['level'] }}">
        @php $kids = count($n['children']); @endphp
        @if ($kids)
            <details @if ((($admin ?? false) && $n['level'] <= 2) || ($n['level'] === 1 && count($nodes) <= 5)) open @endif>{{-- admin: 2 səviyyəyə qədər açıq --}}
                <summary>@include('frontend.account._ref_row', ['n' => $n, 'kids' => $kids])</summary>
                <ul>
                    @include('frontend.account._ref_nodes', ['nodes' => $n['children']])
                </ul>
            </details>
        @else
            <div class="ref-row-wrap">@include('frontend.account._ref_row', ['n' => $n, 'kids' => 0])</div>
        @endif
    </li>
@endforeach
