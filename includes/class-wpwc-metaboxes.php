<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class Metaboxes {

	public static function hooks() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add' ] );
		add_action( 'save_post_wpwc_event', [ __CLASS__, 'save' ] );
	}

	public static function add() {
		add_meta_box(
			'wpwc_event_details',
			__( 'Dettagli evento settimanale', 'wp-weekly-calendar' ),
			[ __CLASS__, 'render' ],
			'wpwc_event',
			'normal',
			'high'
		);
	}

	public static function render( $post ) {
		wp_nonce_field( 'wpwc_event_save', 'wpwc_event_nonce' );

		$day   = get_post_meta( $post->ID, '_wpwc_day', true ) ?: '1';
		$start = get_post_meta( $post->ID, '_wpwc_start', true ) ?: '';
		$end   = get_post_meta( $post->ID, '_wpwc_end', true )   ?: '';
		$att   = get_post_meta( $post->ID, '_wpwc_attivita', true ) ?: '';

		$days = week_days();

		$attivita = get_posts( [
			'post_type'   => 'attivita',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
			'fields'      => 'ids',
		] );
		?>
		<style>
			.wpwc-field{margin-bottom:12px;}
			.wpwc-inline{display:flex;gap:12px;align-items:center}
			.wpwc-colorchip{display:inline-block;width:14px;height:14px;border-radius:50%;margin-left:6px;vertical-align:middle;border:1px solid #ccd0d4}
		</style>

		<div class="wpwc-field">
			<label for="wpwc_day"><strong><?php esc_html_e( 'Giorno della settimana', 'wp-weekly-calendar' ); ?></strong></label><br>
			<select id="wpwc_day" name="wpwc_day">
				<?php foreach ( $days as $k => $label ): ?>
					<option value="<?php echo esc_attr($k); ?>" <?php selected( (string)$day, (string)$k ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="wpwc-field wpwc-inline">
			<div>
				<label for="wpwc_start"><strong><?php esc_html_e( 'Ora inizio (HH:MM)', 'wp-weekly-calendar' ); ?></strong></label><br>
				<input type="time" id="wpwc_start" name="wpwc_start" value="<?php echo esc_attr( $start ); ?>" pattern="[0-9]{2}:[0-9]{2}">
			</div>
			<div>
				<label for="wpwc_end"><strong><?php esc_html_e( 'Ora fine (HH:MM)', 'wp-weekly-calendar' ); ?></strong></label><br>
				<input type="time" id="wpwc_end" name="wpwc_end" value="<?php echo esc_attr( $end ); ?>" pattern="[0-9]{2}:[0-9]{2}">
			</div>
		</div>

		<div class="wpwc-field">
			<label for="wpwc_attivita"><strong><?php esc_html_e( 'Attività (categoria del calendario)', 'wp-weekly-calendar' ); ?></strong></label><br>
			<select id="wpwc_attivita" name="wpwc_attivita">
				<option value=""><?php esc_html_e( '— Seleziona attività —', 'wp-weekly-calendar' ); ?></option>
				<?php
				foreach ( $attivita as $id ) {
					$title = get_the_title( $id );
					printf(
						'<option value="%1$d" %2$s>%3$s</option>',
						(int) $id,
						selected( (int) $att, (int) $id, false ),
						esc_html( $title )
					);
				}
				?>
			</select>
			<?php if ( $att ): ?>
				<span class="wpwc-colorchip" style="background:<?php echo esc_attr( get_attivita_color( (int)$att ) ); ?>"></span>
			<?php endif; ?>
			<p class="description">
				<?php esc_html_e( 'Le categorie del calendario sono i post del CPT "attivita". Il colore viene dal campo ACF "colore".', 'wp-weekly-calendar' ); ?>
			</p>
		</div>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['wpwc_event_nonce'] ) || ! wp_verify_nonce( $_POST['wpwc_event_nonce'], 'wpwc_event_save' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$day   = isset($_POST['wpwc_day']) ? sanitize_text_field( $_POST['wpwc_day'] ) : '';
		$start = isset($_POST['wpwc_start']) ? sanitize_time_24h( $_POST['wpwc_start'] ) : '';
		$end   = isset($_POST['wpwc_end']) ? sanitize_time_24h( $_POST['wpwc_end'] ) : '';
		$att   = isset($_POST['wpwc_attivita']) ? (int) $_POST['wpwc_attivita'] : 0;

		$day = in_array( $day, array_keys( week_days() ), true ) ? $day : '1';

		update_post_meta( $post_id, '_wpwc_day', $day );
		update_post_meta( $post_id, '_wpwc_start', $start );
		update_post_meta( $post_id, '_wpwc_end', $end );
		update_post_meta( $post_id, '_wpwc_attivita', $att );
	}
}
