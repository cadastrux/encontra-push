<?php
/**
 * Aviso exibido quando o site ainda nao foi conectado ao painel.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="notice notice-warning">
	<p>
		<strong><?php esc_html_e( 'Este site ainda não está conectado ao painel.', 'encontra-push' ); ?></strong>
	</p>
	<p>
		<?php esc_html_e( 'Gere um código de conexão no painel Encontra Push e informe-o na tela de Integração.', 'encontra-push' ); ?>
	</p>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=encontra-push-integracao' ) ); ?>">
			<?php esc_html_e( 'Ir para Integração', 'encontra-push' ); ?>
		</a>
	</p>
</div>
