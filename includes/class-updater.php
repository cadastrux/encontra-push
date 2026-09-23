<?php
/**
 * Atualizacao do plugin a partir do GitHub.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 103 — atualizacao pelo repositorio.
 *
 * O plugin nao esta no diretorio do WordPress.org, entao a tela de Plugins
 * nunca ofereceria atualizacao sozinha. Esta classe preenche esse papel: le as
 * tags do repositorio, compara com a versao instalada e entrega ao WordPress
 * uma resposta no mesmo formato que o wordpress.org devolveria. O resultado e
 * uma atualizacao comum — aviso na lista de plugins, botao "Atualizar agora",
 * atualizacao automatica se o site tiver ligado.
 *
 * O gancho e `update_plugins_github.com`, ativado pelo cabecalho
 * `Update URI:` do arquivo principal. Preferimos ele a
 * `pre_set_site_transient_update_plugins` por um motivo de seguranca: com o
 * Update URI declarado, o WordPress para de consultar o wordpress.org para
 * este plugin. Sem isso, bastaria alguem publicar um plugin com o slug
 * "encontra-push" no diretorio oficial para que os sites baixassem o pacote
 * dele por cima deste. E um sequestro conhecido, e a defesa e exatamente essa.
 *
 * A fonte da versao sao as TAGS, nao as releases: assim o fluxo de publicacao
 * e `git tag v1.0.14 && git push --tags`, sem depender de criar release nem
 * de anexar um zip a mao. O pacote e o zipball da tag.
 *
 * Repositorio privado: defina o token no wp-config.php. Sem token, um repo
 * privado simplesmente nao devolve atualizacao — nao quebra o site.
 *
 *     define( 'ENCONTRA_PUSH_GITHUB_TOKEN', 'github_pat_...' );
 */
class Encontra_Push_Updater {

	/** Dono/repositorio no GitHub. */
	private const REPO = 'cadastrux/encontra-push';

	/** Quanto tempo a resposta do GitHub fica em cache. */
	private const CACHE = 6 * HOUR_IN_SECONDS;

	/** Cache tambem do "nao ha novidade", para nao consultar a cada pageview. */
	private const CACHE_KEY = 'encontra_push_github_tags';

	public function register(): void {
		// Ativado pelo cabecalho "Update URI: https://github.com/...".
		add_filter( 'update_plugins_github.com', array( $this, 'check' ), 10, 3 );

		// O zipball do GitHub extrai numa pasta com o hash do commit
		// (encontra-push-a1b2c3d). Sem renomear, o WordPress instalaria o
		// plugin numa pasta nova e o antigo continuaria ativo ao lado.
		add_filter( 'upgrader_source_selection', array( $this, 'fix_folder' ), 10, 4 );

		// Repositorio privado: o zipball exige o token no cabecalho.
		add_filter( 'http_request_args', array( $this, 'authorize_download' ), 10, 2 );

		// Tela "Ver detalhes" do plugin.
		add_filter( 'plugins_api', array( $this, 'details' ), 10, 3 );

		// "Verificar novamente" na tela de Atualizacoes tem que consultar de
		// verdade, senao o cache de 6h faz o botao parecer quebrado.
		add_action( 'load-update-core.php', array( $this, 'flush' ) );
		add_action( 'upgrader_process_complete', array( $this, 'flush' ) );
	}

	/* ------------------------------------------------------------ Consulta */

