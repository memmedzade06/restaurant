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
    // true — fondakı fotoda əldə tutulan stəkan qüllənin dibidir (Qız qalası fotosu kimi);
    // false — foto sadəcə arxa fondur, qüllə masadakı nəlbəkinin üstündə qurulur
    const PHOTO_IS_BASE = false;
    const FLOOR = BASE_Y + (PHOTO_IS_BASE ? 40 : 8); // düşən stəkan burada sınır
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
            slosh: {a: 0, v: 0},   // yellənən/düşən stəkandakı çayın meyli
        };
        newSwing();
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

    // 30-cu stəkandan sonra yellənmə sürətlənir: bir anda artım, sonra hər yeni stəkanda daha da
    const BOOST_FROM = 30;           // neçə stəkan qoyulandan sonra başlasın
    const BOOST = 1.25;              // 30-cu stəkanda sürətə birdəfəlik artım
    const BOOST_STEP = 0.03;         // sonrakı hər stəkanda əlavə artım (40-cı stəkanda ×1.55, 50-cidə ×1.85)
    const SPEED_CAP = 650;           // sürətin ən yuxarı həddi — oyun tamam oynanılmaz olmasın
    const placed = () => g.tower.length - 1; // oyunçunun qoyduğu stəkan sayı
    const boost = () => placed() < BOOST_FROM ? 1 : BOOST + (placed() - BOOST_FROM) * BOOST_STEP;

    // Sürətlənmə ilə birlikdə (BOOST_FROM-dan sonra) çaşdırıcı, amma ədalətli hiylələr:
    //  1) hər stəkan öz sürəti ilə gəlir (gah yavaş, gah sürətli), amma bir stəkan ərzində sürət sabitdir;
    //  2) bəzən stəkan yolun ortasında BİR DƏFƏ geri dönür — dönməzdən əvvəl titrəyib xəbər verir.
    const TRICK_SPEED = [0.85, 1.3];  // stəkanın sürət çarpanı (təsadüfi, bu aralıqda)
    const TRICK_FLIP = 0.35;          // stəkanın yolda geri dönmə ehtimalı
    const TRICK_WARN = 0.35;          // geri dönməzdən neçə saniyə əvvəl titrəsin
    const rnd = (a, b) => a + Math.random() * (b - a);

    /** Yeni yellənən stəkanın xasiyyəti */
    function newSwing() {
        const tricky = placed() >= BOOST_FROM;
        g.swing.mul = tricky ? rnd(TRICK_SPEED[0], TRICK_SPEED[1]) : 1;
        g.swing.t = 0;
        // geri dönmə: bu vaxtdan sonra stəkan ekranın ortasındakı flipX nöqtəsindən keçəndə dönür
        const flip = tricky && Math.random() < TRICK_FLIP;
        g.swing.flipAfter = flip ? rnd(0.2, 1.4) : Infinity;
        g.swing.flipX = rnd(GW + 70, LW - GW - 70);
        g.swing.warn = false;
    }

    const topBottomY = () => BASE_Y - (g.tower.length - 1) * GH; // ən üst stəkanın dibi
    const swingY = () => topBottomY() - GH - SWING_GAP;           // yellənən stəkanın dibi

    /* ---------- Səs ---------- */
    // Səslər fayl yükləmədən, Web Audio API ilə yaradılır: stəkan cingiltisi və şüşə sınması.
    const sound = (() => {
        let ac = null;
        let muted = false;
        try { muted = localStorage.getItem('stackMuted') === '1'; } catch (e) {}

        function ctxOk() {
            if (muted) return null;
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return null;
            if (!ac) ac = new AC();
            if (ac.state === 'suspended') ac.resume();
            return ac;
        }

        /** Stəkan cingiltisi: bir neçə yüksək, tez sönən ton (şüşənin qeyri-harmonik obertonları) */
        function clink(pitch = 1, vol = 0.5) {
            const a = ctxOk();
            if (!a) return;
            const t = a.currentTime;
            const out = a.createGain();
            out.gain.value = vol;
            out.connect(a.destination);
            const base = 1650 * pitch * (0.97 + Math.random() * 0.06);
            [[1, 0.5, 0.45], [2.32, 0.3, 0.3], [4.25, 0.16, 0.18], [6.63, 0.08, 0.1]].forEach(([k, amp, dur]) => {
                const o = a.createOscillator();
                const gn = a.createGain();
                o.type = 'sine';
                o.frequency.value = base * k;
                gn.gain.setValueAtTime(0, t);
                gn.gain.linearRampToValueAtTime(amp, t + 0.004);
                gn.gain.exponentialRampToValueAtTime(0.0001, t + dur);
                o.connect(gn).connect(out);
                o.start(t);
                o.stop(t + dur + 0.02);
            });
            noise(a, out, t, 0.025, 3000, 0.25); // toxunma anının qısa "tıq" səsi
        }

        /** Süzgəcdən keçmiş ağ küy */
        function noise(a, out, t, dur, freq, amp) {
            const len = Math.ceil(a.sampleRate * dur);
            const buf = a.createBuffer(1, len, a.sampleRate);
            const d = buf.getChannelData(0);
            for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
            const src = a.createBufferSource();
            src.buffer = buf;
            const f = a.createBiquadFilter();
            f.type = 'highpass';
            f.frequency.value = freq;
            const gn = a.createGain();
            gn.gain.setValueAtTime(amp, t);
            gn.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            src.connect(f).connect(gn).connect(out);
            src.start(t);
        }

        /** Şüşə sınması: küy partlayışı + səpələnən xırda cingiltilər */
        let lastCrash = -Infinity;
        function crash() {
            const a = ctxOk();
            if (!a) return;
            const t = a.currentTime;
            const now = performance.now();
            if (now - lastCrash < 50) return; // eyni anda çox stəkan sınanda səs çox gurlaşmasın
            lastCrash = now;
            const out = a.createGain();
            out.gain.value = 0.45;
            out.connect(a.destination);
            noise(a, out, t, 0.35, 1800, 0.7);
            for (let i = 0; i < 6; i++) {
                const dt = Math.random() * 0.18;
                setTimeout(() => clink(1.4 + Math.random() * 1.6, 0.12), dt * 1000);
            }
        }

        /** Uzaqdan gələn ildırım gurultusu: alçaq tezlikli, yavaş sönən küy */
        function thunder() {
            const a = ctxOk();
            if (!a) return;
            const t = a.currentTime, dur = 2.2;
            const len = Math.ceil(a.sampleRate * dur);
            const buf = a.createBuffer(1, len, a.sampleRate);
            const d = buf.getChannelData(0);
            for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
            const src = a.createBufferSource();
            src.buffer = buf;
            const f = a.createBiquadFilter();
            f.type = 'lowpass';
            f.frequency.value = 160;
            const gn = a.createGain();
            gn.gain.setValueAtTime(0.0001, t);
            gn.gain.exponentialRampToValueAtTime(0.9, t + 0.15);
            gn.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            src.connect(f).connect(gn).connect(a.destination);
            src.start(t);
        }

        return {
            clink, crash, thunder,
            get muted() { return muted; },
            // səs artıq oyunçunun toxunuşu ilə açılıbmı (brauzer səsi yalnız toxunuşdan sonra icazə verir)
            get started() { return !!ac && ac.state === 'running'; },
            toggle() {
                muted = !muted;
                try { localStorage.setItem('stackMuted', muted ? '1' : '0'); } catch (e) {}
                return muted;
            },
        };
    })();

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
        // fotodakı stəkan (ilk stəkan) əldədir — o düşmür
        const keepBase = baseInPhoto();
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
        }).filter((f, i) => !(keepBase && i === 0));
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
        // ton həmişə eynidir; «Əla!» bir az ucadır
        sound.clink(1, perfect ? 0.6 : 0.45);
        g.tower = tower;
        // qonanda çay çalxalanır: kənara qonduqca daha güclü, istiqaməti sapma tərəfinə görə.
        // Zərbə alttakı stəkana da bir az ötürülür.
        const side = d ? Math.sign(d) : (Math.random() < 0.5 ? -1 : 1);
        const power = LAND_KICK * (0.7 + Math.min(0.6, ad / GW * 2));
        tower[tower.length - 1].s = {a: g.slosh.a, v: g.slosh.v - side * power};
        const below = tower[tower.length - 2];
        if (below.s) below.s.v -= side * power * 0.4; else below.s = {a: 0, v: -side * power * 0.4};
        g.slosh = {a: 0, v: 0};
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
        newSwing();
        if (placed() === BOOST_FROM) {
            g.popups.push({text: 'Diqqət! Sürət artdı!', x: LW / 2, y: topBottomY() - GH - 60, life: 2, warn: true});
        }

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
        sound.crash();
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

    /* ---------- Çayın çalxalanması ---------- */
    // Stəkan yellənəndə çay düz qalır. Yalnız divara dəyəndə çalxalanır:
    // divar tərəfə çırpılır, sonra o yan-bu yana yırğalanıb yenə düzəlir (yay / spring modeli).
    const SLOSH_K = 140, SLOSH_D = 4.5, SLOSH_MAX = 0.55;
    const SLOSH_KICK = 6.5; // divara dəyəndə çaya verilən təkan — çay o yan-bu yana çalxalanır
    const LAND_KICK = 4;    // stəkan qüllənin üstünə qonanda çaya verilən təkan

    /** Stəkan divara dəydi: çay hərəkəti davam etdirib divar tərəfə çırpılır, sonra geri qayıdır */
    function wallHit(dirBefore) {
        g.slosh.v -= dirBefore * SLOSH_KICK * (0.85 + Math.random() * 0.3);
    }
    const clamp = (v, m) => Math.max(-m, Math.min(m, v));

    function springStep(s, target, dt) {
        s.v += (-SLOSH_K * (s.a - target) - SLOSH_D * s.v) * dt;
        s.a = clamp(s.a + s.v * dt, SLOSH_MAX);
    }

    function updateSlosh(dt) {
        if (!(dt > 0)) return;
        springStep(g.slosh, 0, dt);
        for (const t of g.tower) if (t.s) springStep(t.s, 0, dt); // qüllədəki stəkanlar
    }

    function update(dt) {
        g.time += dt;
        updateSlosh(dt);

        if (g.state === 'swing') {
            const min = GW / 2 + 8, max = LW - GW / 2 - 8;
            const v = Math.min(SPEED_CAP, speed() * boost() * g.swing.mul);
            g.swing.t += dt;
            const ahead = (g.swing.flipX - g.swing.x) * g.swing.dir; // dönmə nöqtəsinə qalan məsafə (qabaqdadırsa > 0)
            const armed = g.swing.t + TRICK_WARN >= g.swing.flipAfter;
            g.swing.warn = armed && ahead > 0 && ahead < v * TRICK_WARN; // dönməyə az qalıb — titrəyir
            const nx = g.swing.x + g.swing.dir * v * dt;
            if (armed && g.swing.t >= g.swing.flipAfter && ahead >= 0 && (g.swing.flipX - nx) * g.swing.dir <= 0) {
                g.swing.x = g.swing.flipX;
                g.swing.dir *= -1;
                g.swing.flipAfter = Infinity; // bir stəkanda ən çox bir dəfə
                g.swing.warn = false;
            } else {
                g.swing.x = nx;
            }
            if (g.swing.x > max) { g.swing.x = max; g.swing.dir = -1; wallHit(1); }
            if (g.swing.x < min) { g.swing.x = min; g.swing.dir = 1; wallHit(-1); }
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

    const RIM_RY = 3.5; // ağzın yumruluğu (ellipsin şaquli radiusu) — ağıza azca yuxarıdan baxırıq
    const RIM_W = 0.44; // ağzın yarım eni (GW-yə nisbətən)
    function glassPath(w, h) {
        const p = new Path2D();
        // Armudu forma: dar dib, aşağıda yumru qarın, incə bel, yuxarıda açılan ağız
        const b = 0.26 * w; // dib
        p.moveTo(-b, 0);
        p.bezierCurveTo(-0.46 * w, -0.03 * h, -0.5 * w, -0.36 * h, -0.33 * w, -0.58 * h);  // qarın (nazik)
        p.bezierCurveTo(-0.28 * w, -0.66 * h, -0.31 * w, -0.86 * h, -RIM_W * w, -h);      // bel və ağız
        p.ellipse(0, -h, RIM_W * w, RIM_RY, 0, Math.PI, 0, true);                         // yumru ağzın ön tərəfi
        p.bezierCurveTo(0.31 * w, -0.86 * h, 0.28 * w, -0.66 * h, 0.33 * w, -0.58 * h);
        p.bezierCurveTo(0.5 * w, -0.36 * h, 0.46 * w, -0.03 * h, b, 0);
        p.closePath();
        return p;
    }
    const GLASS = glassPath(GW, GH);

    // Arxa fon şəkli (data-bg-photo). PHOTO_IS_BASE = true olanda fotodakı stəkan qüllənin ilk stəkanıdır:
    // oyunçunun stəkanları onun ağzının üstünə düşür. PHOTO_RIM — həmin stəkanın ağzının mərkəzi
    // (şəklin eninə və hündürlüyünə nisbətən), PHOTO_TOP — şəklin yuxarı kənarı (dünya koordinatında).
    const BG = {photo: new Image(), cloth: new Image()};
    BG.photo.src = root.dataset.bgPhoto;
    if (root.dataset.cloth) BG.cloth.src = root.dataset.cloth;
    const BG_DARKEN = 0.35;     // arxa fon şəklinin qaraldılması (0 — olduğu kimi, 1 — tam qara)
    const CLOTH_FOCUS_Y = 0.53; // süfrədə şəklin hansı hündürlüyü görünsün (0 — yuxarı, 1 — aşağı); ortada medalyon
    const PHOTO_RIM = {x: 0.516, y: 0.673};
    const PHOTO_TOP = -8;
    const photoReady = () => BG.photo.complete && BG.photo.naturalWidth > 0;
    const baseInPhoto = () => PHOTO_IS_BASE && photoReady();

    /** Fotonun dünyadakı yeri: stəkanın ağzı ilk qonacaq stəkanın dibinə (BASE_Y - GH) düşür */
    function photoRect() {
        const rimY = BASE_Y - GH;
        const dh = (rimY - PHOTO_TOP) / PHOTO_RIM.y;
        const dw = dh * BG.photo.naturalWidth / BG.photo.naturalHeight;
        return {x: LW / 2 - dw * PHOTO_RIM.x, y: PHOTO_TOP, w: dw, h: dh};
    }

    /** Armudu stəkan: dibi (x, y) nöqtəsindədir */
    /**
     * tilt — çay səthinin stəkana nisbətən meyli (radian, + olanda sol tərəf yuxarı qalxır).
     * Stəkan fırlananda (rot) çay dünyaya nisbətən üfüqi qalsın deyə tilt-dən rot çıxılır.
     */
    /**
     * Qızılı medalyon naxışı: ogee (soğanvari) çərçivə, içində tor, yuxarıda və aşağıda xırda çiçəklər.
     * ox — mərkəzin x-i, k — ölçü, a — şəffaflıq. Stəkanın lokal koordinatlarında çəkilir.
     */
    function drawOrnament(ox, k, a) {
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
        ctx.save();
        ctx.globalAlpha *= a;
        ctx.strokeStyle = '#f2c35a';
        ctx.fillStyle = '#f2c35a';
        ctx.lineCap = 'round';

        // çərçivə: qalın xarici və incə daxili xətt
        ctx.lineWidth = 1.4;
        ctx.stroke(ogee(1));
        ctx.lineWidth = 0.7;
        const inner = ogee(0.62);
        ctx.stroke(inner);

        // daxildə çəpəki tor
        ctx.save();
        ctx.clip(inner);
        ctx.lineWidth = 0.5;
        ctx.beginPath();
        for (let d = -GH; d <= GH; d += 3.2) {
            ctx.moveTo(ox - GW, mid + d - GW); ctx.lineTo(ox + GW, mid + d + GW);
            ctx.moveTo(ox - GW, mid + d + GW); ctx.lineTo(ox + GW, mid + d - GW);
        }
        ctx.stroke();
        ctx.restore();

        // yanlarda qıvrımlar
        ctx.lineWidth = 0.8;
        for (const sd of [-1, 1]) {
            ctx.beginPath();
            ctx.arc(ox + sd * hw * 0.95, mid, hw * 0.28, 0, Math.PI * 2);
            ctx.stroke();
        }

        // yuxarıda və aşağıda xırda çiçəklər
        for (const fy of [top - 3.2 * k, bot + 3.2 * k]) {
            for (let i = 0; i < 5; i++) {
                const ang = i * Math.PI * 2 / 5 - Math.PI / 2;
                ctx.beginPath();
                ctx.arc(ox + Math.cos(ang) * 1.5 * k, fy + Math.sin(ang) * 1.5 * k, 0.9 * k, 0, Math.PI * 2);
                ctx.fill();
            }
        }
        ctx.restore();
    }

    function drawGlass(x, y, rot = 0, alpha = 1, tilt = 0) {
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
        // çay səthinin altındakı yarımmüstəvi: səth (0, sy) nöqtəsindən keçir, a bucağı ilə əyilir
        const a = tilt - rot, sy = -GH * 0.74, L = GH * 2;
        const cx = Math.cos(a), cy = Math.sin(a), nx = -cy, ny = cx; // nx, ny — səthdən "aşağı" yön
        const teaPath = new Path2D();
        teaPath.moveTo(-L * cx, sy - L * cy);
        teaPath.lineTo(L * cx, sy + L * cy);
        teaPath.lineTo(L * cx + L * nx, sy + L * cy + L * ny);
        teaPath.lineTo(-L * cx + L * nx, sy - L * cy + L * ny);
        teaPath.closePath();
        ctx.fillStyle = tea;
        ctx.fill(teaPath);
        // işığın çaydan keçməsi — ortada açıq kəhrəba parıltı
        const glow = ctx.createRadialGradient(-GW * 0.08, -GH * 0.3, 2, -GW * 0.08, -GH * 0.3, GW * 0.42);
        glow.addColorStop(0, 'rgba(255, 110, 40, .35)');
        glow.addColorStop(1, 'rgba(255, 110, 40, 0)');
        ctx.fillStyle = glow;
        ctx.fill(teaPath);
        ctx.strokeStyle = 'rgba(255, 190, 120, .6)'; // çayın səthi
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(-L * cx + nx, sy - L * cy + ny);
        ctx.lineTo(L * cx + nx, sy + L * cy + ny);
        ctx.stroke();

        // şüşənin üzərində qızılı naxış (çayın üstündən görünür)
        drawOrnament(0, 1, 1);                    // ön tərəfdə əsas medalyon
        drawOrnament(-GW * 0.4, 0.62, 0.45);      // yanlarda yarımçıq görünən medalyonlar
        drawOrnament(GW * 0.4, 0.62, 0.45);
        ctx.restore();

        // şüşənin kənarı
        ctx.lineWidth = 2;
        ctx.strokeStyle = 'rgba(255, 255, 255, .95)';
        ctx.stroke(GLASS);

        // yumru ağız: içi azca görünür, arxa kənar incə, ön kənar qalın qızılı haşiyə
        const rw = GW * RIM_W;
        ctx.fillStyle = 'rgba(255, 255, 255, .18)';
        ctx.beginPath();
        ctx.ellipse(0, -GH, rw, RIM_RY, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = 'rgba(233, 162, 59, .75)';
        ctx.lineWidth = 1.2;
        ctx.beginPath();
        ctx.ellipse(0, -GH, rw, RIM_RY, 0, Math.PI, Math.PI * 2);
        ctx.stroke();
        ctx.strokeStyle = '#E9A23B';
        ctx.lineWidth = 2.2;
        ctx.beginPath();
        ctx.ellipse(0, -GH, rw, RIM_RY, 0, 0, Math.PI);
        ctx.stroke();

        // qızılı ayaq
        ctx.fillStyle = '#E9A23B';
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

    /**
     * Masanın üstündə süfrə: yumşaq qırışlarla aşağı sallanır, alt kənarı dalğalıdır.
     * Xalça naxışlı şəkil (data-cloth) yüklənibsə parça onunla örtülür, yoxsa düz qırmızıdır.
     */
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

        const carpet = BG.cloth.complete && BG.cloth.naturalWidth > 0;
        if (carpet) {
            // xalça naxışlı parça: şəklin ortasındakı medalyonun keçdiyi zolaq süfrənin eninə yayılır
            const W2 = x1 - x0 + flare * 2, H2 = bottom - top + 12;
            const iw = BG.cloth.naturalWidth, ih = iw * H2 / W2;
            const iy = Math.max(0, Math.min(BG.cloth.naturalHeight - ih, BG.cloth.naturalHeight * CLOTH_FOCUS_Y - ih / 2));
            ctx.drawImage(BG.cloth, 0, iy, iw, ih, x0 - flare, top, W2, H2);
            // qırışlar: açıq-tünd növbələşən zolaqlar parçanı həcmli göstərir
            const folds2 = ctx.createLinearGradient(x0 - flare, 0, x1 + flare, 0);
            for (let i = 0; i <= folds * 2; i++) {
                folds2.addColorStop(i / (folds * 2), i % 2 ? 'rgba(0, 0, 0, .14)' : 'rgba(255, 255, 255, .06)');
            }
            ctx.fillStyle = folds2;
            ctx.fillRect(x0 - flare, top, W2, H2);
        }

        // yuxarıdan aşağı işıq-kölgə: masanın kənarı parlaq, aşağı tünd
        const depth = ctx.createLinearGradient(0, top, 0, bottom + 8);
        depth.addColorStop(0, 'rgba(255, 255, 255, .18)');
        depth.addColorStop(0.15, 'rgba(255, 255, 255, 0)');
        depth.addColorStop(1, carpet ? 'rgba(20, 5, 0, .35)' : 'rgba(60, 0, 0, .28)');
        ctx.fillStyle = depth;
        ctx.fillRect(x0 - flare, top, x1 - x0 + flare * 2, bottom - top + 10);

        // qırışların incə xətləri
        ctx.strokeStyle = carpet ? 'rgba(30, 10, 0, .25)' : 'rgba(110, 10, 10, .22)';
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

        // masanın üst kənarı — parçanın qatlandığı yer (xalça naxışlıda qızılı haşiyə)
        ctx.fillStyle = carpet ? '#d9a237' : '#f04a43';
        ctx.fillRect(x0, top - 1, x1 - x0, 3);
    }

    function drawScene() {
        const cam = g.camY;
        const sx = g.shake ? Math.sin(g.time * 60) * g.shake : 0;

        // səma: fotodakı axşam səmasının rəngi, yuxarı qalxdıqca gecəyə keçir
        const h = Math.min(1, cam / 2500);
        const bg = ctx.createLinearGradient(0, 0, 0, LH);
        bg.addColorStop(0, mix('#4b5c7c', '#141028', h));
        bg.addColorStop(1, mix('#7b8daf', '#2e1838', h));
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, LW, LH);

        // adi rejim: şəkil bütün kətanı örtür (cover), qüllə böyüdükcə yavaş aşağı sürüşür,
        // yuxarı qalxdıqca üstünə gecə rəngi çökür
        if (!PHOTO_IS_BASE && photoReady()) {
            const dh = LH * 1.15;
            const dw = Math.max(LW, dh * BG.photo.naturalWidth / BG.photo.naturalHeight);
            ctx.drawImage(BG.photo, LW / 2 - dw / 2, LH - dh + Math.min(LH * 0.15, cam * 0.08), dw, dh);
            ctx.fillStyle = `rgba(0, 0, 0, ${BG_DARKEN})`; // şəkli bir az qaraldırıq ki, stəkanlar önə çıxsın
            ctx.fillRect(0, 0, LW, LH);
            if (h > 0) {
                ctx.save();
                ctx.globalAlpha = h * 0.85;
                ctx.fillStyle = bg;
                ctx.fillRect(0, 0, LW, LH);
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

        weatherBack(); // günəş / ay, buludlar, duman — qüllənin arxasında

        ctx.save();
        ctx.translate(0, cam);

        // foto qüllə ilə birlikdə hərəkət edir (fotodakı stəkan qüllənin dibidir);
        // yuxarı kənarı səmaya yumşaq keçir
        if (baseInPhoto()) {
            const r = photoRect();
            ctx.drawImage(BG.photo, r.x, r.y, r.w, r.h);
            const fade = ctx.createLinearGradient(0, r.y, 0, r.y + 80);
            fade.addColorStop(0, '#4b5c7c');
            fade.addColorStop(1, 'rgba(75, 92, 124, 0)');
            ctx.fillStyle = fade;
            ctx.fillRect(0, r.y - 1, LW, 81);
        }

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

        // masa və nəlbəki — yalnız foto yüklənməyibsə (fotoda stəkan və nəlbəki artıq var)
        if (!baseInPhoto()) {
        const table = ctx.createLinearGradient(0, BASE_Y + 8, 0, BASE_Y + 80);
        table.addColorStop(0, '#8a5228');
        table.addColorStop(1, '#4a250f');
        ctx.fillStyle = table;
        ctx.fillRect(0, BASE_Y + 8, LW, 400);
        drawCloth();
        // nəlbəki: şəffaf büllur, qızılı haşiyə, mərkəzdən kənara şüalanan zolaqlar
        const scx = LW / 2, scy = BASE_Y + 4, srx = GW * 0.85, sry = 9;
        ctx.fillStyle = 'rgba(235, 240, 245, .55)';
        ctx.beginPath();
        ctx.ellipse(scx, scy, srx, sry, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = 'rgba(255, 255, 255, .7)';
        ctx.lineWidth = 0.8;
        ctx.beginPath();
        for (let i = 0; i < 36; i++) {
            const ang = i * Math.PI * 2 / 36;
            ctx.moveTo(scx + Math.cos(ang) * srx * 0.5, scy + Math.sin(ang) * sry * 0.5);
            ctx.lineTo(scx + Math.cos(ang) * srx * 0.92, scy + Math.sin(ang) * sry * 0.92);
        }
        ctx.stroke();
        ctx.strokeStyle = 'rgba(150, 160, 170, .6)';
        ctx.beginPath();
        ctx.ellipse(scx, scy, srx * 0.48, sry * 0.48, 0, 0, Math.PI * 2);
        ctx.stroke();
        ctx.strokeStyle = '#E9A23B';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.ellipse(scx, scy, srx, sry, 0, 0, Math.PI * 2);
        ctx.stroke();
        }

        // masada dağılmış çay ləkələri
        for (const p of g.puddles) {
            ctx.fillStyle = `rgba(170, 86, 28, ${p.a * 0.62})`;
            ctx.beginPath();
            ctx.ellipse(p.x, FLOOR + 3, p.r, p.r * 0.22, 0, 0, Math.PI * 2);
            ctx.fill();
        }

        // qüllə
        // ilk stəkan fotodakı stəkandır — foto varsa onu çəkmirik
        g.tower.forEach((t, i) => {
            if (i === 0 && baseInPhoto()) return;
            drawGlass(t.x + sx * (i / Math.max(1, g.tower.length)), BASE_Y - i * GH, 0, 1, t.s ? t.s.a : 0);
        });

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
            const wobble = g.swing.warn ? Math.sin(g.time * 45) * 0.12 : 0; // geri dönəcək — titrəyir
            drawGlass(g.swing.x, swingY(), wobble, 1, g.slosh.a);
        }
        if (g.state === 'drop') drawGlass(g.drop.x, g.drop.y, 0, 1, g.slosh.a);
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
            ctx.font = p.warn ? '900 17px Nunito, sans-serif' : '900 20px Nunito, sans-serif';
            ctx.textAlign = 'center';
            ctx.lineJoin = 'round';
            ctx.lineWidth = 4;
            ctx.strokeStyle = 'rgba(30, 8, 8, .85)'; // tünd fonda da oxunsun deyə kontur
            ctx.strokeText(p.text, p.x, p.y);
            ctx.fillStyle = p.warn ? '#ff7a6b' : '#ffd27a';
            ctx.fillText(p.text, p.x, p.y);
            ctx.globalAlpha = 1;
        });
        ctx.restore();

        weatherFront(); // yağış, qar, uçan yarpaqlar, şimşək — qüllənin önündə

        // hesab
        ctx.textAlign = 'center';
        ctx.save();
        ctx.shadowColor = 'rgba(0, 0, 0, .6)';  // tünd fonda oxunsun
        ctx.shadowBlur = 6;
        ctx.fillStyle = '#fff';
        ctx.font = '900 44px Nunito, sans-serif';
        ctx.fillText(g.score, LW / 2, 58);
        ctx.font = '700 13px Nunito, sans-serif';
        ctx.globalAlpha = 0.7;
        ctx.fillText(`Rekord: ${Math.max(best, g.score)}`, LW / 2, 80);
        ctx.restore();
    }

    function mix(a, b, t) {
        const pa = a.match(/\w\w/g).map(x => parseInt(x, 16));
        const pb = b.match(/\w\w/g).map(x => parseInt(x, 16));
        return 'rgb(' + pa.map((v, i) => Math.round(v + (pb[i] - v) * t)).join(',') + ')';
    }

    /* ---------- Bakının havası ---------- */
    // Server Bakının indiki havasını verir (data-weather): {kind, wind, windDir, windy, temp, day, label}.
    // kind: clear | clouds | rain | storm | snow | fog. Effektlər yalnız görünüşdür, oyunun gedişinə təsir etmir.
    let W = null;
    try { W = JSON.parse(root.dataset.weather || 'null'); } catch (e) { W = null; }
    const fx = {drops: [], flakes: [], leaves: [], fog: [], stars: [], flash: 0, bolt: null, nextBolt: 4, t: 0};
    let windX = 0; // -1..1: küləyin ekranda istiqaməti və gücü

    function initWeather() {
        fx.drops = []; fx.flakes = []; fx.leaves = []; fx.fog = []; fx.stars = [];
        fx.flash = 0; fx.bolt = null; fx.nextBolt = rnd(3, 6);
        if (!W) return;
        // küləyin gəldiyi tərəf (dərəcə) — şərqdən əsəndə sola, qərbdən əsəndə sağa
        const s = Math.sin(W.windDir * Math.PI / 180);
        windX = (Math.abs(s) < 0.3 ? 1 : -Math.sign(s)) * Math.min(1, W.wind / 50);
        const k = W.kind;
        const rain = k === 'rain' ? 80 : k === 'storm' ? 130 : 0;
        for (let i = 0; i < rain; i++) fx.drops.push(newDrop(true));
        if (k === 'snow') for (let i = 0; i < 70; i++) fx.flakes.push(newFlake(true));
        if (k === 'fog') for (let i = 0; i < 4; i++) fx.fog.push({x: rnd(0, LW), y: 140 + i * 95, w: rnd(320, 460)});
        if (W.windy) for (let i = 0; i < 16; i++) fx.leaves.push(newLeaf(true));
        if (!W.day) for (let i = 0; i < 28; i++) fx.stars.push({x: rnd(0, LW), y: rnd(10, 260), p: rnd(0, 6)});
    }

    function newDrop(any) {
        return {x: rnd(-80, LW + 80), y: any ? rnd(-LH, LH) : rnd(-60, -10), len: rnd(10, 17), v: rnd(520, 720)};
    }
    function newFlake(any) {
        return {x: rnd(-40, LW + 40), y: any ? rnd(-LH, LH) : rnd(-30, -5), r: rnd(1, 2.6), v: rnd(28, 62), p: rnd(0, 6)};
    }
    function newLeaf(any) {
        const dir = windX < 0 ? -1 : 1;
        return {
            x: any ? rnd(0, LW) : (dir > 0 ? rnd(-60, -10) : rnd(LW + 10, LW + 60)), y: rnd(60, LH - 80),
            v: rnd(120, 230) * dir, p: rnd(0, 6), rot: rnd(0, 6), vr: rnd(-6, 6), s: rnd(5, 9),
            c: ['#c9831f', '#a0522d', '#d9a237', '#8b5a2b'][Math.floor(rnd(0, 4))],
        };
    }

    function updateWeather(dt) {
        if (!W || !(dt > 0)) return;
        fx.t += dt;
        for (const d of fx.drops) {
            d.y += d.v * dt;
            d.x += windX * d.v * 0.45 * dt;
            if (d.y > LH + 20) Object.assign(d, newDrop(false));
        }
        for (const f of fx.flakes) {
            f.y += f.v * dt;
            f.x += (windX * 90 + Math.sin(fx.t * 1.6 + f.p) * 18) * dt;
            if (f.y > LH + 10 || f.x < -60 || f.x > LW + 60) Object.assign(f, newFlake(false));
        }
        for (const l of fx.leaves) {
            l.x += l.v * dt;
            l.y += Math.sin(fx.t * 3 + l.p) * 40 * dt;
            l.rot += l.vr * dt;
            if (l.x < -80 || l.x > LW + 80) Object.assign(l, newLeaf(false));
        }
        for (const f of fx.fog) {
            f.x += (8 + Math.abs(windX) * 20) * dt;
            if (f.x - f.w / 2 > LW) f.x = -f.w / 2;
        }
        // ildırım: təsadüfi fasilələrlə şimşək çaxır, bir az sonra gurultu gəlir
        if (W.kind === 'storm') {
            fx.nextBolt -= dt;
            if (fx.nextBolt <= 0) {
                fx.flash = 1;
                fx.nextBolt = rnd(4, 9);
                const pts = [[rnd(60, LW - 60), 0]];
                while (pts[pts.length - 1][1] < rnd(220, 330)) {
                    const [px, py] = pts[pts.length - 1];
                    pts.push([px + rnd(-22, 22), py + rnd(18, 34)]);
                }
                fx.bolt = pts;
                if (sound.started) setTimeout(() => sound.thunder(), rnd(250, 700));
            }
            fx.flash = Math.max(0, fx.flash - dt * 2.8);
        }
    }

    function weatherBack() {
        if (!W) return;
        const k = W.kind;
        // ümumi çalar: gecə tündləşir, yağışda boz-mavi, günəşli gündə isti
        const tint = !W.day ? 'rgba(8, 12, 40, .42)'
            : k === 'storm' ? 'rgba(20, 26, 40, .38)'
            : k === 'rain' ? 'rgba(40, 55, 75, .25)'
            : k === 'fog' ? 'rgba(200, 205, 210, .18)'
            : k === 'clear' ? 'rgba(255, 196, 110, .08)' : null;
        if (tint) { ctx.fillStyle = tint; ctx.fillRect(0, 0, LW, LH); }

        if (k === 'clear' && W.day) {
            // günəş: isti parıltı, yavaş fırlanan şüalar
            const sx = LW - 78, sy = 150;
            const glow = ctx.createRadialGradient(sx, sy, 8, sx, sy, 120);
            glow.addColorStop(0, 'rgba(255, 226, 140, .55)');
            glow.addColorStop(1, 'rgba(255, 226, 140, 0)');
            ctx.fillStyle = glow;
            ctx.fillRect(0, 0, LW, LH);
            ctx.save();
            ctx.translate(sx, sy);
            ctx.rotate(fx.t * 0.15);
            ctx.strokeStyle = 'rgba(255, 228, 150, .35)';
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.beginPath();
            for (let i = 0; i < 12; i++) {
                const a = i * Math.PI / 6, r1 = 30, r2 = 48 + (i % 2) * 14;
                ctx.moveTo(Math.cos(a) * r1, Math.sin(a) * r1);
                ctx.lineTo(Math.cos(a) * r2, Math.sin(a) * r2);
            }
            ctx.stroke();
            ctx.restore();
            ctx.fillStyle = 'rgba(255, 232, 160, .95)';
            ctx.beginPath(); ctx.arc(sx, sy, 22, 0, Math.PI * 2); ctx.fill();
        }
        if (!W.day) {
            // gecə: sayrışan ulduzlar və ay
            for (const s of fx.stars) {
                ctx.fillStyle = `rgba(255, 255, 255, ${0.35 + 0.4 * Math.abs(Math.sin(fx.t * 1.5 + s.p))})`;
                ctx.fillRect(s.x, s.y, 2, 2);
            }
            if (k === 'clear' || k === 'clouds') {
                const mx = 70, my = 140;
                const glow = ctx.createRadialGradient(mx, my, 4, mx, my, 60);
                glow.addColorStop(0, 'rgba(250, 245, 210, .4)');
                glow.addColorStop(1, 'rgba(250, 245, 210, 0)');
                ctx.fillStyle = glow;
                ctx.fillRect(0, 0, LW, LH);
                ctx.fillStyle = '#f4f0cf';
                ctx.beginPath(); ctx.arc(mx, my, 16, 0, Math.PI * 2); ctx.fill();
            }
        }
        for (const f of fx.fog) {
            const gr = ctx.createLinearGradient(0, f.y - 40, 0, f.y + 40);
            gr.addColorStop(0, 'rgba(235, 238, 240, 0)');
            gr.addColorStop(0.5, 'rgba(235, 238, 240, .45)');
            gr.addColorStop(1, 'rgba(235, 238, 240, 0)');
            ctx.fillStyle = gr;
            ctx.fillRect(f.x - f.w / 2, f.y - 40, f.w, 80);
            ctx.fillRect(f.x - f.w / 2 - LW - 60, f.y - 40, f.w, 80); // ekranın o biri tərəfindən davamı
        }
    }

    function weatherFront() {
        if (!W) return;
        if (fx.drops.length) {
            ctx.strokeStyle = 'rgba(205, 222, 255, .55)';
            ctx.lineWidth = 1.2;
            ctx.beginPath();
            for (const d of fx.drops) {
                ctx.moveTo(d.x, d.y);
                ctx.lineTo(d.x - windX * d.len * 0.45, d.y - d.len); // külək yağışı maili edir
            }
            ctx.stroke();
        }
        if (fx.flakes.length) {
            ctx.fillStyle = 'rgba(255, 255, 255, .9)';
            ctx.beginPath();
            for (const f of fx.flakes) {
                ctx.moveTo(f.x + f.r, f.y);
                ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2);
            }
            ctx.fill();
        }
        for (const l of fx.leaves) {
            ctx.save();
            ctx.translate(l.x, l.y);
            ctx.rotate(l.rot);
            ctx.fillStyle = l.c;
            ctx.beginPath();
            ctx.ellipse(0, 0, l.s, l.s * 0.45, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = 'rgba(60, 30, 10, .6)'; // yarpağın orta damarı
            ctx.lineWidth = 0.8;
            ctx.beginPath();
            ctx.moveTo(-l.s, 0);
            ctx.lineTo(l.s, 0);
            ctx.stroke();
            ctx.restore();
        }
        if (fx.flash > 0) {
            ctx.fillStyle = `rgba(235, 240, 255, ${fx.flash * 0.5})`;
            ctx.fillRect(0, 0, LW, LH);
            if (fx.bolt && fx.flash > 0.45) {
                ctx.strokeStyle = `rgba(255, 255, 255, ${fx.flash})`;
                ctx.lineWidth = 2.5;
                ctx.shadowColor = 'rgba(180, 200, 255, .9)';
                ctx.shadowBlur = 12;
                ctx.beginPath();
                fx.bolt.forEach(([x, y], i) => (i ? ctx.lineTo(x, y) : ctx.moveTo(x, y)));
                ctx.stroke();
                ctx.shadowBlur = 0;
            }
        }
        // sol yuxarıda havanın qısa təsviri
        const icon = {clear: W.day ? '☀️' : '🌙', clouds: '☁️', rain: '🌧️', storm: '⛈️', snow: '❄️', fog: '🌫️'}[W.kind] || '';
        ctx.save();
        ctx.textAlign = 'left';
        ctx.shadowColor = 'rgba(0, 0, 0, .7)';
        ctx.shadowBlur = 4;
        ctx.fillStyle = '#fff';
        ctx.font = '800 13px Nunito, sans-serif';
        ctx.fillText(`${icon}${W.windy ? ' 💨' : ''} Bakı${W.temp !== null ? ` ${W.temp}°C` : ''}`, 12, 24);
        ctx.font = '700 11px Nunito, sans-serif';
        ctx.globalAlpha = 0.85;
        ctx.fillText(W.label, 12, 40);
        ctx.restore();
    }

    initWeather();
    // baxış rejimi (?hava=...) yoxdursa, canlı havanı səhifə açılandan sonra gətiririk
    if (!W && root.dataset.weatherUrl) {
        fetch(root.dataset.weatherUrl, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
            .then(r => (r.ok ? r.json() : null))
            .then(d => { if (d && d.weather) { W = d.weather; initWeather(); } })
            .catch(() => { /* hava gəlmədi — oyun effektsiz davam edir */ });
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

    const stage = root.querySelector('.st-stage');
    const fullBtn = root.querySelector('.st-full');

    function resize() {
        let w;
        if (root.classList.contains('is-full')) {
            // böyük ekran: kətan ekrana sığsın, nisbət (400×560) qorunsun
            w = Math.floor(Math.min(window.innerWidth, window.innerHeight * LW / LH));
            stage.style.width = w + 'px';
        } else {
            stage.style.width = '';
            w = stage.clientWidth;
        }
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
        updateWeather(dt);
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

    /* ---------- Səs düyməsi ---------- */
    const soundBtn = root.querySelector('.st-sound');
    function paintSound() {
        soundBtn.querySelector('i').className = sound.muted ? 'fa fa-volume-mute' : 'fa fa-volume-up';
        soundBtn.title = sound.muted ? 'Səsi aç' : 'Səsi bağla';
        soundBtn.setAttribute('aria-label', soundBtn.title);
    }
    soundBtn.addEventListener('click', () => {
        sound.toggle();
        paintSound();
        if (!sound.muted) sound.clink();
        soundBtn.blur();
    });
    paintSound();

    /* ---------- Böyük ekran ---------- */
    // Fullscreen API olmayan brauzerlərdə (məs. iPhone Safari) oyun CSS ilə ekranı tutur
    const nativeFull = () => document.fullscreenElement || document.webkitFullscreenElement;

    function setFull(on) {
        root.classList.toggle('is-full', on);
        document.body.classList.toggle('st-no-scroll', on);
        fullBtn.querySelector('i').className = on ? 'fa fa-compress' : 'fa fa-expand';
        fullBtn.title = on ? 'Kiçik ekran' : 'Böyük ekran';
        fullBtn.setAttribute('aria-label', fullBtn.title);
        resize();
    }

    function toggleFull() {
        const on = !root.classList.contains('is-full');
        if (on) {
            const req = root.requestFullscreen || root.webkitRequestFullscreen;
            if (req) {
                const p = req.call(root);
                if (p && p.catch) p.catch(() => {}); // icazə verilməsə də CSS rejimi işləyir
            }
            setFull(true);
        } else {
            if (nativeFull()) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            setFull(false);
        }
    }

    fullBtn.addEventListener('click', e => {
        toggleFull();
        fullBtn.blur(); // fokus düymədə qalmasın ki, Boşluq oyunda işləsin
    });
    // istifadəçi Esc ilə brauzerin tam ekranından çıxanda
    const onFullChange = () => { if (!nativeFull() && root.classList.contains('is-full')) setFull(false); };
    document.addEventListener('fullscreenchange', onFullChange);
    document.addEventListener('webkitfullscreenchange', onFullChange);
    window.addEventListener('keydown', e => {
        if (e.key === 'Escape' && root.classList.contains('is-full') && !nativeFull()) setFull(false);
    });

    resize();
    reset();
    requestAnimationFrame(frame);

    // Testlər üçün
    window.__stack = {
        state: () => g, tap, consts: {GW, PERFECT, SUPPORT, BOOST_FROM}, boost, newSwing, stability, checkPrize, continueGame,
        manual: on => { manual = on; },
        weather: () => ({W, windX, drops: fx.drops.length, flakes: fx.flakes.length, leaves: fx.leaves.length}),
        step: (dt = 1 / 60) => { if (g.state !== 'over' || g.falling) update(dt); },
    };
})();
