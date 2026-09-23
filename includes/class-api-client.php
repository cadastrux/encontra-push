<?php
/**
 * Cliente HTTP do painel central.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 59 — API central.
 *
 * Toda comunicacao com o painel passa por aqui. O metodo publico devolve
 * sempre a mesma forma de resultado, com "ok" booleano, para que quem chama
 * nunca precise interpretar WP_Error e codigo HTTP ao mesmo tempo.
 */
class Encontra_Push_Api_Client {

	private const TIMEOUT = 12;

	public function __construct( private Encontra_Push_Settings $settings ) {}

	/* --------------------------------------------------------- Pairing -- */

	/**
	 * Secao 12: troca o codigo de conexao pelas credenciais permanentes.
	 * Esta e a unica chamada que nao vai assinada — ainda nao ha segredo.
	 *
	 * @return array{ok: bool, data: array, error: string}
	 */
	public function pair( string $code, string $panel_url ): array {
		$body = wp_json_encode(
			array(
				'pairing_code'   => $code,
				'site_url'       => home_url(),
				'home_url'       => home_url(),
				'wp_version'     => get_bloginfo( 'version' ),
				'php_version'    => PHP_VERSION,
				'plugin_version' => ENCONTRA_PUSH_VERSION,
			)
		);

		$response = wp_remote_post(
			untrailingslashit( $panel_url ) . '/api/v1/pair',
			array(
				'timeout'     => self::TIMEOUT,
				// SEC-021: sem seguir redirect (o painel nunca redireciona a API) e
				// com o certificado sempre validado.
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => self::unsigned_headers(),
				'body'        => $body,
			)
		);

		return $this->normalize( $response );
	}

	/** Pede ao painel que busque o challenge na REST API deste site. */
	public function verify_pairing(): array {
		$body = wp_json_encode( array( 'site_id' => $this->settings->site_id() ) );

		$response = wp_remote_post(
			$this->settings->api_url() . '/api/v1/pair/verify',
			array(
				'timeout'     => self::TIMEOUT,
				// SEC-021: sem seguir redirect (o painel nunca redireciona a API) e
				// com o certificado sempre validado.
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => self::unsigned_headers(),
				'body'        => $body,
			)
		);

		return $this->normalize( $response );
	}

	/**
	 * User-agent unico em TODAS as chamadas ao painel.
	 *
	 * Sem isto, o pareamento saia com o user-agent padrao do WordPress, que
	 * varias regras de firewall (ModSecurity, Imunify360) tratam como suspeito
	 * em POST vindo de outro servidor. Com um identificador proprio, a
	 * liberacao no servidor do painel vira uma regra simples.
	 */
	public static function user_agent(): string {
		return 'EncontraPush/' . ENCONTRA_PUSH_VERSION . '; ' . home_url();
	}

