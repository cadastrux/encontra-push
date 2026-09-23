<?php
/**
 * Secao 56 — aparencia.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 * @var array                  $appearance
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Aparência', 'encontra-push' ); ?></h1>

	<?php if ( ! $settings->is_connected() ) : ?>
		<?php require ENCONTRA_PUSH_DIR . 'admin/views/partial-not-connected.php'; ?>
		<?php return; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'encontra_push_appearance' ); ?>
		<input type="hidden" name="action" value="encontra_push_appearance">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ep-theme"><?php esc_html_e( 'Tema', 'encontra-push' ); ?></label></th>
				<td>
					<select id="ep-theme" name="appearance[theme]">
						<option value="auto" <?php selected( $appearance['theme'], 'auto' ); ?>><?php esc_html_e( 'Automático', 'encontra-push' ); ?></option>
						<option value="light" <?php selected( $appearance['theme'], 'light' ); ?>><?php esc_html_e( 'Claro', 'encontra-push' ); ?></option>
						<option value="dark" <?php selected( $appearance['theme'], 'dark' ); ?>><?php esc_html_e( 'Escuro', 'encontra-push' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'No modo automático, o pré-prompt acompanha a preferência do sistema do visitante.', 'encontra-push' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="ep-accent"><?php esc_html_e( 'Cor principal', 'encontra-push' ); ?></label></th>
				<td>
					<input type="color" id="ep-accent" name="appearance[accent]"
					       value="<?php echo esc_attr( $appearance['accent'] ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="ep-button"><?php esc_html_e( 'Cor do botão', 'encontra-push' ); ?></label></th>
				<td>
					<input type="color" id="ep-button" name="appearance[button_color]"
					       value="<?php echo esc_attr( $appearance['button_color'] ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="ep-radius"><?php esc_html_e( 'Arredondamento da borda', 'encontra-push' ); ?></label></th>
				<td>
					<input type="number" id="ep-radius" min="0" max="40" class="small-text"
					       name="appearance[radius]" value="<?php echo esc_attr( $appearance['radius'] ); ?>"> px
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="ep-appearance-position"><?php esc_html_e( 'Posição', 'encontra-push' ); ?></label></th>
				<td>
					<select id="ep-appearance-position" name="appearance[position]">
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
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $appearance['position'], $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<p class="description" style="max-width: 720px;">
			<?php esc_html_e( 'Esta tela aceita apenas cores e opções fechadas. CSS livre não é permitido aqui, nem para quem tem a permissão de HTML irrestrito: o pré-prompt roda em todas as páginas do site e um estilo arbitrário seria um vetor de injeção.', 'encontra-push' ); ?>
		</p>

		<?php submit_button( __( 'Salvar aparência', 'encontra-push' ) ); ?>
	</form>
</div>
