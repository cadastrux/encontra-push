<?php
/**
 * Metricas do painel exibidas no wp-admin.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 54 — cache das metricas.
 *
 * "Nao consultar painel a cada carregamento do wp-admin." Com dezenas de
 * sites, isso viraria uma enxurrada de requisicoes ao painel toda vez que
 * alguem abrisse o dashboard. O transient de 5 minutos resolve, e o botao
 * "Atualizar agora" existe para quem precisa do numero exato.
 */
class Encontra_Push_Metrics {

	private const TTL = 300;

	public function __construct( private Encontra_Push_Api_Client $api ) {}

	public function metrics( bool $force = false ): array {
		return $this->cached( 'metrics', '/metrics', array( 'days' => 30 ), $force );
	}

	public function campaigns( bool $force = false ): array {
		return $this->cached( 'campaigns', '/campaigns', array( 'limit' => 10 ), $force );
	}

	public function subscribers( bool $force = false ): array {
		return $this->cached( 'subscribers', '/subscribers/summary', array(), $force );
	}

	public function flush(): void {
		foreach ( array( 'metrics', 'campaigns', 'subscribers' ) as $key ) {
			delete_transient( 'encontra_push_' . $key );
		}
	}

	/**
	 * @return array{ok: bool, data: array, error: string, cached_at: int}
	 */
	private function cached( string $key, string $suffix, array $query, bool $force ): array {
		$transient = 'encontra_push_' . $key;

		if ( ! $force ) {
			$cached = get_transient( $transient );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = $this->api->get( $this->api->site_path( $suffix ), $query );

		$payload = array(
			'ok'        => $result['ok'],
			'data'      => $result['data'],
			'error'     => $result['error'],
			'cached_at' => time(),
		);

		// Uma falha tambem e cacheada, por menos tempo: se o painel estiver
		// fora do ar, nao adianta tentar a cada refresh do wp-admin.
		set_transient( $transient, $payload, $result['ok'] ? self::TTL : 60 );

		return $payload;
	}
}
