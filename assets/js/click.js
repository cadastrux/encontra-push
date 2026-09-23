/* =========================================================================
   Encontra Push — registro do clique vindo da notificação.

   Seção 39.1. Quatro passos, nesta ordem:

     1. detecta o parâmetro epc;
     2. valida o formato;
     3. registra o clique no painel (via rota do próprio site);
     4. remove o parâmetro da URL com history.replaceState.

   O passo 4 evita que o visitante compartilhe um link com o token de clique
   de outra pessoa e que a URL suja acabe indexada pelos buscadores.
   ========================================================================= */

(function () {
    'use strict';

    var config = window.EncontraPushClick;

    if (!config || !config.endpoint) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var token = params.get(config.param || 'epc');

    if (!token || !/^[A-Za-z0-9_-]{16,64}$/.test(token)) {
        return;
    }

    // O beacon não pode atrasar a página: dispara e segue.
    try {
        fetch(config.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                events: [
                    {
                        type: 'clicked',
                        token: token,
                        event_at: new Date().toISOString(),
                    },
                ],
            }),
            keepalive: true,
        }).catch(function () { /* métrica é melhor esforço */ });
    } catch (e) { /* idem */ }

    // Limpa a URL preservando os demais parâmetros (UTM continua valendo
    // para o analytics do site).
    try {
        params.delete(config.param || 'epc');

        var query = params.toString();
        var clean = window.location.pathname + (query ? '?' + query : '') + window.location.hash;

        window.history.replaceState({}, document.title, clean);
    } catch (e) { /* navegador sem History API: a URL fica como está */ }
})();
