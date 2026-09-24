<?php
/**
 * Secao 55 — configuracao de inscricao, com previa ao vivo.
 *
 * Desktop e celular tem conjuntos independentes. Os dois ficam no formulario
 * ao mesmo tempo, e a aba so troca qual esta visivel: assim o envio leva os
 * dois de uma vez e nada depende de JavaScript para ser salvo.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 * @var array                  $prompt
 */

defined( 'ABSPATH' ) || exit;

/*
 * Enquanto o conjunto do celular nunca tiver sido salvo, o formulario abre com
 * os valores do desktop. Assim o primeiro save ja grava os dois completos, e a
 * partir dali eles seguem separados. Sem isso, a aba do celular abriria vazia
 * e quem salvasse sem olhar apagaria a configuracao que o site vinha usando.
 */
$ep_mobile = array_merge( $prompt, (array) ( $prompt['mobile_settings'] ?? array() ) );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Solicitação de permissão', 'encontra-push' ); ?></h1>

	<?php if ( ! $settings->is_connected() ) : ?>
		<?php require ENCONTRA_PUSH_DIR . 'admin/views/partial-not-connected.php'; ?>
		<?php return; ?>
	<?php endif; ?>

	<p class="description" style="max-width: 760px;">
		<?php esc_html_e( 'Desktop e celular têm configurações próprias: gatilho, textos, posição e até a forma de pedir a permissão. Trocar de aba não perde o que foi digitado na outra — as duas são salvas juntas.', 'encontra-push' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'encontra_push_prompt' ); ?>
		<input type="hidden" name="action" value="encontra_push_prompt">

		<div class="ep-columns">
			<div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Onde aparece', 'encontra-push' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="prompt[desktop]" value="1" <?php checked( ! empty( $prompt['desktop'] ) ); ?>>
								<?php esc_html_e( 'Desktop', 'encontra-push' ); ?>
							</label>

							<label style="margin-left: 12px;">
								<input type="checkbox" name="prompt[mobile]" value="1" <?php checked( ! empty( $prompt['mobile'] ) ); ?>>
								<?php esc_html_e( 'Celular e tablet', 'encontra-push' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Desmarcado, o pré-prompt não aparece naquele tipo de aparelho — independentemente do que estiver configurado na aba dele.', 'encontra-push' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="nav-tab-wrapper" style="margin-top: 18px;">
					<a href="#" class="nav-tab nav-tab-active" data-ep-device-tab="desktop">
						<?php esc_html_e( 'Desktop', 'encontra-push' ); ?>
					</a>
					<a href="#" class="nav-tab" data-ep-device-tab="mobile">
						<?php esc_html_e( 'Celular', 'encontra-push' ); ?>
					</a>
				</h2>

				<div data-ep-device-panel="desktop">
					<?php
					$ep_prefix = 'prompt';
					$ep_id     = 'desktop';
					$ep_p      = $prompt;
					require ENCONTRA_PUSH_DIR . 'admin/views/partial-prompt-device.php';
					?>
				</div>

				<div data-ep-device-panel="mobile" hidden>
					<?php
					$ep_prefix = 'prompt[mobile_settings]';
					$ep_id     = 'mobile';
					$ep_p      = $ep_mobile;
					require ENCONTRA_PUSH_DIR . 'admin/views/partial-prompt-device.php';
					?>
				</div>

				<?php submit_button( __( 'Salvar configuração', 'encontra-push' ) ); ?>
			</div>

			<div>
				<h2><?php esc_html_e( 'Prévia', 'encontra-push' ); ?></h2>

				<div class="ep-preview">
					<div class="ep-notif">
						<span aria-hidden="true" style="font-size: 22px;">&#128276;</span>
						<div>
							<p class="ep-notif__title" data-preview-title><?php echo esc_html( $prompt['title'] ); ?></p>
							<p class="ep-notif__body" data-preview-body><?php echo esc_html( $prompt['body'] ); ?></p>
							<p style="margin: 12px 0 0; text-align: right;">
								<span class="button" data-preview-decline><?php echo esc_html( $prompt['decline_label'] ); ?></span>
								<span class="button button-primary" data-preview-accept><?php echo esc_html( $prompt['accept_label'] ); ?></span>
							</p>
						</div>
					</div>

					<p class="ep-preview__note">
						<?php esc_html_e( 'A prévia acompanha a aba aberta. Ela mostra o pré-prompt do site, não a notificação do sistema — essa é desenhada pelo sistema operacional.', 'encontra-push' ); ?>
					</p>
				</div>
			</div>
		</div>
	</form>
</div>
