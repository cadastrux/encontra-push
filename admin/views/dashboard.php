<?php
/**
 * Secao 53 — dashboard do plugin.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 * @var Encontra_Push_Metrics  $metrics
 */

defined( 'ABSPATH' ) || exit;

$result = $metrics->metrics();
$cards  = $result['data']['cards'] ?? array();
$series = $result['data']['series'] ?? array();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Encontra Push', 'encontra-push' ); ?></h1>

	<?php if ( ! $settings->is_connected() ) : ?>
		<?php require ENCONTRA_PUSH_DIR . 'admin/views/partial-not-connected.php'; ?>
		<?php return; ?>
	<?php endif; ?>

	<?php if ( ! $result['ok'] ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html( $result['error'] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ep-grid">
		<div class="ep-stat">
			<div class="ep-stat__label"><?php esc_html_e( 'Status da conexão', 'encontra-push' ); ?></div>
			<div class="ep-stat__value" style="font-size: 18px;">
				<?php
				$status = (string) ( $cards['connection'] ?? 'desconhecido' );
				$class  = 'active' === $status ? 'ok' : ( 'error' === $status ? 'error' : 'warn' );
				?>
				<span class="ep-badge ep-badge--<?php echo esc_attr( $class ); ?>">
					<?php echo esc_html( $status ); ?>
				</span>
			</div>
		</div>

		<?php
		$stats = array(
			array( __( 'Assinantes ativos', 'encontra-push' ), $cards['active_subscribers'] ?? 0, '' ),
			array( __( 'Novos em 7 dias', 'encontra-push' ), $cards['new_7d'] ?? 0, '' ),
			array( __( 'Campanhas em 30 dias', 'encontra-push' ), $cards['campaigns_30d'] ?? 0, '' ),
			array( __( 'Push aceitos', 'encontra-push' ), $cards['accepted'] ?? 0, __( 'aceitos pelo Push Service', 'encontra-push' ) ),
			array( __( 'Cliques', 'encontra-push' ), $cards['clicks'] ?? 0, __( 'cliques únicos', 'encontra-push' ) ),
		);

		foreach ( $stats as $stat ) :
			?>
			<div class="ep-stat">
				<div class="ep-stat__label"><?php echo esc_html( $stat[0] ); ?></div>
				<div class="ep-stat__value"><?php echo esc_html( number_format_i18n( (int) $stat[1] ) ); ?></div>
				<?php if ( $stat[2] ) : ?>
					<div class="ep-stat__hint"><?php echo esc_html( $stat[2] ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<div class="ep-stat">
			<div class="ep-stat__label"><?php esc_html_e( 'CTR', 'encontra-push' ); ?></div>
			<div class="ep-stat__value">
				<?php echo esc_html( number_format_i18n( (float) ( $cards['ctr'] ?? 0 ), 2 ) ); ?>%
			</div>
			<div class="ep-stat__hint">
				<?php echo esc_html( $cards['ctr_formula'] ?? '' ); ?>
			</div>
		</div>
	</div>

	<p class="description">
		<?php
		printf(
			/* translators: %s: horario da ultima atualizacao */
			esc_html__( 'Dados em cache desde %s. As métricas do painel são consultadas no máximo a cada 5 minutos.', 'encontra-push' ),
			esc_html( wp_date( 'H:i', (int) $result['cached_at'] ) )
		);
		?>
	</p>

	<p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
			<?php wp_nonce_field( 'encontra_push_refresh' ); ?>
			<input type="hidden" name="action" value="encontra_push_refresh">
			<button type="submit" class="button"><?php esc_html_e( 'Atualizar agora', 'encontra-push' ); ?></button>
		</form>

		<a class="button button-primary" href="<?php echo esc_url( $settings->panel_url() ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Abrir painel completo', 'encontra-push' ); ?>
		</a>
	</p>

	<?php if ( ! empty( $series ) ) : ?>
		<h2><?php esc_html_e( 'Assinantes nos últimos 30 dias', 'encontra-push' ); ?></h2>

		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Data', 'encontra-push' ); ?></th>
					<th><?php esc_html_e( 'Novos', 'encontra-push' ); ?></th>
					<th><?php esc_html_e( 'Cancelados', 'encontra-push' ); ?></th>
					<th><?php esc_html_e( 'Aceitos', 'encontra-push' ); ?></th>
					<th><?php esc_html_e( 'Cliques', 'encontra-push' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_slice( array_reverse( $series ), 0, 14 ) as $row ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m', strtotime( (string) $row['date'] ) ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['new'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['unsubscribes'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['accepted'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['clicked'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
