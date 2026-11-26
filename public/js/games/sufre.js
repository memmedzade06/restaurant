/**
 * Süfrəni doldur. Ofisiant sinisini sağa-sola aparıb düşən süfrə nemətlərini tutursan: paxlava, şəkərbura, qənd,
 * paxlava, şəkərbura, şokolad (2 xal), qənd, limon, qoz (1 xal), armudu stəkanda çay (3 xal), qızıl çaynik (5 xal), qəlyan (10 xal). Közərmiş qəlyan kömürü siniyə düşsə — oyun bitir.
 * (Kodda köhnə adlar qalıb: "basket" — sini, növ "b" — kömür.)
 *
 * Əşyaların siyahısını server verir (POST /basla). Hər əşya sininin xəttinə çatan anda sininin yeri yadda saxlanır
 * və oyun bitəndə yalnız bu yerlər serverə göndərilir — hesabı server özü hesablayır (App\Services\SufreGame).
 * Ona görə buradakı düsturlar (crossTime, itemX, kombo) serverdəkilərlə eyni olmalıdır.
 */
(function () {
    const root = document.getElementById('sufre');
    if (!root) return;

    const C = JSON.parse(root.dataset.consts);
    const W = C.W, H = C.H;
    const cfg = {
        canSave: root.dataset.canSave === '1',
        startUrl: root.dataset.startUrl,
        saveUrl: root.dataset.saveUrl,
        registerUrl: root.dataset.registerUrl,
        prizeUrl: root.dataset.prizeUrl,
        prizesUrl: root.dataset.prizesUrl,
        prizes: JSON.parse(root.dataset.prizes || '{}'),   // {xal: {key, label}}
        prizeLeft: root.dataset.prizeLeft === '' ? null : parseInt(root.dataset.prizeLeft, 10), // null — qonaq
        csrf: document.querySelector('meta[name="csrf-token"]')?.content,
    };
    const PRIZE_MINS = Object.keys(cfg.prizes).map(Number).sort((a, b) => a - b);
    let best = parseInt(root.dataset.best || '0', 10);
    let bonus = JSON.parse(root.dataset.bonus || '{"tiers":[]}');

    const stage = root.querySelector('.st-stage');
    const canvas = stage.querySelector('canvas');
    const ctx = canvas.getContext('2d');
    const startBox = stage.querySelector('.st-start');
    const result = stage.querySelector('.st-result');
    const offer = stage.querySelector('.st-offer');
    const fullBtn = stage.querySelector('.st-full');
    let offered = {};          // bu oyunda təklif olunmuş hədiyyə həddləri
    let claiming = false;

    // xalça naxışı: fonda zəif görünür, aşağıda süfrə kimi
    const cloth = new Image();
    cloth.src = root.dataset.cloth;
    // adi nemətlər (növü 'n', it[5] — hansı nemət): paxlava, şəkərbura, qənd, limon, qoz
    const SWEETS = ['paxlava', 'shekerbura', 'qend', 'limon', 'qoz', 'shokolad'];
    const SWEET_COLORS = {
        paxlava: ['#e9b45a', '#b9772a', '#7fae3c'], shekerbura: ['#f6deaa', '#d9ac62'], qend: ['#ffffff', '#e4e0d8'],
        limon: ['#ffe066', '#f2c200'], qoz: ['#a87444', '#6e4321'], shokolad: ['#6b3519', '#c8262c', '#e9b847'],
    };
    let tray = [];             // sinidə yığılan nemətlər: sini dolanda (TRAY_FULL) "Süfrə hazır!" deyib özü boşalır
    let trayDoneAt = 0;        // sini dolduğu an (oyun vaxtı): bir az dolu görünür, sonra boşalır
    const TRAY_FULL = 8;

    // ---- düsturlar (serverdəki SufreGame ilə eyni) ----
    const crossTime = it => it[0] + (C.catchY - C.startY) / it[3];
    // oynadıqca sini kiçilir (serverdəki SufreGame::halfAt ilə eyni)
    const halfAt = tt => Math.max(C.halfMin, C.half - Math.max(0, tt - C.shrinkFrom) * C.shrinkRate);
    const itemX = (it, t) => it[1] === 'b' ? it[2] + 2 * Math.sin(it[4] + t * 3) : it[2] + 6 * Math.sin(it[4] + t * 2);
    const itemY = (it, t) => C.startY + it[3] * (t - it[0]);
    const itemValue = it => it[1] === 'n' ? (C.sweetValues[it[5]] ?? 1) : C.values[it[1]];

    // ---- vəziyyət ----
    let state = 'idle';        // idle | loading | play | over
    let items = [], cross = [], next = 0, live = [];
    let basket = [];           // i-ci əşya xəttə çatanda sininin yeri — serverə gedən yeganə məlumat
    let t = 0, last = 0;
    let bx = W / 2, target = W / 2;
    let score = 0, lives = C.lives, streak = 0, caught = 0;
    let parts = [], pops = [], shake = 0, flash = 0;
    let keys = {left: false, right: false};
    let auto = false;          // yalnız lokal testlər üçün (aşağıda __sufre)

    const combo = () => Math.min(C.maxCombo, 1 + Math.floor(streak / C.comboStep));

    // ---- ölçü ----
    let scale = 1, dpr = 1;
    function resize() {
        const full = root.classList.contains('is-full');
        let cssW = stage.clientWidth || W;
        if (full) cssW = Math.min(window.innerWidth, window.innerHeight * W / H);
        const cssH = cssW * H / W;
        dpr = Math.min(2, window.devicePixelRatio || 1);
        canvas.style.width = cssW + 'px';
        canvas.style.height = cssH + 'px';
        canvas.width = Math.round(cssW * dpr);
        canvas.height = Math.round(cssH * dpr);
        scale = cssW / W;
    }
    window.addEventListener('resize', resize);

    // ---- idarəetmə ----
    const clampX = x => { const h = halfAt(t); return Math.max(h, Math.min(W - h, x)); };
    const toGameX = clientX => { const r = canvas.getBoundingClientRect(); return (clientX - r.left) / r.width * W; };
    // Sürüşdürmə: barmaq sininin üstünə qoyulubsa, sini tutulduğu yerdən barmaqla birlikdə gedir (barmağın altına
    // sıçramır); sinidən kənara toxunulubsa, sini ora gəlir. Siçanla isə sini sadəcə göstəricinin ardınca gedir.
    let grab = 0;
    function press(clientX) {
        const x = toGameX(clientX);
        grab = Math.abs(x - bx) <= halfAt(t) + 24 ? bx - x : 0;
        target = clampX(x + grab);
    }
    const drag = clientX => { target = clampX(toGameX(clientX) + grab); };

    // Telefonda toxunuş birbaşa tutulur: basıb saxlayanda iPhone/Android brauzerləri (və Instagram, Facebook içindəki
    // brauzer) kontekst menyusu, mətn seçmə və ya səhifənin sürüşməsi ilə pointer hadisələrini kəsir — sini dayanırdı.
    const onTouch = e => {
        if (!e.touches.length) return;
        if (state === 'play') {
            e.preventDefault();
            (e.type === 'touchstart' ? press : drag)(e.touches[0].clientX);
        }
    };
    canvas.addEventListener('touchstart', onTouch, {passive: false});
    canvas.addEventListener('touchmove', onTouch, {passive: false});
    canvas.addEventListener('contextmenu', e => e.preventDefault());

    canvas.addEventListener('pointermove', e => {
        if (e.pointerType === 'touch' || state !== 'play') return; // toxunuş yuxarıda tutulur
        grab = 0;
        drag(e.clientX);
    });
    canvas.addEventListener('pointerdown', e => {
        if (state === 'idle') { start(); return; }
        if (e.pointerType !== 'touch' && state === 'play') { grab = 0; drag(e.clientX); }
    });
    startBox.style.pointerEvents = 'none';
    stage.addEventListener('click', e => {
        if (state === 'idle' && !e.target.closest('button')) start();
    });
    window.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft') { keys.left = true; if (state === 'play') e.preventDefault(); }
        if (e.key === 'ArrowRight') { keys.right = true; if (state === 'play') e.preventDefault(); }
        if ((e.key === ' ' || e.key === 'Enter') && state === 'idle' && document.activeElement?.tagName !== 'INPUT') {
            e.preventDefault();
            start();
        }
    });
    window.addEventListener('keyup', e => {
        if (e.key === 'ArrowLeft') keys.left = false;
        if (e.key === 'ArrowRight') keys.right = false;
    });
    result.querySelector('.st-again').addEventListener('click', () => {
        result.hidden = true;
        start();
    });
    offer.querySelector('.st-continue').addEventListener('click', () => {
        offer.hidden = true;
        if (state === 'offer') state = 'play';
    });
    offer.querySelector('.st-accept').addEventListener('click', acceptPrize);

    // ---- oyunun gedişi ----
    async function start() {
        if (state === 'loading') return;
        state = 'loading';
        startBox.hidden = true;
        try {
            const r = await fetch(cfg.startUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf},
            });
            if (!r.ok) throw new Error(r.status);
            items = (await r.json()).items;
        } catch (e) {
            state = 'idle';
            startBox.hidden = false;
            startBox.querySelector('b').textContent = 'Əlaqə alınmadı — yenidən toxunun';
            return;
        }
        cross = items.map(crossTime);
        next = 0; live = []; basket = [];
        t = 0; last = 0;
        bx = target = W / 2;
        score = 0; lives = C.lives; streak = 0; caught = 0; tray = []; trayDoneAt = 0;
        parts = []; pops = []; shake = 0; flash = 0;
        offered = {}; claiming = false;
        offer.hidden = true;
        result.querySelector('.st-prize').hidden = true;
        result.querySelector('.nb-result-box').classList.remove('has-prize');
        result.classList.remove('is-prize');
        result.querySelector('.st-again').textContent = 'Yenidən oyna';
        state = 'play';
    }

    // ---- hədiyyə: 500, 700, 1000 xal (config/games.php → sufre.prizes) ----
    const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    /** Xal hədiyyə həddini keçibsə — oyunu dayandırıb "götür / davam et" təklif edirik */
    function checkPrize() {
        const reached = PRIZE_MINS.filter(m => score >= m && !offered[m]);
        if (!reached.length) return;
        reached.forEach(m => { offered[m] = true; });
        if (cfg.canSave && cfg.prizeLeft !== null && cfg.prizeLeft <= 0) {
            pops.push({x: W / 2, y: C.catchY - 90, txt: 'Bugünkü hədiyyə götürülüb', l: 2, col: '#ffd86b'});
            return; // oyunu dayandırmırıq, sadəcə xal toplanır
        }
        const min = reached[reached.length - 1];
        const isLast = min === PRIZE_MINS[PRIZE_MINS.length - 1];
        const nextMin = PRIZE_MINS.find(m => m > min);
        const accept = offer.querySelector('.st-accept');
        const note = offer.querySelector('.st-offer-note');
        offer.querySelector('.st-offer-title').textContent = `${cfg.prizes[min].label} qazandınız!`;
        accept.disabled = false;
        accept.querySelector('span').textContent = 'Hədiyyəni götür';
        offer.querySelector('.st-continue span').textContent = isLast ? 'Hədiyyəsiz davam et' : 'Davam et';
        if (!cfg.canSave) {
            accept.hidden = true;
            note.innerHTML = `Hədiyyə kodu almaq üçün <a href="${cfg.registerUrl}">qeydiyyatdan keçin</a> və profilinizi təsdiqləyin.`;
        } else {
            accept.hidden = false;
            note.innerHTML = isLast
                ? 'Hədiyyəni götürsəniz, kod veriləcək və oyun bitəcək. Davam etsəniz, sadəcə rekord üçün oynayırsınız.'
                : `Hədiyyəni götürsəniz, kod veriləcək və oyun bitəcək. Davam etsəniz, növbəti hədəf: <b>${nextMin} xal — ${esc(cfg.prizes[nextMin].label)}</b>! Amma kömür siniyə düşsə, hədiyyə yanır.`;
        }
        state = 'offer';
        offer.hidden = false;
    }

    async function acceptPrize() {
        if (claiming || state !== 'offer') return;
        claiming = true;
        const accept = offer.querySelector('.st-accept');
        const note = offer.querySelector('.st-offer-note');
        accept.disabled = true;
        accept.querySelector('span').textContent = 'Gözləyin...';
        let r = null, d = {};
        try {
            r = await fetch(cfg.prizeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf},
                // indiyə qədər xəttə çatan hər əşya üçün sininin yeri və oyunun dayandığı an — hesabı server yoxlayır
                body: JSON.stringify({basket: Array.from({length: basket.length}, (_, i) => basket[i] ?? null), until: t}),
            });
            d = await r.json().catch(() => ({}));
        } catch (e) { /* əlaqə yoxdur */ }
        claiming = false;

        if (!r || !r.ok || !d.prize) {
            note.textContent = d.message || 'Xəta baş verdi. Yenidən cəhd edin.';
            accept.disabled = false;
            accept.querySelector('span').textContent = 'Hədiyyəni götür';
            if (r && r.status === 429) { cfg.prizeLeft = 0; accept.hidden = true; }
            if (r && r.status === 422) accept.hidden = true;
            offer.querySelector('.st-continue span').textContent = 'Davam et'; // hədiyyəsiz oynamağa davam etmək olar
            return;
        }

        if (cfg.prizeLeft !== null) cfg.prizeLeft--;
        offer.hidden = true;
        state = 'over';
        best = d.best;
        const bestEl = document.getElementById('sfBest');
        if (bestEl) bestEl.textContent = best;
        result.querySelector('.st-title').textContent = 'Təbriklər! 🎉';
        result.querySelector('.st-score').textContent = d.score;
        const bonusLine = d.bonus > 0 ? '<br>Balansınıza +' + String(Math.round(d.bonus * 100) / 100).replace('.', ',') + ' xal yazıldı' : '';
        result.querySelector('.st-note').innerHTML = `${d.score} xal — <b>${esc(d.prize.label)}</b> qazandınız.` + bonusLine;
        result.querySelector('.nb-result-box').classList.add('has-prize');
        result.classList.add('is-prize');
        const box = result.querySelector('.st-prize');
        box.innerHTML = `<div class="coupon-row"><div class="prize-code">${esc(d.prize.code)}</div></div>` +
            `<p class="coupon-note">Kodu ofisianta göstərin. ${esc(d.prize.expires)}-dək etibarlıdır. ` +
            `<a href="${cfg.prizesUrl}">Hədiyyələrim</a>-də də saxlanılır.</p>` +
            (window.earnMoreHtml ? window.earnMoreHtml() : '');
        box.hidden = false;
        result.querySelector('.st-again').textContent = 'Yeni oyun';
        result.hidden = false;
    }

    function step(dt) {
        const prevT = t, prevBx = bx;
        t += dt;

        // sini: hədəfə doğru, amma serverin icazə verdiyi sürətdən tez yox
        if (keys.left || keys.right) target = clampX(target + ((keys.right ? 1 : 0) - (keys.left ? 1 : 0)) * C.maxSpeed * 0.32 * dt);
        if (auto) target = autoTarget();
        const maxMove = C.maxSpeed * 0.98 * dt;
        bx += Math.max(-maxMove, Math.min(maxMove, target - bx));

        while (next < items.length && items[next][0] <= t) live.push({i: next++, done: false, gone: false});

        // bu kadrda xəttə çatan əşyalar — çatma anına görə sıra ilə (son can gedəndə oyun dərhal bitir)
        const crossing = live.filter(o => !o.done && cross[o.i] <= t).sort((a, b) => cross[a.i] - cross[b.i]);
        for (const o of crossing) {
            const tc = cross[o.i];
            if (tc > C.maxSeconds) { finish(); return; }
            const k = t > prevT ? (tc - prevT) / (t - prevT) : 1;
            const at = Math.round((prevBx + (bx - prevBx) * Math.max(0, Math.min(1, k))) * 10) / 10;
            basket[o.i] = at;
            o.done = true;
            const it = items[o.i];
            const x = itemX(it, tc);
            const hit = Math.abs(x - at) <= halfAt(tc);
            if (it[1] === 'b') {
                if (hit) {
                    o.gone = true;
                    lives--;
                    streak = 0;
                    boom(x, C.catchY);
                    if (lives <= 0) { finish(); return; }
                }
            } else if (hit) {
                o.gone = true;
                const v = itemValue(it) * combo();
                score += v;
                streak++;
                caught++;
                if (!trayDoneAt) {
                    tray.push(it);
                    if (tray.length >= TRAY_FULL) { // sini doldu — ofisiant aparır
                        trayDoneAt = t;
                        pops.push({x: bx, y: C.catchY - 40, txt: 'Süfrə hazır!', l: 1.2, col: '#ffd86b'});
                    }
                }
                burst(x, C.catchY - 10, colorOf(it), '+' + v, 14, 4);
            } else {
                streak = 0; // nemət yerə düşdü: kombo sıfırlanır, sinidəkilər isə qalır
            }
        }
        if (trayDoneAt && t - trayDoneAt > 0.6) { tray = []; trayDoneAt = 0; }
        if (t >= C.maxSeconds) { finish(); return; }
        checkPrize(); // bu kadrda xəttə çatan bütün əşyalar sayılandan sonra (server də "until" anına qədər sayır)

        // ekrandan çıxan və yerə düşən əşyalar
        live = live.filter(o => {
            if (o.gone) return false;
            const y = itemY(items[o.i], t);
            if (o.done && items[o.i][1] === 'b' && y > H - 14) {
                burst(itemX(items[o.i], t), H - 16, ['#ff7a2e', '#ffb02e', '#5c5852', '#2b2a28'], '', 12, 4); // kömür süfrəyə düşüb sönür
                return false;
            }
            return y < H + 60;
        });

        for (const p of parts) { p.x += p.vx * dt; p.y += p.vy * dt; p.vy += 320 * dt; p.l -= dt; }
        parts = parts.filter(p => p.l > 0);
        for (const p of pops) { p.y -= 40 * dt; p.l -= dt; }
        pops = pops.filter(p => p.l > 0);
        shake = Math.max(0, shake - dt);
        flash = Math.max(0, flash - dt);
    }

    async function finish() {
        state = 'over';
        const title = result.querySelector('.st-title');
        const scoreEl = result.querySelector('.st-score');
        const note = result.querySelector('.st-note');
        title.textContent = lives <= 0 ? 'Kömür siniyə düşdü!' : 'Vaxt bitdi!';
        scoreEl.textContent = score;
        note.textContent = '';
        setTimeout(() => { result.hidden = false; }, 700);

        if (!cfg.canSave) {
            note.innerHTML = 'Nəticəniz yadda qalsın və xal bonusu qazanasınız deyə <a href="' + cfg.registerUrl + '">qeydiyyatdan keçin</a>.';
            return;
        }
        note.textContent = 'Nəticə yoxlanılır…';
        const sent = Array.from({length: basket.length}, (_, i) => basket[i] ?? null);
        try {
            const r = await fetch(cfg.saveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf},
                body: JSON.stringify({basket: sent}),
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok) {
                note.textContent = d.message || 'Nəticə yazılmadı.';
                return;
            }
            scoreEl.textContent = d.score; // serverin hesabı əsasdır
            best = d.best;
            const bestEl = document.getElementById('sfBest');
            if (bestEl) bestEl.textContent = best;
            const lines = [];
            if (d.record) lines.push('Yeni rekord! 🎉');
            else lines.push('Rekordunuz: ' + best + ' xal');
            if (d.bonus > 0) lines.push('Balansınıza +' + String(Math.round(d.bonus * 100) / 100).replace('.', ',') + ' xal yazıldı');
            if (d.bonusInfo) bonus = d.bonusInfo;
            const nextTier = bonus.tiers.find(x => !x.done);
            if (nextTier && !(d.bonus > 0)) lines.push(nextTier.score + ' xala çatsanız, +' + String(nextTier.points).replace('.', ',') + ' xal bonus');
            note.innerHTML = lines.map(l => '<span class="d-block">' + l + '</span>').join('');
        } catch (e) {
            note.textContent = 'Əlaqə alınmadı — nəticə yazılmadı.';
        }
    }

    // ---- effektlər ----
    function colorOf(it) {
        if (it[1] === 'g') return ['#ffd24a', '#f0a500', '#fff2b0'];
        if (it[1] === 'h') return ['#2a9696', '#c9973a', '#e8f5f0'];
        if (it[1] === 'c') return SWEET_COLORS.shokolad;
        if (it[1] === 'f') return ['#c0501f', '#8a2f10', '#ffe0b0'];
        return SWEET_COLORS[SWEETS[it[5] % SWEETS.length]];
    }
    function burst(x, y, col, txt, n, size, txtCol) {
        for (let i = 0; i < n; i++) {
            const a = Math.random() * Math.PI * 2, v = 60 + Math.random() * (n > 20 ? 240 : 130);
            parts.push({x, y, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 40, l: 0.55 + Math.random() * 0.2,
                col: Array.isArray(col) ? col[Math.random() * col.length | 0] : col, s: size});
        }
        if (txt) pops.push({x, y: y - 14, txt, l: 0.9, col: txtCol || '#ffe9a6'});
    }
    function boom(x, y) {
        // közərmiş kömür siniyə düşdü: qığılcım və tüstü
        burst(x, y, ['#ff4d1f', '#ff9a2e', '#ffd86b', '#5c5852', '#2b2a28'], C.lives > 1 ? '-1 can' : 'Yandı!', 36, 5, '#ff8a6b');
        shake = 0.35;
        flash = 0.25;
    }

    // ---- çəkmə ----
    function drawBackground() {
        const bot = H - 24;
        // tünd şərab rəngli fon, üstündə zəif xalça naxışı — nemətlər aydın seçilsin
        // (rəsmli fon istifadə olunmur: orada da çaydan və stəkanlar var, düşən əşyalarla qarışır)
        const bg = ctx.createRadialGradient(W / 2, bot * 0.35, 40, W / 2, bot * 0.4, bot);
        bg.addColorStop(0, '#4a1d18');
        bg.addColorStop(1, '#1e0b08');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, bot);
        if (cloth.complete && cloth.naturalWidth) {
            const k = Math.max(W / cloth.naturalWidth, bot / cloth.naturalHeight);
            ctx.globalAlpha = 0.12;
            ctx.drawImage(cloth, (W - cloth.naturalWidth * k) / 2, (bot - cloth.naturalHeight * k) / 2, cloth.naturalWidth * k, cloth.naturalHeight * k);
            ctx.globalAlpha = 1;
        }
        // aşağıda süfrə
        if (cloth.complete && cloth.naturalWidth) {
            ctx.drawImage(cloth, 0, 0, cloth.naturalWidth, cloth.naturalWidth * 24 / W, 0, bot, W, 24);
        } else {
            ctx.fillStyle = '#7e1a1a';
            ctx.fillRect(0, bot, W, 24);
        }
        ctx.fillStyle = 'rgba(0, 0, 0, .35)';
        ctx.fillRect(0, bot, W, 3);
    }

    /*
     * Nemətlər real görünsün deyə ətraflı çəkilir (qatlar, teksturalar, parıltılar). Bu, hər kadrda çəkmək üçün
     * baha başa gəlir, ona görə hər nemət bir dəfə ayrıca kətana (sprayta) çəkilir və hazır şəkil kimi qoyulur.
     * Kətanın ölçüsü dəyişəndə (böyük ekran, pəncərənin eni) spraytlar yenidən yaradılır ki, bulanıq olmasın.
     * Hərəkətli hissələr (çayın buğu, kömürün közərməsi, çaynikin parıltısı) hər kadrda üstündən çəkilir.
     */
    const sprites = {};
    function sprite(key, w, h, paint) {
        const res = Math.max(1, dpr * scale) * 1.5;
        const sp = sprites[key];
        if (sp && sp.res === res) return sp;
        const cv = document.createElement('canvas');
        cv.width = Math.ceil(w * res);
        cv.height = Math.ceil(h * res);
        const c = cv.getContext('2d');
        c.setTransform(res, 0, 0, res, (w / 2) * res, (h / 2) * res); // (0, 0) — spraytın mərkəzi
        paint(c);
        return (sprites[key] = {cv, w, h, res});
    }
    const blit = (sp, s) => ctx.drawImage(sp.cv, -sp.w / 2 * s, -sp.h / 2 * s, sp.w * s, sp.h * s);

    // nemətlərin rəsmləri ayrıca faylda (sufre-art.js) — Oyunlar səhifəsindəki kart da onları istifadə edir
    const {GW, GH, paintPaxlava, paintShekerbura, paintQend, paintLimon, paintShokolad, paintQoz,
        paintGlass, paintTeapot, paintHookah, paintCoal, paintCoalGlow} = window.SufreArt;

    /** Buğ: Stəkan qülləsindəki kimi ağzın üstündə yumşaq duman ləkələri (stəkanın lokal koordinatlarında) */
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
        ctx.beginPath(); ctx.arc(0, 0, rx, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    }
    function drawSteam(s, tt, seed) {
        const rimY = -GH / 2 * s;
        mistBlob(0, rimY - 2 * s, GW * 0.62 * s, 7 * s, 0.45 * (0.55 + 0.15 * Math.sin(tt * 1.3 + seed * 6)));
        for (let k = 0; k < 5; k++) {
            const phase = (tt * 0.3 + k / 5 + seed) % 1;
            const a = Math.sin(phase * Math.PI) * 0.45;
            const cx = ((k - 2) * GW * 0.15 + Math.sin(tt * 0.9 + k * 1.7 + seed * 5) * 3) * s;
            const rx = GW * 0.2 * (0.8 + phase * 0.6) * s;
            mistBlob(cx, rimY - (3 + phase * 10) * s, rx, rx * 0.5, a);
        }
    }

    function drawCoal(it, tt, s) {
        const pulse = (Math.sin(tt * 5 + it[4]) + 1) / 2;
        const glow = ctx.createRadialGradient(0, 3 * s, 4 * s, 0, 3 * s, 30 * s);
        glow.addColorStop(0, `rgba(255, 100, 25, ${0.35 + pulse * 0.25})`);
        glow.addColorStop(1, 'rgba(255, 60, 10, 0)');
        ctx.fillStyle = glow;
        ctx.beginPath(); ctx.arc(0, 3 * s, 30 * s, 0, Math.PI * 2); ctx.fill();
        blit(sprite('coal', 36, 40, paintCoal), s);
        ctx.globalAlpha = 0.5 + pulse * 0.5;
        blit(sprite('coalGlow', 36, 40, paintCoalGlow), s);
        ctx.globalAlpha = 1;
        for (let i = 0; i < 3; i++) { // qığılcımlar yuxarı uçur
            const ph = (tt * 1.6 + i / 3 + it[4]) % 1;
            ctx.fillStyle = `rgba(255, 200, 80, ${1 - ph})`;
            ctx.fillRect((Math.sin(i * 2.1 + it[4]) * 9) * s, (-15 - ph * 22) * s, 2 * s, 2 * s);
        }
    }

    const SWEET_PAINT = {paxlava: paintPaxlava, shekerbura: paintShekerbura, qend: paintQend, limon: paintLimon, qoz: paintQoz, shokolad: paintShokolad};
    // Ölçülər real ölçülərə uyğun nisbətdədir (qənd ~2 sm, kömür kubu ~2,5 sm, qoz ~3,5 sm, limon dilimi ~5 sm,
    // paxlava ~6 sm, şəkərbura ~8 sm, armudu stəkan ~10 sm, çaynik ~15 sm). Xırda nemətlər oyunda görünsün deyə
    // nisbət bir az yumşaldılıb. Ölçü yalnız görünüşdür — tutulub-tutulmamağı nemətin mərkəzi və sininin eni təyin edir.
    // Qəlyan əslində çaynikdən dəfələrlə hündürdür (~70 sm); oyunda ən böyük əşyadır, amma ekrana sığsın deyə kiçildilib.
    const SIZE = {qend: 0.6, b: 0.72, qoz: 0.62, limon: 0.68, shokolad: 0.75, paxlava: 0.78, shekerbura: 0.9, f: 0.62, g: 1.3, h: 0.95};
    function drawItem(it, x, y, tt, s = 1, still = false) {
        ctx.save();
        ctx.translate(x, y);
        if (!still) ctx.rotate(Math.sin(tt * 2.2 + it[4]) * (it[1] === 'b' ? 0.25 : 0.15));
        const kind = it[1] === 'n' ? SWEETS[it[5] % SWEETS.length] : it[1] === 'c' ? 'shokolad' : it[1];
        s *= SIZE[kind];
        if (it[1] === 'b') {
            drawCoal(it, tt, s);
        } else if (it[1] === 'f') {
            blit(sprite('glass', GW * 1.4, GH + 12, paintGlass), s);
            if (!still) drawSteam(s, tt, it[4]);
        } else if (it[1] === 'g') {
            blit(sprite('teapot', 64, 56, paintTeapot), s);
            if (!still) {
                const a = (Math.sin(tt * 6 + it[4]) + 1) / 2; // parıltı
                ctx.fillStyle = `rgba(255, 252, 230, ${0.35 + a * 0.65})`;
                for (const [px, py, r] of [[-8, -1, 2.6], [17, -10, 2], [-19, -15, 1.6]]) {
                    ctx.beginPath();
                    ctx.moveTo(px * s, (py - r * 2) * s); ctx.lineTo((px + r * 0.4) * s, py * s); ctx.lineTo(px * s, (py + r * 2) * s); ctx.lineTo((px - r * 0.4) * s, py * s);
                    ctx.closePath(); ctx.fill();
                    ctx.beginPath();
                    ctx.moveTo((px - r * 2) * s, py * s); ctx.lineTo(px * s, (py + r * 0.4) * s); ctx.lineTo((px + r * 2) * s, py * s); ctx.lineTo(px * s, (py - r * 0.4) * s);
                    ctx.closePath(); ctx.fill();
                }
            }
        } else if (it[1] === 'h') {
            blit(sprite('hookah', 70, 100, paintHookah), s);
            if (!still) { // başlıqdan tüstü qalxır, köz közərir
                const pulse = (Math.sin(tt * 4 + it[4]) + 1) / 2;
                ctx.fillStyle = `rgba(255, 120, 30, ${0.35 + pulse * 0.4})`;
                ctx.beginPath(); ctx.ellipse(0, -40 * s, 4.5 * s, 1.6 * s, 0, 0, Math.PI * 2); ctx.fill();
                for (let k = 0; k < 4; k++) {
                    const ph = (tt * 0.35 + k / 4 + it[4]) % 1;
                    const rx = (3 + ph * 7) * s;
                    mistBlob(Math.sin(tt * 1.3 + k * 2 + it[4]) * 4 * s, (-43 - ph * 22) * s, rx, rx * 0.7, Math.sin(ph * Math.PI) * 0.35);
                }
            }
        } else {
            blit(sprite(kind, 48, 48, SWEET_PAINT[kind]), s);
        }
        ctx.restore();
    }

    // ofisiant sinisi: mis rəngli dəyirmi sini; üstündə ardıcıl tutulan nemətlər yığılır
    function drawTray(tt) {
        const y = C.catchY + 8, rx = halfAt(t) + 6, ry = 13;
        ctx.fillStyle = 'rgba(0, 0, 0, .3)';
        ctx.beginPath(); ctx.ellipse(bx, H - 14, rx * 0.8, 5, 0, 0, 7); ctx.fill();
        ctx.fillStyle = '#7a3e14';
        ctx.beginPath(); ctx.ellipse(bx, y + 4, rx, ry, 0, 0, 7); ctx.fill();
        const g = ctx.createLinearGradient(bx - rx, 0, bx + rx, 0);
        g.addColorStop(0, '#b8642a'); g.addColorStop(0.45, '#f2b36b'); g.addColorStop(1, '#a4531d');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.ellipse(bx, y, rx, ry, 0, 0, 7); ctx.fill();
        ctx.strokeStyle = 'rgba(122, 62, 20, .7)'; ctx.lineWidth = 1.2;
        ctx.beginPath(); ctx.ellipse(bx, y, rx - 6, ry - 3.5, 0, 0, 7); ctx.stroke();
        ctx.beginPath(); ctx.ellipse(bx, y, rx * 0.45, ry * 0.45, 0, 0, 7); ctx.stroke();
        ctx.fillStyle = 'rgba(122, 62, 20, .55)';
        for (let i = 0; i < 12; i++) { // naxış
            const a = i * Math.PI / 6;
            ctx.beginPath(); ctx.arc(bx + Math.cos(a) * (rx - 12), y + Math.sin(a) * (ry - 6), 1.2, 0, 7); ctx.fill();
        }
        // tutulan nemətlər sinidə yığılır; sini dolanda bir anlıq parlayır və boşalır
        const n = tray.length, gap = Math.min(12, (rx * 2 - 22) / (TRAY_FULL - 1));
        if (trayDoneAt) {
            ctx.fillStyle = `rgba(255, 216, 107, ${0.45 * (1 - (t - trayDoneAt) / 0.6)})`;
            ctx.beginPath(); ctx.ellipse(bx, y, rx + 4, ry + 3, 0, 0, 7); ctx.fill();
        }
        tray.forEach((it, i) => drawItem(it, bx + (i - (n - 1) / 2) * gap, y - 6 - (i % 2) * 3, tt, 0.6, true));
    }

    function drawHud() {
        ctx.textAlign = 'left';
        const withLives = C.lives > 1; // bir can olanda ürəklər göstərilmir
        ctx.fillStyle = 'rgba(30, 11, 11, .55)';
        ctx.beginPath(); ctx.roundRect(10, 10, 132, withLives ? 54 : 38, 12); ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.font = '700 22px sans-serif';
        ctx.fillText(score, 20, 36);
        if (withLives) {
            ctx.font = '16px sans-serif';
            let hearts = '';
            for (let i = 0; i < C.lives; i++) hearts += i < lives ? '♥' : '♡';
            ctx.fillStyle = '#ff6b6b';
            ctx.fillText(hearts, 20, 56);
        }
        const c = combo();
        ctx.fillStyle = c > 1 ? '#ffc23d' : 'rgba(255, 255, 255, .6)';
        ctx.font = '700 15px sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText('x' + c, 132, 36);

        // geri sayım (yuxarıda ortada): son 10 saniyədə qırmızı və döyünür
        const left = Math.max(0, Math.ceil(C.maxSeconds - t));
        const hurry = left <= 10 && state !== 'over';
        const pulse = hurry ? 1 + 0.08 * Math.sin(t * 12) : 1;
        ctx.save();
        ctx.translate(W / 2, 28);
        ctx.scale(pulse, pulse);
        ctx.fillStyle = hurry ? 'rgba(160, 20, 15, .85)' : 'rgba(30, 11, 11, .55)';
        ctx.beginPath(); ctx.roundRect(-38, -18, 76, 36, 12); ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.font = '700 20px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0'), 0, 7);
        ctx.restore();
    }

    function draw(now) {
        ctx.setTransform(dpr * scale, 0, 0, dpr * scale, 0, 0);
        ctx.save();
        if (shake > 0) {
            const s = 7 * shake / 0.35;
            ctx.translate((Math.random() - 0.5) * 2 * s, (Math.random() - 0.5) * 2 * s);
        }
        drawBackground();
        const tt = state === 'idle' ? now : t;
        if (state !== 'idle') {
            for (const o of live) {
                const it = items[o.i];
                drawItem(it, itemX(it, tt), itemY(it, tt), tt);
            }
        }
        for (const p of parts) {
            ctx.globalAlpha = Math.max(0, Math.min(1, p.l * 2));
            ctx.fillStyle = p.col;
            ctx.fillRect(p.x, p.y, p.s, p.s);
        }
        ctx.globalAlpha = 1;
        ctx.textAlign = 'center';
        ctx.font = '700 18px sans-serif';
        for (const p of pops) {
            ctx.globalAlpha = Math.min(1, p.l * 2);
            ctx.fillStyle = p.col;
            ctx.fillText(p.txt, p.x, p.y);
        }
        ctx.globalAlpha = 1;
        drawTray(tt);
        ctx.restore();
        if (flash > 0) {
            ctx.fillStyle = 'rgba(255, 110, 30, ' + flash * 1.4 + ')';
            ctx.fillRect(0, 0, W, H);
        }
        if (state === 'play' || state === 'over' || state === 'offer') drawHud();
    }

    function frame(ms) {
        const now = ms / 1000;
        const dt = last ? Math.min(now - last, 0.05) : 0;
        last = now;
        if (state === 'play') step(dt);
        else if (state === 'over') {
            for (const p of parts) { p.x += p.vx * dt; p.y += p.vy * dt; p.vy += 320 * dt; p.l -= dt; }
            parts = parts.filter(p => p.l > 0);
            shake = Math.max(0, shake - dt);
            flash = Math.max(0, flash - dt);
        }
        draw(now);
        requestAnimationFrame(frame);
    }

    // ---- böyük ekran (Stəkan qülləsindəki kimi) ----
    const nativeFull = () => document.fullscreenElement || document.webkitFullscreenElement;
    function setFull(on) {
        root.classList.toggle('is-full', on);
        document.body.classList.toggle('st-no-scroll', on);
        fullBtn.querySelector('i').className = on ? 'fa fa-compress' : 'fa fa-expand';
        fullBtn.title = on ? 'Kiçik ekran' : 'Böyük ekran';
        fullBtn.setAttribute('aria-label', fullBtn.title);
        resize();
    }
    fullBtn.addEventListener('click', () => {
        const on = !root.classList.contains('is-full');
        if (on) {
            const req = root.requestFullscreen || root.webkitRequestFullscreen;
            if (req) {
                const p = req.call(root);
                if (p && p.catch) p.catch(() => {});
            }
        } else if (nativeFull()) {
            (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        }
        setFull(on);
        fullBtn.blur();
    });
    const onFullChange = () => { if (!nativeFull() && root.classList.contains('is-full')) setFull(false); };
    document.addEventListener('fullscreenchange', onFullChange);
    document.addEventListener('webkitfullscreenchange', onFullChange);

    // ---- avtopilot: yalnız lokal testlər üçün ----
    function autoTarget() {
        let bestX = bx, bestT = Infinity;
        for (const o of live) {
            if (o.done) continue;
            const it = items[o.i];
            if (it[1] === 'b') continue;
            const tc = cross[o.i];
            if (tc < bestT) { bestT = tc; bestX = itemX(it, tc) + 20; }
        }
        for (const o of live) {
            if (o.done || items[o.i][1] !== 'b') continue;
            const tc = cross[o.i];
            if (tc - t < 0.6 && Math.abs(itemX(items[o.i], tc) - bestX) < halfAt(tc) + 6) bestX += bestX > W / 2 ? -110 : 110;
        }
        return clampX(bestX);
    }

    resize();
    requestAnimationFrame(frame);

    if (root.dataset.debug !== '1') return;
    window.__sufre = {state: () => ({state, t, score, lives, caught, combo: combo(), basket, items, tray: tray.length, half: halfAt(t), bx, target}), auto: on => { auto = on; }, start,
        paints: {paxlava: paintPaxlava, shekerbura: paintShekerbura, qend: paintQend, limon: paintLimon, qoz: paintQoz, shokolad: paintShokolad, glass: paintGlass, teapot: paintTeapot, hookah: paintHookah, coal: paintCoal, coalGlow: paintCoalGlow}};
})();
