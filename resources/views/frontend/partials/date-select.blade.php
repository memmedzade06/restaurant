{{--
    Tarix seçimi: Gün → Ay → İl (brauzerin dilindən asılı olmayaraq həmişə bu ardıcıllıqla).
    $prefix — sahələrin adı: {prefix}_day, {prefix}_month, {prefix}_year
    $value — ilkin tarix (Carbon/null), $minYear, $maxYear, $required (bool)
--}}
@php
    $months = ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'İyun', 'İyul', 'Avqust', 'Sentyabr', 'Oktyabr', 'Noyabr', 'Dekabr'];
    $d = old($prefix . '_day', $value?->day);
    $m = old($prefix . '_month', $value?->month);
    $y = old($prefix . '_year', $value?->year);
    $req = ! empty($required);
@endphp
<div class="date-select">
    <select name="{{ $prefix }}_day" aria-label="Gün" @if ($req) required @endif>
        <option value="">Gün</option>
        @for ($i = 1; $i <= 31; $i++)
            <option value="{{ $i }}" @selected((int) $d === $i)>{{ $i }}</option>
        @endfor
    </select>
    <select name="{{ $prefix }}_month" aria-label="Ay" @if ($req) required @endif>
        <option value="">Ay</option>
        @foreach ($months as $i => $name)
            <option value="{{ $i + 1 }}" @selected((int) $m === $i + 1)>{{ $name }}</option>
        @endforeach
    </select>
    <select name="{{ $prefix }}_year" aria-label="İl" @if ($req) required @endif>
        <option value="">İl</option>
        @for ($i = $maxYear; $i >= $minYear; $i--)
            <option value="{{ $i }}" @selected((int) $y === $i)>{{ $i }}</option>
        @endfor
    </select>
</div>
