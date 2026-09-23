<?php
/**
 * Sino de noticias: botao fixo na lateral do site.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sino fixo com contador de nao lidas.
 *
 *  - visitante INSCRITO: o painel lateral mostra as noticias mais recentes do
 *    site, com marcacao de lidas, e o botao "Parar de receber";
 *  - visitante NAO inscrito: mostra o convite para ativar as notificacoes;
 *  - notificacoes bloqueadas no navegador: explica como liberar.
 *
 * A configuracao e LOCAL (opcao do WordPress), nao vai para o painel: o painel
 * so aceita as chaves de aparencia que conhece, e o sino e um recurso do site.
 *
 * As noticias vao embutidas na pagina (wp_localize_script), sem requisicao
 * extra por pageview. A lista fica em cache e e refeita quando um post e
 * publicado, editado ou removido.
 */
class Encontra_Push_Widget {

	private const OPTION = 'encontra_push_widget';
	private const CACHE  = 'encontra_push_widget_items';

	public function register(): void {
		// Qualquer mudanca de post publicado invalida a lista.
		add_action( 'transition_post_status', array( $this, 'maybe_flush' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'flush' ) );
		add_action( 'updated_option', array( $this, 'flush_on_option' ), 10, 1 );
	}

	/* ------------------------------------------------------ Configuracao -- */

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return array(
			'enabled'         => true,
			'position'        => 'left',
			'offset'          => 20,
			'count'           => 3,
			'desktop'         => true,
			'mobile'          => true,
			'badge'           => true,
			// "Amostra gratis": quem ainda nao ativou tambem ve as noticias,
			// com o convite logo abaixo. Ver sem receber e o melhor argumento
			// para ativar.
			'preview'         => true,
			// Card com a ultima noticia, ao lado do sino, ao abrir o site.
			'teaser'          => true,
			'teaser_seconds'  => 8,
			'title'           => __( 'Notícias recentes', 'encontra-push' ),
			'subscribe_title' => __( 'Receba as notícias em primeira mão', 'encontra-push' ),
			'subscribe_text'  => __( 'Ative as notificações e saiba na hora quando publicarmos algo novo.', 'encontra-push' ),
			'button_label'    => __( 'Ativar notificações', 'encontra-push' ),
			'subscribed_text' => __( 'Tudo certo! Você já está recebendo nossos alertas.', 'encontra-push' ),
			'blocked_text'    => __( 'As notificações estão bloqueadas neste navegador. Toque no cadeado ao lado do endereço do site, libere as notificações e recarregue a página.', 'encontra-push' ),
		);
	}

	/** @return array<string, mixed> */
	public function config(): array {
		return self::sanitize( wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() ) );
	}

	public function save( array $input ): void {
		// Checkbox desmarcado nao vem no POST: ausente e "nao".
		foreach ( array( 'enabled', 'desktop', 'mobile', 'badge', 'preview', 'teaser' ) as $flag ) {
			$input[ $flag ] = ! empty( $input[ $flag ] );
		}

		update_option( self::OPTION, self::sanitize( wp_parse_args( $input, self::defaults() ) ), true );

		$this->flush();
	}

	/**
	 * Lista fechada de chaves e faixas. Tudo o que sai daqui vai para todas as
	 * paginas do site, entao nada de HTML ou valor fora do previsto.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitize( array $input ): array {
		$defaults = self::defaults();

		$text = static function ( $value, string $fallback, int $max ): string {
			$value = trim( sanitize_text_field( (string) $value ) );

			return '' === $value ? $fallback : mb_substr( $value, 0, $max );
		};

		return array(
			'enabled'         => (bool) $input['enabled'],
			'position'        => in_array( $input['position'], array( 'right', 'left' ), true ) ? $input['position'] : 'left',
			'offset'          => max( 0, min( 200, absint( $input['offset'] ) ) ),
			'count'           => max( 1, min( 10, absint( $input['count'] ) ) ),
			'desktop'         => (bool) $input['desktop'],
			'mobile'          => (bool) $input['mobile'],
			'badge'           => (bool) $input['badge'],
			'preview'         => (bool) $input['preview'],
			'teaser'          => (bool) $input['teaser'],
			'teaser_seconds'  => max( 3, min( 30, absint( $input['teaser_seconds'] ) ) ),
			'title'           => $text( $input['title'], $defaults['title'], 60 ),
			'subscribe_title' => $text( $input['subscribe_title'], $defaults['subscribe_title'], 90 ),
			'subscribe_text'  => $text( $input['subscribe_text'], $defaults['subscribe_text'], 240 ),
			'button_label'    => $text( $input['button_label'], $defaults['button_label'], 40 ),
			'subscribed_text' => $text( $input['subscribed_text'], $defaults['subscribed_text'], 160 ),
			'blocked_text'    => $text( $input['blocked_text'], $defaults['blocked_text'], 240 ),
		);
	}

	/* ------------------------------------------------------------ Noticias */

	/**
	 * Ultimos posts publicados, prontos para o JavaScript.
	 *
	 * @return array<int, array{id: int, title: string, url: string, image: string, date: string}>
	 */
	public function items(): array {
		$config = $this->config();
		$cached = get_transient( self::CACHE );

		if ( is_array( $cached ) && ( $cached['count'] ?? 0 ) === $config['count'] ) {
			return $cached['items'];
		}

		$posts = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => $config['count'],
				'has_password'     => false,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		$items = array();

		foreach ( $posts as $post ) {
			$image = get_the_post_thumbnail_url( $post, 'thumbnail' );

			$items[] = array(
				'id'    => (int) $post->ID,
				'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
				'url'   => esc_url_raw( (string) get_permalink( $post ) ),
				'image' => $image ? esc_url_raw( $image ) : '',
				'date'  => (string) get_post_time( 'c', true, $post ),
			);
		}

		set_transient(
			self::CACHE,
			array(
				'count' => $config['count'],
				'items' => $items,
			),
			HOUR_IN_SECONDS
		);

		return $items;
	}

	/** Configuracao entregue ao front-end; null quando o sino esta desligado. */
	public function client_config(): ?array {
		$config = $this->config();

		if ( ! $config['enabled'] ) {
			return null;
		}

		return $config + array( 'items' => $this->items() );
	}

	/* ----------------------------------------------------------- Cache ---- */

	public function maybe_flush( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'post' !== $post->post_type ) {
			return;
		}

		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			$this->flush();
		}
	}

	public function flush(): void {
		delete_transient( self::CACHE );
	}

	/** Trocar o tamanho da miniatura ou o permalink muda os itens. */
	public function flush_on_option( string $option ): void {
		if ( in_array( $option, array( 'permalink_structure', 'thumbnail_size_w', 'thumbnail_size_h' ), true ) ) {
			$this->flush();
		}
	}
}
