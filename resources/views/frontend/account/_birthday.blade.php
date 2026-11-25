{{-- Hesabım: ad günü hədiyyəsi — doğum tarixi (bir dəfə) və ad günü gələndə masaya endirim kuponu --}}
@php
    $bd = \App\Support\Birthday::status($me);
    $B = \App\Support\Birthday::class;
@endphp
@if ($bd['state'] !== $B::NOT_ELIGIBLE)
    <div @class(['acc-card', 'mt-4', 'bday-card', 'is-ready' => $bd['state'] === $B::AVAILABLE]) id="ad-gunu">
        <div class="acc-head"><span class="acc-ico bday-ico"><i class="fa fa-birthday-cake"></i></span><h5>Ad günü hədiyyəsi</h5></div>

        @if (session()->has('birthday_saved'))
            <div class="alert-ok text-start"><i class="fa fa-check-circle me-2"></i>Doğum tarixi yadda saxlandı.@if (session('birthday_saved') > 0) <b>+{{ \App\Services\Referrals::format(session('birthday_saved')) }} xal</b> balansınıza yazıldı! 🎉@endif</div>
        @endif
        @error('birthday') <div class="alert-soft text-start mb-3">{{ $message }}</div> @enderror

        @switch($bd['state'])
            @case($B::NO_DATE)
                @php($bdBonus = \App\Support\Birthday::setPoints() > 0 && ! \App\Models\PointLog::where('user_id', $me->id)->where('source', 'birthday')->exists())
                <p class="bday-lead">
                    Ad gününüzdə <b>masaya 50% endirim</b> kuponu hədiyyə edirik!
                    @if ($bdBonus)
                        Doğum tarixinizi yazın və dərhal <b class="bday-bonus">+{{ \App\Services\Referrals::format(\App\Support\Birthday::setPoints()) }} xal</b> qazanın.
                    @else
                        Doğum tarixinizi yazın.
                    @endif
                </p>
                <form method="POST" action="{{ route('account.birthday') }}" class="bday-form">
                    @csrf
                    <input type="date" name="birth_date" value="{{ old('birth_date') }}" max="{{ now('Asia/Baku')->subYears(5)->toDateString() }}" min="1920-01-02" required aria-label="Doğum tarixi">
                    <button type="submit" class="btn btn-wine">Yadda saxla</button>
                </form>
                <small class="hint d-block mt-2"><i class="fa fa-info-circle me-1"></i>Tarix bir dəfə yazılır, sonra dəyişmək olmur — diqqətlə seçin.</small>
                @break

            @case($B::AVAILABLE)
                <div class="bday-ready">
                    <div class="bday-ready-emoji" aria-hidden="true">🎂</div>
                    <h6>Ad gününüz mübarək!</h6>
                    <p>Sizi təbrik edirik! Masaya <b>50% endirim</b> kuponunuz hazırdır.</p>
                    <form method="POST" action="{{ route('account.birthday.claim') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary bday-claim"><i class="fa fa-gift me-2"></i>Kuponu götür</button>
                    </form>
                    <small class="hint d-block mt-2">{{ $bd['until']->isSameDay(\App\Support\Birthday::today()) ? 'Yalnız bu gün götürmək olar' : \App\Support\Birthday::format($bd['until']) . '-dək götürmək olar' }} · kupon {{ (int) config('birthday.valid_days', 7) }} gün etibarlıdır</small>
                </div>
                @break

            @case($B::CLAIMED)
                <p class="mb-2"><i class="fa fa-check-circle text-success me-1"></i>Bu ilin ad günü kuponu götürülüb. Afiyət olsun! 🎉</p>
                <a href="{{ route('prizes') }}" class="acc-more">Hədiyyələrimə bax <i class="fa fa-arrow-right ms-1"></i></a>
                @break

            @case($B::TOO_NEW)
                <ul class="account-list acc-details mb-2">
                    <li><span><i class="fa fa-calendar-day me-2"></i>Ad günü</span><b>{{ \App\Support\Birthday::format($me->birth_date) }}</b></li>
                </ul>
                <p class="small text-muted mb-0">Doğum tarixi yaxın vaxtda yazıldığı üçün bu il kupon verilmir — növbəti ad gününüzdə 50% endirim kuponu sizi gözləyir.</p>
                @break

            @default {{-- WAIT --}}
                <ul class="account-list acc-details mb-2">
                    <li><span><i class="fa fa-calendar-day me-2"></i>Ad günü</span><b>{{ \App\Support\Birthday::format($me->birth_date) }}</b></li>
                    <li><span><i class="fa fa-hourglass-half me-2"></i>Qalıb</span><b>{{ $bd['days_left'] }} gün</b></li>
                </ul>
                <p class="small text-muted mb-0">Ad gününüzdə burada <b>masaya 50% endirim</b> kuponu görünəcək — onu yalnız ad günü götürmək olar.</p>
        @endswitch
    </div>
@endif
