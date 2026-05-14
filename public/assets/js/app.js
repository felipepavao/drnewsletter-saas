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

    // -----------------------------------------------------------
    // Botão submit: desabilita + mostra spinner ao submeter o form.
    // Especialmente importante em ações de IA que demoram 10-30s.
    // -----------------------------------------------------------
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (e.defaultPrevented) return;  // confirm() cancelou

        // Pequeno delay pra deixar o navegador iniciar o POST antes de mexer
        // (senão em alguns browsers o button disabled não submete o valor).
        setTimeout(function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                if (btn.dataset.original) return;
                btn.dataset.original = btn.innerHTML;
                btn.disabled = true;
                var loadingMsg = btn.dataset.loading || 'Processando…';
                btn.innerHTML = '<span class="spinner"></span> ' + loadingMsg;
                btn.classList.add('is-loading');
            });
        }, 10);
    });

    // -----------------------------------------------------------
    // Contador de caracteres em textareas com data-maxlen-counter
    // -----------------------------------------------------------
    document.querySelectorAll('textarea[maxlength]').forEach(function (ta) {
        var max = parseInt(ta.getAttribute('maxlength'), 10) || 0;
        if (max === 0) return;
        var counter = document.createElement('div');
        counter.className = 'char-counter';
        var update = function () {
            var rem = max - ta.value.length;
            counter.textContent = rem + ' caracteres restantes';
            counter.style.color = rem < 100 ? 'var(--color-warning)' : 'var(--color-muted)';
        };
        ta.addEventListener('input', update);
        ta.parentNode.insertBefore(counter, ta.nextSibling);
        update();
    });
})();
