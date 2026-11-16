/*
 * Uzun nərd — oyunçu kompüterə qarşı.
 *
 * Qaydalar:
 *  - hər tərəfin 15 daşı öz "başında" başlayır, başlar diaqonal olaraq qarşı-qarşıyadır;
 *  - hər iki tərəf eyni istiqamətdə hərəkət edir, daş vurmaq yoxdur;
 *  - rəqibin daşı olan xanaya (1 daş olsa belə) getmək olmaz;
 *  - hər gediş başdan yalnız 1 daş götürmək olar (ilk gedişdə 6-6, 4-4, 3-3 düşsə — 2 daş);
 *  - qarşısında rəqib daşı olmayan 6 ardıcıl xanalıq "çəpər" qurmaq olmaz;
 *  - zərlərin hamısını oynamaq mütləqdir; yalnız biri oynana bilirsə — böyüyü;
 *  - 15 daşın hamısı evə (son dörddəbirə) çatandan sonra daş çıxarmaq olar;
 *  - kim daşlarını əvvəl çıxarsa, qalibdir. Rəqib heç bir daş çıxara bilməyibsə — mars.
 *
 * Lövhə: 24 xana (0..23). Hər oyunçunun öz yolu var (0 = baş, 23 = son xana);
 * oyunçu üçün yol mövqeyi p lövhədə (p + offset) % 24 xanasıdır.
 */
