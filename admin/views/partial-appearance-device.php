<?php
/**
 * Campos de aparencia de UM aparelho.
 *
 * Incluido duas vezes — desktop e celular. A posicao NAO esta aqui: ela vive
 * no pre-prompt (`prompt[position]`), que e de onde o JavaScript do site le.
 * Ter o mesmo campo nas duas telas so criaria a duvida de qual vale.
 *
 * @package EncontraPush
 * @var string $ep_prefix Prefixo do name: "appearance" ou "appearance[mobile_settings]".
 * @var string $ep_id     Sufixo dos ids, para os rotulos nao colidirem.
 * @var array  $ep_a      Valores deste aparelho.
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row">
			<label for="ep-theme-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Tema', 'encontra-push' ); ?></label>
		</th>
		<td>
			<select id="ep-theme-<?php echo esc_attr( $ep_id ); ?>" name="<?php echo esc_attr( $ep_prefix ); ?>[theme]">
				<option value="auto" <?php selected( $ep_a['theme'] ?? 'auto', 'auto' ); ?>><?php esc_html_e( 'Automático', 'encontra-push' ); ?></option>
				<option value="light" <?php selected( $ep_a['theme'] ?? 'auto', 'light' ); ?>><?php esc_html_e( 'Claro', 'encontra-push' ); ?></option>
				<option value="dark" <?php selected( $ep_a['theme'] ?? 'auto', 'dark' ); ?>><?php esc_html_e( 'Escuro', 'encontra-push' ); ?></option>
			</select>
			<p class="description">
				<?php esc_html_e( 'No modo automático, o pré-prompt acompanha a preferência do sistema do visitante.', 'encontra-push' ); ?>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="ep-accent-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Cor principal', 'encontra-push' ); ?></label>
		</th>
		<td>
			<input type="color" id="ep-accent-<?php echo esc_attr( $ep_id ); ?>"
			       name="<?php echo esc_attr( $ep_prefix ); ?>[accent]"
			       value="<?php echo esc_attr( $ep_a['accent'] ?? '#5b4bd6' ); ?>">
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="ep-button-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Cor do botão', 'encontra-push' ); ?></label>
		</th>
		<td>
			<input type="color" id="ep-button-<?php echo esc_attr( $ep_id ); ?>"
			       name="<?php echo esc_attr( $ep_prefix ); ?>[button_color]"
			       value="<?php echo esc_attr( $ep_a['button_color'] ?? $ep_a['accent'] ?? '#5b4bd6' ); ?>">
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="ep-radius-<?php echo esc_attr( $ep_id ); ?>"><?php esc_html_e( 'Arredondamento da borda', 'encontra-push' ); ?></label>
		</th>
		<td>
			<input type="number" id="ep-radius-<?php echo esc_attr( $ep_id ); ?>" min="0" max="40" class="small-text"
			       name="<?php echo esc_attr( $ep_prefix ); ?>[radius]"
			       value="<?php echo esc_attr( (int) ( $ep_a['radius'] ?? 12 ) ); ?>"> px
		</td>
	</tr>
</table>
