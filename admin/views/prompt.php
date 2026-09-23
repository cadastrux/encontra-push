<?php
/**
 * Secao 55 — configuracao de inscricao, com previa ao vivo.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 * @var array                  $prompt
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Solicitação de permissão', 'encontra-push' ); ?></h1>

	<?php if ( ! $settings->is_connected() ) : ?>
		<?php require ENCONTRA_PUSH_DIR . 'admin/views/partial-not-connected.php'; ?>
		<?php return; ?>
	<?php endif; ?>

	<p class="description" style="max-width: 760px;">
		<?php esc_html_e( 'O prompt nativo do navegador só é aberto depois que o visitante clica no nosso pré-prompt. Pedir permissão automaticamente no carregamento e o caminho mais rápido para o navegador passar a bloquear o pedido em todo o domínio.', 'encontra-push' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'encontra_push_prompt' ); ?>
		<input type="hidden" name="action" value="encontra_push_prompt">

		<div class="ep-columns">
			<div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ep-mode"><?php esc_html_e( 'Quando exibir', 'encontra-push' ); ?></label></th>
						<td>
							<select id="ep-mode" name="prompt[mode]">
								<?php
								$modes = array(
									'delay'     => __( 'Após X segundos', 'encontra-push' ),
									'visits'    => __( 'Após X visitas', 'encontra-push' ),
									'pageviews' => __( 'Após X páginas na sessão', 'encontra-push' ),
									'selector'  => __( 'Ao clicar em um seletor CSS', 'encontra-push' ),
									'manual'    => __( 'Somente por botão manual', 'encontra-push' ),
									'disabled'  => __( 'Desativado', 'encontra-push' ),
								);

								foreach ( $modes as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $prompt['mode'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Gatilhos', 'encontra-push' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'Segundos', 'encontra-push' ); ?>
								<input type="number" min="0" max="600" name="prompt[delay_seconds]"
								       value="<?php echo esc_attr( $prompt['delay_seconds'] ); ?>" class="small-text">
							</label>

							<label style="margin-left: 12px;">
								<?php esc_html_e( 'Visitas', 'encontra-push' ); ?>
								<input type="number" min="1" max="50" name="prompt[visits]"
								       value="<?php echo esc_attr( $prompt['visits'] ); ?>" class="small-text">
							</label>

							<label style="margin-left: 12px;">
								<?php esc_html_e( 'Páginas', 'encontra-push' ); ?>
								<input type="number" min="1" max="50" name="prompt[pageviews]"
								       value="<?php echo esc_attr( $prompt['pageviews'] ); ?>" class="small-text">
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="ep-selector"><?php esc_html_e( 'Seletor CSS', 'encontra-push' ); ?></label></th>
						<td>
							<input type="text" id="ep-selector" class="regular-text" name="prompt[css_selector]"
							       value="<?php echo esc_attr( $prompt['css_selector'] ); ?>"
							       placeholder=".botao-notificacoes">
							<p class="description">
								<?php esc_html_e( 'No modo manual, use o atributo data-encontra-push-subscribe em qualquer botão do tema.', 'encontra-push' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Dispositivos', 'encontra-push' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="prompt[desktop]" value="1" <?php checked( ! empty( $prompt['desktop'] ) ); ?>>
								<?php esc_html_e( 'Desktop', 'encontra-push' ); ?>
							</label>

							<label style="margin-left: 12px;">
								<input type="checkbox" name="prompt[mobile]" value="1" <?php checked( ! empty( $prompt['mobile'] ) ); ?>>
								<?php esc_html_e( 'Mobile', 'encontra-push' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Reexibição', 'encontra-push' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'Após fechar (dias)', 'encontra-push' ); ?>
								<input type="number" min="1" max="365" name="prompt[redisplay_dismiss_days]"
								       value="<?php echo esc_attr( $prompt['redisplay_dismiss_days'] ); ?>" class="small-text">
							</label>

							<label style="margin-left: 12px;">
								<?php esc_html_e( 'Após "Agora não" (dias)', 'encontra-push' ); ?>
								<input type="number" min="1" max="365" name="prompt[redisplay_later_days]"
								       value="<?php echo esc_attr( $prompt['redisplay_later_days'] ); ?>" class="small-text">
							</label>

							<p class="description">
								<?php esc_html_e( 'Se o visitante bloquear a permissão no próprio navegador, o pré-prompt não volta a aparecer.', 'encontra-push' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="ep-prompt-title"><?php esc_html_e( 'Título', 'encontra-push' ); ?></label></th>
						<td>
							<input type="text" id="ep-prompt-title" class="regular-text" name="prompt[title]"
							       value="<?php echo esc_attr( $prompt['title'] ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="ep-prompt-body"><?php esc_html_e( 'Texto', 'encontra-push' ); ?></label></th>
						<td>
							<textarea id="ep-prompt-body" class="large-text" rows="3" name="prompt[body]"><?php echo esc_textarea( $prompt['body'] ); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Botões', 'encontra-push' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'Aceitar', 'encontra-push' ); ?>
								<input type="text" name="prompt[accept_label]" value="<?php echo esc_attr( $prompt['accept_label'] ); ?>">
							</label>

							<label style="margin-left: 12px;">
								<?php esc_html_e( 'Recusar', 'encontra-push' ); ?>
								<input type="text" name="prompt[decline_label]" value="<?php echo esc_attr( $prompt['decline_label'] ); ?>">
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="ep-position"><?php esc_html_e( 'Posição', 'encontra-push' ); ?></label></th>
						<td>
							<select id="ep-position" name="prompt[position]">
								<?php
								$positions = array(
									'top-center'    => __( 'Topo, centro', 'encontra-push' ),
									'top-left'      => __( 'Topo, esquerda', 'encontra-push' ),
									'top-right'     => __( 'Topo, direita', 'encontra-push' ),
									'bottom-center' => __( 'Rodapé, centro', 'encontra-push' ),
									'bottom-left'   => __( 'Rodapé, esquerda', 'encontra-push' ),
									'bottom-right'  => __( 'Rodapé, direita', 'encontra-push' ),
								);

								foreach ( $positions as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $prompt['position'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

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
								<span class="button"><?php echo esc_html( $prompt['decline_label'] ); ?></span>
								<span class="button button-primary"><?php echo esc_html( $prompt['accept_label'] ); ?></span>
							</p>
						</div>
					</div>

					<p class="ep-preview__note">
						<?php esc_html_e( 'A prévia mostra o pré-prompt do site, não a notificação do sistema. A notificação final é desenhada pelo sistema operacional.', 'encontra-push' ); ?>
					</p>
				</div>
			</div>
		</div>
	</form>
</div>
