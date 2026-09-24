<?php
/**
 * Campos do pre-prompt de UM aparelho.
 *
 * Incluido duas vezes — desktop e celular — porque os dois conjuntos sao
 * independentes. Um parcial evita que eles se separem com o tempo: um campo
 * novo entra aqui e aparece nos dois de uma vez.
 *
 * @package EncontraPush
 * @var string $ep_prefix Prefixo do name: "prompt" ou "prompt[mobile_settings]".
 * @var string $ep_id     Sufixo dos ids, para os rotulos nao colidirem.
 * @var array  $ep_p      Valores deste aparelho.
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row">
			<label for="ep-mode-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Quando exibir', 'encontra-push' ); ?></label>
		</th>
		<td>
			<select id="ep-mode-<?php echo esc_attr( $ep_id ); ?>" name="<?php echo esc_attr( $ep_prefix ); ?>[mode]">
				<?php
				$ep_modes = array(
					'delay'     => __( 'Após X segundos', 'encontra-push' ),
					'visits'    => __( 'Após X visitas', 'encontra-push' ),
					'pageviews' => __( 'Após X páginas na sessão', 'encontra-push' ),
					'selector'  => __( 'Ao clicar em um seletor CSS', 'encontra-push' ),
					'manual'    => __( 'Somente por botão manual', 'encontra-push' ),
					'disabled'  => __( 'Desativado', 'encontra-push' ),
				);

				foreach ( $ep_modes as $ep_value => $ep_label ) :
					?>
					<option value="<?php echo esc_attr( $ep_value ); ?>" <?php selected( $ep_p['mode'] ?? 'delay', $ep_value ); ?>>
						<?php echo esc_html( $ep_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="ep-style-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Como pedir a permissão', 'encontra-push' ); ?></label>
		</th>
		<td>
			<select id="ep-style-<?php echo esc_attr( $ep_id ); ?>" name="<?php echo esc_attr( $ep_prefix ); ?>[style]">
				<option value="custom" <?php selected( $ep_p['style'] ?? 'custom', 'custom' ); ?>>
					<?php esc_html_e( 'Pré-prompt do site, depois o navegador', 'encontra-push' ); ?>
				</option>
				<option value="native" <?php selected( $ep_p['style'] ?? 'custom', 'native' ); ?>>
					<?php esc_html_e( 'Direto o pedido do navegador', 'encontra-push' ); ?>
				</option>
			</select>
			<p class="description">
				<?php esc_html_e( 'Com o pré-prompt, o site pergunta antes e só chama o navegador depois do clique — quem recusa pode ser perguntado outra vez. Direto converte mais, mas quem recusar bloqueia o domínio e não há como pedir de novo. No Firefox e no Safari o pedido direto não abre sem um clique, então nesses navegadores o pré-prompt aparece do mesmo jeito.', 'encontra-push' ); ?>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Gatilhos', 'encontra-push' ); ?></th>
		<td>
			<label>
				<?php esc_html_e( 'Segundos', 'encontra-push' ); ?>
				<input type="number" min="0" max="600" name="<?php echo esc_attr( $ep_prefix ); ?>[delay_seconds]"
				       value="<?php echo esc_attr( $ep_p['delay_seconds'] ?? 8 ); ?>" class="small-text">
			</label>

			<label style="margin-left: 12px;">
				<?php esc_html_e( 'Visitas', 'encontra-push' ); ?>
				<input type="number" min="1" max="50" name="<?php echo esc_attr( $ep_prefix ); ?>[visits]"
				       value="<?php echo esc_attr( $ep_p['visits'] ?? 2 ); ?>" class="small-text">
			</label>

			<label style="margin-left: 12px;">
				<?php esc_html_e( 'Páginas', 'encontra-push' ); ?>
				<input type="number" min="1" max="50" name="<?php echo esc_attr( $ep_prefix ); ?>[pageviews]"
				       value="<?php echo esc_attr( $ep_p['pageviews'] ?? 2 ); ?>" class="small-text">
			</label>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="ep-selector-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Seletor CSS', 'encontra-push' ); ?></label>
		</th>
		<td>
			<input type="text" id="ep-selector-<?php echo esc_attr( $ep_id ); ?>" class="regular-text"
			       name="<?php echo esc_attr( $ep_prefix ); ?>[css_selector]"
			       value="<?php echo esc_attr( $ep_p['css_selector'] ?? '' ); ?>"
			       placeholder=".botao-notificacoes">
			<p class="description">
				<?php esc_html_e( 'No modo manual, use o atributo data-encontra-push-subscribe em qualquer botão do tema.', 'encontra-push' ); ?>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Reexibição', 'encontra-push' ); ?></th>
		<td>
			<label>
				<?php esc_html_e( 'Após fechar (dias)', 'encontra-push' ); ?>
				<input type="number" min="1" max="365" name="<?php echo esc_attr( $ep_prefix ); ?>[redisplay_dismiss_days]"
				       value="<?php echo esc_attr( $ep_p['redisplay_dismiss_days'] ?? 7 ); ?>" class="small-text">
			</label>

			<label style="margin-left: 12px;">
				<?php esc_html_e( 'Após "Agora não" (dias)', 'encontra-push' ); ?>
				<input type="number" min="1" max="365" name="<?php echo esc_attr( $ep_prefix ); ?>[redisplay_later_days]"
				       value="<?php echo esc_attr( $ep_p['redisplay_later_days'] ?? 30 ); ?>" class="small-text">
			</label>
			<p class="description">
				<?php esc_html_e( 'Se o visitante bloquear a permissão no próprio navegador, o pré-prompt não volta a aparecer.', 'encontra-push' ); ?>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="ep-title-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Título', 'encontra-push' ); ?></label>
		</th>
		<td>
			<input type="text" id="ep-title-<?php echo esc_attr( $ep_id ); ?>" class="regular-text"
			       data-ep-preview="title"
			       name="<?php echo esc_attr( $ep_prefix ); ?>[title]"
			       value="<?php echo esc_attr( $ep_p['title'] ?? '' ); ?>">
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="ep-body-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Texto', 'encontra-push' ); ?></label>
		</th>
		<td>
			<textarea id="ep-body-<?php echo esc_attr( $ep_id ); ?>" class="large-text" rows="3"
			          data-ep-preview="body"
			          name="<?php echo esc_attr( $ep_prefix ); ?>[body]"><?php echo esc_textarea( $ep_p['body'] ?? '' ); ?></textarea>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Botões', 'encontra-push' ); ?></th>
		<td>
			<label>
				<?php esc_html_e( 'Aceitar', 'encontra-push' ); ?>
				<input type="text" data-ep-preview="accept"
				       name="<?php echo esc_attr( $ep_prefix ); ?>[accept_label]"
				       value="<?php echo esc_attr( $ep_p['accept_label'] ?? '' ); ?>">
			</label>

			<label style="margin-left: 12px;">
				<?php esc_html_e( 'Recusar', 'encontra-push' ); ?>
				<input type="text" data-ep-preview="decline"
				       name="<?php echo esc_attr( $ep_prefix ); ?>[decline_label]"
				       value="<?php echo esc_attr( $ep_p['decline_label'] ?? '' ); ?>">
			</label>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="ep-position-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Posição', 'encontra-push' ); ?></label>
		</th>
		<td>
			<select id="ep-position-<?php echo esc_attr( $ep_id ); ?>" name="<?php echo esc_attr( $ep_prefix ); ?>[position]">
				<?php
				$ep_positions = array(
					'top-center'    => __( 'Topo, centro', 'encontra-push' ),
					'top-left'      => __( 'Topo, esquerda', 'encontra-push' ),
					'top-right'     => __( 'Topo, direita', 'encontra-push' ),
					'bottom-center' => __( 'Rodapé, centro', 'encontra-push' ),
					'bottom-left'   => __( 'Rodapé, esquerda', 'encontra-push' ),
					'bottom-right'  => __( 'Rodapé, direita', 'encontra-push' ),
				);

				foreach ( $ep_positions as $ep_value => $ep_label ) :
					?>
					<option value="<?php echo esc_attr( $ep_value ); ?>" <?php selected( $ep_p['position'] ?? 'top-center', $ep_value ); ?>>
						<?php echo esc_html( $ep_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
</table>
