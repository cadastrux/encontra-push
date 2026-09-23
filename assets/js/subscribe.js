/* =========================================================================
   Encontra Push — captura de assinantes no front-end.

   Seções 19 (solicitação de permissão), 20 (iOS/iPadOS), 113 a 115.

   Principio que organiza este arquivo: a chamada NATIVA de permissão só
   acontece depois de um clique do usuário no nosso pré-prompt. Chamar
   Notification.requestPermission() no carregamento da página é a maneira mais
   rápida de perder a origem — o Chrome passa a bloquear o prompt em sites com
   taxa alta de recusa, e a decisão é por domínio, sem volta fácil.
   ========================================================================= */

(function () {
    'use strict';

    /*
     * Diagnóstico no console do navegador (F12 > Console, filtro "Encontra").
     *
     * Toda falha de inscrição era silenciosa: o visitante concedia a
     * permissão, nada chegava ao painel, e não havia como saber por que.
     * Cada desistencia agora deixa uma linha explicando o motivo.
     *
     * A primeira linha ("carregado") também responde a uma pergunta comum:
     * se ELA não aparece, o script nem está na página — quase sempre cache
     * de página servindo uma versão anterior a conexão do plugin.
     */
    function log(message, detail) {
        try {
            if (detail !== undefined) {
                console.info('[Encontra Push] ' + message, detail);
            } else {
                console.info('[Encontra Push] ' + message);
            }
        } catch (e) { /* console indisponível */ }
    }

    var config = window.EncontraPush;

    if (!config || !config.vapidPublicKey) {
        log('sem chave VAPID na configuração da página; o plugin ainda não está conectado ao painel ou a página veio de um cache antigo.');

        return;
    }

    log('carregado (modo ' + config.integrationMode + ', permissão atual: '
        + ('Notification' in window ? Notification.permission : 'indisponível') + ').');

    var STORAGE_PREFIX = 'encontra_push_';

    /* ------------------------------------------------ deteccao de suporte */

    /*
     * Seção 115: feature detection, nunca user-agent. O navegador que não
     * suporta simplesmente não vê CTA nenhum — mostrar um botão que não
     * funciona é pior do que não mostrar nada.
     */
    var supported =
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window;

    if (!supported) {
        log('este navegador não oferece Web Push neste contexto (no iPhone/iPad, só com o site instalado na Tela de Início).');
        onReady(initWidget);
        maybeShowIosGuide();
        return;
    }

    if (Notification.permission === 'denied') {
        log('notificações bloqueadas neste navegador; o pré-prompt não será exibido.');
    }

    /* --------------------------------------------------------- utilidades */

    function store(key, value) {
        try {
            localStorage.setItem(STORAGE_PREFIX + key, String(value));
        } catch (e) {
            // Seção 19.3: cai para cookie first-party quando o localStorage
            // está bloqueado (modo anônimo, políticas restritivas).
            document.cookie =
                STORAGE_PREFIX + key + '=' + encodeURIComponent(String(value)) +
                ';path=/;max-age=' + 60 * 60 * 24 * 365 + ';SameSite=Lax';
        }
    }

    function read(key) {
        try {
            var value = localStorage.getItem(STORAGE_PREFIX + key);
            if (value !== null) return value;
        } catch (e) { /* segue para o cookie */ }

        var match = document.cookie.match(new RegExp('(^|; )' + STORAGE_PREFIX + key + '=([^;]*)'));

        return match ? decodeURIComponent(match[2]) : null;
    }

    function deviceType() {
        if (/iPad|Tablet/i.test(navigator.userAgent)) return 'tablet';
        if (/Mobi|iPhone|Android.*Mobile/i.test(navigator.userAgent)) return 'mobile';
        return 'desktop';
    }

    function isMobile() {
        return deviceType() !== 'desktop';
    }

    function report(event) {
        // Seção 112: contadores do funil de opt-in, sempre agregados.
        try {
            fetch(config.endpoints.optin, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event: event, device_type: deviceType() }),
                keepalive: true,
            });
        } catch (e) { /* métrica nunca pode quebrar a inscrição */ }
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var output = new Uint8Array(raw.length);

        for (var i = 0; i < raw.length; ++i) {
            output[i] = raw.charCodeAt(i);
        }

        return output;
    }

    /* ------------------------------------------------ elegibilidade do prompt */

    function shouldShowPrompt() {
        var prompt = config.prompt || {};

        if (prompt.mode === 'disabled') return false;
        if (!prompt.desktop && !isMobile()) return false;
        if (!prompt.mobile && isMobile()) return false;

        // Seção 113: permissão negada no navegador encerra o assunto. Insistir
        // com um botão que abre um prompt que não vai aparecer só frustra.
        if (Notification.permission === 'denied') return false;

        // Já inscrito: não há o que pedir.
        if (Notification.permission === 'granted') return false;

        // Seção 19.3: respeitar a janela de reexibição.
        var snoozeUntil = parseInt(read('snooze_until') || '0', 10);

        if (snoozeUntil && Date.now() < snoozeUntil) return false;

        return true;
    }

    function trackVisit() {
        var visits = parseInt(read('visits') || '0', 10) + 1;
        store('visits', visits);

        var pageviews = parseInt(sessionStorageGet('pageviews') || '0', 10) + 1;
        sessionStorageSet('pageviews', pageviews);

        return { visits: visits, pageviews: pageviews };
    }

    function sessionStorageGet(key) {
        try {
            return sessionStorage.getItem(STORAGE_PREFIX + key);
        } catch (e) {
            return null;
        }
    }

    function sessionStorageSet(key, value) {
        try {
            sessionStorage.setItem(STORAGE_PREFIX + key, String(value));
        } catch (e) { /* ignora */ }
    }

    /* --------------------------------------------------------- pré-prompt */

    function buildPrompt() {
        var prompt = config.prompt || {};
        var appearance = config.appearance || {};

        var root = document.createElement('div');
        root.className = 'ep-prompt ep-prompt--' + (prompt.position || 'top-center')
            + ' ep-prompt--theme-' + promptTheme(appearance, prompt);
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-live', 'polite');
        root.setAttribute('aria-label', prompt.title || '');

        applyAppearance(root, appearance);

        var bell = document.createElement('span');
        bell.className = 'ep-prompt__icon';
        bell.setAttribute('aria-hidden', 'true');
        bell.textContent = '🔔';

        var content = document.createElement('div');
        content.className = 'ep-prompt__content';

        var title = document.createElement('p');
        title.className = 'ep-prompt__title';
        title.textContent = prompt.title || '';

        var body = document.createElement('p');
        body.className = 'ep-prompt__body';
        body.textContent = prompt.body || '';

        content.appendChild(title);
        content.appendChild(body);

        var actions = document.createElement('div');
        actions.className = 'ep-prompt__actions';

        var decline = document.createElement('button');
        decline.type = 'button';
        decline.className = 'ep-prompt__btn ep-prompt__btn--ghost';
        decline.textContent = prompt.decline_label || 'Agora não';

        var accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'ep-prompt__btn ep-prompt__btn--primary';
        accept.textContent = prompt.accept_label || 'Ativar notificações';

        actions.appendChild(decline);
        actions.appendChild(accept);
        content.appendChild(actions);

        root.appendChild(bell);
        root.appendChild(content);

        decline.addEventListener('click', function () {
            // Seção 19.3: "Agora não" adia por mais tempo que fechar.
            snooze(prompt.redisplay_later_days || 30);
            report('preprompt_dismiss');
            close(root);
        });

        accept.addEventListener('click', function () {
            report('preprompt_accept');
            close(root);
            requestPermission();
        });

        return root;
    }

    /**
     * Tema escolhido em Aparência: light, dark ou auto (padrão). Antes da
     * versão 1.0.5 o valor era salvo mas nunca aplicado — o pré-prompt
     * seguia sempre o sistema do visitante.
     */
    function promptTheme(appearance, prompt) {
        var theme = appearance.theme || prompt.theme || 'auto';

        return theme === 'light' || theme === 'dark' ? theme : 'auto';
    }

    /** Cores e borda da tela Aparência. Só hexadecimal e número passam. */
    function applyAppearance(root, appearance) {
        var hex = /^#[0-9a-f]{6}$/i;

        if (appearance.accent && hex.test(appearance.accent)) {
            root.style.setProperty('--ep-accent', appearance.accent);
        }

        if (appearance.button_color && hex.test(appearance.button_color)) {
            root.style.setProperty('--ep-button', appearance.button_color);
        }

        var radius = parseInt(appearance.radius, 10);

        if (!isNaN(radius)) {
            root.style.setProperty('--ep-radius', Math.max(0, Math.min(40, radius)) + 'px');
        }
    }

    function close(root) {
        root.classList.add('ep-prompt--leaving');
        setTimeout(function () {
            if (root.parentNode) root.parentNode.removeChild(root);
        }, 200);
    }

    function snooze(days) {
        store('snooze_until', Date.now() + days * 24 * 60 * 60 * 1000);
    }

    function showPrompt() {
        if (document.querySelector('.ep-prompt')) return;

        var element = buildPrompt();
        document.body.appendChild(element);

        // Força o reflow antes de animar, senão a transicao não acontece.
        requestAnimationFrame(function () {
            element.classList.add('ep-prompt--visible');
        });

        report('preprompt_shown');
    }

    /* ------------------------------------------------------ inscrição ---- */

    async function requestPermission() {
        var permission;

        try {
            permission = await Notification.requestPermission();
        } catch (e) {
            return;
        }

        if (permission !== 'granted') {
            report('native_permission_denied');
            // Bloqueio nativo: não insistir mais (seção 113).
            snooze(365);
            refreshWidget();

            return;
        }

        report('native_permission_granted');

        await subscribe();
    }

    async function subscribe() {
        try {
            var registration = await registerServiceWorker();

            if (!registration) return;

            var serverKey = urlBase64ToUint8Array(config.vapidPublicKey);
            var subscription = await registration.pushManager.getSubscription();

            /*
             * Subscription criada com OUTRA chave VAPID — chave do site
             * rotacionada, ou herdada de um provedor anterior. O Push Service
             * recusaria todos os nossos envios para ela. Descarta e cria de
             * novo com a chave atual, avisando o painel da antiga.
             */
            if (subscription && !sameKey(subscription, serverKey)) {
                log('a inscrição existente foi feita com outra chave VAPID; recriando.');

                var oldEndpoint = subscription.endpoint;
                await subscription.unsubscribe();
                subscription = null;

                notifyUnsubscribe(oldEndpoint);
            }

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    // userVisibleOnly é obrigatório: o navegador exige que toda
                    // mensagem resulte em notificação visível.
                    userVisibleOnly: true,
                    applicationServerKey: serverKey,
                });
            }

            await sendSubscription(subscription);
        } catch (e) {
            // Antes este bloco era vazio, e toda falha de inscrição virava um
            // assinante que simplesmente nunca chegava ao painel. Uma linha no
            // console, para quem investiga, não incomoda o visitante.
            log('falha ao inscrever este navegador:', e && e.message ? e.message : e);
            widgetError = 'Não foi possível ativar neste navegador. Recarregue a página e tente de novo.';
        }
    }

    /**
     * navigator.serviceWorker.ready nunca rejeita: se o worker não ativar,
     * a promessa fica pendente para sempre — e o botão do sino ficava em
     * "Ativando..." indefinidamente. Com prazo, vira erro tratavel.
     */
    function readyWithTimeout(ms) {
        return Promise.race([
            navigator.serviceWorker.ready,
            new Promise(function (resolve, reject) {
                setTimeout(function () {
                    reject(new Error('o Service Worker não ativou em ' + (ms / 1000) + ' s'));
                }, ms);
            }),
        ]);
    }

    /** A inscrição existente foi feita com a chave VAPID atual do site? */
    function sameKey(subscription, serverKey) {
        var current = subscription.options && subscription.options.applicationServerKey;

        // Navegador que não expoe a chave: sem como comparar, assume a atual.
        if (!current) return true;

        var bytes = new Uint8Array(current);

        if (bytes.length !== serverKey.length) return false;

        for (var i = 0; i < bytes.length; i++) {
            if (bytes[i] !== serverKey[i]) return false;
        }

        return true;
    }

    function notifyUnsubscribe(endpoint) {
        try {
            fetch(config.endpoints.unsubscribe, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint: endpoint }),
                keepalive: true,
            });
        } catch (e) { /* o painel descobre sozinho no próximo envio */ }
    }

    /**
     * Seção 17 — compatibilidade com Service Workers existentes.
     *
     * No modo "integration" ou "custom", o site já tem o próprio Service
     * Worker e o plugin NÃO registra o seu: sobrescrever o registro na raiz
     * quebraria o PWA ou o cache offline do site.
     *
     * Em todos os caminhos, a função só devolve um registro com worker ATIVO.
     * O Chrome e o Edge recusam pushManager.subscribe() enquanto o worker
     * recém-registrado ainda está instalando ("no active Service Worker"); o
     * Safari espera. Sem aguardar a ativacao, a primeira visita falhava nos
     * navegadores Chromium e só a página seguinte funcionava.
     */
    async function registerServiceWorker() {
        if (config.integrationMode !== 'exclusive') {
            var siteRegistration = await navigator.serviceWorker.getRegistration('/');

            if (!siteRegistration) {
                // Sem Service Worker do site, não há onde enxertar os listeners.
                log('modo de integração "' + config.integrationMode + '", mas o site não tem Service Worker registrado.');
                widgetError = 'As notificações ainda não estão disponíveis neste site.';

                return null;
            }

            return readyWithTimeout(15000);
        }

        var existing = await navigator.serviceWorker.getRegistration('/');
        var worker = existing && (existing.active || existing.waiting || existing.installing);

        if (worker) {
            var script = worker.scriptURL || '';

            // Outro Service Worker controla a raiz: não substituimos (seção 17).
            // O motivo vai para o console, e a tela de Diagnóstico do plugin
            // mostra o conflito ao operador.
            if (script.indexOf('encontra-push-sw.js') === -1) {
                log('outro Service Worker controla este site e não será substituído: ' + script
                    + ' — veja Encontra Push > Diagnóstico no wp-admin.');
                widgetError = 'Este navegador já recebe notificações deste site por outro serviço.';

                return null;
            }

            return readyWithTimeout(15000);
        }

        await navigator.serviceWorker.register(config.serviceWorker, { scope: '/' });

        return readyWithTimeout(15000);
    }

    async function sendSubscription(subscription) {
        var json = subscription.toJSON();
        var params = new URLSearchParams(window.location.search);

        var response = await fetch(config.endpoints.subscribe, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                endpoint: json.endpoint,
                p256dh: json.keys && json.keys.p256dh,
                auth: json.keys && json.keys.auth,
                expiration_time: subscription.expirationTime || null,
                language: navigator.language,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                device_type: deviceType(),
                source_url: window.location.href,
                referrer: document.referrer || '',
                utm_source: params.get('utm_source') || '',
                utm_medium: params.get('utm_medium') || '',
                utm_campaign: params.get('utm_campaign') || '',
                utm_content: params.get('utm_content') || '',
            }),
        });

        // Antes a resposta era ignorada: um 403 de firewall ou um erro do
        // painel deixava o navegador inscrito mas ausente da base.
        if (!response.ok) {
            log('o site recusou a inscrição (HTTP ' + response.status + '). Veja Encontra Push > Diagnóstico.');
            widgetError = 'O site não conseguiu registrar a inscrição agora. Tente de novo em instantes.';

            return;
        }

        store('subscribed', '1');
        store('unsubscribed', '0');
        log('inscrição registrada no painel.');

        refreshWidget();
    }

    /* ------------------------------------------------------------ iOS ---- */

    /**
     * Seção 20 — iPhone e iPad.
     *
     * No iOS/iPadOS, Web Push só funciona com o site instalado na Tela de
     * Início é aberto pelo ícone. Em Safari comum, PushManager sequer existe.
     *
     * A instrução só aparece quando o ambiente realmente exige o passo extra:
     * se já estiver em modo standalone com suporte, não dizemos nada.
     */
    function maybeShowIosGuide() {
        var isAppleMobile = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        if (!isAppleMobile) return;

        var standalone = window.navigator.standalone === true ||
            window.matchMedia('(display-mode: standalone)').matches;

        // Já instalado e ainda sem suporte: não há instrução que resolva.
        if (standalone) return;

        if (read('ios_guide_seen')) return;

        var prompt = config.prompt || {};

        if (prompt.mode === 'disabled') return;

        var root = document.createElement('div');
        root.className = 'ep-prompt ep-prompt--bottom-center ep-prompt--ios ep-prompt--theme-'
            + promptTheme(config.appearance || {}, prompt);
        applyAppearance(root, config.appearance || {});
        root.setAttribute('role', 'dialog');

        var content = document.createElement('div');
        content.className = 'ep-prompt__content';

        var title = document.createElement('p');
        title.className = 'ep-prompt__title';
        title.textContent = config.i18n.iosTitle;
        content.appendChild(title);

        var list = document.createElement('ol');
        list.className = 'ep-prompt__steps';

        config.i18n.iosSteps.forEach(function (step) {
            var item = document.createElement('li');
            item.textContent = step;
            list.appendChild(item);
        });

        content.appendChild(list);

        var actions = document.createElement('div');
        actions.className = 'ep-prompt__actions';

        var close_button = document.createElement('button');
        close_button.type = 'button';
        close_button.className = 'ep-prompt__btn ep-prompt__btn--ghost';
        close_button.textContent = config.i18n.close;

        close_button.addEventListener('click', function () {
            store('ios_guide_seen', '1');
            close(root);
        });

        actions.appendChild(close_button);
        content.appendChild(actions);
        root.appendChild(content);

        document.body.appendChild(root);
        requestAnimationFrame(function () {
            root.classList.add('ep-prompt--visible');
        });
    }

    /* ---------------------------------------------------------- disparo -- */

    function boot() {
        var counters = trackVisit();

        initWidget();

        // Já concedida: garante que a subscription atual chegou ao painel
        // (o navegador pode ter trocado o endpoint) — sem pedir nada de novo.
        // Exceto se o visitante escolheu "Parar de receber": a permissão do
        // navegador continua concedida, mas a vontade dele é não receber.
        if (Notification.permission === 'granted') {
            if (read('unsubscribed') !== '1') {
                subscribe();
            }

            return;
        }

        if (!shouldShowPrompt()) return;

        var prompt = config.prompt || {};

        switch (prompt.mode) {
            case 'visits':
                if (counters.visits >= (prompt.visits || 2)) showPrompt();
                break;

            case 'pageviews':
                if (counters.pageviews >= (prompt.pageviews || 2)) showPrompt();
                break;

            case 'selector':
                bindSelector(prompt.css_selector);
                break;

            case 'manual':
                bindSelector('[data-encontra-push-subscribe]');
                break;

            default:
                setTimeout(showPrompt, (prompt.delay_seconds || 8) * 1000);
        }
    }

    function bindSelector(selector) {
        if (!selector) return;

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest(selector);

            if (!trigger) return;

            event.preventDefault();

            // Clique explícito já é a ação do usuário que a seção 19.1 exige:
            // pode ir direto para o prompt nativo.
            report('preprompt_accept');
            requestPermission();
        });
    }

    /* ------------------------------------------------ sino de notícias -- */

    /*
     * Botão fixo com sino na lateral do site (Encontra Push > Sino de notícias).
     *
     *   inscrito      -> últimas notícias, com contador de não lidas e o botão
     *                    "Parar de receber";
     *   não inscrito  -> convite com o botão de ativar;
     *   bloqueado     -> como liberar no navegador;
     *   sem suporte   -> no iPhone, os passos da Tela de Início.
     *
     * Tudo montado com textContent: nenhum texto vindo do site vira HTML.
     */

    var widget = null; // { root, button, badge, panel, body, foot, open }

    // Último motivo de falha da inscrição, mostrado no convite do sino.
    var widgetError = '';

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;

        return node;
    }

    function widgetConfig() {
        return config.widget || null;
    }

    function widgetItems() {
        var cfg = widgetConfig();

        return (cfg && Array.isArray(cfg.items)) ? cfg.items : [];
    }

    function readIds() {
        return (read('widget_read') || '').split(',').filter(Boolean);
    }

    function isUnread(item) {
        var seen = parseInt(read('widget_seen') || '0', 10);
        var published = Date.parse(item.date);

        if (readIds().indexOf(String(item.id)) !== -1) return false;

        return isNaN(published) || published > seen;
    }

    function unreadCount() {
        return widgetItems().filter(isUnread).length;
    }

    function markRead(id) {
        var ids = readIds();

        if (ids.indexOf(String(id)) === -1) {
            ids.unshift(String(id));
        }

        store('widget_read', ids.slice(0, 50).join(','));
    }

    function markAllRead() {
        store('widget_seen', Date.now());
        store('widget_read', '');
    }

    function timeAgo(iso) {
        var time = Date.parse(iso);

        if (isNaN(time)) return '';

        var seconds = Math.round((time - Date.now()) / 1000);
        var units = [
            ['year', 31536000], ['month', 2592000], ['week', 604800],
            ['day', 86400], ['hour', 3600], ['minute', 60],
        ];

        try {
            var rtf = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });

            for (var i = 0; i < units.length; i++) {
                if (Math.abs(seconds) >= units[i][1]) {
                    return rtf.format(Math.round(seconds / units[i][1]), units[i][0]);
                }
            }

            return rtf.format(0, 'minute');
        } catch (e) {
            return new Date(time).toLocaleDateString('pt-BR');
        }
    }

    function isAppleMobile() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    /** Estado atual do visitante, conferido no próprio navegador. */
    async function widgetState() {
        if (!supported) return 'unsupported';
        if (Notification.permission === 'denied') return 'blocked';
        if (Notification.permission !== 'granted') return 'default';

        try {
            var registration = await navigator.serviceWorker.getRegistration('/');
            var subscription = registration && await registration.pushManager.getSubscription();

            return subscription && read('unsubscribed') !== '1' ? 'subscribed' : 'default';
        } catch (e) {
            return 'default';
        }
    }

    function bellIcon() {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');

        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('width', '26');
        svg.setAttribute('height', '26');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');

        ['M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9', 'M13.73 21a2 2 0 0 1-3.46 0'].forEach(function (d) {
            var path = document.createElementNS(ns, 'path');
            path.setAttribute('d', d);
            svg.appendChild(path);
        });

        return svg;
    }

    function initWidget() {
        var cfg = widgetConfig();

        if (!cfg || !cfg.enabled || widget) return;
        if (isMobile() ? !cfg.mobile : !cfg.desktop) return;

        var appearance = config.appearance || {};
        var side = cfg.position === 'left' ? 'left' : 'right';

        var root = el('div', 'ep-bell ep-bell--' + side + ' ep-prompt--theme-' + promptTheme(appearance, config.prompt || {}));
        applyAppearance(root, appearance);
        root.style.setProperty('--ep-bell-offset', Math.max(0, Math.min(200, parseInt(cfg.offset, 10) || 20)) + 'px');

        var button = el('button', 'ep-bell__button');
        button.type = 'button';
        button.setAttribute('aria-label', cfg.title || 'Notícias');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-controls', 'ep-bell-panel');
        button.appendChild(bellIcon());

        var badge = el('span', 'ep-bell__badge');
        badge.setAttribute('aria-hidden', 'true');
        button.appendChild(badge);

        var panel = el('div', 'ep-bell__panel');
        panel.id = 'ep-bell-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', cfg.title || 'Notícias');
        panel.hidden = true;

        var head = el('div', 'ep-bell__head');
        var headTitle = el('p', 'ep-bell__title');
        var closeButton = el('button', 'ep-bell__close', '×');
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', 'Fechar');
        head.appendChild(headTitle);
        head.appendChild(closeButton);

        var body = el('div', 'ep-bell__body');
        var foot = el('div', 'ep-bell__foot');

        panel.appendChild(head);
        panel.appendChild(body);
        panel.appendChild(foot);

        root.appendChild(panel);
        root.appendChild(button);
        document.body.appendChild(root);

        widget = { root: root, button: button, badge: badge, panel: panel, title: headTitle, body: body, foot: foot, open: false };

        button.addEventListener('click', function () {
            toggleWidget(!widget.open);
        });

        closeButton.addEventListener('click', function () {
            toggleWidget(false);
            button.focus();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && widget.open) {
                toggleWidget(false);
                button.focus();
            }
        });

        document.addEventListener('click', function (event) {
            if (widget.open && !root.contains(event.target)) {
                toggleWidget(false);
            }
        });

        updateBadge();
        maybeShowTeaser();
    }

    /**
     * Card da última notícia ao lado do sino, assim que a página carrega.
     *
     * E a amostra que chama a atenção para o sino: some sozinho depois de
     * alguns segundos e aparece UMA vez por sessão do navegador, para não
     * incomodar quem está navegando pelo site.
     */
    function maybeShowTeaser() {
        var cfg = widgetConfig();

        if (!cfg.teaser || sessionStorageGet('teaser_shown')) return;

        var items = widgetItems().filter(function (item) {
            return /^https?:\/\//i.test(item.url || '');
        });

        if (items.length === 0) return;

        var item = items[0];

        sessionStorageSet('teaser_shown', '1');

        var card = el('div', 'ep-bell__teaser');

        var link = el('a', 'ep-bell__teaser-link');
        link.href = item.url;

        if (item.image && /^https?:\/\//i.test(item.image)) {
            var img = el('img', 'ep-bell__teaser-thumb');
            img.src = item.image;
            img.alt = '';
            img.loading = 'lazy';
            img.width = 56;
            img.height = 56;
            link.appendChild(img);
        }

        var text = el('span', 'ep-bell__teaser-text');
        text.appendChild(el('span', 'ep-bell__teaser-title', item.title));
        text.appendChild(el('span', 'ep-bell__item-time', timeAgo(item.date)));
        link.appendChild(text);

        var close = el('button', 'ep-bell__teaser-close', '×');
        close.type = 'button';
        close.setAttribute('aria-label', 'Fechar');

        card.appendChild(link);
        card.appendChild(close);
        widget.root.appendChild(card);

        requestAnimationFrame(function () {
            card.classList.add('ep-bell__teaser--visible');
        });

        var timer = setTimeout(hide, Math.max(3, Math.min(30, parseInt(cfg.teaser_seconds, 10) || 8)) * 1000);

        function hide() {
            clearTimeout(timer);
            card.classList.remove('ep-bell__teaser--visible');
            setTimeout(function () {
                if (card.parentNode) card.parentNode.removeChild(card);
            }, 220);
        }

        close.addEventListener('click', function (event) {
            event.preventDefault();
            hide();
        });

        // Não some enquanto a pessoa está lendo ou com o dedo em cima.
        card.addEventListener('mouseenter', function () {
            clearTimeout(timer);
        });

        link.addEventListener('click', function () {
            markRead(item.id);
            updateBadge();
        });
    }

    function toggleWidget(open) {
        if (!widget) return;

        widget.open = open;
        widget.panel.hidden = !open;
        widget.button.setAttribute('aria-expanded', open ? 'true' : 'false');
        widget.root.classList.toggle('ep-bell--open', open);

        if (open) {
            renderWidget();
        }
    }

    function updateBadge() {
        if (!widget) return;

        var cfg = widgetConfig();
        var count = unreadCount();

        widget.badge.textContent = count > 9 ? '9+' : String(count);
        widget.badge.hidden = !cfg.badge || count === 0;

        widget.button.setAttribute(
            'aria-label',
            (cfg.title || 'Notícias') + (count > 0 ? ' (' + count + ' não lidas)' : '')
        );
    }

    /** Redesenha o painel se estiver aberto; o contador sempre. */
    function refreshWidget() {
        updateBadge();

        if (widget && widget.open) {
            renderWidget();
        }
    }

    async function renderWidget() {
        var cfg = widgetConfig();
        var state = await widgetState();

        widget.body.textContent = '';
        widget.foot.textContent = '';
        widget.root.setAttribute('data-state', state);

        if (state === 'subscribed') {
            widget.title.textContent = cfg.title;
            renderNews(cfg);

            return;
        }

        /*
         * "Amostra grátis": quem ainda não ativou também vê as notícias, e o
         * convite fica logo abaixo. Ver o que anda sendo publicado convence
         * mais do que qualquer texto pedindo permissão.
         */
        var preview = cfg.preview && widgetItems().length > 0;

        widget.title.textContent = preview ? cfg.title : cfg.subscribe_title;

        if (preview) {
            renderList();
        }

        if (state === 'blocked') {
            widget.foot.appendChild(el('p', 'ep-bell__text ep-bell__text--hint', cfg.blocked_text));
        } else if (state === 'unsupported') {
            renderUnsupported(preview);
        } else {
            renderInvite(cfg, preview);
        }
    }

    function renderNews(cfg) {
        renderList();

        if (unreadCount() > 0) {
            var markAll = el('button', 'ep-bell__link', 'Marcar tudo como lido');
            markAll.type = 'button';
            markAll.addEventListener('click', function () {
                markAllRead();
                refreshWidget();
            });
            widget.foot.appendChild(markAll);
        }

        widget.foot.appendChild(el('p', 'ep-bell__status', cfg.subscribed_text));

        var stop = el('button', 'ep-bell__btn ep-bell__btn--ghost', 'Parar de receber');
        stop.type = 'button';
        stop.addEventListener('click', async function () {
            stop.disabled = true;
            await window.EncontraPushUnsubscribe();
            refreshWidget();
        });
        widget.foot.appendChild(stop);
    }

    /** Lista de notícias, usada tanto para inscritos quanto na amostra. */
    function renderList() {
        var items = widgetItems();

        if (items.length === 0) {
            widget.body.appendChild(el('p', 'ep-bell__text', 'Nenhuma notícia por enquanto.'));

            return;
        }

        var list = el('ul', 'ep-bell__list');

        items.forEach(function (item) {
            if (!/^https?:\/\//i.test(item.url || '')) return;

            var li = el('li', 'ep-bell__entry');
            var link = el('a', 'ep-bell__item' + (isUnread(item) ? ' ep-bell__item--unread' : ''));
            link.href = item.url;

            if (item.image && /^https?:\/\//i.test(item.image)) {
                var img = el('img', 'ep-bell__thumb');
                img.src = item.image;
                img.alt = '';
                img.loading = 'lazy';
                img.width = 56;
                img.height = 56;
                link.appendChild(img);
            }

            var text = el('span', 'ep-bell__item-text');
            text.appendChild(el('span', 'ep-bell__item-title', item.title));
            text.appendChild(el('span', 'ep-bell__item-time', timeAgo(item.date)));
            link.appendChild(text);

            link.addEventListener('click', function () {
                markRead(item.id);
                updateBadge();
            });

            li.appendChild(link);
            list.appendChild(li);
        });

        widget.body.appendChild(list);
    }

    /**
     * Convite para ativar. Com a amostra ligada, ele vem DEPOIS da lista, no
     * rodapé do painel; sem ela, ocupa o painel inteiro.
     */
    function renderInvite(cfg, preview) {
        var target = preview ? widget.foot : widget.body;

        target.appendChild(el('p', preview ? 'ep-bell__status' : 'ep-bell__text', cfg.subscribe_text));

        if (widgetError) {
            widget.body.appendChild(el('p', 'ep-bell__text ep-bell__text--error', widgetError));
        }

        var hint = el('p', 'ep-bell__text ep-bell__text--hint');
        hint.hidden = true;
        widget.body.appendChild(hint);

        var activate = el('button', 'ep-bell__btn ep-bell__btn--primary', cfg.button_label);
        activate.type = 'button';
        activate.addEventListener('click', async function () {
            activate.disabled = true;
            activate.textContent = 'Ativando…';
            widgetError = '';

            report('preprompt_accept');
            store('unsubscribed', '0');

            /*
             * Chrome e Edge às vezes não abrem a janela de permissão: mostram
             * só um ícone de sino na barra de endereço ("pedido silencioso"),
             * e a promessa do navegador fica esperando até a pessoa clicar lá.
             * Sem este aviso, o botão parecia travado em "Ativando...".
             */
            var quietTimer = setTimeout(function () {
                if (Notification.permission === 'default') {
                    hint.textContent = 'Confirme o pedido do navegador. Se nenhuma janela abriu, clique no ícone de sino ou de cadeado na barra de endereço e escolha "Permitir".';
                    hint.hidden = false;
                }
            }, 4000);

            try {
                // Permissão já concedida (ex.: parou de receber antes): só inscreve.
                if (Notification.permission === 'granted') {
                    await subscribe();
                } else {
                    await requestPermission();
                }
            } finally {
                clearTimeout(quietTimer);
            }

            refreshWidget();
        });

        widget.foot.appendChild(activate);
    }

    function renderUnsupported(preview) {
        var target = preview ? widget.foot : widget.body;

        if (!isAppleMobile()) {
            target.appendChild(el('p', 'ep-bell__text', 'Este navegador não permite notificações neste site.'));
            return;
        }

        target.appendChild(el('p', 'ep-bell__text', config.i18n.iosTitle + ':'));

        var steps = el('ol', 'ep-bell__steps');

        config.i18n.iosSteps.forEach(function (step) {
            steps.appendChild(el('li', null, step));
        });

        target.appendChild(steps);
    }

    /** API publica para o site desinscrever o visitante (seção 22.1). */
    window.EncontraPushUnsubscribe = async function () {
        try {
            var registration = await navigator.serviceWorker.getRegistration('/');

            if (!registration) return false;

            var subscription = await registration.pushManager.getSubscription();

            if (!subscription) return false;

            var endpoint = subscription.endpoint;

            await subscription.unsubscribe();

            // Lembra a escolha: sem isto, a próxima página reinscreveria o
            // navegador sozinha (a permissão continua "granted").
            store('unsubscribed', '1');
            store('subscribed', '0');

            await fetch(config.endpoints.unsubscribe, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint: endpoint }),
            });

            return true;
        } catch (e) {
            return false;
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
