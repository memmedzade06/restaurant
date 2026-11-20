/*
 * Hədiyyə kodunun QR kodu: hər ".prize-code" elementinin (və data-prize-qr atributlu elementin)
 * altına avtomatik QR əlavə olunur. QR-da admin ünvanı var (/admin/hediyyeler/qr/QP-XXXXXX):
 * administrator onu telefonla oxudub açanda hədiyyə istifadə olunur.
 * Oyunlar kodu sonradan (JS ilə) göstərir — ona görə səhifə dəyişiklikləri də izlənir.
 * QR brauzerdə yaradılır (qrcode-generator), kod heç yerə göndərilmir.
 */
(function () {
    'use strict';

    const tpl = document.querySelector('meta[name="prize-qr-url"]')?.content;
    if (!tpl) return;
    const DARK = '#3A0D0D';

    function render(el) {
        if (el.dataset.qrDone || el.classList.contains('no-qr') || !window.qrcode) return;
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

    scan(document.body);
    new MutationObserver(list => {
        for (const m of list) m.addedNodes.forEach(scan);
    }).observe(document.body, {childList: true, subtree: true});
})();
