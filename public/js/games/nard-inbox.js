/*
 * Nərd dəvətləri — saytın bütün səhifələrində işləyir (yalnız təsdiqlənmiş istifadəçilər üçün).
 * Bir neçə saniyədən bir serverdən soruşur:
 *  - mənə gələn dəvətlər → küncdə "X sizi nərdə dəvət edir — Qəbul et / Rədd et";
 *  - göndərdiyim dəvət qəbul olunubsa → oyuna keçirik;
 *  - davam edən oyunum varsa və başqa səhifədəyəmsə → "Oyununuz davam edir — Qayıt".
 */
(function () {
    'use strict';

    const box = document.getElementById('nardInbox');
    if (!box) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const onMatchPage = !!document.querySelector('#nard[data-match-url]');
    const dismissed = new Set();      // rədd edilmiş/bağlanmış dəvətlər bir daha göstərilmir
    let redirected = false;

    const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    async function post(url) {
        const r = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest'},
        });
        return {ok: r.ok, data: await r.json().catch(() => ({}))};
    }

    function render(d) {
        let html = '';
        for (const inv of d.incoming || []) {
            if (dismissed.has(inv.id)) continue;
            html += `<div class="ni-card" data-id="${inv.id}">
                <div class="ni-text"><i class="fa fa-dice me-2"></i><b>${esc(inv.from)}</b> sizi nərd oynamağa dəvət edir</div>
                <div class="ni-actions">
                    <button type="button" class="btn btn-primary btn-sm ni-accept">Qəbul et</button>
                    <button type="button" class="btn btn-ghost-dark btn-sm ni-decline">Rədd et</button>
                </div>
            </div>`;
        }
        if (d.match_url && !onMatchPage) {
            html += `<div class="ni-card ni-match">
                <div class="ni-text"><i class="fa fa-dice me-2"></i>Nərd oyununuz davam edir</div>
                <div class="ni-actions"><a href="${esc(d.match_url)}" class="btn btn-primary btn-sm">Oyuna qayıt</a></div>
            </div>`;
        }
        // yalnız dəyişəndə yeniləyirik — yoxsa oyunçu basmaq istədiyi düymə əlinin altından dəyişər
        if (box.dataset.html !== html) {
            box.dataset.html = html;
            box.innerHTML = html;
        }
    }

    async function check() {
        if (document.hidden) return;
        try {
            const r = await fetch(box.dataset.url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            if (!r.ok) { if (r.status === 403 || r.status === 401) stop(); return; }
            const d = await r.json();
            // göndərdiyim dəvət qəbul olundu — oyuna keçirik
            if (d.outgoing && d.outgoing.status === 'accepted' && d.outgoing.match_url && !onMatchPage && !redirected) {
                redirected = true;
                location.href = d.outgoing.match_url;
                return;
            }
            render(d);
            document.dispatchEvent(new CustomEvent('nard:inbox', {detail: d})); // nərd səhifəsi göndərilən dəvəti göstərir
        } catch (e) { /* şəbəkə xətası — növbəti dəfə */ }
    }

    box.addEventListener('click', async e => {
        const card = e.target.closest('.ni-card');
        if (!card || !card.dataset.id) return;
        const id = card.dataset.id;
        if (e.target.closest('.ni-accept')) {
            e.target.disabled = true;
            const r = await post(box.dataset.accept.replace('__ID__', id));
            if (r.ok && r.data.match_url) { location.href = r.data.match_url; return; }
            dismissed.add(+id);
            card.querySelector('.ni-text').textContent = r.data.message || 'Dəvət artıq etibarlı deyil.';
            setTimeout(check, 2500);
        } else if (e.target.closest('.ni-decline')) {
            dismissed.add(+id);
            card.remove();
            post(box.dataset.decline.replace('__ID__', id)).catch(() => {});
        }
    });

    let timer = setInterval(check, 5000);
    function stop() { clearInterval(timer); }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
    window.nardInboxCheck = check; // nərd səhifəsi dəvət göndərəndən sonra dərhal yoxlasın deyə
    check();
})();
