<?php
/**
 * Carregamento do front-end de inscricao.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secoes 19 (solicitacao de permissao), 20 (iOS) e 115 (suporte do navegador).
 */
class Encontra_Push_Frontend {

	public function __construct(
		private Encontra_Push_Settings $settings,
		private Encontra_Push_Widget $widget
	) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'manifest_link' ), 2 );
		add_action( 'init', array( $this, 'manifest_route' ) );
	}

	public function enqueue(): void {
		if ( ! $this->settings->is_connected() ) {
			return;
		}

		$prompt = $this->settings->prompt_config();
		$widget = $this->widget->client_config();

		// Convite desligado E sino desligado: nada a carregar. Com o sino
		// ligado, o script vem mesmo sem o convite automatico.
		if ( 'disabled' === ( $prompt['mode'] ?? '' ) && null === $widget ) {
			return;
		}

		wp_enqueue_style(
			'encontra-push',
			ENCONTRA_PUSH_URL . 'assets/css/prompt.css',
			array(),
			ENCONTRA_PUSH_VERSION
		);

		wp_enqueue_script(
			'encontra-push',
			ENCONTRA_PUSH_URL . 'assets/js/subscribe.js',
			array(),
			ENCONTRA_PUSH_VERSION,
			true
		);

		wp_localize_script(
			'encontra-push',
			'EncontraPush',
			array(
				'vapidPublicKey'  => $this->settings->vapid_public_key(),
				'serviceWorker'   => home_url( '/' . ENCONTRA_PUSH_SW_PATH ),
				'integrationMode' => $this->settings->integration_mode(),
				'prompt'          => $prompt,
				'appearance'      => $this->settings->appearance_config(),
				'widget'          => $widget,
				'endpoints'       => array(
					'subscribe'   => esc_url_raw( rest_url( 'encontra-push/v1/subscribe' ) ),
					'unsubscribe' => esc_url_raw( rest_url( 'encontra-push/v1/unsubscribe' ) ),
					'optin'       => esc_url_raw( rest_url( 'encontra-push/v1/optin' ) ),
				),
				'i18n'            => array(
					'blocked'   => __( 'As notificações foram bloqueadas no navegador. Altere a permissão nas configurações do site.', 'encontra-push' ),
					'iosSteps'  => array(
						__( 'Abra o menu Compartilhar.', 'encontra-push' ),
						__( 'Toque em "Adicionar a Tela de Início".', 'encontra-push' ),
						__( 'Abra o site pelo ícone criado.', 'encontra-push' ),
						__( 'Toque em ativar notificações.', 'encontra-push' ),
					),
					'iosTitle'  => __( 'Para ativar notificações neste dispositivo', 'encontra-push' ),
					'close'     => __( 'Fechar', 'encontra-push' ),
				),
			)
		);
	}

	/* --------------------------------------------------------- Manifest -- */

	/**
	 * Secao 20.1 — manifest para o cenario de Web App instalado no iOS.
	 *
	 * Regra explicita da especificacao: se outro plugin ou tema ja fornece um
	 * manifest, NÃO substituir. Uma instalacao com PWA proprio perderia a
	 * identidade do app se sobrescrevessemos.
	 */
	public function manifest_link(): void {
		if ( ! $this->settings->is_connected() || $this->has_external_manifest() ) {
			return;
		}

		printf(
			'<link rel="manifest" href="%s">' . "\n",
			esc_url( home_url( '/encontra-push-manifest.json' ) )
		);
	}

	public function manifest_route(): void {
		add_rewrite_rule( '^encontra-push-manifest\.json$', 'index.php?encontra_push_manifest=1', 'top' );

		add_filter(
			'query_vars',
			static function ( array $vars ): array {
				$vars[] = 'encontra_push_manifest';

				return $vars;
			}
		);

		add_action(
			'parse_request',
			function ( WP $wp ): void {
				if ( empty( $wp->query_vars['encontra_push_manifest'] ) ) {
					return;
				}

				$icon = get_site_icon_url( 512 );

				status_header( 200 );
				header( 'Content-Type: application/manifest+json; charset=UTF-8' );

				echo wp_json_encode(
					array(
						'id'         => '/',
						'name'       => get_bloginfo( 'name' ),
						'short_name' => mb_substr( get_bloginfo( 'name' ), 0, 12 ),
						'start_url'  => '/',
						'display'    => 'standalone',
						'icons'      => $icon
							? array(
								array(
									'src'   => $icon,
									'sizes' => '512x512',
									'type'  => 'image/png',
								),
							)
							: array(),
					),
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				);

				exit;
			}
		);
	}

	/**
	 * Detecta manifest ja declarado por tema ou outro plugin de PWA.
	 */
	private function has_external_manifest(): bool {
		if ( has_action( 'wp_head', 'wp_site_icon' ) && function_exists( 'pwa_manifest_link' ) ) {
			return true;
		}

		/**
		 * Permite ao site informar que ja tem manifest proprio.
		 */
		return (bool) apply_filters( 'encontra_push_has_external_manifest', false );
	}
}
