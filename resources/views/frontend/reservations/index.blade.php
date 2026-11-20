@extends('frontend.layouts.app')
@section('title', 'Masa rezervasiyası - 50 Qəpik')

@section('css')
<link href="{{ asset('css/reservations.css') }}?v=38" rel="stylesheet">
@endsection

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Rezervasiya</span>
        <h1>Masanızı seçin</h1>
        <p>Planda istədiyiniz masaya toxunun, tarix və saatı seçib məlumatlarınızı yazın</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container" id="booking" data-booking='@json($booking)'>
        @if (session('success'))
            <div class="alert alert-success rv-alert"><i class="fa fa-check-circle me-2"></i>{{ session('success') }}</div>
        @endif

        <div class="rv-toolbar">
            <div class="rv-floors" role="tablist" aria-label="Mərtəbə">
                @foreach ($floors as $n => $floor)
                    <button type="button" role="tab" class="rv-floor-tab {{ $loop->first ? 'active' : '' }}" data-floor="{{ $n }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="floor-{{ $n }}">
                        <i class="fa fa-layer-group me-1"></i>{{ $floor['name'] }}
                    </button>
                @endforeach
            </div>
            <label class="rv-day">
                <i class="fa fa-calendar-alt"></i>
                <span>Tarix:</span>
                <input type="date" id="rvDay" value="{{ $booking['today'] }}" min="{{ $booking['today'] }}" max="{{ $booking['maxDate'] }}">
            </label>
        </div>

        <div class="rv-legend">
            {{-- rənglər plandakı masalar kimidir: taxta — boş, qızılı — seçilən tarixdə rezerv var --}}
            <span><i class="rv-dot rv-dot-free"></i>Boş</span>
            <span><i class="rv-dot rv-dot-busy"></i>Seçilən tarixdə rezerv var (saatı masada yazılır)</span>
            <span><i class="fa {{ $kinds['table']['icon'] }}"></i>{{ $kinds['table']['name'] }}</span>
            <span><i class="fa {{ $kinds['sofa']['icon'] }}"></i>{{ $kinds['sofa']['name'] }}</span>
            <span><i class="fa {{ $kinds['okey']['icon'] }}"></i>{{ $kinds['okey']['name'] }}</span>
        </div>

        @foreach ($floors as $n => $floor)
            <div class="rv-plan-wrap" id="floor-{{ $n }}" role="tabpanel" @unless ($loop->first) hidden @endunless>
                {{-- monitor çərçivənin sol divarına söykənir (planın kənar boşluğundan asılı olmadan) --}}
                @foreach (array_filter($floor['decor'] ?? [], fn ($d) => $d['type'] === 'screen') as $d)
                    <div class="rv-decor rv-decor-screen rv-wall-left" style="top:calc(var(--pad) + (100% - 2 * var(--pad)) * {{ $d['y'] / 100 }});height:calc((100% - 2 * var(--pad)) * {{ $d['h'] / 100 }})"><span>{{ $d['label'] }}</span></div>
                @endforeach
                <div class="rv-plan" style="aspect-ratio: {{ $floor['ratio'] }}; max-width: {{ $floor['max_vh'] ?? round(78 * $floor['ratio'], 1) }}vh">
                    @foreach (array_filter($floor['decor'] ?? [], fn ($d) => $d['type'] !== 'screen') as $d)
                        <div class="rv-decor rv-decor-{{ $d['type'] }}" style="left:{{ $d['x'] }}%;top:{{ $d['y'] }}%;width:{{ $d['w'] }}%;height:{{ $d['h'] }}%">
                            @if ($d['label'] !== '')<span>{{ $d['label'] }}</span>@endif
                        </div>
                    @endforeach
                    @foreach ($floor['tables'] as $key => $t)
                        <button type="button" class="rv-table rv-kind-{{ $t['kind'] }}" data-table="{{ $key }}"
                                style="left:{{ $t['x'] }}%;top:{{ $t['y'] }}%;width:{{ $t['w'] }}%;height:{{ $t['h'] }}%"
                                aria-label="{{ $floor['name'] }}, {{ $t['label'] }} ({{ $kinds[$t['kind']]['name'] }}) — rezerv et">
                            @if ($t['kind'] === 'table')
                                {{-- 4 nəfərlik masa: ortada masa, yuxarıda və aşağıda üzbəüz iki-iki stul --}}
                                <span class="setting" aria-hidden="true"><i class="chair chair-t1"></i><i class="chair chair-t2"></i><i class="chair chair-b1"></i><i class="chair chair-b2"></i><i class="dine-table"></i></span>
                            @endif
                            @if ($t['kind'] === 'okey')
                                {{-- okey masası: künclərində oyuq olan kvadrat masa, ortasında yaşıl mahud, stullar oyuq künclərə baxır --}}
                                <span class="okey" aria-hidden="true"><i class="okey-table"><i class="chair okey-c okey-c-tl"></i><i class="chair okey-c okey-c-tr"></i><i class="chair okey-c okey-c-bl"></i><i class="chair okey-c okey-c-br"></i><i class="okey-felt"></i></i></span>
                            @endif
                            @if ($t['kind'] === 'sofa')
                                {{-- divanlı masa: ortada masa, yuxarıda və aşağıda üzbəüz divanlar (enli divanda 3, dar divanda 2 yastıq) --}}
                                @php($sofa = '<i class="sofa-back"></i><i class="sofa-arm sofa-arm-l"></i><i class="sofa-arm sofa-arm-r"></i><span class="sofa-seat">' . str_repeat('<i></i>', $t['w'] >= 14 ? 3 : 2) . '</span>')
                                <span class="booth" aria-hidden="true"><span class="sofa sofa-top">{!! $sofa !!}</span><i class="booth-table"></i><span class="sofa sofa-bottom">{!! $sofa !!}</span></span>
                            @endif
                            @if ($t['kind'] === 'cabin' && ($in = config("reservations.cabin_furniture.$key")))
                                {{-- kabinetin içi: divanlı masa və ya üzbəüz stullu masa --}}
                                <span class="cabin-furniture" aria-hidden="true">
                                    @if ($in === 'sofa')
                                        @php($sofa = '<i class="sofa-back"></i><i class="sofa-arm sofa-arm-l"></i><i class="sofa-arm sofa-arm-r"></i><span class="sofa-seat">' . str_repeat('<i></i>', 4) . '</span>')
                                        <span class="booth"><span class="sofa sofa-top">{!! $sofa !!}</span><i class="booth-table"></i><span class="sofa sofa-bottom">{!! $sofa !!}</span></span>
                                    @else
                                        <span class="setting"><i class="chair chair-t1"></i><i class="chair chair-t2"></i><i class="chair chair-b1"></i><i class="chair chair-b2"></i><i class="dine-table"></i></span>
                                    @endif
                                </span>
                            @endif
                            {{-- dar ekranda "Masa" sözü gizlənir, yalnız nömrə qalır --}}
                            <span class="rv-table-name">@if (str_starts_with($t['label'], 'Masa '))<span class="rv-word">Masa </span>{{ substr($t['label'], 5) }}@else{{ $t['label'] }}@endif</span>
                            @unless ($t['kind'] === 'cabin')
                                <span class="rv-table-kind" aria-hidden="true"><i class="fa {{ $kinds[$t['kind']]['icon'] }}"></i><span class="rv-word"> {{ $kinds[$t['kind']]['short'] }}</span></span>
                            @endunless
                            <span class="rv-table-busy" hidden></span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach

        <p class="rv-note"><i class="fa fa-info-circle me-1"></i>Rezervasiya {{ rtrim(rtrim(number_format($booking['hours'], 1, '.', ''), '0'), '.') }} saat üçündür. Sualınız varsa, zəng edin: <a href="tel:+994517171565">+994 51 717 15 65</a></p>
    </div>
