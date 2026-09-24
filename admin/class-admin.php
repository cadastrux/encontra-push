<?php
/**
 * Telas do wp-admin.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 52 — menu do plugin, e secoes 53 a 58.
 *
 * Toda acao administrativa passa por duas barreiras (secoes 99 e 100):
 * capability propria e nonce. Nenhuma delas depende de manage_options
 * sozinho, para que um Editor consiga operar campanhas sem receber acesso as
 * configuracoes do WordPress inteiro.
 */
class Encontra_Push_Admin {

	private const PAGE = 'encontra-push';

	public function __construct( private Encontra_Push $plugin ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_encontra_push_connect', array( $this, 'handle_connect' ) );
		add_action( 'admin_post_encontra_push_disconnect', array( $this, 'handle_disconnect' ) );
		add_action( 'admin_post_encontra_push_refresh', array( $this, 'handle_refresh' ) );
		add_action( 'admin_post_encontra_push_verify', array( $this, 'handle_verify' ) );
		add_action( 'admin_post_encontra_push_prompt', array( $this, 'handle_prompt' ) );
		add_action( 'admin_post_encontra_push_appearance', array( $this, 'handle_appearance' ) );
		add_action( 'admin_post_encontra_push_widget', array( $this, 'handle_widget' ) );
		add_action( 'admin_post_encontra_push_diagnostics', array( $this, 'handle_diagnostics' ) );
		add_action( 'admin_post_encontra_push_check_update', array( $this, 'handle_check_update' ) );
		add_action( 'admin_post_encontra_push_uninstall_options', array( $this, 'handle_uninstall_options' ) );
		add_action( 'admin_notices', array( $this, 'connection_notice' ) );
	}

	public function menu(): void {
		add_menu_page(
			__( 'Encontra Push', 'encontra-push' ),
			__( 'Encontra Push', 'encontra-push' ),
			'encontra_push_view',
			self::PAGE,
			array( $this, 'render_dashboard' ),
			'dashicons-bell',
			58
		);

		$pages = array(
			'encontra-push'              => array( __( 'Dashboard', 'encontra-push' ), 'encontra_push_view', 'render_dashboard' ),
			'encontra-push-campanhas'    => array( __( 'Campanhas', 'encontra-push' ), 'encontra_push_view', 'render_campaigns' ),
			'encontra-push-assinantes'   => array( __( 'Assinantes', 'encontra-push' ), 'encontra_push_view', 'render_subscribers' ),
			'encontra-push-autopush'     => array( __( 'AutoPush', 'encontra-push' ), 'encontra_push_view', 'render_autopush' ),
			'encontra-push-solicitacao'  => array( __( 'Solicitação', 'encontra-push' ), 'encontra_push_manage', 'render_prompt' ),
			'encontra-push-aparencia'    => array( __( 'Aparência', 'encontra-push' ), 'encontra_push_manage', 'render_appearance' ),
			'encontra-push-sino'         => array( __( 'Sino de notícias', 'encontra-push' ), 'encontra_push_manage', 'render_widget' ),
			'encontra-push-integracao'   => array( __( 'Integração', 'encontra-push' ), 'encontra_push_settings', 'render_integration' ),
			'encontra-push-diagnostico'  => array( __( 'Diagnóstico', 'encontra-push' ), 'encontra_push_manage', 'render_diagnostics' ),
		);

		foreach ( $pages as $slug => $page ) {
			list( $title, $capability, $callback ) = $page;

			add_submenu_page(
				self::PAGE,
				$title . ' · ' . __( 'Encontra Push', 'encontra-push' ),
				$title,
				$capability,
				$slug,
				array( $this, $callback )
			);
		}
	}

