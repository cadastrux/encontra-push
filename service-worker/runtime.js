/* =========================================================================
   Encontra Push — Service Worker (modo A, exclusivo).

   Servido em /encontra-push-sw.js com Service-Worker-Allowed: / para que o
   scope seja a raiz da origem (seção 16.1).

   Os marcadores abaixo são substituidos na entrega pelo PHP: o Service Worker
   não pode ler opções do WordPress, então a URL do endpoint de eventos é
   injetada no momento em que o arquivo é servido.
   ========================================================================= */

const EP_VERSION = '__ENCONTRA_PUSH_VERSION__';
const EP_EVENT_URL = '__ENCONTRA_PUSH_EVENT_URL__';

/*
 * skipWaiting + clients.claim: sem isso, uma correcao no Service Worker só
 * valeria depois que o visitante fechasse TODAS as abas do site — o que pode
 * levar dias. Aqui não há cache de assets, então assumir o controle
 * imediatamente não corre o risco de servir mistura de versões.
 */
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

/* ----------------------------------------------------------------- push -- */

self.addEventListener('push', (event) => {
    event.waitUntil(handlePush(event));
});

async function handlePush(event) {
    const payload = readPayload(event);

    /*
     * Toda mensagem PRECISA resultar em uma notificação visível. Navegadores
     * revogam a permissão de push de origens que recebem mensagens e não
     * mostram nada ("silent push"). Por isso, mesmo com payload corrompido,
     * exibimos um aviso genérico em vez de simplesmente sair.
     */
    if (!payload) {
        await self.registration.showNotification('Novidades', {
            body: 'Toque para ver as novidades do site.',
            tag: 'encontra-push-fallback',
        });

        return;
    }

    // Seção 37.4: beacon de "received", melhor esforço.
    const receivedBeacon = sendEvent('received', payload);

    const options = {
        body: payload.body || '',
        icon: payload.icon || undefined,
        badge: payload.badge || undefined,
        image: payload.image || undefined,
        tag: payload.tag || undefined,
        renotify: Boolean(payload.renotify && payload.tag),
        requireInteraction: Boolean(payload.requireInteraction),
        timestamp: Date.now(),
        data: {
            url: payload.url,
            message_id: payload.message_id,
            campaign_id: payload.campaign_id,
            event_token: payload.data && payload.data.event_token,
        },
    };

    // Seção 108: actions são opcionais; navegador sem suporte simplesmente
    // ignora a propriedade.
    if (Array.isArray(payload.actions) && payload.actions.length) {
        options.actions = payload.actions.slice(0, 2);
    }

    await self.registration.showNotification(payload.title || 'Novidades', options);

    // Seção 37.5: "shown" só é registrado DEPOIS que showNotification
    // resolveu — antes disso, nada garante que a notificação apareceu.
    await Promise.all([receivedBeacon, sendEvent('shown', payload)]);
}

function readPayload(event) {
    if (!event.data) {
        return null;
    }

    try {
        return event.data.json();
    } catch (e) {
        // Payload em texto puro: ainda da para mostrar algo útil.
        try {
            const text = event.data.text();

            return text ? { title: 'Novidades', body: text } : null;
        } catch (err) {
            return null;
        }
    }
}

/* ------------------------------------------------------ notificationclick */

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data || {};
    const url = data.url;

    event.waitUntil(
        Promise.all([
            sendEvent('clicked', { message_id: data.message_id, data: { event_token: data.event_token } }),
            openTarget(url),
        ])
    );
});

/*
 * Abre a página de destino reaproveitando uma aba do site, se houver.
 * Abrir uma aba nova a cada clique enche o navegador do visitante de
 * duplicatas do mesmo site.
 */