(function () {
    'use strict';

    const HUMAN = 0, AI = 1;
    const OFFSET = [0, 12];
    const HOME_START = 18;
    const root = document.getElementById('nard');
    if (!root) return;

    const cfg = {
        canSave: root.dataset.canSave === '1',
        saveUrl: root.dataset.saveUrl,
        csrf: document.querySelector('meta[name="csrf-token"]')?.content,
    };
    // İki nəfərlik oyun (dəvətlə): vəziyyət serverdədir, bu brauzer onu soruşub göstərir.
    // Oyunçu özünü həmişə ağ daşlarla (HUMAN), rəqibi qara daşlarla (AI yerində) görür.
    const MP = root.dataset.matchUrl ? {
        stateUrl: root.dataset.matchUrl,
        rollUrl: root.dataset.rollUrl,
        moveUrl: root.dataset.moveUrl,
        resignUrl: root.dataset.resignUrl,
        version: 0,
        timer: null,
        finished: false,
    } : null;
    const OPP_NAME = root.dataset.oppName || 'Kompüter';

    /* ================= Qaydalar ================= */

    const toBoard = (pl, p) => (p + OFFSET[pl]) % 24;
    const toPath = (pl, b) => (b - OFFSET[pl] + 24) % 24;

    function newState() {
        const pts = Array.from({length: 24}, () => ({o: -1, n: 0}));
        pts[toBoard(HUMAN, 0)] = {o: HUMAN, n: 15};
        pts[toBoard(AI, 0)] = {o: AI, n: 15};
        return {pts, off: [0, 0]};
    }

    const clone = s => ({pts: s.pts.map(x => ({o: x.o, n: x.n})), off: s.off.slice()});
    const key = s => s.pts.map(x => x.o < 0 ? '.' : (x.o ? 'b' : 'w') + x.n).join(',') + '|' + s.off.join(',');

    function allInHome(s, pl) {
        for (let b = 0; b < 24; b++) {
            if (s.pts[b].o === pl && toPath(pl, b) < HOME_START) return false;
        }
        return true;
    }

    /** Rəqibin qarşısında olan, ötüb keçilə bilməyən 6 xanalıq çəpər varmı */
    function hasIllegalPrime(s, pl) {
        const opp = 1 - pl;
        let oppMax = -1; // rəqibin ən qabaqdakı daşının mövqeyi (öz yolunda)
        for (let b = 0; b < 24; b++) {
            if (s.pts[b].o === opp) oppMax = Math.max(oppMax, toPath(opp, b));
        }
        for (let start = 0; start <= 18; start++) {
            let run = true;
            for (let k = 0; k < 6 && run; k++) run = s.pts[toBoard(opp, start + k)].o === pl;
            if (run && oppMax <= start + 5) return true; // çəpərin qabağında rəqib daşı yoxdur
        }
        return false;
    }

    function apply(s, pl, from, to) {
        const ns = clone(s);
        const src = ns.pts[from];
        src.n--;
        if (src.n === 0) src.o = -1;
        if (to === 'off') ns.off[pl]++;
        else { ns.pts[to].o = pl; ns.pts[to].n++; }
        return ns;
    }

    /** Bir zərlə mümkün olan bütün tək gedişlər */
    function genMoves(s, ctx) {
        const pl = ctx.pl, moves = [];
        const dice = [...new Set(ctx.dice)];
        const home = allInHome(s, pl);
        const primeBefore = hasIllegalPrime(s, pl);

        // evdə ən arxada olan daş (çıxarma qaydası üçün)
        let rearmost = 24;
        if (home) for (let b = 0; b < 24; b++) if (s.pts[b].o === pl) rearmost = Math.min(rearmost, toPath(pl, b));

        for (const d of dice) {
            for (let b = 0; b < 24; b++) {
                if (s.pts[b].o !== pl) continue;
                const p = toPath(pl, b);
                const fromHead = p === 0;
                if (fromHead && ctx.headUsed >= ctx.headLimit) continue;
                const q = p + d;
                let to;
                if (q < 24) {
                    to = toBoard(pl, q);
                    if (s.pts[to].o === 1 - pl) continue;
                } else {
                    if (!home) continue;
                    if (q > 24 && p !== rearmost) continue; // böyük zərlə yalnız ən arxadakı daş çıxır
                    to = 'off';
                }
                const ns = apply(s, pl, b, to);
                if (to !== 'off' && !primeBefore && hasIllegalPrime(ns, pl)) continue;
                moves.push({from: b, to, die: d, head: fromHead, ns});
            }
        }
        return moves;
    }

    function nextCtx(ctx, m) {
        const dice = ctx.dice.slice();
        dice.splice(dice.indexOf(m.die), 1);
        return {pl: ctx.pl, dice, headUsed: ctx.headUsed + (m.head ? 1 : 0), headLimit: ctx.headLimit};
    }

    /** Bu vəziyyətdən maksimum neçə zər oynana bilər */
    function maxUsable(s, ctx, memo = new Map()) {
        if (!ctx.dice.length) return 0;
        const k = key(s) + '#' + ctx.dice.join('') + '#' + ctx.headUsed;
        if (memo.has(k)) return memo.get(k);
        let best = 0;
        for (const m of genMoves(s, ctx)) {
            best = Math.max(best, 1 + maxUsable(m.ns, nextCtx(ctx, m), memo));
            if (best === ctx.dice.length) break;
        }
        memo.set(k, best);
        return best;
    }

    /** Qaydalara uyğun gedişlər (zərlərin maksimum istifadəsi nəzərə alınmaqla) */
    function legalMoves(s, ctx) {
        const moves = genMoves(s, ctx);
        if (!moves.length) return [];
        const memo = new Map();
        const target = maxUsable(s, ctx, memo);
        let legal = moves.filter(m => 1 + maxUsable(m.ns, nextCtx(ctx, m), memo) === target);

        // iki fərqli zərdən yalnız biri oynana bilirsə — böyüyü oynanmalıdır
        if (target === 1 && ctx.dice.length === 2 && ctx.dice[0] !== ctx.dice[1]) {
            const big = Math.max(...legal.map(m => m.die));
            legal = legal.filter(m => m.die === big);
        }
        return legal;
    }

    function pips(s, pl) {
        let sum = 0;
        for (let b = 0; b < 24; b++) if (s.pts[b].o === pl) sum += s.pts[b].n * (24 - toPath(pl, b));
        return sum;
    }

    /* ================= Kompüter ================= */

    /** Gedişin sonunda vəziyyəti kompüterin nöqteyi-nəzərindən qiymətləndirir */
    function evaluate(s, pl) {
        const opp = 1 - pl;
        let score = (pips(s, opp) - pips(s, pl)) + s.off[pl] * 6;

        let oppRear = 24;
        for (let b = 0; b < 24; b++) if (s.pts[b].o === opp) oppRear = Math.min(oppRear, toPath(opp, b));

        for (let b = 0; b < 24; b++) {
            const x = s.pts[b];
            if (x.o !== pl) continue;
            score += 1.2;                                   // tutulmuş xana
            if (toPath(opp, b) > oppRear) score += 1.6;     // rəqibin yolunu kəsir
            if (x.n > 3 && toPath(pl, b) !== 0) score -= (x.n - 3) * 0.7; // bir yerə çox yığmamaq
        }
        return score + Math.random() * 0.4; // bir az təsadüf — hər oyun eyni olmasın
    }

    /** Bütün tam gediş variantları (eyni nəticə verənlər birləşdirilir) */
    function sequences(s, ctx, out = new Map(), path = []) {
        const moves = legalMoves(s, ctx);
        if (!moves.length) {
            const k = key(s);
            if (!out.has(k)) out.set(k, {moves: path, state: s});
            return out;
        }
        const seen = new Set();
        for (const m of moves) {
            const k = key(m.ns) + '#' + m.die;
            if (seen.has(k)) continue;
            seen.add(k);
            sequences(m.ns, nextCtx(ctx, m), out, path.concat(m));
            if (out.size > 4000) break; // çox nadir hallarda hesablamanı məhdudlaşdırırıq
        }
        return out;
    }

    function bestSequence(s, ctx) {
        let best = null, bestScore = -Infinity;
        for (const seq of sequences(s, ctx).values()) {
            const sc = evaluate(seq.state, ctx.pl);
            if (sc > bestScore) { bestScore = sc; best = seq; }
        }
        return best ? best.moves : [];
    }

    /* ================= Oyun gedişatı ================= */

    let game;

    const $ = sel => root.querySelector(sel);
    const els = {
        board: $('.nb-board'),
        status: $('.nb-status'),
        roll: $('#nbRoll'),
        undo: $('#nbUndo'),
        restart: document.getElementById('nbRestart'), // yan paneldədir
        dice: $('.nb-dice'),
        offH: $('#nbOffHuman'),
        offA: $('#nbOffAi'),
        trayH: $('.nb-tray-human'),
        result: $('.nb-result'),
    };

    const rollDie = () => 1 + Math.floor(Math.random() * 6);
    const sleep = ms => new Promise(r => setTimeout(r, ms));

    function startGame() {
        game = {
            s: newState(),
            turn: null,
            ctx: null,
            legal: [],
            selected: null,
            history: [],
            firstTurn: [true, true],
            rolled: [],
            over: false,
            busy: false,
        };
        els.result.hidden = true;
        render();
        decideStarter();
    }

    async function decideStarter() {
        game.busy = true;
        let h, a;
        do {
            h = rollDie(); a = rollDie();
            setStatus(`Kim başlayır? Siz: <b>${h}</b> · Kompüter: <b>${a}</b>${h === a ? ' — bərabərdir, yenidən…' : ''}`);
            renderDice([h, a], [], true);
            await sleep(1100);
        } while (h === a);
        game.busy = false;
        beginTurn(h > a ? HUMAN : AI);
    }

    function beginTurn(pl) {
        game.turn = pl;
        game.ctx = null;
        game.legal = [];
        game.selected = null;
        game.history = [];
        if (pl === HUMAN) {
            setStatus('Sizin növbəniz — <b>zər atın</b>.');
            renderDice([], [], false);
            render();
        } else {
            render();
            aiTurn();
        }
    }

    function makeCtx(pl, d1, d2) {
        const dice = d1 === d2 ? [d1, d1, d1, d1] : [d1, d2];
        const headLimit = game.firstTurn[pl] && d1 === d2 && [6, 4, 3].includes(d1) ? 2 : 1;
        return {pl, dice, headUsed: 0, headLimit};
    }

    async function humanRoll() {
        if (game.over || game.busy || game.turn !== HUMAN || game.ctx) return;
        if (MP) return mpRoll();
        game.busy = true;
        const d1 = rollDie(), d2 = rollDie();
        await animateDice([d1, d2]);
        game.rolled = [d1, d2];
        game.ctx = makeCtx(HUMAN, d1, d2);
        game.firstTurn[HUMAN] = false;
        game.busy = false;
        refreshHuman();
    }

    function refreshHuman() {
        game.legal = game.ctx.dice.length ? legalMoves(game.s, game.ctx) : [];
        game.selected = null;
        renderDice(game.rolled, game.ctx.dice, false);

        if (!game.legal.length) {
            const left = game.ctx.dice.length;
            render();
            if (left && !game.history.length) setStatus('Oynamağa gediş yoxdur — növbə kompüterə keçir.');
            else if (left) setStatus('Qalan zərlə gediş yoxdur — növbə kompüterə keçir.');
            else setStatus('Gediş bitdi.');
            endHumanTurn();
            return;
        }
        setStatus('Daş seçin, sonra işıqlanan xanaya basın.');
        render();
    }

    async function endHumanTurn() {
        game.busy = true;
        await sleep(game.history.length ? 650 : 1400);
        game.busy = false;
        if (checkWin()) return;
        beginTurn(AI);
    }

    function humanMove(m) {
        if (MP) return mpMove(m);
        game.history.push({s: game.s, ctx: game.ctx});
        game.s = m.ns;
        game.ctx = nextCtx(game.ctx, m);
        if (checkWin()) { render(); return; }
        refreshHuman();
    }

    function undo() {
        if (game.busy || game.turn !== HUMAN || !game.history.length) return;
        const last = game.history.pop();
        game.s = last.s;
        game.ctx = last.ctx;
        refreshHuman();
    }

    async function aiTurn() {
        game.busy = true;
        setStatus('Kompüter düşünür…');
        await sleep(600);
        const d1 = rollDie(), d2 = rollDie();
        await animateDice([d1, d2]);
        let ctx = makeCtx(AI, d1, d2);
        game.firstTurn[AI] = false;
        renderDice([d1, d2], ctx.dice, false);

        await sleep(50);
        const moves = bestSequence(game.s, ctx);
        if (!moves.length) {
            setStatus('Kompüterin gedişi yoxdur.');
            await sleep(1200);
        }
        for (const m of moves) {
            await sleep(550);
            game.lastMove = {from: m.from, to: m.to};
            game.s = m.ns;
            ctx = nextCtx(ctx, m);
            renderDice([d1, d2], ctx.dice, false);
            render();
            if (checkWin()) { game.busy = false; return; }
        }
        await sleep(500);
        game.lastMove = null;
        game.busy = false;
        beginTurn(HUMAN);
    }

    function checkWin() {
        for (const pl of [HUMAN, AI]) {
            if (game.s.off[pl] === 15) {
                game.over = true;
                game.legal = [];
                game.selected = null;
                const mars = game.s.off[1 - pl] === 0;
                showResult(pl === HUMAN, mars);
                return true;
            }
        }
        return false;
    }

    async function showResult(won, mars) {
        render();
        setStatus(won ? 'Qalib gəldiniz! 🎉' : 'Kompüter qalib gəldi.');
        const box = els.result;
        box.querySelector('.nb-result-title').textContent = won ? (mars ? 'Mars! Təbriklər! 🎉' : 'Qalib gəldiniz! 🎉') : (mars ? 'Mars oldunuz…' : 'Bu dəfə uduzdunuz');
        const note = box.querySelector('.nb-result-note');
        note.innerHTML = won ? 'Möhtəşəm oyun idi!' : 'Növbəti dəfə şansınız gətirəcək.';
        // kompüterə qarşı oyun məşqdir — xal yalnız dostla oyunda verilir
        note.innerHTML += '<br><small>Xal və günün qalibləri yalnız dostla oyunda hesablanır — sağdakı «Dostla oyna» bölməsindən dəvət göndərin.</small>';
        box.hidden = false;
    }

    /* ================= Görüntü ================= */

    // Ekranda yuxarı sıra soldan sağa 11..0, aşağı sıra soldan sağa 12..23
    const TOP = [11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0];
    const BOTTOM = [12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23];

    function buildBoard() {
        const half = (arr, cls) => `
            <div class="nb-row ${cls}">
                <div class="nb-quad">${arr.slice(0, 6).map(pointHtml).join('')}</div>
                <div class="nb-bar"></div>
                <div class="nb-quad">${arr.slice(6).map(pointHtml).join('')}</div>
            </div>`;
        els.board.innerHTML = half(TOP, 'nb-top') + half(BOTTOM, 'nb-bottom');
        els.board.addEventListener('click', onBoardClick);
        els.trayH.addEventListener('click', () => onTarget('off'));
    }

    const pointHtml = b => `<div class="nb-pt ${b % 2 ? 'odd' : 'even'}" data-b="${b}"><span class="nb-tri"></span><span class="nb-stack"></span></div>`;

    function render() {
        const s = game.s;
        const sel = game.selected;
        const fromSet = new Set(game.turn === HUMAN && !game.busy ? game.legal.map(m => m.from) : []);
        const toSet = new Set(sel !== null ? game.legal.filter(m => m.from === sel).map(m => m.to) : []);

        els.board.querySelectorAll('.nb-pt').forEach(el => {
            const b = +el.dataset.b;
            const x = s.pts[b];
            const shown = Math.min(x.n, 5);
            let html = '';
            for (let i = 0; i < shown; i++) {
                const label = i === shown - 1 && x.n > 5 ? `<em>${x.n}</em>` : '';
                html += `<i class="nb-chk ${x.o === HUMAN ? 'w' : 'b'}">${label}</i>`;
            }
            el.querySelector('.nb-stack').innerHTML = html;
            el.classList.toggle('can-from', fromSet.has(b));
            el.classList.toggle('selected', sel === b);
            el.classList.toggle('can-to', toSet.has(b));
            el.classList.toggle('last', (!!game.lastMove && (game.lastMove.from === b || game.lastMove.to === b)) || !!(game.lastMarks && game.lastMarks.has(b)));
        });

        els.offH.textContent = s.off[HUMAN];
        els.offA.textContent = s.off[AI];
        els.trayH.classList.toggle('can-to', toSet.has('off'));

        els.roll.disabled = !(game.turn === HUMAN && !game.ctx && !game.busy && !game.over);
        els.undo.disabled = !(game.turn === HUMAN && game.history.length && !game.busy && !game.over);
        root.classList.toggle('is-ai', game.turn === AI);
    }

    const PIPS = {1: [5], 2: [1, 9], 3: [1, 5, 9], 4: [1, 3, 7, 9], 5: [1, 3, 5, 7, 9], 6: [1, 3, 4, 6, 7, 9]};
    const dieHtml = (v, used, cls = '') =>
        `<span class="nb-die ${used ? 'used' : ''} ${cls}">${[1,2,3,4,5,6,7,8,9].map(i => `<i class="${PIPS[v].includes(i) ? 'on' : ''}"></i>`).join('')}</span>`;

    /** Zərləri göstərir; istifadə olunanlar solğun görünür */
    function renderDice(rolled, remaining, opening) {
        if (!rolled.length) { els.dice.innerHTML = ''; return; }
        if (opening) {
            els.dice.innerHTML = `<span class="nb-die-label">Siz</span>${dieHtml(rolled[0], false)}${dieHtml(rolled[1], false, 'ai')}<span class="nb-die-label">${esc(OPP_NAME)}</span>`;
            return;
        }
        // Cüt düşəndə də iki zər göstərilir; neçə gediş qaldığı yanında yazılır
        if (rolled[0] === rolled[1]) {
            const left = remaining.length;
            els.dice.innerHTML = dieHtml(rolled[0], left === 0, '') + dieHtml(rolled[1], left === 0, '') +
                (left ? `<span class="nb-moves-left" title="Qalan gediş">×${left}</span>` : '');
            return;
        }
        const left = remaining.slice();
        // hansı zər hələ oynanmayıb
        const flags = rolled.map(v => { const i = left.indexOf(v); if (i >= 0) { left.splice(i, 1); return false; } return true; });
        els.dice.innerHTML = rolled.map((v, i) => dieHtml(v, flags[i], '')).join('');
    }

    async function animateDice(final) {
        for (let i = 0; i < 7; i++) {
            els.dice.innerHTML = dieHtml(rollDie(), false, 'spin') + dieHtml(rollDie(), false, 'spin');
            await sleep(70);
        }
        renderDice(final, final[0] === final[1] ? [final[0], final[0], final[0], final[0]] : final, false);
        await sleep(250);
    }

    function setStatus(html) { els.status.innerHTML = html; }
    const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    /* ================= Klik ================= */

    function onBoardClick(e) {
        const el = e.target.closest('.nb-pt');
        if (!el || game.turn !== HUMAN || game.busy || game.over || !game.ctx) return;
        const b = +el.dataset.b;

        if (game.selected !== null && game.legal.some(m => m.from === game.selected && m.to === b)) {
            onTarget(b);
            return;
        }
        if (game.legal.some(m => m.from === b)) {
            game.selected = game.selected === b ? null : b;
            render();
        } else if (game.s.pts[b].o === HUMAN) {
            setStatus('Bu daşla gediş mümkün deyil — işıqlanan daşlardan birini seçin.');
        }
    }

    function onTarget(to) {
        if (game.selected === null || game.turn !== HUMAN || game.busy) return;
        // eyni yerə bir neçə zərlə getmək olursa (çıxarmada), kiçik zəri işlədirik
        const options = game.legal.filter(m => m.from === game.selected && m.to === to).sort((a, b) => a.die - b.die);
        if (options.length) humanMove(options[0]);
    }

    els.roll.addEventListener('click', humanRoll);
    els.undo.addEventListener('click', undo);
    els.restart.addEventListener('click', () => {
        if (MP) return mpResign();
        if (game && !game.over && game.history.length + game.s.off[0] > 0 && !confirm('Yeni oyuna başlansın? Bu oyun itəcək.')) return;
        startGame();
    });
    root.querySelector('.nb-again')?.addEventListener('click', startGame);

    /* ================= İki nəfərlik oyun (dəvətlə) ================= */

    async function mpRequest(url, body) {
        const opts = {credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}};
        if (body !== undefined) {
            opts.method = 'POST';
            opts.headers['Content-Type'] = 'application/json';
            opts.headers['X-CSRF-TOKEN'] = cfg.csrf;
            opts.body = JSON.stringify(body);
        }
        const r = await fetch(url, opts);
        const d = await r.json().catch(() => null);
        if (d && d.version !== undefined) mpApply(d, true);
        if (!r.ok && d && d.message) setStatus(esc(d.message));
        return d;
    }

    /** Serverdən gələn vəziyyəti lövhəyə köçürür */
    function mpApply(d, force) {
        root.querySelector('.nb-player.ai')?.classList.toggle('off-line', !d.oppOnline);
        if (!force && d.version === MP.version) return;
        MP.version = d.version;

        game.s = {pts: d.pts.map(([o, n]) => ({o, n})), off: d.off};
        game.over = !!d.result;
        game.turn = d.result ? null : (d.turn === 0 ? HUMAN : AI);
        game.ctx = d.turn === 0 && d.rolled.length && !d.result ? {dice: d.dice} : null;
        game.legal = d.legal.map(([from, to, die]) => ({from, to, die}));
        if (!game.legal.some(m => m.from === game.selected)) game.selected = null;
        game.history = [];
        // rəqibin son gedişləri işıqlanır (mənim növbəm başlayanda) və ya bu növbədəki gedişlər
        const marks = d.turn === 0 && !d.rolled.length ? d.last : d.turnMoves;
        game.lastMarks = new Set((marks || []).flat().filter(b => b !== 'off'));

        const untouched = d.off[0] + d.off[1] === 0 && d.pts[0][1] === 15 && d.pts[12][1] === 15;
        if (d.rolled.length) renderDice(d.rolled, d.dice, false);
        else if (d.skip) renderDice(d.skip.rolled, [], false);
        else if (untouched && d.opening) renderDice(d.opening, [], true);
        else renderDice([], [], false);

        const opp = esc(OPP_NAME);
        if (d.result) {
            mpResult(d.result);
        } else if (d.turn === 0) {
            if (d.rolled.length) setStatus('Daş seçin, sonra işıqlanan xanaya basın.');
            else if (d.skip && d.skip.who === 'opp') setStatus(`${opp} ${d.skip.rolled.join('-')} atdı, gediş yox idi. Sizin növbəniz — <b>zər atın</b>.`);
            else if (untouched) setStatus(`Kim başlayır? Siz: <b>${d.opening[0]}</b> · ${opp}: <b>${d.opening[1]}</b> — siz başlayırsınız, <b>zər atın</b>.`);
            else setStatus('Sizin növbəniz — <b>zər atın</b>.');
        } else {
            if (d.skip && d.skip.who === 'me') setStatus(`${d.skip.rolled.join('-')} zərinizlə gediş yox idi — növbə ${opp}-ə keçdi.`);
            else if (untouched && !d.rolled.length) setStatus(`Kim başlayır? Siz: <b>${d.opening[0]}</b> · ${opp}: <b>${d.opening[1]}</b> — ${opp} başlayır.`);
            else setStatus(d.rolled.length ? `${opp} gedir…` : `${opp} zər atır…`);
        }
        render();
    }

    async function mpRoll() {
        game.busy = true;
        render();
        const spin = setInterval(() => { els.dice.innerHTML = dieHtml(rollDie(), false, 'spin') + dieHtml(rollDie(), false, 'spin'); }, 70);
        try {
            await Promise.all([mpRequest(MP.rollUrl, {}), sleep(450)]);
        } finally {
            clearInterval(spin);
            game.busy = false;
            mpRequest(MP.stateUrl); // zər animasiyası üstünə yazmasın deyə vəziyyəti yenidən çəkirik
        }
    }

    async function mpMove(m) {
        game.busy = true;
        game.selected = null;
        render();
        try {
            await mpRequest(MP.moveUrl, {from: m.from, to: m.to, die: m.die});
        } finally {
            game.busy = false;
            render();
        }
    }

    async function mpResign() {
        if (game.over || !confirm('Təslim olursunuz? Qələbə rəqibə veriləcək.')) return;
        await mpRequest(MP.resignUrl, {});
    }

    function mpResult(res) {
        if (MP.finished) return;
        MP.finished = true;
        clearInterval(MP.timer);
        const box = els.result;
        const opp = esc(OPP_NAME);
        let title, note;
        if (res.reason === 'left') {
            title = res.won ? 'Qalib gəldiniz! 🎉' : 'Oyun bitdi';
            note = res.won ? `${opp} oyundan çıxdı.` : 'Uzun müddət səhifədə olmadığınız üçün oyun bitdi.';
        } else if (res.reason === 'resigned') {
            title = res.won ? 'Qalib gəldiniz! 🎉' : 'Təslim oldunuz';
            note = res.won ? `${opp} təslim oldu.` : `Qələbə ${opp}-ə verildi.`;
        } else {
            title = res.won ? (res.mars ? 'Mars! Təbriklər! 🎉' : 'Qalib gəldiniz! 🎉') : (res.mars ? 'Mars oldunuz…' : `${opp} qalib gəldi`);
            note = res.won ? 'Möhtəşəm oyun idi!' : 'Növbəti dəfə şansınız gətirəcək.';
            if (res.won) {
                note += res.points
                    ? ` <b>+${res.points} xal</b> günün cədvəlinə yazıldı.`
                    : ' Bu gün bu oyunçu ilə xal limiti dolub — bu qələbə xal vermədi.';
            }
        }
        if (res.reason !== 'finished' && res.won) note += ' <small>(təslim olma və ya çıxma ilə qələbə xal vermir)</small>';
        box.querySelector('.nb-result-title').innerHTML = title;
        box.querySelector('.nb-result-note').innerHTML = note;
        box.hidden = false;
        setStatus(title);
        els.restart.disabled = true;
    }

    function mpStart() {
        game = {s: newState(), turn: null, ctx: null, legal: [], selected: null, history: [], firstTurn: [true, true], rolled: [], over: false, busy: false};
        render();
        setStatus('Oyun yüklənir…');
        const poll = () => { if (!game.busy) mpRequest(MP.stateUrl).catch(() => {}); };
        poll();
        MP.timer = setInterval(poll, 1500);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    }

    /* ================= Dostla oyna: onlayn oyunçular və dəvət ================= */

    function setupFriends() {
        const panel = document.getElementById('nardFriends');
        if (!panel) return;
        const list = panel.querySelector('.nard-players');
        const empty = panel.querySelector('.np-empty');
        const wait = panel.querySelector('.np-wait');
        let pendingId = null;

        const post = (url, body) => fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf},
            body: JSON.stringify(body || {}),
        }).then(async r => ({ok: r.ok, data: await r.json().catch(() => ({}))}));

        function showWait(html, cancellable) {
            wait.innerHTML = html + (cancellable ? ' <a href="#" class="np-cancel">Ləğv et</a>' : '');
            wait.hidden = !html;
        }

        async function load() {
            if (document.hidden) return;
            try {
                const r = await fetch(panel.dataset.playersUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
                if (!r.ok) return;
                const d = await r.json();
                empty.hidden = d.players.length > 0;
                list.innerHTML = d.players.map(p => `<li>
                    <span class="np-name"><span class="online-dot"></span>${esc(p.name)}</span>
                    ${p.busy ? '<span class="np-busy">oyundadır</span>'
                        : `<button type="button" class="btn btn-primary np-invite" data-id="${p.id}" data-name="${esc(p.name)}">Dəvət et</button>`}
                </li>`).join('');
            } catch (e) { /* növbəti dəfə */ }
        }

        panel.addEventListener('click', async e => {
            const btn = e.target.closest('.np-invite');
            if (btn) {
                btn.disabled = true;
                const r = await post(panel.dataset.inviteUrl, {user_id: +btn.dataset.id});
                btn.disabled = false;
                if (r.ok) {
                    pendingId = r.data.invite.id;
                    showWait(`<b>${esc(r.data.invite.to)}</b> dəvət olundu — cavab gözlənilir…`, true);
                    window.nardInboxCheck && window.nardInboxCheck();
                } else {
                    showWait(esc(r.data.message || 'Dəvət göndərilmədi.'), false);
                }
                return;
            }
            if (e.target.closest('.np-cancel')) {
                e.preventDefault();
                if (pendingId) post(panel.dataset.cancelUrl.replace('__ID__', pendingId));
                pendingId = null;
                showWait('', false);
            }
        });

        // göndərdiyim dəvətin taleyi (saytın ümumi dəvət yoxlaması xəbər verir)
        document.addEventListener('nard:inbox', e => {
            const out = e.detail.outgoing;
            if (!out || out.id !== pendingId) return;
            if (out.status === 'declined') { showWait(`<b>${esc(out.to)}</b> dəvəti rədd etdi.`, false); pendingId = null; }
            else if (out.status === 'expired') { showWait('Dəvətin vaxtı keçdi — cavab gəlmədi.', false); pendingId = null; }
            else if (out.status === 'cancelled') { showWait('', false); pendingId = null; }
        });

        load();
        setInterval(load, 8000);
    }

    /* ================= Böyük ekran ================= */
    // Lövhənin qatı (nard-stage) bütün ekranı tutur. Fullscreen API olmayan brauzerlərdə
    // (məs. iPhone Safari) qat CSS ilə ekranı örtür. Lövhənin eni ekranın hündürlüyünə görə seçilir ki, sığsın.
    function setupFullscreen() {
        const stage = root.parentElement;
        const btn = root.querySelector('.nb-full');
        if (!stage || !btn) return;
        const nativeFull = () => document.fullscreenElement || document.webkitFullscreenElement;

        // böyük ekranda lövhə ekranın bütün enini və hündürlüyünü tutur (CSS: .nard-stage.is-full),
        // telefonu çevirəndə də ekrana uyğunlaşır
        function setFull(on) {
            stage.classList.toggle('is-full', on);
            document.body.classList.toggle('st-no-scroll', on);
            btn.querySelector('i').className = on ? 'fa fa-compress' : 'fa fa-expand';
            btn.title = on ? 'Kiçik ekran' : 'Böyük ekran';
            btn.setAttribute('aria-label', btn.title);
        }

        btn.addEventListener('click', () => {
            const on = !stage.classList.contains('is-full');
            if (on) {
                setFull(true); // nisbəti ölçmək üçün əvvəlcə adi ölçüdə ölçülür, sonra qat açılır
                const req = stage.requestFullscreen || stage.webkitRequestFullscreen;
                if (req) { const p = req.call(stage); if (p && p.catch) p.catch(() => {}); }
            } else {
                if (nativeFull()) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                setFull(false);
            }
            btn.blur();
        });
        const onChange = () => { if (!nativeFull() && stage.classList.contains('is-full')) setFull(false); };
        document.addEventListener('fullscreenchange', onChange);
        document.addEventListener('webkitfullscreenchange', onChange);
        window.addEventListener('keydown', e => {
            if (e.key === 'Escape' && stage.classList.contains('is-full') && !nativeFull()) setFull(false);
        });
    }

    buildBoard();
    setupFullscreen();
    if (MP) {
        mpStart();
    } else {
        startGame();
        setupFriends();
    }

    // Testlər üçün qaydalar mühərriki
    window.__nard = {newState, legalMoves, makeCtx: (pl, a, b) => ({pl, dice: a === b ? [a, a, a, a] : [a, b], headUsed: 0, headLimit: 1}), toBoard, toPath, hasIllegalPrime, bestSequence, apply, game: () => game};
})();
