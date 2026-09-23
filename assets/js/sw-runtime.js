/* =========================================================================
   Encontra Push — runtime para o MODO B (integração).

   Seção 17.2. Quando o site já tem PWA, Workbox, Firebase ou outro Service
   Worker no scope raiz, o plugin NÃO registra o próprio. Em vez disso, a
   equipe do site inclui este arquivo no Service Worker existente:

       importScripts('.../encontra-push/assets/js/sw-runtime.js');

   Diferencas em relação ao runtime do modo A:

     - não chama skipWaiting nem clients.claim, porque o ciclo de vida
       pertence ao Service Worker do site;
     - descobre a URL do endpoint de eventos a partir do próprio scope, já
       que aqui não há substituição feita pelo PHP;
     - ignora mensagens push que não sejam do Encontra Push, para conviver
       com outros remetentes no mesmo Service Worker.
   ========================================================================= */

(function () {
    'use strict';

    var EVENT_URL = new URL('wp-json/encontra-push/v1/event', self.registration.scope).href;

    self.addEventListener('push', function (event) {
        var payload = null;

        try {
            payload = event.data ? event.data.json() : null;
        } catch (e) {
            return; // payload de outro remetente
        }

        // Assinatura mínima de uma mensagem nossa. Sem isto, este listener
        // sequestraria as notificações de outros serviços do mesmo site.
        if (!payload || payload.v !== 1 || !payload.message_id) {
            return;
        }

        event.waitUntil(show(payload));
    });

    function show(payload) {
        var received = beacon('received', payload.data && payload.data.event_token);

        var options = {
            body: payload.body || '',
            icon: payload.icon || undefined,
            badge: payload.badge || undefined,
            image: payload.image || undefined,
            tag: payload.tag || undefined,
            renotify: Boolean(payload.renotify && payload.tag),
            requireInteraction: Boolean(payload.requireInteraction),
            data: {
                url: payload.url,
                message_id: payload.message_id,
                event_token: payload.data && payload.data.event_token,
                encontra: true,
            },
        };

        if (Array.isArray(payload.actions) && payload.actions.length) {
            options.actions = payload.actions.slice(0, 2);
        }

        return self.registration
            .showNotification(payload.title || 'Novidades', options)
            .then(function () {
                return Promise.all([received, beacon('shown', payload.data && payload.data.event_token)]);
            });
    }

    self.addEventListener('notificationclick', function (event) {
        var data = event.notification.data || {};

        // Notificação de outro serviço: não interferir.
        if (!data.encontra) {
            return;
        }

        event.notification.close();

        event.waitUntil(
            Promise.all([
                beacon('clicked', data.event_token),
                data.url ? openTarget(data.url) : Promise.resolve(),
            ])
        );
    });

    self.addEventListener('notificationclose', function (event) {
        var data = event.notification.data || {};

        if (!data.encontra) {
            return;
        }

        event.waitUntil(beacon('closed', data.event_token));
    });

    function openTarget(url) {
        return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            var target;

            try {
                target = new URL(url);
            } catch (e) {
                return;
            }

            // Só páginas web: nunca javascript:, data: ou file: (SEC-019).
            if (target.protocol !== 'https:' && target.protocol !== 'http:') {
                return;
            }

            for (var i = 0; i < list.length; i++) {
                var client = list[i];

                if (client.url.indexOf(target.origin) === 0 && 'focus' in client) {
                    return client.focus().then(function () {
                        return 'navigate' in client ? client.navigate(target.href) : undefined;
                    });
                }
            }

            return self.clients.openWindow ? self.clients.openWindow(target.href) : undefined;
        });
    }

    function beacon(type, token) {
        if (!token) {
            return Promise.resolve();
        }

        return fetch(EVENT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                events: [{ type: type, token: token, event_at: new Date().toISOString() }],
            }),
            keepalive: true,
            credentials: 'omit',
        }).catch(function () { /* melhor esforço */ });
    }
})();
