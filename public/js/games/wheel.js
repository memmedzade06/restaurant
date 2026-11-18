/*
 * Çarx oyunu — məharət oyunu. "Fırlat" basanda çarx dayanmadan fırlanır, "Dayan" basanda dayanır:
 * göstəricinin altındakı sektor düşür. Fırlanmanın parametrlərini server verir, nəticəni də server
 * həmin düsturla (GameController::runAngle) özü hesablayır — brauzer yalnız "neçə ms sonra basıldı" göndərir.
 */
(function () {
    'use strict';

    const root = document.getElementById('wheel');
    if (!root) return;

    const segments = JSON.parse(root.dataset.segments);
    const svg = root.querySelector('.wheel-svg');
    const btn = document.getElementById('wheelSpin');
    const resultBox = document.getElementById('wheelResult');
    const intro = document.getElementById('wheelIntro');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const verified = root.dataset.verified === '1';
    let closed = JSON.parse(root.dataset.closed || '[]');

    let rotation = 0;
    let busy = false;     // serverə sorğu gedir
    let run = null;       // fırlanan çarxın parametrləri
    let t0 = 0, raf = 0;

    const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    // Sektorlar: 0-cı sektorun ortası yuxarıdadır (göstəricinin altında), saat əqrəbi istiqamətində, eni "deg"
    function draw() {
        const R = 100;
        const pt = (deg, r = R) => {
            const rad = (deg - 90) * Math.PI / 180;
            return [r * Math.cos(rad), r * Math.sin(rad)];
        };
        let html = '<g class="wheel-rot">';
        let a = -segments[0].deg / 2;
        const edges = [];
        segments.forEach(s => {
            const a0 = a, a1 = a + s.deg, mid = a + s.deg / 2;
            a = a1;
            edges.push(a0);
            const shut = closed.includes(s.key);
            const [x0, y0] = pt(a0), [x1, y1] = pt(a1);
            const large = s.deg > 180 ? 1 : 0;
            html += s.deg >= 359.99
                ? `<circle r="${R}" fill="${esc(s.color)}"/>`
                : `<path d="M0 0 L${x0.toFixed(2)} ${y0.toFixed(2)} A${R} ${R} 0 ${large} 1 ${x1.toFixed(2)} ${y1.toFixed(2)} Z" fill="${shut ? '#9c928a' : esc(s.color)}" stroke="#fff" stroke-width="1.2"/>`;

            // yazı radius boyunca; dar sektorda qısa yazı və kiçik şrift
            const arc = 66 * s.deg * Math.PI / 180;          // sektorun yazı hissəsindəki eni
            let text = shut ? 'Bitdi' : s.label;
            let lines = [text];
            const words = text.split(' ');
            if (!shut && text.length > 12 && words.length > 1) {
                if (arc >= 22) {
                    const half = Math.ceil(words.length / 2);
                    lines = [words.slice(0, half).join(' '), words.slice(half).join(' ')];
                } else {
                    lines = [s.short || text];
                }
            } else if (!shut && arc < 14 && s.short) {
                lines = [s.short];
            }
            const fs = Math.min(lines.length > 1 ? 7.6 : 8.5, arc * 0.72).toFixed(2);
            const tspans = lines.length === 1
                ? esc(lines[0])
                : lines.map((l, k) => `<tspan x="0" dy="${k === 0 ? -5 : 10}">${esc(l)}</tspan>`).join('');
            html += `<text transform="rotate(${mid.toFixed(2)}) translate(0 -62) rotate(90)" text-anchor="middle" dominant-baseline="middle" class="wheel-label" style="font-size:${fs}px">${tspans}</text>`;
        });
        html += '<circle r="100" fill="none" stroke="#E9A23B" stroke-width="5"/>';
        // sektor sərhədlərində ağ nöqtələr — dayandırarkən nişan almağa kömək edir
        if (segments.length > 1) {
            edges.forEach(e => {
                const [x, y] = pt(e, 97);
                html += `<circle cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="2.3" fill="#fff"/>`;
            });
        }
        html += '</g>';
        svg.innerHTML = html;
        setRot(rotation);
    }

    function setRot(deg) {
        rotation = deg;
        const g = svg.querySelector('.wheel-rot');
        if (g) g.style.transform = `rotate(${deg}deg)`;
    }

    // GameController::runSpeed / runAngle ilə eyni düstur: sürət dalğalanır, dönüş anlarında
    // istiqamət hamar şəkildə dəyişir (yavaşlayır → dayanır → əks tərəfə fırlanır)
    const STEP = 5; // ms — GameController::RUN_STEP_MS
    function speedAt(r, ms) {
        const t = ms / 1000;
        let s = r.v * (1 + r.k * Math.sin(2 * Math.PI * t / r.period + r.phase)) * r.dir;
        const h = (r.flip_ms || 500) / 2;
        for (const f of r.flips || []) {
            if (ms >= f + h) s = -s;
            else if (ms > f - h) s *= Math.cos(Math.PI * (ms - f + h) / (2 * h));
        }
        return s;
    }
    // fırlanma başlayanda bütün yol bir dəfə hesablanır: cum[i] — i·STEP ms-ə qədər dönülən bucaq
    function prepare(r) {
        const n = Math.floor(r.max_ms / STEP) + 1;
        r.cum = new Float64Array(n + 1);
        r.spd = new Float64Array(n + 1);
        r.spd[0] = speedAt(r, 0);
        for (let i = 0; i < n; i++) {
            r.spd[i + 1] = speedAt(r, (i + 1) * STEP);
            r.cum[i + 1] = r.cum[i] + (r.spd[i] + r.spd[i + 1]) / 2 * STEP / 1000;
        }
    }
    function angleAt(r, ms) {
        const i = Math.min(Math.floor(ms / STEP), r.cum.length - 1);
        const travel = r.cum[i] + r.spd[i] * (ms - i * STEP) / 1000;
        return (((r.a0 + travel) % 360) + 360) % 360;
    }

    /* ---------- Vəziyyət: cəhdlər, son nəticə, götürülmüş hədiyyə ---------- */
    const attempts = +root.dataset.attempts || 3;
    // Qonaq üçün vəziyyət brauzerdə saxlanılır (sınaq), təsdiqlənmiş istifadəçi üçün serverdən gəlir
    let state = JSON.parse(root.dataset.state || 'null') || {attempts, attemptsLeft: attempts, last: null, claimed: null};
    const attemptsBox = document.getElementById('wheelAttempts');
    // əyləncə rejimi: son əyləncə fırlatmasının nəticəsi
    let funLast = null;
    let closedNote = '';

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        }).then(async r => ({ok: r.ok, status: r.status, data: await r.json().catch(() => ({}))}));
    }

    function render(animate) {
        // cəhd göstəricisi
        const used = state.attempts - state.attemptsLeft;
        const label = state.fun ? 'Əyləncə rejimi — limitsiz' : state.claimed ? 'Hədiyyə götürülüb' : `Qalan cəhd: <b>${state.attemptsLeft}</b> / ${state.attempts}`;
        attemptsBox.innerHTML = `<span class="attempts-label">${label}</span>` +
            `<span class="attempts-dots">${Array.from({length: state.attempts}, (_, i) => `<i class="${i < used || state.claimed ? 'used' : ''}"></i>`).join('')}</span>`;

        // nəticə paneli
        let html = '';
        const demoNote = verified ? '' : '<span class="demo-tag">Sınaq</span>';
        const again = 'Yenidən fırlat';
        // bu gün başqa oyunda (Hədiyyə Kranı) hədiyyə götürülüb — oynamaq olar, götürmək olmaz
        const warn = state.blockedBy
            ? `<div class="rules-note prize-block"><i class="fa fa-exclamation-triangle me-2"></i>${esc(state.blockedMessage)}</div>` : '';
        // əyləncə fırlatmasının nəticəsi — hədiyyəsiz
        const fun = state.fun
            ? `<div class="fun-result">${funLast
                ? `<span class="eyebrow">Əyləncə üçün</span><div><b>${esc(funLast.label)}</b> düşdü — bu gün hədiyyə verilmir.</div>`
                : (state.funReason === 'attempts'
                    ? 'Bu günün cəhdləri bitdi — çarxı əyləncə üçün istədiyiniz qədər fırlada bilərsiniz, amma hədiyyə verilmir.'
                    : 'Çarxı əyləncə üçün istədiyiniz qədər fırlada bilərsiniz — hədiyyə verilmir.')}</div>` : '';
        if (state.claimed) {
            html = `<span class="eyebrow">Hədiyyəniz 🎉</span><h3>${esc(state.claimed.label)}</h3>` +
                `<div class="prize-code">${esc(state.claimed.code)}</div>` +
                (state.claimed.claimed_at ? `<p class="claimed-at"><i class="fa fa-clock me-1"></i>Götürülüb: <b>${esc(state.claimed.claimed_at)}</b></p>` : '') +
                (state.claimed.expired
                    // kodun vaxtı bitib (bazadan da silinir) — bu gün yenidən fırlatmaq olmur
                    ? (state.claimed.used
                        ? '<p class="small text-muted mb-0">Kod restoranda istifadə olunub. Sabah yenə fırladın!</p>'
                        : `<p class="small text-muted mb-0">Kodun vaxtı <b>${esc(state.claimed.expires)}</b>-də bitib. Sabah yenə fırladın!</p>`)
                    : `<p class="small text-muted mb-0">Kodu ofisianta göstərin. ${esc(state.claimed.expires)} tarixinədək etibarlıdır. Kod <a href="/hediyyelerim">Hədiyyələrim</a> səhifəsində də saxlanılır.</p>`)
                + fun;
        } else if (state.last) {
            const left = state.attemptsLeft;
            if (state.last.prize && state.blockedBy) {
                html = `${warn}<span class="eyebrow">Sizə düşdü</span><h3>${esc(state.last.label)}</h3>` +
                    `<p class="small text-muted">Bu gün hədiyyəni başqa oyunda götürdüyünüz üçün bu hədiyyəni götürmək olmur.</p>` +
                    (left > 0
                        ? `<div class="choice"><button type="button" class="btn btn-primary" data-act="spin"><i class="fa fa-redo me-2"></i>${again}</button></div><p class="small text-muted mb-0">${left} cəhdiniz qalıb.</p>`
                        : '<p class="mb-0">Bu gün üçün cəhdləriniz bitdi. Sabah yenə gəlin!</p>');
            } else if (state.last.prize) {
                html = `${demoNote}<span class="eyebrow">Dəqiq dayandırdınız!</span><h3>${esc(state.last.label)}</h3>` +
                    `<div class="choice">` +
                    `<button type="button" class="btn btn-primary" data-act="claim"><i class="fa fa-gift me-2"></i>Hədiyyəni götür</button>` +
                    (left > 0 ? `<button type="button" class="btn btn-ghost-dark" data-act="spin"><i class="fa fa-redo me-2"></i>${again}</button>` : '') +
                    `</div>` +
                    `<p class="small text-muted mb-0">${left > 0
                        ? `Yenidən fırlatsanız, <b>${esc(state.last.label)}</b> itəcək, çarx isə daha sürətli fırlanacaq. ${left} cəhdiniz qalıb.`
                        : 'Bu son cəhdiniz idi — hədiyyəni götürün!'}</p>`;
            } else {
                html = `${demoNote}<span class="eyebrow">Bu dəfə alınmadı</span><h3>${esc(state.last.label)}!</h3>` + closedNote +
                    (left > 0
                        ? `<div class="choice"><button type="button" class="btn btn-primary" data-act="spin"><i class="fa fa-redo me-2"></i>${again}</button></div><p class="small text-muted mb-0">${left} cəhdiniz qalıb.</p>`
                        : '<p class="mb-0">Bu gün üçün cəhdləriniz bitdi. Sabah yenə gəlin!</p>');
                html = warn + html;
            }
        } else {
            html = warn + fun; // başqa oyunda hədiyyə götürülüb — xəbərdarlıq və əyləncə nəticəsi
        }
        // əyləncə rejimində (məs. cəhdlər bitib) əyləncə qeydi hər zaman görünsün
        if (state.fun && !html.includes('fun-result')) html += fun;
        resultBox.innerHTML = html;
        resultBox.hidden = !html;
        intro.hidden = !!(state.claimed || (state.last && verified));
        if (animate && html) {
            resultBox.classList.remove('pop');
            void resultBox.offsetWidth;
            resultBox.classList.add('pop');
        }
        resultBox.querySelectorAll('[data-act]').forEach(b => { b.disabled = busy || !!run; });

        // mərkəzdəki düymə: dayanıqda "Fırlat", fırlananda "Dayan"
        root.classList.toggle('running', !!run);
        if (run) {
            btn.disabled = busy;
            btn.textContent = 'Dayan';
        } else {
            btn.disabled = busy || (!state.fun && (!!state.claimed || state.attemptsLeft <= 0));
            btn.textContent = state.fun ? 'Fırlat' : state.claimed ? 'Hazır' : state.attemptsLeft <= 0 ? 'Bitdi' : 'Fırlat';
        }
    }

    async function claim() {
        if (busy || run) return;
        if (!verified) {
            resultBox.insertAdjacentHTML('beforeend', `<div class="alert-soft mt-3">Bu sınaq idi. Real hədiyyə götürmək üçün <a href="${esc(root.dataset.authUrl)}">qeydiyyatdan keçin</a>.</div>`);
            resultBox.querySelector('[data-act="claim"]')?.setAttribute('disabled', 'disabled');
            return;
        }
        busy = true;
        render();
        const r = await post(root.dataset.claimUrl).catch(() => null);
        busy = false;
        if (r && r.data.state) state = r.data.state;
        render(true);
        if (!r || !r.ok) resultBox.insertAdjacentHTML('beforeend', `<p class="small text-danger mt-2 mb-0">${esc(r?.data?.message || 'Bağlantı xətası, yenə cəhd edin.')}</p>`);
    }

    function fail(msg) {
        busy = false;
        run = null;
        cancelAnimationFrame(raf);
        render();
        resultBox.innerHTML = `<p class="mb-0">${esc(msg || 'Bağlantı xətası, bir az sonra yenə cəhd edin.')}</p>`;
        resultBox.hidden = false;
    }

    // "Fırlat": server fırlanmanın parametrlərini verir, çarx dayanmadan fırlanır
    async function start() {
        if (busy || run || (!state.fun && (state.claimed || state.attemptsLeft <= 0))) return;
        busy = true;
        render();

        let d;
        try {
            const r = await post(root.dataset.spinUrl);
            d = r.data;
            if (r.status === 429 && d.state) { state = d.state; busy = false; render(true); return; }
            if (!r.ok || !d.run) throw new Error(d.message || '');
        } catch (e) {
            fail(e.message);
            return;
        }

        busy = false;
        run = d.run;
        prepare(run);
        if (d.state && !d.fun) state = {...d.state, last: null};
        closedNote = '';
        resultBox.hidden = true;
        intro.hidden = false;
        t0 = performance.now();
        render();
        const tick = () => {
            if (!run) return;
            const ms = performance.now() - t0;
            if (ms >= run.max_ms) { stop(run.max_ms); return; } // basılmasa çarx özü dayanır — cəhd uğursuz
            setRot(angleAt(run, ms));
            // düymədə geri sayım: neçə saniyə qalıb
            const label = `Dayan ${Math.ceil((run.max_ms - ms) / 1000)}`;
            if (!busy && btn.textContent !== label) btn.textContent = label;
            raf = requestAnimationFrame(tick);
        };
        raf = requestAnimationFrame(tick);
        // vərəqə arxa plandadırsa kadrlar dayanır — vaxt bitəndə çarxı taymer də dayandırsın
        const mine = run;
        setTimeout(() => { if (run === mine) stop(run.max_ms); }, run.max_ms + 50);
        btn.focus({preventScroll: true});
    }

    // "Dayan": çarx basılan anda donur, server həmin anın nəticəsini hesablayır
    async function stop(force) {
        if (!run || busy) return;
        const ms = Math.round(Math.min(force ?? (performance.now() - t0), run.max_ms));
        cancelAnimationFrame(raf);
        setRot(angleAt(run, ms));
        root.classList.remove('stopped');
        void root.offsetWidth;
        root.classList.add('stopped');
        busy = true;
        render();

        let d;
        try {
            const r = await post(root.dataset.stopUrl, {ms});
            d = r.data;
            if (!r.ok) throw new Error(d.message || '');
        } catch (e) {
            fail(e.message);
            return;
        }

        busy = false;
        const run0 = run;
        run = null;
        setRot(d.angle); // serverin hesabladığı bucaq (eyni olmalıdır)
        closedNote = d.timeout
            ? `<p class="small text-muted">Çarxı ${Math.round(run0.max_ms / 1000)} saniyə ərzində dayandırmadınız — cəhd uğursuz sayıldı.</p>`
            : d.closed ? '<p class="small text-muted">Bu hədiyyənin bugünkü sayı bitib — sektor bağlıdır.</p>' : '';
        if (d.closed && !d.timeout && !closed.includes(segments[d.index].key)) { closed.push(segments[d.index].key); draw(); }
        if (d.fun) funLast = {label: d.label, prize: d.prize}; // əyləncə — hədiyyəsiz
        if (d.state) {
            state = d.state;
        } else { // qonaq: sınaq vəziyyətini brauzerdə yeniləyirik
            state = {...state, attemptsLeft: Math.max(0, state.attemptsLeft - 1), last: {label: d.label, prize: d.prize}};
        }
        render(true);
    }

    function press() {
        if (run) stop(); else start();
    }

    resultBox.addEventListener('click', e => {
        const act = e.target.closest('[data-act]')?.dataset.act;
        if (act === 'claim') claim();
        if (act === 'spin') start();
    });

    draw();
    btn.addEventListener('click', press);
    // telefonda çarxın istənilən yerinə toxunmaq da dayandırır
    svg.addEventListener('pointerdown', e => { if (run) { e.preventDefault(); stop(); } });
    // boşluq düyməsi ilə də dayandırmaq olar
    document.addEventListener('keydown', e => {
        // (fokus düymədədirsə, boşluğu düymənin özü "klik" kimi emal edir — ikiqat olmasın)
        if (e.code === 'Space' && run && !e.repeat && !e.target.closest('button, a, input, textarea')) { e.preventDefault(); stop(); }
    });
    render();
})();