	/**
	 * Resposta para o WordPress, no formato que ele espera.
	 *
	 * @param array|false $update      O que outro filtro ja tenha decidido.
	 * @param array       $plugin_data Cabecalhos do plugin instalado.
	 * @param string      $plugin_file Caminho relativo (encontra-push/encontra-push.php).
	 * @return array|false
	 */
	public function check( $update, array $plugin_data, string $plugin_file ) {
		// Outro filtro ja respondeu: nao atropela.
		if ( ! empty( $update ) ) {
			return $update;
		}

		if ( plugin_basename( ENCONTRA_PUSH_FILE ) !== $plugin_file ) {
			return $update;
		}

		$version = $this->latest_version();

		if ( null === $version ) {
			return $update;
		}

		// So responde quando ha novidade de verdade. Devolver uma versao igual
		// ou menor faria o WordPress registrar "sem atualizacao" e, em algumas
		// telas, piscar o aviso a toa.
		if ( version_compare( $version['numero'], ENCONTRA_PUSH_VERSION, '<=' ) ) {
			return $update;
		}

		return array(
			'id'           => 'github.com/' . self::REPO,
			'slug'         => ENCONTRA_PUSH_SLUG,
			'plugin'       => $plugin_file,
			'version'      => $version['numero'],
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $version['pacote'],
			'requires'     => $plugin_data['RequiresWP'] ?? '',
			'requires_php' => $plugin_data['RequiresPHP'] ?? '',
			'tested'       => '',
		);
	}

	/**
	 * Maior tag do repositorio.
	 *
	 * @return array{numero: string, tag: string, pacote: string}|null
	 */
	private function latest_version(): ?array {
		$cache = get_transient( self::CACHE_KEY );

		if ( is_array( $cache ) ) {
			return $cache['versao'] ?? null;
		}

		$resposta = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/tags?per_page=100',
			array(
				'timeout' => 10,
				'headers' => $this->headers(),
			)
		);

		if ( is_wp_error( $resposta ) || 200 !== wp_remote_retrieve_response_code( $resposta ) ) {
			/*
			 * Falha de rede, limite de requisicao do GitHub (60/h sem token)
			 * ou repositorio privado sem token. Guarda o "nao sei" por um
			 * tempo menor: repetir a cada pageview castigaria o site e ainda
			 * poderia bloquear o IP na API.
			 */
			set_transient( self::CACHE_KEY, array( 'versao' => null ), 30 * MINUTE_IN_SECONDS );

			return null;
		}

		$tags = json_decode( wp_remote_retrieve_body( $resposta ), true );

		if ( ! is_array( $tags ) ) {
			set_transient( self::CACHE_KEY, array( 'versao' => null ), 30 * MINUTE_IN_SECONDS );

			return null;
		}

		$maior = null;

		foreach ( $tags as $tag ) {
			$nome = (string) ( $tag['name'] ?? '' );

			// Aceita "1.2.3" e "v1.2.3"; ignora branch de trabalho e afins.
			if ( ! preg_match( '/^v?(\d+\.\d+\.\d+)$/', $nome, $partes ) ) {
				continue;
			}

			if ( null !== $maior && version_compare( $partes[1], $maior['numero'], '<=' ) ) {
				continue;
			}

			$maior = array(
				'numero' => $partes[1],
				'tag'    => $nome,
				// O zipball serve ao repo publico e ao privado (com token) e
				// dispensa criar release e anexar arquivo a mao.
				'pacote' => 'https://api.github.com/repos/' . self::REPO . '/zipball/' . rawurlencode( $nome ),
			);
		}

		set_transient( self::CACHE_KEY, array( 'versao' => $maior ), self::CACHE );

