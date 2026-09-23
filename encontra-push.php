<?php
/**
 * Plugin Name:       Encontra Push
 * Plugin URI:        https://push.encontra.com.br
 * Description:       Conecta este site ao painel Encontra Push: captura de assinantes, Service Worker, AutoPush por publicacao, rastreio de clique e metricas no proprio wp-admin.
 * Version:           1.0.14
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Encontra
 * Author URI:        https://encontra.com.br
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       encontra-push
 * Domain Path:       /languages
 * Update URI:        https://github.com/cadastrux/encontra-push
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

define( 'ENCONTRA_PUSH_VERSION', '1.0.14' );
define( 'ENCONTRA_PUSH_FILE', __FILE__ );
define( 'ENCONTRA_PUSH_DIR', plugin_dir_path( __FILE__ ) );
define( 'ENCONTRA_PUSH_URL', plugin_dir_url( __FILE__ ) );
define( 'ENCONTRA_PUSH_SLUG', 'encontra-push' );

/**
 * Versao do schema local. Incrementar sempre que uma opcao mudar de formato:
 * o upgrade roda migrations locais versionadas (secao 103 da especificacao).
 */
define( 'ENCONTRA_PUSH_DB_VERSION', 1 );

/** Caminho publico do Service Worker (secao 16.1). */
define( 'ENCONTRA_PUSH_SW_PATH', 'encontra-push-sw.js' );

require_once ENCONTRA_PUSH_DIR . 'includes/class-settings.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-auth.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-api-client.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-service-worker.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-subscription.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-rest.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-publisher.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-tracking.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-diagnostics.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-widget.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-frontend.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-metrics.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-heartbeat.php';
require_once ENCONTRA_PUSH_DIR . 'includes/class-updater.php';

if ( is_admin() ) {
	require_once ENCONTRA_PUSH_DIR . 'admin/class-admin.php';
	require_once ENCONTRA_PUSH_DIR . 'admin/class-metabox.php';
}

/**
 * Carregador do plugin.
 *
 * Instancia os modulos e registra os ganchos. Cada modulo cuida de um assunto
 * e nao conhece os detalhes dos outros — a conversa passa sempre pelo cliente
 * de API ou pelas opcoes.
 */
final class Encontra_Push {

	private static ?Encontra_Push $instance = null;

	public Encontra_Push_Settings $settings;
	public Encontra_Push_Api_Client $api;
	public Encontra_Push_Service_Worker $service_worker;
	public Encontra_Push_Rest $rest;
	public Encontra_Push_Publisher $publisher;
	public Encontra_Push_Tracking $tracking;
	public Encontra_Push_Diagnostics $diagnostics;
	public Encontra_Push_Widget $widget;
	public Encontra_Push_Frontend $frontend;
	public Encontra_Push_Metrics $metrics;
	public Encontra_Push_Heartbeat $heartbeat;
	public Encontra_Push_Updater $updater;

