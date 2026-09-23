<?php
/**
 * Envio do evento de publicacao ao painel.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 29 — AutoPush WordPress.
 *
 * A regra e enviar SOMENTE quando houver transicao valida para publish. Isso
 * exclui, de uma vez:
 *
 *   - autosave e revisao;
 *   - atualizacao de um post que ja estava publicado;
 *   - quick edit, que tambem passa por save_post;
 *   - update via REST, que dispara os mesmos ganchos.
 *
 * A checagem de "$old_status === 'publish'" e o que impede o caso mais comum
 * de notificacao duplicada: editar um post publicado e disparar tudo de novo.
 *
 * O gancho e `wp_after_insert_post`, e NAO `transition_post_status`, por um
 * motivo concreto: no editor de blocos e na API REST, a imagem destacada e os
 * termos sao gravados DEPOIS do post. O WP_REST_Posts_Controller chama
 * wp_insert_post() com $fire_after_hooks = false, trata featured_media e as
 * taxonomias, e so entao dispara wp_after_insert_post — que existe justamente
 * para isso ("uma vez que o post, seus termos e metadados foram salvos").
 *
 * Com transition_post_status, get_the_post_thumbnail_url() ainda devolvia
 * false nesse caminho, e a notificacao saia sem imagem mesmo com o post tendo
 * imagem destacada. Em sites que publicam por automacao (que usa REST), isso
 * valia para TODAS as publicacoes.
 */
class Encontra_Push_Publisher {

	public function __construct(
		private Encontra_Push_Settings $settings,
		private Encontra_Push_Api_Client $api
	) {}

	public function register(): void {
		add_action( 'wp_after_insert_post', array( $this, 'on_inserted' ), 10, 4 );
		add_action( 'encontra_push_sync_taxonomies', array( $this, 'sync_taxonomies' ) );

		if ( ! wp_next_scheduled( 'encontra_push_sync_taxonomies' ) ) {
			wp_schedule_event( time() + 900, 'daily', 'encontra_push_sync_taxonomies' );
		}
	}

	/**
	 * @param int          $post_id     ID do post salvo.
	 * @param WP_Post      $post        Post ja com termos e metadados gravados.
	 * @param bool         $update      Se foi atualizacao de post existente.
	 * @param WP_Post|null $post_before Estado anterior; null quando e criacao.
	 */
	public function on_inserted( int $post_id, $post, bool $update, $post_before = null ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// O status anterior sai de $post_before. Em criacao ele e null, e
		// "novo" nunca e 'publish' — entao a guarda contra republicacao
		// continua valendo igual ao que transition_post_status dava.
		$old_status = $post_before instanceof WP_Post ? $post_before->post_status : 'new';

		if ( ! $this->should_notify( $post->post_status, $old_status, $post ) ) {
			return;
		}

		$override = $this->override_for( $post );

		// Secao 29.2: o metabox pode vetar o envio deste post especifico.
		if ( 'never' === ( $override['mode'] ?? 'auto' ) ) {
			return;
		}

		$payload = $this->build_payload( $post, $override );

		$result = $this->api->post(
			$this->api->site_path( '/posts/published' ),
			$payload,
			// Secao 88: chave derivada de site + post + revisao. O painel
			// rejeita a repeticao, entao um retry de rede nao vira campanha
			// duplicada.
			array( 'Idempotency-Key' => 'pub-' . $post->ID . '-' . $payload['publish_revision'] )
		);

		update_post_meta(
			$post->ID,
			'_encontra_push_sent',
			array(
				'at'      => time(),
				'ok'      => $result['ok'],
				'message' => $result['ok'] ? '' : $result['error'],
			)
		);
	}

	/**
	 * Decide se este evento merece virar notificacao.
	 */
	private function should_notify( string $new_status, string $old_status, WP_Post $post ): bool {
		if ( ! $this->settings->is_connected() ) {
			return false;
		}

		// A unica transicao que interessa.
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return false;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( 'auto-draft' === $post->post_status || empty( $post->post_title ) ) {
			return false;
		}

		$types = get_post_types( array( 'public' => true ) );

		if ( ! in_array( $post->post_type, $types, true ) ) {
			return false;
		}

		// Post publicado com data retroativa (importacao, migracao) nao deve
		// notificar: a "novidade" e antiga.
		$published = strtotime( $post->post_date_gmt . ' UTC' );

		if ( $published && $published < ( time() - DAY_IN_SECONDS ) ) {
			return false;
		}

		/**
		 * Permite que o site vete ou libere um caso especifico.
		 *
		 * @param bool    $should Se o evento deve ser enviado.
		 * @param WP_Post $post   Post publicado.
		 */
		return (bool) apply_filters( 'encontra_push_should_notify', true, $post );
	}

