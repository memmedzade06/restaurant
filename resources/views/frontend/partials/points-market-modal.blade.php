{{-- Oyunlar səhifəsinə daxil olan, balansında xalı olan təsdiqlənmiş istifadəçiyə xatırlatma:
     xallarını Hədiyyə marketində hədiyyəyə çevirə bilər. Brauzer sessiyasında bir dəfə görünür. --}}
@auth
    @php($pmUser = auth()->user())
    @if (request()->routeIs('games.index') && !session('registered') && $pmUser->isVerified() && (float) $pmUser->points > 0)
        <div class="modal fade" id="pointsMarketModal" tabindex="-1" aria-labelledby="pointsMarketTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content success-modal">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button>
                    <div class="success-ico points-ico"><i class="fa fa-gift"></i></div>
                    <h3 id="pointsMarketTitle">Sizin {{ \App\Services\Referrals::format((float) $pmUser->points) }} xalınız var!</h3>
                    <p>Topladığınız xallarla <b>Hədiyyə marketində</b> özünüzə hədiyyə seçə bilərsiniz.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="{{ route('market') }}" class="btn btn-primary px-4 py-2"><i class="fa fa-gift me-2"></i>Hədiyyə seç</a>
                        <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal">Sonra</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var key = 'pointsMarketShown';
                try { if (sessionStorage.getItem(key)) return; sessionStorage.setItem(key, '1'); } catch (e) {}
                var el = document.getElementById('pointsMarketModal');
                // Oyunlar səhifəsi açılandan bir az sonra göstəririk
                setTimeout(function () { new bootstrap.Modal(el).show(); }, 600);
            })();
        </script>
    @endif
@endauth
