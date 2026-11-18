/*
 * Şifrə sahələrində "şifrəni göstər" düyməsi (göz işarəsi): qeydiyyat, giriş, şifrə bərpası.
 * Basanda yazılan şifrə görünür, yenidən basanda gizlənir.
 */
(function () {
    'use strict';

    document.querySelectorAll('.auth-card input[type="password"]').forEach(input => {
        const wrap = document.createElement('div');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle';
        btn.setAttribute('aria-label', 'Şifrəni göstər');
        btn.setAttribute('aria-pressed', 'false');
        btn.innerHTML = '<i class="fa fa-eye" aria-hidden="true"></i>';
        wrap.appendChild(btn);

        btn.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = `<i class="fa ${show ? 'fa-eye-slash' : 'fa-eye'}" aria-hidden="true"></i>`;
            btn.setAttribute('aria-label', show ? 'Şifrəni gizlət' : 'Şifrəni göstər');
            btn.setAttribute('aria-pressed', String(show));
            // kursor yerində qalsın ki, yazmağa davam etmək olsun
            const pos = input.value.length;
            input.focus();
            input.setSelectionRange(pos, pos);
        });
    });
})();