		return $maior;
	}

	/** @return array<string, string> */
	private function headers(): array {
		$headers = array(
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			// A API do GitHub recusa requisicao sem User-Agent.
			'User-Agent'           => 'EncontraPush/' . ENCONTRA_PUSH_VERSION . '; ' . home_url( '/' ),
		);

		$token = $this->token();

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	private function token(): string {
		return defined( 'ENCONTRA_PUSH_GITHUB_TOKEN' )
			? trim( (string) ENCONTRA_PUSH_GITHUB_TOKEN )
			: '';
	}

	/* ----------------------------------------------------------- Download */

	/**
	 * Acrescenta o token ao baixar o zipball de um repositorio privado.
	 *
	 * O download e feito por download_url(), que nao aceita cabecalho extra.
	 * Por isso a injecao acontece aqui, e SOMENTE para a URL do proprio
	 * repositorio — nunca em qualquer outra requisicao do site.
	 *
	 * @param array  $args Argumentos da requisicao.
	 * @param string $url  URL de destino.
	 * @return array
	 */
	public function authorize_download( array $args, string $url ): array {
		$token = $this->token();

		if ( '' === $token ) {
			return $args;
		}

		$esperado = 'https://api.github.com/repos/' . self::REPO . '/zipball/';

		if ( ! str_starts_with( $url, $esperado ) ) {
			return $args;
		}

		$args['headers'] = array_merge(
			is_array( $args['headers'] ?? null ) ? $args['headers'] : array(),
			array(
				'Authorization' => 'Bearer ' . $token,
				'User-Agent'    => 'EncontraPush/' . ENCONTRA_PUSH_VERSION,
			)
		);

		return $args;
	}

	/**
	 * Renomeia a pasta extraida para o slug do plugin.
	 *
	 * O zipball vem como "cadastrux-encontra-push-a1b2c3d/". Se o WordPress
	 * instalasse com esse nome, o plugin apareceria duplicado na lista e o
	 * antigo continuaria ativo.
	 *
	 * @param string      $source        Pasta extraida.
	 * @param string      $remote_source Pasta temporaria do download.
	 * @param WP_Upgrader $upgrader      Instancia do atualizador.
	 * @param array       $hook_extra    Contexto (qual plugin esta sendo atualizado).
	 * @return string|WP_Error
	 */
	public function fix_folder( $source, $remote_source, $upgrader = null, $hook_extra = array() ) {
		// So mexe quando o alvo e ESTE plugin.
		if ( ( $hook_extra['plugin'] ?? '' ) !== plugin_basename( ENCONTRA_PUSH_FILE ) ) {
			return $source;
		}

		$destino = trailingslashit( dirname( $source ) ) . ENCONTRA_PUSH_SLUG;

		if ( untrailingslashit( $source ) === untrailingslashit( $destino ) ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return $source;
		}

		if ( $wp_filesystem->exists( $destino ) ) {
			$wp_filesystem->delete( $destino, true );
		}

		if ( ! $wp_filesystem->move( $source, $destino ) ) {
			return new WP_Error(
				'encontra_push_rename',
				__( 'Não foi possível preparar a pasta do plugin para a atualização.', 'encontra-push' )
			);
		}

		return trailingslashit( $destino );
	}

	/* ------------------------------------------------------------ Detalhes */

	/**
	 * Preenche a janela "Ver detalhes" do plugin.
	 *
	 * @param false|object|array $resultado Resultado de outro filtro.
	 * @param string             $acao      Acao pedida.
	 * @param object             $args      Argumentos.
	 * @return false|object|array
	 */
	public function details( $resultado, string $acao, $args ) {
		if ( 'plugin_information' !== $acao || ( $args->slug ?? '' ) !== ENCONTRA_PUSH_SLUG ) {
			return $resultado;
		}

		// plugins_api roda no admin, mas nao ha garantia de que este arquivo
		// ja foi carregado quando outro plugin dispara a consulta.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$versao = $this->latest_version();
		$dados  = get_plugin_data( ENCONTRA_PUSH_FILE, false, false );

		return (object) array(
			'name'          => $dados['Name'],
			'slug'          => ENCONTRA_PUSH_SLUG,
			'version'       => $versao['numero'] ?? ENCONTRA_PUSH_VERSION,
			'author'        => $dados['Author'],
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => $dados['RequiresWP'] ?? '',
			'requires_php'  => $dados['RequiresPHP'] ?? '',
			'download_link' => $versao['pacote'] ?? '',
			'sections'      => array(
				'description' => wp_kses_post( $dados['Description'] ),
				'changelog'   => sprintf(
					/* translators: %s: endereco do repositorio. */
					'<p>' . esc_html__( 'O histórico de versões fica no repositório: %s', 'encontra-push' ) . '</p>',
					'<a href="https://github.com/' . self::REPO . '/releases" target="_blank" rel="noopener">github.com/' . self::REPO . '</a>'
				),
			),
		);
	}

	/** Descarta o cache para a proxima consulta ir na fonte. */
	public function flush(): void {
		delete_transient( self::CACHE_KEY );
	}
}
