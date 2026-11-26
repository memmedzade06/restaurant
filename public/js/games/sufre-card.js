/**
 * Oyunlar səhifəsində "Süfrəni doldur" kartının şəkli: oyundakı nemətlər (sufre-art.js) yuxarıdan düşür,
 * aşağıda mis sini sağa-sola gedir, üstündə tutulmuş nemətlər. Kart görünəndə oynayır, görünməyəndə dayanır;
 * "hərəkəti azalt" ayarında sabit şəkil çəkilir.
 */
(function () {
    const cv = document.querySelector('.sf-card-canvas');
    if (!cv || !window.SufreArt) return;
    const A = window.SufreArt;
    const ctx = cv.getContext('2d');
    const H = 190; // məntiqi hündürlük (kartın şəkil sahəsi)
    let W = 320, k = 1, dpr = 1;

    const cloth = new Image();
    cloth.src = cv.dataset.cloth || '';

    // nemətlərin kartdakı ölçüsü — oyundakı kimi real nisbətdə
    const KINDS = {
        paxlava: [A.paintPaxlava, 48, 48, 0.7], shekerbura: [A.paintShekerbura, 48, 48, 0.8], qend: [A.paintQend, 48, 48, 0.55],
        limon: [A.paintLimon, 48, 48, 0.6], shokolad: [A.paintShokolad, 48, 48, 0.68], qoz: [A.paintQoz, 48, 48, 0.56],
        glass: [A.paintGlass, A.GW * 1.4, A.GH + 12, 0.56], teapot: [A.paintTeapot, 64, 56, 1.1], hookah: [A.paintHookah, 70, 100, 0.8],
        coal: [A.paintCoal, 36, 40, 0.65],
    };
    // düşən nemətlər: [növ, x (enin hissəsi), sürət px/s, faza]
    const FALL = [
        ['paxlava', 0.12, 46, 0.1], ['glass', 0.34, 58, 0.6], ['coal', 0.55, 52, 0.3], ['hookah', 0.86, 40, 0.75],
        ['shokolad', 0.68, 55, 0.95],
    ];
    const ON_TRAY = ['limon', 'qend', 'paxlava', 'shokolad'];

    const sprites = {};
    function sprite(kind) {
        const [paint, w, h] = KINDS[kind];
        const res = Math.max(1, dpr * k) * 1.5;
        const sp = sprites[kind];
        if (sp && sp.res === res) return sp;
        const c2 = document.createElement('canvas');
        c2.width = Math.ceil(w * res); c2.height = Math.ceil(h * res);
        const c = c2.getContext('2d');
        c.setTransform(res, 0, 0, res, (w / 2) * res, (h / 2) * res);
        paint(c);
        if (kind === 'coal') A.paintCoalGlow(c);
        return (sprites[kind] = {cv: c2, w, h, res});
    }
    const CARD_SCALE = 1.6; // kart vitrindir — nemətlər oyundakından iri görünür
    function put(kind, x, y, rot = 0, extra = 1) {
        const sp = sprite(kind), s = KINDS[kind][3] * extra * CARD_SCALE;
        ctx.save();
        ctx.translate(x, y);
        if (rot) ctx.rotate(rot);
        if (kind === 'coal') { // köz parıltısı
            const g = ctx.createRadialGradient(0, 2, 2, 0, 2, 30);
            g.addColorStop(0, 'rgba(255, 100, 25, .45)'); g.addColorStop(1, 'rgba(255, 60, 10, 0)');
            ctx.fillStyle = g; ctx.beginPath(); ctx.arc(0, 2, 30, 0, Math.PI * 2); ctx.fill();
        }
        ctx.drawImage(sp.cv, -sp.w / 2 * s, -sp.h / 2 * s, sp.w * s, sp.h * s);
        ctx.restore();
    }

    function resize() {
        const r = cv.getBoundingClientRect();
        dpr = Math.min(2, window.devicePixelRatio || 1);
        cv.width = Math.round(r.width * dpr);
        cv.height = Math.round(r.height * dpr);
        k = r.height / H;
        W = r.width / k;
    }

    function tray(x) {
        const y = H - 38, rx = 64, ry = 13;
        ctx.fillStyle = 'rgba(0, 0, 0, .3)';
        ctx.beginPath(); ctx.ellipse(x, H - 10, rx * 0.8, 4, 0, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#7a3e14';
        ctx.beginPath(); ctx.ellipse(x, y + 4, rx, ry, 0, 0, Math.PI * 2); ctx.fill();
        const g = ctx.createLinearGradient(x - rx, 0, x + rx, 0);
        g.addColorStop(0, '#b8642a'); g.addColorStop(0.45, '#f2b36b'); g.addColorStop(1, '#a4531d');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.ellipse(x, y, rx, ry, 0, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = 'rgba(122, 62, 20, .7)'; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.ellipse(x, y, rx - 6, ry - 3, 0, 0, Math.PI * 2); ctx.stroke();
        ON_TRAY.forEach((kind, i) => put(kind, x + (i - 1.5) * 22, y - 9 - (i % 2) * 2, 0, 0.6));
    }

    function frame(t) {
        ctx.setTransform(dpr * k, 0, 0, dpr * k, 0, 0);
        const bg = ctx.createRadialGradient(W / 2, H * 0.35, 20, W / 2, H * 0.4, W * 0.7);
        bg.addColorStop(0, '#4a1d18'); bg.addColorStop(1, '#1e0b08');
        ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
        if (cloth.complete && cloth.naturalWidth) { // zəif xalça naxışı, aşağıda süfrə
            const s = Math.max(W / cloth.naturalWidth, H / cloth.naturalHeight);
            ctx.globalAlpha = 0.12;
            ctx.drawImage(cloth, (W - cloth.naturalWidth * s) / 2, (H - cloth.naturalHeight * s) / 2, cloth.naturalWidth * s, cloth.naturalHeight * s);
            ctx.globalAlpha = 1;
            ctx.drawImage(cloth, 0, 0, cloth.naturalWidth, cloth.naturalWidth * 16 / W, 0, H - 16, W, 16);
        } else {
            ctx.fillStyle = '#7e1a1a'; ctx.fillRect(0, H - 16, W, 16);
        }
        ctx.fillStyle = 'rgba(0, 0, 0, .3)'; ctx.fillRect(0, H - 16, W, 2);

        for (const [kind, fx, speed, ph] of FALL) {
            const span = H + 90;
            const y = ((t * speed + ph * span) % span) - 50;
            if (y > H - 50) continue; // sininin xəttindən aşağı düşmür (kartda "tutulur")
            put(kind, fx * W + Math.sin(t * 2 + ph * 6) * 5, y, Math.sin(t * 2.2 + ph * 6) * 0.15);
        }
        tray(W / 2 + Math.sin(t * 0.9) * W * 0.28);
    }

    let raf = 0, visible = true, start = performance.now();
    const still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function loop(ms) {
        raf = 0;
        if (!visible) return;
        frame((ms - start) / 1000);
        if (!still) raf = requestAnimationFrame(loop);
    }
    function kick() { if (!raf) raf = requestAnimationFrame(loop); }

    resize();
    frame(1.2);
    window.addEventListener('resize', () => { resize(); kick(); });
    cloth.addEventListener('load', kick);
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(es => { visible = es[0].isIntersecting; if (visible) kick(); }).observe(cv);
    }
    kick();
})();
