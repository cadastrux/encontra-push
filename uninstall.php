<?php
/**
 * Desinstalacao do plugin.
 *
 * @package EncontraPush
 */

// Executado pelo WordPress apenas na exclusao definitiva do plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Secao 102 — desinstalacao.
 *
 * Regra da especificacao: "nao apagar subscription central automaticamente".
 * A base de assinantes pertence ao painel e representa consentimento dado
 * pelos visitantes; remover o plugin de um site — inclusive por engano, ou
 * durante uma migracao — nao pode destruir isso.
 *
 * Portanto, por padrao, a desinstalacao NAO revoga a conexao e NAO chama o
 * painel. Ela apenas limpa os dados locais quando o operador marcou essa
 * opcao explicitamente na tela de desinstalacao.
 */

$options = get_option( 'encontra_push_uninstall_options', array() );

$remove_local  = ! empty( $options['remove_local'] );
$revoke_remote = ! empty( $options['revoke_connection'] );

if ( $revoke_remote ) {
	/*
	 * Revogacao explicita: pede ao painel que revogue a credencial deste
	 * site. Mesmo aqui, os assinantes permanecem — apenas a credencial de API
	 * deixa de valer.
	 *
	 * SEC-014: a versao anterior mandava um heartbeat SEM assinatura, que o
	 * painel recusava — a credencial continuava valida e o operador achava que
	 * tinha revogado. Agora a chamada e assinada e vai para /revoke.
	 */
	if ( ! class_exists( 'Encontra_Push_Settings' ) ) {
		require_once __DIR__ . '/includes/class-settings.php';
	}

	if ( ! class_exists( 'Encontra_Push_Auth' ) ) {
		require_once __DIR__ . '/includes/class-auth.php';
	}

	$ep_settings = new Encontra_Push_Settings();

	if ( $ep_settings->is_connected() && '' !== $ep_settings->api_secret() ) {
		$ep_path = '/api/v1/sites/' . rawurlencode( $ep_settings->site_id() ) . '/revoke';

		wp_remote_post(
			$ep_settings->api_url() . $ep_path,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array_merge(
					Encontra_Push_Auth::headers(
						'POST',
						$ep_path,
						'',
						$ep_settings->site_id(),
						$ep_settings->api_key_id(),
						$ep_settings->api_secret()
					),
					array( 'Accept' => 'application/json' )
				),
			)
		);
	}
}

if ( $remove_local || $revoke_remote ) {
	delete_option( 'encontra_push_connection' );
	delete_option( 'encontra_push_config' );
	delete_option( 'encontra_push_state' );
	delete_option( 'encontra_push_db_version' );
	delete_option( 'encontra_push_uninstall_options' );
	delete_option( 'encontra_push_widget' );
	delete_option( 'encontra_push_version' );
	delete_transient( 'encontra_push_widget_items' );

	// Transients de metricas (secao 54).
	foreach ( array( 'metrics', 'campaigns', 'subscribers' ) as $key ) {
		delete_transient( 'encontra_push_' . $key );
	}

	// Meta por post do metabox de AutoPush.
	delete_post_meta_by_key( '_encontra_push_options' );
	delete_post_meta_by_key( '_encontra_push_sent' );

	// Capabilities proprias (secao 99).
	if ( class_exists( 'Encontra_Push' ) ) {
		Encontra_Push::revoke_capabilities();
	} else {
		$capabilities = array(
			'encontra_push_view',
			'encontra_push_manage',
			'encontra_push_campaign',
			'encontra_push_settings',
		);

		foreach ( wp_roles()->roles as $role_name => $details ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}
}

wp_clear_scheduled_hook( 'encontra_push_heartbeat' );
wp_clear_scheduled_hook( 'encontra_push_sync_taxonomies' );
