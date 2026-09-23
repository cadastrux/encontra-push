<?php
/**
 * Entrega do Service Worker na raiz da origem.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secoes 16 e 17.
 *
 * O Service Worker precisa ser servido pela raiz do dominio para controlar o
 * scope "/". Em vez de escrever um arquivo fisico na raiz — o que exigiria
 * permissao de escrita e colidiria com outros plugins —, registramos uma
 * regra de reescrita e devolvemos o conteudo com os cabecalhos corretos.
 *
 * Isso tambem atende ao requisito de alta prioridade da secao 17: nada e
 * gravado por cima de arquivo existente. Se ja houver um /encontra-push-sw.js
 * fisico ou outro Service Worker na raiz, o arquivo real ganha, porque o
 * servidor o entrega antes de chegar ao WordPress.
 */
class Encontra_Push_Service_Worker {

	public function __construct( private Encontra_Push_Settings $settings ) {}

	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'parse_request', array( $this, 'maybe_serve' ) );

		// Secao 105: manter o Service Worker fora do cache agressivo.
		add_filter( 'litespeed_cache_optimize_js_excludes', array( $this, 'exclude_from_cache' ) );
		add_filter( 'rocket_exclude_js', array( $this, 'exclude_from_cache' ) );
	}

	public function add_rewrite(): void {
		add_rewrite_rule( '^' . ENCONTRA_PUSH_SW_PATH . '$', 'index.php?encontra_push_sw=1', 'top' );
	}

	public function add_query_var( array $vars ): array {
		$vars[] = 'encontra_push_sw';

		return $vars;
	}

	public function maybe_serve( WP $wp ): void {
		if ( empty( $wp->query_vars['encontra_push_sw'] ) ) {
			return;
		}

		$this->serve();
	}

	private function serve(): void {
		$runtime = ENCONTRA_PUSH_DIR . 'service-worker/runtime.js';

		if ( ! file_exists( $runtime ) ) {
			status_header( 404 );
			exit;
		}

		// O Cache-Control abaixo nao basta para plugins de cache de pagina,
		// que decidem pelos proprios sinais. Um Service Worker em cache
		// congelaria qualquer correcao nos navegadores dos visitantes.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		do_action( 'litespeed_control_set_nocache', 'encontra push: service worker' );
		header( 'X-LiteSpeed-Cache-Control: no-cache' );

		// Secao 16.2 — cabecalhos exigidos.
		status_header( 200 );
		header( 'Content-Type: application/javascript; charset=UTF-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Service-Worker-Allowed: /' );
		header( 'X-Content-Type-Options: nosniff' );

		$contents = (string) file_get_contents( $runtime );

		// O runtime precisa saber para onde mandar os beacons. A URL e do
		// PROPRIO site (secao 40): o Service Worker nunca fala com o painel.
		$contents = str_replace(
			array( '__ENCONTRA_PUSH_EVENT_URL__', '__ENCONTRA_PUSH_VERSION__' ),
			array( esc_url_raw( rest_url( 'encontra-push/v1/event' ) ), ENCONTRA_PUSH_VERSION ),
			$contents
		);

		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput -- JavaScript servido como arquivo.
		exit;
	}

	public function exclude_from_cache( $excludes ) {
		$excludes   = is_array( $excludes ) ? $excludes : array();
		$excludes[] = ENCONTRA_PUSH_SW_PATH;

		return $excludes;
	}

	public function url(): string {
		return home_url( '/' . ENCONTRA_PUSH_SW_PATH );
	}

	/**
	 * Trecho que o site em modo B deve incluir no proprio Service Worker.
	 */
	public function integration_snippet(): string {
		return "importScripts('" . ENCONTRA_PUSH_URL . "assets/js/sw-runtime.js');";
	}
}
