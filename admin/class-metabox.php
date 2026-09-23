<?php
/**
 * Metabox de AutoPush no editor de post.
 *
 * @package EncontraPush
 */

defined( 'ABSPATH' ) || exit;

/**
 * Secao 29.2 — opcao por post.
 *
 * Fica visivel ANTES da publicacao, porque e nesse momento que o editor
 * decide se aquele conteudo merece notificacao. Depois de publicado, mudar a
 * opcao nao dispara nada — a transicao para publish ja aconteceu.
 */
class Encontra_Push_Metabox {

	private const META = '_encontra_push_options';
	private const NONCE = 'encontra_push_metabox';

	public function __construct( private Encontra_Push_Settings $settings ) {}

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	public function add(): void {
		if ( ! $this->settings->is_connected() ) {
			return;
		}

		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			add_meta_box(
				'encontra-push',
				__( 'Encontra Push', 'encontra-push' ),
				array( $this, 'render' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	public function render( WP_Post $post ): void {
		$options = get_post_meta( $post->ID, self::META, true );
		$options = is_array( $options ) ? $options : array();
		$mode    = $options['mode'] ?? 'auto';
		$sent    = get_post_meta( $post->ID, '_encontra_push_sent', true );

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<?php if ( is_array( $sent ) ) : ?>
			<p class="<?php echo $sent['ok'] ? '' : 'ep-badge ep-badge--error'; ?>">
				<?php if ( $sent['ok'] ) : ?>
					<?php
					printf(
						/* translators: %s: data e hora do envio */
						esc_html__( 'Evento enviado ao painel em %s.', 'encontra-push' ),
						esc_html( wp_date( 'd/m/Y H:i', (int) $sent['at'] ) )
					);
					?>
				<?php else : ?>
					<?php echo esc_html( $sent['message'] ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<p>
			<label>
				<input type="radio" name="encontra_push[mode]" value="auto" <?php checked( $mode, 'auto' ); ?>>
				<?php esc_html_e( 'Usar configuração automática', 'encontra-push' ); ?>
			</label><br>

			<label>
				<input type="radio" name="encontra_push[mode]" value="always" <?php checked( $mode, 'always' ); ?>>
				<?php esc_html_e( 'Enviar', 'encontra-push' ); ?>
			</label><br>

			<label>
				<input type="radio" name="encontra_push[mode]" value="never" <?php checked( $mode, 'never' ); ?>>
				<?php esc_html_e( 'Não enviar', 'encontra-push' ); ?>
			</label>
		</p>

		<p>
			<label for="ep-title"><strong><?php esc_html_e( 'Título da notificação', 'encontra-push' ); ?></strong></label>
			<input type="text" id="ep-title" class="widefat" name="encontra_push[title]"
			       value="<?php echo esc_attr( $options['title'] ?? '' ); ?>"
			       placeholder="<?php esc_attr_e( 'Deixe vazio para usar o título do post', 'encontra-push' ); ?>">
		</p>

		<p>
			<label for="ep-body"><strong><?php esc_html_e( 'Texto', 'encontra-push' ); ?></strong></label>
			<textarea id="ep-body" class="widefat" rows="3" name="encontra_push[body]"
			          placeholder="<?php esc_attr_e( 'Deixe vazio para usar o resumo', 'encontra-push' ); ?>"><?php echo esc_textarea( $options['body'] ?? '' ); ?></textarea>
		</p>

		<p>
			<label for="ep-image"><strong><?php esc_html_e( 'Imagem', 'encontra-push' ); ?></strong></label>
			<input type="url" id="ep-image" class="widefat" name="encontra_push[image]"
			       value="<?php echo esc_attr( $options['image'] ?? '' ); ?>"
			       placeholder="<?php esc_attr_e( 'Deixe vazio para usar a imagem destacada', 'encontra-push' ); ?>">
		</p>

		<p>
			<label for="ep-schedule"><strong><?php esc_html_e( 'Horário do envio', 'encontra-push' ); ?></strong></label>
			<input type="datetime-local" id="ep-schedule" class="widefat" name="encontra_push[scheduled_at]"
			       value="<?php echo esc_attr( $options['scheduled_at'] ?? '' ); ?>">
			<span class="description">
				<?php esc_html_e( 'Vazio envia conforme a regra de AutoPush do site.', 'encontra-push' ); ?>
			</span>
		</p>
		<?php
	}

	public function save( int $post_id, WP_Post $post ): void {
		// Ordem importa: as checagens baratas primeiro.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado na linha seguinte.
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'encontra_push_campaign' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ja verificado.
		$input = isset( $_POST['encontra_push'] ) ? (array) wp_unslash( $_POST['encontra_push'] ) : array();

		$options = array(
			'mode'         => in_array( $input['mode'] ?? 'auto', array( 'auto', 'always', 'never' ), true )
				? $input['mode']
				: 'auto',
			'title'        => sanitize_text_field( $input['title'] ?? '' ),
			// Notificacao nao aceita HTML: sanitize_text_field remove tudo.
			'body'         => sanitize_text_field( $input['body'] ?? '' ),
			'image'        => esc_url_raw( $input['image'] ?? '' ),
			'scheduled_at' => sanitize_text_field( $input['scheduled_at'] ?? '' ),
		);

		update_post_meta( $post_id, self::META, $options );
	}
}
