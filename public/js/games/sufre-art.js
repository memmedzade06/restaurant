/**
 * Süfrəni doldur — nemətlərin rəsmləri (paxlava, şəkərbura, qənd, limon, şokolad, qoz, armudu stəkanda çay,
 * qızıl çaynik, qəlyan, közərmiş kömür). Hər funksiya verilən kətan kontekstinə (0, 0) mərkəzində çəkir.
 * Həm oyun (sufre.js), həm Oyunlar səhifəsindəki kart (sufre-card.js) istifadə edir — kartdakı nemətlər oyundakı ilə eynidir.
 */
(function () {
    // Köhnə brauzerlərdə (iOS 16-dan əvvəlki Safari, Chrome 99-dan əvvəl) kətanın roundRect funksiyası yoxdur —
    // olmasa, rəsm xəta verib yarımçıq dayanırdı (kart boş görünürdü, oyunda saat paneli çəkilmirdi).
    if (window.CanvasRenderingContext2D && !CanvasRenderingContext2D.prototype.roundRect) {
        CanvasRenderingContext2D.prototype.roundRect = function (x, y, w, h, r) {
            let rad = Array.isArray(r) ? (r[0] || 0) : (r || 0);
            rad = Math.max(0, Math.min(rad, Math.abs(w) / 2, Math.abs(h) / 2));
            this.moveTo(x + rad, y);
            this.lineTo(x + w - rad, y);
            this.arcTo(x + w, y, x + w, y + rad, rad);
            this.lineTo(x + w, y + h - rad);
            this.arcTo(x + w, y + h, x + w - rad, y + h, rad);
            this.lineTo(x + rad, y + h);
            this.arcTo(x, y + h, x, y + h - rad, rad);
            this.lineTo(x, y + rad);
            this.arcTo(x, y, x + rad, y, rad);
            this.closePath();
        };
    }

    /** Toxumlu təsadüfi ədədlər: teksturalar hər dəfə eyni çıxsın */
    function seeded(seed) {
        let v = seed % 2147483647;
        return () => (v = (v * 16807) % 2147483647) / 2147483647;
    }
    function softShadow(c, x, y, rx, ry, a = 0.35) {
        c.save();
        c.translate(x, y);
        c.scale(1, ry / rx);
        const g = c.createRadialGradient(0, 0, 0, 0, 0, rx);
        g.addColorStop(0, `rgba(0, 0, 0, ${a})`);
        g.addColorStop(1, 'rgba(0, 0, 0, 0)');
        c.fillStyle = g;
        c.beginPath(); c.arc(0, 0, rx, 0, Math.PI * 2); c.fill();
        c.restore();
    }
    const poly = (c, pts) => { c.beginPath(); c.moveTo(pts[0][0], pts[0][1]); for (const p of pts.slice(1)) c.lineTo(p[0], p[1]); c.closePath(); };

    // ---- paxlava: romb, yanlarda nazik xəmir qatları, üstü zəfəranlı və şərbətli, ortasında badam ----
    function paintPaxlava(c) {
        const T = 6, B = [0, -16], L = [-18, -1], F = [0, 14], R = [18, -1];
        const dn = p => [p[0], p[1] + T];
        softShadow(c, 0, 21, 19, 4);
        for (const [face, shade] of [[[L, F, dn(F), dn(L)], 'rgba(0, 0, 0, .2)'], [[F, R, dn(R), dn(F)], 'rgba(255, 255, 255, .05)']]) {
            poly(c, face);
            const g = c.createLinearGradient(0, -1, 0, 20);
            g.addColorStop(0, '#d9a052'); g.addColorStop(1, '#8e5420');
            c.fillStyle = g; c.fill();
            c.save(); c.clip();
            for (let i = 1; i < 7; i++) { // xəmir qatları
                const o = i * T / 7;
                c.strokeStyle = i % 2 ? 'rgba(255, 228, 165, .8)' : 'rgba(105, 52, 14, .55)';
                c.lineWidth = 0.7;
                c.beginPath(); c.moveTo(face[0][0], face[0][1] + o); c.lineTo(face[1][0], face[1][1] + o); c.stroke();
            }
            c.fillStyle = shade; c.fill();
            c.restore();
        }
        poly(c, [B, L, F, R]);
        const top = c.createLinearGradient(0, -16, 0, 14);
        top.addColorStop(0, '#7e2a0b'); top.addColorStop(0.45, '#a9481a'); top.addColorStop(1, '#d6873c');
        c.fillStyle = top; c.fill();
        c.save(); c.clip();
        const sheen = c.createRadialGradient(-5, -4, 1, -5, -4, 16);
        sheen.addColorStop(0, 'rgba(255, 190, 110, .45)'); sheen.addColorStop(1, 'rgba(255, 190, 110, 0)');
        c.fillStyle = sheen; c.fillRect(-20, -18, 40, 34);
        const r = seeded(31);
        for (let i = 0; i < 70; i++) { // şərbətdə xırda parıltılar və zəfəran ləkələri
            c.fillStyle = i % 3 ? `rgba(255, 225, 170, ${0.15 + r() * 0.3})` : `rgba(110, 25, 5, ${0.2 + r() * 0.3})`;
            c.beginPath(); c.arc(-16 + r() * 32, -15 + r() * 28, 0.35 + r() * 0.6, 0, Math.PI * 2); c.fill();
        }
        c.fillStyle = 'rgba(255, 245, 220, .4)';
        c.beginPath(); c.ellipse(-8, -4, 5.5, 1.4, -0.7, 0, Math.PI * 2); c.fill();
        c.beginPath(); c.ellipse(7, 3, 3.5, 1, -0.7, 0, Math.PI * 2); c.fill();
        c.restore();
        c.strokeStyle = 'rgba(255, 210, 140, .75)'; c.lineWidth = 1;
        c.beginPath(); c.moveTo(L[0], L[1]); c.lineTo(F[0], F[1]); c.lineTo(R[0], R[1]); c.stroke();
        // badam
        c.save();
        c.translate(0, -1); c.rotate(0.95);
        softShadow(c, 0.8, 1, 4.6, 3.2, 0.4);
        const al = c.createRadialGradient(-1, -1.5, 0.5, 0, 0, 6);
        al.addColorStop(0, '#fbecca'); al.addColorStop(0.6, '#e2bd86'); al.addColorStop(1, '#a8743c');
        c.fillStyle = al;
        c.beginPath(); c.ellipse(0, 0, 5.4, 3.2, 0, 0, Math.PI * 2); c.fill();
        c.strokeStyle = 'rgba(120, 70, 30, .55)'; c.lineWidth = 0.6; c.stroke();
        c.fillStyle = 'rgba(255, 255, 255, .7)';
        c.beginPath(); c.ellipse(-1.8, -1, 1.6, 0.6, 0, 0, Math.PI * 2); c.fill();
        c.restore();
    }

    // ---- şəkərbura: yarımay, açıq qızılı xəmir, kənarı çimdikli, üstündə maşa ilə vurulmuş naxış ----
    function paintShekerbura(c) {
        const R = 20, by = 6;
        const shape = () => { c.beginPath(); c.arc(0, by, R, Math.PI, 0); c.quadraticCurveTo(0, by + 3, -R, by); c.closePath(); };
        softShadow(c, 0, by + 6, 21, 4);
        c.save(); c.translate(0, 3); shape(); c.fillStyle = '#c4934f'; c.fill(); c.restore(); // qalınlıq
        shape();
        const g = c.createRadialGradient(-3, 0, 2, 0, by - 4, R + 3);
        g.addColorStop(0, '#fdedc6'); g.addColorStop(0.65, '#f0d090'); g.addColorStop(1, '#d6a058');
        c.fillStyle = g; c.fill();
        c.save(); c.clip();
        c.strokeStyle = 'rgba(185, 112, 38, .35)'; c.lineWidth = 5;
        c.beginPath(); c.arc(0, by, R, Math.PI, 0); c.stroke(); // kənarlar azca qızarıb
        // maşa naxışı: qövs boyunca balıq sümüyü
        for (let a = Math.PI + 0.42; a < Math.PI * 2 - 0.4; a += 0.21) {
            for (const rr of [8.5, 12.5]) {
                const px = Math.cos(a) * rr, py = by + Math.sin(a) * rr;
                const tx = -Math.sin(a), ty = Math.cos(a); // qövsün istiqaməti
                const nx = Math.cos(a), ny = Math.sin(a);  // mərkəzdən çölə
                for (const sd of [-1, 1]) {
                    const ex = px + (tx * 1.6 + nx * sd * 1.6), ey = py + (ty * 1.6 + ny * sd * 1.6);
                    c.strokeStyle = 'rgba(150, 88, 30, .75)'; c.lineWidth = 1.1;
                    c.beginPath(); c.moveTo(px, py); c.lineTo(ex, ey); c.stroke();
                    c.strokeStyle = 'rgba(255, 246, 220, .75)'; c.lineWidth = 0.6;
                    c.beginPath(); c.moveTo(px + 0.6, py - 0.4); c.lineTo(ex + 0.6, ey - 0.4); c.stroke();
                }
            }
        }
        const sheen = c.createRadialGradient(-7, -4, 1, -7, -4, 12);
        sheen.addColorStop(0, 'rgba(255, 255, 255, .3)'); sheen.addColorStop(1, 'rgba(255, 255, 255, 0)');
        c.fillStyle = sheen; c.fillRect(-R, -R, R * 2, R * 2);
        c.restore();
        // çimdikli kənar
        for (let a = Math.PI + 0.07; a < Math.PI * 2 - 0.05; a += 0.15) {
            const ox = Math.cos(a) * R, oy = by + Math.sin(a) * R;
            const ix = Math.cos(a + 0.07) * (R - 4.2), iy = by + Math.sin(a + 0.07) * (R - 4.2);
            c.strokeStyle = 'rgba(150, 90, 32, .7)'; c.lineWidth = 1.2;
            c.beginPath(); c.moveTo(ox, oy); c.lineTo(ix, iy); c.stroke();
            c.strokeStyle = 'rgba(255, 244, 212, .8)'; c.lineWidth = 0.7;
            c.beginPath(); c.moveTo(ox + 0.7, oy + 0.2); c.lineTo(ix + 0.7, iy + 0.2); c.stroke();
        }
        for (let x = -R + 2; x < R - 1; x += 3.1) {
            c.strokeStyle = 'rgba(150, 90, 32, .6)'; c.lineWidth = 1;
            c.beginPath(); c.moveTo(x, by + 1); c.lineTo(x + 1.3, by - 2.6); c.stroke();
        }
    }

    // ---- qənd: kəllə qənddən sınmış kələ-kötür parça, kristal dənəli ----
    function paintQend(c) {
        softShadow(c, 1, 17, 15, 3.5);
        const A = [-13, -6], Bq = [1, -14], Cq = [14, -6], D = [0, 1], A2 = [-12, 9], D2 = [1, 16], C2 = [13, 8];
        const faces = [[[A, Bq, Cq, D], '#fdfcf8'], [[A, D, D2, A2], '#e7e2d9'], [[D, Cq, C2, D2], '#d1cbc0']];
        const r = seeded(77);
        for (const [pts, col] of faces) {
            poly(c, pts); c.fillStyle = col; c.fill();
            c.save(); c.clip();
            for (let i = 0; i < 110; i++) { // dənəlik
                c.fillStyle = r() < 0.55 ? `rgba(255, 255, 255, ${0.4 + r() * 0.5})` : `rgba(150, 142, 128, ${0.12 + r() * 0.25})`;
                c.fillRect(-14 + r() * 28, -15 + r() * 32, 0.6 + r() * 0.9, 0.6 + r() * 0.9);
            }
            c.restore();
        }
        poly(c, [[10, -8], [14, -6], [13.5, -1], [9.5, -3]]); // sınmış künc
        c.fillStyle = '#c4bdb0'; c.fill();
        c.strokeStyle = 'rgba(255, 255, 255, .95)'; c.lineWidth = 0.8;
        c.beginPath(); c.moveTo(A[0], A[1]); c.lineTo(Bq[0], Bq[1]); c.lineTo(Cq[0], Cq[1]); c.stroke();
        c.strokeStyle = 'rgba(160, 152, 140, .5)';
        c.beginPath(); c.moveTo(A[0], A[1]); c.lineTo(D[0], D[1]); c.lineTo(Cq[0], Cq[1]); c.moveTo(D[0], D[1]); c.lineTo(D2[0], D2[1]); c.stroke();
        c.fillStyle = '#fff';
        for (const [x, y] of [[-5, -7], [4, -9], [-7, 5], [6, 3]]) { // kristal parıltıları
            c.beginPath(); c.moveTo(x, y - 1.8); c.lineTo(x + 0.4, y); c.lineTo(x, y + 1.8); c.lineTo(x - 0.4, y); c.closePath(); c.fill();
            c.beginPath(); c.moveTo(x - 1.8, y); c.lineTo(x, y + 0.4); c.lineTo(x + 1.8, y); c.lineTo(x, y - 0.4); c.closePath(); c.fill();
        }
    }

    // ---- limon dilimi: qabıq, ağ lif qatı, şirəli dilimlər, toxum ----
    function paintLimon(c) {
        softShadow(c, 0, 17, 17, 3.5);
        c.save();
        c.scale(1, 0.9);
        const peel = c.createRadialGradient(-5, -5, 2, 0, 0, 17);
        peel.addColorStop(0, '#ffeb5a'); peel.addColorStop(0.85, '#f1c20c'); peel.addColorStop(1, '#c48f00');
        c.fillStyle = peel;
        c.beginPath(); c.arc(0, 0, 17, 0, Math.PI * 2); c.fill();
        const r = seeded(19);
        for (let i = 0; i < 60; i++) { // qabığın məsamələri
            const a = r() * Math.PI * 2, rr = 15.6 + r() * 1.2;
            c.fillStyle = `rgba(180, 125, 0, ${0.2 + r() * 0.3})`;
            c.beginPath(); c.arc(Math.cos(a) * rr, Math.sin(a) * rr, 0.35, 0, Math.PI * 2); c.fill();
        }
        c.fillStyle = '#fbf6dd';
        c.beginPath(); c.arc(0, 0, 15.2, 0, Math.PI * 2); c.fill();
        const n = 10;
        for (let i = 0; i < n; i++) {
            const a0 = i * Math.PI * 2 / n + 0.05, a1 = (i + 1) * Math.PI * 2 / n - 0.05, am = (a0 + a1) / 2;
            c.beginPath();
            c.moveTo(Math.cos(am) * 2.4, Math.sin(am) * 2.4);
            c.arc(0, 0, 13.8, a0, a1);
            c.closePath();
            const pulp = c.createRadialGradient(0, 0, 2, 0, 0, 14);
            pulp.addColorStop(0, '#fff7bd'); pulp.addColorStop(0.65, '#f9de52'); pulp.addColorStop(1, '#efc72a');
            c.fillStyle = pulp; c.fill();
            c.save(); c.clip();
            for (let k = 0; k < 7; k++) { // şirə kisəcikləri
                const aa = a0 + r() * (a1 - a0), rr = 4 + r() * 9;
                c.save();
                c.translate(Math.cos(aa) * rr, Math.sin(aa) * rr); c.rotate(aa);
                c.fillStyle = 'rgba(255, 255, 228, .55)';
                c.beginPath(); c.ellipse(0, 0, 1.9, 0.5, 0, 0, Math.PI * 2); c.fill();
                c.restore();
            }
            c.restore();
        }
        c.fillStyle = '#fbf3d0';
        c.beginPath(); c.arc(0, 0, 2.2, 0, Math.PI * 2); c.fill();
        for (const [a, rr] of [[0.9, 7], [3.6, 6]]) { // toxumlar
            c.save(); c.translate(Math.cos(a) * rr, Math.sin(a) * rr); c.rotate(a);
            c.fillStyle = '#f4ecd2'; c.strokeStyle = 'rgba(170, 150, 100, .7)'; c.lineWidth = 0.5;
            c.beginPath(); c.ellipse(0, 0, 2.2, 1.1, 0, 0, Math.PI * 2); c.fill(); c.stroke();
            c.restore();
        }
        c.strokeStyle = 'rgba(255, 255, 255, .6)'; c.lineWidth = 1.4; c.lineCap = 'round';
        c.beginPath(); c.arc(0, 0, 16.2, Math.PI * 1.05, Math.PI * 1.45); c.stroke();
        c.fillStyle = 'rgba(255, 255, 255, .7)';
        for (const [x, y] of [[-5, -6], [4, -3], [-2, 6]]) { c.beginPath(); c.arc(x, y, 0.8, 0, Math.PI * 2); c.fill(); }
        c.restore();
    }

    // ---- şokolad: yarısı açılmış lövhə — tünd şokolad dilimləri, qızılı folqa və qırmızı kağız qab ----
    function paintShokolad(c) {
        softShadow(c, 1, 15, 19, 3.5);
        c.save();
        c.rotate(-0.18);
        const L = -18, R = 18, T = -11, B = 11, cut = 3; // lövhə; sağ tərəfi kağızın içindədir
        // lövhənin qalınlığı (aşağı kənar)
        c.fillStyle = '#2a140a';
        c.beginPath(); c.roundRect(L, T + 2.5, R - L, B - T, 2.5); c.fill();
        // dilimlər: 3 × 2, hər birinin kənarları işıqlı-kölgəli (qabarıq)
        const cols = 3, rows = 2, gap = 1.2;
        const cw = (cut - L - gap * cols) / cols, rh = (B - T - gap * rows) / rows;
        c.fillStyle = '#3a1c0e';
        c.beginPath(); c.roundRect(L, T, cut - L, B - T, 2.5); c.fill();
        for (let i = 0; i < cols; i++) {
            for (let j = 0; j < rows; j++) {
                const x = L + gap / 2 + i * (cw + gap), y = T + gap / 2 + j * (rh + gap);
                const g = c.createLinearGradient(x, y, x + cw, y + rh);
                g.addColorStop(0, '#8a4a26'); g.addColorStop(0.5, '#5e2f17'); g.addColorStop(1, '#40200f');
                c.fillStyle = g;
                c.beginPath(); c.roundRect(x, y, cw, rh, 1.5); c.fill();
                c.fillStyle = 'rgba(255, 210, 170, .35)';                 // yuxarı-sol kənar işıq tutur
                c.fillRect(x + 1, y + 0.8, cw - 2, 0.9);
                c.fillRect(x + 0.8, y + 1, 0.9, rh - 2);
                c.fillStyle = 'rgba(0, 0, 0, .3)';                        // aşağı-sağ kənar kölgədə
                c.fillRect(x + 1, y + rh - 1.6, cw - 2, 0.9);
            }
        }
        // qızılı folqa: kağızın kənarından çıxıb azca qırışır
        c.beginPath();
        c.moveTo(cut - 1, T - 1.5); c.lineTo(cut + 3, T - 2.5); c.lineTo(cut + 2, -3); c.lineTo(cut + 4, 2); c.lineTo(cut + 2.5, B + 2); c.lineTo(cut - 1, B + 1.5);
        c.closePath();
        const foil = c.createLinearGradient(cut - 1, T, cut + 4, B);
        foil.addColorStop(0, '#fff2b8'); foil.addColorStop(0.35, '#d9a72c'); foil.addColorStop(0.6, '#fff6cc'); foil.addColorStop(1, '#a8781a');
        c.fillStyle = foil; c.fill();
        // qırmızı kağız qab, üstündə qızılı zolaq
        c.beginPath(); c.roundRect(cut + 2, T - 2.5, R + 2 - cut, B - T + 5, 2.5);
        const paper = c.createLinearGradient(0, T - 2.5, 0, B + 2.5);
        paper.addColorStop(0, '#c8262c'); paper.addColorStop(0.5, '#a5141c'); paper.addColorStop(1, '#6e0a10');
        c.fillStyle = paper; c.fill();
        c.fillStyle = '#e9b847';
        c.fillRect(cut + 2, -2.2, R + 2 - cut, 4.4);
        c.fillStyle = 'rgba(255, 245, 210, .55)';
        c.fillRect(cut + 2, -2.2, R + 2 - cut, 0.8);
        c.strokeStyle = 'rgba(255, 255, 255, .25)'; c.lineWidth = 0.8;
        c.beginPath(); c.moveTo(cut + 4, T - 1); c.lineTo(R, T - 1); c.stroke();
        c.restore();
    }

    // ---- qoz: qabıqlı qoz, qırışlı səth, ortasında tikiş ----
    function paintQoz(c) {
        softShadow(c, 0, 19, 15, 3.5);
        const shape = () => {
            c.beginPath();
            c.moveTo(0, -17.5);
            c.bezierCurveTo(10, -16.5, 15, -6, 14.2, 3);
            c.bezierCurveTo(13.5, 12, 6.5, 17.5, 0, 17.5);
            c.bezierCurveTo(-6.5, 17.5, -13.5, 12, -14.2, 3);
            c.bezierCurveTo(-15, -6, -10, -16.5, 0, -17.5);
            c.closePath();
        };
        shape();
        // qabıq: tünd əsasın üstündə kələ-kötür qabarıqlar; aralarında qalan tünd yerlər dərin şırımlardır
        c.fillStyle = '#5e3a1a'; c.fill();
        c.save(); c.clip();
        const r = seeded(53);
        for (let gy = -18; gy <= 18; gy += 4.4) {
            for (let gx = -15; gx <= 15; gx += 5.6) {
                const cx = gx + (r() - 0.5) * 3, cy = gy + (r() - 0.5) * 2.4;
                const rad = 3 + r() * 1.6, n = 9, pts = [];
                for (let k = 0; k < n; k++) { // kələ-kötür, üfüqi uzunsov qabarıq
                    const a = k / n * Math.PI * 2 + r() * 0.4, rr = rad * (0.55 + r() * 0.7);
                    pts.push([cx + Math.cos(a) * rr * 1.35, cy + Math.sin(a) * rr * 0.85]);
                }
                // qabarığın rəngi işığa görə: sol-yuxarı açıq, sağ-aşağı tünd
                const light = Math.max(0, Math.min(1, 0.55 - (cx + cy) / 45));
                const bump = c.createRadialGradient(cx - 1, cy - 1, 0.3, cx, cy, rad * 1.5);
                bump.addColorStop(0, `rgb(${Math.round(180 + light * 40)}, ${Math.round(132 + light * 35)}, ${Math.round(84 + light * 30)})`);
                bump.addColorStop(1, `rgb(${Math.round(128 + light * 25)}, ${Math.round(86 + light * 20)}, ${Math.round(48 + light * 15)})`);
                c.fillStyle = bump;
                c.beginPath(); c.moveTo(pts[0][0], pts[0][1]);
                for (let k = 1; k <= n; k++) {
                    const p = pts[k % n], q = pts[(k + 1) % n];
                    c.quadraticCurveTo(p[0], p[1], (p[0] + q[0]) / 2, (p[1] + q[1]) / 2);
                }
                c.fill();
            }
        }
        const shade = c.createRadialGradient(-5, -7, 4, 0, 0, 20); // kürə kimi işıq-kölgə
        shade.addColorStop(0, 'rgba(255, 230, 190, .18)'); shade.addColorStop(0.6, 'rgba(0, 0, 0, 0)'); shade.addColorStop(1, 'rgba(40, 18, 4, .55)');
        c.fillStyle = shade; c.fillRect(-16, -19, 32, 38);
        c.strokeStyle = 'rgba(55, 28, 8, .4)'; c.lineWidth = 3; shape(); c.stroke(); // kənarlar tünd
        c.restore();
        c.strokeStyle = 'rgba(65, 34, 12, .75)'; c.lineWidth = 2; c.lineCap = 'round';
        c.beginPath(); c.moveTo(0, -17); c.bezierCurveTo(1.5, -6, -1.5, 6, 0.5, 17); c.stroke(); // tikiş
        c.strokeStyle = 'rgba(238, 205, 160, .6)'; c.lineWidth = 0.9;
        c.beginPath(); c.moveTo(1.3, -16); c.bezierCurveTo(2.8, -6, -0.2, 6, 1.8, 16); c.stroke();
        const hl = c.createRadialGradient(-6, -8, 0.5, -6, -8, 7);
        hl.addColorStop(0, 'rgba(255, 238, 205, .35)'); hl.addColorStop(1, 'rgba(255, 238, 205, 0)');
        c.fillStyle = hl; c.beginPath(); c.arc(-6, -8, 7, 0, Math.PI * 2); c.fill();
    }

    // ---- armudu stəkanda çay: Stəkan qülləsindəki stəkanın eynisi (public/js/games/stack.js, drawGlassFull) ----
    const GW = 44, GH = 61, RIM_RY = 3.5, RIM_W = 0.44, TEA_SURFACE_W = 0.3;
    function glassPath(w, h) {
        const p = new Path2D();
        const b = 0.26 * w;
        p.moveTo(-b, 0);
        p.bezierCurveTo(-0.46 * w, -0.03 * h, -0.5 * w, -0.36 * h, -0.33 * w, -0.58 * h);
        p.bezierCurveTo(-0.28 * w, -0.66 * h, -0.31 * w, -0.86 * h, -RIM_W * w, -h);
        p.ellipse(0, -h, RIM_W * w, RIM_RY, 0, Math.PI, 0, true);
        p.bezierCurveTo(0.31 * w, -0.86 * h, 0.28 * w, -0.66 * h, 0.33 * w, -0.58 * h);
        p.bezierCurveTo(0.5 * w, -0.36 * h, 0.46 * w, -0.03 * h, b, 0);
        p.closePath();
        return p;
    }
    const GLASS = glassPath(GW, GH);
    function drawOrnament(c, ox, k, a) {
        const top = -GH * 0.8, bot = -GH * 0.2, mid = (top + bot) / 2, hw = GW * 0.17 * k;
        const ogee = (s) => {
            const p = new Path2D();
            const t = mid + (top - mid) * s, b = mid + (bot - mid) * s, w = hw * s;
            p.moveTo(ox, t);
            p.bezierCurveTo(ox + w * 0.2, t + (mid - t) * 0.25, ox + w * 1.25, t + (mid - t) * 0.45, ox + w, mid);
            p.bezierCurveTo(ox + w * 1.25, b - (b - mid) * 0.45, ox + w * 0.2, b - (b - mid) * 0.25, ox, b);
            p.bezierCurveTo(ox - w * 0.2, b - (b - mid) * 0.25, ox - w * 1.25, b - (b - mid) * 0.45, ox - w, mid);
            p.bezierCurveTo(ox - w * 1.25, t + (mid - t) * 0.45, ox - w * 0.2, t + (mid - t) * 0.25, ox, t);
            p.closePath();
            return p;
        };
        c.save();
        c.globalAlpha *= a * 0.55;
        c.strokeStyle = '#d9a03e';
        c.fillStyle = '#d9a03e';
        c.lineCap = 'round';
        c.lineWidth = 1.4;
        c.stroke(ogee(1));
        c.lineWidth = 0.7;
        const inner = ogee(0.62);
        c.stroke(inner);
        c.save();
        c.clip(inner);
        c.lineWidth = 0.5;
        c.beginPath();
        for (let d = -GH; d <= GH; d += 3.2) {
            c.moveTo(ox - GW, mid + d - GW); c.lineTo(ox + GW, mid + d + GW);
            c.moveTo(ox - GW, mid + d + GW); c.lineTo(ox + GW, mid + d - GW);
        }
        c.stroke();
        c.restore();
        c.lineWidth = 0.8;
        for (const sd of [-1, 1]) {
            c.beginPath();
            c.arc(ox + sd * hw * 0.95, mid, hw * 0.28, 0, Math.PI * 2);
            c.stroke();
        }
        for (const fy of [top - 3.2 * k, bot + 3.2 * k]) {
            for (let i = 0; i < 5; i++) {
                const ang = i * Math.PI * 2 / 5 - Math.PI / 2;
                c.beginPath();
                c.arc(ox + Math.cos(ang) * 1.5 * k, fy + Math.sin(ang) * 1.5 * k, 0.9 * k, 0, Math.PI * 2);
                c.fill();
            }
        }
        c.restore();
    }
    function drawGlassBase(c) {
        const rx = GW * 0.27, ry = 2.6, T = 5;
        const sh = c.createRadialGradient(0, 1, 1, 0, 1, rx * 1.25);
        sh.addColorStop(0, 'rgba(0, 0, 0, .35)');
        sh.addColorStop(1, 'rgba(0, 0, 0, 0)');
        c.save();
        c.scale(1, 0.22);
        c.fillStyle = sh;
        c.beginPath(); c.arc(0, 1 / 0.22, rx * 1.25, 0, Math.PI * 2); c.fill();
        c.restore();
        const side = new Path2D();
        side.moveTo(-rx, -T);
        side.lineTo(-rx, 0);
        side.ellipse(0, 0, rx, ry, 0, Math.PI, 0, true);
        side.lineTo(rx, -T);
        side.ellipse(0, -T, rx, ry, 0, 0, Math.PI, false);
        side.closePath();
        const body = c.createLinearGradient(-rx, 0, rx, 0);
        body.addColorStop(0, 'rgba(235, 245, 248, .55)');
        body.addColorStop(0.22, 'rgba(200, 95, 45, .55)');
        body.addColorStop(0.5, 'rgba(230, 120, 55, .5)');
        body.addColorStop(0.78, 'rgba(150, 40, 12, .6)');
        body.addColorStop(1, 'rgba(200, 220, 228, .5)');
        c.fillStyle = body;
        c.fill(side);
        const depth = c.createLinearGradient(0, -T - ry, 0, ry);
        depth.addColorStop(0, 'rgba(255, 255, 255, .25)');
        depth.addColorStop(1, 'rgba(40, 10, 0, .25)');
        c.fillStyle = depth;
        c.fill(side);
        c.strokeStyle = 'rgba(255, 225, 190, .55)';
        c.lineWidth = 0.7;
        c.beginPath(); c.ellipse(0, -T, rx, ry, 0, 0, Math.PI); c.stroke();
        c.lineWidth = 1;
        c.strokeStyle = 'rgba(255, 255, 255, .85)';
        c.beginPath(); c.moveTo(-rx + 0.5, -T); c.lineTo(-rx + 0.5, 0); c.stroke();
        c.strokeStyle = 'rgba(90, 40, 20, .5)';
        c.beginPath(); c.moveTo(rx - 0.5, -T); c.lineTo(rx - 0.5, 0); c.stroke();
        c.strokeStyle = '#E9A23B';
        c.lineWidth = 1.1;
        c.beginPath(); c.ellipse(0, -T * 0.45, rx, ry, 0, 0, Math.PI); c.stroke();
        c.strokeStyle = 'rgba(255, 255, 255, .9)';
        c.lineWidth = 1.2;
        c.beginPath(); c.ellipse(0, 0, rx, ry, 0, Math.PI * 0.15, Math.PI * 0.85); c.stroke();
        c.fillStyle = 'rgba(255, 255, 255, .8)';
        c.beginPath(); c.ellipse(-rx * 0.55, -T * 0.55, 1.4, 0.8, 0, 0, Math.PI * 2); c.fill();
    }
    function paintGlass(c) {
        c.translate(0, GH / 2); // spraytın mərkəzi stəkanın ortasıdır, stəkanın dibi isə (0, 0)-da çəkilir
        c.fillStyle = 'rgba(255, 255, 255, .22)';
        c.fill(GLASS);
        c.save();
        c.clip(GLASS);
        const teaTop = -GH * 0.74;
        const tea = c.createLinearGradient(0, teaTop, 0, 0);
        tea.addColorStop(0, 'rgba(40, 5, 1, .98)');
        tea.addColorStop(0.25, 'rgba(88, 14, 2, .97)');
        tea.addColorStop(0.55, 'rgba(156, 40, 7, .95)');
        tea.addColorStop(0.82, 'rgba(208, 94, 22, .93)');
        tea.addColorStop(1, 'rgba(236, 146, 52, .92)');
        c.fillStyle = tea;
        c.fillRect(-GW, teaTop, GW * 2, -teaTop + 2);
        const glow = c.createRadialGradient(-GW * 0.08, -GH * 0.2, 2, -GW * 0.08, -GH * 0.2, GW * 0.36);
        glow.addColorStop(0, 'rgba(255, 160, 60, .16)');
        glow.addColorStop(1, 'rgba(255, 170, 70, 0)');
        c.fillStyle = glow;
        c.fillRect(-GW, teaTop, GW * 2, -teaTop + 2);
        c.save();
        c.translate(0, teaTop);
        const srx = GW * TEA_SURFACE_W, sry = RIM_RY * 0.85;
        const surf = c.createLinearGradient(0, -sry, 0, sry);
        surf.addColorStop(0, 'rgba(58, 12, 3, .96)');
        surf.addColorStop(1, 'rgba(150, 52, 16, .95)');
        c.fillStyle = surf;
        c.beginPath(); c.ellipse(0, 0, srx, sry, 0, 0, Math.PI * 2); c.fill();
        c.strokeStyle = 'rgba(230, 150, 80, .55)';
        c.lineWidth = 0.7;
        c.stroke();
        c.fillStyle = 'rgba(255, 230, 200, .22)';
        c.beginPath(); c.ellipse(-srx * 0.3, -sry * 0.1, srx * 0.38, sry * 0.32, 0, 0, Math.PI * 2); c.fill();
        c.restore();
        drawOrnament(c, 0, 1, 1);
        drawOrnament(c, -GW * 0.4, 0.62, 0.45);
        drawOrnament(c, GW * 0.4, 0.62, 0.45);
        c.restore();
        c.lineWidth = 2;
        c.strokeStyle = 'rgba(255, 255, 255, .95)';
        c.stroke(GLASS);
        const rw = GW * RIM_W;
        c.fillStyle = 'rgba(255, 255, 255, .18)';
        c.beginPath(); c.ellipse(0, -GH, rw, RIM_RY, 0, 0, Math.PI * 2); c.fill();
        c.strokeStyle = 'rgba(233, 162, 59, .75)';
        c.lineWidth = 1.2;
        c.beginPath(); c.ellipse(0, -GH, rw, RIM_RY, 0, Math.PI, Math.PI * 2); c.stroke();
        c.strokeStyle = '#E9A23B';
        c.lineWidth = 2.2;
        c.beginPath(); c.ellipse(0, -GH, rw, RIM_RY, 0, 0, Math.PI); c.stroke();
        drawGlassBase(c);
        c.strokeStyle = 'rgba(255, 255, 255, .7)';
        c.lineWidth = 3;
        c.lineCap = 'round';
        c.beginPath();
        c.moveTo(-GW * 0.33, -GH * 0.18);
        c.quadraticCurveTo(-GW * 0.4, -GH * 0.38, -GW * 0.28, -GH * 0.56);
        c.stroke();
    }

    // ---- qızıl çaynik: cilalı qızıl metal, əkslər, oyma naxışlı qurşaq ----
    const metal = (c, x0, x1) => {
        const g = c.createLinearGradient(x0, 0, x1, 0);
        g.addColorStop(0, '#5a3a00'); g.addColorStop(0.13, '#a87512'); g.addColorStop(0.3, '#f6d97e');
        g.addColorStop(0.38, '#fffbe4'); g.addColorStop(0.48, '#e5b73a'); g.addColorStop(0.7, '#966508');
        g.addColorStop(0.86, '#d9a833'); g.addColorStop(1, '#6a4500');
        return g;
    };
    function paintTeapot(c) {
        softShadow(c, 0, 23, 20, 4.5);
        // qulp (gövdənin arxasında)
        c.lineCap = 'round';
        c.strokeStyle = '#8a5d06'; c.lineWidth = 4;
        c.beginPath(); c.moveTo(13, -3); c.bezierCurveTo(26, -6, 27, 13, 14, 14); c.stroke();
        c.strokeStyle = '#e8bd52'; c.lineWidth = 2;
        c.beginPath(); c.moveTo(13.5, -3.5); c.bezierCurveTo(25, -6, 25.5, 12, 14, 13); c.stroke();
        c.strokeStyle = 'rgba(255, 250, 220, .8)'; c.lineWidth = 0.7;
        c.beginPath(); c.moveTo(15, -4.5); c.bezierCurveTo(23, -6, 24.5, 4, 23.5, 7); c.stroke();
        // lülə
        // lülə: gövdədən çıxan yerdə enli, ucuna doğru daralıb yuxarı əyilir
        c.beginPath();
        c.moveTo(-12, -4);
        c.bezierCurveTo(-21, -5, -25, -9, -28, -16);   // üst kənar
        c.lineTo(-23.5, -17.5);                        // uc
        c.bezierCurveTo(-24, -6, -20, 4, -13, 10);     // alt kənar: gövdəyə birləşən yerdə enli
        c.closePath();
        c.fillStyle = metal(c, -28, -10); c.fill();
        c.strokeStyle = 'rgba(90, 55, 0, .6)'; c.lineWidth = 0.6; c.stroke();
        c.strokeStyle = 'rgba(255, 248, 215, .75)'; c.lineWidth = 0.8;
        c.beginPath(); c.moveTo(-15, -4); c.bezierCurveTo(-21, -5.5, -24, -9, -26.5, -14.5); c.stroke();
        c.fillStyle = '#3e2700';
        c.beginPath(); c.ellipse(-25.8, -16.8, 2.4, 1, -0.33, 0, Math.PI * 2); c.fill();
        c.strokeStyle = '#e8bd52'; c.lineWidth = 0.8; c.stroke();
        // altlıq
        c.fillStyle = metal(c, -11, 11);
        c.beginPath(); c.ellipse(0, 19.5, 10.5, 3, 0, 0, Math.PI * 2); c.fill();
        // gövdə
        const body = new Path2D();
        body.moveTo(-10, -6);
        body.bezierCurveTo(-19, -4, -21, 13, -9, 18.5);
        body.lineTo(9, 18.5);
        body.bezierCurveTo(21, 13, 19, -4, 10, -6);
        body.closePath();
        c.fillStyle = metal(c, -20, 20); c.fill(body);
        c.save(); c.clip(body);
        const vg = c.createLinearGradient(0, -6, 0, 19);
        vg.addColorStop(0, 'rgba(255, 255, 255, .15)'); vg.addColorStop(0.6, 'rgba(0, 0, 0, 0)'); vg.addColorStop(1, 'rgba(60, 25, 0, .4)');
        c.fillStyle = vg; c.fillRect(-21, -7, 42, 27);
        c.fillStyle = 'rgba(110, 25, 10, .22)'; // otağın (şərab rəngli divarın) əksi
        c.beginPath(); c.ellipse(4, 13, 15, 4, 0, 0, Math.PI * 2); c.fill();
        // oyma naxışlı qurşaq
        for (const [y, col, w] of [[4, 'rgba(105, 65, 0, .7)', 0.9], [4.8, 'rgba(255, 244, 200, .75)', 0.5], [8.5, 'rgba(105, 65, 0, .7)', 0.9], [9.3, 'rgba(255, 244, 200, .75)', 0.5]]) {
            c.strokeStyle = col; c.lineWidth = w;
            c.beginPath(); c.moveTo(-20, y); c.quadraticCurveTo(0, y + 4, 20, y); c.stroke();
        }
        c.fillStyle = 'rgba(105, 65, 0, .65)';
        for (let x = -15; x <= 15; x += 3.4) {
            const yy = 6.4 + (1 - (x / 20) ** 2) * 2;
            c.beginPath(); c.moveTo(x, yy - 1.2); c.lineTo(x + 1.1, yy); c.lineTo(x, yy + 1.2); c.lineTo(x - 1.1, yy); c.closePath(); c.fill();
        }
        const spec = c.createRadialGradient(-7, 1, 0.5, -7, 1, 7);
        spec.addColorStop(0, 'rgba(255, 255, 255, .9)'); spec.addColorStop(1, 'rgba(255, 255, 255, 0)');
        c.fillStyle = spec; c.beginPath(); c.arc(-7, 1, 7, 0, Math.PI * 2); c.fill();
        c.restore();
        c.strokeStyle = 'rgba(80, 50, 0, .55)'; c.lineWidth = 0.7; c.stroke(body);
        // qapaq
        c.fillStyle = '#9c6c0c';
        c.beginPath(); c.ellipse(0, -6, 10.5, 2.9, 0, 0, Math.PI * 2); c.fill();
        c.beginPath();
        c.moveTo(-9, -6.5); c.bezierCurveTo(-8, -15, 8, -15, 9, -6.5); c.closePath();
        c.fillStyle = metal(c, -9, 9); c.fill();
        c.strokeStyle = 'rgba(255, 245, 205, .85)'; c.lineWidth = 0.7;
        c.beginPath(); c.ellipse(0, -6, 10.5, 2.9, 0, Math.PI, Math.PI * 2); c.stroke();
        const knob = c.createRadialGradient(-1, -16, 0.3, 0, -15, 3.2);
        knob.addColorStop(0, '#fffbe6'); knob.addColorStop(0.5, '#e6b53a'); knob.addColorStop(1, '#7a5200');
        c.fillStyle = knob;
        c.beginPath(); c.arc(0, -15, 3, 0, Math.PI * 2); c.fill();
    }

    // ---- qəlyan: rəngli şüşə kolba (içində su), mis gövdə və bəzək halqaları, külqabı, gil başlıq, şlanq ----
    const brass = (c, x0, x1) => {
        const g = c.createLinearGradient(x0, 0, x1, 0);
        g.addColorStop(0, '#5a3a08'); g.addColorStop(0.3, '#c9973a'); g.addColorStop(0.45, '#fbe3a2');
        g.addColorStop(0.6, '#b8842c'); g.addColorStop(1, '#4e3206');
        return g;
    };
    function paintHookah(c) {
        softShadow(c, 0, 47, 20, 4.5);
        // şlanq: gövdənin yanından çıxır, aşağı sallanıb yerə qıvrılır
        c.lineCap = 'round';
        const hose = () => { c.beginPath(); c.moveTo(4, 4); c.bezierCurveTo(26, 0, 30, 22, 22, 34); c.bezierCurveTo(16, 43, 26, 47, 31, 42); };
        c.strokeStyle = '#2a1a12'; c.lineWidth = 4.2; hose(); c.stroke();
        c.strokeStyle = '#6b4a35'; c.lineWidth = 2.6; hose(); c.stroke();
        c.setLineDash([1.2, 2]); c.strokeStyle = 'rgba(230, 200, 150, .55)'; c.lineWidth = 1; hose(); c.stroke(); c.setLineDash([]);
        c.save(); c.translate(31, 42); c.rotate(-0.7); // ağızlıq
        c.fillStyle = brass(c, -2, 2); c.fillRect(-1.6, -7, 3.2, 8);
        c.fillStyle = '#e8d6a8'; c.beginPath(); c.ellipse(0, -7.5, 1.4, 0.8, 0, 0, Math.PI * 2); c.fill();
        c.restore();
        // şüşə kolba
        const vase = new Path2D();
        vase.moveTo(-4.5, 13);
        vase.bezierCurveTo(-5, 20, -19, 24, -18, 35);
        vase.bezierCurveTo(-17.5, 43, -10, 45.5, 0, 45.5);
        vase.bezierCurveTo(10, 45.5, 17.5, 43, 18, 35);
        vase.bezierCurveTo(19, 24, 5, 20, 4.5, 13);
        vase.closePath();
        const glass = c.createLinearGradient(-18, 0, 18, 0);
        glass.addColorStop(0, 'rgba(10, 70, 75, .95)'); glass.addColorStop(0.35, 'rgba(40, 150, 150, .85)');
        glass.addColorStop(0.6, 'rgba(20, 105, 110, .9)'); glass.addColorStop(1, 'rgba(6, 45, 50, .95)');
        c.fillStyle = glass; c.fill(vase);
        c.save(); c.clip(vase);
        c.fillStyle = 'rgba(4, 35, 40, .55)'; c.fillRect(-20, 31, 40, 16); // su
        c.strokeStyle = 'rgba(160, 235, 230, .6)'; c.lineWidth = 0.8;
        c.beginPath(); c.ellipse(0, 31, 17, 2.2, 0, 0, Math.PI * 2); c.stroke(); // suyun səthi
        c.fillStyle = 'rgba(200, 245, 240, .5)';
        for (const [x, y, r] of [[-1, 38, 1.1], [2, 34.5, 0.8], [-3, 42, 0.7], [1.5, 40.5, 0.6]]) { c.beginPath(); c.arc(x, y, r, 0, Math.PI * 2); c.fill(); } // qabarcıqlar
        c.fillStyle = 'rgba(70, 50, 30, .5)'; c.fillRect(-1.2, 13, 2.4, 25); // suya enən boru
        c.restore();
        c.strokeStyle = 'rgba(255, 255, 255, .55)'; c.lineWidth = 1.6;
        c.beginPath(); c.moveTo(-12, 27); c.quadraticCurveTo(-15.5, 33, -13, 40); c.stroke(); // şüşədə parıltı
        c.strokeStyle = 'rgba(210, 180, 110, .7)'; c.lineWidth = 0.8;
        c.beginPath(); c.ellipse(0, 31, 16.5, 2, 0, 0, Math.PI); c.stroke(); // kolbada qızılı naxış zolağı
        c.strokeStyle = 'rgba(0, 30, 35, .6)'; c.lineWidth = 0.6; c.stroke(vase);
        // gövdə (mis), bəzək halqaları
        c.fillStyle = brass(c, -2.6, 2.6); c.fillRect(-2.3, -26, 4.6, 40);
        c.fillStyle = brass(c, -6.5, 6.5);
        c.beginPath(); c.ellipse(0, 13, 6.5, 2.2, 0, 0, Math.PI * 2); c.fill();               // kolbanın ağzına oturan yer
        c.fillStyle = brass(c, -5, 5);
        c.beginPath(); c.ellipse(0, 5, 5, 3, 0, 0, Math.PI * 2); c.fill();                     // şlanq çıxışı
        c.beginPath(); c.arc(0, -6, 4.2, 0, Math.PI * 2); c.fill();                            // kürə bəzək
        c.fillStyle = brass(c, -3.6, 3.6);
        c.beginPath(); c.ellipse(0, -15, 3.6, 1.6, 0, 0, Math.PI * 2); c.fill();
        c.fillStyle = 'rgba(255, 250, 225, .75)';
        c.beginPath(); c.ellipse(-1.4, -7.4, 1.3, 1.8, 0, 0, Math.PI * 2); c.fill();          // kürədə parıltı
        c.fillStyle = brass(c, 3, 8); c.fillRect(3.5, 3.5, 4, 3);                             // şlanq yuvası
        // külqabı (boşqab)
        c.fillStyle = '#6e4a10';
        c.beginPath(); c.ellipse(0, -24.6, 14, 3.6, 0, 0, Math.PI * 2); c.fill();
        c.fillStyle = brass(c, -14, 14);
        c.beginPath(); c.ellipse(0, -25.6, 14, 3.4, 0, 0, Math.PI * 2); c.fill();
        c.strokeStyle = 'rgba(255, 240, 200, .7)'; c.lineWidth = 0.6;
        c.beginPath(); c.ellipse(0, -25.6, 11, 2.4, 0, 0, Math.PI * 2); c.stroke();
        // gil başlıq, üstündə folqa və köz
        c.beginPath(); c.moveTo(-3.5, -27); c.lineTo(-7, -38); c.lineTo(7, -38); c.lineTo(3.5, -27); c.closePath();
        const clay = c.createLinearGradient(-7, 0, 7, 0);
        clay.addColorStop(0, '#5e2a14'); clay.addColorStop(0.4, '#b5643a'); clay.addColorStop(1, '#4a200f');
        c.fillStyle = clay; c.fill();
        c.strokeStyle = 'rgba(255, 210, 170, .35)'; c.lineWidth = 0.6;
        c.beginPath(); c.moveTo(-5.5, -33); c.lineTo(5.5, -33); c.stroke();
        c.fillStyle = '#cfd3d6';
        c.beginPath(); c.ellipse(0, -38, 7.2, 1.9, 0, 0, Math.PI * 2); c.fill();               // folqa
        c.fillStyle = 'rgba(120, 125, 130, .6)';
        for (const x of [-4, -1.5, 1.5, 4]) { c.beginPath(); c.arc(x, -38, 0.35, 0, Math.PI * 2); c.fill(); }
        c.fillStyle = '#2b2826';
        c.beginPath(); c.moveTo(-3.5, -39); c.lineTo(-1.5, -42); c.lineTo(3, -41.5); c.lineTo(4, -39); c.closePath(); c.fill(); // kömür
        c.strokeStyle = '#ff8a2e'; c.lineWidth = 0.8;
        c.beginPath(); c.moveTo(-3.5, -39); c.lineTo(4, -39); c.stroke();
    }

    // ---- közərmiş qəlyan kömürü: hindistan cevizi kömürü kubu, üstü kül bağlayıb, çatlarından od görünür ----
    const COAL = {T: [[-13, -8], [0, -15], [13, -8], [0, -1]], L: [[-13, -8], [0, -1], [0, 14], [-13, 7]], R: [[0, -1], [13, -8], [13, 7], [0, 14]]};
    function paintCoal(c) {
        softShadow(c, 0, 17, 15, 3.5, 0.45);
        const r = seeded(91);
        for (const [face, col, ash] of [[COAL.L, '#2b2826', 70], [COAL.R, '#1d1b1a', 60], [COAL.T, '#4a4643', 190]]) {
            poly(c, face);
            c.fillStyle = col; c.fill();
            c.save(); c.clip();
            for (let i = 0; i < ash; i++) { // kül və məsamələr
                const pore = r() < 0.25;
                c.fillStyle = pore ? `rgba(0, 0, 0, ${0.3 + r() * 0.3})` : `rgba(215, 210, 200, ${(face === COAL.T ? 0.25 : 0.1) + r() * 0.35})`;
                c.beginPath(); c.arc(-13 + r() * 26, -15 + r() * 30, 0.4 + r() * 0.9, 0, Math.PI * 2); c.fill();
            }
            if (face === COAL.T) {
                for (const [x, y, rr] of [[-4, -9, 5], [4, -6, 4], [0, -11, 3]]) { // kül ləkələri
                    const g = c.createRadialGradient(x, y, 0, x, y, rr);
                    g.addColorStop(0, 'rgba(232, 228, 220, .55)'); g.addColorStop(1, 'rgba(232, 228, 220, 0)');
                    c.fillStyle = g; c.fillRect(x - rr, y - rr, rr * 2, rr * 2);
                }
            }
            c.restore();
        }
        c.strokeStyle = 'rgba(150, 145, 138, .35)'; c.lineWidth = 0.6;
        poly(c, COAL.T); c.stroke();
    }
    function paintCoalGlow(c) { // köz: ayrıca qat — hər kadrda parlaqlığı dəyişir ("nəfəs alır")
        for (const face of [COAL.L, COAL.R]) {
            poly(c, face);
            c.save(); c.clip();
            const g = c.createLinearGradient(0, 14, 0, -4);
            g.addColorStop(0, 'rgba(255, 95, 15, .85)'); g.addColorStop(0.45, 'rgba(255, 70, 10, .35)'); g.addColorStop(1, 'rgba(255, 60, 10, 0)');
            c.fillStyle = g; c.fillRect(-14, -9, 28, 24);
            c.restore();
        }
        c.shadowColor = '#ff5a00'; c.shadowBlur = 5;
        c.strokeStyle = '#ffb347'; c.lineWidth = 1; c.lineJoin = 'round';
        for (const crack of [[[-10, 0], [-7, 3], [-8, 6], [-4, 9]], [[-3, 2], [-5, 6]], [[4, 3], [6, 6], [5, 9], [9, 10]], [[10, -2], [8, 1]]]) {
            c.beginPath(); c.moveTo(crack[0][0], crack[0][1]);
            for (const p of crack.slice(1)) c.lineTo(p[0], p[1]);
            c.stroke();
        }
        c.strokeStyle = 'rgba(255, 150, 50, .85)'; c.lineWidth = 1.2;
        c.beginPath(); c.moveTo(-13, 7); c.lineTo(0, 14); c.lineTo(13, 7); c.moveTo(0, -1); c.lineTo(0, 14); c.stroke();
        c.shadowBlur = 0;
    }

    window.SufreArt = {
        GW, GH, paintPaxlava, paintShekerbura, paintQend, paintLimon, paintShokolad, paintQoz,
        paintGlass, paintTeapot, paintHookah, paintCoal, paintCoalGlow,
    };
})();
