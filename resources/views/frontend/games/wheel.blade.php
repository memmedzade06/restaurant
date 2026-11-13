@extends('frontend.layouts.app')
@section('title', 'Bəxt çarxı - 50 Qəpik Oyunlar')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Oyunlar</span>
        <h1>Bəxt çarxı</h1>
        <p>{{ $attempts }} cəhdiniz var — bəyəndiyiniz endirimi götürün</p>
    </div>
</section>

@php
    $authUrl = auth()->check() ? route('verify.telegram') : route('register');
@endphp

<section class="section pt-4">
    <div class="container">
        <a href="{{ route('games.index') }}" class="back-link"><i class="fa fa-arrow-left"></i> Oyunlara qayıt</a>
        <div class="row g-5 align-items-center">
            <div class="col-lg-7">
                <div id="wheel" class="wheel"
                     data-segments='@json(collect($segments)->map(fn ($s) => ['label' => $s['label'], 'color' => $s['color']])->values())'
                     data-spin-url="{{ route('games.wheel.spin') }}"
                     data-claim-url="{{ route('games.wheel.claim') }}"
                     data-auth-url="{{ $authUrl }}"
                     data-verified="{{ $verified ? 1 : 0 }}"
                     data-attempts="{{ $attempts }}"
                     data-state='@json($state)'>
                    <div class="wheel-pointer"></div>
                    <svg class="wheel-svg" viewBox="-110 -110 220 220" aria-hidden="true"></svg>
                    <button type="button" class="wheel-btn" id="wheelSpin">Fırlat</button>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="side-card wheel-panel">
                    <div class="attempts" id="wheelAttempts" aria-live="polite"></div>

                    {{-- Nəticə və seçim düymələri JS ilə doldurulur --}}
                    <div id="wheelResult" class="wheel-result" hidden></div>

                    <div id="wheelIntro">
                        <h4>Necə işləyir?</h4>
                        <ul class="rules">
                            <li>Sizin <b>{{ $attempts }} cəhdiniz</b> var.</li>
                            <li>Hər fırlatmadan sonra seçin: <b>Hədiyyəni götür</b> və ya <b>yenidən fırlat</b>.</li>
                            <li>Yenidən fırladanda əvvəlki nəticə itir — risk sizindir!</li>
                            <li>Hədiyyəni götürəndə <b>kod</b> yaranır — restoranda ofisianta göstərin. Kod {{ config('games.wheel.code_valid_days') }} gün etibarlıdır, istifadə olunandan sonra qüvvədən düşür. Bütün kodlarınız: <a href="{{ route('prizes') }}">Hədiyyələrim</a>.</li>
                        </ul>
                        @unless ($verified)
                            <div class="alert-soft mt-3">
                                Qonaq kimi yalnız <b>sınaq</b> fırlatması edə bilərsiniz.
                                Real hədiyyə üçün
                                @auth <a href="{{ route('verify.telegram') }}">nömrənizi təsdiqləyin</a>
                                @else <a href="{{ route('register') }}">qeydiyyatdan keçin</a> və ya <a href="{{ route('login') }}">daxil olun</a>
                                @endauth.
                            </div>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script src="{{ asset('js/games/wheel.js') }}?v=6"></script>
@endsection
