<?php
/**
 * Sino de noticias.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 * @var array                  $widget
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Sino de notícias', 'encontra-push' ); ?></h1>

	<?php if ( ! $settings->is_connected() ) : ?>
		<?php require ENCONTRA_PUSH_DIR . 'admin/views/partial-not-connected.php'; ?>
		<?php return; ?>
	<?php endif; ?>

	<p class="description" style="max-width: 720px;">
		<?php esc_html_e( 'Botão fixo com um sino na lateral do site. Para quem já está inscrito, ele mostra as últimas notícias publicadas, com contador de não lidas. Para quem ainda não está, mostra o convite para ativar as notificações. Cores e tema seguem a tela Aparência.', 'encontra-push' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'encontra_push_widget' ); ?>
		<input type="hidden" name="action" value="encontra_push_widget">

		<h2 class="title"><?php esc_html_e( 'Exibição', 'encontra-push' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Sino no site', 'encontra-push' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="widget[enabled]" value="1" <?php checked( $widget['enabled'] ); ?>>
						<?php esc_html_e( 'Mostrar o sino de notícias', 'encontra-push' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Dispositivos', 'encontra-push' ); ?></th>
				<td>
					<label style="margin-right: 16px;">
						<input type="checkbox" name="widget[desktop]" value="1" <?php checked( $widget['desktop'] ); ?>>
						<?php esc_html_e( 'Computador', 'encontra-push' ); ?>
					</label>
					<label>
						<input type="checkbox" name="widget[mobile]" value="1" <?php checked( $widget['mobile'] ); ?>>
						<?php esc_html_e( 'Celular e tablet', 'encontra-push' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="ep-widget-position"><?php esc_html_e( 'Lado da tela', 'encontra-push' ); ?></label></th>
				<td>
					<select id="ep-widget-position" name="widget[position]">
						<option value="right" <?php selected( $widget['position'], 'right' ); ?>><?php esc_html_e( 'Direita', 'encontra-push' ); ?></option>
						<option value="left" <?php selected( $widget['position'], 'left' ); ?>><?php esc_html_e( 'Esquerda', 'encontra-push' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="ep-widget-offset"><?php esc_html_e( 'Distância do rodapé', 'encontra-push' ); ?></label></th>
				<td>
					<input type="number" id="ep-widget-offset" class="small-text" min="0" max="200"
					       name="widget[offset]" value="<?php echo esc_attr( $widget['offset'] ); ?>"> px
					<p class="description">
						<?php esc_html_e( 'Aumente se o sino ficar por cima de outro botão fixo do site (WhatsApp, barra de cookies, voltar ao topo).', 'encontra-push' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'O sino fica atrás do banner de cookies de propósito. Se em algum tema ele ainda cobrir outra camada, acrescente ao CSS do site:', 'encontra-push' ); ?>
						<code>.ep-bell { --ep-bell-z: 500; }</code>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="ep-widget-count"><?php esc_html_e( 'Notícias na lista', 'encontra-push' ); ?></label></th>
				<td>
					<input type="number" id="ep-widget-count" class="small-text" min="1" max="10"
					       name="widget[count]" value="<?php echo esc_attr( $widget['count'] ); ?>">
					<p class="description"><?php esc_html_e( 'Os últimos posts publicados, de 1 a 10.', 'encontra-push' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Contador', 'encontra-push' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="widget[badge]" value="1" <?php checked( $widget['badge'] ); ?>>
						<?php esc_html_e( 'Mostrar no sino o número de notícias não lidas', 'encontra-push' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Amostra', 'encontra-push' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="widget[preview]" value="1" <?php checked( $widget['preview'] ); ?>>
						<?php esc_html_e( 'Mostrar as notícias também para quem ainda não ativou as notificações', 'encontra-push' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'O convite para ativar aparece logo abaixo da lista. Ver o que o site publica costuma convencer mais do que só pedir permissão. Desmarcado, quem não está inscrito vê apenas o convite.', 'encontra-push' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Card de destaque', 'encontra-push' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="widget[teaser]" value="1" <?php checked( $widget['teaser'] ); ?>>
						<?php esc_html_e( 'Ao abrir o site, mostrar a última notícia num card ao lado do sino', 'encontra-push' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Aparece uma vez por visita ao site e some sozinho. O visitante pode fechar no X, e o card não some enquanto o ponteiro estiver sobre ele.', 'encontra-push' ); ?>
					</p>

					<p style="margin-top: 10px;">
						<label for="ep-widget-teaser-seconds"><?php esc_html_e( 'Tempo na tela', 'encontra-push' ); ?></label>
						<input type="number" id="ep-widget-teaser-seconds" class="small-text" min="3" max="30"
						       name="widget[teaser_seconds]" value="<?php echo esc_attr( $widget['teaser_seconds'] ); ?>">
						<?php esc_html_e( 'segundos', 'encontra-push' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Textos', 'encontra-push' ); ?></h2>

		<table class="form-table" role="presentation">
			<?php
			$fields = array(
				'title'           => array( __( 'Título da lista (inscrito)', 'encontra-push' ), 60, false ),
				'subscribed_text' => array( __( 'Mensagem para quem já está inscrito', 'encontra-push' ), 160, false ),
				'subscribe_title' => array( __( 'Título do convite (não inscrito)', 'encontra-push' ), 90, false ),
				'subscribe_text'  => array( __( 'Texto do convite', 'encontra-push' ), 240, true ),
				'button_label'    => array( __( 'Botão do convite', 'encontra-push' ), 40, false ),
				'blocked_text'    => array( __( 'Mensagem com notificações bloqueadas', 'encontra-push' ), 240, true ),
			);

			foreach ( $fields as $key => $field ) :
				list( $label, $max, $multiline ) = $field;
				?>
				<tr>
					<th scope="row"><label for="ep-widget-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td>
						<?php if ( $multiline ) : ?>
							<textarea id="ep-widget-<?php echo esc_attr( $key ); ?>" class="large-text" rows="2"
							          maxlength="<?php echo esc_attr( $max ); ?>"
							          name="widget[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $widget[ $key ] ); ?></textarea>
						<?php else : ?>
							<input type="text" id="ep-widget-<?php echo esc_attr( $key ); ?>" class="regular-text"
							       maxlength="<?php echo esc_attr( $max ); ?>"
							       name="widget[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $widget[ $key ] ); ?>">
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<p class="description"><?php esc_html_e( 'Campo vazio volta ao texto padrão.', 'encontra-push' ); ?></p>

		<?php submit_button( __( 'Salvar sino', 'encontra-push' ) ); ?>
	</form>
</div>
