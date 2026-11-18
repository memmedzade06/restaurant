/*
 * Oyuna girəndə əvvəlcə qaydalar pəncərəsi açılır. "Tanış oldum" basılana qədər oyun bağlıdır:
 * pəncərə səhifəni örtür, oyunların klaviatura düymələri (boşluq, oxlar) də işləmir.
 * Qaydalar səhifədəki [data-game-rules] blokundan götürülür — mətn bir yerdə yazılır, iki yerdə görünür.
 */
(function () {
    'use strict';

    const src = document.querySelector('[data-game-rules]');
    if (!src || !window.bootstrap) return;

    const game = src.dataset.gameRules || document.querySelector('h1')?.textContent.trim() || 'Oyun';
    const body = document.createElement('div');
    src.querySelectorAll('.rules-note, ul.rules').forEach(el => body.appendChild(el.cloneNode(true)));
    if (!body.children.length) return;
    body.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));

    const modalEl = document.createElement('div');
    modalEl.className = 'modal fade rules-modal';
    modalEl.id = 'gameRulesModal';
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-labelledby', 'gameRulesTitle');
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.innerHTML =
        '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">' +
        '<div class="modal-content">' +
        '<div class="modal-header"><span class="rules-modal-ico"><i class="fa fa-book-open"></i></span>' +
        '<div><span class="eyebrow">Qaydalar</span><h3 class="modal-title" id="gameRulesTitle"></h3></div></div>' +
        '<div class="modal-body"></div>' +
        '<div class="modal-footer"><button type="button" class="btn btn-primary w-100 py-3" data-bs-dismiss="modal">' +
        '<i class="fa fa-check me-2"></i>Tanış oldum, oyuna başla</button></div>' +
        '</div></div>';
    modalEl.querySelector('.modal-title').textContent = game;
    modalEl.querySelector('.modal-body').appendChild(body);
    document.body.appendChild(modalEl);

    // pəncərə açıqkən oyunun klaviatura idarəsi (boşluq, oxlar, Enter) işləməsin.
    // Pəncərə açılanda fokus onun içinə keçir — ona görə düymə hara basılsa da, oyuna ötürülmür;
    // pəncərənin öz işi (düyməni basmaq, mətni sürüşdürmək) isə adi qaydada işləyir.
    const GAME_KEYS = [' ', 'Spacebar', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'];
    const blockKeys = e => {
        if (!GAME_KEYS.includes(e.key) && !['Space', 'Enter'].includes(e.code)) return;
        e.stopImmediatePropagation();                     // oyunun dinləyicilərinə çatmasın
        if (!modalEl.contains(e.target)) e.preventDefault(); // arxadakı səhifə sürüşməsin
    };
    window.addEventListener('keydown', blockKeys, true);

    // arxa fona basmaqla və ya Esc ilə bağlanmır — yalnız "Tanış oldum"
    const modal = new bootstrap.Modal(modalEl, {backdrop: 'static', keyboard: false});
    modalEl.addEventListener('hidden.bs.modal', () => {
        window.removeEventListener('keydown', blockKeys, true);
        document.dispatchEvent(new CustomEvent('game-rules-accepted'));
    });
    modal.show();
})();
