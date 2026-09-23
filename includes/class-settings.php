<?php
/**
 * Opcoes locais do plugin.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 14.2 — armazenamento de credenciais no WordPress.
 *
 * Tres regras que este arquivo existe para garantir:
 *
 *  1. o api_secret nunca aparece em HTML nem em resposta de REST;
 *  2. a opcao que o guarda usa autoload = no, para nao ir junto em todo
 *     carregamento de pagina do site;
 *  3. o valor e cifrado com material derivado dos salts do WordPress, de modo
 *     que um dump da tabela wp_options, sozinho, nao entregue o segredo.
 */
class Encontra_Push_Settings {

	private const OPT_CONNECTION = 'encontra_push_connection';
	private const OPT_CONFIG     = 'encontra_push_config';
	private const OPT_STATE      = 'encontra_push_state';

	/** @var array<string, mixed>|null */
	private ?array $connection_cache = null;

	/* ------------------------------------------------------------ Conexao */

	/**
	 * Guarda as credenciais recebidas no pairing.
	 *
	 * @param array $data site_id, api_key_id, api_secret, vapid_public_key,
	 *                    panel_url, api_url.
	 */
	public function save_connection( array $data ): void {
		$panel = esc_url_raw( $data['panel_url'] ?? $this->panel_url() );

		// O segredo so trafega por HTTPS: um endereco em HTTP aqui mandaria
		// cada requisicao assinada em claro (SEC-021).
		if ( ! str_starts_with( $panel, 'https://' ) ) {
			$panel = $this->panel_url();
		}

		$api = esc_url_raw( $data['api_url'] ?? $panel );

		// Sem api_url na resposta, a API vive no mesmo host do painel — que e
		// o arranjo padrao. O campo so diverge quando a operacao decide
		// separar o processamento (secao 77, Fase B).
		if ( ! str_starts_with( $api, 'https://' ) || ! self::is_trusted_api_host( $api, $panel ) ) {
			$api = $panel;
		}

		$payload = array(
			'site_id'          => sanitize_text_field( $data['site_id'] ?? '' ),
			'api_key_id'       => sanitize_text_field( $data['api_key_id'] ?? '' ),
			'api_secret'       => $this->encrypt( (string) ( $data['api_secret'] ?? '' ) ),
			'vapid_public_key' => sanitize_text_field( $data['vapid_public_key'] ?? '' ),
			'panel_url'        => untrailingslashit( $panel ),
			'api_url'          => untrailingslashit( $api ),
			'connected_at'     => time(),
		);

		// autoload = no: o segredo nao precisa estar em memoria a cada
		// requisicao do site, so quando o plugin fala com o painel.
		update_option( self::OPT_CONNECTION, $payload, false );

		$this->connection_cache = null;
	}

	public function forget_connection(): void {
		delete_option( self::OPT_CONNECTION );
		delete_option( self::OPT_CONFIG );

		$this->connection_cache = null;
	}

	public function is_connected(): bool {
		$connection = $this->connection();

		return ! empty( $connection['site_id'] ) && ! empty( $connection['api_key_id'] );
	}

	/** @return array<string, mixed> */
	public function connection(): array {
		if ( null === $this->connection_cache ) {
			$this->connection_cache = (array) get_option( self::OPT_CONNECTION, array() );
		}

		return $this->connection_cache;
	}

	public function site_id(): string {
		return (string) ( $this->connection()['site_id'] ?? '' );
	}

	public function api_key_id(): string {
		return (string) ( $this->connection()['api_key_id'] ?? '' );
	}

	/**
	 * Segredo em claro. Uso restrito a assinatura HMAC.
	 *
	 * Nunca deve ser impresso, retornado por REST ou passado ao front-end.
	 */
	public function api_secret(): string {
		return $this->decrypt( (string) ( $this->connection()['api_secret'] ?? '' ) );
	}

	public function vapid_public_key(): string {
		return (string) ( $this->connection()['vapid_public_key'] ?? '' );
	}