	public function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'encontra-push' ) ) {
			return;
		}

		wp_enqueue_style(
			'encontra-push-admin',
			ENCONTRA_PUSH_URL . 'assets/css/admin.css',
			array(),
			ENCONTRA_PUSH_VERSION
		);

		wp_enqueue_script(
			'encontra-push-admin',
			ENCONTRA_PUSH_URL . 'assets/js/admin.js',
			array(),
			ENCONTRA_PUSH_VERSION,
			true
		);

		wp_localize_script(
			'encontra-push-admin',
			'EncontraPushAdmin',
			array(
				'restUrl'        => esc_url_raw( rest_url( 'encontra-push/v1/' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'vapidPublicKey' => $this->plugin->settings->vapid_public_key(),
				'serviceWorker'  => home_url( '/' . ENCONTRA_PUSH_SW_PATH ),
				'panelUrl'       => $this->plugin->settings->panel_url(),
			)
		);
	}

	/* ------------------------------------------------------------ Telas -- */

	public function render_dashboard(): void {
		$this->render( 'dashboard', array( 'metrics' => $this->plugin->metrics ) );
	}

	public function render_campaigns(): void {
		$this->render( 'campaigns', array( 'result' => $this->plugin->metrics->campaigns() ) );
	}

	public function render_subscribers(): void {
		$this->render( 'subscribers', array( 'result' => $this->plugin->metrics->subscribers() ) );
	}

	public function render_autopush(): void {
		$this->render( 'autopush', array() );
	}

	public function render_prompt(): void {
		$this->render( 'prompt', array( 'prompt' => $this->plugin->settings->prompt_config() ) );
	}

	public function render_appearance(): void {
		$this->render( 'appearance', array( 'appearance' => $this->plugin->settings->appearance_config() ) );
	}

	public function render_widget(): void {
		$this->render( 'widget', array( 'widget' => $this->plugin->widget->config() ) );
	}

	public function render_integration(): void {
		$this->render(
			'integration',
			array(
				'settings'       => $this->plugin->settings,
				'service_worker' => $this->plugin->service_worker,
			)
		);
	}

	public function render_diagnostics(): void {
		$this->render( 'diagnostics', array( 'checks' => $this->plugin->diagnostics->run() ) );
	}

	private function render( string $view, array $data ): void {
		$data['settings'] = $data['settings'] ?? $this->plugin->settings;
		$data['plugin']   = $this->plugin;

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- escopo controlado.
		extract( $data, EXTR_SKIP );

		require ENCONTRA_PUSH_DIR . 'admin/views/' . $view . '.php';
	}

	/* ----------------------------------------------------------- Acoes --- */

	/**
	 * Secao 12: consome o codigo de conexao e guarda as credenciais.
	 */
	public function handle_connect(): void {
		$this->authorize( 'encontra_push_settings', 'encontra_push_connect' );

		$code  = isset( $_POST['pairing_code'] ) ? sanitize_text_field( wp_unslash( $_POST['pairing_code'] ) ) : '';
		$panel = isset( $_POST['panel_url'] ) ? esc_url_raw( wp_unslash( $_POST['panel_url'] ) ) : '';
		$panel = '' !== $panel ? $panel : $this->plugin->settings->panel_url();

		if ( '' === $code ) {
			$this->redirect_back( 'error', __( 'Informe o código de conexão gerado no painel.', 'encontra-push' ) );
		}

		// O pareamento devolve o segredo de API: so por HTTPS (SEC-021).
		if ( ! str_starts_with( $panel, 'https://' ) ) {
			$this->redirect_back( 'error', __( 'O endereço do painel precisa começar com https://.', 'encontra-push' ) );
		}

		$result = $this->plugin->api->pair( $code, $panel );

		if ( ! $result['ok'] ) {
			$this->redirect_back( 'error', $result['error'] );
		}

		$data = $result['data'];

		/*
		 * O painel responde com os enderecos canonicos. Preferimos os dele
		 * aos digitados: quem instala costuma informar o endereco com "www",
		 * com barra no fim ou em http, e a assinatura HMAC nao perdoa
		 * divergencia de host.
		 */
		$this->plugin->settings->save_connection(
			array(
				'site_id'          => $data['site_id'] ?? '',
				'api_key_id'       => $data['api_key_id'] ?? '',
				'api_secret'       => $data['api_secret'] ?? '',
				'vapid_public_key' => $data['vapid_public_key'] ?? '',
				'panel_url'        => $data['panel_url'] ?? $panel,
				'api_url'          => $data['api_url'] ?? ( $data['panel_url'] ?? $panel ),
			)
		);

		// Secao 11.3: o challenge fica disponivel na rota /verify para que o
		// painel confirme que quem instalou o plugin controla este dominio.
		$this->plugin->settings->set_challenge( (string) ( $data['challenge'] ?? '' ) );

		// A validacao e o passo seguinte e pode ser repetida se falhar agora.
		$verify = $this->plugin->api->verify_pairing();

		// Sincroniza configuracao e taxonomias logo apos conectar.
		$config = $this->plugin->api->get( $this->plugin->api->site_path( '/config' ) );

		if ( $config['ok'] ) {
			$this->plugin->settings->save_config( $config['data'] );
		}

		$this->plugin->publisher->sync_taxonomies();

		flush_rewrite_rules();
		$this->purge_page_cache();

		$this->redirect_back(
			$verify['ok'] ? 'success' : 'warning',
			$verify['ok']
				? __( 'Conectado e domínio validado. O site já pode capturar assinantes.', 'encontra-push' )
				: __( 'Credenciais salvas, mas o painel ainda não validou o domínio. Use o botão "Validar domínio".', 'encontra-push' )
		);
	}

	public function handle_verify(): void {
		$this->authorize( 'encontra_push_settings', 'encontra_push_verify' );

		$result = $this->plugin->api->verify_pairing();

		$this->redirect_back(
			$result['ok'] ? 'success' : 'error',
			$result['ok']
				? __( 'Domínio validado.', 'encontra-push' )
				: ( $result['error'] ?: __( 'O painel não conseguiu validar o domínio.', 'encontra-push' ) )
		);
	}

	/**
	 * Secao 102: desconectar e uma acao explicita e nunca apaga a base de
	 * assinantes no painel — apenas encerra a credencial deste site.
	 */
	public function handle_disconnect(): void {
		$this->authorize( 'encontra_push_settings', 'encontra_push_disconnect' );

		$this->plugin->settings->forget_connection();
		$this->plugin->metrics->flush();

		$this->redirect_back(
			'success',
			__( 'Conexão removida deste site. Os assinantes continuam no painel.', 'encontra-push' )
		);
	}

	/**
	 * Secao 55 — configuracao de inscricao editada no wp-admin.
	 *
	 * O valor e enviado ao painel e so entao gravado localmente, a partir da
	 * resposta. Assim as duas telas nunca divergem: se o painel recusar ou
	 * normalizar um valor, e a versao dele que fica valendo.
	 */
	/**
	 * Um conjunto de pre-prompt, para desktop ou para celular.
	 *
	 * @param array<string, mixed> $input Campos crus do formulario.
	 * @return array<string, mixed>
	 */
	private static function prompt_device( array $input ): array {
		return array(
			'mode'                   => sanitize_key( $input['mode'] ?? 'delay' ),
			/*
			 * Como a permissao e pedida: `custom` mostra o pre-prompt do site
			 * antes; `native` abre o pedido do navegador direto.
			 *
			 * O direto so funciona no Chromium — Firefox e Safari exigem um
			 * gesto do usuario e ignoram a chamada feita no carregamento. Nos
			 * dois, o JavaScript cai no pre-prompt sozinho, senao o visitante
			 * desses navegadores nunca teria como se inscrever.
			 */
			'style'                  => in_array( $input['style'] ?? 'custom', array( 'custom', 'native' ), true )
				? $input['style']
				: 'custom',
			'delay_seconds'          => absint( $input['delay_seconds'] ?? 8 ),
			'visits'                 => absint( $input['visits'] ?? 2 ),
			'pageviews'              => absint( $input['pageviews'] ?? 2 ),
			'css_selector'           => sanitize_text_field( $input['css_selector'] ?? '' ),
			'redisplay_dismiss_days' => absint( $input['redisplay_dismiss_days'] ?? 7 ),
			'redisplay_later_days'   => absint( $input['redisplay_later_days'] ?? 30 ),
			'title'                  => sanitize_text_field( $input['title'] ?? '' ),
			'body'                   => sanitize_text_field( $input['body'] ?? '' ),
			'accept_label'           => sanitize_text_field( $input['accept_label'] ?? '' ),
			'decline_label'          => sanitize_text_field( $input['decline_label'] ?? '' ),
			'position'               => sanitize_key( $input['position'] ?? 'top-center' ),
		);
	}

	public function handle_prompt(): void {
		$this->authorize( 'encontra_push_manage', 'encontra_push_prompt' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado em authorize().
		$input = isset( $_POST['prompt'] ) ? (array) wp_unslash( $_POST['prompt'] ) : array();

		/*
		 * Desktop e celular sao dois conjuntos completos. O do desktop fica na
		 * raiz — formato antigo, mantido para nao migrar o que ja esta
		 * gravado — e o do celular em `mobile_settings`.
		 *
		 * `desktop` e `mobile` continuam sendo os booleanos de "aparece neste
		 * tipo de aparelho", e valem para os dois conjuntos.
		 */
		$payload = self::prompt_device( $input ) + array(
			'desktop' => ! empty( $input['desktop'] ),
			'mobile'  => ! empty( $input['mobile'] ),
		);

		if ( isset( $input['mobile_settings'] ) && is_array( $input['mobile_settings'] ) ) {
			$payload['mobile_settings'] = self::prompt_device( $input['mobile_settings'] );
		}

		$result = $this->plugin->api->request_put( array( 'prompt' => $payload ) );

		if ( ! $result['ok'] ) {
			$this->redirect_back( 'error', $result['error'] );
		}

		$config           = $this->plugin->settings->config();
		$config['prompt'] = $result['data']['prompt'] ?? $payload;

		$this->plugin->settings->save_config( $config );
		$this->purge_page_cache();

		$this->redirect_back( 'success', __( 'Configuração de inscrição salva.', 'encontra-push' ) );
	}

	/** Secao 56 — aparencia. */
	/**
	 * Um conjunto de aparencia, para desktop ou para celular.
	 *
	 * @param array<string, mixed> $input Campos crus do formulario.
	 * @return array<string, mixed>
	 */
	private static function appearance_device( array $input ): array {
		return array(
			'theme'        => sanitize_key( $input['theme'] ?? 'auto' ),
			// Secao 56: apenas cor hexadecimal. Nada de CSS arbitrario para
			// quem nao tem unfiltered_html — e nem para quem tem, nesta tela.
			'accent'       => sanitize_hex_color( $input['accent'] ?? '' ) ?: '#5b4bd6',
			'button_color' => sanitize_hex_color( $input['button_color'] ?? '' ) ?: '#5b4bd6',
			'radius'       => absint( $input['radius'] ?? 12 ),
			/*
			 * `position` NAO entra aqui. A tela de Aparencia tinha um campo de
			 * posicao que nunca fez nada: o JavaScript do site le
			 * `prompt.position`, e nao `appearance.position`. Manter os dois
			 * so criava a duvida de qual valia.
			 */
		);
	}

	public function handle_appearance(): void {
		$this->authorize( 'encontra_push_manage', 'encontra_push_appearance' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado em authorize().
		$input = isset( $_POST['appearance'] ) ? (array) wp_unslash( $_POST['appearance'] ) : array();

		$payload = self::appearance_device( $input );

		if ( isset( $input['mobile_settings'] ) && is_array( $input['mobile_settings'] ) ) {
			$payload['mobile_settings'] = self::appearance_device( $input['mobile_settings'] );
		}

		$result = $this->plugin->api->request_put( array( 'appearance' => $payload ) );

		if ( ! $result['ok'] ) {
			$this->redirect_back( 'error', $result['error'] );
		}

		$config               = $this->plugin->settings->config();
		$config['appearance'] = $result['data']['appearance'] ?? $payload;

		$this->plugin->settings->save_config( $config );
		$this->purge_page_cache();

		$this->redirect_back( 'success', __( 'Aparência salva.', 'encontra-push' ) );
	}

	/**
	 * Sino de noticias. Configuracao local: nao passa pelo painel, que so
	 * conhece as chaves do pré-prompt e da aparencia.
	 */
	public function handle_widget(): void {
		$this->authorize( 'encontra_push_manage', 'encontra_push_widget' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado em authorize().
		$input = isset( $_POST['widget'] ) ? (array) wp_unslash( $_POST['widget'] ) : array();

		// Sanitizacao completa (lista fechada de chaves) em Encontra_Push_Widget::save().
		$this->plugin->widget->save( $input );
		$this->purge_page_cache();

		$this->redirect_back( 'success', __( 'Sino de notícias salvo.', 'encontra-push' ) );
	}

	/**
	 * Forca a verificacao de atualizacao no GitHub.
	 *
	 * O plugin guarda a resposta do GitHub por 6 horas e o WordPress guarda a
	 * propria lista de atualizacoes. Publicar uma versao e nao ve-la aparecer
	 * e quase sempre um desses dois caches, nao um defeito — este botao
	 * resolve sem mandar o operador esperar.
	 */
	public function handle_check_update(): void {
		$this->authorize( 'encontra_push_manage', 'encontra_push_check_update' );

		$this->plugin->updater->force_check();

		$estado = $this->plugin->updater->status();

		if ( null === $estado['disponivel'] ) {
			$this->redirect_back(
				'error',
				__( 'Não foi possível falar com o GitHub agora. Tente de novo em alguns minutos.', 'encontra-push' )
			);

			return;
		}

		if ( version_compare( $estado['disponivel'], $estado['instalada'], '>' ) ) {
			$this->redirect_back(
				'success',
				sprintf(
					/* translators: %s: numero da versao. */
					__( 'Versão %s disponível. Abra a tela de Plugins para atualizar.', 'encontra-push' ),
					$estado['disponivel']
				)
			);

			return;
		}

		$this->redirect_back(
			'success',
			sprintf(
				/* translators: %s: numero da versao. */
				__( 'O plugin já está na versão mais recente (%s).', 'encontra-push' ),
				$estado['instalada']
			)
		);
	}

	/** Secao 57: envia ao painel o diagnostico completo, servidor + navegador. */
	public function handle_diagnostics(): void {
		$this->authorize( 'encontra_push_manage', 'encontra_push_diagnostics' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado em authorize().
		$raw     = isset( $_POST['browser_checks'] ) ? sanitize_textarea_field( wp_unslash( $_POST['browser_checks'] ) ) : '';
		$browser = json_decode( $raw, true );

		$this->plugin->diagnostics->report( is_array( $browser ) ? $browser : array() );

		$this->redirect_back( 'success', __( 'Diagnóstico enviado ao painel.', 'encontra-push' ) );
	}

	/**
	 * Secao 102 — o que a desinstalacao deve apagar.
	 *
	 * A preferencia e gravada com autoload = no e lida por uninstall.php, que
	 * roda quando nao existe mais interface para perguntar nada ao operador.
	 */
	public function handle_uninstall_options(): void {
		$this->authorize( 'encontra_push_settings', 'encontra_push_uninstall_options' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado em authorize().
		$input = isset( $_POST['uninstall'] ) ? (array) wp_unslash( $_POST['uninstall'] ) : array();

		update_option(
			'encontra_push_uninstall_options',
			array(
				'revoke_connection' => ! empty( $input['revoke_connection'] ),
				'remove_local'      => ! empty( $input['remove_local'] ),
			),
			false
		);

		$this->redirect_back( 'success', __( 'Preferência de desinstalação salva.', 'encontra-push' ) );
	}

	/** Secao 54: botao "Atualizar agora". */
	public function handle_refresh(): void {
		$this->authorize( 'encontra_push_view', 'encontra_push_refresh' );

		$this->plugin->metrics->flush();
		$this->plugin->metrics->metrics( true );

		$this->redirect_back( 'success', __( 'Métricas atualizadas.', 'encontra-push' ) );
	}

	/* --------------------------------------------------------------------- */

	/** Secao 100: capability + nonce em toda acao administrativa. */
	private function authorize( string $capability, string $action ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Você não tem permissão para esta ação.', 'encontra-push' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Limpa o cache de pagina dos plugins mais comuns.
	 *
	 * A configuracao do front-end (chave VAPID, textos do pré-prompt, cores)
	 * vai embutida no HTML de cada pagina. Com cache de pagina ativo, o
	 * visitante continuaria recebendo o HTML antigo — no caso de um site
	 * recem-conectado, um HTML sem o script de inscricao. O LiteSpeed ainda
	 * guarda versoes separadas para celular e desktop, entao um pode
	 * funcionar enquanto o outro segue servindo a versao velha.
	 *
	 * Cada chamada so acontece se o respectivo plugin estiver instalado.
	 */
	private function purge_page_cache(): void {
		// A lista de plugins de cache fica num lugar so: tambem e usada quando
		// o plugin e atualizado (Encontra_Push::maybe_upgrade).
		Encontra_Push::purge_page_cache();
	}

	/**
	 * O texto do aviso fica num transient do usuario, nao na URL (SEC-023).
	 *
	 * Com a mensagem na query string, qualquer um podia mandar a um admin um
	 * link do wp-admin exibindo um "aviso oficial" com o texto que quisesse
	 * (ex.: "reconecte o plugin em painel-falso.com").
	 */
	private function redirect_back( string $type, string $message ): void {
		$referer = remove_query_arg( array( 'ep_notice', 'ep_message' ), wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE ) );

		set_transient(
			'encontra_push_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( add_query_arg( 'ep_notice', '1', $referer ) );

		exit;
	}

	public function connection_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- apenas sinaliza que ha aviso.
		if ( empty( $_GET['ep_notice'] ) ) {
			return;
		}

		$key    = 'encontra_push_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( $key );

		$type    = sanitize_key( (string) ( $notice['type'] ?? 'info' ) );
		$message = sanitize_text_field( (string) ( $notice['message'] ?? '' ) );

		$class = array(
			'success' => 'notice-success',
			'error'   => 'notice-error',
			'warning' => 'notice-warning',
		)[ $type ] ?? 'notice-info';

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
}
