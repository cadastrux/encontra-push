<?php
/**
 * Rastreio de clique vindo da notificacao.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 39 — tracking de clique.
 *
 * A notificacao aponta para o proprio site com ?epc=TOKEN. Quando a pagina
 * carrega, o plugin:
 *
 *   1. detecta o parametro;
 *   2. valida o formato;
 *   3. registra o clique no painel;
 *   4. remove o parametro da barra do navegador com history.replaceState.
 *
 * O passo 4 importa mais do que parece: sem ele, o visitante compartilha a URL
 * com o token de outra pessoa, e o link sujo acaba indexado pelos buscadores.
 *
 * O token nao carrega dado pessoal — e apenas uma chave opaca para um registro
 * no painel.
 */
class Encontra_Push_Tracking {

	public const PARAM = 'epc';

	public function __construct( private Encontra_Push_Settings $settings ) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		// Evita que a URL com token vire pagina canonica nos buscadores.
		add_action( 'wp_head', array( $this, 'canonical_hint' ), 1 );
	}

	public function enqueue(): void {
		if ( ! $this->has_token() || ! $this->settings->is_connected() ) {
			return;
		}

		wp_enqueue_script(
			'encontra-push-click',
			ENCONTRA_PUSH_URL . 'assets/js/click.js',
			array(),
			ENCONTRA_PUSH_VERSION,
			true
		);

		wp_localize_script(
			'encontra-push-click',
			'EncontraPushClick',
			array(
				'endpoint' => esc_url_raw( rest_url( 'encontra-push/v1/event' ) ),
				'param'    => self::PARAM,
			)
		);
	}

	public function canonical_hint(): void {
		if ( ! $this->has_token() ) {
			return;
		}

		echo '<meta name="robots" content="noindex, follow">' . "\n";
	}

	private function has_token(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- leitura de parametro publico.
		$token = isset( $_GET[ self::PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ) : '';

		return '' !== $token && self::is_valid_format( $token );
	}

	/**
	 * Secao 39.1, passo 2: validar o formato antes de qualquer coisa.
	 * O token e base64url de 24 bytes, entao tem 32 caracteres do alfabeto.
	 */
	public static function is_valid_format( string $token ): bool {
		return (bool) preg_match( '/^[A-Za-z0-9_-]{16,64}$/', $token );
	}
}
