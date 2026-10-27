<?php
/**
 * Regras de pre-prompt por URL: lista e formulario.
 *
 * A mesma tela faz as duas coisas. Com `ep_rule` na query ela abre o
 * formulario daquela regra (ou de uma nova); sem ele, mostra a lista.
 *
 * @package EncontraPush
 * @var array      $rules   Todas as regras gravadas.
 * @var int|null   $editing Indice em edicao, ou null para regra nova.
 * @var bool       $is_form Se a tela deve abrir o formulario.
 * @var array      $rule    Valores da regra em edicao.
 * @var string     $page    Slug desta pagina de menu.
 */

defined( 'ABSPATH' ) || exit;

$ep_o   = (array) ( $rule['overrides'] ?? array() );
$ep_url = admin_url( 'admin.php?page=' . $page );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Regras por URL', 'encontra-push' ); ?></h1>

	<?php if ( ! $is_form ) : ?>

		<p class="description" style="max-width: 760px;">
			<?php esc_html_e( 'A configuração em "Solicitação" vale para o site inteiro. Aqui você muda o convite em páginas específicas: uma editoria pode pedir mais cedo, uma página institucional pode não pedir nada, uma matéria pode ter texto próprio.', 'encontra-push' ); ?>
		</p>

		<p class="description" style="max-width: 760px;">
			<strong><?php esc_html_e( 'A primeira regra que casar vence.', 'encontra-push' ); ?></strong>
			<?php esc_html_e( 'Use as setas para colocar a mais específica acima da mais genérica. Campo deixado em branco na regra continua herdando do site — ela só troca o que você preencher.', 'encontra-push' ); ?>
		</p>

		<p>
			<a href="<?php echo esc_url( add_query_arg( 'ep_rule', 'new', $ep_url ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Adicionar regra', 'encontra-push' ); ?>
			</a>
		</p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 28%;"><?php esc_html_e( 'Padrão de URL', 'encontra-push' ); ?></th>
					<th><?php esc_html_e( 'O que muda', 'encontra-push' ); ?></th>
					<th style="width: 10%;"><?php esc_html_e( 'Situação', 'encontra-push' ); ?></th>
					<th style="width: 18%;"><?php esc_html_e( 'Ações', 'encontra-push' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( array() === $rules ) : ?>
				<tr>
					<td colspan="4">
						<?php esc_html_e( 'Nenhuma regra ainda. Todas as páginas usam a configuração do site.', 'encontra-push' ); ?>
					</td>
				</tr>
			<?php endif; ?>

			<?php foreach ( $rules as $ep_i => $ep_rule ) : ?>
				<tr>
					<td>
						<strong><code><?php echo esc_html( $ep_rule['pattern'] ?? '' ); ?></code></strong>
						<?php if ( ! empty( $ep_rule['label'] ) ) : ?>
							<br><span class="description"><?php echo esc_html( $ep_rule['label'] ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( Encontra_Push_Url_Rules::summary( $ep_rule ) ); ?></td>
					<td>
						<?php if ( empty( $ep_rule['enabled'] ) ) : ?>
							<span class="description"><?php esc_html_e( 'Pausada', 'encontra-push' ); ?></span>
						<?php else : ?>
							<?php esc_html_e( 'Ativa', 'encontra-push' ); ?>
						<?php endif; ?>
					</td>
					<td>
						<a href="<?php echo esc_url( add_query_arg( 'ep_rule', $ep_i, $ep_url ) ); ?>">
							<?php esc_html_e( 'Editar', 'encontra-push' ); ?>
						</a>

						<?php
						/*
						 * Mover e excluir vao por POST com nonce, e nao por
						 * link: num GET, bastaria induzir o administrador a
						 * abrir uma URL para apagar a regra (SEC-023).
						 *
						 * Cada item: direcao, rotulo, e se o botao fica
						 * desabilitado (a primeira nao sobe, a ultima nao desce).
						 */
						$ep_movimentos = array(
							array( -1, '↑', 0 === $ep_i ),
							array( 1, '↓', $ep_i === count( $rules ) - 1 ),
						);
						?>

						<?php foreach ( $ep_movimentos as $ep_mv ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'encontra_push_url_rule_move' ); ?>
								<input type="hidden" name="action" value="encontra_push_url_rule_move">
								<input type="hidden" name="index" value="<?php echo esc_attr( (string) $ep_i ); ?>">
								<input type="hidden" name="direction" value="<?php echo esc_attr( (string) $ep_mv[0] ); ?>">
								<button type="submit" class="button-link" <?php disabled( $ep_mv[2] ); ?>>
									<?php echo esc_html( $ep_mv[1] ); ?>
								</button>
							</form>
						<?php endforeach; ?>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<?php wp_nonce_field( 'encontra_push_url_rule_delete' ); ?>
							<input type="hidden" name="action" value="encontra_push_url_rule_delete">
							<input type="hidden" name="index" value="<?php echo esc_attr( (string) $ep_i ); ?>">
							<button type="submit" class="button-link delete"
							        onclick="return confirm('<?php echo esc_js( __( 'Excluir esta regra?', 'encontra-push' ) ); ?>');">
								<?php esc_html_e( 'Excluir', 'encontra-push' ); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

	<?php else : ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'encontra_push_url_rule_save' ); ?>
			<input type="hidden" name="action" value="encontra_push_url_rule_save">
			<?php if ( null !== $editing ) : ?>
				<input type="hidden" name="index" value="<?php echo esc_attr( (string) $editing ); ?>">
			<?php endif; ?>

			<h2><?php echo null === $editing ? esc_html__( 'Nova regra', 'encontra-push' ) : esc_html__( 'Editar regra', 'encontra-push' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ep-rule-pattern"><?php esc_html_e( 'Padrão de URL', 'encontra-push' ); ?></label></th>
					<td>
						<input type="text" id="ep-rule-pattern" class="regular-text code" name="rule[pattern]"
						       value="<?php echo esc_attr( $rule['pattern'] ?? '' ); ?>"
						       placeholder="/esportes*" required>
						<p class="description">
							<?php esc_html_e( 'Caminho da página, sem o domínio. O asterisco vale por qualquer trecho.', 'encontra-push' ); ?>
						</p>
						<p class="description">
							<code>/</code> <?php esc_html_e( 'só a home', 'encontra-push' ); ?><br>
							<code>/esportes*</code> <?php esc_html_e( 'a editoria e tudo dentro dela', 'encontra-push' ); ?><br>
							<code>/esportes/*</code> <?php esc_html_e( 'só o que está dentro (a barra é literal, então /esportes fica de fora)', 'encontra-push' ); ?><br>
							<code>/2026/*/eleicoes*</code> <?php esc_html_e( 'o asterisco também vale no meio', 'encontra-push' ); ?><br>
							<code>*</code> <?php esc_html_e( 'todas as páginas', 'encontra-push' ); ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'A barra final não importa: /sobre e /sobre/ são a mesma página. A query string é ignorada, então um ?utm_source não faz a página escapar da regra.', 'encontra-push' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ep-rule-label"><?php esc_html_e( 'Apelido', 'encontra-push' ); ?></label></th>
					<td>
						<input type="text" id="ep-rule-label" class="regular-text" name="rule[label]"
						       value="<?php echo esc_attr( $rule['label'] ?? '' ); ?>"
						       placeholder="<?php esc_attr_e( 'Editoria de esportes', 'encontra-push' ); ?>">
						<p class="description"><?php esc_html_e( 'Só para você se achar na lista. Não aparece para o visitante.', 'encontra-push' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Situação', 'encontra-push' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="rule[enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>>
							<?php esc_html_e( 'Regra ativa', 'encontra-push' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Desmarcar pausa a regra sem apagá-la.', 'encontra-push' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'O que esta regra sobrescreve', 'encontra-push' ); ?></h2>

			<p class="description" style="max-width: 760px;">
				<?php esc_html_e( 'Tudo que ficar em "Herdar do site" ou em branco continua vindo da configuração geral. Preencha só o que precisa mudar nestas páginas.', 'encontra-push' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ep-rule-mode"><?php esc_html_e( 'Quando exibir', 'encontra-push' ); ?></label></th>
					<td>
						<select id="ep-rule-mode" name="rule[overrides][mode]">
							<option value=""><?php esc_html_e( '— Herdar do site —', 'encontra-push' ); ?></option>
							<?php
							$ep_modos = array(
								'delay'     => __( 'Após X segundos', 'encontra-push' ),
								'scroll'    => __( 'Ao rolar X% da página', 'encontra-push' ),
								'visits'    => __( 'Após X visitas', 'encontra-push' ),
								'pageviews' => __( 'Após X páginas na sessão', 'encontra-push' ),
								'selector'  => __( 'Ao clicar em um seletor CSS', 'encontra-push' ),
								'manual'    => __( 'Somente por botão manual', 'encontra-push' ),
								'disabled'  => __( 'Não pedir nada nesta página', 'encontra-push' ),
							);

							foreach ( $ep_modos as $ep_v => $ep_l ) :
								?>
								<option value="<?php echo esc_attr( $ep_v ); ?>" <?php selected( $ep_o['mode'] ?? '', $ep_v ); ?>>
									<?php echo esc_html( $ep_l ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Gatilhos', 'encontra-push' ); ?></th>
					<td>
						<?php
						$ep_numeros = array(
							'delay_seconds'  => array( __( 'Segundos', 'encontra-push' ), 0, 600 ),
							'scroll_percent' => array( __( 'Rolagem (%)', 'encontra-push' ), 1, 100 ),
							'visits'         => array( __( 'Visitas', 'encontra-push' ), 1, 50 ),
							'pageviews'      => array( __( 'Páginas', 'encontra-push' ), 1, 50 ),
						);

						foreach ( $ep_numeros as $ep_k => $ep_n ) :
							?>
							<label style="margin-right: 12px;">
								<?php echo esc_html( $ep_n[0] ); ?>
								<input type="number" class="small-text"
								       min="<?php echo esc_attr( (string) $ep_n[1] ); ?>"
								       max="<?php echo esc_attr( (string) $ep_n[2] ); ?>"
								       name="rule[overrides][<?php echo esc_attr( $ep_k ); ?>]"
								       value="<?php echo esc_attr( isset( $ep_o[ $ep_k ] ) ? (string) $ep_o[ $ep_k ] : '' ); ?>"
								       placeholder="—">
							</label>
						<?php endforeach; ?>
						<p class="description">
							<?php esc_html_e( 'Em branco herda do site. Só o número do modo escolhido acima tem efeito.', 'encontra-push' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ep-rule-style"><?php esc_html_e( 'Como pedir', 'encontra-push' ); ?></label></th>
					<td>
						<select id="ep-rule-style" name="rule[overrides][style]">
							<option value=""><?php esc_html_e( '— Herdar do site —', 'encontra-push' ); ?></option>
							<option value="custom" <?php selected( $ep_o['style'] ?? '', 'custom' ); ?>>
								<?php esc_html_e( 'Pré-prompt do site, depois o navegador', 'encontra-push' ); ?>
							</option>
							<option value="native" <?php selected( $ep_o['style'] ?? '', 'native' ); ?>>
								<?php esc_html_e( 'Direto o pedido do navegador', 'encontra-push' ); ?>
							</option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ep-rule-position"><?php esc_html_e( 'Posição na tela', 'encontra-push' ); ?></label></th>
					<td>
						<select id="ep-rule-position" name="rule[overrides][position]">
							<option value=""><?php esc_html_e( '— Herdar do site —', 'encontra-push' ); ?></option>
							<?php
							$ep_posicoes = array(
								'top-center'    => __( 'Topo, centro', 'encontra-push' ),
								'top-left'      => __( 'Topo, esquerda', 'encontra-push' ),
								'top-right'     => __( 'Topo, direita', 'encontra-push' ),
								'bottom-center' => __( 'Rodapé, centro', 'encontra-push' ),
								'bottom-left'   => __( 'Rodapé, esquerda', 'encontra-push' ),
								'bottom-right'  => __( 'Rodapé, direita', 'encontra-push' ),
							);

							foreach ( $ep_posicoes as $ep_v => $ep_l ) :
								?>
								<option value="<?php echo esc_attr( $ep_v ); ?>" <?php selected( $ep_o['position'] ?? '', $ep_v ); ?>>
									<?php echo esc_html( $ep_l ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ep-rule-title"><?php esc_html_e( 'Título', 'encontra-push' ); ?></label></th>
					<td>
						<input type="text" id="ep-rule-title" class="large-text" name="rule[overrides][title]"
						       value="<?php echo esc_attr( $ep_o['title'] ?? '' ); ?>"
						       placeholder="<?php esc_attr_e( 'Em branco herda o título do site', 'encontra-push' ); ?>">
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ep-rule-body"><?php esc_html_e( 'Texto', 'encontra-push' ); ?></label></th>
					<td>
						<textarea id="ep-rule-body" class="large-text" rows="3" name="rule[overrides][body]"
						          placeholder="<?php esc_attr_e( 'Em branco herda o texto do site', 'encontra-push' ); ?>"><?php echo esc_textarea( $ep_o['body'] ?? '' ); ?></textarea>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Botões', 'encontra-push' ); ?></th>
					<td>
						<label style="margin-right: 12px;">
							<?php esc_html_e( 'Aceitar', 'encontra-push' ); ?>
							<input type="text" class="regular-text" name="rule[overrides][accept_label]"
							       value="<?php echo esc_attr( $ep_o['accept_label'] ?? '' ); ?>" placeholder="—">
						</label>

						<label>
							<?php esc_html_e( 'Recusar', 'encontra-push' ); ?>
							<input type="text" class="regular-text" name="rule[overrides][decline_label]"
							       value="<?php echo esc_attr( $ep_o['decline_label'] ?? '' ); ?>" placeholder="—">
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ep-rule-selector"><?php esc_html_e( 'Seletor CSS', 'encontra-push' ); ?></label></th>
					<td>
						<input type="text" id="ep-rule-selector" class="regular-text" name="rule[overrides][css_selector]"
						       value="<?php echo esc_attr( $ep_o['css_selector'] ?? '' ); ?>"
						       placeholder=".botao-notificacoes">
						<p class="description"><?php esc_html_e( 'Usado quando o modo acima for "Ao clicar em um seletor CSS".', 'encontra-push' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php submit_button( __( 'Salvar regra', 'encontra-push' ), 'primary', 'submit', false ); ?>
				<a href="<?php echo esc_url( $ep_url ); ?>" class="button"><?php esc_html_e( 'Cancelar', 'encontra-push' ); ?></a>
			</p>
		</form>

	<?php endif; ?>
</div>