	public function panel_url(): string {
		$stored = (string) ( $this->connection()['panel_url'] ?? '' );

		if ( '' !== $stored ) {
			return untrailingslashit( $stored );
		}

		/**
		 * Permite apontar para outro painel em ambiente de homologacao.
		 */
		return untrailingslashit( apply_filters( 'encontra_push_panel_url', 'https://push.encontra.com.br' ) );
	}

	/**
	 * Endereco para onde vao as requisicoes assinadas.
	 *
	 * Separado de panel_url de proposito: o painel e uma interface para
	 * pessoas, a API e um servico para maquinas, e os dois podem acabar em
	 * hosts diferentes sem que isso seja assunto de quem administra o site.
	 *
	 * Na duvida, cai no painel — que e onde a API responde por padrao.
	 */
	public function api_url(): string {
		$stored = (string) ( $this->connection()['api_url'] ?? '' );

		if ( '' !== $stored ) {
			return untrailingslashit( $stored );
		}

		return $this->panel_url();
	}

	/**
	 * Atualiza o endereco da API informado pelo painel no heartbeat.
	 *
	 * E assim que uma migracao da API alcanca sites ja conectados, sem
	 * atualizar o plugin e sem ninguem abrir o wp-admin.
	 *
	 * @return bool  true quando o endereco mudou de fato.
	 */
	public function sync_api_url( string $api_url ): bool {
		$api_url = esc_url_raw( $api_url );

		if ( '' === $api_url || ! str_starts_with( $api_url, 'https://' ) ) {
			// Downgrade para HTTP seria um caminho fácil demais para um
			// ataque de DNS redirecionar credenciais assinadas.
			return false;
		}

		/*
		 * SEC-022: a resposta do heartbeat NAO e assinada — a autenticidade
		 * dela depende so do TLS. Por isso o novo endereco precisa ficar no
		 * mesmo dominio do painel (ex.: apipush.encontra.com.br). Qualquer
		 * outro host e ignorado: uma resposta adulterada nao consegue desviar
		 * todas as requisicoes assinadas deste site para um servidor alheio.
		 */
		if ( ! self::is_trusted_api_host( $api_url, $this->panel_url() ) ) {
			return false;
		}

		if ( untrailingslashit( $api_url ) === $this->api_url() ) {
			return false;
		}

		$connection            = $this->connection();
		$connection['api_url'] = untrailingslashit( $api_url );

		update_option( self::OPT_CONNECTION, $connection, false );

		$this->connection_cache = null;

		return true;
	}

	/**
	 * O host da API e o do painel, ou esta no mesmo dominio dele.
	 *
	 * Painel em push.encontra.com.br aceita encontra.com.br e qualquer
	 * *.encontra.com.br. Outros hosts podem ser liberados pelo filtro
	 * `encontra_push_trusted_api_hosts` (lista de hosts exatos).
	 */
	public static function is_trusted_api_host( string $api_url, string $panel_url ): bool {
		$api_host   = strtolower( (string) wp_parse_url( $api_url, PHP_URL_HOST ) );
		$panel_host = strtolower( (string) wp_parse_url( $panel_url, PHP_URL_HOST ) );

		if ( '' === $api_host || '' === $panel_host ) {
			return false;
		}

		if ( $api_host === $panel_host ) {
			return true;
		}

		$extra = (array) apply_filters( 'encontra_push_trusted_api_hosts', array() );

		if ( in_array( $api_host, array_map( 'strtolower', $extra ), true ) ) {
			return true;
		}

		// Dominio do painel sem o primeiro rotulo: push.encontra.com.br -> encontra.com.br.
		$labels = explode( '.', $panel_host );

		if ( count( $labels ) < 3 ) {
			return false;
		}

		$base = implode( '.', array_slice( $labels, 1 ) );

		return $api_host === $base || str_ends_with( $api_host, '.' . $base );
	}

	/* ----------------------------------------------------- Configuracao -- */

	/**
	 * Configuracao espelhada do painel (pré-prompt, aparencia).
	 *
	 * O painel e a fonte da verdade; aqui e apenas um cache para o front-end
	 * nao depender de uma chamada externa a cada pageview.
	 *
	 * @return array<string, mixed>
	 */
	public function config(): array {
		return (array) get_option( self::OPT_CONFIG, array() );
	}

