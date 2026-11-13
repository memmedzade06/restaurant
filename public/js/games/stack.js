/*
 * Stəkan qülləsi — armudu stəkanları üst-üstə düzmək oyunu.
 *
 * Yuxarıda stəkan sağa-sola yellənir; toxunanda (klik / boşluq) düşür və qüllənin üstünə qonur.
 * Mərkəzə yaxın qonsa — qüllə böyüyür, çox kənara qonsa — stəkan aşır və oyun bitir.
 * Dəqiq qonuş ("Əla!") bonus xal verir. Sürət hər xalda artır, 30 xaldan sonra daha kəskin.
 *
 * Bütün hesablamalar 400×560 ölçülü "məntiqi" koordinatlarda aparılır, kətan ekrana uyğun böyüdülür.
 */
(function () {
    'use strict';

    const root = document.getElementById('stack');
    if (!root) return;

    const canvas = root.querySelector('canvas');
    const ctx = canvas.getContext('2d');
    const overlay = root.querySelector('.st-result');
    const offer = root.querySelector('.st-offer');
    const startHint = root.querySelector('.st-start');
    const cfg = {
        canSave: root.dataset.canSave === '1',
        saveUrl: root.dataset.saveUrl,
        registerUrl: root.dataset.registerUrl,
        startUrl: root.dataset.startUrl,
        prizeUrl: root.dataset.prizeUrl,
        prizesUrl: root.dataset.prizesUrl,
        // {xal: {key, label}} — bu xala çatanda hədiyyə təklif olunur
        prizes: JSON.parse(root.dataset.prizes || '{}'),
        // bu gün qalan hədiyyə sayı (boş — istifadəçi təsdiqlənməyib)
        prizeLeft: root.dataset.prizeLeft === '' ? null : +root.dataset.prizeLeft,
        csrf: document.querySelector('meta[name="csrf-token"]')?.content,
    };

    /* ---------- Ölçülər ---------- */
    const LW = 400, LH = 560;          // məntiqi ölçü
    const GW = 52, GH = 72;            // stəkanın eni və hündürlüyü (nazik, hündür armudu)
    const BASE_Y = LH - 58;            // ilk stəkanın dibi (nəlbəkinin üstü)
    const SWING_GAP = 130;             // yellənən stəkanla qüllə arasındakı məsafə
    const FLOOR = BASE_Y + 8;          // masanın səthi — düşən stəkan burada sınır
    const PERFECT = GW * 0.07;         // "Əla!" sayılan sapma
    const SUPPORT = GW * 0.52;         // üstündəki yükün dayaq sahəsi (ağız + bir az ehtiyat ki, oyun çox çətin olmasın)
    const WARN = 0.3;                  // dayaq ehtiyatı bundan az qalanda "qüllə əyilir" xəbərdarlığı
    let scale = 1, dpr = 1;

    let best = +root.dataset.best || 0;
    let g; // oyunun vəziyyəti

    function reset() {
        g = {
            state: 'ready',           // ready | swing | drop | offer | fall | over
            tower: [{x: LW / 2}],     // qüllədəki stəkanların mərkəzləri
            score: 0,
            combo: 0,
            swing: {x: GW / 2 + 12, dir: 1},
            drop: null,
            falling: null,
            shards: [],
            puddles: [],
            stains: [],
            camY: 0,
            shake: 0,
            popups: [],
            time: 0,
            finish: false,
            offered: {},              // bu oyunda təklif olunmuş hədiyyələr
            claiming: false,
        };
        overlay.hidden = true;
        offer.hidden = true;
        overlay.querySelector('.st-prize').hidden = true;
        overlay.querySelector('.st-again').textContent = 'Yenidən oyna';
        startHint.hidden = false;
    }

    // 30 xala qədər stəkan yavaş-yavaş sürətlənir, 30-dan sonra hər xalda daha kəskin
    const SPEED_BASE = 150, SPEED_FROM = 30, SPEED_STEP_SLOW = 3, SPEED_STEP = 8, SPEED_MAX = 480;
    const speed = () => {
        const slow = Math.min(g.score, SPEED_FROM) * SPEED_STEP_SLOW;
        const fast = Math.max(0, g.score - SPEED_FROM) * SPEED_STEP;
        return Math.min(SPEED_MAX, SPEED_BASE + slow + fast);
    };
    const topBottomY = () => BASE_Y - (g.tower.length - 1) * GH; // ən üst stəkanın dibi
    const swingY = () => topBottomY() - GH - SWING_GAP;           // yellənən stəkanın dibi

    /* ---------- Hərəkət ---------- */

    function tap() {
        if (g.state === 'ready') {
            g.state = 'swing';
            startHint.hidden = true;
            post(cfg.startUrl, {}); // server oyunun başladığı vaxtı yadda saxlayır
            return;
        }
        if (g.state === 'swing') {
            g.state = 'drop';
            g.drop = {x: g.swing.x, y: swingY(), vy: 0};
        }
    }

    /**
     * Qüllənin dayanıqlığı (ağırlıq mərkəzi qaydası):
     * hər stəkanın üstündəki BÜTÜN stəkanların ümumi ağırlıq mərkəzi həmin stəkanın ağzının
     * üzərində olmalıdır. Olmasa, o səviyyədən yuxarı hissə aşır.
     * Qaytarır: {level: aşan səviyyə və ya -1, side: aşma istiqaməti, margin: ən kiçik ehtiyat (0..1)}
     */
    function stability(tower) {
        let margin = 1, level = -1, side = 0;
        for (let i = 0; i < tower.length - 1; i++) {
            let sum = 0;
            for (let j = i + 1; j < tower.length; j++) sum += tower[j].x;
            const com = sum / (tower.length - 1 - i);  // yuxarıdakıların ağırlıq mərkəzi
            const off = com - tower[i].x;
            const m = 1 - Math.abs(off) / SUPPORT;
            if (m < 0 && level < 0) { level = i; side = Math.sign(off); } // ən aşağıdakı zəif nöqtə
            margin = Math.min(margin, m);
        }
        return {level, side, margin};
    }

    /**
     * Qüllə aşır: zəif nöqtədən yuxarıdakı yük aşağıdakıları da sürükləyir — BÜTÜN stəkanlar düşür.
     * Zəif nöqtədən yuxarıdakılar daha sürətli və uzağa uçur, aşağıdakılar sonra yıxılır.
     */
    function collapse(tower, level, side) {
        g.falling = tower.map((t, i) => {
            const above = i > level;                 // zəif nöqtədən yuxarıda
            const k = above ? i - level : 0;
            return {
                x: t.x,
                y: BASE_Y - i * GH,
                vx: side * (above ? 50 + k * 28 : 18 + i * 10),
                vy: above ? -30 - k * 12 : -10,
                rot: 0,
                vr: side * (above ? 2.2 + k * 0.5 : 1.2 + i * 0.25),
                delay: above ? 0 : 0.12 + (level - i) * 0.06, // aşağıdakılar bir az gec yıxılır
            };
        });
        g.tower = [];
        g.state = 'fall';
        g.fallTime = 0;
        g.drop = null;
        g.shake = 8;
        g.popups.push({text: 'Qüllə aşdı!', x: LW / 2, y: BASE_Y - tower.length * GH - 20, life: 1.2});
    }

    function land() {
        const top = g.tower[g.tower.length - 1];
        const d = g.drop.x - top.x;
        const ad = Math.abs(d);
        const perfect = ad <= PERFECT;
        const x = perfect ? top.x : g.drop.x; // dəqiq qonanda tam üstünə oturdulur

        const tower = g.tower.concat([{x}]);
        const st = stability(tower);
        if (st.level >= 0) {
            collapse(tower, st.level, st.side);
            return;
        }

        let gain = 1, text = null;
        if (perfect) {
            g.combo++;
            gain = 2;
            text = g.combo >= 3 ? `Əla! ×${g.combo}` : 'Əla! +2';
        } else {
            g.combo = 0;
            g.shake = Math.min(7, ad / 5);
        }
        g.tower = tower;
        g.score += gain;
        g.margin = st.margin;
        if (text) g.popups.push({text, x, y: topBottomY() - GH - 10, life: 1});
        if (st.margin < WARN) {
            // qüllə əyilib — oyunçu növbəti stəkanla tarazlığı düzəltməlidir
            g.shake = Math.max(g.shake, 5);
            g.popups.push({text: 'Diqqət, qüllə əyilir!', x: LW / 2, y: topBottomY() - GH - (text ? 34 : 10), life: 1.3, warn: true});
        }

        g.drop = null;
        g.state = 'swing';
        // növbəti stəkan kənardan başlasın
        g.swing.x = g.swing.dir > 0 ? GW / 2 + 12 : LW - GW / 2 - 12;

        checkPrize();
    }

    /* ---------- Hədiyyə: 4 xalda çay, 8 xalda qəlyan ---------- */

    function post(url, body) {
        if (!cfg.canSave || !url) return Promise.resolve(null);
        return fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf},
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).catch(() => null);
    }

    const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    /** Xal hədiyyə həddini keçibsə — oyunu dayandırıb təklif göstəririk */
    function checkPrize() {
        const mins = Object.keys(cfg.prizes).map(Number).sort((a, b) => a - b);
        const reached = mins.filter(m => g.score >= m && !g.offered[m]);
        if (!reached.length) return;
        reached.forEach(m => { g.offered[m] = true; });
        // sonuncu hədəf — finiş: oyun burada bitir
        g.finish = g.score >= mins[mins.length - 1];
        const noPrize = !cfg.canSave || (cfg.prizeLeft !== null && cfg.prizeLeft <= 0);
        if (g.finish && noPrize) {
            gameOver(true);
            return;
        }
        // bu günün hədiyyəsi artıq götürülüb — oyunu dayandırmırıq, sadəcə xal toplanır
        if (cfg.canSave && cfg.prizeLeft !== null && cfg.prizeLeft <= 0) {
            g.popups.push({text: "Bugünkü hədiyyə artıq götürülüb", x: LW / 2, y: topBottomY() - GH - 40, life: 2.2, warn: true});
            return;
        }

        const min = reached[reached.length - 1];
        const prize = cfg.prizes[min];
        const isLast = min === mins[mins.length - 1];
        const next = mins.find(m => m > min);

        offer.querySelector('.st-offer-title').textContent = g.finish ? `Finiş! 🏁 ${prize.label} qazandınız!` : `${prize.label} qazandınız!`;
        const note = offer.querySelector('.st-offer-note');
        const accept = offer.querySelector('.st-accept');
        const cont = offer.querySelector('.st-continue');
        accept.disabled = false;
        accept.querySelector('span').textContent = 'Hədiyyəni götür';
        cont.querySelector('span').textContent = isLast ? 'Hədiyyəsiz davam et' : 'Davam et';
        cont.hidden = g.finish; // finişdən sonra davam etmək olmur

        if (!cfg.canSave) {
            note.innerHTML = `Hədiyyə kodu almaq üçün <a href="${cfg.registerUrl}">qeydiyyatdan keçin</a> və nömrənizi təsdiqləyin.`;
            accept.hidden = true;
        } else {
            accept.hidden = false;
            note.innerHTML = g.finish
                ? 'Oyunu sona çatdırdınız! Hədiyyəni götürün — kod veriləcək.'
                : isLast
                ? 'Hədiyyəni götürsəniz, kod veriləcək və yeni oyun başlayacaq. Davam etsəniz, sadəcə xal toplayırsınız.'
                : `Hədiyyəni götürsəniz, kod veriləcək və yeni oyun başlayacaq. Davam etsəniz, növbəti hədəf: <b>${next} xal — ${esc(cfg.prizes[next].label)}</b>! Amma qüllə aşsa, hədiyyə yanır.`;
        }

        g.state = 'offer';
        offer.hidden = false;
    }

    function continueGame() {
        offer.hidden = true;
        if (g.state === 'offer') g.state = 'swing';
    }

    async function acceptPrize() {
        if (g.claiming) return;
        g.claiming = true;
        const accept = offer.querySelector('.st-accept');
        accept.disabled = true;
        accept.querySelector('span').textContent = 'Gözləyin...';

        const r = await post(cfg.prizeUrl, {score: g.score});
        const d = r ? await r.json().catch(() => ({})) : {};
        g.claiming = false;

        if (!r || !r.ok || !d.prize) {
            offer.querySelector('.st-offer-note').textContent = d.message || 'Xəta baş verdi. Yenidən cəhd edin.';
            accept.disabled = false;
            accept.querySelector('span').textContent = 'Hədiyyəni götür';
            if (r && r.status === 429) { cfg.prizeLeft = 0; accept.hidden = true; }
            if (r && r.status === 422) accept.hidden = true;
            if (g.finish && accept.hidden) {
                const msg = d.message;
                offer.hidden = true;
                await gameOver(true);
                if (msg) overlay.querySelector('.st-note').innerHTML += `<br>${esc(msg)}`;
            }
            return;
        }

        if (cfg.prizeLeft !== null) cfg.prizeLeft--;
        offer.hidden = true;
        g.state = 'over';
        if (d.best !== undefined) {
            best = d.best;
            const el = document.getElementById('stBest');
            if (el) el.textContent = d.best;
        }

        overlay.querySelector('.st-score').textContent = g.score;
        overlay.querySelector('.st-title').textContent = 'Təbriklər! 🎉';
        overlay.querySelector('.st-note').innerHTML = `<b>${esc(d.prize.label)}</b> qazandınız.`;
        const box = overlay.querySelector('.st-prize');
        box.innerHTML = `<div class="prize-code">${esc(d.prize.code)}</div>` +
            `<p class="small text-muted mb-3">Kodu ofisianta göstərin. ${esc(d.prize.expires)} tarixinədək etibarlıdır. ` +
            `Kod <a href="${cfg.prizesUrl}">Hədiyyələrim</a> səhifəsində də saxlanılır.</p>`;
        box.hidden = false;
        overlay.querySelector('.st-again').textContent = 'Yeni oyun';
        overlay.hidden = false;
    }

    /* ---------- Sınma: şüşə qırıqları, çay damcıları, masada çay ləkəsi ---------- */

    function shatter(f) {
        f.broken = true;
        const x = Math.max(-20, Math.min(LW + 20, f.x));
        g.shake = Math.max(g.shake, 6);
        // şüşə qırıqları
        for (let i = 0; i < 14; i++) {
            const a = -Math.PI * (0.1 + Math.random() * 0.8); // yuxarı-yana
            const v = 160 + Math.random() * 260;
            g.shards.push({
                kind: 'glass', x: x + (Math.random() - 0.5) * GW * 0.6, y: FLOOR - 4,
                vx: Math.cos(a) * v + f.vx * 0.3, vy: Math.sin(a) * v,
                rot: Math.random() * 6, vr: (Math.random() - 0.5) * 18,
                size: 4 + Math.random() * 7, life: 1, rest: false,
                gold: Math.random() < 0.18, // qızılı haşiyədən qırıq
            });
        }
        // çay damcıları: bəziləri havada uçarkən arxa fona yapışır
        for (let i = 0; i < 14; i++) {
            const a = -Math.PI * (0.15 + Math.random() * 0.7);
            const v = 120 + Math.random() * 260;
            g.shards.push({
                kind: 'tea', x, y: FLOOR - 6, vx: Math.cos(a) * v, vy: Math.sin(a) * v,
                size: 2 + Math.random() * 3.5, life: 1, rest: false, age: 0,
                wallAt: Math.random() < 0.6 ? 0.08 + Math.random() * 0.35 : Infinity,
            });
        }
        // arxa fona çırpılan çay: böyük ləkə, ətrafında xırda sıçrantılar və aşağı axan izlər
        splat(x + (Math.random() - 0.5) * 30, FLOOR - 30 - Math.random() * 70, 9 + Math.random() * 7, true);
        // masada çay ləkəsi
        g.puddles.push({x, r: 4, max: 20 + Math.random() * 12, a: 0.75});
    }

    /** Arxa fonda çay ləkəsi (x, y — mərkəz, r — ölçü) */
    function splat(x, y, r, big) {
        // ləkənin forması bir az qeyri-düzgün olsun
        const blobs = Array.from({length: big ? 6 : 2}, () => ({
            dx: (Math.random() - 0.5) * r, dy: (Math.random() - 0.5) * r, r: r * (0.35 + Math.random() * 0.45),
        }));
        const stain = {x, y, r, blobs, grow: 0, drips: []};
        if (big || Math.random() < 0.25) {
            const n = big ? 2 + Math.floor(Math.random() * 3) : 1;
            for (let i = 0; i < n; i++) {
                stain.drips.push({dx: (Math.random() - 0.5) * r * 1.2, w: 1.5 + Math.random() * (big ? 2.5 : 1), len: 0, max: 12 + Math.random() * (big ? 45 : 18), speed: 8 + Math.random() * 14});
            }
        }
        g.stains.push(stain);
        if (big) {
            for (let i = 0; i < 9; i++) { // ətrafdakı xırda sıçrantılar
                const a = Math.random() * Math.PI * 2, d = r * (1.3 + Math.random() * 2.6);
                g.stains.push({x: x + Math.cos(a) * d, y: y + Math.sin(a) * d * 0.8, r: 1.2 + Math.random() * 3, blobs: [], grow: 0, drips: []});
            }
        }
    }

    function updateShards(dt) {
        for (const s of g.shards) {
            if (s.rest) { s.life -= dt * 0.35; continue; }
            if (s.kind === 'tea') {
                s.age += dt;
                if (s.age >= s.wallAt) { // arxa fona dəydi — orada ləkə qalır
                    splat(s.x, s.y, s.size * 1.5, false);
                    s.life = 0;
                    continue;
                }
            }
            s.vy += 1400 * dt;
            s.x += s.vx * dt;
            s.y += s.vy * dt;
            if (s.rot !== undefined) s.rot += s.vr * dt;
            if (s.y >= FLOOR && s.vy > 0) {
                if (s.kind === 'tea') { s.rest = true; s.y = FLOOR; continue; } // damcı masaya hopur
                s.y = FLOOR;
                s.vy *= -0.32; s.vx *= 0.55; s.vr *= 0.5;          // qırıq sıçrayır
                if (Math.abs(s.vy) < 40) { s.rest = true; s.vy = 0; }
            }
        }
        g.shards = g.shards.filter(s => s.life > 0);
        for (const p of g.puddles) p.r += (p.max - p.r) * Math.min(1, dt * 5);
        for (const st of g.stains) {
            st.grow = Math.min(1, st.grow + dt * 9);             // ləkə bir anda yayılır
            for (const d of st.drips) d.len = Math.min(d.max, d.len + d.speed * dt); // izlər yavaş aşağı axır
        }
    }

    function update(dt) {
        g.time += dt;

        if (g.state === 'swing') {
            const min = GW / 2 + 8, max = LW - GW / 2 - 8;
            g.swing.x += g.swing.dir * speed() * dt;
            if (g.swing.x > max) { g.swing.x = max; g.swing.dir = -1; }
            if (g.swing.x < min) { g.swing.x = min; g.swing.dir = 1; }
        }

        if (g.state === 'drop') {
            g.drop.vy += 2600 * dt;
            g.drop.y += g.drop.vy * dt;
            if (g.drop.y >= topBottomY() - GH) land();
        }

        if (g.state === 'fall') {
            g.fallTime += dt;
            for (const f of g.falling) {
                if (f.broken) continue;
                if (f.delay > 0) { f.delay -= dt; continue; }
                f.vy += 1500 * dt;
                f.x += f.vx * dt;
                f.y += f.vy * dt;
                f.rot += f.vr * dt;
                if (f.y >= FLOOR) shatter(f); // masaya dəydi — sınır
            }
            // bütün stəkanlar sınıb, qırıqlar da yerə çöküb
            const done = g.falling.every(f => f.broken) && g.shards.every(s => s.rest || s.life < 0.5);
            if ((done && g.fallTime > 1) || g.fallTime > 4.5) gameOver();
        }

        updateShards(dt);

        // kamera: qüllənin başı ekranın yuxarı hissəsində qalsın;
        // qüllə aşanda isə masaya enir ki, stəkanların sınması görünsün
        if (g.state !== 'fall' && g.state !== 'over') {
            const want = Math.max(0, 170 - swingY() + GH);
            g.camY += (want - g.camY) * Math.min(1, dt * 4);
        } else {
            g.camY += (0 - g.camY) * Math.min(1, dt * 2.6);
        }

        g.shake = Math.max(0, g.shake - dt * 14);
        g.popups.forEach(p => { p.life -= dt * 0.9; p.y -= dt * 40; });
        g.popups = g.popups.filter(p => p.life > 0);
    }

    /* ---------- Çəkmə ---------- */

    function glassPath(w, h) {
        const p = new Path2D();
        // Armudu forma: dar dib, aşağıda yumru qarın, incə bel, yuxarıda açılan ağız
        const b = 0.26 * w; // dib
        p.moveTo(-b, 0);
        p.bezierCurveTo(-0.46 * w, -0.03 * h, -0.5 * w, -0.36 * h, -0.33 * w, -0.58 * h);  // qarın (nazik)
        p.bezierCurveTo(-0.28 * w, -0.66 * h, -0.31 * w, -0.86 * h, -0.44 * w, -h);       // bel və ağız
        p.lineTo(0.44 * w, -h);
        p.bezierCurveTo(0.31 * w, -0.86 * h, 0.28 * w, -0.66 * h, 0.33 * w, -0.58 * h);
        p.bezierCurveTo(0.5 * w, -0.36 * h, 0.46 * w, -0.03 * h, b, 0);
        p.closePath();
        return p;
    }
    const GLASS = glassPath(GW, GH);

    // Arxa fon: 50 Qəpik loqosu
    const BG = {logo: new Image()};
    BG.logo.src = root.dataset.bgLogo;

    /** Armudu stəkan: dibi (x, y) nöqtəsindədir */
    function drawGlass(x, y, rot = 0, alpha = 1) {
        ctx.save();
        ctx.globalAlpha = alpha;
        ctx.translate(x, y);
        if (rot) ctx.rotate(rot);

        // şüşənin özü (çaydan əvvəl çəkilir ki, çayın rəngini solğunlaşdırmasın)
        ctx.fillStyle = 'rgba(255, 255, 255, .22)';
        ctx.fill(GLASS);

        // çay
        ctx.save();
        ctx.clip(GLASS);
        // dəmlənmiş qara çay: şəffaf, qırmızımtıl-kəhrəba rəng
        const tea = ctx.createLinearGradient(0, -GH * 0.75, 0, 0);
        tea.addColorStop(0, 'rgba(205, 42, 10, .9)');
        tea.addColorStop(0.55, 'rgba(160, 18, 4, .95)');
        tea.addColorStop(1, 'rgba(98, 6, 2, .98)');
        ctx.fillStyle = tea;
        ctx.fillRect(-GW, -GH * 0.74, GW * 2, GH * 0.74);
        // işığın çaydan keçməsi — ortada açıq kəhrəba parıltı
        const glow = ctx.createRadialGradient(-GW * 0.08, -GH * 0.3, 2, -GW * 0.08, -GH * 0.3, GW * 0.42);
        glow.addColorStop(0, 'rgba(255, 110, 40, .35)');
        glow.addColorStop(1, 'rgba(255, 110, 40, 0)');
        ctx.fillStyle = glow;
        ctx.fillRect(-GW, -GH * 0.74, GW * 2, GH * 0.74);
        ctx.fillStyle = 'rgba(255, 190, 120, .6)'; // çayın səthi
        ctx.fillRect(-GW, -GH * 0.74, GW * 2, 2);
        ctx.restore();

        // şüşənin kənarı
        ctx.lineWidth = 2;
        ctx.strokeStyle = 'rgba(255, 255, 255, .95)';
        ctx.stroke(GLASS);

        // qızılı haşiyə və ayaq
        ctx.fillStyle = '#E9A23B';
        ctx.fillRect(-GW * 0.44, -GH - 1, GW * 0.88, 3.5);
        ctx.beginPath();
        ctx.ellipse(0, 0, GW * 0.28, 4, 0, 0, Math.PI * 2);
        ctx.fill();

        // parıltı
        ctx.strokeStyle = 'rgba(255, 255, 255, .7)';
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(-GW * 0.33, -GH * 0.18);
        ctx.quadraticCurveTo(-GW * 0.4, -GH * 0.38, -GW * 0.28, -GH * 0.56);
        ctx.stroke();
        ctx.restore();
    }

    /** Masanın üstündə süfrə: düz qırmızı parça, yumşaq qırışlarla aşağı sallanır, alt kənarı dalğalıdır */
    function drawCloth() {
        const top = BASE_Y + 8, bottom = LH - 8;
        const x0 = 26, x1 = LW - 26, flare = 10;          // aşağıda bir az genişlənir
        const folds = 9;
        const fw = (x1 - x0 + flare * 2) / folds;

        // qırışlar: eninə açıq-tünd növbələşən çalarlar
        const shade = ctx.createLinearGradient(x0 - flare, 0, x1 + flare, 0);
        for (let i = 0; i <= folds * 2; i++) {
            const t = i / (folds * 2);
            const edge = Math.min(t, 1 - t) < 0.06;     // kənarlar bir az tünd
            shade.addColorStop(t, edge ? '#b81f1f' : (i % 2 ? '#d42a2a' : '#e8403a'));
        }

        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x0, top);
        ctx.lineTo(x1, top);
        ctx.quadraticCurveTo(x1 + flare * 0.4, (top + bottom) / 2, x1 + flare, bottom);
        // alt kənar: hər qırışda bir az aşağı sallanır
        for (let i = folds; i > 0; i--) {
            const xa = x0 - flare + i * fw;
            const dip = 4 + (i % 3) * 1.5;
            ctx.quadraticCurveTo(xa - fw / 2, bottom + dip, xa - fw, bottom);
        }
        ctx.quadraticCurveTo(x0 - flare * 0.4, (top + bottom) / 2, x0, top);
        ctx.closePath();
        ctx.fillStyle = shade;
        ctx.fill();
        ctx.clip();

        // yuxarıdan aşağı işıq-kölgə: masanın kənarı parlaq, aşağı tünd
        const depth = ctx.createLinearGradient(0, top, 0, bottom + 8);
        depth.addColorStop(0, 'rgba(255, 255, 255, .18)');
        depth.addColorStop(0.15, 'rgba(255, 255, 255, 0)');
        depth.addColorStop(1, 'rgba(60, 0, 0, .28)');
        ctx.fillStyle = depth;
        ctx.fillRect(x0 - flare, top, x1 - x0 + flare * 2, bottom - top + 10);

        // qırışların incə xətləri
        ctx.strokeStyle = 'rgba(110, 10, 10, .22)';
        ctx.lineWidth = 1.2;
        for (let i = 1; i < folds; i++) {
            const xb = x0 - flare + i * fw;
            const xt = x0 + (xb - (x0 - flare)) * (x1 - x0) / (x1 - x0 + flare * 2);
            ctx.beginPath();
            ctx.moveTo(xt, top + 10);
            ctx.quadraticCurveTo((xt + xb) / 2 + 2, (top + bottom) / 2, xb, bottom + 4);
            ctx.stroke();
        }
        ctx.restore();

        // masanın üst kənarı — parçanın qatlandığı yer
        ctx.fillStyle = '#f04a43';
        ctx.fillRect(x0, top - 1, x1 - x0, 3);
    }

    /** Bir limon dilimi: sarı qabıq, açıq ət, ağ dilim xətləri. tilt — eninə sıxılma (yanakı görünüş) */
    function drawLemon(x, y, r, tilt = 1, rot = 0) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(rot);
        ctx.scale(tilt, 1);
        ctx.fillStyle = '#e8b417';                      // qabıq
        ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#fff6cf';                      // ağ qat
        ctx.beginPath(); ctx.arc(0, 0, r * 0.86, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#fbe26a';                      // ət
        ctx.beginPath(); ctx.arc(0, 0, r * 0.76, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = 'rgba(255, 250, 225, .95)';   // dilimlər
        ctx.lineWidth = 0.9;
        for (let i = 0; i < 8; i++) {
            const a = i * Math.PI / 4;
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(Math.cos(a) * r * 0.74, Math.sin(a) * r * 0.74);
            ctx.stroke();
        }
        ctx.fillStyle = '#fffbe8';
        ctx.beginPath(); ctx.arc(0, 0, r * 0.12, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    }

    /** Ayaqlı büllur qab, içində limon dilimləri. (cx, y) — qabın dibinin ortası */
    function drawLemonDish(cx, y) {
        const glass = 'rgba(235, 245, 250, .55)';
        const edge = 'rgba(150, 175, 190, .9)';
        const shine = 'rgba(255, 255, 255, .85)';
        ctx.lineWidth = 1;

        // kölgə
        ctx.fillStyle = 'rgba(60, 0, 0, .18)';
        ctx.beginPath(); ctx.ellipse(cx, y + 1, 24, 3.5, 0, 0, Math.PI * 2); ctx.fill();

        // ayaq (oturacaq)
        ctx.fillStyle = glass; ctx.strokeStyle = edge;
        ctx.beginPath(); ctx.ellipse(cx, y - 2, 20, 4.5, 0, 0, Math.PI * 2); ctx.fill(); ctx.stroke();

        // gövdə — üzlü büllur
        const stemTop = y - 17;
        ctx.beginPath();
        ctx.moveTo(cx - 13, y - 3);
        ctx.quadraticCurveTo(cx - 7, y - 9, cx - 6, stemTop);
        ctx.lineTo(cx + 6, stemTop);
        ctx.quadraticCurveTo(cx + 7, y - 9, cx + 13, y - 3);
        ctx.closePath();
        ctx.fill(); ctx.stroke();
        ctx.strokeStyle = 'rgba(150, 175, 190, .55)';
        for (const dx of [-6, -2, 2, 6]) {
            ctx.beginPath(); ctx.moveTo(cx + dx * 1.6, y - 4); ctx.lineTo(cx + dx * 0.8, stemTop + 1); ctx.stroke();
        }

        // qabın arxa kənarı
        const rimY = y - 30, rx = 34;
        ctx.fillStyle = 'rgba(225, 238, 245, .5)'; ctx.strokeStyle = edge;
        ctx.beginPath(); ctx.ellipse(cx, rimY, rx, 6, 0, Math.PI, Math.PI * 2); ctx.fill(); ctx.stroke();

        // limon dilimləri — qabda yelpik kimi düzülüb
        const lemons = [
            [-22, -5, 10, 0.55, -0.5], [22, -5, 10, 0.55, 0.5],
            [-13, -9, 11, 0.8, -0.25], [13, -9, 11, 0.8, 0.25],
            [0, -11, 12, 1, 0], [-6, -3, 10, 0.95, -0.1], [7, -3, 10, 0.95, 0.12],
        ];
        for (const [dx, dy, r, t, a] of lemons) drawLemon(cx + dx, rimY + dy, r, t, a);

        // qabın ön tərəfi (limonların alt hissəsini örtür)
        ctx.fillStyle = glass; ctx.strokeStyle = edge;
        ctx.beginPath();
        ctx.moveTo(cx - rx, rimY);
        ctx.quadraticCurveTo(cx - rx + 6, stemTop + 1, cx - 6, stemTop);
        ctx.lineTo(cx + 6, stemTop);
        ctx.quadraticCurveTo(cx + rx - 6, stemTop + 1, cx + rx, rimY);
        ctx.ellipse(cx, rimY, rx, 6, 0, 0, Math.PI);
        ctx.closePath();
        ctx.fill(); ctx.stroke();

        // qızılı haşiyə və parıltı
        ctx.strokeStyle = '#E9A23B'; ctx.lineWidth = 1.6;
        ctx.beginPath(); ctx.ellipse(cx, rimY, rx, 6, 0, 0, Math.PI); ctx.stroke();
        ctx.strokeStyle = shine; ctx.lineWidth = 1.4;
        ctx.beginPath(); ctx.moveTo(cx - rx + 7, rimY + 4); ctx.quadraticCurveTo(cx - rx + 10, stemTop + 3, cx - 10, stemTop + 2); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(cx - 4, y - 5); ctx.lineTo(cx - 3, stemTop + 2); ctx.stroke();
    }

    function drawScene() {
        const cam = g.camY;
        const sx = g.shake ? Math.sin(g.time * 60) * g.shake : 0;

        // fon: hündürlüyə görə rəng dəyişir
        const h = Math.min(1, cam / 2500);
        const bg = ctx.createLinearGradient(0, 0, 0, LH);
        bg.addColorStop(0, mix('#fbe6c8', '#2a1033', h));
        bg.addColorStop(1, mix('#f6cf9f', '#7e1a1a', h));
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, LW, LH);

        // 50 Qəpik loqosu — arxa planda su nişanı; qüllədən yavaş hərəkət edir (dərinlik effekti)
        if (BG.logo.complete && BG.logo.naturalWidth) {
            const s = 230, ly = 150 + cam * 0.3;
            if (ly < LH) {
                ctx.save();
                ctx.globalAlpha = 0.16 * (1 - h * 0.5);
                ctx.drawImage(BG.logo, LW / 2 - s / 2, ly, s, s);
                ctx.restore();
            }
        }

        // yüksəklikdə ulduzlar
        if (h > 0.25) {
            ctx.fillStyle = `rgba(255,255,255,${(h - 0.25) * 0.9})`;
            for (let i = 0; i < 40; i++) {
                const x = (i * 97) % LW, y = ((i * 151 + cam * 0.2) % LH);
                ctx.fillRect(x, y, 2, 2);
            }
        }

        ctx.save();
        ctx.translate(0, cam);

        // arxa fona çırpılmış çay ləkələri və aşağı axan izləri
        for (const st of g.stains) {
            const k = 0.4 + 0.6 * st.grow;
            ctx.fillStyle = 'rgba(184, 96, 34, .42)'; // tökülmüş çay: şəffaf kəhrəba
            for (const d of st.drips) {
                ctx.beginPath();
                ctx.roundRect
                    ? ctx.roundRect(st.x + d.dx - d.w / 2, st.y, d.w, d.len, d.w / 2)
                    : ctx.rect(st.x + d.dx - d.w / 2, st.y, d.w, d.len);
                ctx.fill();
                ctx.beginPath(); // izin ucundakı damcı
                ctx.arc(st.x + d.dx, st.y + d.len, d.w * 0.9, 0, Math.PI * 2);
                ctx.fill();
            }
            ctx.beginPath();
            ctx.arc(st.x, st.y, st.r * k, 0, Math.PI * 2);
            for (const b of st.blobs) {
                ctx.moveTo(st.x + b.dx * k + b.r * k, st.y + b.dy * k);
                ctx.arc(st.x + b.dx * k, st.y + b.dy * k, b.r * k, 0, Math.PI * 2);
            }
            ctx.fill();
        }

        // masa və nəlbəki
        const table = ctx.createLinearGradient(0, BASE_Y + 8, 0, BASE_Y + 80);
        table.addColorStop(0, '#8a5228');
        table.addColorStop(1, '#4a250f');
        ctx.fillStyle = table;
        ctx.fillRect(0, BASE_Y + 8, LW, 400);
        drawCloth();
        ctx.fillStyle = '#fff';
        ctx.strokeStyle = '#E9A23B';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.ellipse(LW / 2, BASE_Y + 4, GW * 0.8, 9, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();

        // masanın sağında: ayaqlı büllur qabda limon dilimləri
        ctx.save();
        ctx.translate(LW - 62, BASE_Y + 9);
        ctx.scale(0.72, 0.72); // qabın ölçüsü
        drawLemonDish(0, 0);
        ctx.restore();

        // masada dağılmış çay ləkələri
        for (const p of g.puddles) {
            ctx.fillStyle = `rgba(170, 86, 28, ${p.a * 0.62})`;
            ctx.beginPath();
            ctx.ellipse(p.x, FLOOR + 3, p.r, p.r * 0.22, 0, 0, Math.PI * 2);
            ctx.fill();
        }

        // qüllə
        g.tower.forEach((t, i) => drawGlass(t.x + sx * (i / Math.max(1, g.tower.length)), BASE_Y - i * GH));

        // yellənən / düşən / aşan stəkan
        if (g.state === 'swing' || g.state === 'ready') {
            // hədəf xətti
            ctx.strokeStyle = 'rgba(126, 26, 26, .18)';
            ctx.setLineDash([4, 6]);
            ctx.beginPath();
            ctx.moveTo(g.swing.x, swingY());
            ctx.lineTo(g.swing.x, topBottomY() - GH);
            ctx.stroke();
            ctx.setLineDash([]);
            drawGlass(g.swing.x, swingY());
        }
        if (g.state === 'drop') drawGlass(g.drop.x, g.drop.y);
        if ((g.state === 'fall' || g.state === 'over') && g.falling) {
            g.falling.forEach(f => { if (!f.broken) drawGlass(f.x, f.y, f.rot); });
        }

        // şüşə qırıqları və çay damcıları
        for (const s of g.shards) {
            ctx.globalAlpha = Math.max(0, Math.min(1, s.life));
            if (s.kind === 'tea') {
                ctx.fillStyle = 'rgba(196, 108, 40, .78)';
                ctx.beginPath();
                if (s.rest) ctx.ellipse(s.x, FLOOR + 2, s.size * 1.6, s.size * 0.45, 0, 0, Math.PI * 2);
                else ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
                ctx.fill();
            } else {
                ctx.save();
                ctx.translate(s.x, s.y);
                ctx.rotate(s.rot);
                ctx.beginPath();
                ctx.moveTo(-s.size * 0.5, s.size * 0.4);
                ctx.lineTo(s.size * 0.1, -s.size * 0.6);
                ctx.lineTo(s.size * 0.6, s.size * 0.3);
                ctx.closePath();
                ctx.fillStyle = s.gold ? 'rgba(233, 162, 59, .95)' : 'rgba(255, 255, 255, .88)';
                ctx.strokeStyle = s.gold ? '#c9831f' : 'rgba(140, 100, 70, .55)'; // açıq fonda da görünsün
                ctx.lineWidth = 1;
                ctx.fill();
                ctx.stroke();
                ctx.restore();
            }
        }
        ctx.globalAlpha = 1;

        // "Əla!" yazıları
        g.popups.forEach(p => {
            ctx.globalAlpha = Math.max(0, p.life);
            ctx.fillStyle = p.warn ? '#c0392b' : '#7E1A1A';
            ctx.font = p.warn ? '900 17px Nunito, sans-serif' : '900 20px Nunito, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(p.text, p.x, p.y);
            ctx.globalAlpha = 1;
        });
        ctx.restore();

        // hesab
        ctx.textAlign = 'center';
        ctx.fillStyle = h > 0.5 ? '#fff' : '#3A0D0D';
        ctx.font = '900 44px Nunito, sans-serif';
        ctx.fillText(g.score, LW / 2, 58);
        ctx.font = '700 13px Nunito, sans-serif';
        ctx.globalAlpha = 0.7;
        ctx.fillText(`Rekord: ${Math.max(best, g.score)}`, LW / 2, 80);
        ctx.globalAlpha = 1;
    }

    function mix(a, b, t) {
        const pa = a.match(/\w\w/g).map(x => parseInt(x, 16));
        const pb = b.match(/\w\w/g).map(x => parseInt(x, 16));
        return 'rgb(' + pa.map((v, i) => Math.round(v + (pb[i] - v) * t)).join(',') + ')';
    }

    /* ---------- Oyunun sonu ---------- */

    async function gameOver(finished = false) {
        g.state = 'over';
        offer.hidden = true;
        const score = g.score;
        const record = score > best;
        if (record) best = score;

        overlay.querySelector('.st-score').textContent = score;
        overlay.querySelector('.st-title').textContent = finished ? 'Finiş! 🏁' : (record && score > 0 ? 'Yeni rekord! 🎉' : 'Qüllə aşdı!');
        const note = overlay.querySelector('.st-note');
        note.innerHTML = `Ən yaxşı nəticəniz: <b>${best}</b>`;
        overlay.hidden = false;

        if (!cfg.canSave) {
            if (score > 0) note.innerHTML += `<br><a href="${cfg.registerUrl}">Qeydiyyatdan keçin</a> ki, rekordunuz liderlər cədvəlinə düşsün.`;
            return;
        }
        try {
            const r = await fetch(cfg.saveUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf},
                credentials: 'same-origin',
                body: JSON.stringify({score}),
            });
            const d = await r.json();
            if (r.ok && d.saved) {
                best = d.best;
                note.innerHTML = `Ən yaxşı nəticəniz: <b>${d.best}</b>` + (d.record ? '<br>Rekordunuz liderlər cədvəlinə yazıldı!' : '');
                const el = document.getElementById('stBest');
                if (el) el.textContent = d.best;
            }
        } catch (e) { /* şəbəkə xətası — oyun davam edir */ }
    }

    /* ---------- Döngü və ölçü ---------- */

    function resize() {
        const w = root.querySelector('.st-stage').clientWidth;
        scale = w / LW;
        dpr = Math.min(2, window.devicePixelRatio || 1);
        canvas.style.width = w + 'px';
        canvas.style.height = (LH * scale) + 'px';
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(LH * scale * dpr);
    }

    let last = performance.now();
    let manual = false; // testlər üçün: oyunu kadr-kadr idarə etmək
    function frame(now) {
        const dt = Math.min(0.05, (now - last) / 1000);
        last = now;
        if (!manual && (g.state !== 'over' || g.falling)) update(dt);
        ctx.setTransform(dpr * scale, 0, 0, dpr * scale, 0, 0);
        drawScene();
        requestAnimationFrame(frame);
    }

    canvas.addEventListener('pointerdown', e => { e.preventDefault(); tap(); });
    window.addEventListener('keydown', e => {
        if ((e.code === 'Space' || e.code === 'Enter') && !e.target.closest('input, textarea, button, a')) {
            e.preventDefault();
            if (g.state === 'over') reset(); else tap();
        }
    });
    root.querySelector('.st-again').addEventListener('click', reset);
    offer.querySelector('.st-continue').addEventListener('click', continueGame);
    offer.querySelector('.st-accept').addEventListener('click', acceptPrize);
    window.addEventListener('resize', resize);

    resize();
    reset();
    requestAnimationFrame(frame);

    // Testlər üçün
    window.__stack = {
        state: () => g, tap, consts: {GW, PERFECT, SUPPORT}, stability, checkPrize, continueGame,
        manual: on => { manual = on; },
        step: (dt = 1 / 60) => { if (g.state !== 'over' || g.falling) update(dt); },
    };
})();
