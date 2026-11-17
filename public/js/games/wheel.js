/*
 * Bəxt çarxı. Nəticəni server seçir; çarx sadəcə həmin sektora qədər fırlanır.
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
    const n = segments.length;
    const step = 360 / n;
    let rotation = 0;
    let spinning = false;

    const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    // Sektorları çəkirik: 0-cı sektor yuxarıda (göstəricinin altında), saat əqrəbi istiqamətində
    function draw() {
        const R = 100;
        const pt = deg => {
            const rad = (deg - 90) * Math.PI / 180;
            return [R * Math.cos(rad), R * Math.sin(rad)];
        };
        let html = '<g class="wheel-rot">';
        segments.forEach((s, i) => {
            const a0 = i * step - step / 2, a1 = a0 + step;
            const [x0, y0] = pt(a0), [x1, y1] = pt(a1);
            html += `<path d="M0 0 L${x0.toFixed(2)} ${y0.toFixed(2)} A${R} ${R} 0 0 1 ${x1.toFixed(2)} ${y1.toFixed(2)} Z" fill="${esc(s.color)}" stroke="#fff" stroke-width="1.2"/>`;
            const mid = i * step;
            // uzun yazı iki sətrə bölünür ki, sektora sığsın
            const words = s.label.split(' ');
            let lines = [s.label];
            if (s.label.length > 12 && words.length > 1) {
                const half = Math.ceil(words.length / 2);
                lines = [words.slice(0, half).join(' '), words.slice(half).join(' ')];
            }
            const tspans = lines.length === 1
                ? esc(lines[0])
                : lines.map((l, k) => `<tspan x="0" dy="${k === 0 ? -5 : 10}">${esc(l)}</tspan>`).join('');
            html += `<text transform="rotate(${mid}) translate(0 -60) rotate(90)" text-anchor="middle" dominant-baseline="middle" class="wheel-label${lines.length > 1 ? ' two' : ''}">${tspans}</text>`;
        });
        html += '<circle r="100" fill="none" stroke="#E9A23B" stroke-width="5"/>';
        for (let i = 0; i < n * 2; i++) {
            const [x, y] = pt(i * step / 2);
            html += `<circle cx="${(x * 0.97).toFixed(2)}" cy="${(y * 0.97).toFixed(2)}" r="2.3" fill="#fff"/>`;
        }
        html += '</g>';
        svg.innerHTML = html;
    }

    /* ---------- Vəziyyət: cəhdlər, son nəticə, götürülmüş hədiyyə ---------- */
    const attempts = +root.dataset.attempts || 3;
    // Qonaq üçün vəziyyət brauzerdə saxlanılır (sınaq), təsdiqlənmiş istifadəçi üçün serverdən gəlir
    let state = JSON.parse(root.dataset.state || 'null') || {attempts, attemptsLeft: attempts, last: null, claimed: null};
    const attemptsBox = document.getElementById('wheelAttempts');

    function post(url) {
        return fetch(url, {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}, credentials: 'same-origin'})
            .then(async r => ({ok: r.ok, status: r.status, data: await r.json().catch(() => ({}))}));
    }

    function render(animate) {
        // cəhd göstəricisi
        const used = state.attempts - state.attemptsLeft;
        attemptsBox.innerHTML = `<span class="attempts-label">${state.claimed ? 'Hədiyyə götürülüb' : `Qalan cəhd: <b>${state.attemptsLeft}</b> / ${state.attempts}`}</span>` +
            `<span class="attempts-dots">${Array.from({length: state.attempts}, (_, i) => `<i class="${i < used || state.claimed ? 'used' : ''}"></i>`).join('')}</span>`;

        // nəticə paneli
        let html = '';
        const demoNote = verified ? '' : '<span class="demo-tag">Sınaq</span>';
        if (state.claimed) {
            html = `<span class="eyebrow">Hədiyyəniz 🎉</span><h3>${esc(state.claimed.label)}</h3>` +
                `<div class="prize-code">${esc(state.claimed.code)}</div>` +
                (state.claimed.claimed_at ? `<p class="claimed-at"><i class="fa fa-clock me-1"></i>Götürülüb: <b>${esc(state.claimed.claimed_at)}</b></p>` : '') +
                (state.claimed.expired
                    // kodun vaxtı bitib (bazadan da silinir) — bu gün yenidən fırlatmaq olmur
                    ? `<p class="small text-muted mb-0">Kodun vaxtı <b>${esc(state.claimed.expires)}</b>-də bitib. Sabah yenə fırladın!</p>`
                    : `<p class="small text-muted mb-0">Kodu ofisianta göstərin. ${esc(state.claimed.expires)} tarixinədək etibarlıdır. Kod <a href="/hediyyelerim">Hədiyyələrim</a> səhifəsində də saxlanılır.</p>`);
        } else if (state.last) {
            const left = state.attemptsLeft;
            if (state.last.prize) {
                html = `${demoNote}<span class="eyebrow">Sizə düşdü</span><h3>${esc(state.last.label)}</h3>` +
                    `<div class="choice">` +
                    `<button type="button" class="btn btn-primary" data-act="claim"><i class="fa fa-gift me-2"></i>Hədiyyəni götür</button>` +
                    (left > 0 ? `<button type="button" class="btn btn-ghost-dark" data-act="spin"><i class="fa fa-redo me-2"></i>Yenidən fırlat</button>` : '') +
                    `</div>` +
                    `<p class="small text-muted mb-0">${left > 0
                        ? `Yenidən fırlatsanız, <b>${esc(state.last.label)}</b> itəcək. ${left} cəhdiniz qalıb.`
                        : 'Bu son cəhdiniz idi — hədiyyəni götürün!'}</p>`;
            } else {
                html = `${demoNote}<span class="eyebrow">Bu dəfə alınmadı</span><h3>${esc(state.last.label)}!</h3>` +
                    (left > 0
                        ? `<div class="choice"><button type="button" class="btn btn-primary" data-act="spin"><i class="fa fa-redo me-2"></i>Yenidən fırlat</button></div><p class="small text-muted mb-0">${left} cəhdiniz qalıb.</p>`
                        : '<p class="mb-0">Bu gün üçün cəhdləriniz bitdi. Sabah yenə gəlin!</p>');
            }
        }
        resultBox.innerHTML = html;
        resultBox.hidden = !html;
        intro.hidden = !!(state.claimed || (state.last && verified));
        if (animate && html) {
            resultBox.classList.remove('pop');
            void resultBox.offsetWidth;
            resultBox.classList.add('pop');
        }

        // mərkəzdəki düymə
        btn.disabled = spinning || !!state.claimed || state.attemptsLeft <= 0;
        btn.textContent = state.claimed ? 'Hazır' : state.attemptsLeft <= 0 ? 'Bitdi' : (state.last ? 'Yenə fırlat' : (verified ? 'Fırlat' : 'Sınaq fırlat'));
    }

    async function claim() {
        if (spinning) return;
        if (!verified) {
            resultBox.insertAdjacentHTML('beforeend', `<div class="alert-soft mt-3">Bu sınaq idi. Real hədiyyə götürmək üçün <a href="${esc(root.dataset.authUrl)}">qeydiyyatdan keçin</a>.</div>`);
            resultBox.querySelector('[data-act="claim"]')?.setAttribute('disabled', 'disabled');
            return;
        }
        spinning = true;
        render();
        const r = await post(root.dataset.claimUrl).catch(() => null);
        spinning = false;
        if (r && r.data.state) state = r.data.state;
        render(true);
        if (!r || !r.ok) resultBox.insertAdjacentHTML('beforeend', `<p class="small text-danger mt-2 mb-0">${esc(r?.data?.message || 'Bağlantı xətası, yenə cəhd edin.')}</p>`);
    }

    resultBox.addEventListener('click', e => {
        const act = e.target.closest('[data-act]')?.dataset.act;
        if (act === 'claim') claim();
        if (act === 'spin') spin();
    });

    async function spin() {
        if (spinning || state.claimed || state.attemptsLeft <= 0) return;
        spinning = true;
        render();

        let d;
        try {
            const r = await post(root.dataset.spinUrl);
            d = r.data;
            if (r.status === 429 && d.state) { state = d.state; spinning = false; render(true); return; }
            if (!r.ok) throw new Error(d.message || 'Xəta');
        } catch (e) {
            spinning = false;
            render();
            resultBox.innerHTML = '<p class="mb-0">Bağlantı xətası, bir az sonra yenə cəhd edin.</p>';
            resultBox.hidden = false;
            return;
        }

        // seçilmiş sektor göstəricinin (yuxarı) altında dayansın
        const jitter = (Math.random() - 0.5) * step * 0.6;
        const target = 360 - d.index * step + jitter;
        const current = ((rotation % 360) + 360) % 360;
        rotation += 360 * 6 + ((target - current + 360) % 360);

        const g = svg.querySelector('.wheel-rot');
        g.style.transition = 'transform 5.2s cubic-bezier(.12,.75,.15,1)';
        g.style.transform = `rotate(${rotation}deg)`;

        let finished = false;
        const done = () => {
            if (finished) return;
            finished = true;
            g.removeEventListener('transitionend', done);
            spinning = false;
            if (d.state) {
                state = d.state;
            } else { // qonaq: sınaq vəziyyətini brauzerdə yeniləyirik
                state = {...state, attemptsLeft: state.attemptsLeft - 1, last: {label: d.label, prize: d.prize}};
            }
            render(true);
        };
        g.addEventListener('transitionend', done);
        // brauzer animasiyanı göstərməsə belə nəticə görünsün
        setTimeout(done, 5600);
    }

    draw();
    btn.addEventListener('click', spin);
    render();
})();
