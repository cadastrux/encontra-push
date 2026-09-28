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

		/*
		 * Duas versoes da imagem destacada, porque a notificacao usa as duas de
		 * formas diferentes:
		 *
		 *   image — o banner deitado que o Chrome mostra ao expandir;
		 *   icon  — a miniatura quadrada ao lado do texto, sempre visivel.
		 *
		 * A escolha e feita por DIMENSAO, e nao por nome de tamanho. O motivo:
		 * get_the_post_thumbnail_url( $post, 'large' ) devolve o arquivo
		 * ORIGINAL quando o tamanho 'large' nao existe, sem dizer que fez isso.
		 * Numa imagem destacada de 280x280 — o WordPress so gera 'medium' a
		 * partir de 300 px de largura — 'large' e 'medium' voltavam os dois o
		 * mesmo quadrado de 280 px, e o plugin mandava esse quadrado no campo
		 * do banner. O painel anunciava "imagem grande enviada" e o Chrome nao
		 * tinha o que exibir, porque um quadrado pequeno nao preenche um espaco
		 * deitado de proporcao 2:1.
		 *
		 * wp_get_attachment_image_src() devolve largura e altura junto com a
		 * URL, entao da para decidir com o dado na mao em vez de confiar no
		 * nome do tamanho.
		 */
		$attachment_id = (int) get_post_thumbnail_id( $post );

		$image = $attachment_id ? $this->banner_url( $attachment_id ) : false;
		$thumb = $attachment_id ? $this->miniatura_url( $attachment_id ) : false;

		/**
		 * Permite ao site fornecer a imagem por outro caminho.
		 *
		 * A busca acima so enxerga a imagem destacada NATIVA (_thumbnail_id).
		 * Tema ou plugin que guarde a capa num campo proprio fica de fora, e a
		 * notificacao sai sem imagem sem que nada acuse o motivo. Este filtro e
		 * a saida para esse caso:
		 *
		 *   add_filter( 'encontra_push_post_image', function ( $url, $post, $tamanho ) {
		 *       return $url ?: get_post_meta( $post->ID, 'minha_capa', true );
		 *   }, 10, 3 );
		 *
		 * O filtro vem DEPOIS da escolha por dimensao, e nao antes: uma URL
		 * dada pelo site passa direto, sem medicao. Nao ha como medi-la sem
		 * baixar o arquivo, e baixar imagem a cada publicacao atrasaria o
		 * gancho de publicacao do WordPress. Quem usa o filtro assume a
		 * responsabilidade de mandar um banner deitado em 'large'.
		 *
		 * @param string|false $url     URL escolhida, ou false quando nenhuma serve.
		 * @param WP_Post      $post    Post publicado.
		 * @param string       $tamanho 'large' para o banner deitado, 'medium' para o icone.
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

	/**
	 * Largura minima para uma imagem valer como banner.
	 *
	 * O numero vem do que se observou renderizar, e nao de uma especificacao:
	 * um WebP de 300x200 aparece no Chrome (esticado e sem nitidez, mas
	 * aparece), enquanto um quadrado de 280x280 no mesmo lugar nao produz
	 * banner util. O corte fica logo acima do primeiro, para nao derrubar o
	 * que hoje funciona, e logo abaixo do segundo, que era o defeito.
	 *
	 * Deitada e bonita comeca perto de 1200x600; isto aqui e o piso, nao o
	 * alvo.
	 */
	private const BANNER_LARGURA_MINIMA = 300;

	/**
	 * Acima disto o arquivo so gasta banda: o navegador exibe o banner em
	 * algumas centenas de pixels, e quem recebe costuma estar no celular.
	 */
	private const BANNER_LARGURA_MAXIMA = 1600;

	/**
	 * A melhor versao da imagem destacada para o banner deitado.
	 *
	 * Devolve false quando nenhuma versao serve — e nesse caso a notificacao
	 * sai sem banner, de proposito. Mandar um quadrado pequeno neste campo e
	 * pior que nao mandar nada: o navegador estica, corta ou descarta, e o
	 * painel fica dizendo que enviou uma imagem que ninguem viu.
	 *
	 * @return string|false
	 */
	private function banner_url( int $attachment_id ) {
		$candidatas = $this->versoes( $attachment_id, array( 'large', 'medium_large', 'full' ) );

		// Nenhuma medida: nao da para julgar, entao nao se julga.
		if ( ! $candidatas ) {
			return $this->sem_medidas( $attachment_id );
		}

		$servem = array_filter(
			$candidatas,
			static function ( array $v ): bool {
				// Deitada, ou perto disso. Uma foto em pe no lugar do banner
				// aparece cortada na cabeca de quem esta na imagem.
				return $v['w'] >= self::BANNER_LARGURA_MINIMA && $v['w'] >= $v['h'];
			}
		);

		if ( ! $servem ) {
			return false;
		}

		/*
		 * Entre as que servem, a maior que ainda cabe no teto. Com um original
		 * de 1200x600 isso escolhe o proprio original; com um de 4000x2000
		 * escolhe o 'large' de 1024, evitando mandar 4000 px para um espaco
		 * que nunca passa de algumas centenas.
		 */
		$noTeto = array_values(
			array_filter(
				$servem,
				static fn( array $v ): bool => $v['w'] <= self::BANNER_LARGURA_MAXIMA
			)
		);

		if ( $noTeto ) {
			usort( $noTeto, static fn( array $a, array $b ): int => $b['w'] <=> $a['w'] );

			return $noTeto[0]['url'];
		}

		// Todas passam do teto: fica a menor, que e a menos pesada.
		$servem = array_values( $servem );
		usort( $servem, static fn( array $a, array $b ): int => $a['w'] <=> $b['w'] );

		return $servem[0]['url'];
	}

	/**
	 * A melhor versao para o icone: o menor quadrado que ainda fica nitido.
	 *
	 * O navegador exibe este espaco com algo entre 64 e 192 px. Mandar o
	 * arquivo grande aqui faz cada assinante baixar centenas de kB para ver
	 * uma miniatura — e sao milhares de assinantes por disparo.
	 *
	 * @return string|false
	 */
	private function miniatura_url( int $attachment_id ) {
		$candidatas = $this->versoes( $attachment_id, array( 'thumbnail', 'medium', 'full' ) );

		if ( ! $candidatas ) {
			return $this->sem_medidas( $attachment_id );
		}

		$nitidas = array_values(
			array_filter(
				$candidatas,
				static fn( array $v ): bool => $v['w'] >= 96
			)
		);

		$escolhidas = $nitidas ? $nitidas : $candidatas;

		// A menor que ainda serve: e a que menos custa a quem recebe.
		usort( $escolhidas, static fn( array $a, array $b ): int => $a['w'] <=> $b['w'] );

		return $escolhidas[0]['url'];
	}

	/**
	 * Saida para o anexo cujas dimensoes o WordPress nao conhece.
	 *
	 * Existe porque isto acontece de verdade, e nao e caso de canto: uma
	 * automacao que grava o arquivo e aponta _thumbnail_id sem chamar
	 * wp_generate_attachment_metadata() deixa o anexo sem _wp_attachment_metadata.
	 * Sem esse registro, image_downsize() devolve false e
	 * wp_get_attachment_image_src() devolve false para TODOS os tamanhos —
	 * inclusive 'full'. Medir aqui exigiria abrir o arquivo a cada publicacao.
	 *
	 * Nesse caso a escolha por dimensao simplesmente nao se aplica, e a regra
	 * passa a ser a antiga: manda a URL do arquivo e deixa o navegador decidir.
	 * Uma melhoria nao pode transformar "imagem as vezes ruim" em "nunca
	 * imagem" para quem depende desse caminho.
	 *
	 * @return string|false
	 */
	private function sem_medidas( int $attachment_id ) {
		$url = wp_get_attachment_url( $attachment_id );

		return $url ? $url : false;
	}

	/**
	 * URLs e dimensoes reais das versoes pedidas, sem repeticao.
	 *
	 * A deduplicacao pela URL e o coracao disto: quando um tamanho registrado
	 * nao existe, o WordPress devolve o arquivo original em vez de avisar.
	 * Sem dedupe, o mesmo arquivo apareceria tres vezes e pareceria haver
	 * escolha onde nao ha nenhuma.
	 *
	 * @param  string[] $tamanhos Nomes de tamanho do WordPress.
	 * @return array<int, array{url: string, w: int, h: int}>
	 */
	private function versoes( int $attachment_id, array $tamanhos ): array {
		$porUrl = array();

		foreach ( $tamanhos as $tamanho ) {
			$src = wp_get_attachment_image_src( $attachment_id, $tamanho );

			if ( ! is_array( $src ) || empty( $src[0] ) || empty( $src[1] ) ) {
				continue;
			}

			$porUrl[ $src[0] ] = array(
				'url' => (string) $src[0],
				'w'   => (int) $src[1],
				'h'   => (int) $src[2],
			);
		}

		return array_values( $porUrl );
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
