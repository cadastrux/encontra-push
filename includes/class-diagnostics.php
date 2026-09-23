<?php
/**
 * Diagnostico da integracao.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 57 — diagnostico do plugin.
 *
 * Metade das verificacoes so pode ser feita no servidor (HTTPS, REST, conexao
 * com o painel) e metade so no navegador (Service Worker ativo, conflito de
 * scope, PushManager). Esta classe cuida da parte do servidor; a parte do
 * navegador chega pelo JavaScript da tela de diagnostico e e reenviada ao
 * painel junto com o resto.
 */
class Encontra_Push_Diagnostics {

	public function __construct(
		private Encontra_Push_Settings $settings,
		private Encontra_Push_Api_Client $api
	) {}

	/**
	 * @return array<int, array{key: string, label: string, status: string, hint: string}>
	 */
	public function run(): array {
		$checks = array();

		$checks[] = $this->check(
			'https',
			__( 'HTTPS', 'encontra-push' ),
			is_ssl() || str_starts_with( home_url(), 'https://' ),
			__( 'O Web Push só funciona em origem segura. Sem HTTPS válido, o navegador nem registra o Service Worker.', 'encontra-push' )
		);

		$checks[] = $this->check(
			'connection',
			__( 'Conexão com o painel', 'encontra-push' ),
			$this->settings->is_connected(),
			__( 'Informe o código de conexão gerado no painel, em Encontra Push > Integração.', 'encontra-push' )
		);

		$checks[] = $this->check(
			'vapid',
			__( 'Chave VAPID', 'encontra-push' ),
			'' !== $this->settings->vapid_public_key(),
			__( 'A chave pública vem do painel no momento do pareamento.', 'encontra-push' )
		);

		$checks[] = $this->check(
			'rest',
			__( 'REST API do WordPress', 'encontra-push' ),
			$this->rest_reachable(),
			__( 'As rotas /wp-json/encontra-push/v1 precisam responder. Plugins de segurança costumam bloquear a REST API.', 'encontra-push' )
		);

		$checks[] = $this->check(
			'service_worker',
			__( 'Arquivo do Service Worker', 'encontra-push' ),
			$this->service_worker_reachable(),
			__( 'O arquivo precisa responder como application/javascript na raiz do domínio.', 'encontra-push' )
		);

		$checks[] = $this->check(
			'encryption',
			__( 'Criptografia local disponível', 'encontra-push' ),
			$this->settings->encryption_available(),
			__( 'Sem a extensão openssl, o segredo de API fica gravado em claro nas opções do WordPress.', 'encontra-push' )
		);

		$checks[] = $this->check(
			'panel',
			__( 'Painel respondendo', 'encontra-push' ),
			$this->panel_reachable(),
			__( 'Verifique se a hospedagem permite conexões HTTPS de saída para o painel.', 'encontra-push' )
		);

		$checks[] = $this->check(
			'cron',
			__( 'WP-Cron ativo', 'encontra-push' ),
			! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
			__( 'O WP-Cron é usado apenas para o heartbeat. O processamento de campanhas não depende dele.', 'encontra-push' ),
			'warning'
		);

		return $checks;
	}

	/** Envia o resultado ao painel, incluindo o que o navegador apurou. */
	public function report( array $browser_checks = array() ): void {
		if ( ! $this->settings->is_connected() ) {
			return;
		}

		$server = array();

		foreach ( $this->run() as $check ) {
			$server[ $check['key'] ] = 'ok' === $check['status'];
		}

		$this->api->post(
			$this->api->site_path( '/diagnostics' ),
			array( 'checks' => array_merge( $server, $browser_checks ) )
		);
	}

	/* --------------------------------------------------------------------- */

	private function check( string $key, string $label, bool $ok, string $hint, string $failure_status = 'error' ): array {
		return array(
			'key'    => $key,
			'label'  => $label,
			'status' => $ok ? 'ok' : $failure_status,
			'hint'   => $hint,
		);
	}

	private function rest_reachable(): bool {
		$response = wp_remote_get(
			rest_url( 'encontra-push/v1/health' ),
			array( 'timeout' => 8, 'sslverify' => false )
		);

		return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	private function service_worker_reachable(): bool {
		$response = wp_remote_get(
			home_url( '/' . ENCONTRA_PUSH_SW_PATH ),
			array( 'timeout' => 8, 'sslverify' => false )
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );

		return str_contains( $type, 'javascript' );
	}

	private function panel_reachable(): bool {
		$response = wp_remote_get(
			$this->settings->panel_url() . '/status',
			array( 'timeout' => 8 )
		);

		return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
	}
}