	private static function unsigned_headers(): array {
		return array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'User-Agent'   => self::user_agent(),
		);
	}

	/* ------------------------------------------------- Chamadas assinadas */

	public function get( string $path, array $query = array() ): array {
		if ( ! empty( $query ) ) {
			$path .= '?' . http_build_query( $query );
		}

		return $this->request( 'GET', $path );
	}

	public function post( string $path, array $payload = array(), array $extra_headers = array() ): array {
		return $this->request( 'POST', $path, $payload, $extra_headers );
	}

	public function patch( string $path, array $payload = array() ): array {
		return $this->request( 'PATCH', $path, $payload );
	}

	public function put( string $path, array $payload = array() ): array {
		return $this->request( 'PUT', $path, $payload );
	}

	/** Atalho para gravar a configuracao do proprio site (secoes 55 e 56). */
	public function request_put( array $payload ): array {
		return $this->put( $this->site_path( '/config' ), $payload );
	}

	public function delete( string $path ): array {
		return $this->request( 'DELETE', $path );
	}

	/** Prefixo com o site desta instalacao, usado pela maioria das rotas. */
	public function site_path( string $suffix ): string {
		return '/api/v1/sites/' . rawurlencode( $this->settings->site_id() ) . $suffix;
	}

	/* --------------------------------------------------------------------- */

	private function request( string $method, string $path, array $payload = array(), array $extra_headers = array() ): array {
		if ( ! $this->settings->is_connected() ) {
			return array(
				'ok'    => false,
				'data'  => array(),
				'error' => __( 'Este site ainda não está conectado ao painel.', 'encontra-push' ),
			);
		}

		$body = empty( $payload ) ? '' : (string) wp_json_encode( $payload );

		// A assinatura cobre o caminho SEM query string, igual ao painel.
		$path_for_signature = strtok( $path, '?' );

		$headers = Encontra_Push_Auth::headers(
			$method,
			$path_for_signature,
			$body,
			$this->settings->site_id(),
			$this->settings->api_key_id(),
			$this->settings->api_secret()
		);

		$args = array(
			'method'      => $method,
			'timeout'     => self::TIMEOUT,
			// SEC-021: sem seguir redirect (o painel nunca redireciona a API) e
			// com o certificado sempre validado.
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array_merge(
				$headers,
				$extra_headers,
				array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'User-Agent'   => self::user_agent(),
				)
			),
		);

		if ( '' !== $body ) {
			$args['body'] = $body;
		}

		// Requisicao assinada vai para a API, nao para o painel. Os dois
		// coincidem por padrao; quando nao coincidirem, e aqui que a
		// diferenca importa.
		$response = wp_remote_request( $this->settings->api_url() . $path, $args );

		return $this->normalize( $response );
	}

	/**
	 * @param array|WP_Error $response
	 * @return array{ok: bool, status: int, data: array, error: string}
	 */
	private function normalize( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'status' => 0,
				'data'   => array(),
				'error'  => $response->get_error_message(),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data   = is_array( $data ) ? $data : array();

		if ( $status >= 200 && $status < 300 ) {
			return array(
				'ok'     => true,
				'status' => $status,
				'data'   => $data,
				'error'  => '',
			);
		}

		if ( isset( $data['message'] ) ) {
			return array(
				'ok'     => false,
				'status' => $status,
				'data'   => $data,
				'error'  => (string) $data['message'],
			);
		}

		return array(
			'ok'     => false,
			'status' => $status,
			'data'   => $data,
			'error'  => $this->describe_foreign_response( $response, $status ),
		);
	}

	/**
	 * Explica uma resposta de erro que NÃO veio do Encontra Push.
	 *
	 * Toda recusa da aplicacao volta em JSON com "message". Quando o corpo
	 * nao e JSON, quem respondeu foi algo no caminho — firewall de aplicacao
	 * (ModSecurity, Imunify360), proxy (Cloudflare) ou o proprio servidor web.
	 * Dizer isso poupa horas procurando o problema dentro do painel, onde ele
	 * nao esta: nesses casos a requisicao nem aparece no log da aplicacao.
	 *
	 * @param array $response Resposta do wp_remote_*.
	 */
	private function describe_foreign_response( $response, int $status ): string {
		$server = (string) wp_remote_retrieve_header( $response, 'server' );
		$body   = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) wp_remote_retrieve_body( $response ) ) ) );

		$message = sprintf(
			/* translators: %d: codigo HTTP */
			__( 'O servidor do painel recusou a requisição (HTTP %d) antes de ela chegar ao Encontra Push.', 'encontra-push' ),
			$status
		);

		if ( in_array( $status, array( 403, 406, 429, 503 ), true ) ) {
			$message .= ' ' . __( 'Isso costuma ser um firewall no servidor do painel (ModSecurity, Imunify360) ou um proxy como o Cloudflare bloqueando chamadas entre servidores.', 'encontra-push' );
		}

		if ( '' !== $server ) {
			/* translators: %s: cabecalho Server da resposta */
			$message .= ' ' . sprintf( __( 'Servidor: %s.', 'encontra-push' ), $server );
		}

		if ( '' !== $body ) {
			/* translators: %s: inicio do corpo da resposta */
			$message .= ' ' . sprintf( __( 'Resposta: "%s"', 'encontra-push' ), mb_substr( $body, 0, 160 ) );
		}

		return $message;
	}
}
