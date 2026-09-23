<?php
/**
 * REST API local do plugin.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 60 — REST API WordPress: /wp-json/encontra-push/v1/
 *
 * Toda comunicacao do navegador acontece aqui, e nao diretamente com o painel
 * (secao 40). Tres ganhos concretos:
 *
 *  - nao ha CORS a resolver, porque a chamada e para a propria origem;
 *  - o trafego continua first-party, entao bloqueadores e politicas de
 *    cookies de terceiros nao interferem;
 *  - nenhuma credencial do painel precisa existir no front-end.
 *
 * As rotas publicas (subscribe, unsubscribe, event) nao aceitam nenhuma
 * credencial administrativa e tem limite de requisicoes por IP.
 */
class Encontra_Push_Rest {

	private const NAMESPACE = 'encontra-push/v1';

	public function __construct(
		private Encontra_Push_Settings $settings,
		private Encontra_Push_Api_Client $api
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		// Publica: configuracao do front-end. Nunca expoe segredo.
		register_rest_route(
			self::NAMESPACE,
			'/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'config' ),
				'permission_callback' => '__return_true',
			)
		);

		// Secao 11.3: o painel consulta esta rota para validar o dominio.
		register_rest_route(
			self::NAMESPACE,
			'/verify',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'verify' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/subscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'subscribe' ),
				'permission_callback' => array( $this, 'public_rate_limit' ),
				'args'                => $this->subscription_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/unsubscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'unsubscribe' ),
				'permission_callback' => array( $this, 'public_rate_limit' ),
			)
		);

		// Secao 40: beacons do Service Worker chegam aqui e sao reencaminhados.
		register_rest_route(
			self::NAMESPACE,
			'/event',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'event' ),
				'permission_callback' => array( $this, 'public_rate_limit' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/optin',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'optin' ),
				'permission_callback' => array( $this, 'public_rate_limit' ),
			)
		);

		// Administrativa: exige capability e nonce REST (secoes 60 e 100).
		register_rest_route(
			self::NAMESPACE,
			'/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);
	}

	/* ------------------------------------------------------- Permissoes -- */

	/**
	 * Secao 87 — rotas publicas precisam limitar abuso.
	 *
	 * Transient por IP: simples e suficiente para o volume de um site. O
	 * painel aplica o proprio limite por credencial em cima disso.
	 */
	public function public_rate_limit( WP_REST_Request $request ) {
		$key   = 'ep_rl_' . md5( $this->client_ip() . '|' . $request->get_route() );
		$count = (int) get_transient( $key );

		if ( $count >= 60 ) {
			return new WP_Error(
				'rate_limited',
				__( 'Muitas requisições. Tente novamente em instantes.', 'encontra-push' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	public function admin_permission(): bool {
		return current_user_can( 'encontra_push_manage' );
	}

	/* ---------------------------------------------------------- Handlers - */

	public function config(): WP_REST_Response {
		$prompt = $this->settings->prompt_config();

		return new WP_REST_Response(
			array(
				'connected'        => $this->settings->is_connected(),
				// Apenas a chave PUBLICA: a privada nunca sai do painel.
				'vapid_public_key' => $this->settings->vapid_public_key(),
				'service_worker'   => home_url( '/' . ENCONTRA_PUSH_SW_PATH ),
				'integration_mode' => $this->settings->integration_mode(),
				'prompt'           => $prompt,
				'appearance'       => $this->settings->appearance_config(),
				'endpoints'        => array(
					'subscribe'   => rest_url( self::NAMESPACE . '/subscribe' ),
					'unsubscribe' => rest_url( self::NAMESPACE . '/unsubscribe' ),
					'event'       => rest_url( self::NAMESPACE . '/event' ),
					'optin'       => rest_url( self::NAMESPACE . '/optin' ),
				),
			)
		);
	}

	/**
	 * Secao 11.3 — challenge de validacao do dominio.
	 *
	 * Devolve apenas o challenge e a versao do plugin. Nenhum dado do site,
	 * nenhuma credencial: e uma rota publica por necessidade.
	 */
	public function verify(): WP_REST_Response {
		$response = new WP_REST_Response(
			array(
				'challenge'      => $this->settings->challenge(),
				'plugin_version' => ENCONTRA_PUSH_VERSION,
				'site_url'       => home_url(),
			)
		);

		// A resposta muda a cada pareamento: cache de pagina nao pode guarda-la.
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Rota publica: so o que o diagnostico do painel usa (SEC-020).
	 *
	 * Versao do WordPress e do PHP ajudam quem procura uma falha conhecida para
	 * explorar — e o painel ja as recebe pelo heartbeat, que e autenticado.
	 */
	public function health(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'status'         => 'ok',
				'https'          => is_ssl(),
				'service_worker' => (bool) $this->settings->vapid_public_key(),
			)
		);
	}

	/**
	 * Secao 89 — o upsert e idempotente no painel, entao o reenvio da mesma
	 * subscription a cada visita nao duplica nada.
	 */
	public function subscribe( WP_REST_Request $request ) {
		$subscription = Encontra_Push_Subscription::from_request( $request );

		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		$result = $this->api->post(
			$this->api->site_path( '/subscriptions' ),
			$subscription,
			// Secao 88: a chave de idempotencia usa o hash do endpoint, que e
			// estavel para o mesmo navegador.
			array( 'Idempotency-Key' => 'sub-' . hash( 'sha256', $subscription['endpoint'] ) )
		);

		if ( ! $result['ok'] ) {
			return new WP_Error( 'subscribe_failed', $result['error'], array( 'status' => 502 ) );
		}

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'created' => (bool) ( $result['data']['created'] ?? false ),
			),
			201
		);
	}

	public function unsubscribe( WP_REST_Request $request ) {
		$endpoint = esc_url_raw( (string) $request->get_param( 'endpoint' ) );

		if ( '' === $endpoint ) {
			return new WP_Error( 'missing_endpoint', __( 'Endpoint ausente.', 'encontra-push' ), array( 'status' => 400 ) );
		}

		$result = $this->api->post( $this->api->site_path( '/subscriptions/unsubscribe' ), array( 'endpoint' => $endpoint ) );

		return new WP_REST_Response( array( 'ok' => $result['ok'] ), $result['ok'] ? 200 : 502 );
	}

	/**
	 * Secao 40 — eventos do Service Worker.
	 *
	 * O corpo carrega apenas tokens de curta finalidade; nenhum identificador
	 * de usuario. O plugin so reencaminha.
	 */
	public function event( WP_REST_Request $request ) {
		$events = $request->get_param( 'events' );

		if ( ! is_array( $events ) || array() === $events ) {
			return new WP_Error( 'empty_events', __( 'Nenhum evento informado.', 'encontra-push' ), array( 'status' => 400 ) );
		}

		$clean = array();

		foreach ( array_slice( $events, 0, 50 ) as $event ) {
			$type  = sanitize_key( (string) ( $event['type'] ?? '' ) );
			$token = sanitize_text_field( (string) ( $event['token'] ?? '' ) );

			if ( '' === $type || '' === $token ) {
				continue;
			}

			$clean[] = array(
				'type'     => $type,
				'token'    => $token,
				'event_id' => sanitize_text_field( (string) ( $event['event_id'] ?? '' ) ),
				'event_at' => sanitize_text_field( (string) ( $event['event_at'] ?? '' ) ),
			);
		}

		if ( array() === $clean ) {
			return new WP_REST_Response( array( 'ok' => true, 'recorded' => 0 ) );
		}

		$result = $this->api->post( '/api/v1/events', array( 'events' => $clean ) );

		return new WP_REST_Response(
			array(
				'ok'       => $result['ok'],
				'recorded' => (int) ( $result['data']['recorded'] ?? 0 ),
			),
			$result['ok'] ? 200 : 502
		);
	}

	/** Secao 112 — funil de opt-in, agregado. */
	public function optin( WP_REST_Request $request ) {
		$event = sanitize_key( (string) $request->get_param( 'event' ) );

		$allowed = array(
			'preprompt_shown',
			'preprompt_accept',
			'preprompt_dismiss',
			'native_permission_granted',
			'native_permission_denied',
		);

		if ( ! in_array( $event, $allowed, true ) ) {
			return new WP_REST_Response( array( 'ok' => true ) );
		}

		$this->api->post(
			$this->api->site_path( '/optin' ),
			array(
				'events' => array(
					array(
						'event'       => $event,
						'device_type' => sanitize_key( (string) $request->get_param( 'device_type' ) ),
						'count'       => 1,
					),
				),
			)
		);

		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/** Secao 58 — teste de push disparado pelo administrador. */
	public function test( WP_REST_Request $request ) {
		$endpoint = esc_url_raw( (string) $request->get_param( 'endpoint' ) );

		if ( '' === $endpoint ) {
			return new WP_Error( 'missing_endpoint', __( 'Endpoint ausente.', 'encontra-push' ), array( 'status' => 400 ) );
		}

		$result = $this->api->post( $this->api->site_path( '/test' ), array( 'endpoint' => $endpoint ) );

		return new WP_REST_Response(
			array(
				'ok'      => $result['ok'],
				'message' => $result['ok']
					? (string) ( $result['data']['message'] ?? __( 'Mensagem aceita pelo Push Service.', 'encontra-push' ) )
					: $result['error'],
			),
			$result['ok'] ? 200 : 422
		);
	}

	/* --------------------------------------------------------------------- */

	private function subscription_args(): array {
		return array(
			'endpoint' => array( 'required' => true, 'type' => 'string' ),
			'p256dh'   => array( 'required' => true, 'type' => 'string' ),
			'auth'     => array( 'required' => true, 'type' => 'string' ),
		);
	}

	/**
	 * IP apenas para rate limit, nunca persistido (secao 41).
	 */
	private function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
