/*
 * Profil → Xallar: dəvət linkini kopyalamaq, paylaşmaq və QR kodu göstərmək/yükləmək.
 * QR kod brauzerdə yaradılır (qrcode-generator), link heç yerə göndərilmir.
 */
(function () {
    'use strict';

    const box = document.getElementById('refQr');
    if (!box) return;
    const link = box.dataset.link;
    const DARK = '#3A0D0D';

    // QR kod: ekranda SVG (istənilən ölçüdə kəskin), yükləmək üçün PNG
    let qr = null;
    if (window.qrcode) {
        qr = window.qrcode(0, 'M');
        qr.addData(link);
        qr.make();
        box.innerHTML = qr.createSvgTag({cellSize: 6, margin: 3, scalable: true});
        const svg = box.querySelector('svg');
        svg.querySelectorAll('path, rect').forEach(el => {
            if (el.getAttribute('fill') === 'black' || el.getAttribute('fill') === '#000000') el.setAttribute('fill', DARK);
        });
    } else {
        box.innerHTML = '<p class="small text-muted mb-0">QR kodu yükləmək olmadı. Linki kopyalayıb paylaşın.</p>';
        document.getElementById('refDownload').hidden = true;
    }

    // Kopyala
    const input = document.getElementById('refLink');
    const copyBtn = document.getElementById('refCopy');
    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(link);
        } catch (e) {
            input.select();
            document.execCommand('copy');
        }
        copyBtn.innerHTML = '<i class="fa fa-check me-1"></i>Kopyalandı';
        setTimeout(() => { copyBtn.innerHTML = '<i class="fa fa-copy me-1"></i>Kopyala'; }, 2000);
    });
    input.addEventListener('focus', () => input.select());

    // Paylaş (telefonda — WhatsApp, Telegram və s.)
    const shareBtn = document.getElementById('refShare');
    if (navigator.share) {
        shareBtn.hidden = false;
        shareBtn.addEventListener('click', () => {
            navigator.share({title: '50 Qəpik', text: '50 Qəpik saytında qeydiyyatdan keç, oyunlarda hədiyyələr qazan!', url: link}).catch(() => {});
        });
    }

    // QR kodu PNG kimi yüklə (çap etmək və ya şəkil kimi göndərmək üçün)
    document.getElementById('refDownload').addEventListener('click', () => {
        if (!qr) return;
        const n = qr.getModuleCount(), cell = 12, margin = 4 * cell;
        const size = n * cell + margin * 2;
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = size;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, size, size);
        ctx.fillStyle = DARK;
        for (let r = 0; r < n; r++) {
            for (let c = 0; c < n; c++) {
                if (qr.isDark(r, c)) ctx.fillRect(margin + c * cell, margin + r * cell, cell, cell);
            }
        }
        const a = document.createElement('a');
        a.download = '50qepik-devet-qr.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
    });
})();
