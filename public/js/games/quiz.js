/**
 * Bilik Yarışı (brauzer tərəfi). Oyun serverdə gedir: suallar bir-bir gəlir, düz cavabı
 * server yalnız cavab veriləndən sonra bildirir, vaxtı da server yoxlayır.
 * Burada yalnız ekran, taymer zolağı və düymələr var.
 */
(function () {
    const root = document.getElementById('quiz');
    if (!root) return;

    const cfg = JSON.parse(root.dataset.quiz);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const $ = (sel) => root.querySelector(sel);
    const screens = {
        intro: $('.qz-intro'), play: $('.qz-play'), stageDone: $('.qz-stage-done'), over: $('.qz-over'),
    };
    const RECORD_KEY = 'quiz_record_v1';

    let timer = null;
    let locked = true;     // cavab göndərilib / sual yüklənir — düymələr işləmir
    let total = 0;         // bu oyunda düz cavablar
    let passed = 0;        // keçilən mərhələlər

    /* ---------- Köməkçilər ---------- */

    /** 1 → "1-ci", 3 → "3-cü", 6 → "6-cı", 9 → "9-cu", 10 → "10-cu" */
    function ordinal(n) {
        const last = n % 10;
        const tens = Math.floor(n / 10) % 10;
        const byDigit = {1: 'ci', 2: 'ci', 3: 'cü', 4: 'cü', 5: 'ci', 6: 'cı', 7: 'ci', 8: 'ci', 9: 'cu'};
        const byTens = {1: 'cu', 2: 'ci', 3: 'cu', 4: 'cı', 5: 'ci', 6: 'cı', 7: 'ci', 8: 'ci', 9: 'cı', 0: 'cü'};
        return n + '-' + (last ? byDigit[last] : byTens[tens]);
    }

    /** 0.25 → "0,25" */
    const fmt = (p) => String(+(+p).toFixed(3)).replace('.', ',');

    function show(name) {
        Object.entries(screens).forEach(([k, el]) => { el.hidden = k !== name; });
    }

    async function post(url, body) {
        const r = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
            credentials: 'same-origin',
            body: JSON.stringify(body || {}),
        });
        const d = await r.json().catch(() => ({}));
        if (!r.ok) {
            const err = new Error(d.message || 'Xəta baş verdi. Yenidən cəhd edin.');
            err.needVerify = !!d.needVerify;
            err.status = r.status;
            throw err;
        }
        return d;
    }

    function failed(err) {
        stopTimer();
        if (err.needVerify) return askVerify(passed);
        // server bu oyunu artıq tanımır (vaxtı keçib, başqa vərəqdə yeni oyun başlanıb və s.) — oyun bitmiş sayılır
        if (err.status === 409) {
            $('.qz-over-title').textContent = 'Oyun başa çatdı';
            $('.qz-over-note').innerHTML = (total ? `Düz cavab: <b>${total}</b><br>` : '') + 'Yeni oyuna başlamaq üçün düyməni basın.';
            saveRecord();
            renderRecord();
            return show('over');
        }
        $('.qz-over-title').textContent = 'Bağlantı xətası';
        $('.qz-over-note').textContent = err.message;
        show('over');
    }

    function readRecord() {
        try { return JSON.parse(localStorage.getItem(RECORD_KEY)) || null; } catch (e) { return null; }
    }

    function saveRecord() {
        const old = readRecord();
        const better = total > 0 && (!old || total > old.correct);
        if (better) {
            try { localStorage.setItem(RECORD_KEY, JSON.stringify({stage: passed, correct: total})); } catch (e) { /* gizli rejim */ }
        }
        return better;
    }

    function renderRecord() {
        const rec = readRecord();
        const el = $('.qz-record');
        if (rec && rec.correct > 0) {
            el.innerHTML = `<i class="fa fa-medal me-1"></i>Sizin rekord: <b>${rec.correct} düz cavab</b>` +
                (rec.stage > 0 ? `, ${rec.stage} mərhələ` : '');
            el.hidden = false;
        }
    }

    /** Xal haqqında qeyd (mərhələ sonunda) */
    function pointsNote(bonus, left) {
        if (!cfg.canEarn) {
            return cfg.stagePoints > 0
                ? `<br><a href="${cfg.registerUrl}">Profilinizi təsdiqləyin</a> ki, mərhələləri keçəndə xal qazanasınız (1-ci mərhələ: <b>+${fmt(cfg.stagePoints)} xal</b>, sonra daha çox).`
                : '';
        }
        if (bonus > 0) return `<br><span class="qz-bonus">⭐ +${fmt(bonus)} xal balansınıza yazıldı!</span>`;
        if (left !== null && left <= 0) return '<br><small class="text-muted">Bu günün xal limiti bitib — sabah yenə qazanın.</small>';
        return '';
    }

    /* ---------- Oyun ---------- */

    async function newGame() {
        total = 0;
        passed = 0;
        locked = true;
        try {
            const d = await post(cfg.startUrl);
            renderQuestion(d.question);
        } catch (e) { failed(e); }
    }

    /** Davam etmək üçün profil təsdiqi lazımdır (server növbəti mərhələni bağlayıb) */
    function askVerify(stage) {
        $('.qz-stage-title').textContent = stage ? `${ordinal(stage)} mərhələ keçildi! 🎉` : 'Davam etmək üçün təsdiq lazımdır';
        $('.qz-stage-note').innerHTML = (stage ? `Cəmi düz cavab: <b>${total}</b>.<br>` : '') +
            (cfg.isGuest
                ? 'Növbəti mərhələlərə keçmək və xal qazanmaq üçün <b>qeydiyyatdan keçin</b> və profilinizi təsdiqləyin. Qayıdanda oyunu buradan davam etdirəcəksiniz.'
                : 'Növbəti mərhələlərə keçmək və xal qazanmaq üçün <b>profilinizi təsdiqləyin</b>. Qayıdanda oyunu buradan davam etdirəcəksiniz.');
        $('.qz-next-stage').hidden = true;
        $('.qz-verify').hidden = false;
        show('stageDone');
    }

    async function nextQuestion() {
        locked = true;
        try {
            const d = await post(cfg.nextUrl);
            renderQuestion(d.question);
        } catch (e) { failed(e); }
    }

    function renderLives(lives) {
        const el = $('.qz-lives');
        el.innerHTML = Array.from({length: cfg.lives}, (_, i) =>
            `<i class="fa fa-heart ${i < lives ? '' : 'is-lost'}"></i>`).join('');
        el.setAttribute('aria-label', `Qalan can: ${lives}`);
    }

    function renderQuestion(q) {
        show('play');
        $('.qz-stage').textContent = `${ordinal(q.stage)} mərhələ`;
        $('.qz-count').textContent = `Sual ${q.number} / ${q.total}`;
        renderLives(q.lives);
        $('.qz-question').textContent = q.text;
        const fb = $('.qz-feedback');
        fb.textContent = '';
        fb.className = 'qz-feedback';

        const box = $('.qz-options');
        box.innerHTML = '';
        q.options.forEach((text, i) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'qz-option';
            b.dataset.index = i;
            b.innerHTML = `<span class="qz-key">${i + 1}</span><span class="qz-text"></span>`;
            b.querySelector('.qz-text').textContent = text;
            b.addEventListener('click', () => answer(i));
            box.appendChild(b);
        });

        locked = false;
        startTimer();
    }

    /**
     * Taymer saata (Date.now) görə işləyir: vərəq arxa plana keçsə də vaxt dayanmır.
     * Vaxt bitəndə serverə "cavab yoxdur" göndərilir (server vaxtı özü də yoxlayır).
     */
    function startTimer() {
        stopTimer();
        const bar = $('.qz-timer-bar');
        const left = $('.qz-time-left');
        const totalMs = cfg.seconds * 1000;
        const deadline = Date.now() + totalMs;

        const tick = () => {
            const remain = Math.max(0, deadline - Date.now());
            bar.style.transform = `scaleX(${remain / totalMs})`;
            bar.classList.toggle('is-low', remain < totalMs * 0.35);
            left.textContent = Math.ceil(remain / 1000) + ' san.';
            if (remain <= 0) {
                stopTimer();
                answer(null); // vaxt bitdi
            }
        };
        tick();
        timer = setInterval(tick, 50);
    }

    function stopTimer() {
        if (timer) clearInterval(timer);
        timer = null;
    }

    /** choice = null — vaxt bitib */
    async function answer(choice) {
        if (locked) return;
        locked = true;
        stopTimer();

        const buttons = [...root.querySelectorAll('.qz-option')];
        buttons.forEach((b) => { b.disabled = true; });
        if (choice !== null) buttons[choice].classList.add('is-picked');

        let d;
        try {
            d = await post(cfg.answerUrl, {choice});
        } catch (e) { return failed(e); }

        buttons[d.right]?.classList.add('is-right');
        const fb = $('.qz-feedback');
        if (d.correct) {
            fb.textContent = 'Düzdür! 👏';
            fb.classList.add('is-ok');
        } else {
            if (choice !== null) buttons[choice].classList.add('is-wrong');
            fb.textContent = d.timeout ? '⏰ Vaxt bitdi!' : 'Səhv cavab.';
            fb.classList.add('is-bad');
        }
        renderLives(d.lives);
        total = d.totalCorrect;

        setTimeout(() => {
            if (d.gameOver) return finish(false);
            if (d.stageDone) {
                passed = d.stage;
                if (d.allDone) return finish(true, d);
                return stageComplete(d);
            }
            nextQuestion();
        }, d.correct ? 900 : 1600);
    }

    function stageComplete(d) {
        if (d.needVerify) return askVerify(d.stage);
        $('.qz-next-stage').hidden = false;
        $('.qz-verify').hidden = true;
        $('.qz-stage-title').textContent = `${ordinal(d.stage)} mərhələ keçildi! 🎉`;
        $('.qz-stage-note').innerHTML = `Cəmi düz cavab: <b>${total}</b>. Növbəti mərhələdə suallar daha çətindir, canlar yenilənir.` +
            pointsNote(d.bonus, d.pointsLeft);
        show('stageDone');
    }

    function finish(allDone, d) {
        stopTimer();
        const record = saveRecord();
        $('.qz-over-title').textContent = allDone ? 'Bütün sualları keçdiniz! 🏆' : 'Oyun bitdi';
        $('.qz-over-note').innerHTML =
            `Düz cavab: <b>${total}</b><br>Keçilən mərhələ: <b>${passed}</b>` +
            (record ? '<br><span class="qz-new-record">⭐ Yeni rekord!</span>' : '') +
            (d ? pointsNote(d.bonus, d.pointsLeft) : '');
        renderRecord();
        show('over');
    }

    /* ---------- Düymələr ---------- */

    $('.qz-start').addEventListener('click', newGame);
    $('.qz-again').addEventListener('click', newGame);
    $('.qz-next-stage').addEventListener('click', nextQuestion);
    // təsdiqdən sonra qayıdıb: yarımçıq oyunun növbəti mərhələsi
    $('.qz-resume')?.addEventListener('click', () => {
        passed = cfg.resumeStage - 1;
        nextQuestion();
    });

    // klaviatura: 1, 2, 3 — cavablar
    document.addEventListener('keydown', (e) => {
        if (screens.play.hidden || locked) return;
        const n = parseInt(e.key, 10);
        if (n >= 1 && n <= root.querySelectorAll('.qz-option').length) answer(n - 1);
    });

    renderRecord();
})();
