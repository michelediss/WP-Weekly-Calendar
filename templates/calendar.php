<?php
use WPWC\Activities;
use function WPWC\get_attivita_color;

$days = $days ?? [ '1'=>'Lunedì','2'=>'Martedì','3'=>'Mercoledì','4'=>'Giovedì','5'=>'Venerdì','6'=>'Sabato','7'=>'Domenica' ];
$selected_att = isset($_GET['attivita']) ? (int) $_GET['attivita'] : 0;

$meta_query = [];
if ( $selected_att ) $meta_query[] = [ 'key' => '_wpwc_attivita', 'value' => $selected_att ];

$q = new WP_Query( [
	'post_type'      => 'wpwc_event',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'meta_key'       => '_wpwc_start',
	'orderby'        => [ 'meta_value' => 'ASC', 'title' => 'ASC' ],
	'meta_query'     => $meta_query,
	'no_found_rows'  => true,
] );

$events_by_day = array_fill_keys( array_keys($days), [] );
while( $q->have_posts() ): $q->the_post();
	$id   = get_the_ID();
	$day  = get_post_meta( $id, '_wpwc_day', true ) ?: '1';
	$start= get_post_meta( $id, '_wpwc_start', true );
	$end  = get_post_meta( $id, '_wpwc_end', true );
	$att  = (int) get_post_meta( $id, '_wpwc_attivita', true );
	$events_by_day[$day][] = [
		'id' => $id,
		'title' => get_the_title(),
		'content' => wp_strip_all_tags( get_the_content() ),
		'start' => $start,
		'end'   => $end,
		'att'   => $att,
	];
endwhile; wp_reset_postdata();

$activities = $activities ?? Activities::all();
?>
<div class="wpwc-wrap">

	<div class="wpwc-toolbar">
		<a class="wpwc-chip" href="<?php echo esc_url( remove_query_arg('attivita') ); ?>">
			<span class="dot" style="background:#999"></span><?php esc_html_e('Tutte le attività','wp-weekly-calendar'); ?>
		</a>
		<?php foreach( $activities as $a ): ?>
			<?php $url = add_query_arg( 'attivita', (int)$a['id'] ); ?>
			<a class="wpwc-chip" href="<?php echo esc_url( $url ); ?>" data-wpwc-attivita="<?php echo (int)$a['id']; ?>">
				<span class="dot" style="background:<?php echo esc_attr($a['color']); ?>"></span>
				<?php echo esc_html( $a['title'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="wpwc-grid">
		<div class="wpwc-head">
			<div class="wpwc-day timecol"><?php esc_html_e('Orari','wp-weekly-calendar'); ?></div>
			<?php foreach( $days as $dlabel ): ?>
				<div class="wpwc-day"><?php echo esc_html( $dlabel ); ?></div>
			<?php endforeach; ?>
		</div>

		<?php
		$timecol = '<div class="wpwc-cell timecol"><div class="wpwc-time">08:00</div><div class="wpwc-time">12:00</div><div class="wpwc-time">16:00</div><div class="wpwc-time">20:00</div></div>';
		$max_rows = 8;
		for ( $row = 0; $row < $max_rows; $row++ ):
			echo $timecol;
			foreach( array_keys($days) as $day_key ):
				echo '<div class="wpwc-cell">';
				if ( ! empty( $events_by_day[$day_key] ) ) {
					foreach ( $events_by_day[$day_key] as $ev ) {
						$aid = (int) $ev['att'];
						$color = $aid ? get_attivita_color( $aid ) : '#999';
						$title = $aid ? get_the_title( $aid ) : '';
						$att_url = $aid ? get_permalink( $aid ) : '#';
						printf(
							'<div class="wpwc-event" style="border-left:6px solid %1$s;background:linear-gradient(0deg,%1$s1A,%1$s1A),#fff">
								<div class="title">%2$s</div>
								<div class="meta">%3$s – %4$s • <a href="%5$s">%6$s</a></div>
							</div>',
							esc_attr( $color ),
							esc_html( $ev['title'] ),
							esc_html( $ev['start'] ?: '--:--' ),
							esc_html( $ev['end'] ?: '--:--' ),
							esc_url( $att_url ),
							esc_html( $title ?: __('Senza attività','wp-weekly-calendar') )
						);
					}
				}
				echo '</div>';
			endforeach;
		endfor;
		?>
	</div>

	<div style="margin-top:16px">
		<h3><?php esc_html_e('Attività (categorie del calendario)','wp-weekly-calendar'); ?></h3>
		<ul>
			<?php foreach( $activities as $a ): ?>
				<li>
					<span class="dot" style="background:<?php echo esc_attr($a['color']); ?>;width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;vertical-align:middle"></span>
					<a href="<?php echo esc_url( $a['url'] ); ?>"><?php echo esc_html( $a['title'] ); ?></a>
					<small style="color:#64748b">/attivita/<?php echo esc_html( $a['slug'] ); ?></small>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

</div>
