/*
 * Masa rezervasiyası: mərtəbə planında masaya toxunanda pəncərə açılır —
 * tarix, saat, ad-soyad və nömrə yazılıb göndərilir.
 * Seçilmiş günün tutulmuş saatları serverdən gəlir (şəxsi məlumatsız) — həmin saatlar seçilə bilmir.
 */
(function () {
    'use strict';

    const root = document.getElementById('booking');
    if (!root) return;
    const cfg = JSON.parse(root.dataset.booking);
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const gap = cfg.hours * 60;
    const $ = (id) => document.getElementById(id);

    const day = $('rvDay');
    const modalEl = $('rvModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = $('rvForm'), dateIn = $('rvDate'), timeIn = $('rvTime'), err = $('rvError'), taken = $('rvTaken');
    const busyCache = {};
    let table = null;

    const mins = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
    const fmtDate = (d) => d.split('-').reverse().join('.');

    async function busyFor(date) {
        if (busyCache[date]) return busyCache[date];
        const r = await fetch(`${cfg.busyUrl}?date=${encodeURIComponent(date)}`, {headers: {Accept: 'application/json'}});
        if (!r.ok) return {};
        busyCache[date] = (await r.json()).busy || {};
        return busyCache[date];
    }

    /* ---------- Plan: seçilmiş gündə rezervi olan masalar ---------- */
    async function paintPlan() {
        const busy = await busyFor(day.value).catch(() => ({}));
        document.querySelectorAll('.rv-table').forEach(btn => {
            const times = busy[btn.dataset.table] || [];
            const tag = btn.querySelector('.rv-table-busy');
            btn.classList.toggle('is-busy', times.length > 0);
            tag.hidden = !times.length;
            tag.textContent = times.join(', ');
        });
    }

    document.querySelectorAll('.rv-floor-tab').forEach(tab => tab.addEventListener('click', () => {
        document.querySelectorAll('.rv-floor-tab').forEach(t => {
            const on = t === tab;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', on);
            $(`floor-${t.dataset.floor}`).hidden = !on;
        });
    }));
    day.addEventListener('change', () => { if (day.value) paintPlan(); });

    /* ---------- Pəncərə ---------- */
    async function fillTimes(want) {
        if (!timeIn) return;
        const date = dateIn.value;
        const busy = date ? (await busyFor(date).catch(() => ({})))[table] || [] : [];
        const prev = want || timeIn.value;
        timeIn.innerHTML = '';
        let firstFree = null;
        cfg.slots.forEach(s => {
            const past = date === cfg.today && s <= cfg.now;
            const clash = busy.some(b => Math.abs(mins(b) - mins(s)) < gap);
            const o = new Option(s + (clash ? ' — tutulub' : ''), s);
            o.disabled = past || clash;
            if (past) o.hidden = true;
            timeIn.add(o);
            if (!o.disabled && !firstFree) firstFree = s;
        });
        const keep = [...timeIn.options].find(o => o.value === prev && !o.disabled);
        timeIn.value = keep ? prev : (firstFree || '');
        if (!firstFree) {
            timeIn.add(new Option('Boş saat yoxdur', '', true, true));
            timeIn.options[timeIn.options.length - 1].disabled = true;
        }
        $('rvTakenRow').hidden = !busy.length;
        taken.innerHTML = busy.length
            ? `<i class="fa fa-clock me-1"></i>${fmtDate(date)} bu masa <b>${busy.join(', ')}</b> üçün rezerv olunub.`
            : '';
    }

    /* ---------- Telefon: yalnız rəqəm, "050 123 45 67" formatında ---------- */
    const OPERATORS = ['10', '50', '51', '55', '60', '70', '77', '99'];
    function formatPhone(value) {
        let d = String(value).replace(/\D/g, '');
        if (d.startsWith('994')) d = '0' + d.slice(3);       // +994 50 ... → 050 ...
        else if (d && d[0] !== '0') d = '0' + d;             // 50 ... → 050 ...
        d = d.slice(0, 10);
        return [d.slice(0, 3), d.slice(3, 6), d.slice(6, 8), d.slice(8, 10)].filter(Boolean).join(' ');
    }
    const phoneOk = (v) => {
        const d = v.replace(/\D/g, '');
        return d.length === 10 && OPERATORS.includes(d.slice(1, 3));
    };
    const phoneIn = $('rvPhone');
    phoneIn.value = formatPhone(phoneIn.value);
    phoneIn.addEventListener('input', () => {
        const atEnd = phoneIn.selectionStart === phoneIn.value.length;
        phoneIn.value = formatPhone(phoneIn.value);
        if (atEnd) phoneIn.setSelectionRange(phoneIn.value.length, phoneIn.value.length);
        phoneIn.classList.remove('is-invalid');
    });

    /* ---------- Yarımçıq rezervasiya: girişdən əvvəl yazılanlar yadda qalır ---------- */
    const PENDING = 'rvPending';
    const savePending = (d) => { try { localStorage.setItem(PENDING, JSON.stringify({...d, at: Date.now()})); } catch (_) {} };
    const takePending = () => {
        try {
            const d = JSON.parse(localStorage.getItem(PENDING) || 'null');
            localStorage.removeItem(PENDING);
            return d && Date.now() - d.at < 2 * 3600 * 1000 && cfg.tables[d.table] ? d : null; // 2 saatdan köhnəsi atılır
        } catch (_) { return null; }
    };
    const gate = $('rvGate'), fields = $('rvFields');
    const showGate = (on) => { if (gate) gate.hidden = !on; fields.hidden = on; };
    $('rvGateBack')?.addEventListener('click', () => showGate(false));

    function open(btn, pending) {
        table = btn.dataset.table;
        const t = cfg.tables[table];
        $('rvTable').value = table;
        $('rvTitle').textContent = t.label;
        $('rvFloor').textContent = t.floor;
        $('rvKind').innerHTML = t.kind === 'cabin' ? '' : `<i class="fa ${t.icon} me-1"></i>${t.kindName}`;
        $('rvKind').hidden = t.kind === 'cabin';
        modalEl.querySelector('.rv-modal-ico i').className = `fa ${t.icon}`;
        dateIn.value = pending?.date || day.value || cfg.today;
        if (pending) {
            if (pending.name) $('rvName').value = pending.name;
            if (pending.phone) $('rvPhone').value = formatPhone(pending.phone);
        }
        $('rvResume').hidden = !pending;
        err.hidden = true;
        form.hidden = false;
        showGate(false);
        $('rvDone').hidden = true;
        form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
        fillTimes(pending?.time);
        modal.show();
    }

    document.querySelectorAll('.rv-table').forEach(btn => btn.addEventListener('click', () => open(btn)));
    dateIn.addEventListener('change', () => fillTimes());
    modalEl.addEventListener('shown.bs.modal', () => {
        const empty = [...form.querySelectorAll('input[type=text], input[type=tel]')].find(i => !i.value);
        ($('rvResume').hidden ? (empty || timeIn) : $('rvSubmit')).focus();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        err.hidden = true;
        form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));

        const missing = [...form.querySelectorAll('[required]')].filter(i => !i.value.trim());
        if (missing.length) {
            missing.forEach(i => i.classList.add('is-invalid'));
            err.textContent = 'Bütün xanaları doldurun.';
            err.hidden = false;
            missing[0].focus();
            return;
        }
        if (!phoneOk(phoneIn.value)) {
            phoneIn.classList.add('is-invalid');
            err.textContent = 'Nömrəni tam yazın, məs. 050 123 45 67 (operator kodu: 050, 051, 055, 070, 077, 099, 010, 060).';
            err.hidden = false;
            phoneIn.focus();
            return;
        }

        // daxil olmayan / təsdiqlənməmiş: məlumatı yadda saxla, giriş xəbərdarlığını göstər
        if (cfg.access !== 'ok') {
            savePending({table, date: dateIn.value, time: timeIn.value, name: $('rvName').value.trim(), phone: $('rvPhone').value.trim()});
            showGate(true);
            gate.querySelector('a')?.focus();
            return;
        }

        const btn = $('rvSubmit');
        btn.disabled = true;
        try {
            const r = await fetch(cfg.storeUrl, {
                method: 'POST',
                headers: {Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest'},
                body: new FormData(form),
            });
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.ok) {
                busyCache[dateIn.value] = data.busy || busyCache[dateIn.value];
                if (dateIn.value !== day.value) day.value = dateIn.value;
                paintPlan();
                $('rvDoneText').textContent = data.message;
                form.hidden = true;
                $('rvDone').hidden = false;
                return;
            }
            if (r.status === 429) {
                err.textContent = 'Çox tez-tez cəhd etdiniz. Bir dəqiqə sonra yenidən yoxlayın.';
            } else if (data.errors) {
                const fields = {date: 'rvDate', time: 'rvTime', name: 'rvName', phone: 'rvPhone'};
                Object.keys(data.errors).forEach(k => fields[k] && $(fields[k]).classList.add('is-invalid'));
                err.textContent = Object.values(data.errors).flat()[0];
                if (data.errors.time) { delete busyCache[dateIn.value]; fillTimes(); paintPlan(); }
            } else {
                err.textContent = 'Xəta baş verdi. Yenidən yoxlayın və ya bizə zəng edin.';
            }
            err.hidden = false;
        } catch (_) {
            err.textContent = 'İnternet bağlantısını yoxlayın.';
            err.hidden = false;
        } finally {
            btn.disabled = false;
        }
    });

    paintPlan();

    // girişdən / təsdiqdən sonra qayıdanda yarımçıq rezervasiyanı aç
    if (cfg.access === 'ok') {
        const p = takePending();
        if (p) {
            const btn = document.querySelector(`.rv-table[data-table="${p.table}"]`);
            const floor = btn?.closest('.rv-plan-wrap')?.id.replace('floor-', '');
            document.querySelector(`.rv-floor-tab[data-floor="${floor}"]`)?.click();
            if (btn) open(btn, p);
        }
    }
})();
