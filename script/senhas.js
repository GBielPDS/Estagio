/* Melhoria progressiva: somente a apresentação do campo é alterada. */
(() => {
    'use strict';
    const ocultarTodos = [];
    document.querySelectorAll('input[type="password"]').forEach((campo, indice) => {
        if (campo.closest('.senha-controle')) return;
        if (!campo.id) campo.id = `senha-campo-${indice}`;
        const grupo = document.createElement('div');
        grupo.className = 'senha-controle';
        campo.before(grupo);
        grupo.append(campo);
        const botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'senha-visibilidade';
        botao.setAttribute('aria-controls', campo.id);
        botao.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="senha-risco" d="m3 3 18 18"/></svg>';
        grupo.append(botao);
        const rotulo = campo.labels?.[0]?.textContent.trim() || 'Senha';
        const apresentar = (visivel) => {
            campo.type = visivel ? 'text' : 'password';
            const texto = `${visivel ? 'Ocultar' : 'Mostrar'} senha — ${rotulo}`;
            botao.setAttribute('aria-label', texto);
            botao.setAttribute('title', texto);
            botao.setAttribute('aria-pressed', String(visivel));
        };
        const ocultar = () => apresentar(false);
        ocultar();
        ocultarTodos.push(ocultar);
        botao.addEventListener('click', () => apresentar(campo.type === 'password'));
        // Mover o foco entre campo e olho não interrompe o uso pelo teclado.
        grupo.addEventListener('focusout', (evento) => {
            if (!grupo.contains(evento.relatedTarget)) ocultar();
        });
        grupo.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape') ocultar();
        });
        campo.form?.addEventListener('submit', ocultar);
        campo.form?.addEventListener('reset', ocultar);
    });
    const ocultar = () => ocultarTodos.forEach((acao) => acao());
    window.addEventListener('blur', ocultar);
    window.addEventListener('pagehide', ocultar);
    window.addEventListener('pageshow', ocultar);
    document.addEventListener('visibilitychange', () => { if (document.hidden) ocultar(); });
})();
