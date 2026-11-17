/* =========================================================
   50 Qəpik — Hədiyyə Kranı (3D)
   Şüşə qutunun eni, hündürlüyü və dərinliyi var: çəngəl sağa-sola
   və irəli-geri gedir. Şirniyyatlar (konfet, lolipop, lokum, paxlava,
   Şəki halvası, qızıl sikkə və s.) kürələr kimi fizika ilə qalaqlanır. Ön sol
   küncdəki çıxışa salanda xal qazanırsınız. Hələlik yalnız əyləncə üçündür.
   ========================================================= */
(function () {
    'use strict';

    var root = document.getElementById('claw');
    if (!root) return;

    var canvas = root.querySelector('canvas');
    var ctx = canvas.getContext('2d');

    /* ---------- dünya (x — en, y — hündürlük aşağı, z — dərinlik: 0 ön şüşə, D arxa divar) ---------- */
    var W = 420, H = 520;
    var G = { l: 22, r: 398, t: 118, b: 474 };      // ön şüşənin içi
    var FLOOR = G.b;
    var D = 150;                                     // qutunun dərinliyi
    var V = { x: W / 2, y: G.t + 18 };              // perspektivin qaçış nöqtəsi
    var SB = .68;                                    // arxa divarın miqyası
    var CHUTE = { l: G.l, r: 112, d: 70, top: 300 }; // ön sol küncdə çıxış quyusu (x < r, z < d)
    var CHUTE_X = (CHUTE.l + CHUTE.r) / 2, CHUTE_Z = CHUTE.d / 2;
    var RAIL = G.t + 8;
    var TOP_Y = G.t + 30;                            // çəngəlin başı ən yuxarıda
    var X_MIN = G.l + 24, X_MAX = G.r - 24, Z_MIN = 16, Z_MAX = D - 16;
    var GRAV = 1500;
    var STEP = 1 / 120;
    var ATTEMPTS = 5, TURN_SECONDS = 15;
    var SPEED = 150;

    var TYPES = {
        // n — qutuda neçə ədəd; az olan nadir və dəyərlidir
        konfet:    { name: 'Konfet',       r: 15, pts: 1, heavy: 0,   n: 6 },
        lolipop:   { name: 'Lolipop',      r: 15, pts: 1, heavy: .02, n: 4 },
        pismaniye: { name: 'Pişmaniye',    r: 17, pts: 1, heavy: 0,   n: 3 },
        lokum:     { name: 'Rəngli lokum', r: 14, pts: 1, heavy: 0,   n: 5 },
        sekerbura: { name: 'Şəkərbura',    r: 17, pts: 2, heavy: .06, n: 4 },
        paxlava:   { name: 'Paxlava',      r: 17, pts: 2, heavy: .08, n: 4 },
        badambura: { name: 'Badambura',    r: 16, pts: 2, heavy: .04, n: 3 },
        nabat:     { name: 'Nabat',        r: 15, pts: 2, heavy: .05, n: 3 },
        mutekke:   { name: 'Mütəkkə',      r: 17, pts: 2, heavy: .07, n: 3 },
        kete:      { name: 'Kətə',         r: 18, pts: 2, heavy: .08, n: 3 },
        qogal:     { name: 'Qoğal',        r: 20, pts: 3, heavy: .18, n: 3 },
        halva:     { name: 'Şəki halvası', r: 18, pts: 4, heavy: .14, n: 2 },
        sikke:     { name: 'Qızıl sikkə',  r: 13, pts: 5, heavy: .1,  n: 1 }
    };
    var CS = .8;   // çəngəlin miqyası

    function sc(z) { return 1 - (1 - SB) * z / D; }
    function proj(x, y, z) {
        var s = sc(z);
        return { x: V.x + (x - V.x) * s, y: V.y + (y - V.y) * s, s: s };
    }

    /* ---------- HTML elementləri ---------- */
    var $ = function (s) { return root.querySelector(s); };
    var el = {
        start: $('.cl-start'), result: $('.cl-result'), title: $('.cl-title'), score: $('.cl-score'),
        note: $('.cl-note'), again: $('.cl-again'), attempts: $('.cl-attempts'), points: $('.cl-points'),
        time: $('.cl-time'), drop: $('.cl-drop'), arrows: root.querySelectorAll('.cl-arrow')
    };
    var bestEl = document.querySelector('.cl-best');

    function readBest() { try { return parseInt(localStorage.getItem('claw_best'), 10) || 0; } catch (e) { return 0; } }
    function saveBest(v) { try { localStorage.setItem('claw_best', String(v)); } catch (e) { /* gizli rejim */ } }
    if (bestEl) bestEl.textContent = readBest();

    /* ---------- vəziyyət ---------- */
    var items = [], pops = [], confetti = [], sparkles = [];
    var claw, game;
    var input = { keyL: false, keyR: false, keyB: false, keyF: false, bx: 0, bz: 0, target: null };
    var clock = 0, flash = 0;

    function clamp(v, a, b) { return v < a ? a : v > b ? b : v; }
    function inChute(a) { return a.x < CHUTE.r && a.z < CHUTE.d; }

    function resetClaw() {
        claw = { x: CHUTE_X, y: TOP_Y, z: CHUTE_Z, vx: 0, vy: 0, vz: 0, open: 1, state: 'idle', t: 0, held: null, target: null, slip: -1, chance: 1 };
    }

    function makeItems() {
        var bag = [];
        Object.keys(TYPES).forEach(function (k) { for (var i = 0; i < TYPES[k].n; i++) bag.push(k); });
        for (var i = bag.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = bag[i]; bag[i] = bag[j]; bag[j] = t; }

        items = bag.map(function (k, i) {
            var r = TYPES[k].r;
            var x = CHUTE.r + 6 + r + Math.random() * (G.r - CHUTE.r - 6 - 2 * r);
            var z = r + Math.random() * (D - 2 * r);
            var y = G.t + 40 - i * 22;
            return { type: k, r: r, x: x, y: y, z: z, px: x, py: y, pz: z, ang: (Math.random() - .5) * .8, v: Math.floor(Math.random() * 4), held: false, gone: false };
        });
        // qalağı əvvəlcədən yerinə oturduruq ki, oyunçu düşməni görməsin
        for (var s = 0; s < 840; s++) physics(STEP, true);
    }

    function newGame() {
        resetClaw();
        makeItems();
        pops = []; confetti = [];
        game = { started: false, over: false, attempts: ATTEMPTS, score: 0, turnLeft: TURN_SECONDS, caught: [] };
        hud();
    }

    function start() {
        if (game.started) return;
        game.started = true;
        el.start.hidden = true;
        claw.state = 'ready';
        game.turnLeft = TURN_SECONDS;
        hud();
    }

    function again() {
        el.result.hidden = true;
        newGame();
        start();
    }

    /* ---------- fizika: kürələr, Verlet inteqrasiyası ---------- */
    function physics(dt, silent) {
        var i, j, a, b;
        for (i = 0; i < items.length; i++) {
            a = items[i];
            if (a.held || a.gone) continue;
            var vx = (a.x - a.px) * .995, vy = (a.y - a.py) * .995, vz = (a.z - a.pz) * .995;
            a.px = a.x; a.py = a.y; a.pz = a.z;
            a.x += vx; a.z += vz;
            a.y += vy + GRAV * dt * dt;
            a.ang += (vx + vz * .4) / a.r * .6;   // yuvarlananda fırlanır
        }
        for (var it = 0; it < 4; it++) {
            for (i = 0; i < items.length; i++) {
                a = items[i];
                if (a.gone) continue;
                for (j = i + 1; j < items.length; j++) {
                    b = items[j];
                    if (b.gone || (a.held && b.held)) continue;
                    var dx = b.x - a.x, dy = b.y - a.y, dz = b.z - a.z, m = a.r + b.r;
                    if (dx > m || dx < -m || dz > m || dz < -m) continue;
                    var d = Math.sqrt(dx * dx + dy * dy + dz * dz);
                    if (d >= m || d === 0) continue;
                    var push = (m - d) / d;
                    var wa = a.held ? 0 : b.held ? 1 : .5, wb = b.held ? 0 : 1 - wa;
                    a.x -= dx * push * wa; a.y -= dy * push * wa; a.z -= dz * push * wa;
                    b.x += dx * push * wb; b.y += dy * push * wb; b.z += dz * push * wb;
                }
                if (!a.held) bounds(a);
            }
        }
        for (i = items.length - 1; i >= 0; i--) {
            a = items[i];
            if (!a.gone && inChute(a) && a.y - a.r > FLOOR + 6) {
                a.gone = true;
                if (!silent) caught(a);
            }
            if (a.gone) items.splice(i, 1);
        }
    }

    function bounds(a) {
        a.x = clamp(a.x, G.l + a.r, G.r - a.r);
        a.z = clamp(a.z, a.r, D - a.r);
        if (inChute(a)) return;   // mərkəzi quyunun üstündədirsə — sərbəst düşür
        // quyunun şüşə divarları: quyu sütununa ən yaxın nöqtədən itələyirik
        var cx = clamp(a.x, CHUTE.l, CHUTE.r), cz = clamp(a.z, 0, CHUTE.d), cy = clamp(a.y, CHUTE.top, FLOOR + 400);
        var dx = a.x - cx, dy = a.y - cy, dz = a.z - cz, d = Math.sqrt(dx * dx + dy * dy + dz * dz);
        if (d < a.r) {
            if (d < .01) { dx = 1; dy = 0; dz = 0; d = 1; }
            var k = (a.r - d) / d;
            a.x += dx * k; a.y += dy * k; a.z += dz * k;
        }
        // döşəmə
        if (a.y + a.r > FLOOR) {
            a.y = FLOOR - a.r;
            a.px = a.x - (a.x - a.px) * .7;   // sürtünmə
            a.pz = a.z - (a.z - a.pz) * .7;
        }
    }

    /* ---------- çəngəl ---------- */
    function hubY() { return claw.y + 12 * CS; }
    function gripY() { return claw.y + 36 * CS; }
    function flat(a) { return Math.hypot(a.x - claw.x, a.z - claw.z); }

    function drop() {
        if (!game.started || game.over || claw.state !== 'ready') return;
        game.attempts--;
        claw.state = 'down';
        input.target = null;
        hud();
    }

    function touching() {
        var tip = hubY() + 35 * CS;
        if (tip >= FLOOR - 2) return true;
        for (var i = 0; i < items.length; i++) {
            var a = items[i];
            if (flat(a) < a.r * .8 + 6 && tip >= a.y - a.r * .15) return true;
        }
        return false;
    }

    function pickItem() {
        var best = null, bd = 1e9, gy = gripY();
        items.forEach(function (a) {
            var d = Math.hypot(a.x - claw.x, a.y - gy, a.z - claw.z);
            if (d < a.r + 9 && d < bd) { best = a; bd = d; }
        });
        return best;
    }

    // Tutuşun möhkəmliyi: ortadan tutmaq, yüngül və üstü boş oyuncaq — yaxşı şans
    function holdChance(a) {
        var off = flat(a) / a.r;
        var buried = 0;
        items.forEach(function (b) {
            if (b !== a && b.y < a.y - a.r * .3 && Math.hypot(b.x - a.x, b.y - a.y, b.z - a.z) < a.r + b.r + 2) buried++;
        });
        return clamp(.9 - off * .7 - TYPES[a.type].heavy - buried * .18, .05, .9);
    }

    function releaseHeld(slipped) {
        var a = claw.held;
        if (!a) return;
        a.held = false;
        a.px = a.x - claw.vx * STEP;
        a.py = a.y - claw.vy * STEP;
        a.pz = a.z - claw.vz * STEP;
        claw.held = null;
        claw.slip = -1;
        if (slipped) {
            var p = proj(a.x, a.y - a.r - 6, a.z);
            pops.push({ text: 'Ay... düşdü!', x: p.x, y: p.y, t: 0, color: '#fbf6ef' });
        }
    }

    function caught(a) {
        var t = TYPES[a.type];
        var p = proj(CHUTE_X, CHUTE.top, 0);
        game.score += t.pts;
        game.caught.push(t.name);
        pops.push({ text: '+' + t.pts + ' ' + t.name, x: Math.max(p.x + 30, 96), y: p.y + 40, t: 0, color: '#E9A23B', big: true });
        flash = 1.6;
        // konfetti: çıxışdan yuxarı səpilir
        var colors = ['#E9A23B', '#f6cf6b', '#c62a2a', '#FBF6EF', '#7fae3a'];
        for (var i = 0; i < 34; i++) {
            confetti.push({
                x: p.x + (Math.random() - .5) * 40, y: p.y + 10,
                vx: (Math.random() - .3) * 260, vy: -220 - Math.random() * 260,
                rot: Math.random() * 6, vr: (Math.random() - .5) * 14,
                c: colors[i % colors.length], t: 0, life: 1.4 + Math.random() * .8
            });
        }
        hud();
    }

    function nextTurn() {
        if (game.attempts <= 0) { claw.state = 'wait'; claw.t = 0; return; }
        claw.state = 'ready';
        game.turnLeft = TURN_SECONDS;
        hud();
    }

    // (tx, tz) nöqtəsinə doğru bir addım; çatdısa true
    function moveTo(tx, tz, dt) {
        var dx = tx - claw.x, dz = tz - claw.z, d = Math.hypot(dx, dz), step = SPEED * dt;
        if (d <= step) { claw.x = tx; claw.z = tz; return true; }
        claw.x += dx / d * step; claw.z += dz / d * step;
        return false;
    }

    function updateClaw(dt) {
        var oldX = claw.x, oldY = claw.y, oldZ = claw.z;
        switch (claw.state) {
            case 'ready':
                var mx = clamp((input.keyR ? 1 : 0) - (input.keyL ? 1 : 0) + input.bx, -1, 1);
                var mz = clamp((input.keyB ? 1 : 0) - (input.keyF ? 1 : 0) + input.bz, -1, 1);
                if (mx || mz) {
                    input.target = null;
                    claw.x = clamp(claw.x + mx * SPEED * dt, X_MIN, X_MAX);
                    claw.z = clamp(claw.z + mz * SPEED * .7 * dt, Z_MIN, Z_MAX);
                } else if (input.target && moveTo(input.target.x, input.target.z, dt)) {
                    input.target = null;
                }
                game.turnLeft -= dt;
                // vaxt bitdi: oyun dayanır, "Yenidən başla" təklif olunur
                if (game.turnLeft <= 0) { game.turnLeft = 0; endGame(true); }
                break;
            case 'down':
                claw.y += 230 * dt;
                if (touching()) {
                    claw.state = 'close'; claw.t = 0;
                    claw.target = pickItem();
                }
                break;
            case 'close':
                claw.t += dt;
                var to = claw.target ? .55 : .1;
                claw.open = Math.max(to, 1 - claw.t / .35 * (1 - to));
                if (claw.t >= .4) {
                    var a = claw.target;
                    claw.target = null;
                    if (a && !a.gone && Math.hypot(a.x - claw.x, a.y - gripY(), a.z - claw.z) < a.r + 12) {
                        claw.chance = holdChance(a);
                        a.held = true;
                        claw.held = a;
                        // tutuş zəifdirsə, oyuncaq yolda bir anda sürüşüb düşəcək
                        claw.slip = Math.random() > claw.chance ? .15 + Math.random() * 1.7 : -1;
                    }
                    claw.state = 'up';
                }
                break;
            case 'up':
                claw.y -= 150 * dt;
                if (claw.y <= TOP_Y) { claw.y = TOP_Y; claw.state = 'home'; }
                break;
            case 'home':
                if (moveTo(CHUTE_X, CHUTE_Z, dt)) { claw.state = 'release'; claw.t = 0; }
                break;
            case 'release':
                claw.t += dt;
                claw.open = Math.min(1, claw.open + dt / .3);
                if (claw.t > .12) releaseHeld(false);
                if (claw.t > 1.1) nextTurn();
                break;
            case 'wait':
                claw.t += dt;
                if (claw.t > 1.4) endGame();
                break;
        }
        if (claw.state !== 'close' && claw.state !== 'release' && !claw.held) claw.open += (1 - claw.open) * Math.min(1, dt * 8);

        claw.vx = (claw.x - oldX) / dt;
        claw.vy = (claw.y - oldY) / dt;
        claw.vz = (claw.z - oldZ) / dt;

        // tutulan oyuncaq çəngəllə gedir
        var h = claw.held;
        if (h) {
            var k = Math.min(1, dt * 14);
            h.x += (claw.x - h.x) * k;
            h.y += (gripY() - h.y) * k;
            h.z += (claw.z - h.z) * k;
            h.px = h.x; h.py = h.y; h.pz = h.z;
            h.ang = Math.sin(clock * 9) * (claw.slip >= 0 ? .2 : .07);
            if (claw.slip >= 0) {
                claw.slip -= dt;
                if (claw.slip <= 0) releaseHeld(true);
            }
        }
    }

    // timeUp — cəhdin vaxtı bitdi (çəngəl salınmadı)
    function endGame(timeUp) {
        game.over = true;
        claw.state = 'idle';
        input.target = null;
        var best = readBest();
        var record = game.score > best;
        if (record) { saveBest(game.score); if (bestEl) bestEl.textContent = game.score; }

        var caught = game.caught.length ? 'Tutduqlarınız: ' + game.caught.join(', ') + '.' : '';
        if (timeUp) {
            el.title.textContent = 'Vaxt bitdi! ⏰';
            el.note.textContent = 'Çəngəli ' + TURN_SECONDS + ' saniyə ərzində salmadınız. ' + (caught || 'Yenidən başlayıb sınayın!');
            el.again.textContent = 'Yenidən başla';
        } else {
            el.title.textContent = game.score === 0 ? 'Bu dəfə alınmadı' : record ? 'Yeni rekord! 🎉' : 'Əla oyun!';
            el.note.textContent = caught || 'Halqaya baxın: çəngəl şirniyyatın düz üstündə olanda tutun — yenə sınayın!';
            el.again.textContent = 'Yenidən oyna';
        }
        el.score.textContent = game.score;
        el.result.hidden = false;
        hud();
    }

    /* ---------- HUD ---------- */
    var lastHud = '';
    function hud() {
        var t = claw.state === 'ready' ? String(Math.ceil(game.turnLeft)) : '–';
        var key = game.attempts + '|' + game.score + '|' + t + '|' + claw.state;
        if (key === lastHud) return;
        lastHud = key;
        el.attempts.textContent = game.attempts;
        el.points.textContent = game.score;
        el.time.textContent = t;
        el.time.parentNode.classList.toggle('is-low', claw.state === 'ready' && game.turnLeft <= 5);
        el.drop.disabled = game.started && claw.state !== 'ready';
    }

    /* =========================================================
       Rəsm
       ========================================================= */
    var dpr = Math.min(2, window.devicePixelRatio || 1);
    canvas.width = W * dpr;
    canvas.height = H * dpr;
    var back = document.createElement('canvas'), front = document.createElement('canvas');
    back.width = front.width = W * dpr;
    back.height = front.height = H * dpr;

    var GOLD = '#E9A23B', GOLD_D = '#C9831F', WINE = '#7E1A1A', CREAM = '#FBF6EF';

    function rr(c, x, y, w, h, r) {
        c.beginPath();
        c.moveTo(x + r, y);
        c.arcTo(x + w, y, x + w, y + h, r);
        c.arcTo(x + w, y + h, x, y + h, r);
        c.arcTo(x, y + h, x, y, r);
        c.arcTo(x, y, x + w, y, r);
        c.closePath();
    }

    // dünyadakı nöqtələrdən çoxbucaqlı
    function quad(c, pts) {
        c.beginPath();
        pts.forEach(function (p, i) { var q = proj(p[0], p[1], p[2]); if (i) c.lineTo(q.x, q.y); else c.moveTo(q.x, q.y); });
        c.closePath();
    }
    function line3(c, a, b) {
        var p = proj(a[0], a[1], a[2]), q = proj(b[0], b[1], b[2]);
        c.beginPath(); c.moveTo(p.x, p.y); c.lineTo(q.x, q.y); c.stroke();
    }

    // Arxa qat: gövdə, lövhə, qutunun içi perspektivdə (arxa divar, yan divarlar, döşəmə, quyu)
    function drawBack() {
        var c = back.getContext('2d');
        c.setTransform(dpr, 0, 0, dpr, 0, 0);
        c.clearRect(0, 0, W, H);

        var body = c.createLinearGradient(0, 0, W, 0);
        body.addColorStop(0, '#4f0f0f'); body.addColorStop(.18, '#8e2222'); body.addColorStop(.5, '#9a2626'); body.addColorStop(.82, '#8e2222'); body.addColorStop(1, '#4f0f0f');
        rr(c, 4, 4, W - 8, H - 8, 24); c.fillStyle = body; c.fill();
        c.lineWidth = 2; c.strokeStyle = 'rgba(246,207,107,.5)'; rr(c, 6, 6, W - 12, H - 12, 22); c.stroke();

        // yan haşiyələr: qızılı nöqtələr və romblar
        [7, W - 21].forEach(function (sx) {
            c.fillStyle = 'rgba(0,0,0,.22)'; rr(c, sx, G.t - 6, 14, FLOOR - G.t + 12, 7); c.fill();
            for (var y = G.t + 6, k = 0; y < FLOOR; y += 15, k++) {
                c.fillStyle = k % 3 === 0 ? GOLD : 'rgba(233,162,59,.45)';
                if (k % 3 === 0) {
                    c.beginPath(); c.moveTo(sx + 7, y - 4); c.lineTo(sx + 11, y); c.lineTo(sx + 7, y + 4); c.lineTo(sx + 3, y); c.closePath(); c.fill();
                } else dot(c, sx + 7, y, 1.8);
            }
        });

        // yuxarı lövhə: parlayan yazı
        var mg = c.createLinearGradient(0, 12, 0, 74);
        mg.addColorStop(0, '#4a1010'); mg.addColorStop(1, '#250707');
        rr(c, 18, 12, W - 36, 62, 14); c.fillStyle = mg; c.fill();
        c.lineWidth = 3; c.strokeStyle = GOLD; c.stroke();
        c.lineWidth = 1; c.strokeStyle = 'rgba(233,162,59,.35)'; rr(c, 24, 18, W - 48, 50, 10); c.stroke();
        c.textAlign = 'center'; c.textBaseline = 'middle';
        c.font = '400 26px Pacifico, cursive';
        c.save(); c.shadowColor = '#ffb347'; c.shadowBlur = 16; c.fillStyle = '#ffd98a';
        c.fillText('Hədiyyə Kranı', W / 2, 41); c.restore();
        c.fillStyle = '#ffe6b0'; c.fillText('Hədiyyə Kranı', W / 2, 41);
        [W / 2 - 112, W / 2 + 112].forEach(function (sx) { star(c, sx, 43, 7, '#f6cf6b'); });

        c.save();
        c.beginPath(); c.rect(G.l, G.t, G.r - G.l, FLOOR - G.t); c.clip();

        // tavan
        c.fillStyle = '#1e0909'; c.fillRect(G.l, G.t, G.r - G.l, FLOOR - G.t);

        // yan divarlar (tünd, şaquli zolaqlarla)
        [[G.l, '#3a1411'], [G.r, '#3a1411']].forEach(function (w) {
            quad(c, [[w[0], G.t, 0], [w[0], G.t, D], [w[0], FLOOR, D], [w[0], FLOOR, 0]]);
            c.fillStyle = w[1]; c.fill();
            c.strokeStyle = 'rgba(233,162,59,.1)'; c.lineWidth = 1;
            for (var z = 25; z < D; z += 25) line3(c, [w[0], G.t, z], [w[0], FLOOR, z]);
        });
        // yan divarlarda kölgə
        var shL = c.createLinearGradient(G.l, 0, proj(G.l, 0, D).x, 0);
        shL.addColorStop(0, 'rgba(0,0,0,.35)'); shL.addColorStop(1, 'rgba(0,0,0,0)');
        quad(c, [[G.l, G.t, 0], [G.l, G.t, D], [G.l, FLOOR, D], [G.l, FLOOR, 0]]); c.fillStyle = shL; c.fill();
        var shR = c.createLinearGradient(G.r, 0, proj(G.r, 0, D).x, 0);
        shR.addColorStop(0, 'rgba(0,0,0,.35)'); shR.addColorStop(1, 'rgba(0,0,0,0)');
        quad(c, [[G.r, G.t, 0], [G.r, G.t, D], [G.r, FLOOR, D], [G.r, FLOOR, 0]]); c.fillStyle = shR; c.fill();

        // arxa divar: xalça naxışı
        var bl = proj(G.l, G.t, D), br = proj(G.r, FLOOR, D);
        var bg = c.createLinearGradient(0, bl.y, 0, br.y);
        bg.addColorStop(0, '#5e241d'); bg.addColorStop(1, '#2c110f');
        c.fillStyle = bg; c.fillRect(bl.x, bl.y, br.x - bl.x, br.y - bl.y);
        c.save();
        c.beginPath(); c.rect(bl.x, bl.y, br.x - bl.x, br.y - bl.y); c.clip();
        for (var row = 0, gy = bl.y + 34; gy < br.y + 30; gy += 42, row++) {
            for (var gx = bl.x + (row % 2 ? 42 : 21); gx < br.x + 30; gx += 42) gol(c, gx, gy, 14);
        }
        c.fillStyle = 'rgba(126,26,26,.6)'; c.fillRect(bl.x, bl.y + 8, br.x - bl.x, 10);
        c.strokeStyle = 'rgba(233,162,59,.45)'; c.lineWidth = 1.2;
        c.beginPath();
        for (var zx = bl.x, up = 0; zx <= br.x + 6; zx += 6, up ^= 1) c.lineTo(zx, up ? bl.y + 10 : bl.y + 16);
        c.stroke();
        c.restore();
        c.strokeStyle = 'rgba(233,162,59,.35)'; c.lineWidth = 1.5; c.strokeRect(bl.x, bl.y, br.x - bl.x, br.y - bl.y);

        // döşəmə: perspektivdə xalça torları
        quad(c, [[G.l, FLOOR, 0], [G.r, FLOOR, 0], [G.r, FLOOR, D], [G.l, FLOOR, D]]);
        var fp = proj(0, FLOOR, D);
        var fg = c.createLinearGradient(0, fp.y, 0, FLOOR);
        fg.addColorStop(0, '#3d1512'); fg.addColorStop(1, '#5a1d17');
        c.fillStyle = fg; c.fill();
        c.strokeStyle = 'rgba(233,162,59,.14)'; c.lineWidth = 1;
        for (var lx = G.l + 32; lx < G.r; lx += 32) line3(c, [lx, FLOOR, 0], [lx, FLOOR, D]);
        for (var lz = 30; lz < D; lz += 30) line3(c, [G.l, FLOOR, lz], [G.r, FLOOR, lz]);

        // çıxış quyusu: döşəmədə tünd dəlik və içəri divarlar
        quad(c, [[CHUTE.l, FLOOR, 0], [CHUTE.r, FLOOR, 0], [CHUTE.r, FLOOR, CHUTE.d], [CHUTE.l, FLOOR, CHUTE.d]]);
        c.fillStyle = '#0d0404'; c.fill();
        quad(c, [[CHUTE.l, CHUTE.top, CHUTE.d], [CHUTE.r, CHUTE.top, CHUTE.d], [CHUTE.r, FLOOR, CHUTE.d], [CHUTE.l, FLOOR, CHUTE.d]]);
        c.fillStyle = 'rgba(0,0,0,.35)'; c.fill();

        // lampalardan düşən işıq
        [178, 262, 346].forEach(function (lx) {
            var lp = proj(lx, G.t + 4, D * .55);
            var a = proj(lx - 60, FLOOR, D * .2), b = proj(lx + 60, FLOOR, D * .2);
            var cone = c.createLinearGradient(0, lp.y, 0, a.y);
            cone.addColorStop(0, 'rgba(255,214,150,.22)'); cone.addColorStop(1, 'rgba(255,214,150,0)');
            c.fillStyle = cone;
            c.beginPath(); c.moveTo(lp.x - 5, lp.y); c.lineTo(lp.x + 5, lp.y); c.lineTo(b.x, b.y); c.lineTo(a.x, a.y); c.closePath(); c.fill();
            c.save(); c.shadowColor = '#ffcc55'; c.shadowBlur = 10; c.fillStyle = '#ffe08a';
            c.beginPath(); c.ellipse(lp.x, lp.y, 6, 2.4, 0, 0, Math.PI * 2); c.fill(); c.restore();
        });

        // yan relslər (çəngəlin körpüsü bunların üstündə irəli-geri gedir)
        c.strokeStyle = GOLD_D; c.lineWidth = 4; c.lineCap = 'round';
        line3(c, [G.l + 4, RAIL, 0], [G.l + 4, RAIL, D]);
        line3(c, [G.r - 4, RAIL, 0], [G.r - 4, RAIL, D]);
        c.restore();
    }

    // Ön qat: quyunun şüşə divarları, aşağı panel, şüşə parıltısı, çərçivə, tente
    function drawFront() {
        var c = front.getContext('2d');
        c.setTransform(dpr, 0, 0, dpr, 0, 0);
        c.clearRect(0, 0, W, H);
        c.textAlign = 'center'; c.textBaseline = 'middle';

        c.save();
        c.beginPath(); c.rect(G.l, G.t, G.r - G.l, FLOOR - G.t); c.clip();

        // quyunun şüşə divarları (yan və arxa) və ön paneli
        quad(c, [[CHUTE.r, CHUTE.top, 0], [CHUTE.r, CHUTE.top, CHUTE.d], [CHUTE.r, FLOOR, CHUTE.d], [CHUTE.r, FLOOR, 0]]);
        c.fillStyle = 'rgba(255,255,255,.1)'; c.fill();
        quad(c, [[CHUTE.l, CHUTE.top, CHUTE.d], [CHUTE.r, CHUTE.top, CHUTE.d], [CHUTE.r, FLOOR, CHUTE.d], [CHUTE.l, FLOOR, CHUTE.d]]);
        c.fillStyle = 'rgba(255,255,255,.06)'; c.fill();
        // quyunun ağzı: qızılı-bordo zolaqlı haşiyə
        c.lineWidth = 5; c.strokeStyle = GOLD; c.lineJoin = 'round';
        c.beginPath();
        [[CHUTE.l, 0], [CHUTE.r, 0], [CHUTE.r, CHUTE.d], [CHUTE.l, CHUTE.d]].forEach(function (p, i) {
            var q = proj(p[0], CHUTE.top, p[1]);
            if (i) c.lineTo(q.x, q.y); else c.moveTo(q.x, q.y);
        });
        c.stroke();
        c.setLineDash([6, 6]); c.strokeStyle = WINE; c.lineWidth = 5;
        c.stroke();
        c.setLineDash([]);
        c.strokeStyle = 'rgba(233,162,59,.6)'; c.lineWidth = 1.5;
        line3(c, [CHUTE.r, CHUTE.top, 0], [CHUTE.r, FLOOR, 0]);
        line3(c, [CHUTE.r, CHUTE.top, CHUTE.d], [CHUTE.r, FLOOR, CHUTE.d]);
        // ön şüşədə parlayan "ÇIXIŞ"
        c.save(); c.shadowColor = '#ffb347'; c.shadowBlur = 10;
        c.fillStyle = '#ffd98a';
        c.font = '900 13px Nunito, sans-serif';
        c.fillText('ÇIXIŞ', CHUTE_X, CHUTE.top + 30);
        c.beginPath();
        c.moveTo(CHUTE_X - 8, CHUTE.top + 42); c.lineTo(CHUTE_X + 8, CHUTE.top + 42); c.lineTo(CHUTE_X, CHUTE.top + 52); c.closePath(); c.fill();
        c.restore();

        // şüşə parıltısı
        c.fillStyle = 'rgba(255,255,255,.06)';
        c.beginPath(); c.moveTo(G.r - 150, G.t); c.lineTo(G.r - 90, G.t); c.lineTo(G.r - 260, FLOOR); c.lineTo(G.r - 320, FLOOR); c.closePath(); c.fill();
        c.fillStyle = 'rgba(255,255,255,.09)';
        c.beginPath(); c.moveTo(G.r - 70, G.t); c.lineTo(G.r - 56, G.t); c.lineTo(G.r - 226, FLOOR); c.lineTo(G.r - 240, FLOOR); c.closePath(); c.fill();
        c.restore();

        // aşağı panel
        var base = c.createLinearGradient(0, FLOOR, 0, H);
        base.addColorStop(0, '#6d1616'); base.addColorStop(1, '#4a0f0f');
        c.fillStyle = base;
        c.beginPath();
        c.moveTo(4, FLOOR); c.lineTo(W - 4, FLOOR); c.lineTo(W - 4, H - 28);
        c.arcTo(W - 4, H - 4, W - 28, H - 4, 24); c.lineTo(28, H - 4); c.arcTo(4, H - 4, 4, H - 28, 24);
        c.closePath(); c.fill();
        c.fillStyle = GOLD; c.fillRect(G.l, FLOOR, G.r - G.l, 3);
        // hədiyyə qapısı
        rr(c, CHUTE.l + 6, FLOOR + 10, CHUTE.r - CHUTE.l - 12, 26, 6);
        c.fillStyle = '#1E0B0B'; c.fill(); c.lineWidth = 2; c.strokeStyle = GOLD; c.stroke();
        c.fillStyle = GOLD; c.font = '900 10px Nunito, sans-serif';
        c.fillText('HƏDİYYƏ', CHUTE_X, FLOOR + 23);
        // bəzək: qızılı nöqtələr, jeton yeri və brend
        for (var x = CHUTE.r + 22; x < 236; x += 16) {
            c.fillStyle = 'rgba(233,162,59,.55)'; dot(c, x, FLOOR + 23, 2.2);
        }
        rr(c, 252, FLOOR + 11, 22, 24, 5); c.fillStyle = GOLD; c.fill();
        c.fillStyle = '#1E0B0B'; rr(c, 261.5, FLOOR + 15, 3, 16, 1.5); c.fill();
        c.fillStyle = GOLD; c.font = '700 16px "Playfair Display", Georgia, serif';
        c.fillText('50 Qəpik', 334, FLOOR + 24);

        // şüşənin çərçivəsi
        c.lineWidth = 5; c.strokeStyle = GOLD;
        rr(c, G.l - 3, G.t - 3, G.r - G.l + 6, FLOOR - G.t + 6, 8); c.stroke();

        // zolaqlı tente (dalğalı aşağı kənar)
        var n = 12, sw = (W - 16) / n, top = 76, bottom = 104;
        for (var i = 0; i < n; i++) {
            var x0 = 8 + i * sw;
            c.fillStyle = i % 2 ? CREAM : GOLD;
            c.beginPath();
            c.moveTo(x0, top); c.lineTo(x0 + sw, top); c.lineTo(x0 + sw, bottom);
            c.arc(x0 + sw / 2, bottom, sw / 2, 0, Math.PI);
            c.closePath(); c.fill();
        }
        c.fillStyle = 'rgba(0,0,0,.18)'; c.fillRect(8, top, W - 16, 4);
        c.fillStyle = GOLD_D; rr(c, 6, top - 4, W - 12, 6, 3); c.fill();
    }

    /* ---------- köməkçilər ---------- */
    function dot(c, x, y, r) { c.beginPath(); c.arc(x, y, r, 0, Math.PI * 2); c.fill(); }

    // dörd guşəli ulduz (parıltı)
    function star(c, x, y, r, color) {
        c.fillStyle = color;
        c.beginPath();
        c.moveTo(x, y - r); c.quadraticCurveTo(x, y, x + r, y); c.quadraticCurveTo(x, y, x, y + r);
        c.quadraticCurveTo(x, y, x - r, y); c.quadraticCurveTo(x, y, x, y - r);
        c.fill();
    }

    // xalça gölü: iç-içə romblar və ucunda qarmaqlar
    function gol(c, x, y, r) {
        c.strokeStyle = 'rgba(233,162,59,.11)'; c.lineWidth = 1.4;
        c.beginPath(); c.moveTo(x, y - r); c.lineTo(x + r, y); c.lineTo(x, y + r); c.lineTo(x - r, y); c.closePath(); c.stroke();
        c.fillStyle = 'rgba(126,26,26,.35)';
        c.beginPath(); c.moveTo(x, y - r * .55); c.lineTo(x + r * .55, y); c.lineTo(x, y + r * .55); c.lineTo(x - r * .55, y); c.closePath(); c.fill();
        c.fillStyle = 'rgba(233,162,59,.14)'; dot(c, x, y, r * .14);
        [[0, -1], [1, 0], [0, 1], [-1, 0]].forEach(function (d) {
            var tx = x + d[0] * r, ty = y + d[1] * r;
            c.beginPath(); c.moveTo(tx, ty); c.lineTo(tx + d[0] * 6 + d[1] * 4, ty + d[1] * 6 - d[0] * 4); c.stroke();
        });
    }

    /* ---------- şirniyyatlar: real görünüş ----------
       Hər növün 4 variantı bir dəfə ayrıca kətana (sprite) çəkilir — detallar təsadüfi olduğu üçün
       eyni növ şirniyyatlar bir-birinin surəti kimi görünmür. İşıq hər yerdə yuxarı-soldan düşür. */
    var SS = 3, spriteCache = {};

    function rnd(a, b) { return a + Math.random() * (b - a); }

    // işıq-kölgə: yuxarı-solda parıltı, aşağı-sağda kölgə (əvvəlcədən clip edilmiş formanın üstünə)
    function light(c, r, k) {
        k = k || 1;
        var g = c.createRadialGradient(-r * .45, -r * .5, r * .05, -r * .05, -r * .05, r * 1.35);
        g.addColorStop(0, 'rgba(255,255,255,' + (.42 * k) + ')');
        g.addColorStop(.38, 'rgba(255,255,255,0)');
        g.addColorStop(.75, 'rgba(60,25,0,' + (.12 * k) + ')');
        g.addColorStop(1, 'rgba(40,12,0,' + (.5 * k) + ')');
        c.fillStyle = g; c.fillRect(-r * 2, -r * 2, r * 4, r * 4);
    }
    // xəmir teksturası: xırda açıq və tünd ləkələr
    function crumb(c, r, n, dark, lite) {
        for (var i = 0; i < n; i++) {
            c.fillStyle = Math.random() < .5 ? dark : lite;
            c.globalAlpha = rnd(.15, .45);
            dot(c, rnd(-r, r), rnd(-r, r), rnd(.15, .6));
        }
        c.globalAlpha = 1;
    }
    // kənarlara doğru qızartı
    function bake(c, r, color) {
        var g = c.createRadialGradient(0, 0, r * .35, 0, 0, r * 1.15);
        g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, color);
        c.fillStyle = g; c.fillRect(-r * 2, -r * 2, r * 4, r * 4);
    }
    // şəkər tozu
    function powder(c, r, n, x0, y0, x1, y1) {
        for (var i = 0; i < n; i++) {
            c.fillStyle = 'rgba(255,255,255,' + rnd(.35, .95) + ')';
            dot(c, rnd(x0, x1) * r, rnd(y0, y1) * r, rnd(.2, .55));
        }
    }

    var DRAW = {
        // bükülü konfet: parlaq folqa, burulmuş uclar, qırışlar
        konfet: function (c, r, v) {
            var C = [['#9e0f1c', '#e8404b'], ['#b97a08', '#f8d56b'], ['#14583a', '#3fae74'], ['#4b1f73', '#a06ad0']][v];
            [-1, 1].forEach(function (s) {
                var g = c.createLinearGradient(0, -r * .55, 0, r * .55);
                g.addColorStop(0, C[1]); g.addColorStop(.45, C[0]); g.addColorStop(1, '#1e0303');
                c.fillStyle = g;
                c.beginPath();
                c.moveTo(s * r * .56, -r * .16); c.lineTo(s * r * 1.16, -r * .56);
                c.quadraticCurveTo(s * r * .98, 0, s * r * 1.16, r * .56); c.lineTo(s * r * .56, r * .16); c.closePath(); c.fill();
                for (var k = -3; k <= 3; k++) {
                    c.strokeStyle = 'rgba(255,255,255,.4)'; c.lineWidth = .6;
                    c.beginPath(); c.moveTo(s * r * .6, k * r * .035); c.lineTo(s * r * 1.1, k * r * .16 + rnd(-.5, .5)); c.stroke();
                    c.strokeStyle = 'rgba(0,0,0,.3)';
                    c.beginPath(); c.moveTo(s * r * .6, k * r * .035 + .8); c.lineTo(s * r * 1.08, k * r * .16 + 1.4); c.stroke();
                }
                var kn = c.createLinearGradient(0, -r * .2, 0, r * .2);
                kn.addColorStop(0, C[1]); kn.addColorStop(1, '#2a0505');
                c.fillStyle = kn; c.beginPath(); c.ellipse(s * r * .6, 0, r * .09, r * .2, 0, 0, Math.PI * 2); c.fill();
            });
            c.save();
            c.beginPath(); c.ellipse(0, 0, r * .66, r * .55, 0, 0, Math.PI * 2);
            c.fillStyle = C[0]; c.fill(); c.clip();
            var sh = c.createLinearGradient(0, -r * .55, 0, r * .55);
            sh.addColorStop(0, 'rgba(255,255,255,.15)'); sh.addColorStop(.22, 'rgba(255,255,255,.55)'); sh.addColorStop(.4, 'rgba(255,255,255,0)');
            sh.addColorStop(.85, 'rgba(0,0,0,.35)'); sh.addColorStop(1, 'rgba(0,0,0,.55)');
            c.fillStyle = sh; c.fillRect(-r, -r, r * 2, r * 2);
            // folqa qırışları
            for (var i = 0; i < 26; i++) {
                var x = rnd(-.6, .6) * r, y = rnd(-.5, .5) * r, l = rnd(1.5, 4), t = rnd(0, Math.PI);
                c.strokeStyle = Math.random() < .5 ? 'rgba(255,255,255,.45)' : 'rgba(0,0,0,.25)'; c.lineWidth = .5;
                c.beginPath(); c.moveTo(x, y); c.lineTo(x + Math.cos(t) * l, y + Math.sin(t) * l); c.stroke();
            }
            c.fillStyle = 'rgba(255,255,255,.75)';
            c.beginPath(); c.ellipse(-r * .18, -r * .3, r * .32, r * .06, -.12, 0, Math.PI * 2); c.fill();
            c.restore();
        },
        // lolipop: şüşə kimi parlaq spiral konfet, kağız çubuq
        lolipop: function (c, r, v) {
            var C = [['#d4142a', '#ffffff'], ['#f08a0c', '#ffe9a8'], ['#1f9a57', '#f4ffe8'], ['#e04c94', '#fff0f7']][v];
            var sg = c.createLinearGradient(-r * .08, 0, r * .08, 0);
            sg.addColorStop(0, '#d8cfbe'); sg.addColorStop(.4, '#ffffff'); sg.addColorStop(1, '#bfb5a2');
            c.fillStyle = sg; c.fillRect(-r * .075, r * .45, r * .15, r * 1.15);
            c.fillStyle = 'rgba(0,0,0,.25)'; c.fillRect(-r * .075, r * .45, r * .15, r * .25);
            c.save();
            c.beginPath(); c.arc(0, 0, r * .9, 0, Math.PI * 2); c.fillStyle = C[1]; c.fill(); c.clip();
            c.rotate(rnd(0, 6));
            // fırıldaq kimi əyri zolaqlar
            for (var i = 0; i < 6; i++) {
                c.save(); c.rotate(i / 6 * Math.PI * 2);
                c.fillStyle = C[0];
                c.beginPath(); c.moveTo(0, 0);
                c.bezierCurveTo(r * .5, -r * .1, r * .8, r * .3, r * .95, r * .7);
                c.lineTo(r * .95, r * 1.1);
                c.bezierCurveTo(r * .55, r * .5, r * .35, r * .15, 0, 0);
                c.fill();
                c.restore();
            }
            var rim = c.createRadialGradient(0, 0, r * .55, 0, 0, r * .92);
            rim.addColorStop(0, 'rgba(0,0,0,0)'); rim.addColorStop(1, 'rgba(0,0,0,.35)');
            c.fillStyle = rim; c.fillRect(-r, -r, r * 2, r * 2);
            c.restore();
            c.fillStyle = 'rgba(255,255,255,.8)';
            c.beginPath(); c.ellipse(-r * .38, -r * .42, r * .26, r * .11, -.7, 0, Math.PI * 2); c.fill();
            c.fillStyle = 'rgba(255,255,255,.5)'; dot(c, r * .45, r * .38, r * .07);
            c.strokeStyle = 'rgba(255,255,255,.45)'; c.lineWidth = .8;
            c.beginPath(); c.arc(0, 0, r * .86, Math.PI * 1.05, Math.PI * 1.6); c.stroke();
        },
        // pişmaniye: nazik şəkər liflərindən yumşaq çəhrayı topa
        pismaniye: function (c, r, v) {
            var base = ['#f6b3c6', '#f9c9d6', '#f3a8be', '#fbd7e1'][v];
            var g = c.createRadialGradient(-r * .2, -r * .25, r * .1, 0, 0, r);
            g.addColorStop(0, '#fff0f4'); g.addColorStop(.6, base); g.addColorStop(1, 'rgba(230,140,170,.0)');
            c.fillStyle = g;
            for (var p = 0; p < 9; p++) dot(c, rnd(-.45, .45) * r, rnd(-.35, .35) * r, rnd(.45, .7) * r);
            for (var i = 0; i < 260; i++) {
                var a = rnd(0, Math.PI * 2), d = Math.sqrt(Math.random()) * r * .92;
                var x = Math.cos(a) * d, y = Math.sin(a) * d * .85;
                var t = rnd(0, Math.PI * 2), l = rnd(2, 6);
                c.strokeStyle = Math.random() < .55 ? 'rgba(255,245,248,.75)' : 'rgba(214,110,145,.45)';
                c.lineWidth = rnd(.25, .55);
                c.beginPath(); c.moveTo(x, y); c.quadraticCurveTo(x + Math.cos(t + 1) * l * .6, y + Math.sin(t + 1) * l * .6, x + Math.cos(t) * l, y + Math.sin(t) * l); c.stroke();
            }
        },
        // rəngli lokum: yumşaq jele kub, üstü şəkər tozlu
        lokum: function (c, r, v) {
            var C = [['#e86d93', '#f7b3c8'], ['#5fae5a', '#a8dba0'], ['#efe6d6', '#ffffff'], ['#ee8f3a', '#f9c48c']][v];
            var k = r * .8;
            // sağ yan
            c.fillStyle = C[0];
            c.beginPath(); c.moveTo(k * .95, -k * .45); c.lineTo(k * 1.25, -k * .9); c.lineTo(k * 1.25, k * .55); c.lineTo(k * .95, k * .95); c.closePath(); c.fill();
            c.fillStyle = 'rgba(0,0,0,.28)'; c.fill();
            // üst
            c.fillStyle = C[1];
            c.beginPath(); c.moveTo(-k, -k * .45); c.lineTo(-k * .65, -k * .95); c.lineTo(k * 1.25, -k * .95); c.lineTo(k * .95, -k * .45); c.closePath(); c.fill();
            // ön üz: jele kimi ortası işıqlı
            c.save();
            rr(c, -k, -k * .5, k * 1.95, k * 1.48, k * .22);
            var g = c.createRadialGradient(-k * .2, k * .1, k * .1, 0, 0, k * 1.3);
            g.addColorStop(0, C[1]); g.addColorStop(.7, C[0]); g.addColorStop(1, C[0]);
            c.fillStyle = g; c.fill(); c.clip();
            light(c, k, .6);
            powder(c, k, 70, -1, -.5, .95, .98);
            c.restore();
            c.save();
            c.beginPath(); c.moveTo(-k, -k * .45); c.lineTo(-k * .65, -k * .95); c.lineTo(k * 1.25, -k * .95); c.lineTo(k * .95, -k * .45); c.closePath(); c.clip();
            powder(c, k, 160, -1, -1, 1.3, -.4);
            c.restore();
            powder(c, k, 30, .95, -.9, 1.25, .9);
        },
        // şəkərbura: yarımdairəvi, kənarı dişli, üstü maşa ilə çimdiklənmiş sünbül naxışı
        sekerbura: function (c, r, v) {
            c.save();
            c.beginPath(); c.ellipse(0, 0, r, r * .72, 0, 0, Math.PI * 2);
            var g = c.createRadialGradient(-r * .2, -r * .2, r * .1, 0, 0, r);
            g.addColorStop(0, '#f8e3b0'); g.addColorStop(.75, '#ecc77e'); g.addColorStop(1, '#c98a3c');
            c.fillStyle = g; c.fill(); c.clip();
            crumb(c, r, 220, '#b07a32', '#fff6dc');
            bake(c, r, 'rgba(150,80,20,.55)');
            // sünbül naxışı: 3 sıra qoşa çimdik
            for (var row = -1; row <= 1; row++) {
                for (var i = -4; i <= 4; i++) {
                    var x = i * r * .19, y = row * r * .26;
                    if (x * x / (r * r * .72) + y * y / (r * r * .3) > 1) continue;
                    [-1, 1].forEach(function (s) {
                        c.strokeStyle = 'rgba(120,62,12,.75)'; c.lineWidth = 1;
                        c.beginPath(); c.moveTo(x, y); c.lineTo(x + r * .1, y + s * r * .1); c.stroke();
                        c.strokeStyle = 'rgba(255,248,225,.8)'; c.lineWidth = .7;
                        c.beginPath(); c.moveTo(x + .6, y - .6); c.lineTo(x + r * .1 + .6, y + s * r * .1 - .6); c.stroke();
                    });
                }
            }
            light(c, r, .8);
            c.restore();
            // dişli kənar
            for (var t = 0; t < 30; t++) {
                var a = t / 30 * Math.PI * 2, x0 = Math.cos(a) * r * .97, y0 = Math.sin(a) * r * .7;
                c.strokeStyle = 'rgba(140,76,20,.55)'; c.lineWidth = .9;
                c.beginPath(); c.moveTo(x0 * .9, y0 * .9); c.lineTo(x0, y0); c.stroke();
            }
            c.strokeStyle = 'rgba(140,76,20,.6)'; c.lineWidth = 1;
            c.beginPath(); c.ellipse(0, 0, r, r * .72, 0, 0, Math.PI * 2); c.stroke();
        },
        // paxlava: laylı romb, şərbətli parlaq üst, ortada doğranmış püstə
        paxlava: function (c, r, v) {
            var top = function () { c.beginPath(); c.moveTo(0, -r * .95); c.lineTo(r, -r * .1); c.lineTo(0, r * .75); c.lineTo(-r, -r * .1); c.closePath(); };
            // yan (qalınlıq): nazik xəmir layları
            c.save();
            c.beginPath(); c.moveTo(-r, -r * .1); c.lineTo(0, r * .75); c.lineTo(r, -r * .1); c.lineTo(r, r * .15); c.lineTo(0, r * 1.0); c.lineTo(-r, r * .15); c.closePath();
            c.fillStyle = '#b8742a'; c.fill(); c.clip();
            for (var y = -r * .1; y < r * 1.05; y += 1.1) {
                c.strokeStyle = Math.round(y) % 2 ? 'rgba(255,225,160,.55)' : 'rgba(110,55,10,.5)'; c.lineWidth = .5;
                c.beginPath(); c.moveTo(-r, y); c.lineTo(0, y + r * .85); c.lineTo(r, y); c.stroke();
            }
            c.fillStyle = 'rgba(0,0,0,.18)'; c.fillRect(0, -r, r, r * 2.2);
            c.restore();
            c.save();
            top();
            var g = c.createLinearGradient(-r, -r, r, r);
            g.addColorStop(0, '#f2bf5e'); g.addColorStop(.6, '#d08c2c'); g.addColorStop(1, '#9c5a16');
            c.fillStyle = g; c.fill(); c.clip();
            crumb(c, r, 160, '#8a4a10', '#ffe1a0');
            for (var k = 0; k < 7; k++) {
                c.strokeStyle = 'rgba(120,60,10,.3)'; c.lineWidth = .6;
                c.beginPath(); c.moveTo(-r + k * r * .3, -r); c.lineTo(-r + k * r * .3 + r * .4, r); c.stroke();
            }
            // şərbət parıltısı
            c.fillStyle = 'rgba(255,240,200,.45)';
            c.beginPath(); c.ellipse(-r * .3, -r * .35, r * .35, r * .1, -.65, 0, Math.PI * 2); c.fill();
            light(c, r, .7);
            c.restore();
            // püstə
            for (var i = 0; i < 46; i++) {
                var a = rnd(0, Math.PI * 2), d = Math.sqrt(Math.random()) * r * .26;
                c.fillStyle = ['#4f7f1f', '#77a83a', '#9dc95a', '#3d6316'][i % 4];
                c.beginPath(); c.ellipse(Math.cos(a) * d, -r * .1 + Math.sin(a) * d * .8, rnd(.6, 1.4), rnd(.4, 1), rnd(0, 3), 0, Math.PI * 2); c.fill();
            }
            c.strokeStyle = 'rgba(110,55,10,.6)'; c.lineWidth = .8; top(); c.stroke();
        },
        // badambura: uzunsov bükülmüş xəmir, ucunda laylar görünür, şəkər tozu
        badambura: function (c, r, v) {
            var shape = function () { rr(c, -r * 1.12, -r * .55, r * 2.24, r * 1.1, r * .55); };
            c.save();
            shape();
            var g = c.createLinearGradient(0, -r * .55, 0, r * .55);
            g.addColorStop(0, '#f7d690'); g.addColorStop(.5, '#e2a856'); g.addColorStop(1, '#a8661f');
            c.fillStyle = g; c.fill(); c.clip();
            crumb(c, r * 1.1, 200, '#93541a', '#fff0c8');
            for (var k = -5; k <= 5; k++) {
                var x = k * r * .21;
                c.strokeStyle = 'rgba(120,60,10,.5)'; c.lineWidth = 1;
                c.beginPath(); c.moveTo(x - r * .12, -r * .55); c.quadraticCurveTo(x, 0, x + r * .12, r * .55); c.stroke();
                c.strokeStyle = 'rgba(255,240,200,.5)'; c.lineWidth = .6;
                c.beginPath(); c.moveTo(x - r * .1, -r * .55); c.quadraticCurveTo(x + 1, 0, x + r * .14, r * .55); c.stroke();
            }
            powder(c, r, 90, -1.05, -.55, 1.05, -.05);
            light(c, r * 1.1, .7);
            c.restore();
            // sol ucda laylar (spiral)
            c.strokeStyle = 'rgba(140,76,20,.7)'; c.lineWidth = .7;
            for (var i = 1; i <= 3; i++) { c.beginPath(); c.ellipse(-r * .92, 0, r * .07 * i, r * .14 * i, 0, Math.PI * .5, Math.PI * 1.5); c.stroke(); }
        },
        // nabat: çubuğa yığılmış kəhrəba rəngli kristal şəkər
        nabat: function (c, r, v) {
            var wg = c.createLinearGradient(-r * .08, 0, r * .08, 0);
            wg.addColorStop(0, '#6b3a14'); wg.addColorStop(.5, '#b47a3c'); wg.addColorStop(1, '#5a2e0e');
            c.fillStyle = wg; c.fillRect(-r * .07, -r * 1.35, r * .14, r * 2.6);
            var n = 16;
            for (var i = 0; i < n; i++) {
                var x = rnd(-.6, .6) * r, y = rnd(-.85, .85) * r, q = rnd(.22, .38) * r, t = rnd(0, Math.PI);
                c.save(); c.translate(x, y); c.rotate(t);
                var g = c.createLinearGradient(-q, -q, q, q);
                g.addColorStop(0, '#fff3c0'); g.addColorStop(.35, '#f7c84a'); g.addColorStop(1, '#c97a08');
                c.globalAlpha = .92;
                c.fillStyle = g;
                c.beginPath(); c.moveTo(0, -q); c.lineTo(q * .85, -q * .25); c.lineTo(q * .55, q * .8); c.lineTo(-q * .55, q * .75); c.lineTo(-q * .9, -q * .2); c.closePath(); c.fill();
                c.globalAlpha = 1;
                c.strokeStyle = 'rgba(150,80,0,.5)'; c.lineWidth = .5; c.stroke();
                c.fillStyle = 'rgba(255,255,255,.65)';
                c.beginPath(); c.moveTo(0, -q); c.lineTo(q * .85, -q * .25); c.lineTo(q * .1, -q * .05); c.closePath(); c.fill();
                c.restore();
            }
            star(c, r * .45, -r * .6, r * .18, '#ffffff');
            star(c, -r * .35, r * .35, r * .12, '#ffffff');
        },
        // mütəkkə: yarımay çörək, yumurta ilə parlaq üst, çatlardan qoz görünür
        mutekke: function (c, r, v) {
            var shape = function () {
                c.beginPath();
                c.arc(0, -r * .15, r * 1.05, Math.PI * -.06, Math.PI * 1.06, false);
                c.arc(0, -r * .75, r * .92, Math.PI * .96, Math.PI * .04, true);
                c.closePath();
            };
            c.save();
            shape();
            var g = c.createLinearGradient(0, -r * .2, 0, r * .95);
            g.addColorStop(0, '#f0bb62'); g.addColorStop(.6, '#c27a2a'); g.addColorStop(1, '#7e4513');
            c.fillStyle = g; c.fill(); c.clip();
            crumb(c, r, 180, '#6e3a0e', '#ffe2a6');
            // çatlar və qoz
            for (var i = 0; i < 5; i++) {
                var a = Math.PI * (.15 + i * .17), x = Math.cos(a) * r * .72, y = -r * .15 + Math.sin(a) * r * .72;
                c.fillStyle = '#5a3212';
                c.beginPath(); c.ellipse(x, y, r * .12, r * .05, a, 0, Math.PI * 2); c.fill();
                c.fillStyle = 'rgba(210,170,110,.8)'; dot(c, x + .5, y - .3, r * .03);
            }
            c.fillStyle = 'rgba(255,240,200,.45)';
            c.beginPath(); c.ellipse(-r * .4, r * .25, r * .3, r * .07, .5, 0, Math.PI * 2); c.fill();
            light(c, r, .6);
            c.restore();
            c.strokeStyle = 'rgba(100,50,10,.6)'; c.lineWidth = .9; shape(); c.stroke();
        },
        // kətə: yastı, qalın kənarlı, üstü çəngəllə naxışlanmış qızartılı
        kete: function (c, r, v) {
            c.fillStyle = '#8a4e16';
            c.beginPath(); c.ellipse(0, r * .14, r, r * .74, 0, 0, Math.PI * 2); c.fill();
            c.save();
            c.beginPath(); c.ellipse(0, 0, r, r * .74, 0, 0, Math.PI * 2);
            var g = c.createRadialGradient(-r * .2, -r * .2, r * .1, 0, 0, r);
            g.addColorStop(0, '#f3c46e'); g.addColorStop(.7, '#d39340'); g.addColorStop(1, '#9c5a1a');
            c.fillStyle = g; c.fill(); c.clip();
            crumb(c, r, 200, '#7e4513', '#ffe6b0');
            // çəngəl izləri: iki halqa nöqtə və ortada ulduz naxışı
            [.78, .52].forEach(function (k) {
                for (var i = 0; i < 22; i++) {
                    var t = i / 22 * Math.PI * 2;
                    c.fillStyle = 'rgba(90,45,8,.7)'; dot(c, Math.cos(t) * r * k, Math.sin(t) * r * k * .74, .55);
                    c.fillStyle = 'rgba(255,235,190,.6)'; dot(c, Math.cos(t) * r * k - .4, Math.sin(t) * r * k * .74 - .4, .35);
                }
            });
            for (var j = 0; j < 8; j++) {
                var u = j / 8 * Math.PI * 2;
                c.strokeStyle = 'rgba(90,45,8,.55)'; c.lineWidth = .8;
                c.beginPath(); c.moveTo(0, 0); c.lineTo(Math.cos(u) * r * .32, Math.sin(u) * r * .24); c.stroke();
            }
            bake(c, r, 'rgba(110,50,10,.5)');
            light(c, r, .7);
            c.restore();
        },
        // qoğal: laylı, yumru, üstü parlaq, çörəkotu dənələri
        qogal: function (c, r, v) {
            c.save();
            c.beginPath();
            for (var i = 0; i <= 40; i++) {
                var a = i / 40 * Math.PI * 2, rr2 = r * (1 + Math.sin(a * 7 + v) * .025 + rnd(-.015, .015));
                if (i) c.lineTo(Math.cos(a) * rr2, Math.sin(a) * rr2 * .9); else c.moveTo(Math.cos(a) * rr2, Math.sin(a) * rr2 * .9);
            }
            c.closePath();
            var g = c.createRadialGradient(-r * .25, -r * .3, r * .1, 0, 0, r);
            g.addColorStop(0, '#f6cd76'); g.addColorStop(.6, '#d08a35'); g.addColorStop(1, '#8a4a14');
            c.fillStyle = g; c.fill(); c.clip();
            crumb(c, r, 200, '#6e3a0e', '#ffe6b0');
            // laylar
            for (var t = 0; t < Math.PI * 5; t += .08) {
                var rad = r * (.1 + t / (Math.PI * 5) * .85);
                var x = Math.cos(t) * rad, y = Math.sin(t) * rad * .9;
                c.fillStyle = 'rgba(110,55,10,.35)'; dot(c, x, y, .8);
                c.fillStyle = 'rgba(255,235,180,.4)'; dot(c, x - .7, y - .7, .5);
            }
            // çörəkotu
            for (var s = 0; s < 22; s++) {
                c.fillStyle = '#1a120c';
                c.beginPath(); c.ellipse(rnd(-.7, .7) * r, rnd(-.65, .65) * r, rnd(.6, .9), rnd(.3, .45), rnd(0, 3), 0, Math.PI * 2); c.fill();
            }
            c.fillStyle = 'rgba(255,245,210,.5)';
            c.beginPath(); c.ellipse(-r * .38, -r * .45, r * .3, r * .1, -.6, 0, Math.PI * 2); c.fill();
            light(c, r, .8);
            c.restore();
        },
        // Şəki halvası: düyü unundan nazik tor (rişte), qırmızımtıl şərbət, altında qoz-fındıq qatı
        halva: function (c, r, v) {
            var top = function () { c.beginPath(); c.moveTo(0, -r * .95); c.lineTo(r * 1.05, -r * .1); c.lineTo(0, r * .72); c.lineTo(-r * 1.05, -r * .1); c.closePath(); };
            // yan: fındıq qatı
            c.save();
            c.beginPath(); c.moveTo(-r * 1.05, -r * .1); c.lineTo(0, r * .72); c.lineTo(r * 1.05, -r * .1); c.lineTo(r * 1.05, r * .2); c.lineTo(0, r * 1.02); c.lineTo(-r * 1.05, r * .2); c.closePath();
            c.fillStyle = '#c99a62'; c.fill(); c.clip();
            for (var i = 0; i < 40; i++) { c.fillStyle = ['#8a5a2a', '#e8cfa0', '#6b3a14'][i % 3]; dot(c, rnd(-1, 1) * r, rnd(-.1, 1) * r, rnd(.6, 1.3)); }
            c.fillStyle = 'rgba(80,20,10,.5)'; c.fillRect(-r * 1.1, -r * .2, r * 2.2, r * .25);
            c.fillStyle = 'rgba(0,0,0,.2)'; c.fillRect(0, -r, r * 1.2, r * 2.2);
            c.restore();
            c.save();
            top();
            var g = c.createLinearGradient(-r, -r, r, r);
            g.addColorStop(0, '#d0452e'); g.addColorStop(.6, '#a52618'); g.addColorStop(1, '#6e130c');
            c.fillStyle = g; c.fill(); c.clip();
            // rişte toru: sıx çarpaz nazik xətlər
            for (var k = -14; k <= 14; k++) {
                c.lineWidth = .55;
                c.strokeStyle = 'rgba(255,200,150,.55)';
                c.beginPath(); c.moveTo(k * r * .1 - r, -r); c.lineTo(k * r * .1 + r, r); c.stroke();
                c.strokeStyle = 'rgba(255,215,170,.45)';
                c.beginPath(); c.moveTo(k * r * .1 + r, -r); c.lineTo(k * r * .1 - r, r); c.stroke();
            }
            for (var d = 0; d < 60; d++) { c.fillStyle = 'rgba(90,10,5,.35)'; dot(c, rnd(-1, 1) * r, rnd(-1, 1) * r, rnd(.4, 1)); }
            c.fillStyle = 'rgba(255,220,190,.4)';
            c.beginPath(); c.ellipse(-r * .3, -r * .38, r * .38, r * .09, -.65, 0, Math.PI * 2); c.fill();
            light(c, r, .7);
            c.restore();
            c.strokeStyle = 'rgba(70,10,5,.6)'; c.lineWidth = .8; top(); c.stroke();
        },
        // qızıl "50 Qəpik" sikkəsi: metal parıltı, dişli kənar, qabarıq yazı
        sikke: function (c, r, v) {
            c.fillStyle = '#7a4d06'; dot(c, r * .05, r * .08, r * 1.02);
            var g = c.createRadialGradient(-r * .35, -r * .4, r * .05, 0, 0, r * 1.05);
            g.addColorStop(0, '#fff6c8'); g.addColorStop(.35, '#f5cd4c'); g.addColorStop(.75, '#d39a17'); g.addColorStop(1, '#8f5f07');
            c.fillStyle = g; dot(c, 0, 0, r);
            // dişli kənar
            for (var i = 0; i < 60; i++) {
                var t = i / 60 * Math.PI * 2;
                c.strokeStyle = i % 2 ? 'rgba(120,75,0,.55)' : 'rgba(255,240,180,.5)'; c.lineWidth = .6;
                c.beginPath(); c.moveTo(Math.cos(t) * r * .9, Math.sin(t) * r * .9); c.lineTo(Math.cos(t) * r, Math.sin(t) * r); c.stroke();
            }
            // qabarıq iç halqa
            c.lineWidth = 1.2;
            c.strokeStyle = 'rgba(255,250,215,.85)'; c.beginPath(); c.arc(0, 0, r * .8, Math.PI * .75, Math.PI * 1.75); c.stroke();
            c.strokeStyle = 'rgba(110,70,0,.75)'; c.beginPath(); c.arc(0, 0, r * .8, Math.PI * 1.75, Math.PI * 2.75); c.stroke();
            c.textAlign = 'center'; c.textBaseline = 'middle';
            var emboss = function (txt, y, size) {
                c.font = '900 ' + size + 'px Nunito, sans-serif';
                c.fillStyle = 'rgba(255,250,215,.9)'; c.fillText(txt, -.5, y - .5);
                c.fillStyle = 'rgba(100,60,0,.8)'; c.fillText(txt, .6, y + .6);
                c.fillStyle = '#e2ad2c'; c.fillText(txt, 0, y);
            };
            emboss('50', -r * .1, Math.round(r * .9));
            emboss('QƏPİK', r * .48, Math.round(r * .3));
            c.save(); c.beginPath(); c.arc(0, 0, r, 0, Math.PI * 2); c.clip();
            var sh = c.createLinearGradient(-r, -r, r, r);
            sh.addColorStop(.3, 'rgba(255,255,255,0)'); sh.addColorStop(.42, 'rgba(255,255,255,.45)'); sh.addColorStop(.5, 'rgba(255,255,255,0)');
            c.fillStyle = sh; c.fillRect(-r, -r, r * 2, r * 2);
            c.restore();
        }
    };

    function sprite(a) {
        var key = a.type + ':' + (a.v || 0);
        var sp = spriteCache[key];
        if (!sp) {
            var half = Math.ceil(a.r * 1.75);
            var cv = document.createElement('canvas');
            cv.width = cv.height = half * 2 * SS;
            var c = cv.getContext('2d');
            c.scale(SS, SS); c.translate(half, half);
            DRAW[a.type](c, a.r, a.v || 0);
            sp = spriteCache[key] = { cv: cv, half: half };
        }
        return sp;
    }

    // oyuncaq dünyadakı yerinə görə: uzaqdakı kiçik və yuxarıda görünür
    function drawItem(c, a) {
        var p = proj(a.x, a.y, a.z), s = p.s;
        // döşəmədə kölgə
        var f = proj(a.x, FLOOR, a.z);
        if (!inChute(a)) {
            var near = clamp(1 - (FLOOR - a.r - a.y) / 120, .2, 1);
            c.fillStyle = 'rgba(0,0,0,' + (.32 * near) + ')';
            c.beginPath(); c.ellipse(f.x + 2 * s, f.y - 1, a.r * .85 * s, a.r * .26 * s, 0, 0, Math.PI * 2); c.fill();
        }
        var sp = sprite(a);
        c.save();
        c.translate(p.x, p.y);
        c.scale(s, s);
        c.rotate(a.ang);
        c.drawImage(sp.cv, -sp.half, -sp.half, sp.half * 2, sp.half * 2);
        c.restore();
        // qızıl sikkə parıldayır
        if (a.type === 'sikke') {
            var k = (Math.sin(clock * 4 + a.x) + 1) / 2;
            c.globalAlpha = .4 + k * .6;
            star(c, p.x + a.r * .55 * s, p.y - a.r * .55 * s, a.r * (.3 + k * .35) * s, '#ffffff');
            c.globalAlpha = 1;
        }
    }

    /* ---------- çəngəl rəsmi ---------- */
    // bir diş: göbəkdən (0, 0) başlayır, s = -1 sol, 1 sağ
    function arm(c, s, open) {
        var a = .25 + .55 * open, b = a - .9;
        var x0 = s * 4, x1 = x0 + s * Math.sin(a) * 22, y1 = Math.cos(a) * 22;
        var x2 = x1 + s * Math.sin(b) * 20, y2 = y1 + Math.cos(b) * 20;
        c.beginPath(); c.moveTo(x0, 0); c.lineTo(x1, y1); c.lineTo(x2, y2); c.stroke();
        c.beginPath(); c.moveTo(x2, y2); c.lineTo(x2 - s * 5, y2 - 2); c.stroke();
    }

    // körpü (relslərin üstündə irəli-geri gedən en tir), araba və kabel
    function drawGantry(c) {
        var z = claw.z;
        var l = proj(G.l + 4, RAIL, z), r = proj(G.r - 4, RAIL, z);
        c.strokeStyle = 'rgba(0,0,0,.35)'; c.lineWidth = 7 * l.s;
        c.beginPath(); c.moveTo(l.x, l.y + 3); c.lineTo(r.x, r.y + 3); c.stroke();
        var gg = c.createLinearGradient(0, l.y - 4, 0, l.y + 4);
        gg.addColorStop(0, '#f6cf6b'); gg.addColorStop(1, GOLD_D);
        c.strokeStyle = gg; c.lineWidth = 6 * l.s; c.lineCap = 'round';
        c.beginPath(); c.moveTo(l.x, l.y); c.lineTo(r.x, r.y); c.stroke();
        c.fillStyle = GOLD; dot(c, l.x, l.y, 5 * l.s); dot(c, r.x, r.y, 5 * r.s);

        var t = proj(claw.x, RAIL, z), h = proj(claw.x, claw.y - 8 * CS, z);
        c.strokeStyle = '#d8c6a8'; c.lineWidth = 1.6 * t.s;
        c.beginPath(); c.moveTo(t.x, t.y + 4); c.lineTo(h.x, h.y); c.stroke();
        c.fillStyle = WINE; rr(c, t.x - 14 * t.s, t.y - 6 * t.s, 28 * t.s, 12 * t.s, 4 * t.s); c.fill();
        c.strokeStyle = GOLD; c.lineWidth = 1.5; c.stroke();
    }

    function drawClaw(c, behind) {
        var p = proj(claw.x, claw.y, claw.z), k = CS * p.s;
        c.save();
        c.translate(p.x, p.y); c.scale(k, k);
        if (behind) {
            // arxa (orta) diş
            c.strokeStyle = '#8f877a'; c.lineWidth = 4; c.lineCap = 'round';
            c.beginPath(); c.moveTo(0, 12); c.lineTo(0, 30 + 8 * (1 - claw.open)); c.stroke();
            c.restore();
            return;
        }
        // yan dişlər
        c.save(); c.translate(0, 12);
        c.lineCap = 'round'; c.lineJoin = 'round';
        c.strokeStyle = '#5f584d'; c.lineWidth = 6.5;
        arm(c, -1, claw.open); arm(c, 1, claw.open);
        c.strokeStyle = '#e6dfd2'; c.lineWidth = 3;
        arm(c, -1, claw.open); arm(c, 1, claw.open);
        c.restore();
        // baş: qızılı gövdə, bordo qurşaq, parıltı
        var hg = c.createLinearGradient(-15, 0, 15, 0);
        hg.addColorStop(0, GOLD_D); hg.addColorStop(.45, '#fbe08f'); hg.addColorStop(1, '#a8661a');
        c.fillStyle = hg; rr(c, -15, -10, 30, 22, 6); c.fill();
        c.fillStyle = WINE; c.fillRect(-15, -1, 30, 4);
        c.fillStyle = 'rgba(255,255,255,.55)'; rr(c, -11, -8, 7, 4, 2); c.fill();
        c.fillStyle = '#3a2a1a'; dot(c, 0, 12, 5);
        c.fillStyle = GOLD; dot(c, 0, 12, 2);
        c.restore();
    }

    // çəngəlin altındakı ən hündür səth (oyuncağın üstü və ya döşəmə) — nişan oradadır
    function aimSurface() {
        var y = inChute(claw) ? FLOOR + 30 : FLOOR;
        items.forEach(function (a) {
            if (a.held) return;
            var d = Math.hypot(a.x - claw.x, a.z - claw.z);
            if (d < a.r) { var top = a.y - Math.sqrt(a.r * a.r - d * d); if (top < y) y = top; }
        });
        return y;
    }

    function drawAim(c) {
        var y = aimSurface();
        var top = proj(claw.x, hubY() + 40 * CS, claw.z), m = proj(claw.x, y, claw.z);
        c.strokeStyle = 'rgba(255,224,138,.4)'; c.setLineDash([4, 6]); c.lineWidth = 1.5;
        c.beginPath(); c.moveTo(top.x, top.y); c.lineTo(m.x, m.y); c.stroke();
        c.setLineDash([]);
        // nişan halqası: üfüqi dairə perspektivdə yastı ellips kimi görünür
        var pulse = 1 + Math.sin(clock * 6) * .08;
        c.lineWidth = 2; c.strokeStyle = 'rgba(255,224,138,.9)';
        c.beginPath(); c.ellipse(m.x, m.y, 13 * m.s * pulse, 4.5 * m.s * pulse, 0, 0, Math.PI * 2); c.stroke();
        c.fillStyle = 'rgba(255,224,138,.25)'; c.fill();
        // döşəmədə çəngəlin kölgəsi (oyuncağın altında olsa da yeri görünsün)
        var f = proj(claw.x, FLOOR, claw.z);
        if (y < FLOOR - 1) {
            c.strokeStyle = 'rgba(255,224,138,.25)'; c.lineWidth = 1;
            c.beginPath(); c.ellipse(f.x, f.y, 10 * f.s, 3.5 * f.s, 0, 0, Math.PI * 2); c.stroke();
        }
    }

    /* ---------- kadr ---------- */
    function render() {
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, W, H);
        ctx.drawImage(back, 0, 0, W, H);

        // lövhənin lampaları: növbə ilə yanır, oyuncaq çıxanda hamısı sürətlə qaçır
        var speed = flash > 0 ? 14 : 3, phase = Math.floor(clock * speed);
        for (var i = 0, x = 34; x < W - 30; x += 22, i++) {
            [20, 66].forEach(function (y, row) {
                var on = (i + row + phase) % 2 === 0;
                ctx.fillStyle = on ? '#ffe08a' : '#6b3a14';
                if (on) { ctx.shadowColor = '#ffcc55'; ctx.shadowBlur = 8; }
                dot(ctx, x, y, 3.4);
                ctx.shadowBlur = 0;
            });
        }

        ctx.save();
        ctx.beginPath(); ctx.rect(G.l, G.t, G.r - G.l, FLOOR - G.t + 60); ctx.clip();

        // arxadan önə: çəngəldən arxadakı oyuncaqlar, çəngəl, sonra öndəkilər
        var list = items.filter(function (a) { return !a.held; }).sort(function (a, b) { return b.z - a.z || a.y - b.y; });
        var i2 = 0;
        while (i2 < list.length && list[i2].z > claw.z) drawItem(ctx, list[i2++]);
        drawGantry(ctx);
        drawClaw(ctx, true);
        if (claw.held) drawItem(ctx, claw.held);
        drawClaw(ctx, false);
        while (i2 < list.length) drawItem(ctx, list[i2++]);
        if (claw.state === 'ready') drawAim(ctx);
        ctx.restore();

        ctx.drawImage(front, 0, 0, W, H);

        // şüşədə sayrışan parıltılar
        sparkles.forEach(function (s) {
            var k = Math.sin(s.t / s.life * Math.PI);
            ctx.globalAlpha = k * .9;
            star(ctx, s.x, s.y, s.r * (.6 + k * .4), '#fff3d0');
        });
        ctx.globalAlpha = 1;

        // konfetti
        confetti.forEach(function (p) {
            ctx.save();
            ctx.globalAlpha = Math.max(0, 1 - p.t / p.life);
            ctx.translate(p.x, p.y); ctx.rotate(p.rot);
            ctx.fillStyle = p.c; ctx.fillRect(-3.5, -2, 7, 4);
            ctx.restore();
        });

        // uçan yazılar
        pops.forEach(function (p) {
            var k = p.t / 1.4;
            ctx.globalAlpha = Math.max(0, 1 - k);
            ctx.font = '900 ' + (p.big ? 19 : 15) + 'px Nunito, sans-serif';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.lineWidth = 4; ctx.strokeStyle = 'rgba(30,11,11,.85)';
            ctx.strokeText(p.text, p.x, p.y - k * 40);
            ctx.fillStyle = p.color; ctx.fillText(p.text, p.x, p.y - k * 40);
            ctx.globalAlpha = 1;
        });
    }

    var last = 0, acc = 0;
    function frame(now) {
        var dt = Math.min(.05, (now - (last || now)) / 1000);
        last = now;
        clock += dt;
        flash = Math.max(0, flash - dt);
        acc += dt;
        while (acc >= STEP) {
            updateClaw(STEP);
            physics(STEP, false);
            acc -= STEP;
        }
        effects(dt);
        hud();
        render();
        requestAnimationFrame(frame);
    }

    function effects(dt) {
        pops.forEach(function (p) { p.t += dt; });
        pops = pops.filter(function (p) { return p.t < 1.4; });
        confetti.forEach(function (p) {
            p.t += dt; p.vy += 700 * dt; p.vx *= .99;
            p.x += p.vx * dt; p.y += p.vy * dt; p.rot += p.vr * dt;
        });
        confetti = confetti.filter(function (p) { return p.t < p.life; });
        if (sparkles.length < 5 && Math.random() < dt * 2.2) {
            sparkles.push({ x: G.l + 20 + Math.random() * (G.r - G.l - 40), y: G.t + 40 + Math.random() * (FLOOR - G.t - 80), r: 3 + Math.random() * 4, t: 0, life: .7 + Math.random() * .6 });
        }
        sparkles.forEach(function (s) { s.t += dt; });
        sparkles = sparkles.filter(function (s) { return s.t < s.life; });
    }

    // Yalnız lokal mühitdə: animasiya dayananda (gizli tab) oyunu addım-addım sürmək üçün
    if (root.dataset.debug === '1') {
        window.clawDebug = {
            tick: function (sec) {
                for (var i = 0; i < sec * 120; i++) { clock += STEP; updateClaw(STEP); physics(STEP, false); effects(STEP); }
                hud(); render();
            },
            aim: function (x, z) { input.target = { x: clamp(x, X_MIN, X_MAX), z: clamp(z, Z_MIN, Z_MAX) }; },
            state: function () { return { claw: claw.state, x: Math.round(claw.x), z: Math.round(claw.z), held: claw.held && claw.held.type, chance: claw.chance, items: items.length, game: game }; },
            items: function () { return items.map(function (a) { return [a.type, Math.round(a.x), Math.round(a.y), Math.round(a.z)]; }); }
        };
    }

    /* ---------- idarəetmə ---------- */
    var KEYS = { ArrowLeft: 'keyL', a: 'keyL', A: 'keyL', ArrowRight: 'keyR', d: 'keyR', D: 'keyR', ArrowUp: 'keyB', w: 'keyB', W: 'keyB', ArrowDown: 'keyF', s: 'keyF', S: 'keyF' };
    document.addEventListener('keydown', function (e) {
        var tag = (e.target && e.target.tagName) || '';
        if (/INPUT|TEXTAREA|SELECT/.test(tag)) return;
        if (KEYS[e.key]) { input[KEYS[e.key]] = true; start(); e.preventDefault(); return; }
        if (e.key === ' ' || (e.key === 'Enter' && e.target === document.body)) {
            if (game.over) return;
            e.preventDefault();
            if (!game.started) start(); else drop();
        }
    });
    document.addEventListener('keyup', function (e) { if (KEYS[e.key]) input[KEYS[e.key]] = false; });
    window.addEventListener('blur', function () { input.keyL = input.keyR = input.keyB = input.keyF = false; input.bx = input.bz = 0; });

    Array.prototype.forEach.call(el.arrows, function (b) {
        var dx = parseInt(b.dataset.dx || '0', 10), dz = parseInt(b.dataset.dz || '0', 10);
        b.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            start();
            input.bx = dx; input.bz = dz;
            input.target = null;
            try { b.setPointerCapture(e.pointerId); } catch (err) { /* köhnə brauzer */ }
        });
        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(function (ev) {
            b.addEventListener(ev, function () { if (input.bx === dx && input.bz === dz) { input.bx = 0; input.bz = 0; } });
        });
        b.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    });

    el.drop.addEventListener('click', function () { if (!game.started) start(); else drop(); });
    el.again.addEventListener('click', again);

    // Şüşəyə toxunanda çəngəl həmin yerə gedir: ekran nöqtəsini qalağın təxmini
    // hündürlüyündəki üfüqi müstəviyə geri proyeksiya edirik
    function aim(e) {
        if (!game.started) { start(); return; }
        if (claw.state !== 'ready') return;
        var rect = canvas.getBoundingClientRect();
        var sx = (e.clientX - rect.left) * W / rect.width, sy = (e.clientY - rect.top) * H / rect.height;
        var y0 = FLOOR - 30;
        var s = clamp((sy - V.y) / (y0 - V.y), SB, 1);
        input.target = {
            x: clamp(V.x + (sx - V.x) / s, X_MIN, X_MAX),
            z: clamp((1 - s) / (1 - SB) * D, Z_MIN, Z_MAX)
        };
    }
    var aiming = false;
    canvas.addEventListener('pointerdown', function (e) { aiming = true; aim(e); });
    canvas.addEventListener('pointermove', function (e) { if (aiming) aim(e); });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (ev) { canvas.addEventListener(ev, function () { aiming = false; }); });

    /* ---------- başla ---------- */
    newGame();
    drawBack();
    drawFront();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { spriteCache = {}; drawBack(); drawFront(); });
    requestAnimationFrame(frame);
})();
