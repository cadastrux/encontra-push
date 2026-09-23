<?php
/**
 * AutoPush: o que o site controla e o que o painel controla.
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings $settings
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'AutoPush', 'encontra-push' ); ?></h1>

	<?php if ( ! $settings->is_connected() ) : ?>
		<?php require ENCONTRA_PUSH_DIR . 'admin/views/partial-not-connected.php'; ?>
		<?php return; ?>
	<?php endif; ?>

	<div class="card" style="max-width: 760px;">
		<h2><?php esc_html_e( 'Como funciona neste site', 'encontra-push' ); ?></h2>

		<p>
			<?php esc_html_e( 'Quando um post passa para o status "publicado", o plugin envia o evento ao painel. Lá, as regras de AutoPush decidem se aquele conteúdo vira notificação, em qual horário e para qual público.', 'encontra-push' ); ?>
		</p>

		<h3><?php esc_html_e( 'O que NÃO dispara notificação', 'encontra-push' ); ?></h3>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><?php esc_html_e( 'Salvamento automático e revisão.', 'encontra-push' ); ?></li>
			<li><?php esc_html_e( 'Edição de um post que já estava publicado.', 'encontra-push' ); ?></li>
			<li><?php esc_html_e( 'Edição rápida na listagem de posts.', 'encontra-push' ); ?></li>
			<li><?php esc_html_e( 'Atualização via REST API.', 'encontra-push' ); ?></li>
			<li><?php esc_html_e( 'Post publicado com data retroativa de mais de um dia (importações).', 'encontra-push' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'Controle por post', 'encontra-push' ); ?></h3>
		<p>
			<?php esc_html_e( 'O editor de cada post tem um painel lateral "Encontra Push", onde é possível forçar o envio, vetar aquele post específico ou personalizar título, texto, imagem e horário.', 'encontra-push' ); ?>
		</p>

		<h3><?php esc_html_e( 'Sem envio duplicado', 'encontra-push' ); ?></h3>
		<p>
			<?php esc_html_e( 'Cada publicação carrega uma chave de idempotência formada por site, post e revisão de publicação. Se o WordPress repetir o evento, o painel reconhece e não cria uma segunda campanha.', 'encontra-push' ); ?>
		</p>

		<p>
			<a class="button button-primary" href="<?php echo esc_url( $settings->panel_url() . '/autopush' ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Configurar regras no painel', 'encontra-push' ); ?>
			</a>
		</p>
	</div>
</div>
