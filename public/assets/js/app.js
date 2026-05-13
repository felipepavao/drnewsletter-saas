/* =============================================================
   Dr. Newsletter — base JS (vanilla, sem deps, sem build).
   Carregado em layout 'app' (autenticado). Mantém-se minúsculo.
   ============================================================= */

(function () {
    'use strict';

    // -----------------------------------------------------------
    // data-confirm: confirma antes de submeter forms destrutivos
    // Uso: <form data-confirm="Tem certeza?"> … </form>
    // -----------------------------------------------------------
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        var msg = form.dataset.confirm;
        if (!msg) return;
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    }, true);

    // -----------------------------------------------------------
    // auto-uppercase + digit-only em inputs de código de 6 dígitos
    // -----------------------------------------------------------
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!(el instanceof HTMLInputElement)) return;
        if (!el.classList.contains('input--code')) return;
        el.value = el.value.replace(/\D/g, '').slice(0, 6);
    });

    // -----------------------------------------------------------
    // auto-dismiss flash após 6s (clicar também fecha)
    // -----------------------------------------------------------
    document.querySelectorAll('.flash').forEach(function (el) {
        var dismiss = function () {
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        };
        el.addEventListener('click', dismiss);
        setTimeout(dismiss, 6000);
    });
})();
