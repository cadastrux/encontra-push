<?php
/**
 * Regras de pre-prompt por endereco de pagina.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Copy e comportamento diferentes por URL.
 *
 * A configuracao do site (que vive no painel) vale para o site inteiro. Uma
 * materia de futebol e a pagina "quem somos" merecem convites diferentes, e e
 * isso que estas regras resolvem: um padrao de URL aponta para um punhado de
 * campos que SOBRESCREVEM a configuracao do site naquela pagina.
 *
 * Por que isto mora so no plugin, e nao no painel: o painel guarda um conjunto
 * de prompt por site (raiz = desktop, `mobile_settings` = celular) e o
 * sanitizador dele descarta chave que nao conhece. Mandar uma lista de regras
 * junto faria o painel devolver o conjunto sem ela, e a lista se perderia no
 * primeiro "salvar". Entao a lista fica numa opcao local.
 *
 * Sobrescreve apenas o que foi preenchido. Campo em branco na regra significa
 * "herdar do site", e nao "apagar" — assim uma regra criada hoje continua
 * valendo quando um campo novo for acrescentado amanha.
 */
class Encontra_Push_Url_Rules {

	private const OPTION = 'encontra_push_url_rules';

	/** Teto de regras. A avaliacao e linear e roda em toda pageview. */
	public const MAX = 50;

	public const MODES = array( 'delay', 'scroll', 'visits', 'pageviews', 'selector', 'manual', 'disabled' );

	public const STYLES = array( 'custom', 'native' );

	public const POSITIONS = array(
		'top-center',
		'top-left',
		'top-right',
		'bottom-center',
		'bottom-left',
		'bottom-right',
	);

	/** Campos numericos: chave => array( minimo, maximo ). */
	private const NUMEROS = array(
		'scroll_percent' => array( 1, 100 ),
		'delay_seconds'  => array( 0, 600 ),
		'visits'         => array( 1, 50 ),
		'pageviews'      => array( 1, 50 ),
	);

	/** Campos de texto: chave => limite de caracteres. */
	private const TEXTOS = array(
		'title'          => 160,
		'body'           => 300,
		'accept_label'   => 60,
		'decline_label'  => 60,
		'css_selector'   => 191,
	);

	/* ------------------------------------------------------------ Leitura */

	/** @return array<int, array<string, mixed>> */
	public function all(): array {
		$rules = get_option( self::OPTION, array() );

		return is_array( $rules ) ? array_values( $rules ) : array();
	}

	/** @param array<int, array<string, mixed>> $rules */
	public function save( array $rules ): void {
		update_option( self::OPTION, array_values( array_slice( $rules, 0, self::MAX ) ), false );
	}

	public function get( int $index ): ?array {
		$rules = $this->all();

		return $rules[ $index ] ?? null;
	}

	/* ------------------------------------------------------------- Escrita */

	/** Grava em $index, ou acrescenta ao fim quando $index e nulo. */
	public function put( ?int $index, array $rule ): void {
		$rules = $this->all();

		if ( null === $index || ! isset( $rules[ $index ] ) ) {
			if ( count( $rules ) >= self::MAX ) {
				return;
			}

			$rules[] = $rule;
		} else {
			$rules[ $index ] = $rule;
		}

		$this->save( $rules );
	}

	public function delete( int $index ): void {
		$rules = $this->all();

		if ( ! isset( $rules[ $index ] ) ) {
			return;
		}

		unset( $rules[ $index ] );
		$this->save( $rules );
	}

	/**
	 * Troca a regra de lugar com a vizinha.
	 *
	 * A ordem e o criterio de desempate: a primeira que casar vence. Mover e a
	 * unica forma de resolver dois padroes que pegam a mesma pagina.
	 */
	public function move( int $index, int $direcao ): void {
		$rules  = $this->all();
		$destino = $index + $direcao;

		if ( ! isset( $rules[ $index ], $rules[ $destino ] ) ) {
			return;
		}

		$guarda            = $rules[ $index ];
		$rules[ $index ]   = $rules[ $destino ];
		$rules[ $destino ] = $guarda;

		$this->save( $rules );
	}

	/* ------------------------------------------------------------ Casamento */

	/**
	 * Caminho da requisicao atual, sem query string nem ancora.
	 *
	 * A query fica de fora de proposito: `?utm_source=...` nao muda que pagina
	 * e, e incluir a query faria toda campanha de midia escapar das regras.
	 */
	public static function current_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';