async function openTarget(url) {
    if (!url) {
        return;
    }

    const clientList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

    let target;

    try {
        target = new URL(url);
    } catch (e) {
        return;
    }

    // Só páginas web: nunca javascript:, data: ou file: (SEC-019).
    if (target.protocol !== 'https:' && target.protocol !== 'http:') {
        return;
    }

    for (const client of clientList) {
        let current;

        try {
            current = new URL(client.url);
        } catch (e) {
            continue;
        }

        if (current.origin === target.origin && 'focus' in client) {
            await client.focus();

            if ('navigate' in client && current.href !== target.href) {
                return client.navigate(target.href);
            }

            return;
        }
    }

    if (self.clients.openWindow) {
        return self.clients.openWindow(target.href);
    }
}

/* ------------------------------------------------------ notificationclose */

/*
 * Seção 16.3: notificationclose e tratado como melhor esforço. O suporte e o
 * comportamento variam bastante entre navegadores — em alguns o evento nem
 * dispara quando a notificação expira sozinha.
 */
self.addEventListener('notificationclose', (event) => {
    const data = event.notification.data || {};

    event.waitUntil(
        sendEvent('closed', { message_id: data.message_id, data: { event_token: data.event_token } })
    );
});

/* ------------------------------------------------------- pushsubscriptionchange */

/*
 * O navegador pode trocar a subscription sozinho (rotação do Push Service,
 * limpeza de dados). Sem tratar este evento, o assinante some da base sem
 * aviso e o painel só descobre quando o envio falhar com 410.
 */
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(renewSubscription(event));
});

async function renewSubscription(event) {
    try {
        const oldSubscription = event.oldSubscription || (await self.registration.pushManager.getSubscription());

        const applicationServerKey = event.oldSubscription && event.oldSubscription.options
            ? event.oldSubscription.options.applicationServerKey
            : null;

        if (!applicationServerKey) {
            return;
        }

        const fresh = await self.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey,
        });

        const json = fresh.toJSON();
        const base = EP_EVENT_URL.replace(/\/event$/, '');

        await fetch(base + '/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                endpoint: json.endpoint,
                p256dh: json.keys && json.keys.p256dh,
                auth: json.keys && json.keys.auth,
                source_url: self.registration.scope,
            }),
            keepalive: true,
        });

        if (oldSubscription && oldSubscription.endpoint !== json.endpoint) {
            await fetch(base + '/unsubscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint: oldSubscription.endpoint }),
                keepalive: true,
            });
        }
    } catch (e) {
        // Nada a fazer: o próximo envio falhara com erro permanente e o
        // painel invalidara a subscription (seção 36.2).
    }
}

/* ---------------------------------------------------------------- beacon -- */

/**
 * Seção 40: os eventos vão para o PROPRIO site (first-party), que reencaminha
 * ao painel. O Service Worker nunca conhece credencial do painel.
 *
 * Seção 90: cada beacon carrega um event_id único; o painel usa esse id para
 * não contar duas vezes o mesmo evento se a rede fizer o fetch repetir.
 */
function sendEvent(type, payload) {
    const token = payload && payload.data && payload.data.event_token;

    if (!token || !EP_EVENT_URL) {
        return Promise.resolve();
    }

    const body = JSON.stringify({
        events: [
            {
                type: type,
                token: token,
                event_id: generateEventId(),
                event_at: new Date().toISOString(),
            },
        ],
    });

    return fetch(EP_EVENT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body,
        // keepalive permite que a requisição sobreviva ao encerramento do
        // Service Worker, que acontece poucos segundos após o evento.
        keepalive: true,
        credentials: 'omit',
    }).catch(() => {
        // Beacon e melhor esforço: perder um evento de métrica jamais pode
        // impedir a notificação de ser exibida.
    });
}

/** ULID simplificado: ordenavel por tempo e sem colisao prática. */
function generateEventId() {
    const alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    let timestamp = Date.now();
    let id = '';

    for (let i = 0; i < 10; i++) {
        id = alphabet[timestamp % 32] + id;
        timestamp = Math.floor(timestamp / 32);
    }

    const random = new Uint8Array(16);
    (self.crypto || {}).getRandomValues && self.crypto.getRandomValues(random);

    for (let i = 0; i < 16; i++) {
        id += alphabet[random[i] % 32];
    }

    return id;
}
