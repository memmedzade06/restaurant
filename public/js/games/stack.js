/*
 * Stəkan qülləsi — armudu stəkanları üst-üstə düzmək oyunu.
 *
 * Yuxarıda stəkan sağa-sola yellənir; toxunanda (klik / boşluq) düşür və qüllənin üstünə qonur.
 * Mərkəzə yaxın qonsa — qüllə böyüyür, çox kənara qonsa — stəkan aşır və oyun bitir.
 * Hər stəkan 1 xal verir; dəqiq qonanda "Əla!" yazısı çıxır (bonus xal yoxdur). Sürət hər xalda artır, 30 xaldan sonra daha kəskin.
 *
 * Bütün hesablamalar 400×560 ölçülü "məntiqi" koordinatlarda aparılır, kətan ekrana uyğun böyüdülür.
 */
(function () {
    'use strict';

    const root = document.getElementById('stack');
    if (!root) return;

    const canvas = root.querySelector('canvas');
    let ctx = canvas.getContext('2d'); // let: stəkanın hazır şəkli (sprite) çəkiləndə müvəqqəti başqa kətana yönəlir
    const overlay = root.querySelector('.st-result');
    const offer = root.querySelector('.st-offer');
    const startHint = root.querySelector('.st-start');
    const resumeBtn = root.querySelector('.st-resume');
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
    const GW = 44, GH = 61;            // stəkanın eni və hündürlüyü (nazik, hündür armudu)
    const BASE_Y = LH - 58;            // ilk stəkanın dibi (nəlbəkinin üstü)
    const SWING_GAP = 190;             // yellənən stəkanla qüllə arasındakı məsafə (böyük — stəkan ekranda daha yuxarıda yellənir)
    const RAISE_FROM = 20;             // bu qədər stəkan qoyulandan sonra yellənən stəkan yuxarı qalxır (daha uzağa düşür)
    const RAISE_BY = 55;               // nə qədər yuxarı qalxır (px)
    const VISIBLE_GLASSES = 2;         // qüllədən ekranda neçə stəkan görünür (ən üstdəkilər)
    // true — fondakı fotoda əldə tutulan stəkan qüllənin dibidir (Qız qalası fotosu kimi);
    // false — foto sadəcə arxa fondur, qüllə masadakı nəlbəkinin üstündə qurulur
    const PHOTO_IS_BASE = false;
    const FLOOR = BASE_Y + (PHOTO_IS_BASE ? 40 : 8); // düşən stəkan burada sınır
    const PERFECT = GW * 0.1;          // "Əla!" sayılan sapma — bu qədər yaxın qonan stəkan düz ortaya oturur
    const SUPPORT = GW * 0.58;         // üstündəki yükün dayaq sahəsi (ağız + bir az ehtiyat ki, oyun çox çətin olmasın)
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
            gap: SWING_GAP,           // yellənən stəkanla qüllə arasındakı indiki məsafə (RAISE_FROM-dan sonra rəvan artır)
            shake: 0,
            popups: [],
            time: 0,
            steam: {px: null, drift: 0}, // hərəkət edən stəkanın buğu: əvvəlki x və gecikən sürüşmə (px)
            finish: false,
            offered: {},              // bu oyunda təklif olunmuş hədiyyələr
            claiming: false,
            t0: 0,                    // oyunun başladığı an (performance.now)
            log: [],                  // hər qonan stəkan: [başlanğıcdan keçən ms, stəkanın sapması px] — server yoxlayır
            slosh: {a: 0, v: 0},   // yellənən/düşən stəkandakı çayın meyli
        };
        startFromRandomSide(); // birinci stəkan da sağdan və ya soldan
        newSwing();
        overlay.hidden = true;
        offer.hidden = true;
        overlay.querySelector('.st-prize').hidden = true;
        overlay.querySelector('.st-again').textContent = 'Yenidən oyna';
        resumeBtn.hidden = true;
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
    const BOOST_STEP = 0.015;        // sonrakı hər stəkanda əlavə artım (40-cı stəkanda ×1.4, 50-cidə ×1.55)
    const SPEED_CAP = 500;           // sürətin ən yuxarı həddi — 100 xala çatmaq mümkün qalsın
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
    // Yellənən stəkan sağdan və ya soldan (təsadüfi) çıxır və ekranın o biri tərəfinə doğru hərəkət edir
    function startFromRandomSide() {
        const fromLeft = Math.random() < 0.5;
        g.swing.dir = fromLeft ? 1 : -1;
        g.swing.x = fromLeft ? GW / 2 + 12 : LW - GW / 2 - 12;
    }

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
    const swingY = () => topBottomY() - GH - g.gap;               // yellənən stəkanın dibi

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
            g.t0 = performance.now();
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

        // hər stəkan 1 xal; dəqiq qonanda bonus yoxdur, sadəcə «Əla!» yazısı çıxır
        const gain = 1;
        let text = null;
        if (perfect) {
            g.combo++;
            text = g.combo >= 3 ? `Əla! ${g.combo} ardıcıl` : 'Əla!';
        } else {
            g.combo = 0;
            g.shake = Math.min(7, ad / 5);
        }
        // ton həmişə eynidir; «Əla!» bir az ucadır
        sound.clink(1, perfect ? 0.6 : 0.45);
        g.tower = tower;
        g.log.push([Math.round(performance.now() - g.t0), Math.round(d * 10) / 10]);
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
        // növbəti stəkan kənardan başlasın — hansı kənardan (sağ/sol), təsadüfi seçilir
        startFromRandomSide();
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
        // sonuncu hədəf — finiş: ən böyük hədiyyə; hədiyyəni götürdükdən sonra da rekord üçün davam etmək olur
        g.finish = g.score >= mins[mins.length - 1];
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
        cont.querySelector('span').textContent = isLast && cfg.canSave ? 'Hədiyyəsiz davam et' : 'Davam et';
        cont.hidden = false;

        if (!cfg.canSave) {
            note.innerHTML = `Hədiyyə kodu almaq üçün <a href="${cfg.registerUrl}">qeydiyyatdan keçin</a> və nömrənizi təsdiqləyin.`;
            accept.hidden = true;
        } else {
            accept.hidden = false;
            note.innerHTML = g.finish
                ? 'Finişə çatdınız! Hədiyyəni götürün — kod veriləcək, sonra rekord üçün oynamağa davam edə bilərsiniz.'
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

        const r = await post(cfg.prizeUrl, {score: g.score, log: g.log});
        const d = r ? await r.json().catch(() => ({})) : {};
        g.claiming = false;

        if (!r || !r.ok || !d.prize) {
            offer.querySelector('.st-offer-note').textContent = d.message || 'Xəta baş verdi. Yenidən cəhd edin.';
            accept.disabled = false;
            accept.querySelector('span').textContent = 'Hədiyyəni götür';
            if (r && r.status === 429) { cfg.prizeLeft = 0; accept.hidden = true; }
            if (r && r.status === 422) accept.hidden = true;
            // hədiyyə alınmadısa, oyunçu "Davam et" ilə oynamağa davam edə bilər
            offer.querySelector('.st-continue span').textContent = 'Davam et';
            return;
        }

        if (cfg.prizeLeft !== null) cfg.prizeLeft--;
        offer.hidden = true;
        g.state = 'over';
        // finiş hədiyyəsindən sonra eyni qülləni davam etdirmək olar (yeni hədiyyə yoxdur, rekord üçün)
        resumeBtn.hidden = !g.finish;
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

    /** Finiş hədiyyəsini götürdükdən sonra eyni oyuna qayıdır */
    function resumeGame() {
        overlay.hidden = true;
        resumeBtn.hidden = true;
        overlay.querySelector('.st-prize').hidden = true;
        if (g.state === 'over' && g.tower.length) g.state = 'swing';
    }

    /* ---------- Sınma: şüşə qırıqları, çay damcıları, masada çay ləkəsi ---------- */

    /**
     * Qırığın forması: nizamsız iti çoxbucaqlı; bəziləri uzun nazik qəlpədir,
     * bəzilərinin bir kənarı əyridir (armudu stəkanın divarından qopub).
     */
    function shardShape(size, tiny) {
        const n = tiny ? 3 : 3 + Math.floor(Math.random() * 3);
        const sliver = !tiny && Math.random() < 0.35;
        const pts = [];
        for (let i = 0; i < n; i++) {
            const a = i / n * Math.PI * 2 + (Math.random() - 0.5) * 0.7;
            const r = size * (0.4 + Math.random() * 0.65);
            pts.push([Math.cos(a) * r * (sliver ? 1.7 : 1), Math.sin(a) * r * (sliver ? 0.4 : 1)]);
        }
        return {pts, curve: !tiny && Math.random() < 0.5 ? Math.floor(Math.random() * n) : -1};
    }

    function shatter(f) {
        f.broken = true;
        sound.crash();
        const x = Math.max(-20, Math.min(LW + 20, f.x));
        g.shake = Math.max(g.shake, 6);
        // şüşə qırıqları: 16 iri-orta qırıq + 12 xırda qəlpə (toz kimi parıldayır)
        for (let i = 0; i < 28; i++) {
            const tiny = i >= 16;
            const a = -Math.PI * (0.1 + Math.random() * 0.8); // yuxarı-yana
            const v = (tiny ? 120 : 160) + Math.random() * 260;
            const size = tiny ? 1.4 + Math.random() * 1.8 : 4 + Math.random() * 8;
            g.shards.push({
                kind: 'glass', x: x + (Math.random() - 0.5) * GW * 0.6, y: FLOOR - 4,
                vx: Math.cos(a) * v + f.vx * 0.3, vy: Math.sin(a) * v,
                rot: Math.random() * 6, vr: (Math.random() - 0.5) * (tiny ? 30 : 18),
                size, life: 1, rest: false, ...shardShape(size, tiny),
                gold: !tiny && Math.random() < 0.18,          // qızılı haşiyədən qopmuş qırıq
                tea: !tiny && Math.random() < 0.35,           // içində çay olan hissədən — çay rəngli
                seed: Math.random() * 10,
                depth: (Math.random() - 0.5) * TABLE_DEPTH * 0.75, // masaya düşəndə önə-arxaya səpələnir
            });
        }
        // çay damcıları: havaya sıçrayır, süfrənin üstünə və ya ön tərəfinə düşüb ləkə qoyur
        for (let i = 0; i < 14; i++) {
            const a = -Math.PI * (0.15 + Math.random() * 0.7);
            const v = 120 + Math.random() * 260;
            g.shards.push({
                kind: 'tea', x, y: FLOOR - 6, vx: Math.cos(a) * v, vy: Math.sin(a) * v,
                size: 2 + Math.random() * 3.5, life: 1, rest: false,
            });
        }
        if (!hasTable()) {
            g.puddles.push({x, r: 4, max: 20 + Math.random() * 12, a: 0.75});
            return;
        }
        // stəkan sındığı yerdə süfrənin üstündə böyük çay ləkəsi, kənardan aşağı axan izlər
        const T = tableGeom();
        const [l, r] = topRange(FLOOR);
        const px = Math.max(l + 14, Math.min(r - 14, x));
        g.puddles.push({x: px, r: 4, max: 20 + Math.random() * 12, a: 0.75});
        splat(px + (Math.random() - 0.5) * 16, FLOOR - Math.random() * 5, 8 + Math.random() * 6, true, true);
        // çay süfrənin ön kənarından aşağı axır
        const ex = Math.max(T.x0 + 6, Math.min(T.x1 - 6, px + (Math.random() - 0.5) * 24));
        const edge = {x: ex, y: T.front, r: 3, blobs: [], grow: 0, flat: true, drips: []};
        for (let i = 0; i < 2 + Math.floor(Math.random() * 2); i++) {
            edge.drips.push({dx: (Math.random() - 0.5) * 18, w: 1.6 + Math.random() * 2, len: 0, max: 8 + Math.random() * (T.bottom - T.front - 10), speed: 7 + Math.random() * 10});
        }
        g.stains.push(edge);
    }

    /* ---------- Masanın forması (çay ləkələri yalnız süfrənin üstündə qalsın deyə) ---------- */
    const hasTable = () => !baseInPhoto();
    function tableGeom() {
        const front = BASE_Y + 4 + TABLE_DEPTH / 2;
        return {x0: 26, x1: LW - 26, flare: 10, front, back: front - TABLE_DEPTH, inset: TABLE_DEPTH * 0.85, bottom: LH - 8};
    }
    /** Üst səthin verilən hündürlükdəki sol və sağ kənarı (perspektivdə arxaya doğru daralır) */
    function topRange(y) {
        const T = tableGeom();
        const k = Math.max(0, Math.min(1, (T.front - y) / TABLE_DEPTH)) * T.inset;
        return [T.x0 + k, T.x1 - k];
    }
    /** Süfrənin bütün görünən hissəsi: üst səth + ön tərəfdən sallanan parça */
    function clothArea() {
        const T = tableGeom();
        const p = new Path2D();
        p.moveTo(T.x0 + T.inset, T.back);
        p.lineTo(T.x1 - T.inset, T.back);
        p.lineTo(T.x1, T.front);
        p.lineTo(T.x1 + T.flare, T.bottom + 6);
        p.lineTo(T.x0 - T.flare, T.bottom + 6);
        p.lineTo(T.x0, T.front);
        p.closePath();
        return p;
    }

    /**
     * Çay ləkəsi (x, y — mərkəz, r — ölçü). flat — süfrənin üstündə (perspektivdə yastı görünür).
     * big — ətrafında xırda sıçrantılar olur.
     */
    function splat(x, y, r, big, flat = false) {
        // ləkənin forması bir az qeyri-düzgün olsun
        const blobs = Array.from({length: big ? 6 : 2}, () => ({
            dx: (Math.random() - 0.5) * r, dy: (Math.random() - 0.5) * r, r: r * (0.35 + Math.random() * 0.45),
        }));
        const stain = {x, y, r, blobs, grow: 0, drips: [], flat};
        if (!flat && (big || Math.random() < 0.25)) { // şaquli səthdə çay aşağı axır
            const n = big ? 2 + Math.floor(Math.random() * 3) : 1;
            for (let i = 0; i < n; i++) {
                stain.drips.push({dx: (Math.random() - 0.5) * r * 1.2, w: 1.5 + Math.random() * (big ? 2.5 : 1), len: 0, max: 8 + Math.random() * (big ? 20 : 10), speed: 8 + Math.random() * 14});
            }
        }
        g.stains.push(stain);
        if (big) {
            for (let i = 0; i < 9; i++) { // ətrafdakı xırda sıçrantılar
                const a = Math.random() * Math.PI * 2, d = r * (1.3 + Math.random() * 2.6);
                g.stains.push({x: x + Math.cos(a) * d, y: y + Math.sin(a) * d * (flat ? 0.35 : 0.8), r: 1.2 + Math.random() * 3, blobs: [], grow: 0, drips: [], flat});
            }
        }
    }

    /** Dağılmış çay: gölməçələr, ləkələr (üst səthdəkilər perspektivdə yastı) və aşağı axan izlər */
    function drawSpills() {
        for (const p of g.puddles) {
            ctx.fillStyle = `rgba(170, 86, 28, ${p.a * 0.62})`;
            ctx.beginPath();
            ctx.ellipse(p.x, FLOOR + 3, p.r, p.r * 0.22, 0, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.fillStyle = 'rgba(184, 96, 34, .42)'; // tökülmüş çay: şəffaf kəhrəba
        for (const st of g.stains) {
            const k = 0.4 + 0.6 * st.grow;
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
            ctx.save();
            ctx.translate(st.x, st.y);
            if (st.flat) ctx.scale(1, 0.4);
            ctx.beginPath();
            ctx.arc(0, 0, st.r * k, 0, Math.PI * 2);
            for (const b of st.blobs) {
                ctx.moveTo(b.dx * k + b.r * k, b.dy * k);
                ctx.arc(b.dx * k, b.dy * k, b.r * k, 0, Math.PI * 2);
            }
            ctx.fill();
            ctx.restore();
        }
    }

    /** Süfrəyə düşən damcı: üst səthə düşsə yastı ləkə, ön tərəfə düşsə aşağı axan ləkə; masadan kənara düşsə ləkə qalmır */
    function teaLands(s) {
        const T = tableGeom();
        const [l, r] = topRange(FLOOR);
        if (s.x >= l && s.x <= r) {
            splat(s.x, FLOOR + (Math.random() - 0.5) * 10, s.size * 1.4, false, true);
            return true;
        }
        if (s.x >= T.x0 - T.flare && s.x <= T.x1 + T.flare) { // küncdən sürüşüb ön tərəfə
            splat(s.x, T.front + 4 + Math.random() * 12, s.size * 1.2, false, false);
            return true;
        }
        return false;
    }

    function updateShards(dt) {
        for (const s of g.shards) {
            // masaya düşmüş şüşə qırıqları yeni oyuna qədər qalır (reset() siyahını təmizləyir); çay damcıları hopub yox olur
            if (s.rest) { if (s.kind !== 'glass') s.life -= dt * 0.35; continue; }
            s.vy += 1400 * dt;
            s.x += s.vx * dt;
            s.y += s.vy * dt;
            if (s.rot !== undefined) s.rot += s.vr * dt;
            if (s.y > LH + 40) { s.life = 0; continue; }        // masadan kənara düşüb ekrandan çıxdı
            if (s.y >= FLOOR && s.vy > 0 && !s.off) {
                if (hasTable()) {
                    const [l, r] = topRange(FLOOR);
                    if (s.kind === 'tea') {                       // damcı süfrəyə hopur və ya masadan kənara düşür
                        if (teaLands(s)) s.life = 0; else s.off = true;
                        continue;
                    }
                    if (s.x < l || s.x > r) { s.off = true; continue; } // qırıq masanın kənarından aşağı düşür
                } else if (s.kind === 'tea') { s.rest = true; s.y = FLOOR; continue; }
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

    /**
     * Buğ hərəkətdən geri qalır: stəkan sağa gedəndə buğ sola, sola gedəndə sağa əyilir.
     * Sürüşmə yumşaq dəyişir (ətalət) — stəkan dönəndə buğ da bir anda yox, yavaş-yavaş o biri tərəfə keçir.
     */
    function updateSteamDrift(dt) {
        const s = g.steam;
        const mx = g.state === 'swing' || g.state === 'ready' ? g.swing.x : g.state === 'drop' ? g.drop.x : null;
        let target = 0;
        if (mx !== null && s.px !== null && dt > 0) {
            const vx = (mx - s.px) / dt;
            // yeni stəkan kənardan başlayanda x sıçrayır — bu "sürət" sayılmır
            if (Math.abs(vx) < 2000) target = Math.max(-STEAM.maxDrift, Math.min(STEAM.maxDrift, -vx * STEAM.lag));
        }
        s.px = mx;
        s.drift += (target - s.drift) * Math.min(1, dt * STEAM.follow);
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
        updateSteamDrift(dt);

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

        // kamera: qüllədən ekranda yalnız ən üstdəki VISIBLE_GLASSES stəkan görünsün (aşağısı kadrdan çıxır);
        // qüllə aşanda isə masaya enir ki, stəkanların sınması görünsün
        // RAISE_FROM stəkandan sonra yellənən stəkan rəvan yuxarı qalxır (qüllə ekranda yerində qalır)
        const gapWant = SWING_GAP + (g.tower.length - 1 >= RAISE_FROM ? RAISE_BY : 0);
        if (g.state !== 'drop') g.gap += (gapWant - g.gap) * Math.min(1, dt * 4);

        if (g.state !== 'fall' && g.state !== 'over') {
            const want = Math.max(0, LH - VISIBLE_GLASSES * GH - g.gap - swingY());
            g.camY += (want - g.camY) * Math.min(1, dt * 4);
        } else {
            g.camY += (0 - g.camY) * Math.min(1, dt * 2.6);
        }

        g.shake = Math.max(0, g.shake - dt * 14);
        g.popups.forEach(p => { p.life -= dt * 0.9; p.y -= dt * 40; });
        g.popups = g.popups.filter(p => p.life > 0);
    }

    /* ---------- Çəkmə ---------- */

    // isti çayın buğu (ağzın üstündə alçaq duman): on — açıq/bağlı, speed — duman ləkələrinin dövrü,
    // rise — ağızdan nə qədər yuxarı qalxıb itir (px), alpha — ən sıx anındakı görünmə dərəcəsi
    // lag — sürətin nə qədəri buğu geri sürüşdürür, maxDrift — ən çox neçə px, follow — nə tez çatır (ətalət)
    const STEAM = {on: true, speed: 0.3, rise: 10, alpha: 0.45, lag: 0.06, maxDrift: 14, follow: 4};
    const TEA_SURFACE_W = 0.3; // çay səthinin (ellipsin) yarım eni — stəkanın həmin hündürlükdəki eninə uyğun
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
    const BG = {photo: new Image(), cloth: new Image(), logo: new Image()};
    BG.photo.src = root.dataset.bgPhoto;
    const SHOW_LOGO = false; // fonda 50 Qəpik loqosu göstərilmir — sol yuxarı küncdə hesab durur
    if (SHOW_LOGO && root.dataset.logo) BG.logo.src = root.dataset.logo;
    const LOGO = {x: 34, y: 34, r: 24}; // sol yuxarı küncdəki kiçik yumru loqo: mərkəz və radius (SHOW_LOGO)
    const SCORE = {x: 14, y: 50};       // hesabın yeri: sol yuxarı künc (rəqəmin sol-alt nöqtəsi)
    if (root.dataset.cloth) BG.cloth.src = root.dataset.cloth;
    const TABLE_DEPTH = 34;     // masanın üst səthinin görünən dərinliyi (perspektivdə), px
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

    /** Yumşaq kənarlı ağ duman ləkəsi (ellips): mərkəzdə sıx, kənara doğru tamam şəffaf */
    function mistBlob(cx, cy, rx, ry, alpha) {
        if (alpha <= 0.01) return;
        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(1, ry / rx);
        const gr = ctx.createRadialGradient(0, 0, 0, 0, 0, rx);
        gr.addColorStop(0, `rgba(255, 255, 255, ${alpha})`);
        gr.addColorStop(0.55, `rgba(245, 245, 250, ${alpha * 0.45})`);
        gr.addColorStop(1, 'rgba(240, 240, 250, 0)');
        ctx.fillStyle = gr;
        ctx.beginPath();
        ctx.arc(0, 0, rx, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    /**
     * Buğ: isti çayın üstündə, stəkanın ağzına yapışıq alçaq, yumşaq duman (x, rimY — ağzın mərkəzi).
     * Ağzın üstündə sabit zəif duman qatı var; onun üstündə bir neçə duman ləkəsi yavaş yaranır,
     * azca qalxır (STEAM.rise qədər) və itir — buğ yuxarı uçmur. Külək dumanı azca yana sürüşdürür.
     */
    // drift — hərəkətdən geri qalma (px): yuxarıdakı, daha köhnə buğ ləkələri daha çox geri qalır
    function drawSteam(x, rimY, seed, drift = 0) {
        if (!STEAM.on) return;
        const t = g.time;
        const spread = 1 + Math.abs(drift) / 28;       // hərəkətdə buğ bir az yayılır
        // ağzın üstündəki daimi duman qatı — yavaş "nəfəs alır"
        mistBlob(x + windX * 2 + drift * 0.35, rimY - 2, GW * 0.62 * spread, 7, STEAM.alpha * (0.55 + 0.15 * Math.sin(t * 1.3 + seed * 6)));
        for (let k = 0; k < 5; k++) {
            const phase = (t * STEAM.speed + k / 5 + seed) % 1; // 0 — yenicə yaranıb, 1 — itib
            const a = Math.sin(phase * Math.PI) * STEAM.alpha;
            const cx = x + (k - 2) * GW * 0.15 + Math.sin(t * 0.9 + k * 1.7 + seed * 5) * 3 + windX * phase * 8
                + drift * (0.5 + phase * 1.1);
            const cy = rimY - 3 - phase * STEAM.rise;
            const rx = GW * 0.2 * (0.8 + phase * 0.6) * spread;   // qalxdıqca yayılır
            mistBlob(cx, cy, rx, rx * 0.5, a);
        }
    }

    /**
     * Armudu stəkanın dibi: qalın, şəffaf şüşə disk (çay onun üstündədir). Dibdən çayın rəngi
     * sınaraq açıq kəhrəba kimi görünür, kənarları işıqlı, ön kənarı parlaq, ətrafında incə qızılı haşiyə,
     * altında dayandığı yerə düşən yumşaq kölgə. Stəkanın lokal koordinatlarında: dib (0, 0)-dadır.
     */
    function drawGlassBase() {
        const rx = GW * 0.27, ry = 2.6, T = 5;             // yarım en, ellipsin yumruluğu, qalınlıq
        // dayandığı yerə düşən kölgə
        const sh = ctx.createRadialGradient(0, 1, 1, 0, 1, rx * 1.25);
        sh.addColorStop(0, 'rgba(0, 0, 0, .35)');
        sh.addColorStop(1, 'rgba(0, 0, 0, 0)');
        ctx.save();
        ctx.scale(1, 0.22);
        ctx.fillStyle = sh;
        ctx.beginPath(); ctx.arc(0, 1 / 0.22, rx * 1.25, 0, Math.PI * 2); ctx.fill();
        ctx.restore();

        // şüşə disk: yan tərəf (silindr) + alt ellipsin ön yarısı
        const side = new Path2D();
        side.moveTo(-rx, -T);
        side.lineTo(-rx, 0);
        side.ellipse(0, 0, rx, ry, 0, Math.PI, 0, true);
        side.lineTo(rx, -T);
        side.ellipse(0, -T, rx, ry, 0, 0, Math.PI, false);
        side.closePath();
        // içindən çay sınaraq görünür: ortada kəhrəba, kənarlarda şüşənin öz açıq çaları
        const body = ctx.createLinearGradient(-rx, 0, rx, 0);
        body.addColorStop(0, 'rgba(235, 245, 248, .55)');
        body.addColorStop(0.22, 'rgba(200, 95, 45, .55)');
        body.addColorStop(0.5, 'rgba(230, 120, 55, .5)');
        body.addColorStop(0.78, 'rgba(150, 40, 12, .6)');
        body.addColorStop(1, 'rgba(200, 220, 228, .5)');
        ctx.fillStyle = body;
        ctx.fill(side);
        // qalınlıq hissi: yuxarıdan aşağı açıq → tünd
        const depth = ctx.createLinearGradient(0, -T - ry, 0, ry);
        depth.addColorStop(0, 'rgba(255, 255, 255, .25)');
        depth.addColorStop(1, 'rgba(40, 10, 0, .25)');
        ctx.fillStyle = depth;
        ctx.fill(side);

        // dibin üst səthi (çayın altı) — incə açıq xətt
        ctx.strokeStyle = 'rgba(255, 225, 190, .55)';
        ctx.lineWidth = 0.7;
        ctx.beginPath();
        ctx.ellipse(0, -T, rx, ry, 0, 0, Math.PI);
        ctx.stroke();
        // yan kənarlar: sol işıqlı, sağ tünd
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(255, 255, 255, .85)';
        ctx.beginPath(); ctx.moveTo(-rx + 0.5, -T); ctx.lineTo(-rx + 0.5, 0); ctx.stroke();
        ctx.strokeStyle = 'rgba(90, 40, 20, .5)';
        ctx.beginPath(); ctx.moveTo(rx - 0.5, -T); ctx.lineTo(rx - 0.5, 0); ctx.stroke();
        // incə qızılı haşiyə dibin ortasında
        ctx.strokeStyle = '#E9A23B';
        ctx.lineWidth = 1.1;
        ctx.beginPath();
        ctx.ellipse(0, -T * 0.45, rx, ry, 0, 0, Math.PI);
        ctx.stroke();
        // alt kənar: şüşənin ən parlaq yeri (işıq qalın kənardan sınır)
        ctx.strokeStyle = 'rgba(255, 255, 255, .9)';
        ctx.lineWidth = 1.2;
        ctx.beginPath();
        ctx.ellipse(0, 0, rx, ry, 0, Math.PI * 0.15, Math.PI * 0.85);
        ctx.stroke();
        // kiçik parıltı ləkəsi
        ctx.fillStyle = 'rgba(255, 255, 255, .8)';
        ctx.beginPath();
        ctx.ellipse(-rx * 0.55, -T * 0.55, 1.4, 0.8, 0, 0, Math.PI * 2);
        ctx.fill();
    }

    /*
     * Sürət üçün: düz dayanan stəkan (fırlanmır, çayı çalxalanmır) hər kadrda naxışları ilə yenidən
     * çəkilmir — bir dəfə ayrıca kətana çəkilir və hazır şəkil kimi qoyulur. Kətanın ölçüsü dəyişəndə
     * (böyük ekran, pəncərənin eni) yenidən yaradılır ki, bulanıq olmasın.
     */
    const SPRITE_PAD = {l: GW * 0.75, r: GW * 0.75, t: GH + 22, b: 8};
    const glassSprite = {cv: null, res: 0};
    function getGlassSprite() {
        const res = dpr * scale;
        if (glassSprite.cv && glassSprite.res === res) return glassSprite.cv;
        const cv = document.createElement('canvas');
        cv.width = Math.ceil((SPRITE_PAD.l + SPRITE_PAD.r) * res);
        cv.height = Math.ceil((SPRITE_PAD.t + SPRITE_PAD.b) * res);
        const main = ctx;
        ctx = cv.getContext('2d');
        ctx.setTransform(res, 0, 0, res, 0, 0);
        try {
            drawGlassFull(SPRITE_PAD.l, SPRITE_PAD.t, 0, 1, 0);
        } finally {
            ctx = main;
        }
        glassSprite.cv = cv;
        glassSprite.res = res;
        return cv;
    }

    function drawGlass(x, y, rot = 0, alpha = 1, tilt = 0) {
        if (rot || Math.abs(tilt) > 0.004) return drawGlassFull(x, y, rot, alpha, tilt);
        const cv = getGlassSprite();
        const a = ctx.globalAlpha;
        ctx.globalAlpha = alpha;
        ctx.drawImage(cv, x - SPRITE_PAD.l, y - SPRITE_PAD.t, SPRITE_PAD.l + SPRITE_PAD.r, SPRITE_PAD.t + SPRITE_PAD.b);
        ctx.globalAlpha = a;
    }

    function drawGlassFull(x, y, rot = 0, alpha = 1, tilt = 0) {
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
        // çayın səthi: stəkana azca yuxarıdan baxırıq, ona görə səth ellips kimi görünür
        // (çay çalxalananda ellips də əyilir)
        ctx.save();
        ctx.translate(0, sy);
        ctx.rotate(a);
        const srx = GW * TEA_SURFACE_W, sry = RIM_RY * 0.85;
        const surf = ctx.createLinearGradient(0, -sry, 0, sry);
        surf.addColorStop(0, 'rgba(150, 40, 10, .95)');   // arxa kənar tünd
        surf.addColorStop(1, 'rgba(215, 105, 45, .95)');  // ön kənar işıqlı
        ctx.fillStyle = surf;
        ctx.beginPath();
        ctx.ellipse(0, 0, srx, sry, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = 'rgba(255, 200, 150, .7)';      // səthin parlaq haşiyəsi
        ctx.lineWidth = 0.8;
        ctx.stroke();
        ctx.fillStyle = 'rgba(255, 235, 200, .45)';       // səthdə işığın əksi
        ctx.beginPath();
        ctx.ellipse(-srx * 0.35, -sry * 0.15, srx * 0.28, sry * 0.35, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

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

        drawGlassBase();

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
     * Masanın üst səthi — azca yuxarıdan baxırıq (perspektiv): arxa kənar dar və yuxarıda, ön kənar enli.
     * Süfrə üst səthi örtür və ön tərəfdən aşağı sallanır (drawCloth). Stəkan nəlbəki ilə üst səthin üstündə dayanır.
     */
    function drawTableTop(x0, x1, front) {
        const back = front - TABLE_DEPTH, inset = TABLE_DEPTH * 0.85;
        const bx0 = x0 + inset, bx1 = x1 - inset;
        const p = new Path2D();
        p.moveTo(bx0, back);
        p.lineTo(bx1, back);
        p.lineTo(x1, front);
        p.lineTo(x0, front);
        p.closePath();

        // masanın altına düşən kölgə — masa havada asılı görünməsin
        ctx.fillStyle = 'rgba(0, 0, 0, .35)';
        ctx.beginPath();
        ctx.ellipse(LW / 2, LH - 4, (x1 - x0) / 2 + 18, 10, 0, 0, Math.PI * 2);
        ctx.fill();

        ctx.save();
        ctx.clip(p);
        if (BG.cloth.complete && BG.cloth.naturalWidth > 0) {
            // üst səthdə süfrənin naxışı: perspektivdə sıxılmış görünür
            const iw = BG.cloth.naturalWidth, ih = iw * 0.22;
            const iy = BG.cloth.naturalHeight * CLOTH_FOCUS_Y - ih * 1.4;
            ctx.drawImage(BG.cloth, 0, Math.max(0, iy), iw, ih, x0, back, x1 - x0, TABLE_DEPTH);
        } else {
            ctx.fillStyle = '#d42a2a';
            ctx.fillRect(x0, back, x1 - x0, TABLE_DEPTH);
        }
        // işıq yuxarıdan düşür: üst səth ön tərəfdən açıq, arxaya doğru tündləşir
        const light = ctx.createLinearGradient(0, back, 0, front);
        light.addColorStop(0, 'rgba(0, 0, 0, .35)');
        light.addColorStop(0.6, 'rgba(255, 235, 200, .06)');
        light.addColorStop(1, 'rgba(255, 235, 200, .18)');
        ctx.fillStyle = light;
        ctx.fillRect(x0, back, x1 - x0, TABLE_DEPTH);
        // nəlbəkinin altındakı kölgə
        ctx.fillStyle = 'rgba(0, 0, 0, .35)';
        ctx.beginPath();
        ctx.ellipse(LW / 2 + 3, BASE_Y + 6, GW * 0.95, 6, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        // kənarlar: arxa və yan kənarlarda incə qızılı xətt, ön kənar (döngə) parlaq
        ctx.strokeStyle = 'rgba(217, 162, 55, .55)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x0, front);
        ctx.lineTo(bx0, back);
        ctx.lineTo(bx1, back);
        ctx.lineTo(x1, front);
        ctx.stroke();
    }

    /**
     * Masanın üstündə süfrə: yumşaq qırışlarla aşağı sallanır, alt kənarı dalğalıdır.
     * Xalça naxışlı şəkil (data-cloth) yüklənibsə parça onunla örtülür, yoxsa düz qırmızıdır.
     */
    function drawCloth() {
        // ön kənar elə seçilir ki, nəlbəki (BASE_Y + 4) üst səthin tam ortasına düşsün
        const top = BASE_Y + 4 + TABLE_DEPTH / 2, bottom = LH - 8;
        const x0 = 26, x1 = LW - 26, flare = 10;          // aşağıda bir az genişlənir
        drawTableTop(x0, x1, top);
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
            ctx.fillStyle = 'rgba(0, 0, 0, .14)'; // ön tərəf şaquli olduğu üçün üst səthdən tünd görünür
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

        drawGlassTop(x0, x1, top);
    }

    /**
     * Süfrənin üstündə şüşə lövhə: masadan bir az enli, açıq yaşılımtıl çalar, çəp parıltı zolaqları,
     * arxa kənarda işıq əksi və ön tərəfdə görünən qalınlıq (işıq sınan yaşılımtıl kənar).
     */
    function drawGlassTop(x0, x1, front) {
        const back = front - TABLE_DEPTH - 1, inset = TABLE_DEPTH * 0.85, o = 3;  // o — şüşə masadan nə qədər çıxır
        const gx0 = x0 - o, gx1 = x1 + o, bx0 = x0 + inset - o * 0.6, bx1 = x1 - inset + o * 0.6;
        const p = new Path2D();
        p.moveTo(bx0, back);
        p.lineTo(bx1, back);
        p.lineTo(gx1, front);
        p.lineTo(gx0, front);
        p.closePath();

        ctx.save();
        ctx.clip(p);
        // şüşənin öz çaları: arxada bir az daha görünür (işıq şüşədən çəp keçir)
        const tint = ctx.createLinearGradient(0, back, 0, front);
        tint.addColorStop(0, 'rgba(190, 228, 236, .2)');
        tint.addColorStop(1, 'rgba(215, 240, 245, .07)');
        ctx.fillStyle = tint;
        ctx.fillRect(gx0, back, gx1 - gx0, front - back);
        // çəp parıltı zolaqları
        const W = gx1 - gx0;
        const streak = (x, w, a) => {
            ctx.fillStyle = `rgba(255, 255, 255, ${a})`;
            ctx.beginPath();
            ctx.moveTo(x, back);
            ctx.lineTo(x + w, back);
            ctx.lineTo(x + w - 30, front);
            ctx.lineTo(x - 30, front);
            ctx.closePath();
            ctx.fill();
        };
        streak(gx0 + W * 0.16, 30, 0.1);
        streak(gx0 + W * 0.16 + 38, 7, 0.16);
        streak(gx0 + W * 0.72, 46, 0.06);
        streak(gx0 + W * 0.72 + 52, 5, 0.12);
        // arxa kənarda işıq əksi
        const sheen = ctx.createLinearGradient(0, back, 0, back + 12);
        sheen.addColorStop(0, 'rgba(255, 255, 255, .28)');
        sheen.addColorStop(1, 'rgba(255, 255, 255, 0)');
        ctx.fillStyle = sheen;
        ctx.fillRect(gx0, back, W, 12);
        ctx.restore();

        // arxa və yan kənarlar: incə parlaq xətt
        ctx.strokeStyle = 'rgba(235, 250, 255, .6)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(gx0, front);
        ctx.lineTo(bx0, back);
        ctx.lineTo(bx1, back);
        ctx.lineTo(gx1, front);
        ctx.stroke();

        // ön kənar — şüşənin qalınlığı: işıq sınır, yaşılımtıl görünür
        const T = 4;
        const edge = ctx.createLinearGradient(0, front, 0, front + T);
        edge.addColorStop(0, 'rgba(205, 242, 236, .8)');
        edge.addColorStop(0.5, 'rgba(120, 182, 176, .5)');
        edge.addColorStop(1, 'rgba(55, 105, 100, .6)');
        ctx.fillStyle = edge;
        ctx.fillRect(gx0, front, W, T);
        ctx.fillStyle = 'rgba(255, 255, 255, .9)';
        ctx.fillRect(gx0, front, W, 1);
        // künclərdə parıltı
        ctx.fillStyle = 'rgba(255, 255, 255, .7)';
        ctx.fillRect(gx0, front, 2, T);
        ctx.fillRect(gx1 - 2, front, 2, T);
        // şüşənin süfrəyə saldığı nazik kölgə
        ctx.fillStyle = 'rgba(0, 0, 0, .2)';
        ctx.fillRect(x0, front + T, x1 - x0, 2);
    }

    /** Dörd guşəli parıltı */
    function glintStar(x, y, r, a) {
        ctx.save();
        ctx.globalAlpha *= a;
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.moveTo(x, y - r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.quadraticCurveTo(x, y, x, y + r);
        ctx.quadraticCurveTo(x, y, x - r, y);
        ctx.quadraticCurveTo(x, y, x, y - r);
        ctx.fill();
        ctx.restore();
    }

    /**
     * Şüşə qırığı: demək olar şəffaf gövdə, işığa baxan kənarlar parlaq, o biri tərəf tünd,
     * fırlandıqca parıldayır. Masaya düşəndə perspektivdə yastı uzanır və arabir sayrışır.
     */
    function drawShard(s) {
        const pts = s.pts;
        const n = pts.length;
        // parıltı: uçarkən fırlanmadan, masada isə vaxtdan asılı sayrışır
        const glint = s.rest
            ? Math.max(0, Math.sin(g.time * 2.2 + s.seed * 3)) ** 6
            : 0.5 + 0.5 * Math.sin(s.rot * 2.5 + s.seed);

        const restY = FLOOR + 1 + (s.depth || 0);
        ctx.save();
        ctx.translate(s.x, s.rest ? restY : s.y);
        if (s.rest) {
            // masanın şüşəsinə düşən yumşaq kölgə, qırıq üfüqi yastı görünür
            ctx.fillStyle = 'rgba(0, 0, 0, .25)';
            ctx.beginPath();
            ctx.ellipse(1.2, 1.4, s.size * 0.75, s.size * 0.22, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.scale(1, 0.42);
        }
        ctx.rotate(s.rot);

        const shape = () => {
            ctx.beginPath();
            ctx.moveTo(pts[0][0], pts[0][1]);
            for (let i = 0; i < n; i++) {
                const p = pts[i], q = pts[(i + 1) % n];
                if (i === s.curve) ctx.quadraticCurveTo((p[0] + q[0]) * 0.8, (p[1] + q[1]) * 0.8, q[0], q[1]);
                else ctx.lineTo(q[0], q[1]);
            }
            ctx.closePath();
        };

        // gövdə: şəffaf, bir az mavi-ağ və ya çay rəngli
        shape();
        ctx.fillStyle = s.tea ? 'rgba(176, 62, 18, .32)' : 'rgba(215, 236, 242, .2)';
        ctx.fill();
        // içindən keçən işıq: bir küncdən parlaq, o biri tərəfə sönür
        const lg = ctx.createLinearGradient(-s.size, -s.size, s.size, s.size);
        lg.addColorStop(0, `rgba(255, 255, 255, ${0.12 + 0.6 * glint})`);
        lg.addColorStop(0.45, 'rgba(255, 255, 255, 0)');
        lg.addColorStop(1, `rgba(255, 255, 255, ${0.08 + 0.18 * (1 - glint)})`);
        ctx.fillStyle = lg;
        ctx.fill();

        // kənarlar ayrı-ayrı: işığa baxanlar parlaq, qalanları tünd (qalınlıq hissi)
        ctx.lineJoin = 'round';
        for (let i = 0; i < n; i++) {
            const p = pts[i], q = pts[(i + 1) % n];
            const facing = Math.sin(Math.atan2(q[1] - p[1], q[0] - p[0]) + s.rot) > -0.2;
            ctx.beginPath();
            ctx.moveTo(p[0], p[1]);
            if (i === s.curve) ctx.quadraticCurveTo((p[0] + q[0]) * 0.8, (p[1] + q[1]) * 0.8, q[0], q[1]);
            else ctx.lineTo(q[0], q[1]);
            if (s.gold && i === 0) {
                ctx.strokeStyle = '#e9b24a';           // qızılı haşiyənin qırığı
                ctx.lineWidth = 1.8;
            } else {
                ctx.strokeStyle = facing ? `rgba(255, 255, 255, ${0.55 + 0.4 * glint})` : 'rgba(60, 40, 30, .45)';
                ctx.lineWidth = s.size < 3 ? 0.6 : 0.9;
            }
            ctx.stroke();
        }
        ctx.restore();

        // güclü parıltı anında kiçik ulduz
        if (glint > 0.85) {
            glintStar(s.x + s.size * 0.2, (s.rest ? restY : s.y) - s.size * 0.2, 2 + s.size * 0.45, (glint - 0.85) / 0.15);
        }
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
            const px = LW / 2 - dw / 2, py = LH - dh + Math.min(LH * 0.15, cam * 0.08);
            ctx.drawImage(BG.photo, px, py, dw, dh);
            ctx.fillStyle = `rgba(0, 0, 0, ${BG_DARKEN})`; // şəkli bir az qaraldırıq ki, stəkanlar önə çıxsın
            ctx.fillRect(0, 0, LW, LH);
            // şəkildəki tağlı pəncərədən çöl görünür — qaraldılmadan sonra, çünki çöl otaqdan işıqlıdır
            drawWindow(px, py, dw / BG.photo.naturalWidth);
        }

        // 50 Qəpik loqosu — fon şəklinin üstündə, yuxarıda kiçik yumru nişan (yerində qalır)
        if (SHOW_LOGO && !PHOTO_IS_BASE && BG.logo.complete && BG.logo.naturalWidth) {
            const cx = LOGO.x, cy = LOGO.y, r = LOGO.r;
            ctx.save();
            ctx.globalAlpha = 1 - h * 0.4; // yuxarı qalxdıqca gecədə bir az solur
            ctx.shadowColor = 'rgba(0, 0, 0, .55)';
            ctx.shadowBlur = 8;
            ctx.fillStyle = '#fff';
            ctx.beginPath();
            ctx.arc(cx, cy, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.shadowBlur = 0;
            ctx.clip();
            ctx.drawImage(BG.logo, cx - r, cy - r, r * 2, r * 2);
            ctx.restore();
            ctx.save();
            ctx.globalAlpha = 1 - h * 0.4;
            ctx.strokeStyle = '#d9a237'; // qızılı haşiyə
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(cx, cy, r + 1, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();
        }
        if (!PHOTO_IS_BASE && photoReady()) {
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

        // masa yoxdursa (foto rejimi) çay ləkələri fonda qalır
        if (!hasTable()) drawSpills();

        // masa və nəlbəki — yalnız foto yüklənməyibsə (fotoda stəkan və nəlbəki artıq var)
        if (!baseInPhoto()) {
        drawCloth();
        // dağılan çay yalnız süfrənin üstündə və ön tərəfində görünür — kənara çıxan hissə kəsilir
        ctx.save();
        ctx.clip(clothArea());
        drawSpills();
        ctx.restore();
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

        // qüllə
        // ilk stəkan fotodakı stəkandır — foto varsa onu çəkmirik
        g.tower.forEach((t, i) => {
            if (i === 0 && baseInPhoto()) return;
            const gy = BASE_Y - i * GH;
            if (gy + 8 + cam < 0 || gy - GH - 24 + cam > LH) return; // ekrandan kənardadır — çəkmirik
            drawGlass(t.x + sx * (i / Math.max(1, g.tower.length)), gy, 0, 1, t.s ? t.s.a : 0);
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

        // isti çayın buxarı — ağzı açıq qalan stəkanlardan qalxır: qüllənin başı və yellənən/düşən stəkan
        if (g.tower.length && !(g.tower.length === 1 && baseInPhoto())) {
            const top = g.tower[g.tower.length - 1];
            drawSteam(top.x, BASE_Y - (g.tower.length - 1) * GH - GH, 0);
        }
        if (g.state === 'swing' || g.state === 'ready') drawSteam(g.swing.x, swingY() - GH, 0.5, g.steam.drift);
        if (g.state === 'drop') drawSteam(g.drop.x, g.drop.y - GH, 0.5, g.steam.drift);

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
                drawShard(s);
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

        // hesab — sol yuxarı küncdə (əvvəl loqo burada idi)
        ctx.save();
        ctx.textAlign = 'left';
        ctx.shadowColor = 'rgba(0, 0, 0, .6)';  // tünd fonda oxunsun
        ctx.shadowBlur = 6;
        ctx.fillStyle = '#fff';
        ctx.font = '900 44px Nunito, sans-serif';
        ctx.fillText(g.score, SCORE.x, SCORE.y);
        ctx.font = '700 13px Nunito, sans-serif';
        ctx.globalAlpha = 0.7;
        ctx.fillText(`Rekord: ${Math.max(best, g.score)}`, SCORE.x + 2, SCORE.y + 22);
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
    const LEAF_SPEED = [55, 110]; // küləkdə uçan yarpaqların sürəti (px/s, təsadüfi bu aralıqda)

    function initWeather() {
        fx.drops = []; fx.flakes = []; fx.leaves = []; fx.fog = []; fx.stars = [];
        fx.flash = 0; fx.bolt = null; fx.nextBolt = rnd(3, 6);
        if (!W) { initWindow(); return; }
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
        initWindow();
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
            v: rnd(LEAF_SPEED[0], LEAF_SPEED[1]) * dir, p: rnd(0, 6), rot: rnd(0, 6), vr: rnd(-3, 3), s: rnd(5, 9),
            c: ['#c9831f', '#a0522d', '#d9a237', '#8b5a2b'][Math.floor(rnd(0, 4))],
        };
    }

    function updateWeather(dt) {
        if (!(dt > 0)) return;
        fx.t += dt;
        updateWindow(dt);   // hava hələ gəlməyibsə də pəncərədəki buludlar sürüşür
        if (!W) return;
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
            l.y += Math.sin(fx.t * 2 + l.p) * 25 * dt; // yarpaq yavaş-yavaş enib-qalxır
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

    /* ---------- Fondakı tağlı pəncərə: içində çöldəki canlı hava ---------- */

    // Pəncərənin açılışı fon şəklinin öz piksellərində (736×1104): sol krem sütun, yuxarıda qövs,
    // sağda tünd çərçivə (x≈192), aşağıda maili narıncı altlıq. x=164-də və y=330-da çərçivə tirləri.
    const WIN_PTS = [[92, 535], [92, 268], [103, 240], [118, 212], [134, 186], [150, 168], [168, 152], [184, 140], [192, 134], [192, 462]];
    const WIN_BOX = {x0: 92, x1: 192, y0: 124, y1: 535};
    const WIN_SILL = u => 535 - u * 73;           // altlığın hündürlüyü (u: 0 — sol, 1 — sağ)
    const WIN_MULLION = 164, WIN_TRANSOM = 330;
    const SKY = {
        day: {clear: ['#3f86d1', '#b9dcf3'], clouds: ['#7f97b0', '#cdd8e2'], rain: ['#56636f', '#97a3ad'], storm: ['#2b313d', '#55606d'], snow: ['#a9b5c1', '#e4e9ee'], fog: ['#c3c9ce', '#e2e5e7']},
        night: {clear: ['#070d24', '#22325c'], clouds: ['#151a28', '#343c50'], rain: ['#0e131c', '#262e3b'], storm: ['#0a0d14', '#202633'], snow: ['#232b3c', '#4d5870'], fog: ['#2a2f38', '#4a5059']},
    };
    const CLOUD_COLOR = {
        day: {clear: '#ffffff', clouds: '#eef2f5', rain: '#7c8691', storm: '#3b414c', snow: '#d3dae1', fog: '#dfe3e6'},
        night: {clear: '#3a4258', clouds: '#3d4558', rain: '#232a36', storm: '#1a1f29', snow: '#5b667c', fog: '#4a5059'},
    };
    // Uzaqda Bakı: alçaq binalar [u başlanğıc, u son, hündürlük (şəkil px)] və üç Alov qülləsi [u, hündürlük]
    const CITY = [[0, .1, 18], [.08, .18, 30], [.17, .24, 22], [.23, .33, 38], [.32, .4, 26], [.39, .46, 44], [.45, .52, 20], [.86, .94, 28], [.93, 1, 16]];
    const FLAMES = [[.56, 78], [.68, 98], [.8, 70]];
    const win = {drops: [], flakes: [], clouds: [], stars: [], beads: [], lit: []};

    function initWindow() {
        win.drops = []; win.flakes = []; win.clouds = []; win.stars = []; win.beads = [];
        const k = W ? W.kind : 'clouds';
        const nClouds = {clear: 1, clouds: 5, rain: 6, storm: 7, snow: 5, fog: 3}[k] ?? 4;
        for (let i = 0; i < nClouds; i++) win.clouds.push({u: rnd(-0.3, 1.1), v: rnd(0.05, 0.55), s: rnd(0.7, 1.3), sp: rnd(0.012, 0.03)});
        const nDrops = k === 'rain' ? 60 : k === 'storm' ? 110 : 0;
        for (let i = 0; i < nDrops; i++) win.drops.push({u: rnd(-0.2, 1.2), v: rnd(0, 1), sp: rnd(1.4, 2)});
        if (k === 'snow') for (let i = 0; i < 55; i++) win.flakes.push({u: rnd(0, 1), v: rnd(0, 1), r: rnd(0.6, 1.4), sp: rnd(0.08, 0.16), p: rnd(0, 6)});
        if (W && !W.day) for (let i = 0; i < 26; i++) win.stars.push({u: rnd(0, 1), v: rnd(0.02, 0.55), p: rnd(0, 6)});
        // şüşədəki su damcıları (yağışda)
        if (k === 'rain' || k === 'storm') for (let i = 0; i < 26; i++) win.beads.push({u: rnd(0.03, 0.97), v: rnd(0.08, 0.95), r: rnd(0.7, 1.6), sp: Math.random() < 0.3 ? rnd(0.01, 0.04) : 0});
        // gecə binalarda yanan pəncərələr
        win.lit = [];
        for (const [u0, u1, h] of CITY) {
            for (let j = 0; j < Math.round((u1 - u0) * 30); j++) win.lit.push({u: rnd(u0 + 0.01, u1 - 0.01), h: rnd(4, h - 4), on: Math.random() < 0.55});
        }
    }

    function updateWindow(dt) {
        if (!(dt > 0)) return;
        const dir = windX < 0 ? -1 : 1, gust = 1 + Math.abs(windX) * 3;
        for (const c of win.clouds) {
            c.u += c.sp * dir * gust * dt;
            if (c.u > 1.4) c.u = -0.4; else if (c.u < -0.4) c.u = 1.4;
        }
        for (const d of win.drops) {
            d.v += d.sp * dt; d.u += windX * d.sp * 0.4 * dt;
            if (d.v > 1.05) { d.v = rnd(-0.1, 0); d.u = rnd(-0.2, 1.2); }
        }
        for (const f of win.flakes) {
            f.v += f.sp * dt; f.u += (windX * 0.2 + Math.sin(fx.t * 1.5 + f.p) * 0.05) * dt;
            if (f.v > 1.02) { f.v = rnd(-0.05, 0); f.u = rnd(0, 1); }
        }
        for (const b of win.beads) {
            if (!b.sp) continue;
            b.v += b.sp * dt;                                  // iri damcılar şüşədən aşağı süzülür
            if (b.v > 0.97) { b.v = rnd(0.05, 0.3); b.u = rnd(0.03, 0.97); }
        }
    }

    /** Alov qülləsinin forması: enli dib, yuxarı əyilib iti uca çatır */
    function flamePath(x, base, w, h) {
        ctx.beginPath();
        ctx.moveTo(x - w / 2, base);
        ctx.quadraticCurveTo(x - w * 0.62, base - h * 0.55, x + w * 0.08, base - h);
        ctx.quadraticCurveTo(x + w * 0.62, base - h * 0.5, x + w / 2, base);
        ctx.closePath();
    }

    /**
     * Pəncərəni çəkir. (ox, oy) — fon şəklinin kətandakı sol yuxarı küncü, k — miqyas (kətan px / şəkil px).
     * Hava hələ gəlməyibsə, sakit buludlu gündüz göstərilir.
     */
    function drawWindow(ox, oy, k) {
        const night = W ? !W.day : false;
        const kind = W && SKY.day[W.kind] ? W.kind : 'clouds';
        const tod = night ? 'night' : 'day';
        const X = x => ox + x * k, Y = y => oy + y * k;
        const bx0 = X(WIN_BOX.x0), bx1 = X(WIN_BOX.x1), by0 = Y(WIN_BOX.y0), by1 = Y(WIN_BOX.y1);
        const ww = bx1 - bx0, wh = by1 - by0;
        const U = u => bx0 + u * ww, V = v => by0 + v * wh;
        const sill = u => Y(WIN_SILL(u));

        // otağa düşən işıq: gündüz pəncərənin ətrafı yumşaq işıqlanır
        if (!night) {
            const lx = X(142), ly = Y(330);
            const glow = ctx.createRadialGradient(lx, ly, 10, lx, ly, ww * 3.2);
            glow.addColorStop(0, kind === 'clear' ? 'rgba(255, 244, 220, .22)' : 'rgba(235, 240, 245, .14)');
            glow.addColorStop(1, 'rgba(255, 244, 220, 0)');
            ctx.fillStyle = glow;
            ctx.fillRect(0, 0, LW, LH);
        }

        ctx.save();
        ctx.beginPath();
        WIN_PTS.forEach(([x, y], i) => (i ? ctx.lineTo(X(x), Y(y)) : ctx.moveTo(X(x), Y(y))));
        ctx.closePath();
        ctx.clip();

        // səma
        const [c0, c1] = SKY[tod][kind];
        const sg = ctx.createLinearGradient(0, by0, 0, by1);
        sg.addColorStop(0, c0);
        sg.addColorStop(1, c1);
        ctx.fillStyle = sg;
        ctx.fillRect(bx0 - 2, by0 - 2, ww + 4, wh + 4);

        // ulduzlar və ay (gecə, açıq və ya azca buludlu)
        if (night && (kind === 'clear' || kind === 'clouds')) {
            for (const s of win.stars) {
                ctx.fillStyle = `rgba(255, 255, 255, ${0.3 + 0.5 * Math.abs(Math.sin(fx.t * 1.3 + s.p))})`;
                ctx.fillRect(U(s.u), V(s.v * 0.6), 1.2, 1.2);
            }
            const mx = U(0.62), my = V(0.17);
            const mg = ctx.createRadialGradient(mx, my, 2, mx, my, 26);
            mg.addColorStop(0, 'rgba(250, 245, 210, .45)');
            mg.addColorStop(1, 'rgba(250, 245, 210, 0)');
            ctx.fillStyle = mg;
            ctx.fillRect(mx - 26, my - 26, 52, 52);
            ctx.fillStyle = '#f4efcf';
            ctx.beginPath(); ctx.arc(mx, my, 6, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = 'rgba(200, 195, 160, .5)';            // ayın ləkələri
            ctx.beginPath(); ctx.arc(mx - 1.5, my - 1, 1.4, 0, Math.PI * 2); ctx.arc(mx + 2, my + 2, 1, 0, Math.PI * 2); ctx.fill();
        }
        // günəş (gündüz, açıq və ya azca buludlu)
        if (!night && (kind === 'clear' || kind === 'clouds')) {
            const sx = U(0.66), sy = V(0.15);
            const sgl = ctx.createRadialGradient(sx, sy, 3, sx, sy, kind === 'clear' ? 46 : 30);
            sgl.addColorStop(0, `rgba(255, 240, 190, ${kind === 'clear' ? 0.85 : 0.5})`);
            sgl.addColorStop(1, 'rgba(255, 240, 190, 0)');
            ctx.fillStyle = sgl;
            ctx.fillRect(sx - 50, sy - 50, 100, 100);
            ctx.fillStyle = kind === 'clear' ? '#fff6d0' : 'rgba(255, 246, 210, .75)';
            ctx.beginPath(); ctx.arc(sx, sy, 7, 0, Math.PI * 2); ctx.fill();
        }

        // buludlar: küləklə sürüşən yumşaq topalar
        const cc = CLOUD_COLOR[tod][kind];
        for (const c of win.clouds) {
            const cx = U(c.u), cy = V(c.v * 0.6), s = c.s * ww * 0.22;
            ctx.fillStyle = cc;
            ctx.globalAlpha = kind === 'clear' ? 0.6 : 0.92;
            ctx.beginPath();
            ctx.ellipse(cx, cy, s * 1.5, s * 0.55, 0, 0, Math.PI * 2);
            ctx.ellipse(cx - s * 0.6, cy - s * 0.25, s * 0.7, s * 0.6, 0, 0, Math.PI * 2);
            ctx.ellipse(cx + s * 0.45, cy - s * 0.4, s * 0.85, s * 0.75, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.globalAlpha = 1;
            // altı bir az tünd — həcm
            ctx.fillStyle = 'rgba(0, 0, 0, .1)';
            ctx.beginPath(); ctx.ellipse(cx, cy + s * 0.25, s * 1.4, s * 0.28, 0, 0, Math.PI * 2); ctx.fill();
        }

        // ildırım: pəncərə ağarır, içində şimşək xətti
        if (fx.flash > 0) {
            ctx.fillStyle = `rgba(230, 236, 255, ${fx.flash * 0.75})`;
            ctx.fillRect(bx0, by0, ww, wh);
            if (fx.flash > 0.45) {
                ctx.strokeStyle = `rgba(255, 255, 255, ${fx.flash})`;
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                let px = U(0.4), py = by0;
                ctx.moveTo(px, py);
                for (let i = 0; i < 7; i++) { px += Math.sin(i * 2.7 + fx.nextBolt) * ww * 0.12; py += wh * 0.08; ctx.lineTo(px, py); }
                ctx.stroke();
            }
        }

        // uzaqda şəhər: binalar və Alov qüllələri (dibləri altlıq xəttindədir)
        const hazy = kind === 'fog' || kind === 'snow' || kind === 'rain' || kind === 'storm';
        const cityCol = night ? '#0b0e16' : hazy ? '#7d8893' : '#53647a';
        ctx.fillStyle = cityCol;
        for (const [u0, u1, h] of CITY) {
            const yb = sill((u0 + u1) / 2) + 2;
            ctx.fillRect(U(u0), yb - h * k, (u1 - u0) * ww + 0.5, h * k + 4);
        }
        if (night) {
            for (const l of win.lit) {
                if (!l.on) continue;
                const twinkle = 0.6 + 0.4 * Math.sin(fx.t * 0.7 + l.u * 40);
                ctx.fillStyle = `rgba(255, 214, 140, ${0.75 * twinkle})`;
                ctx.fillRect(U(l.u), sill(l.u) - l.h * k, 1, 1);
            }
        }
        for (const [u, h] of FLAMES) {
            const fxp = U(u), base = sill(u) + 2, fw = ww * 0.1;
            flamePath(fxp, base, fw, h * k);
            if (night) {
                // gecə qüllələrin üzündə yanan alov: aşağıdan yuxarı dalğalanan narıncı-qırmızı
                const f = ctx.createLinearGradient(0, base, 0, base - h * k);
                const sh = 0.5 + 0.5 * Math.sin(fx.t * 3 + u * 9);
                f.addColorStop(0, '#ff4b14');
                f.addColorStop(0.35 + sh * 0.2, '#ff9a1f');
                f.addColorStop(0.75, '#ffd25a');
                f.addColorStop(1, '#ff6a1a');
                ctx.fillStyle = f;
                ctx.shadowColor = 'rgba(255, 120, 30, .8)';
                ctx.shadowBlur = 6;
                ctx.fill();
                ctx.shadowBlur = 0;
            } else {
                // gündüz şüşə fasad: səmanı əks etdirir
                const f = ctx.createLinearGradient(fxp - fw / 2, 0, fxp + fw / 2, 0);
                f.addColorStop(0, hazy ? '#8a96a2' : '#5f7d9c');
                f.addColorStop(0.5, hazy ? '#b3bcc5' : '#9cc0de');
                f.addColorStop(1, hazy ? '#76828e' : '#3f5a76');
                ctx.fillStyle = f;
                ctx.fill();
            }
        }

        // duman: şəhəri yarı örtən ağımtıl qatlar
        if (kind === 'fog') {
            for (let i = 0; i < 3; i++) {
                const yy = V(0.45 + i * 0.18), off = ((fx.t * 0.02 + i * 0.37) % 1) * ww;
                const fg = ctx.createLinearGradient(0, yy - 20, 0, yy + 20);
                fg.addColorStop(0, 'rgba(230, 233, 236, 0)');
                fg.addColorStop(0.5, night ? 'rgba(120, 126, 134, .55)' : 'rgba(236, 239, 241, .7)');
                fg.addColorStop(1, 'rgba(230, 233, 236, 0)');
                ctx.fillStyle = fg;
                ctx.fillRect(bx0 - ww + off, yy - 20, ww * 3, 40);
            }
        }

        // yağış və qar (pəncərənin arxasında, çöldə)
        if (win.drops.length) {
            ctx.strokeStyle = night ? 'rgba(170, 185, 210, .5)' : 'rgba(225, 235, 248, .65)';
            ctx.lineWidth = 0.8;
            ctx.beginPath();
            for (const d of win.drops) {
                const x = U(d.u), y = V(d.v);
                ctx.moveTo(x, y);
                ctx.lineTo(x - windX * 3, y - 7);
            }
            ctx.stroke();
        }
        if (win.flakes.length) {
            ctx.fillStyle = 'rgba(255, 255, 255, .95)';
            ctx.beginPath();
            for (const f of win.flakes) {
                const x = U(f.u), y = V(f.v);
                ctx.moveTo(x + f.r, y);
                ctx.arc(x, y, f.r, 0, Math.PI * 2);
            }
            ctx.fill();
            // altlıqda yığılmış qar
            ctx.fillStyle = '#f3f6f9';
            ctx.beginPath();
            ctx.moveTo(U(0), sill(0) + 3);
            for (let u = 0; u <= 1.001; u += 0.1) ctx.lineTo(U(u), sill(u) - 3 - Math.sin(u * 12) * 1.2);
            ctx.lineTo(U(1), sill(1) + 3);
            ctx.closePath();
            ctx.fill();
        }

        // şüşə: çəp parıltı və (yağışda) şüşəyə yapışmış damcılar
        ctx.fillStyle = 'rgba(255, 255, 255, .08)';
        ctx.beginPath();
        ctx.moveTo(U(0.15), by0); ctx.lineTo(U(0.38), by0); ctx.lineTo(U(0.05), by1); ctx.lineTo(U(-0.18), by1);
        ctx.closePath();
        ctx.fill();
        for (const b of win.beads) {
            const x = U(b.u), y = V(b.v);
            ctx.fillStyle = 'rgba(200, 215, 230, .35)';
            ctx.beginPath(); ctx.arc(x, y, b.r, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = 'rgba(255, 255, 255, .7)';
            ctx.fillRect(x - b.r * 0.4, y - b.r * 0.5, 0.8, 0.8);
            if (b.sp) {                                         // süzülən damcının arxasında iz
                ctx.strokeStyle = 'rgba(200, 215, 230, .25)';
                ctx.lineWidth = 0.6;
                ctx.beginPath(); ctx.moveTo(x, y - b.r); ctx.lineTo(x, y - 10); ctx.stroke();
            }
        }

        // çərçivə: şaquli və üfüqi tirlər, kənarlarda daxili kölgə
        ctx.fillStyle = '#22120a';
        ctx.fillRect(X(WIN_MULLION) - 1.4, by0, 2.8, wh);
        ctx.fillRect(bx0, Y(WIN_TRANSOM) - 1.4, ww, 2.8);
        ctx.fillStyle = 'rgba(217, 162, 55, .35)';              // tirlərin işıqlı tərəfi
        ctx.fillRect(X(WIN_MULLION) - 1.4, by0, 0.8, wh);
        ctx.fillRect(bx0, Y(WIN_TRANSOM) - 1.4, ww, 0.8);
        ctx.strokeStyle = 'rgba(0, 0, 0, .55)';
        ctx.lineWidth = 4;
        ctx.beginPath();
        WIN_PTS.forEach(([x, y], i) => (i ? ctx.lineTo(X(x), Y(y)) : ctx.moveTo(X(x), Y(y))));
        ctx.closePath();
        ctx.stroke();
        ctx.restore();
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
        // yuxarı ortada havanın qısa təsviri (sol küncdə hesab durur)
        const icon = {clear: W.day ? '☀️' : '🌙', clouds: '☁️', rain: '🌧️', storm: '⛈️', snow: '❄️', fog: '🌫️'}[W.kind] || '';
        ctx.save();
        ctx.textAlign = 'center';
        ctx.shadowColor = 'rgba(0, 0, 0, .7)';
        ctx.shadowBlur = 4;
        ctx.fillStyle = '#fff';
        ctx.font = '800 13px Nunito, sans-serif';
        const bx = LW / 2;
        ctx.fillText(`${icon}${W.windy ? ' 💨' : ''} Bakı${W.temp !== null ? ` ${W.temp}°C` : ''}`, bx, 30);
        ctx.font = '700 11px Nunito, sans-serif';
        ctx.globalAlpha = 0.85;
        ctx.fillText(W.label, bx, 46);
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
                body: JSON.stringify({score, log: g.log}),
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

    // Yalnız real istifadəçi hərəkətləri qəbul olunur: skriptlə yaradılan (dispatchEvent) toxunuş və
    // düymə hadisələrində isTrusted = false olur — belə "basışlar" oyuna təsir etmir.
    canvas.addEventListener('pointerdown', e => { e.preventDefault(); if (e.isTrusted) tap(); });
    window.addEventListener('keydown', e => {
        if (!e.isTrusted || e.repeat) return; // basılı saxlanan düymə də saymır
        if ((e.code === 'Space' || e.code === 'Enter') && !e.target.closest('input, textarea, button, a')) {
            e.preventDefault();
            if (g.state === 'over') (resumeBtn.hidden ? reset() : resumeGame()); else tap();
        }
    });
    root.querySelector('.st-again').addEventListener('click', reset);
    resumeBtn.addEventListener('click', resumeGame);
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

    // Testlər üçün — yalnız lokal inkişaf mühitində (data-debug="1"). Canlı saytda yoxdur,
    // yoxsa brauzer konsolundan oyunu avtomatik oynamaq olardı.
    if (root.dataset.debug !== '1') return;
    window.__stack = {
        state: () => g, tap, consts: {GW, PERFECT, SUPPORT, BOOST_FROM}, boost, newSwing, stability, checkPrize, continueGame,
        manual: on => { manual = on; },
        weather: () => ({W, windX, drops: fx.drops.length, flakes: fx.flakes.length, leaves: fx.leaves.length}),
        step: (dt = 1 / 60) => { if (g.state !== 'over' || g.falling) update(dt); },
        // animasiya dayananda (gizli tab) kadrı əl ilə çəkmək üçün
        draw: () => { ctx.setTransform(dpr * scale, 0, 0, dpr * scale, 0, 0); drawScene(); },
    };
})();