	public static function instance(): Encontra_Push {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->settings       = new Encontra_Push_Settings();
		$this->api            = new Encontra_Push_Api_Client( $this->settings );
		$this->service_worker = new Encontra_Push_Service_Worker( $this->settings );
		$this->metrics        = new Encontra_Push_Metrics( $this->api );
		$this->rest           = new Encontra_Push_Rest( $this->settings, $this->api );
		$this->publisher      = new Encontra_Push_Publisher( $this->settings, $this->api );
		$this->tracking       = new Encontra_Push_Tracking( $this->settings );
		$this->diagnostics    = new Encontra_Push_Diagnostics( $this->settings, $this->api );
		$this->widget         = new Encontra_Push_Widget();
		$this->frontend       = new Encontra_Push_Frontend( $this->settings, $this->widget );
		$this->heartbeat      = new Encontra_Push_Heartbeat( $this->settings, $this->api );
		$this->updater        = new Encontra_Push_Updater();

		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ) );

		$this->service_worker->register();
		$this->rest->register();
		$this->publisher->register();
		$this->tracking->register();
		$this->widget->register();
		$this->frontend->register();
		$this->heartbeat->register();
		$this->updater->register();

		if ( is_admin() ) {
			( new Encontra_Push_Admin( $this ) )->register();
			( new Encontra_Push_Metabox( $this->settings ) )->register();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'encontra-push', false, dirname( plugin_basename( ENCONTRA_PUSH_FILE ) ) . '/languages' );
	}

	/**
	 * Secao 103: a atualizacao executa migrations locais versionadas.
	 *
	 * Roda no init e nao no hook de ativacao porque uma atualizacao por FTP ou
	 * por artefato nao dispara ativacao — e o schema precisa acompanhar o
	 * codigo de qualquer forma.
	 */
	public function maybe_upgrade(): void {
		/*
		 * Versao nova do plugin = scripts, estilos e dados embutidos na pagina
		 * mudaram. O cache de pagina (o LiteSpeed guarda versoes separadas para
		 * celular e computador) continuaria servindo o HTML antigo — foi assim
		 * que o sino de noticias apareceu no computador e nao no celular.
		 */
		if ( get_option( 'encontra_push_version' ) !== ENCONTRA_PUSH_VERSION ) {
			update_option( 'encontra_push_version', ENCONTRA_PUSH_VERSION, true );
			self::purge_page_cache();
		}

		$installed = (int) get_option( 'encontra_push_db_version', 0 );

		if ( $installed === ENCONTRA_PUSH_DB_VERSION ) {
			return;
		}

		// As migrations futuras entram aqui, uma a uma, comparando $installed.

		update_option( 'encontra_push_db_version', ENCONTRA_PUSH_DB_VERSION, false );

		// O caminho do Service Worker depende das regras de reescrita.
		flush_rewrite_rules();
	}

	/**
	 * Limpa o cache de pagina dos plugins mais comuns.
	 *
	 * A configuracao do front-end (chave VAPID, textos, cores, sino) vai
	 * embutida no HTML de cada pagina; com cache ativo, o visitante continuaria
	 * recebendo a versao velha. Cada chamada so acontece se o respectivo plugin
	 * de cache estiver instalado.
	 */
	public static function purge_page_cache(): void {
		// LiteSpeed Cache.
		do_action( 'litespeed_purge_all' );

		// WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// WP Fastest Cache.
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
		}

		// Autoptimize guarda os scripts combinados.
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
		}
	}

	/* --------------------------------------------------------------------- */

	/**
	 * Ativacao: capabilities proprias (secao 99) e regras de reescrita.
	 *
	 * Nao criamos nenhuma tabela: o plugin nao guarda assinantes localmente —
	 * a base vive no painel, e o site so intermedia.
	 */
	public static function activate(): void {
		self::grant_capabilities();

		add_option( 'encontra_push_db_version', ENCONTRA_PUSH_DB_VERSION, '', false );

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'encontra_push_heartbeat' );
		wp_clear_scheduled_hook( 'encontra_push_flush_events' );

		flush_rewrite_rules();
	}

	/**
	 * Secao 99: capabilities proprias em vez de depender so de manage_options.
	 *
	 * Assim um Editor pode criar campanha sem receber, junto, o direito de
	 * mexer nas configuracoes do WordPress inteiro.
	 */
	public static function grant_capabilities(): void {
		$map = array(
			'administrator' => array(
				'encontra_push_view',
				'encontra_push_manage',
				'encontra_push_campaign',
				'encontra_push_settings',
			),
			'editor'        => array(
				'encontra_push_view',
				'encontra_push_campaign',
			),
		);

		foreach ( $map as $role_name => $capabilities ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}

	public static function revoke_capabilities(): void {
		$capabilities = array(
			'encontra_push_view',
			'encontra_push_manage',
			'encontra_push_campaign',
			'encontra_push_settings',
		);

		foreach ( wp_roles()->roles as $role_name => $details ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}
}

register_activation_hook( __FILE__, array( 'Encontra_Push', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Encontra_Push', 'deactivate' ) );

/**
 * Acesso global ao plugin.
 */
function encontra_push(): Encontra_Push {
	return Encontra_Push::instance();
}

add_action( 'plugins_loaded', 'encontra_push' );
