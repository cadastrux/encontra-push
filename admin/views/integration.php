<?php
/**
 * Secoes 12 (pairing), 16 e 17 (Service Worker), 102 (desconexao).
 *
 * @package EncontraPush
 * @var Encontra_Push_Settings       $settings
 * @var Encontra_Push_Service_Worker $service_worker
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Integração', 'encontra-push' ); ?></h1>

	<div class="ep-columns">
		<div class="card" style="max-width: none;">
			<h2><?php esc_html_e( 'Conexão com o painel', 'encontra-push' ); ?></h2>

			<?php if ( $settings->is_connected() ) : ?>
				<p>
					<span class="ep-badge ep-badge--ok"><?php esc_html_e( 'Conectado', 'encontra-push' ); ?></span>
				</p>

				<table class="widefat striped">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Painel', 'encontra-push' ); ?></td>
							<td><code><?php echo esc_html( $settings->panel_url() ); ?></code></td>
						</tr>
						<?php if ( $settings->api_url() !== $settings->panel_url() ) : ?>
							<tr>
								<td><?php esc_html_e( 'API', 'encontra-push' ); ?></td>
								<td>
									<code><?php echo esc_html( $settings->api_url() ); ?></code>
									<p class="description" style="margin: 4px 0 0;">
										<?php esc_html_e( 'O processamento foi separado do painel. Este endereço é definido pelo painel e atualizado automaticamente.', 'encontra-push' ); ?>
									</p>
								</td>
							</tr>
						<?php endif; ?>
						<tr>
							<td><?php esc_html_e( 'ID do site', 'encontra-push' ); ?></td>
							<td><code><?php echo esc_html( $settings->site_id() ); ?></code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'ID da credencial', 'encontra-push' ); ?></td>
							<td><code><?php echo esc_html( $settings->api_key_id() ); ?></code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Chave pública VAPID', 'encontra-push' ); ?></td>
							<td>
								<code style="word-break: break-all;">
									<?php echo esc_html( substr( $settings->vapid_public_key(), 0, 24 ) ); ?>…
								</code>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="description">
					<?php esc_html_e( 'O segredo da credencial nunca é exibido aqui: ele foi mostrado uma única vez no momento do pareamento e fica gravado de forma cifrada. Para trocar, rotacione a credencial no painel e reconecte este site.', 'encontra-push' ); ?>
				</p>

				<p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
						<?php wp_nonce_field( 'encontra_push_verify' ); ?>
						<input type="hidden" name="action" value="encontra_push_verify">
						<button type="submit" class="button"><?php esc_html_e( 'Validar domínio', 'encontra-push' ); ?></button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;"
					      onsubmit="return confirm('<?php echo esc_js( __( 'Desconectar este site do painel? Os assinantes continuam lá, mas o site deixa de capturar novos e de enviar eventos.', 'encontra-push' ) ); ?>');">
						<?php wp_nonce_field( 'encontra_push_disconnect' ); ?>
						<input type="hidden" name="action" value="encontra_push_disconnect">
						<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Desconectar', 'encontra-push' ); ?></button>
					</form>
				</p>
			<?php else : ?>
				<p>
					<?php esc_html_e( 'Gere um código de conexão no painel, em Sites > (seu site) > Integração, e informe-o abaixo. O código vale por 15 minutos e serve uma única vez.', 'encontra-push' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'encontra_push_connect' ); ?>
					<input type="hidden" name="action" value="encontra_push_connect">

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="ep-code"><?php esc_html_e( 'Código de conexão', 'encontra-push' ); ?></label></th>
							<td>
								<input type="text" id="ep-code" name="pairing_code" class="regular-text"
								       placeholder="EP-XXXX-XXXX" autocomplete="off" required
								       style="font-family: monospace; font-size: 16px; letter-spacing: 0.06em;">
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="ep-panel"><?php esc_html_e( 'URL do painel', 'encontra-push' ); ?></label></th>
							<td>
								<input type="url" id="ep-panel" name="panel_url" class="regular-text"
								       value="<?php echo esc_attr( $settings->panel_url() ); ?>">
								<p class="description">
									<?php esc_html_e( 'Altere apenas se estiver conectando a um ambiente de homologação.', 'encontra-push' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Conectar ao painel', 'encontra-push' ) ); ?>
				</form>
			<?php endif; ?>
		</div>

		<div class="card" style="max-width: none;">
			<h2><?php esc_html_e( 'Service Worker', 'encontra-push' ); ?></h2>

			<table class="widefat striped">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'URL', 'encontra-push' ); ?></td>
						<td><code><?php echo esc_html( $service_worker->url() ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Scope', 'encontra-push' ); ?></td>
						<td><code>/</code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Modo', 'encontra-push' ); ?></td>
						<td>
							<?php
							$labels = array(
								'exclusive'   => __( 'Modo A — exclusivo', 'encontra-push' ),
								'integration' => __( 'Modo B — integração com o Service Worker do site', 'encontra-push' ),
								'custom'      => __( 'Modo C — listeners adicionados manualmente', 'encontra-push' ),
							);

							echo esc_html( $labels[ $settings->integration_mode() ] ?? '' );
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="description">
				<?php esc_html_e( 'O modo é definido no painel. Se o site já usa PWA, Workbox, Firebase ou outro serviço de push, escolha o modo B ou C: o plugin nunca substitui o Service Worker existente.', 'encontra-push' ); ?>
			</p>

			<?php if ( 'exclusive' !== $settings->integration_mode() ) : ?>
				<h3><?php esc_html_e( 'Inclua no Service Worker do site', 'encontra-push' ); ?></h3>
				<code class="ep-code"><?php echo esc_html( $service_worker->integration_snippet() ); ?></code>
				<p class="description">
					<?php esc_html_e( 'O runtime ignora mensagens que não sejam do Encontra Push, então convive com outros remetentes no mesmo Service Worker.', 'encontra-push' ); ?>
				</p>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Cache', 'encontra-push' ); ?></h3>
			<p>
				<?php esc_html_e( 'Excluir das regras de cache e minificação (LiteSpeed Cache, Cloudflare, WP Rocket, Autoptimize):', 'encontra-push' ); ?>
			</p>
			<code class="ep-code">/<?php echo esc_html( ENCONTRA_PUSH_SW_PATH ); ?></code>
		</div>
	</div>

	<?php
	/*
	 * Secao 102 — o que acontece ao remover o plugin.
	 *
	 * A escolha e feita ANTES da desinstalacao porque o WordPress nao exibe
	 * nenhuma tela durante a exclusao do plugin: quando uninstall.php roda,
	 * nao ha mais interface para perguntar nada.
	 */
	$uninstall = (array) get_option( 'encontra_push_uninstall_options', array() );
	?>
	<div class="card" style="max-width: none;">
		<h2><?php esc_html_e( 'Ao remover este plugin', 'encontra-push' ); ?></h2>

		<p>
			<?php esc_html_e( 'Por padrão, remover o plugin não apaga nada: os assinantes pertencem ao painel e continuam lá, com o consentimento que deram. Marque abaixo apenas o que você realmente quer que seja excluído.', 'encontra-push' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'encontra_push_uninstall_options' ); ?>
			<input type="hidden" name="action" value="encontra_push_uninstall_options">

			<p>
				<label>
					<input type="checkbox" name="uninstall[revoke_connection]" value="1"
					       <?php checked( ! empty( $uninstall['revoke_connection'] ) ); ?>>
					<?php esc_html_e( 'Revogar a conexão com o Encontra Push', 'encontra-push' ); ?>
				</label>
			</p>

			<p>
				<label>
					<input type="checkbox" name="uninstall[remove_local]" value="1"
					       <?php checked( ! empty( $uninstall['remove_local'] ) ); ?>>
					<?php esc_html_e( 'Remover as configurações locais deste site', 'encontra-push' ); ?>
				</label>
			</p>

			<p class="description">
				<?php esc_html_e( 'A base de assinantes no painel não é afetada por nenhuma das duas opções. Para excluir os assinantes, use a exclusão do site no próprio painel.', 'encontra-push' ); ?>
			</p>

			<?php submit_button( __( 'Salvar preferência', 'encontra-push' ), 'secondary' ); ?>
		</form>
	</div>
</div>