	private function build_payload( WP_Post $post, array $override ): array {
		$categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'slugs' ) );
		$tags       = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );

		$image = get_the_post_thumbnail_url( $post, 'large' );

		/*
		 * Duas versoes da imagem destacada, porque a notificacao usa as duas de
		 * formas diferentes:
		 *
		 *   image — a imagem grande, que o Chrome mostra ao expandir;
		 *   icon  — a miniatura quadrada ao lado do texto, sempre visivel.
		 *
		 * Mandar a versao grande como icone gastaria banda do visitante a toa:
		 * o navegador a exibe com cerca de 64 px.
		 */
		$thumb = get_the_post_thumbnail_url( $post, 'medium' );

		/**
		 * Permite ao site fornecer a imagem por outro caminho.
		 *
		 * get_the_post_thumbnail_url() so enxerga a imagem destacada NATIVA
		 * (_thumbnail_id). Tema ou plugin que guarde a capa num campo proprio
		 * fica de fora, e a notificacao sai sem imagem sem que nada acuse o
		 * motivo. Este filtro e a saida para esse caso:
		 *
		 *   add_filter( 'encontra_push_post_image', function ( $url, $post, $tamanho ) {
		 *       return $url ?: get_post_meta( $post->ID, 'minha_capa', true );
		 *   }, 10, 3 );
		 *
		 * @param string|false $url     URL encontrada, ou false.
		 * @param WP_Post      $post    Post publicado.
		 * @param string       $tamanho Tamanho pedido ('large' ou 'medium').
		 */
		$image = apply_filters( 'encontra_push_post_image', $image, $post, 'large' );
		$thumb = apply_filters( 'encontra_push_post_image', $thumb, $post, 'medium' );

		return array(
			'post_id'          => (int) $post->ID,
			'post_type'        => (string) $post->post_type,
			'title'            => wp_strip_all_tags( get_the_title( $post ) ),
			'excerpt'          => $this->excerpt( $post ),
			'url'              => get_permalink( $post ),
			'image'            => $image ? esc_url_raw( $image ) : null,
			'image_thumb'      => $thumb ? esc_url_raw( $thumb ) : null,
			'author'           => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'author_id'        => (int) $post->post_author,
			'categories'       => is_wp_error( $categories ) ? array() : $categories,
			'tags'             => is_wp_error( $tags ) ? array() : $tags,
			'published_at'     => get_post_time( 'c', true, $post ),
			// Secao 29.3: identifica esta publicacao especifica. Republicar o
			// mesmo post depois de despublicar gera uma revisao diferente.
			'publish_revision' => substr( hash( 'sha256', $post->ID . '|' . $post->post_date_gmt . '|' . $post->post_modified_gmt ), 0, 32 ),
			'source_event_id'  => 'wp:' . $post->ID . ':' . $post->post_date_gmt,
			'override'         => $override,
		);
	}

	private function excerpt( WP_Post $post ): string {
		$excerpt = has_excerpt( $post )
			? get_the_excerpt( $post )
			: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' );

		return trim( wp_strip_all_tags( $excerpt ) );
	}

	/** Secao 29.2 — opcoes definidas no metabox do post. */
	private function override_for( WP_Post $post ): array {
		$meta = get_post_meta( $post->ID, '_encontra_push_options', true );

		if ( ! is_array( $meta ) ) {
			return array( 'mode' => 'auto' );
		}

		return array(
			'mode'         => in_array( $meta['mode'] ?? 'auto', array( 'auto', 'always', 'never' ), true ) ? $meta['mode'] : 'auto',
			'title'        => ! empty( $meta['title'] ) ? sanitize_text_field( $meta['title'] ) : null,
			'body'         => ! empty( $meta['body'] ) ? sanitize_text_field( $meta['body'] ) : null,
			'image'        => ! empty( $meta['image'] ) ? esc_url_raw( $meta['image'] ) : null,
			'scheduled_at' => ! empty( $meta['scheduled_at'] ) ? sanitize_text_field( $meta['scheduled_at'] ) : null,
			'segment_id'   => ! empty( $meta['segment_id'] ) ? sanitize_text_field( $meta['segment_id'] ) : null,
		);
	}

	/**
	 * Secao 24 — sincroniza as taxonomias do site com o painel, para que o
	 * construtor de segmentos e os filtros de AutoPush conhecam as categorias
	 * reais sem consultar o WordPress a cada tela.
	 */
	public function sync_taxonomies(): void {
		if ( ! $this->settings->is_connected() ) {
			return;
		}

		$payload = array();

		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 500,
				)
			);

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$payload[] = array(
					'taxonomy' => $taxonomy,
					'term_id'  => (int) $term->term_id,
					'slug'     => (string) $term->slug,
					'name'     => (string) $term->name,
					'count'    => (int) $term->count,
				);
			}
		}

		if ( array() === $payload ) {
			return;
		}

		foreach ( array_chunk( $payload, 200 ) as $chunk ) {
			$this->api->post( $this->api->site_path( '/taxonomy/sync' ), array( 'taxonomies' => $chunk ) );
		}
	}
}
