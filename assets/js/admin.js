/* =========================================================================
   Encontra Push — telas do wp-admin.

   Faz o que só o navegador consegue: inspecionar Service Workers registrados,
   confirmar suporte a Push e criar a subscription de teste (seção 58).
   ========================================================================= */

(function () {
    'use strict';

    var config = window.EncontraPushAdmin;

    if (!config) {
        return;
    }

    /* --------------------------------------------- diagnóstico no browser */

    var diagnosticsRoot = document.getElementById('ep-browser-diagnostics');

    if (diagnosticsRoot) {
        runBrowserDiagnostics(diagnosticsRoot);
    }

    async function runBrowserDiagnostics(root) {
        var checks = [];

        var hasSw = 'serviceWorker' in navigator;
        var hasPush = 'PushManager' in window;
        var hasNotification = 'Notification' in window;

        checks.push(item('Service Worker suportado', hasSw));
        checks.push(item('PushManager suportado', hasPush));
        checks.push(item('Notification API suportada', hasNotification));

        if (hasNotification) {
            checks.push(item(
                'Permissão neste navegador: ' + Notification.permission,
                Notification.permission !== 'denied',
                Notification.permission === 'default'
                    ? 'Ainda não solicitada neste navegador.'
                    : ''
            ));
        }

        if (hasSw) {
            /*
             * Seção 17.1: listar TODOS os registros, não apenas o nosso.
             * Um Service Worker de PWA ou de cache controlando o scope raiz é
             * exatamente o conflito que a especificação manda detectar antes
             * de sobrescrever qualquer coisa.
             */
            var registrations = await navigator.serviceWorker.getRegistrations();

            var ours = null;
            var others = [];

            registrations.forEach(function (registration) {
                var worker = registration.active || registration.installing || registration.waiting;
                var script = worker ? worker.scriptURL : '';

                if (script.indexOf('encontra-push-sw.js') !== -1) {
                    ours = registration;
                } else if (script) {
                    others.push(script);
                }
            });

            checks.push(item(
                'Encontra Push registrado',
                Boolean(ours),
                ours ? 'Scope: ' + ours.scope : 'O Service Worker do plugin ainda não foi registrado neste navegador.'
            ));

            if (others.length) {
                checks.push(item(
                    'Outro Service Worker detectado',
                    false,
                    others.join(' · '),
                    'warning'
                ));
            } else {
                checks.push(item('Nenhum conflito de Service Worker', true));
            }

            var rootController = navigator.serviceWorker.controller;

            checks.push(item(
                'Controle do scope raiz',
                Boolean(rootController && rootController.scriptURL.indexOf('encontra-push-sw.js') !== -1),
                rootController ? rootController.scriptURL : 'Nenhum Service Worker controla esta página ainda.',
                'warning'
            ));
        }

        checks.push(item(
            'Contexto seguro (HTTPS)',
            window.isSecureContext,
            'O Web Push exige origem segura.'
        ));

        render(root, checks);
        report(checks);
    }

    function item(label, ok, hint, failureLevel) {
        return {
            label: label,
            status: ok ? 'ok' : (failureLevel || 'error'),
            hint: hint || '',
        };
    }

    function render(root, checks) {
        root.innerHTML = '';

        checks.forEach(function (check) {
            var row = document.createElement('div');
            row.className = 'ep-check';

            var mark = document.createElement('span');
            mark.className = 'ep-check__mark ep-check__mark--' + check.status;
            mark.textContent = check.status === 'ok' ? '\u2713' : (check.status === 'warning' ? '!' : '\u2717');

            var body = document.createElement('div');

            var label = document.createElement('strong');
            label.textContent = check.label;
            body.appendChild(label);

            if (check.hint) {
                var hint = document.createElement('div');
                hint.className = 'ep-check__hint';
                hint.textContent = check.hint;
                body.appendChild(hint);
            }

            row.appendChild(mark);
            row.appendChild(body);
            root.appendChild(row);
        });
    }

    /** Envia o resultado ao painel, para a aba Diagnóstico do site. */
    function report(checks) {
        var payload = {
            third_party_sw: checks.some(function (c) {
                return c.label === 'Outro Service Worker detectado';
            }),
            service_worker: checks.some(function (c) {
                return c.label === 'Encontra Push registrado' && c.status === 'ok';
            }),
            push_manager: 'PushManager' in window,
            notification_api: 'Notification' in window,
            https: window.isSecureContext,
        };

        // Guardado em campo oculto e enviado junto com o formulário da tela,
        // para não precisar de mais uma rota REST só para isto.
        var field = document.getElementById('ep-browser-checks');

        if (field) {
            field.value = JSON.stringify(payload);
        }
    }

    /* ------------------------------------------------- teste de push ----- */

    var testButton = document.getElementById('ep-test-push');

    if (testButton) {
        testButton.addEventListener('click', async function () {
            var output = document.getElementById('ep-test-result');

            output.textContent = 'Preparando a subscription de teste…';

            try {
                if (Notification.permission !== 'granted') {
                    var permission = await Notification.requestPermission();

                    if (permission !== 'granted') {
                        output.textContent = 'Você precisa permitir as notificações neste navegador para testar.';
                        return;
                    }
                }

                var registration = await navigator.serviceWorker.register(config.serviceWorker, { scope: '/' });
                await navigator.serviceWorker.ready;

                var subscription = await registration.pushManager.getSubscription();

                if (!subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(config.vapidPublicKey),
                    });
                }

                var json = subscription.toJSON();

                // Registra a subscription de teste antes de pedir o envio:
                // o painel precisa conhece-la para poder cifrar a mensagem.
                await fetch(config.restUrl + 'subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce,
                    },
                    body: JSON.stringify({
                        endpoint: json.endpoint,
                        p256dh: json.keys.p256dh,
                        auth: json.keys.auth,
                        is_test: true,
                        source_url: window.location.href,
                    }),
                });

                output.textContent = 'Enviando…';

                var response = await fetch(config.restUrl + 'test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce,
                    },
                    body: JSON.stringify({ endpoint: json.endpoint }),
                });

                var data = await response.json();

                output.textContent = data.message || (response.ok ? 'Enviado.' : 'Falha no envio.');
            } catch (e) {
                output.textContent = 'Erro: ' + e.message;
            }
        });
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

    /* ---------------------------------------------- prévia do pré-prompt - */

    var previewTitle = document.getElementById('ep-prompt-title');
    var previewBody = document.getElementById('ep-prompt-body');

    function bindPreview(input, target) {
        if (!input || !target) return;

        var update = function () {
            target.textContent = input.value || input.placeholder || '';
        };

        input.addEventListener('input', update);
        update();
    }

    bindPreview(previewTitle, document.querySelector('[data-preview-title]'));
    bindPreview(previewBody, document.querySelector('[data-preview-body]'));
})();