</section>

{{-- Rezervasiya pəncərəsi --}}
<div class="modal fade" id="rvModal" tabindex="-1" aria-labelledby="rvTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rv-modal">
            <button type="button" class="btn-close rv-close" data-bs-dismiss="modal" aria-label="Bağla"></button>

            <form id="rvForm" class="rv-form" novalidate>
                <div class="rv-modal-head">
                    <span class="rv-modal-ico"><i class="fa fa-chair"></i></span>
                    <div>
                        <small id="rvFloor"></small>
                        <h3 id="rvTitle">Masa</h3>
                        <span class="rv-modal-kind" id="rvKind"></span>
                    </div>
                </div>

                <input type="hidden" name="table" id="rvTable">

                {{-- form hamıya açıqdır; daxil olmayan / təsdiqlənməmiş "Rezerv et" basanda giriş xəbərdarlığı çıxır --}}
                <div id="rvFields">
                <p class="rv-resume" id="rvResume" hidden><i class="fa fa-check-circle me-1"></i>Hesabınıza daxil oldunuz. Məlumatları yoxlayıb rezervi təsdiqləyin.</p>
                <div class="row g-3">
                    <div class="col-6">
                        <label for="rvDate">Tarix</label>
                        <input type="date" id="rvDate" name="date" required min="{{ $booking['today'] }}" max="{{ $booking['maxDate'] }}">
                    </div>
                    <div class="col-6">
                        <label for="rvTime">Saat</label>
                        <select id="rvTime" name="time" required></select>
                    </div>
                    <div class="col-12" id="rvTakenRow" hidden><p class="rv-taken" id="rvTaken"></p></div>
                    <div class="col-12">
                        <label for="rvName">Ad, soyad</label>
                        <input type="text" id="rvName" name="name" required maxlength="120" autocomplete="name" placeholder="Adınız və soyadınız" value="{{ $booking['prefill']['name'] }}">
                    </div>
                    <div class="col-12">
                        <label for="rvPhone">Telefon nömrəsi</label>
                        {{-- nömrə hər dəfə yazılır; hesabda nömrə varsa, əvvəlcədən doldurulur --}}
                        <input type="tel" id="rvPhone" name="phone" required maxlength="13" inputmode="numeric" autocomplete="tel-national" placeholder="Telefon nömrəniz" pattern="0\d{2} \d{3} \d{2} \d{2}" value="{{ $booking['prefill']['phone'] }}">
                    </div>
                </div>

                <p class="rv-error" id="rvError" role="alert" hidden></p>

                <button type="submit" class="btn btn-primary w-100 py-3 mt-3" id="rvSubmit">
                    <i class="fa fa-check me-2"></i>Rezerv et
                </button>
                </div>

                @unless ($booking['access'] === 'ok')
                    {{-- boş-boşuna rezerv olmasın: rezervi yalnız təsdiqlənmiş hesab təsdiqləyə bilər. Yazılan məlumat yadda qalır --}}
                    <div class="rv-gate" id="rvGate" hidden>
                        <div class="rv-gate-ico"><i class="fa {{ $booking['access'] === 'guest' ? 'fa-user-lock' : 'fa-user-check' }}"></i></div>
                        @if ($booking['access'] === 'guest')
                            <p><b>Son addım: rezervi təsdiqləmək üçün hesabınıza daxil olun.</b><br>Boş rezervlər olmasın deyə masanı yalnız təsdiqlənmiş hesabla rezerv etmək olur.</p>
                            <a href="{{ route('reservations.continue', ['to' => 'login']) }}" class="btn btn-primary w-100 py-3"><i class="fa fa-sign-in-alt me-2"></i>Daxil ol</a>
                            <a href="{{ route('reservations.continue', ['to' => 'register']) }}" class="btn btn-outline-secondary w-100 py-2 mt-2">Qeydiyyatdan keç</a>
                        @else
                            <p><b>Son addım: rezervi təsdiqləmək üçün profilinizi təsdiqləyin.</b><br>Boş rezervlər olmasın deyə masanı yalnız təsdiqlənmiş hesabla rezerv etmək olur.</p>
                            <a href="{{ route('reservations.continue') }}" class="btn btn-primary w-100 py-3"><i class="fa fa-check me-2"></i>Profili təsdiqlə</a>
                        @endif
                        <button type="button" class="btn btn-link w-100 mt-1 rv-gate-back" id="rvGateBack">← Məlumatlara qayıt</button>
                    </div>
                @endunless
            </form>

            <div class="rv-done" id="rvDone" hidden>
                <div class="rv-done-ico"><i class="fa fa-check"></i></div>
                <h3>Masa rezerv olundu!</h3>
                <p id="rvDoneText"></p>
                <button type="button" class="btn btn-primary px-5" data-bs-dismiss="modal">Oldu</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="{{ asset('js/reservations.js') }}?v=7"></script>
@endsection
