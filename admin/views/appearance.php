<?php
/**
 * Secao 56 — aparencia.
 *
 * Desktop e celular tem conjuntos independentes, no mesmo formulario: a aba so
 * troca qual esta visivel, e os dois sao salvos juntos.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 * @var array                  $appearance
 */

defined( 'ABSPATH' ) || exit;

/*
 * Enquanto o conjunto do celular nunca tiver sido salvo, o formulario abre com
 * os valores do desktop — assim o primeiro save grava os dois completos e a
 * aba do celular nunca aparece em branco.
 */
$ep_mobile = array_merge( $appearance, (array) ( $appearance['mobile_settings'] ?? array() ) );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Aparência', 'encontra-push' ); ?></h1>

	<?php if ( ! $settings->is_connected() ) : ?>
		<?php require ENCONTRA_PUSH_DIR . 'admin/views/partial-not-connected.php'; ?>
		<?php return; ?>
	<?php endif; ?>

	<p class="description" style="max-width: 760px;">
		<?php esc_html_e( 'Desktop e celular têm cores e tema próprios. A posição do pré-prompt fica na tela Solicitação, junto com os outros ajustes de cada aparelho.', 'encontra-push' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'encontra_push_appearance' ); ?>
		<input type="hidden" name="action" value="encontra_push_appearance">

		<h2 class="nav-tab-wrapper">
			<a href="#" class="nav-tab nav-tab-active" data-ep-device-tab="desktop">
				<?php esc_html_e( 'Desktop', 'encontra-push' ); ?>
			</a>
			<a href="#" class="nav-tab" data-ep-device-tab="mobile">
				<?php esc_html_e( 'Celular', 'encontra-push' ); ?>
			</a>
		</h2>

		<div data-ep-device-panel="desktop">
			<?php
			$ep_prefix = 'appearance';
			$ep_id     = 'desktop';
			$ep_a      = $appearance;
			require ENCONTRA_PUSH_DIR . 'admin/views/partial-appearance-device.php';
			?>
		</div>

		<div data-ep-device-panel="mobile" hidden>
			<?php
			$ep_prefix = 'appearance[mobile_settings]';
			$ep_id     = 'mobile';
			$ep_a      = $ep_mobile;
			require ENCONTRA_PUSH_DIR . 'admin/views/partial-appearance-device.php';
			?>
		</div>

		<p class="description" style="max-width: 720px;">
			<?php esc_html_e( 'Esta tela aceita apenas cores e opções fechadas. CSS livre não é permitido aqui, nem para quem tem a permissão de HTML irrestrito: o pré-prompt roda em todas as páginas do site e um estilo arbitrário seria um vetor de injeção.', 'encontra-push' ); ?>
		</p>

		<?php submit_button( __( 'Salvar aparência', 'encontra-push' ) ); ?>
	</form>
</div>
