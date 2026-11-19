{{-- Qeydiyyat Telegram ilə təsdiqlənəndən sonra (açılan səhifədə — Oyunlar) bir dəfə görünür, 5 saniyəyə özü bağlanır --}}
@if (session('registered'))
    <div class="modal fade" id="registeredModal" tabindex="-1" aria-labelledby="registeredTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content success-modal">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button>
                <div class="success-ico"><i class="fa fa-check"></i></div>
                <h3 id="registeredTitle">Qeydiyyatınız təsdiqləndi!</h3>
                <p>{{ auth()->user()?->phone_verified_at ? 'Telefon nömrəniz Telegram vasitəsilə' : 'E-poçt ünvanınız' }} uğurla təsdiqləndi. 50 Qəpik ailəsinə xoş gəlmisiniz — oyunlar sizi gözləyir!</p>
                <button type="button" class="btn btn-primary px-5 py-2" data-bs-dismiss="modal">Oynamağa başla</button>
                <span class="success-bar"></span>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const el = document.getElementById('registeredModal');
            const modal = new bootstrap.Modal(el);
            modal.show();
            const timer = setTimeout(() => modal.hide(), 5000);
            el.addEventListener('hidden.bs.modal', () => clearTimeout(timer));
        })();
    </script>
@endif
