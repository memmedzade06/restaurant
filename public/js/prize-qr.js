/*
 * Hədiyyə kodunun QR kodu: hər ".prize-code" elementinin (və data-prize-qr atributlu elementin)
 * altına avtomatik QR əlavə olunur. QR-da admin ünvanı var (/admin/hediyyeler/qr/QP-XXXXXX):
 * administrator onu telefonla oxudub açanda hədiyyə istifadə olunur.
 * Oyunlar kodu sonradan (JS ilə) göstərir — ona görə səhifə dəyişiklikləri də izlənir.
 * QR brauzerdə yaradılır (qrcode-generator), kod heç yerə göndərilmir.
 */
(function () {
    'use strict';

    const tpl = document.querySelector('meta[name="prize-qr-url"]')?.content || '';
    const DARK = '#3A0D0D';

    function render(el) {
        if (!tpl || el.dataset.qrDone || el.classList.contains('no-qr') || !window.qrcode) return;
        const code = (el.dataset.prizeQr || el.textContent || '').trim().toUpperCase();
        if (!/^QP-[A-Z0-9]+$/.test(code)) return;
        el.dataset.qrDone = '1';

        const qr = window.qrcode(0, 'M');
        qr.addData(tpl.replace('__CODE__', encodeURIComponent(code)));
        qr.make();
        const box = document.createElement('div');
        box.className = 'prize-qr';
        box.innerHTML = qr.createSvgTag({cellSize: 4, margin: 2, scalable: true}) +
            '<small>Ofisiant bu QR kodu oxutsun</small>';
        box.querySelectorAll('svg path, svg rect').forEach(p => {
            if (p.getAttribute('fill') === 'black' || p.getAttribute('fill') === '#000000') p.setAttribute('fill', DARK);
        });
        const svg = box.querySelector('svg');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', `${code} hədiyyə kodunun QR kodu`);
        el.insertAdjacentElement('afterend', box);
    }

    const scan = (root) => {
        if (!(root instanceof Element)) return;
        if (root.matches('.prize-code, [data-prize-qr]')) render(root);
        root.querySelectorAll('.prize-code, [data-prize-qr]').forEach(render);
    };

    /* ---------- Kuponun etibarlılıq müddəti: geri sayım ---------- */
    // .prize-expiry[data-expires] — "9 dəq 12 san qalıb"; son 2 dəqiqədə qırmızı və döyünür, bitəndə "Vaxtı bitdi"
    function tick() {
        document.querySelectorAll('.prize-expiry[data-expires]').forEach(el => {
            const left = Math.floor((Date.parse(el.dataset.expires) - Date.now()) / 1000);
            const out = el.querySelector('.prize-expiry-left');
            if (!out) return;
            out.hidden = false;
            if (left <= 0) {
                out.textContent = 'Vaxtı bitdi';
                el.classList.add('is-over');
                el.classList.remove('is-soon');
                return;
            }
            const d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
            out.textContent = (d ? `${d} gün ${h} saat` : h ? `${h} saat ${m} dəq` : `${m} dəq ${String(s).padStart(2, '0')} san`) + ' qalıb';
            el.classList.toggle('is-soon', left <= 120);
        });
    }
    tick();
    setInterval(tick, 1000);

    scan(document.body);
    new MutationObserver(list => {
        for (const m of list) m.addedNodes.forEach(scan);
    }).observe(document.body, {childList: true, subtree: true});
})();
