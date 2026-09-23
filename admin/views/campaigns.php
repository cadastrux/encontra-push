<?php
/**
 * Ultimas campanhas do site.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 * @var array                  $result
 */

defined( 'ABSPATH' ) || exit;

$campaigns = $result['data']['campaigns'] ?? array();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Campanhas', 'encontra-push' ); ?></h1>

	<?php if ( ! $settings->is_connected() ) : ?>
		<?php require ENCONTRA_PUSH_DIR . 'admin/views/partial-not-connected.php'; ?>
		<?php return; ?>
	<?php endif; ?>

	<p>
		<?php esc_html_e( 'A criação e o agendamento de campanhas acontecem no painel central, que consolida todos os sites da rede.', 'encontra-push' ); ?>
	</p>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( $settings->panel_url() . '/campanhas' ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Criar campanha no painel', 'encontra-push' ); ?>
		</a>
	</p>

	<table class="wp-list-table widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Campanha', 'encontra-push' ); ?></th>
				<th><?php esc_html_e( 'Tipo', 'encontra-push' ); ?></th>
				<th><?php esc_html_e( 'Status', 'encontra-push' ); ?></th>
				<th><?php esc_html_e( 'Enviada', 'encontra-push' ); ?></th>
				<th><?php esc_html_e( 'Aceitos', 'encontra-push' ); ?></th>
				<th><?php esc_html_e( 'Recebidos*', 'encontra-push' ); ?></th>
				<th><?php esc_html_e( 'Cliques', 'encontra-push' ); ?></th>
				<th><?php esc_html_e( 'CTR', 'encontra-push' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $campaigns ) ) : ?>
				<tr>
					<td colspan="8"><?php esc_html_e( 'Nenhuma campanha ainda.', 'encontra-push' ); ?></td>
				</tr>
			<?php endif; ?>

			<?php foreach ( $campaigns as $campaign ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $campaign['name'] ); ?></strong>
						<div class="description"><?php echo esc_html( $campaign['title'] ); ?></div>
					</td>
					<td><?php echo esc_html( $campaign['type'] ); ?></td>
					<td><?php echo esc_html( $campaign['status_label'] ); ?></td>
					<td>
						<?php
						echo $campaign['sent_at']
							? esc_html( wp_date( 'd/m/Y H:i', strtotime( (string) $campaign['sent_at'] ) ) )
							: '—';
						?>
					</td>
					<td><?php echo esc_html( number_format_i18n( (int) $campaign['accepted'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $campaign['received'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $campaign['clicks'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) $campaign['ctr'], 2 ) ); ?>%</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description">
		<?php esc_html_e( '* "Recebidos" é uma métrica de melhor esforço: depende de o Service Worker conseguir registrar o evento. "Aceitos" significa que o Push Service aceitou a solicitação, o que não prova que o usuário viu a notificação.', 'encontra-push' ); ?>
	</p>
</div>
