<?php
/**
 * Normalizacao dos dados de subscription vindos do navegador.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secoes 21 e 24.
 *
 * O que chega do navegador e dado nao confiavel: tudo passa por validacao de
 * formato e truncamento antes de seguir para o painel. O que NAO coletamos
 * tambem e uma decisao — nao ha IP, nao ha identificador de usuario, nao ha
 * fingerprint (secao 72: coleta minima).
 */
class Encontra_Push_Subscription {

	/**
	 * @return array|WP_Error
	 */
	public static function from_request( WP_REST_Request $request ) {
		$endpoint = esc_url_raw( (string) $request->get_param( 'endpoint' ) );
		$p256dh   = sanitize_text_field( (string) $request->get_param( 'p256dh' ) );
		$auth     = sanitize_text_field( (string) $request->get_param( 'auth' ) );

		if ( '' === $endpoint || '' === $p256dh || '' === $auth ) {
			return new WP_Error(
				'invalid_subscription',
				__( 'Dados de inscrição incompletos.', 'encontra-push' ),
				array( 'status' => 400 )
			);
		}

		if ( ! preg_match( '#^https://#i', $endpoint ) ) {
			return new WP_Error(
				'invalid_endpoint',
				__( 'Endpoint de push inválido.', 'encontra-push' ),
				array( 'status' => 400 )
			);
		}

		$source_url = esc_url_raw( (string) $request->get_param( 'source_url' ) );

		return array(
			'endpoint'         => $endpoint,
			'p256dh'           => $p256dh,
			'auth'             => $auth,
			'content_encoding' => in_array( $request->get_param( 'content_encoding' ), array( 'aes128gcm', 'aesgcm' ), true )
				? (string) $request->get_param( 'content_encoding' )
				: 'aes128gcm',
			'expiration_time'  => $request->get_param( 'expiration_time' ) ? (int) $request->get_param( 'expiration_time' ) : null,
			'is_test'          => (bool) $request->get_param( 'is_test' ),
			'taxonomies'       => self::taxonomies_for( $source_url ),
			'context'          => self::geo_context() + array(
				'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] )
					? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 400 )
					: '',
				'language'     => substr( sanitize_text_field( (string) $request->get_param( 'language' ) ), 0, 16 ),
				'timezone'     => substr( sanitize_text_field( (string) $request->get_param( 'timezone' ) ), 0, 64 ),
				'device_type'  => sanitize_key( (string) $request->get_param( 'device_type' ) ),
				'source_url'   => $source_url,
				'source_path'  => substr( (string) wp_parse_url( $source_url, PHP_URL_PATH ), 0, 512 ),
				'referrer'     => substr( esc_url_raw( (string) $request->get_param( 'referrer' ) ), 0, 512 ),
				'utm_source'   => substr( sanitize_text_field( (string) $request->get_param( 'utm_source' ) ), 0, 128 ),
				'utm_medium'   => substr( sanitize_text_field( (string) $request->get_param( 'utm_medium' ) ), 0, 128 ),
				'utm_campaign' => substr( sanitize_text_field( (string) $request->get_param( 'utm_campaign' ) ), 0, 128 ),
				'utm_content'  => substr( sanitize_text_field( (string) $request->get_param( 'utm_content' ) ), 0, 128 ),
			),
		);
	}

	/**
	 * Secao 41 — pais, estado e cidade, pela borda da Cloudflare.
	 *
	 * Quem enxerga o IP do visitante e ESTE servidor, nao o painel: o painel
	 * so recebe a chamada assinada que sai daqui, entao um GeoIP la apontaria
	 * sempre para a hospedagem do site. Por isso a leitura acontece no plugin.
	 *
	 * `CF-IPCountry` vem por padrao em qualquer zona. `CF-Region` e
	 * `CF-IPCity` exigem o Managed Transform "Add visitor location headers"
	 * ligado na zona do site. Sem ele, so o pais e preenchido — nada quebra.
	 *
	 * O IP em si nunca e lido nem enviado (secao 72: coleta minima).
	 *
	 * @return array<string, string>
	 */
	private static function geo_context(): array {
		$campos = array(
			'country' => 'HTTP_CF_IPCOUNTRY',
			'region'  => 'HTTP_CF_REGION',
			'city'    => 'HTTP_CF_IPCITY',
		);

		$out = array();

		foreach ( $campos as $chave => $cabecalho ) {
			if ( empty( $_SERVER[ $cabecalho ] ) ) {
				continue;
			}

			$valor = trim( wp_unslash( (string) $_SERVER[ $cabecalho ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Cabecalho HTTP nao carrega UTF-8 de forma confiavel: "Sao Paulo"
			// com acento pode chegar em ISO-8859-1 e viraria lixo no banco.
			if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $valor, 'UTF-8' ) ) {
				$valor = mb_convert_encoding( $valor, 'UTF-8', 'ISO-8859-1' );
			}

			$valor = sanitize_text_field( $valor );

			if ( '' !== $valor ) {
				$out[ $chave ] = $valor;
			}
		}

		// XX = desconhecido, T1 = rede Tor. O painel tambem recusa, mas nao
		// custa nada nao mandar.
		if ( isset( $out['country'] ) && in_array( strtoupper( $out['country'] ), array( 'XX', 'T1' ), true ) ) {
			unset( $out['country'] );
		}

		return $out;
	}

	/**
	 * Secao 24 — categorias da pagina onde a inscricao aconteceu.
	 *
	 * Resolvemos no servidor, a partir da URL, em vez de confiar no que o
	 * navegador enviar: assim ninguem pode se auto-atribuir categorias
	 * chamando a rota publica com um corpo forjado.
	 *
	 * @return array<string, array<int, array{id: int, slug: string}>>
	 */
	private static function taxonomies_for( string $source_url ): array {
		if ( '' === $source_url ) {
			return array();
		}

		$post_id = url_to_postid( $source_url );

		if ( ! $post_id ) {
			return array();
		}

		$out = array();

		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );

			if ( ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$out[ $taxonomy ][] = array(
					'id'   => (int) $term->term_id,
					'slug' => (string) $term->slug,
				);
			}
		}

		return $out;
	}
}
