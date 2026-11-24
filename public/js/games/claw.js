/* =========================================================
   50 Qəpik — Hədiyyə Kranı (3D)
   Şüşə qutuda rəngli şarlar: çəngəli sağa-sola və irəli-geri sürüb şar tutursunuz,
   ön sol küncdəki çıxışa düşən şar açılır və içindən hədiyyə çıxır.
   Hədiyyəni SERVER seçir (ClawController): gündə 3 cəhd, real kod — Çarx oyunu kimi.
   Qonaqlar və təsdiqlənməmiş istifadəçilər sınaq rejimində oynayır (kod verilmir).
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
    var TURN_SECONDS = 15;
    var SPEED = 150;

    // Rəngli şarlar: [əsas rəng, açıq çalar]
    var BALL_COLORS = [
        ['#e63946', '#ff9a9a'], ['#f4a261', '#ffd8a8'], ['#2a9d8f', '#8ee6da'], ['#3a86ff', '#a8ccff'],
        ['#8338ec', '#cdaeff'], ['#ff006e', '#ff99c8'], ['#ffbe0b', '#ffe79a'], ['#06d6a0', '#94f4d8']
    ];
    var BALL_R = 17, BALL_N = 34;
    var CS = .8;   // çəngəlin miqyası

    function sc(z) { return 1 - (1 - SB) * z / D; }
    function proj(x, y, z) {
        var s = sc(z);
        return { x: V.x + (x - V.x) * s, y: V.y + (y - V.y) * s, s: s };
    }

    /* ---------- HTML və server ---------- */
    var $ = function (s) { return root.querySelector(s); };
    var el = {
        start: $('.cl-start'), result: $('.cl-result'), box: $('.cl-result .nb-result-box'),
        attempts: $('.cl-attempts'), time: $('.cl-time'), drop: $('.cl-drop'), arrows: root.querySelectorAll('.cl-arrow')
    };
    var VERIFIED = root.dataset.verified === '1';
    var URL = { start: root.dataset.startUrl, open: root.dataset.openUrl, claim: root.dataset.claimUrl, register: root.dataset.registerUrl, prizes: root.dataset.prizesUrl };
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    var server = null;   // təsdiqlənmiş istifadəçinin bu günki vəziyyəti (serverdən)
    try { server = JSON.parse(root.dataset.state || 'null'); } catch (e) { server = null; }
    var DEMO_ATTEMPTS = 3;

    function post(url, body) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body || {})
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) { d._ok = r.ok; d._status = r.status; return d; });
        });
    }
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

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
        items = [];
        for (var i = 0; i < BALL_N; i++) {
            var r = BALL_R;
            var x = CHUTE.r + 6 + r + Math.random() * (G.r - CHUTE.r - 6 - 2 * r);
            var z = r + Math.random() * (D - 2 * r);
            var y = G.t + 40 - i * 22;
            items.push({ type: 'ball', r: r, x: x, y: y, z: z, px: x, py: y, pz: z, ang: (Math.random() - .5) * 2, v: i % BALL_COLORS.length, held: false, gone: false });
        }
        // qalağı əvvəlcədən yerinə oturduruq ki, oyunçu düşməni görməsin
        for (var s = 0; s < 840; s++) physics(STEP, true);
    }

    // əyləncə rejimi: bu gün hədiyyə artıq götürülüb (kranda və ya Çarx oyununda) — limitsiz, hədiyyəsiz
    function funMode() { return VERIFIED && server && server.fun; }
    function attemptsLeft() { return VERIFIED ? (funMode() ? Infinity : (server ? server.attemptsLeft : 0)) : game.demoLeft; }
    function dayClosed() { return VERIFIED && server && !server.fun && (server.claimed || (server.attemptsLeft <= 0 && !server.last)); }

    function newGame() {
        resetClaw();
        makeItems();
        pops = []; confetti = [];
        game = { started: false, over: false, turnLeft: TURN_SECONDS, token: null, busy: false, opened: false, demoLeft: DEMO_ATTEMPTS };
        hud();
    }

    function start() {
        if (game.started || game.over) return;
        game.started = true;
        el.start.hidden = true;
        claw.state = 'ready';
        game.turnLeft = TURN_SECONDS;
        hud();
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

    /**
     * Çəngəl düşür. Təsdiqlənmiş istifadəçidə əvvəlcə server cəhdi sayır və şarı açmaq üçün
     * bir dəfəlik açar (token) verir; qonaqda sınaq açarı verilir.
     */
    function drop() {
        if (!game.started || game.over || game.busy || claw.state !== 'ready') return;
        if (attemptsLeft() <= 0) { showDayClosed(); return; }
        game.busy = true;
        hud();
        post(URL.start).then(function (d) {
            game.busy = false;
            if (!d._ok || !d.token) {
                if (d.state) server = d.state;
                showMessage('Cəhd alınmadı', d.message || 'Bağlantı xətası. Yenidən cəhd edin.');
                hud();
                return;
            }
            if (d.state) server = d.state;
            if (!VERIFIED) game.demoLeft--;
            game.token = d.token;
            game.opened = false;
            claw.state = 'down';
            input.target = null;
            hud();
        }).catch(function () {
            game.busy = false;
            showMessage('Bağlantı xətası', 'İnternet bağlantısını yoxlayıb yenidən cəhd edin.');
            hud();
        });
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

    // Tutuşun möhkəmliyi: şarın düz ortasından və üstü boş olanda yaxşı tutulur.
    // HOLD — ayarlar: base — ortadan tutanda saxlama şansı, edge — kənardan tutanda nə qədər azalır,
    // buried — üstündəki hər şar üçün nə qədər azalır, min/max — şansın hədləri
    var HOLD = { base: .97, edge: .4, buried: .08, min: .35, max: .97 };
    function holdChance(a) {
        var off = flat(a) / a.r;
        var buried = 0;
        items.forEach(function (b) {
            if (b !== a && b.y < a.y - a.r * .3 && Math.hypot(b.x - a.x, b.y - a.y, b.z - a.z) < a.r + b.r + 2) buried++;
        });
        return clamp(HOLD.base - off * HOLD.edge - buried * HOLD.buried, HOLD.min, HOLD.max);
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

    /** Şar çıxışa düşdü: bu cəhdin açarı ilə server şarın içindəkini seçir */
    function caught(a) {
        var p = proj(CHUTE_X, CHUTE.top, 0);
        flash = 1.6;
        var col = BALL_COLORS[a.v % BALL_COLORS.length];
        for (var i = 0; i < 34; i++) {
            confetti.push({
                x: p.x + (Math.random() - .5) * 40, y: p.y + 10,
                vx: (Math.random() - .3) * 260, vy: -220 - Math.random() * 260,
                rot: Math.random() * 6, vr: (Math.random() - .5) * 14,
                c: [col[0], col[1], '#E9A23B', '#FBF6EF'][i % 4], t: 0, life: 1.4 + Math.random() * .8
            });
        }
        // bir cəhddə yalnız bir şar açılır (təsadüfən ikinci şar da düşsə, sadəcə itir)
        if (!game.token || game.opened) return;
        game.opened = true;
        var token = game.token;
        game.token = null;
        pops.push({ text: 'Şar tutuldu! 🎈', x: Math.max(p.x + 30, 96), y: p.y + 40, t: 0, color: '#E9A23B', big: true });
        post(URL.open, { token: token }).then(function (d) {
            if (d.state) server = d.state;
            if (!d._ok) { showMessage('Şar açılmadı', d.message || 'Yenidən cəhd edin.'); hud(); return; }
            setTimeout(function () { showBall(d, col); }, 700); // şar çıxışa düşüb yerə çatsın
            hud();
        }).catch(function () { showMessage('Bağlantı xətası', 'Şar açılmadı. Yenidən cəhd edin.'); });
    }

    function nextTurn() {
        game.token = null;
        if (attemptsLeft() <= 0 || dayClosed()) {
            claw.state = 'idle';
            if (!game.over) setTimeout(function () { if (!game.over) showDayClosed(); }, 900);
            hud();
            return;
        }
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
                if (game.over || game.busy) break;
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
                // vaxt bitdi: cəhd sayılmır, "Yenidən başla" təklif olunur
                if (game.turnLeft <= 0) { game.turnLeft = 0; showTimeUp(); }
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
                        // tutuş zəifdirsə, şar yolda bir anda sürüşüb düşəcək
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
                if (claw.t > 1.1) {
                    if (game.opened) claw.state = 'wait';      // şar açılır — pəncərəni gözləyirik
                    else { pops.push({ text: 'Boş qayıtdı', x: proj(CHUTE_X, CHUTE.top, 0).x + 40, y: proj(CHUTE_X, CHUTE.top, 0).y + 40, t: 0, color: '#fbf6ef' }); nextTurn(); }
                }
                break;
        }
        if (claw.state !== 'close' && claw.state !== 'release' && !claw.held) claw.open += (1 - claw.open) * Math.min(1, dt * 8);

        claw.vx = (claw.x - oldX) / dt;
        claw.vy = (claw.y - oldY) / dt;
        claw.vz = (claw.z - oldZ) / dt;

        // tutulan şar çəngəllə gedir
        var h = claw.held;
        if (h) {
            var k = Math.min(1, dt * 14);
            h.x += (claw.x - h.x) * k;
            h.y += (gripY() - h.y) * k;
            h.z += (claw.z - h.z) * k;
            h.px = h.x; h.py = h.y; h.pz = h.z;
            h.ang += dt * (claw.slip >= 0 ? 3 : 1);
            if (claw.slip >= 0) {
                claw.slip -= dt;
                if (claw.slip <= 0) releaseHeld(true);
            }
        }
    }

    /* ---------- pəncərələr ---------- */
    function dialog(html, actions) {
        game.over = true;
        claw.state = claw.state === 'ready' ? 'idle' : claw.state;
        input.target = null;
        el.box.innerHTML = html + '<div class="st-choice cl-choice"></div>';
        // kupon kartı: bütün ekranın ortasında və yığcam (showClaimed əlavə edir)
        el.result.classList.remove('is-prize');
        el.box.classList.remove('has-prize');
        var bar = el.box.querySelector('.cl-choice');
        actions.forEach(function (a) {
            var b = document.createElement(a.href ? 'a' : 'button');
            b.className = 'btn ' + (a.primary ? 'btn-primary' : 'btn-ghost-dark');
            b.innerHTML = a.label;
            if (a.href) b.href = a.href; else { b.type = 'button'; b.addEventListener('click', a.fn); }
            bar.appendChild(b);
        });
        el.result.hidden = false;
        hud();
    }
    function closeDialog() {
        el.result.hidden = true;
        game.over = false;
        // əyləncə rejimində pəncərə bağlananda kran oynamağa hazır olsun
        if (funMode() && claw.state !== 'ready' && claw.state !== 'down' && claw.state !== 'close' && claw.state !== 'up' && claw.state !== 'home' && claw.state !== 'release') {
            claw.state = 'ready';
            game.turnLeft = TURN_SECONDS;
        }
        hud();
    }

    // əyləncə rejiminin səbəbinə görə qısa izah
    function funText() {
        if (!server) return '';
        if (server.funReason === 'attempts') return 'Bu günün cəhdləri bitdi — əyləncə üçün istədiyiniz qədər oynaya bilərsiniz, amma hədiyyə verilmir. Sabah yenə 3 cəhdiniz olacaq.';
        if (server.funReason === 'blocked') return 'Bu gün hədiyyəni artıq ' + server.blockedBy + ' qazanmısınız — əyləncə üçün oynaya bilərsiniz, amma hədiyyə verilmir.';
        return 'Bu gün hədiyyəni artıq qazanmısınız — əyləncə üçün oynaya bilərsiniz, amma hədiyyə verilmir.';
    }

    function playAgain() {
        closeDialog();
        if (dayClosed() || attemptsLeft() <= 0) { showDayClosed(); return; }
        claw.state = 'ready';
        game.turnLeft = TURN_SECONDS;
        hud();
    }

    // Çarx oyunundakı kimi: "Hədiyyəni götür" və ya "Davam et"
    var CONTINUE = '<i class="fa fa-redo me-2"></i>Davam et';

    /** Şar açıldı: içindəki hədiyyə */
    function showBall(d, col) {
        var ball = '<div class="cl-ball-open" style="--c1:' + col[0] + ';--c2:' + col[1] + '"><span></span><span></span></div>';
        var note, actions = [];
        if (d.demo) {
            note = d.prize
                ? '<p class="cl-note">Bu <b>sınaq</b> idi — real hədiyyə və kod üçün qeydiyyatdan keçin və nömrənizi Telegram ilə təsdiqləyin.</p>'
                : '<p class="cl-note">Bu <b>sınaq</b> idi.</p>';
            actions.push({ label: 'Qeydiyyat', href: URL.register, primary: true });
            if (game.demoLeft > 0) actions.push({ label: CONTINUE + ' <small>(' + game.demoLeft + ')</small>', fn: playAgain });
            else actions.push({ label: 'Yenidən başla', fn: restartDemo });
        } else if (d.fun) {
            // əyləncə rejimi: nəticə göstərilir, amma hədiyyə verilmir
            note = '<p class="cl-note">' + esc(funText()) + '</p>';
            actions.push({ label: CONTINUE, fn: playAgain, primary: true });
            if (server && server.claimed) actions.push({ label: '<i class="fa fa-ticket-alt me-2"></i>Kodum', fn: showClaimed });
        } else if (d.prize) {
            var left = attemptsLeft();
            note = left > 0
                ? '<p class="cl-note">Hədiyyəni götürsəniz, kod yaranır və bu günün oyunu bitir. Davam etsəniz, <b>' + esc(d.label) + '</b> itəcək. ' + left + ' cəhdiniz qalıb.</p>'
                : '<p class="cl-note">Bu son cəhdiniz idi — hədiyyəni götürün!</p>';
            actions.push({ label: '<i class="fa fa-gift me-2"></i>Hədiyyəni götür', fn: claimPrize, primary: true });
            if (left > 0) actions.push({ label: CONTINUE, fn: playAgain });
        } else if (funMode()) {
            // son cəhd də boş çıxdı — artıq əyləncə rejimi
            note = '<p class="cl-note">' + esc(funText()) + '</p>';
            actions.push({ label: '<i class="fa fa-play me-2"></i>Əyləncə üçün oyna', fn: playAgain, primary: true });
        } else {
            note = attemptsLeft() > 0
                ? '<p class="cl-note">Növbəti şarda şansınızı sınayın! ' + attemptsLeft() + ' cəhdiniz qalıb.</p>'
                : '<p class="cl-note">Bu günün cəhdləri bitdi. Sabah yenə gəlin!</p>';
            if (attemptsLeft() > 0) actions.push({ label: CONTINUE, fn: playAgain, primary: true });
            else actions.push({ label: 'Bağla', fn: closeDialog, primary: true });
        }
        dialog(ball + '<span class="eyebrow">' + (d.fun ? 'Əyləncə üçün' : d.prize ? 'Şarın içindən çıxdı 🎉' : 'Şar boş çıxdı') + '</span>'
            + '<h3 class="cl-prize">' + esc(d.label) + '</h3>' + note, actions);
    }

    function claimPrize() {
        var btn = el.box.querySelector('.btn-primary');
        if (btn) btn.disabled = true;
        post(URL.claim).then(function (d) {
            if (d.state) server = d.state;
            if (!d._ok || !server || !server.claimed) { showMessage('Hədiyyə götürülmədi', d.message || 'Yenidən cəhd edin.'); return; }
            showClaimed();
        }).catch(function () { if (btn) btn.disabled = false; });
    }

    /** Götürülmüş hədiyyə: kod və etibarlılıq müddəti */
    function showClaimed() {
        var c = server.claimed;
        var body = '<span class="eyebrow">Hədiyyəniz 🎉</span><h3 class="cl-prize">' + esc(c.label) + '</h3>';
        if (c.expired) {
            body += c.used
                ? '<p class="cl-note">Kod restoranda istifadə olunub. Sabah yenə oynayın!</p>'
                : '<p class="cl-note">Kodun vaxtı <b>' + esc(c.expires) + '</b>-də bitib. Sabah yenə oynayın!</p>';
        } else {
            body += '<div class="coupon-row"><div class="prize-code">' + esc(c.code) + '</div></div>'
                + '<p class="claimed-at"><i class="fa fa-clock me-1"></i>Götürülüb: <b>' + esc(c.claimed_at) + '</b></p>'
                + '<p class="cl-note">Kodu ofisianta göstərin. <b>' + esc(c.expires) + '</b>-dək etibarlıdır. <a href="' + URL.prizes + '">Hədiyyələrim</a>-də də saxlanılır.</p>'
                + (window.earnMoreHtml ? window.earnMoreHtml() : ''); // daha çox xal: Instagram və dəvət
        }
        // hədiyyə götürülüb — kran əyləncə üçün oynanmağa davam edə bilər (hədiyyəsiz)
        dialog(body + '<p class="cl-note">İstəsəniz, əyləncə üçün oynamağa davam edə bilərsiniz — hədiyyə verilmir.</p>',
            [{ label: '<i class="fa fa-play me-2"></i>Əyləncə üçün oyna', fn: playAgain, primary: true },
             { label: 'Bağla', fn: closeDialog }]);
        if (!c.expired) { el.result.classList.add('is-prize'); el.box.classList.add('has-prize'); }
    }

    function showDayClosed() {
        if (server && server.claimed) { showClaimed(); return; }
        // götürülməmiş hədiyyə gözləyir (məs. səhifə yenilənib) — şarı yenidən göstəririk ki, götürə bilsin;
        // boş şarı yenidən göstərməyin mənası yoxdur
        if (server && server.last && server.last.prize) { showBall({ label: server.last.label, prize: true, demo: false }, BALL_COLORS[0]); return; }
        if (!VERIFIED) { restartDemoDialog(); return; }
        // cəhdlər bitib — əyləncə üçün oynamaq olar (hədiyyəsiz)
        dialog('<span class="eyebrow">Bu gün üçün</span><h3>Cəhdlər bitdi</h3><p class="cl-note">' + esc(funText() || 'Sabah yenə 3 cəhdiniz olacaq.') + '</p>',
            [{ label: '<i class="fa fa-play me-2"></i>Əyləncə üçün oyna', fn: playAgain, primary: true },
             { label: 'Bağla', fn: closeDialog }]);
    }

    function showTimeUp() {
        dialog('<h3>Vaxt bitdi! ⏰</h3><p class="cl-note">Kranı ' + TURN_SECONDS + ' saniyə ərzində salmadınız. Cəhd sayılmadı — yenidən başlayın.</p>',
            [{ label: 'Yenidən başla', fn: playAgain, primary: true }]);
    }

    function showMessage(title, text) {
        dialog('<h3>' + esc(title) + '</h3><p class="cl-note">' + esc(text) + '</p>', [{ label: 'Bağla', fn: playAgain, primary: true }]);
    }

    function restartDemo() {
        closeDialog();
        newGame();
        start();
    }
    function restartDemoDialog() {
        dialog('<span class="eyebrow">Sınaq</span><h3>Sınaq cəhdləri bitdi</h3><p class="cl-note">Real hədiyyə üçün qeydiyyatdan keçin — gündə 3 cəhd.</p>',
            [{ label: 'Qeydiyyat', href: URL.register, primary: true }, { label: 'Yenidən başla', fn: restartDemo }]);
    }

    /* ---------- HUD ---------- */
    var lastHud = '';
    function hud() {
        var t = claw.state === 'ready' && !game.over ? String(Math.ceil(game.turnLeft)) : '–';
        var left = attemptsLeft();
        var key = left + '|' + t + '|' + claw.state + '|' + game.busy + '|' + game.over;
        if (key === lastHud) return;
        lastHud = key;
        el.attempts.textContent = left === Infinity ? '∞' : left; // əyləncə rejimi — limitsiz
        el.time.textContent = t;
        el.time.parentNode.classList.toggle('is-low', claw.state === 'ready' && game.turnLeft <= 5);
        el.drop.disabled = game.busy || game.over || (game.started && claw.state !== 'ready') || left <= 0;
    }

    /* =========================================================
       Rəsm
       ========================================================= */
    var dpr = 1;
    var back = document.createElement('canvas'), front = document.createElement('canvas');

    /**
     * Kətanın daxili ölçüsü ekrandakı ölçüyə uyğun seçilir: tam ekranda böyük göstəriləndə
     * də bulanıq olmasın. Arxa və ön qatlar yenidən çəkilir.
     */
    function setResolution() {
        var cssW = canvas.getBoundingClientRect().width || W;
        var next = Math.min(3, Math.max(1, (window.devicePixelRatio || 1) * cssW / W));
        next = Math.round(next * 4) / 4;   // xırda dəyişikliklərdə hər dəfə yenidən çəkməyək
        if (next === dpr && canvas.width) return;
        dpr = next;
        canvas.width = back.width = front.width = Math.round(W * dpr);
        canvas.height = back.height = front.height = Math.round(H * dpr);
        if (typeof drawBack === 'function' && game) { drawBack(); drawFront(); }
    }
    setResolution();

    var GOLD = '#E9A23B', GOLD_D = '#C9831F', WINE = '#7E1A1A', CREAM = '#FBF6EF';

    // arxa divardakı 50 Qəpik loqosu — yüklənəndə arxa qat yenidən çəkilir
    var LOGO = new Image();
    LOGO.onload = function () { drawBack(); };

    /**
     * 50 qəpiklik sikkə: iki metallı (gümüşü xarici halqa, qızılı orta), günəş şüaları,
     * iki neft buruğu, "50" və "QƏPİK" yazısı. (x, y) — mərkəz, r — radius.
     */
    function drawCoin(c, x, y, r) {
        c.save();
        c.translate(x, y);
        // kölgə
        c.fillStyle = 'rgba(0,0,0,.35)';
        c.beginPath(); c.arc(1, 1.5, r, 0, Math.PI * 2); c.fill();
        // xarici gümüşü halqa
        var ring = c.createLinearGradient(-r, -r, r, r);
        ring.addColorStop(0, '#f2f2ee'); ring.addColorStop(.45, '#b9b8b0'); ring.addColorStop(1, '#7d7c74');
        c.fillStyle = ring;
        c.beginPath(); c.arc(0, 0, r, 0, Math.PI * 2); c.fill();
        // halqada şüalar
        c.strokeStyle = 'rgba(90,88,80,.45)'; c.lineWidth = .5;
        for (var i = 0; i < 40; i++) {
            var t = i / 40 * Math.PI * 2;
            c.beginPath(); c.moveTo(Math.cos(t) * r * .7, Math.sin(t) * r * .7); c.lineTo(Math.cos(t) * r * .93, Math.sin(t) * r * .93); c.stroke();
        }
        // qızılı orta hissə
        var core = c.createRadialGradient(-r * .2, -r * .25, r * .05, 0, 0, r * .68);
        core.addColorStop(0, '#fbe7a1'); core.addColorStop(.6, '#dcb04a'); core.addColorStop(1, '#a87b22');
        c.fillStyle = core;
        c.beginPath(); c.arc(0, 0, r * .68, 0, Math.PI * 2); c.fill();
        c.strokeStyle = 'rgba(110,80,20,.6)'; c.lineWidth = .7; c.stroke();
        c.save();
        c.beginPath(); c.arc(0, 0, r * .68, 0, Math.PI * 2); c.clip();
        // günəş şüaları (yuxarıda)
        c.strokeStyle = 'rgba(140,100,30,.45)'; c.lineWidth = .45;
        for (var j = 0; j < 18; j++) {
            var a = Math.PI + j / 17 * Math.PI;
            c.beginPath(); c.moveTo(Math.cos(a) * r * .18, -r * .1 + Math.sin(a) * r * .18); c.lineTo(Math.cos(a) * r * .9, -r * .1 + Math.sin(a) * r * .9); c.stroke();
        }
        // iki neft buruğu (sağda)
        c.strokeStyle = 'rgba(95,65,15,.9)'; c.lineWidth = .7;
        [[r * .2, r * .62], [r * .48, r * .5]].forEach(function (d) {
            var bx = d[0], h = d[1], w = r * .1;
            c.beginPath();
            c.moveTo(bx - w, r * .55); c.lineTo(bx, r * .55 - h); c.lineTo(bx + w, r * .55);
            c.moveTo(bx - w * .66, r * .55 - h * .33); c.lineTo(bx + w * .66, r * .55 - h * .33);
            c.moveTo(bx - w * .33, r * .55 - h * .66); c.lineTo(bx + w * .33, r * .55 - h * .66);
            c.stroke();
        });
        c.restore();
        // "50" və "QƏPİK" (solda)
        c.fillStyle = '#7a5410';
        c.textAlign = 'center'; c.textBaseline = 'middle';
        c.font = '900 ' + Math.round(r * .62) + 'px Nunito, sans-serif';
        c.fillText('50', -r * .26, r * .05);
        c.font = '800 ' + Math.max(4, Math.round(r * .2)) + 'px Nunito, sans-serif';
        c.fillText('QƏPİK', -r * .22, r * .42);
        // parıltı
        c.fillStyle = 'rgba(255,255,255,.45)';
        c.beginPath(); c.ellipse(-r * .45, -r * .55, r * .25, r * .09, -.7, 0, Math.PI * 2); c.fill();
        c.restore();
    }
    if (root.dataset.logo) LOGO.src = root.dataset.logo;

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

        // arxa divara yapışdırılmış 50 Qəpik loqosu — yalnız loqonun öz dairəsi (əlavə haşiyə/altlıq yoxdur)
        if (LOGO.complete && LOGO.naturalWidth) {
            var lx = (bl.x + br.x) / 2, ly = (bl.y + br.y) / 2, lr = 34;   // arxa divarın tam ortası
            // altında yüngül kölgə — divardan bir az qabarıq görünsün
            c.save();
            c.shadowColor = 'rgba(0,0,0,.45)'; c.shadowBlur = 8; c.shadowOffsetY = 2;
            c.fillStyle = 'rgba(0,0,0,.01)';
            c.beginPath(); c.arc(lx, ly, lr * .96, 0, Math.PI * 2); c.fill();
            c.restore();
            c.save();
            c.beginPath(); c.arc(lx, ly, lr, 0, Math.PI * 2); c.clip();   // şəklin künclərini kəsirik
            c.drawImage(LOGO, lx - lr, ly - lr, lr * 2, lr * 2);
            c.fillStyle = 'rgba(40,10,5,.1)'; c.fillRect(lx - lr, ly - lr, lr * 2, lr * 2); // divarın işığına uyğun
            c.restore();
        }

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
        // 50 qəpiklik sikkə — panelin sağında
        drawCoin(c, 360, FLOOR + 23, 18);

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

    /* ---------- şarlar ----------
       Hər rəngdəki şar bir dəfə ayrıca kətana (sprite) çəkilir, sonra hazır şəkil kimi qoyulur. */
    var SS = 3, spriteCache = {};

    var DRAW = {
        /**
         * Rəngli hədiyyə şarı: yarışəffaf parlaq kürə, ortada kapsul tikişi, içində hədiyyə qutusunun
         * siluetı, kənarlarda kölgə, yuxarı-solda işıq əksi.
         */
        ball: function (c, r, v) {
            var C = BALL_COLORS[v % BALL_COLORS.length];
            // gövdə
            var g = c.createRadialGradient(-r * .35, -r * .4, r * .08, 0, 0, r);
            g.addColorStop(0, C[1]);
            g.addColorStop(.55, C[0]);
            g.addColorStop(1, C[0]);
            c.fillStyle = g;
            dot(c, 0, 0, r);
            c.save();
            c.beginPath(); c.arc(0, 0, r, 0, Math.PI * 2); c.clip();
            // içində hədiyyə qutusu (yarışəffaf şardan görünür)
            c.fillStyle = 'rgba(255,255,255,.22)';
            rr(c, -r * .42, -r * .18, r * .84, r * .62, r * .08); c.fill();
            c.fillStyle = 'rgba(255,255,255,.3)';
            c.fillRect(-r * .48, -r * .3, r * .96, r * .16);
            c.fillRect(-r * .07, -r * .3, r * .14, r * .74);
            c.strokeStyle = 'rgba(255,255,255,.35)'; c.lineWidth = r * .07;
            c.beginPath();
            c.ellipse(-r * .16, -r * .38, r * .15, r * .09, -.5, 0, Math.PI * 2);
            c.ellipse(r * .16, -r * .38, r * .15, r * .09, .5, 0, Math.PI * 2);
            c.stroke();
            // kapsulun iki yarısı: aşağı yarı bir az tünd
            c.fillStyle = 'rgba(0,0,0,.12)';
            c.fillRect(-r, r * .05, r * 2, r);
            // kənar kölgəsi
            var rim = c.createRadialGradient(0, 0, r * .6, 0, 0, r);
            rim.addColorStop(0, 'rgba(0,0,0,0)');
            rim.addColorStop(1, 'rgba(0,0,0,.35)');
            c.fillStyle = rim;
            c.fillRect(-r, -r, r * 2, r * 2);
            c.restore();
            // ortadakı tikiş (kapsul açılan yer)
            c.strokeStyle = 'rgba(255,255,255,.75)'; c.lineWidth = 1.2;
            c.beginPath(); c.ellipse(0, r * .05, r * .98, r * .26, 0, 0, Math.PI); c.stroke();
            c.strokeStyle = 'rgba(0,0,0,.2)'; c.lineWidth = .8;
            c.beginPath(); c.ellipse(0, r * .08, r * .98, r * .26, 0, 0, Math.PI); c.stroke();
            // işıq əksləri
            c.fillStyle = 'rgba(255,255,255,.85)';
            c.beginPath(); c.ellipse(-r * .38, -r * .48, r * .28, r * .14, -.6, 0, Math.PI * 2); c.fill();
            c.fillStyle = 'rgba(255,255,255,.6)';
            dot(c, r * .1, -r * .62, r * .07);
            c.strokeStyle = 'rgba(255,255,255,.35)'; c.lineWidth = 1.2;
            c.beginPath(); c.arc(0, 0, r * .86, Math.PI * .15, Math.PI * .55); c.stroke();
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
            state: function () { return { claw: claw.state, x: Math.round(claw.x), z: Math.round(claw.z), held: !!claw.held, chance: claw.chance, items: items.length, game: game, server: server }; },
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

    /* ---------- tam ekran ---------- */
    var fullBtn = $('.cl-full');
    var fsEl = function () { return document.fullscreenElement || document.webkitFullscreenElement; };
    function setFull(on) {
        root.classList.toggle('is-full', on);
        document.documentElement.classList.toggle('cl-noscroll', on);
        fullBtn.innerHTML = on ? '<i class="fa fa-compress"></i>' : '<i class="fa fa-expand"></i>';
        fullBtn.title = fullBtn.ariaLabel = on ? 'Tam ekrandan çıx' : 'Tam ekran';
        requestAnimationFrame(setResolution);
    }
    fullBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (root.classList.contains('is-full')) {
            if (fsEl()) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            setFull(false);
            return;
        }
        setFull(true);
        // brauzerin öz tam ekranı (iPhone-da yoxdur — orada oyun sadəcə səhifəni örtür)
        var req = root.requestFullscreen || root.webkitRequestFullscreen;
        if (req) { try { var p = req.call(root); if (p && p.catch) p.catch(function () {}); } catch (err) { /* icazə verilmədi */ } }
    });
    // Esc ilə və ya brauzerin öz düyməsi ilə çıxanda
    ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (ev) {
        document.addEventListener(ev, function () { if (!fsEl() && root.classList.contains('is-full')) setFull(false); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && root.classList.contains('is-full') && !fsEl()) setFull(false); });
    window.addEventListener('resize', function () { requestAnimationFrame(setResolution); });

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
    // bu gün artıq hədiyyə götürülüb, cəhdlər bitib və ya açılmış şar gözləyir — dərhal göstəririk
    // (başqa oyunda hədiyyə götürülübsə pəncərə açılmır — xəbərdarlıq yan paneldədir, əyləncə üçün oynayır)
    if (VERIFIED && server && (server.claimed || (server.last && server.last.prize) || (!server.fun && server.attemptsLeft <= 0))) {
        el.start.hidden = true;
        game.started = true;
        showDayClosed();
    }
    drawBack();
    drawFront();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { spriteCache = {}; drawBack(); drawFront(); });
    requestAnimationFrame(frame);
})();