		return self::normalize_path( $uri );
	}

	public static function normalize_path( string $uri ): string {
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		return self::trim_path( $path );
	}

	/**
	 * O padrao nao passa por wp_parse_url: `*` nao e URL valida e a funcao
	 * poderia devolver vazio. Ele so e aparado como um caminho.
	 */
	public static function normalize_pattern( string $pattern ): string {
		return self::trim_path( trim( $pattern ) );
	}

	private static function trim_path( string $path ): string {
		$path = trim( $path );

		if ( '' === $path ) {
			return '/';
		}

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		// A barra final nao distingue pagina: /sobre e /sobre/ sao a mesma.
		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
		}

		return '' === $path ? '/' : $path;
	}

	/**
	 * `*` vale por qualquer trecho, inclusive vazio e com barras.
	 *
	 * Atencao a barra antes do curinga: `/esportes/*` pega /esportes/materia
	 * mas NAO pega /esportes, porque a barra e literal. Para alcancar a
	 * editoria e as materias dela de uma vez, use `/esportes*`.
	 */
	public static function matches( string $pattern, string $path ): bool {
		$pattern = self::normalize_pattern( $pattern );

		if ( '' === $pattern ) {
			return false;
		}

		// preg_quote transforma `*` em `\*`; so entao ele vira o curinga.
		$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';

		return 1 === preg_match( $regex, $path );
	}

	/**
	 * Campos que a primeira regra ligada e casada quer sobrescrever.
	 *
	 * @return array<string, mixed> Vazio quando nenhuma regra casa.
	 */
	public function overrides_for( string $path ): array {
		foreach ( $this->all() as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			if ( ! self::matches( (string) ( $rule['pattern'] ?? '' ), $path ) ) {
				continue;
			}

			$overrides = (array) ( $rule['overrides'] ?? array() );

			return $overrides;
		}

		return array();
	}

	/** @return array<string, mixed> */
	public function overrides_for_current(): array {
		return $this->overrides_for( self::current_path() );
	}

	/* -------------------------------------------------------- Saneamento */

	/**
	 * Uma regra vinda do formulario.
	 *
	 * @param array<string, mixed> $input Campos crus.
	 * @return array<string, mixed>|null  Nulo quando o padrao esta vazio.
	 */
	public static function sanitize_rule( array $input ): ?array {
		$pattern = self::normalize_pattern( sanitize_text_field( (string) ( $input['pattern'] ?? '' ) ) );

		// Padrao vazio viraria "/" e pegaria a home sem querer.
		if ( '' === trim( (string) ( $input['pattern'] ?? '' ) ) ) {
			return null;
		}

		return array(
			'label'     => mb_substr( sanitize_text_field( (string) ( $input['label'] ?? '' ) ), 0, 80 ),
			'pattern'   => mb_substr( $pattern, 0, 191 ),
			'enabled'   => ! empty( $input['enabled'] ),
			'overrides' => self::sanitize_overrides( (array) ( $input['overrides'] ?? array() ) ),
		);
	}

	/**
	 * So entra o que foi preenchido: vazio significa herdar do site.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private static function sanitize_overrides( array $input ): array {
		$out = array();

		$listas = array(
			'mode'     => self::MODES,
			'style'    => self::STYLES,
			'position' => self::POSITIONS,
		);

		foreach ( $listas as $chave => $aceitos ) {
			$valor = (string) ( $input[ $chave ] ?? '' );

			if ( in_array( $valor, $aceitos, true ) ) {
				$out[ $chave ] = $valor;
			}
		}

		foreach ( self::NUMEROS as $chave => $faixa ) {
			$bruto = (string) ( $input[ $chave ] ?? '' );

			// Campo em branco herda; "0" e um valor, nao um branco.
			if ( '' === trim( $bruto ) ) {
				continue;
			}

			$out[ $chave ] = min( $faixa[1], max( $faixa[0], absint( $bruto ) ) );
		}

		foreach ( self::TEXTOS as $chave => $limite ) {
			$valor = sanitize_text_field( (string) ( $input[ $chave ] ?? '' ) );

			if ( '' === $valor ) {
				continue;
			}

			$out[ $chave ] = mb_substr( $valor, 0, $limite );
		}

		return $out;
	}

	/* ----------------------------------------------------------- Resumo */

	/**
	 * Frase curta do que a regra muda, para a coluna da tabela.
	 *
	 * @param array<string, mixed> $rule
	 */
	public static function summary( array $rule ): string {
		$o = (array) ( $rule['overrides'] ?? array() );

		if ( array() === $o ) {
			return __( 'Nada sobrescrito (herda tudo do site)', 'encontra-push' );
		}

		$partes = array();

		if ( isset( $o['mode'] ) ) {
			$rotulos = array(
				'delay'     => __( 'após %s s', 'encontra-push' ),
				'scroll'    => __( 'ao rolar %s%%', 'encontra-push' ),
				'visits'    => __( 'após %s visitas', 'encontra-push' ),
				'pageviews' => __( 'após %s páginas', 'encontra-push' ),
			);

			$valores = array(
				'delay'     => $o['delay_seconds'] ?? '—',
				'scroll'    => $o['scroll_percent'] ?? '—',
				'visits'    => $o['visits'] ?? '—',
				'pageviews' => $o['pageviews'] ?? '—',
			);

			if ( isset( $rotulos[ $o['mode'] ] ) ) {
				$partes[] = sprintf( $rotulos[ $o['mode'] ], $valores[ $o['mode'] ] );
			} elseif ( 'disabled' === $o['mode'] ) {
				$partes[] = __( 'convite desativado', 'encontra-push' );
			} elseif ( 'manual' === $o['mode'] ) {
				$partes[] = __( 'somente botão manual', 'encontra-push' );
			} else {
				$partes[] = __( 'por seletor CSS', 'encontra-push' );
			}
		}

		if ( isset( $o['style'] ) ) {
			$partes[] = 'native' === $o['style']
				? __( 'pedido direto do navegador', 'encontra-push' )
				: __( 'pré-prompt do site', 'encontra-push' );
		}

		$copy = array_intersect_key( $o, self::TEXTOS );

		if ( array() !== $copy ) {
			$partes[] = sprintf(
				/* translators: %d: quantidade de campos de texto personalizados */
				_n( '%d texto próprio', '%d textos próprios', count( $copy ), 'encontra-push' ),
				count( $copy )
			);
		}

		if ( isset( $o['position'] ) ) {
			$partes[] = __( 'posição própria', 'encontra-push' );
		}

		return implode( ' · ', $partes );
	}
}
