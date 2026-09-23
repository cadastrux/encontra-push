<?php
/**
 * Secao 57 — diagnostico, e secao 58 — teste de push.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 * @var array                  $checks
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Diagnóstico', 'encontra-push' ); ?></h1>

	<div class="ep-columns">
		<div class="card" style="max-width: none;">
			<h2><?php esc_html_e( 'Verificações do servidor', 'encontra-push' ); ?></h2>

			<?php foreach ( $checks as $check ) : ?>
				<div class="ep-check">
					<span class="ep-check__mark ep-check__mark--<?php echo esc_attr( $check['status'] ); ?>">
						<?php
						echo 'ok' === $check['status'] ? '&#10003;' : ( 'warning' === $check['status'] ? '!' : '&#10007;' );
						?>
					</span>
					<div>
						<strong><?php echo esc_html( $check['label'] ); ?></strong>
						<?php if ( 'ok' !== $check['status'] ) : ?>
							<div class="ep-check__hint"><?php echo esc_html( $check['hint'] ); ?></div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="card" style="max-width: none;">
			<h2><?php esc_html_e( 'Verificações deste navegador', 'encontra-push' ); ?></h2>

			<p class="description">
				<?php esc_html_e( 'Service Worker, conflito de scope e permissão só podem ser inspecionados pelo navegador. O resultado abaixo vale para o navegador em que você está agora.', 'encontra-push' ); ?>
			</p>

			<div id="ep-browser-diagnostics">
				<p><?php esc_html_e( 'Verificando…', 'encontra-push' ); ?></p>
			</div>

			<?php if ( $settings->is_connected() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'encontra_push_diagnostics' ); ?>
					<input type="hidden" name="action" value="encontra_push_diagnostics">
					<input type="hidden" name="browser_checks" id="ep-browser-checks" value="">
					<?php submit_button( __( 'Enviar diagnóstico ao painel', 'encontra-push' ), 'secondary' ); ?>
				</form>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $settings->is_connected() && $settings->vapid_public_key() ) : ?>
		<div class="card" style="max-width: none;">
			<h2><?php esc_html_e( 'Teste de push', 'encontra-push' ); ?></h2>

			<p>
				<?php esc_html_e( 'O teste cria uma subscription marcada como "de teste" neste navegador e pede ao painel que envie uma mensagem para ela. A subscription de teste não entra em campanhas.', 'encontra-push' ); ?>
			</p>

			<p>
				<button type="button" class="button button-primary" id="ep-test-push">
					<?php esc_html_e( 'Executar teste', 'encontra-push' ); ?>
				</button>
			</p>

			<p id="ep-test-result" aria-live="polite"></p>

			<h3><?php esc_html_e( 'O que cada estado significa', 'encontra-push' ); ?></h3>
			<ul style="list-style: disc; padding-left: 20px;">
				<li><?php esc_html_e( 'Enviado ao Push Service: o serviço aceitou a solicitação.', 'encontra-push' ); ?></li>
				<li><?php esc_html_e( 'Recebido: o Service Worker executou o evento (melhor esforço).', 'encontra-push' ); ?></li>
				<li><?php esc_html_e( 'Exibido: showNotification concluiu (melhor esforço).', 'encontra-push' ); ?></li>
				<li><?php esc_html_e( 'Clicado: única prova real de que o usuário interagiu.', 'encontra-push' ); ?></li>
			</ul>
		</div>
	<?php endif; ?>
</div>
