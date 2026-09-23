<?php
/**
 * Heartbeat periodico com o painel.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 61 — heartbeat a cada 6 a 12 horas.
 *
 * Usamos WP-Cron aqui de proposito: e um sinal de vida leve, e o atraso tipico
 * do WP-Cron em site de baixo trafego nao prejudica nada. O que NÃO passa por
 * WP-Cron e o processamento de campanhas (secao 7.3) — isso vive no painel,
 * com Cron de verdade.
 */
class Encontra_Push_Heartbeat {

	private const HOOK = 'encontra_push_heartbeat';

	public function __construct(
		private Encontra_Push_Settings $settings,
		private Encontra_Push_Api_Client $api
	) {}

	public function register(): void {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'send' ) );
	}

	public function schedule(): void {
		if ( ! $this->settings->is_connected() ) {
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::HOOK );
		}
	}

	public function send(): void {
		if ( ! $this->settings->is_connected() ) {
			return;
		}

		$result = $this->api->post(
			$this->api->site_path( '/heartbeat' ),
			array(
				'plugin_version' => ENCONTRA_PUSH_VERSION,
				'wp_version'     => get_bloginfo( 'version' ),
				'php_version'    => PHP_VERSION,
				'service_worker' => $this->settings->vapid_public_key() ? 'ok' : 'unknown',
				'https'          => is_ssl(),
				'site_url'       => home_url(),
			)
		);

		if ( ! $result['ok'] ) {
			return;
		}

		/*
		 * Secao 77: o painel pode ter mudado o endereco da API. Aplicamos a
		 * mudanca ANTES de qualquer outra chamada desta rodada, para que a
		 * proxima ja saia pelo host novo.
		 *
		 * Atencao: o HMAC autentica a REQUISICAO, nao a resposta. Quem garante
		 * que a resposta veio do painel e o TLS (certificado validado, sem
		 * redirect). Por isso sync_api_url() ainda restringe o novo endereco ao
		 * dominio do painel (auditoria SEC-022).
		 */
		if ( ! empty( $result['data']['api_url'] ) ) {
			$this->settings->sync_api_url( (string) $result['data']['api_url'] );
		}

		// Aproveita a resposta para sincronizar a configuracao quando o
		// painel indicar que ela mudou — evita uma segunda chamada.
		$remote_updated = (string) ( $result['data']['config_updated_at'] ?? '' );
		$local_updated  = (string) ( $this->settings->state()['config_updated_at'] ?? '' );

		if ( '' !== $remote_updated && $remote_updated !== $local_updated ) {
			$config = $this->api->get( $this->api->site_path( '/config' ) );

			if ( $config['ok'] ) {
				$this->settings->save_config( $config['data'] );
				$this->settings->update_state( array( 'config_updated_at' => $remote_updated ) );
			}
		}
	}
}
