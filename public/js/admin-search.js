/*
 * Admin: canlı axtarış — yazdıqca (düymə basmadan) nəticələr yenilənir.
 * <form data-live-search="blokId1,blokId2"> — həmin səhifə yenidən yüklənmədən alınır,
 * göstərilən id-li bloklar yenisi ilə əvəz olunur, ünvan sətri də yenilənir.
 */
(function () {
    'use strict';

    document.querySelectorAll('form[data-live-search]').forEach(form => {
        const ids = form.dataset.liveSearch.split(',').map(s => s.trim()).filter(Boolean);
        const input = form.querySelector('input[name="q"]');
        if (!input || !ids.length) return;
        let timer = 0, ctrl = null, last = input.value.trim();

        async function search() {
            const q = input.value.trim();
            if (q === last) return;
            last = q;
            const params = new URLSearchParams(new FormData(form));
            if (!q) params.delete('q');
            const url = form.action + (params.toString() ? '?' + params : '');
            ctrl?.abort();
            ctrl = new AbortController();
            const boxes = ids.map(id => document.getElementById(id)).filter(Boolean);
            boxes.forEach(b => b.classList.add('is-loading'));
            try {
                const res = await fetch(url, {headers: {'Accept': 'text/html'}, signal: ctrl.signal, credentials: 'same-origin'});
                if (!res.ok) throw new Error(res.status);
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                boxes.forEach(b => {
                    const fresh = doc.getElementById(b.id);
                    if (fresh) b.innerHTML = fresh.innerHTML;
                });
                history.replaceState(null, '', url);
            } catch (e) {
                if (e.name !== 'AbortError') form.submit(); // xəta olsa — adi axtarış
            } finally {
                boxes.forEach(b => b.classList.remove('is-loading'));
            }
        }

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 300); });
        form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); search(); });
    });
})();
