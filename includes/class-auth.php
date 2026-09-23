<?php
/**
 * Assinatura HMAC das requisicoes ao painel.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 13 — autenticacao Plugin -> Painel.
 *
 * A string canonica precisa ser construida exatamente como no painel
 * (App\Support\HmacSignature). Qualquer divergencia — uma barra a mais no
 * caminho, o corpo serializado de outro jeito — derruba a autenticacao de
 * todos os sites de uma vez.
 *
 *     METHOD
 *     PATH
 *     SHA256(BODY)
 *     TIMESTAMP
 *     NONCE
 */
class Encontra_Push_Auth {

	public const HEADER_SITE      = 'X-EP-Site';
	public const HEADER_KEY       = 'X-EP-Key';
	public const HEADER_TIMESTAMP = 'X-EP-Timestamp';
	public const HEADER_NONCE     = 'X-EP-Nonce';
	public const HEADER_SIGNATURE = 'X-EP-Signature';

	public static function canonical_string( string $method, string $path, string $body, string $timestamp, string $nonce ): string {
		return implode(
			"\n",
			array(
				strtoupper( $method ),
				$path,
				hash( 'sha256', $body ),
				$timestamp,
				$nonce,
			)
		);
	}

	public static function sign( string $canonical, string $secret ): string {
		return hash_hmac( 'sha256', $canonical, $secret );
	}

	/**
	 * Monta os cabecalhos de autenticacao de uma requisicao.
	 *
	 * @return array<string, string>
	 */
	public static function headers( string $method, string $path, string $body, string $site_id, string $key_id, string $secret ): array {
		$timestamp = (string) time();
		$nonce     = bin2hex( random_bytes( 16 ) );

		$canonical = self::canonical_string( $method, $path, $body, $timestamp, $nonce );

		return array(
			self::HEADER_SITE      => $site_id,
			self::HEADER_KEY       => $key_id,
			self::HEADER_TIMESTAMP => $timestamp,
			self::HEADER_NONCE     => $nonce,
			self::HEADER_SIGNATURE => self::sign( $canonical, $secret ),
		);
	}
}
