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
                <div class="earn-more-grid">
                    @unless ($emIgDone)
                        <a href="{{ route('account') }}#instagram" class="earn-more-item"><span class="em-ico em-ig"><i class="fab fa-instagram"></i></span>
                            <span>Instagram-da izlə <b>+{{ \App\Services\Referrals::format(\App\Models\InstagramFollow::reward()) }} xal</b></span></a>
                    @endunless
                    <a href="{{ route('account') }}#devet" class="earn-more-item"><span class="em-ico em-ref"><i class="fa fa-user-plus"></i></span>
                        <span>Dostlarınla paylaş <b>+{{ $emRef }} xal</b> hər dost</span></a>
                </div>
            </div>
        </template>
    @endif
@endauth
<script>window.earnMoreHtml = function () { var t = document.getElementById('earnMoreTpl'); return t ? t.innerHTML : ''; };</script>
