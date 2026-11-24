{{-- Hədiyyə kuponu qazananda (Stəkan, Çarx, Kran) kodun altında: daha çox xal qazanmaq yolları.
     Oyunların JS-i window.earnMoreHtml() ilə bu şablonu götürür. Yalnız xal qazana bilən təsdiqlənmiş qonaqlara. --}}
@auth
    @php($emUser = auth()->user())
    @if ($emUser->isVerified() && $emUser->earnsGamePoints())
        @php($emIgDone = \App\Models\InstagramFollow::where('user_id', $emUser->id)->whereIn('status', ['pending', 'approved'])->exists())
        @php($emRef = \App\Services\Referrals::format(\App\Services\Referrals::levels()[0] ?? 1))
        <template id="earnMoreTpl">
            <div class="earn-more">
                <div class="earn-more-title"><i class="fa fa-star me-1"></i>Daha çox xal qazanın!</div>
                @unless ($emIgDone)
                    <a href="{{ route('account') }}#instagram" class="earn-more-item"><span class="em-ico em-ig"><i class="fab fa-instagram"></i></span>
                        <span>Instagram səhifəmizi izləyin: <b>+{{ \App\Services\Referrals::format(\App\Models\InstagramFollow::reward()) }} xal</b></span><i class="fa fa-chevron-right"></i></a>
                @endunless
                <a href="{{ route('account') }}#devet" class="earn-more-item"><span class="em-ico em-ref"><i class="fa fa-user-plus"></i></span>
                    <span>Linkinizi dostlarınızla paylaşın, hər dost üçün <b>+{{ $emRef }} xal</b></span><i class="fa fa-chevron-right"></i></a>
                <small>Xalları Hədiyyə marketində hədiyyəyə çevirə bilərsiniz.</small>
            </div>
        </template>
    @endif
@endauth
<script>window.earnMoreHtml = function () { var t = document.getElementById('earnMoreTpl'); return t ? t.innerHTML : ''; };</script>
