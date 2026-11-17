/* =========================================================
   50 Qəpik — Krossvord (Azərbaycan sözləri)
   Şəbəkə serverdə qurulur (App\Services\Crossword); burada yalnız
   yazmaq, gəzmək, yoxlamaq və irəliləyişi yadda saxlamaq var.
   ========================================================= */
(function () {
    'use strict';

    const root = document.getElementById('crossword');
    if (!root) return;

    const P = JSON.parse(root.dataset.puzzle);
    const KEY = 'krossvord:' + root.dataset.key;
    const $ = s => root.querySelector(s);
    const gridEl = $('.cw-grid');

    // Azərbaycan dilində böyük hərf: i → İ, ı → I
    const up = s => s.toLocaleUpperCase('az');

    /* ---------- sözlər və xanalar ---------- */
    const words = [];
    ['across', 'down'].forEach(dir => P[dir].forEach(w => {
        const cells = [];
        for (let i = 0; i < w.len; i++) cells.push(dir === 'across' ? [w.r, w.c + i] : [w.r + i, w.c]);
        words.push({...w, dir, cells, id: dir + w.n});
    }));
    const cellWords = {}; // "r,c" → {across: word, down: word}
    words.forEach(w => w.cells.forEach(([r, c]) => { (cellWords[r + ',' + c] ||= {})[w.dir] = w; }));

    /* ---------- vəziyyət (brauzerdə yadda saxlanılır) ---------- */
    let st = {letters: {}, revealed: {}, time: 0, done: false};
    try { st = Object.assign(st, JSON.parse(localStorage.getItem(KEY) || '{}')); } catch (e) { /* gizli rejim */ }
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) { /* yer yoxdur */ } };

    let active = null;   // {r, c}
    let dir = 'across';

    /* ---------- şəbəkə ---------- */
    gridEl.style.setProperty('--cols', P.cols);
    gridEl.style.setProperty('--rows', P.rows);
    const inputs = {};
    for (let r = 0; r < P.rows; r++) {
        for (let c = 0; c < P.cols; c++) {
            const ch = P.grid[r][c];
            const cell = document.createElement('div');
            cell.className = 'cw-cell' + (ch ? '' : ' cw-block');
            if (ch) {
                const n = P.numbers[r][c];
                if (n) {
                    const num = document.createElement('span');
                    num.className = 'cw-num';
                    num.textContent = n;
                    cell.appendChild(num);
                }
                const inp = document.createElement('input');
                inp.type = 'text';
                inp.maxLength = 2;
                inp.autocomplete = 'off';
                inp.setAttribute('autocapitalize', 'characters');
                inp.setAttribute('autocorrect', 'off');
                inp.spellcheck = false;
                inp.setAttribute('aria-label', `Sətir ${r + 1}, sütun ${c + 1}`);
                inp.value = st.letters[r + ',' + c] || '';
                inp.dataset.r = r;
                inp.dataset.c = c;
                cell.appendChild(inp);
                inputs[r + ',' + c] = inp;
                if (st.revealed[r + ',' + c]) cell.classList.add('cw-revealed');
            }
            gridEl.appendChild(cell);
        }
    }

    /* ---------- izahlar ---------- */
    root.querySelectorAll('.cw-list').forEach(list => {
        words.filter(w => w.dir === list.dataset.dir).forEach(w => {
            const li = document.createElement('li');
            li.dataset.id = w.id;
            li.innerHTML = `<b>${w.n}.</b> <span></span> <small>(${w.len})</small>`;
            li.querySelector('span').textContent = w.clue;
            li.addEventListener('click', () => { dir = w.dir; focusCell(w.cells[0][0], w.cells[0][1]); });
            list.appendChild(li);
        });
    });

    /* ---------- köməkçilər ---------- */
    const k = (r, c) => r + ',' + c;
    const wordAt = (r, c, d) => (cellWords[k(r, c)] || {})[d];
    const currentWord = () => active && (wordAt(active.r, active.c, dir) || wordAt(active.r, active.c, dir === 'across' ? 'down' : 'across'));
    const value = (r, c) => (inputs[k(r, c)] ? inputs[k(r, c)].value : '');
    const wordDone = w => w.cells.every(([r, c]) => value(r, c) === P.grid[r][c]);

    function paint() {
        const w = currentWord();
        if (w) dir = w.dir;
        root.querySelectorAll('.cw-cell').forEach(el => el.classList.remove('cw-in-word', 'cw-active'));
        if (w) w.cells.forEach(([r, c]) => inputs[k(r, c)].parentNode.classList.add('cw-in-word'));
        if (active) inputs[k(active.r, active.c)].parentNode.classList.add('cw-active');
        root.querySelectorAll('.cw-list li').forEach(li => {
            const ww = words.find(x => x.id === li.dataset.id);
            li.classList.toggle('active', !!w && w.id === li.dataset.id);
            li.classList.toggle('solved', wordDone(ww));
        });
        $('.cw-current-n').textContent = w ? `${w.n} ${w.dir === 'across' ? '→' : '↓'}` : '';
        $('.cw-current-clue').textContent = w ? `${w.clue} (${w.len})` : 'Xanaya toxunun';
        $('.cw-filled').textContent = words.filter(wordDone).length;
        $('.cw-total').textContent = words.length;
    }

    function focusCell(r, c) {
        if (!inputs[k(r, c)]) return;
        active = {r, c};
        if (!wordAt(r, c, dir)) dir = dir === 'across' ? 'down' : 'across';
        inputs[k(r, c)].focus({preventScroll: true});
        paint();
    }

    function step(delta) {
        const w = currentWord();
        if (!w) return;
        const i = w.cells.findIndex(([r, c]) => r === active.r && c === active.c);
        const next = w.cells[i + delta];
        if (next) focusCell(next[0], next[1]);
    }

    function setLetter(r, c, ch) {
        const inp = inputs[k(r, c)];
        if (!inp || st.revealed[k(r, c)]) return; // açılmış hərf dəyişmir
        inp.value = ch;
        inp.parentNode.classList.remove('cw-bad');
        if (ch) st.letters[k(r, c)] = ch; else delete st.letters[k(r, c)];
        save();
    }

    function type(ch) {
        if (!active) return;
        setLetter(active.r, active.c, ch);
        step(1);
        paint();
        checkDone();
    }

    function backspace() {
        if (!active) return;
        if (value(active.r, active.c) && !st.revealed[k(active.r, active.c)]) setLetter(active.r, active.c, '');
        else { step(-1); setLetter(active.r, active.c, ''); }
        paint();
    }

    /* ---------- klaviatura və toxunuş ---------- */
    Object.values(inputs).forEach(inp => {
        const r = +inp.dataset.r, c = +inp.dataset.c;
        // Eyni xanaya ikinci toxunuş istiqaməti dəyişir — yalnız xana artıq fokusdadırsa.
        // (Əvvəl sarı, amma fokussuz xanaya toxunanda fokus qadağan olunurdu və hərf yazılmırdı.)
        let wasFocused = false;
        inp.addEventListener('pointerdown', () => { wasFocused = document.activeElement === inp; });
        inp.addEventListener('click', () => {
            if (wasFocused && active && active.r === r && active.c === c && wordAt(r, c, dir === 'across' ? 'down' : 'across')) {
                dir = dir === 'across' ? 'down' : 'across';
                paint();
            }
            wasFocused = false;
        });
        inp.addEventListener('focus', () => { if (!active || active.r !== r || active.c !== c) { active = {r, c}; paint(); } });

        // xanaya yazılanı oxuyub öz vəziyyətimizə köçürürük: son hərfi götürürük
        const take = () => {
            const raw = up(inp.value.replace(/[^A-Za-zƏəĞğIıİiÖöÜüŞşÇç]/g, ''));
            const ch = raw.slice(-1);
            inp.value = st.letters[k(r, c)] || '';
            if (ch) { active = {r, c}; type(ch); }
        };
        // telefon klaviaturaları (Android) hərfi "kompozisiya" ilə yazır — bitənə qədər gözləyirik
        let composing = false;
        inp.addEventListener('compositionstart', () => { composing = true; });
        inp.addEventListener('compositionend', () => { composing = false; take(); });
        inp.addEventListener('input', e => { if (!composing && !e.isComposing) take(); });
        inp.addEventListener('keydown', e => {
            const moves = {ArrowRight: [0, 1, 'across'], ArrowLeft: [0, -1, 'across'], ArrowDown: [1, 0, 'down'], ArrowUp: [-1, 0, 'down']};
            if (moves[e.key]) {
                e.preventDefault();
                const [dr, dc, d] = moves[e.key];
                if (dir !== d && wordAt(r, c, d)) { dir = d; paint(); return; }
                let rr = r + dr, cc = c + dc;
                while (rr >= 0 && cc >= 0 && rr < P.rows && cc < P.cols && !inputs[k(rr, cc)]) { rr += dr; cc += dc; }
                if (inputs[k(rr, cc)]) focusCell(rr, cc);
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                backspace();
            } else if (e.key === 'Tab' || e.key === 'Enter') {
                e.preventDefault();
                const list = words;
                const w = currentWord();
                const i = list.indexOf(w);
                const nw = list[(i + (e.shiftKey ? list.length - 1 : 1)) % list.length];
                dir = nw.dir;
                focusCell(nw.cells[0][0], nw.cells[0][1]);
            }
        });
    });

    root.querySelectorAll('.cw-letters button').forEach(b => {
        b.addEventListener('mousedown', e => e.preventDefault()); // xananın fokusu itməsin
        b.addEventListener('click', () => { if (b.dataset.letter) type(b.dataset.letter); else backspace(); });
    });

    /* ---------- düymələr ---------- */
    // düymələr xananın fokusunu almasın — basandan sonra yazmağa davam etmək olsun
    const refocus = () => { if (active) inputs[k(active.r, active.c)].focus({preventScroll: true}); };
    root.querySelectorAll('.cw-actions button').forEach(b => {
        b.addEventListener('mousedown', e => e.preventDefault());
        b.addEventListener('click', refocus);
    });

    $('.cw-check').addEventListener('click', () => {
        let wrong = 0;
        Object.entries(inputs).forEach(([key, inp]) => {
            const [r, c] = key.split(',').map(Number);
            const bad = inp.value && inp.value !== P.grid[r][c];
            inp.parentNode.classList.toggle('cw-bad', !!bad);
            if (bad) wrong++;
        });
        flash(wrong ? `${wrong} səhv hərf var` : 'Səhv yoxdur 👍');
    });

    function reveal(cells) {
        cells.forEach(([r, c]) => {
            const key = k(r, c);
            if (value(r, c) === P.grid[r][c]) return;
            st.revealed[key] = true;
            st.letters[key] = P.grid[r][c];
            inputs[key].value = P.grid[r][c];
            inputs[key].parentNode.classList.remove('cw-bad');
            inputs[key].parentNode.classList.add('cw-revealed');
        });
        save();
        paint();
        checkDone();
    }
    $('.cw-reveal-letter').addEventListener('click', () => { if (active) reveal([[active.r, active.c]]); });
    $('.cw-reveal-word').addEventListener('click', () => { const w = currentWord(); if (w) reveal(w.cells); });
    $('.cw-clear').addEventListener('click', () => {
        if (!confirm('Bütün yazdıqlarınız silinsin?')) return;
        st = {letters: {}, revealed: {}, time: 0, done: false};
        Object.values(inputs).forEach(inp => { inp.value = ''; inp.parentNode.classList.remove('cw-bad', 'cw-revealed'); });
        save();
        paint();
        refocus(); // təsdiq pəncərəsi fokusu aparır
    });

    // başqa krossvord: təsadüfi nömrə ilə məşq krossvordu
    root.querySelectorAll('.cw-new').forEach(a => a.addEventListener('click', e => {
        e.preventDefault();
        location.href = location.pathname + '?n=' + (1 + Math.floor(Math.random() * 2000000000));
    }));
    $('.cw-done-close').addEventListener('click', () => { $('.cw-done').hidden = true; });

    /* ---------- vaxt və bitmə ---------- */
    const fmt = s => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    setInterval(() => {
        if (st.done || document.hidden) return;
        st.time++;
        $('.cw-time').textContent = fmt(st.time);
        if (st.time % 5 === 0) save();
    }, 1000);
    $('.cw-time').textContent = fmt(st.time);

    function checkDone() {
        if (st.done || !words.every(wordDone)) return;
        st.done = true;
        save();
        const hints = Object.keys(st.revealed).length;
        $('.cw-done-note').textContent = `Vaxt: ${fmt(st.time)}` + (hints ? ` · açılan hərf: ${hints}` : ' · heç bir kömək olmadan! 🏆');
        $('.cw-done').hidden = false;
    }

    let flashT;
    function flash(text) {
        const el = $('.cw-current-clue');
        el.textContent = text;
        clearTimeout(flashT);
        flashT = setTimeout(paint, 1600);
    }

    paint();
    if (st.done) $('.cw-time').textContent = fmt(st.time) + ' ✓';
})();
