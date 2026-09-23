<?php
/**
 * Resumo agregado dos assinantes.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 * @var array                  $result
 */

defined( 'ABSPATH' ) || exit;

$data = $result['data'] ?? array();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Assinantes', 'encontra-push' ); ?></h1>

	<?php if ( ! $settings->is_connected() ) : ?>
		<?php require ENCONTRA_PUSH_DIR . 'admin/views/partial-not-connected.php'; ?>
		<?php return; ?>
	<?php endif; ?>

	<p>
		<?php esc_html_e( 'O wp-admin mostra apenas números agregados. Os dados técnicos da subscription (endpoint e chaves) são credenciais e ficam no painel, criptografados.', 'encontra-push' ); ?>
	</p>

	<div class="ep-grid">
		<div class="ep-stat">
			<div class="ep-stat__label"><?php esc_html_e( 'Ativos', 'encontra-push' ); ?></div>
			<div class="ep-stat__value">
				<?php echo esc_html( number_format_i18n( (int) ( $data['total_active'] ?? 0 ) ) ); ?>
			</div>
		</div>

		<?php foreach ( (array) ( $data['by_status'] ?? array() ) as $status => $total ) : ?>
			<div class="ep-stat">
				<div class="ep-stat__label"><?php echo esc_html( $status ); ?></div>
				<div class="ep-stat__value"><?php echo esc_html( number_format_i18n( (int) $total ) ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="ep-columns">
		<div class="card">
			<h2><?php esc_html_e( 'Por navegador', 'encontra-push' ); ?></h2>
			<table class="wp-list-table widefat striped">
				<tbody>
					<?php foreach ( (array) ( $data['by_browser'] ?? array() ) as $browser => $total ) : ?>
						<tr>
							<td><?php echo esc_html( $browser ); ?></td>
							<td style="text-align: right;"><?php echo esc_html( number_format_i18n( (int) $total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="card">
			<h2><?php esc_html_e( 'Por dispositivo', 'encontra-push' ); ?></h2>
			<table class="wp-list-table widefat striped">
				<tbody>
					<?php foreach ( (array) ( $data['by_device'] ?? array() ) as $device => $total ) : ?>
						<tr>
							<td><?php echo esc_html( $device ); ?></td>
							<td style="text-align: right;"><?php echo esc_html( number_format_i18n( (int) $total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<p>
		<a class="button" href="<?php echo esc_url( $settings->panel_url() . '/assinantes' ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Ver detalhes no painel', 'encontra-push' ); ?>
		</a>
	</p>
</div>