	public function save_config( array $config ): void {
		update_option( self::OPT_CONFIG, $config, false );
	}

	public function prompt_config(): array {
		$config = $this->config();

		return wp_parse_args(
			(array) ( $config['prompt'] ?? array() ),
			array(
				'mode'                    => 'delay',
				'delay_seconds'           => 8,
				'visits'                  => 2,
				'pageviews'               => 2,
				'css_selector'            => '',
				'desktop'                 => true,
				'mobile'                  => true,
				'redisplay_dismiss_days'  => 7,
				'redisplay_later_days'    => 30,
				'title'                   => __( 'Receba novidades deste site', 'encontra-push' ),
				'body'                    => __( 'Fique sabendo quando publicarmos novas informações.', 'encontra-push' ),
				'accept_label'            => __( 'Ativar notificações', 'encontra-push' ),
				'decline_label'           => __( 'Agora não', 'encontra-push' ),
				'position'                => 'top-center',
				'theme'                   => 'auto',
			)
		);
	}

	public function appearance_config(): array {
		$config = $this->config();

		return wp_parse_args(
			(array) ( $config['appearance'] ?? array() ),
			array(
				'theme'        => 'auto',
				'accent'       => '#5b4bd6',
				'button_color' => '#5b4bd6',
				'radius'       => 12,
				'position'     => 'top-center',
			)
		);
	}

	public function integration_mode(): string {
		$mode = (string) ( $this->config()['integration_mode'] ?? 'exclusive' );

		return in_array( $mode, array( 'exclusive', 'integration', 'custom' ), true ) ? $mode : 'exclusive';
	}

	/* ------------------------------------------------------------- Estado */

	/** @return array<string, mixed> */
	public function state(): array {
		return (array) get_option( self::OPT_STATE, array() );
	}

	public function update_state( array $values ): void {
		update_option( self::OPT_STATE, array_merge( $this->state(), $values ), false );
	}

	public function challenge(): string {
		return (string) ( $this->state()['challenge'] ?? '' );
	}

	public function set_challenge( string $challenge ): void {
		$this->update_state( array( 'challenge' => sanitize_text_field( $challenge ) ) );
	}

	/* ------------------------------------------------------- Criptografia */

	/**
	 * Cifra com material derivado dos salts do WordPress.
	 *
	 * Nao substitui a criptografia do painel: e uma camada a mais para que o
	 * segredo nao fique legivel em um dump de wp_options. Se a instalacao usar
	 * salts padrao (o que ja e um problema por si so), o ganho e menor — por
	 * isso a rotacao da credencial existe no painel.
	 */
	private function encrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// Sem openssl, guardamos em claro e sinalizamos no diagnostico —
			// melhor um aviso visivel do que uma falsa sensacao de seguranca.
			return 'plain:' . $value;
		}

		$key   = $this->encryption_key();
		$nonce = random_bytes( 12 );
		$tag   = '';

		$cipher = openssl_encrypt( $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag );

		if ( false === $cipher ) {
			return 'plain:' . $value;
		}

		return 'v1:' . base64_encode( $nonce . $tag . $cipher );
	}

	private function decrypt( string $payload ): string {
		if ( '' === $payload ) {
			return '';
		}

		if ( str_starts_with( $payload, 'plain:' ) ) {
			return substr( $payload, 6 );
		}

		if ( ! str_starts_with( $payload, 'v1:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$raw = base64_decode( substr( $payload, 3 ), true );

		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}

		$plain = openssl_decrypt(
			substr( $raw, 28 ),
			'aes-256-gcm',
			$this->encryption_key(),
			OPENSSL_RAW_DATA,
			substr( $raw, 0, 12 ),
			substr( $raw, 12, 16 )
		);

		return false === $plain ? '' : $plain;
	}

	private function encryption_key(): string {
		$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' )
			. ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' )
			. ( defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : '' );

		return hash( 'sha256', 'encontra-push|' . $material, true );
	}

	public function encryption_available(): bool {
		return function_exists( 'openssl_encrypt' );
	}
}
