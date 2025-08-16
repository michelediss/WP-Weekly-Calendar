#!/usr/bin/env bash
set -Eeuo pipefail

EXPECTED_SLUG="wp-weekly-calendar"
FORCE=0
CHECK=1

usage() {
  cat <<EOF
Scaffolder del plugin WP Weekly Calendar (da eseguire *dentro* ${EXPECTED_SLUG}/).

USO:
  $(basename "$0") [--force] [--no-check]

OPZIONI:
  --force     Sovrascrive i file se esistono già.
  --no-check  Non controlla che la directory corrente si chiami '${EXPECTED_SLUG}'.

ESEMPI:
  $(basename "$0")
  $(basename "$0") --force
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --force) FORCE=1; shift;;
    --no-check) CHECK=0; shift;;
    -h|--help) usage; exit 0;;
    *) echo "Argomento sconosciuto: $1"; usage; exit 1;;
  esac
done

# Verifica di essere dentro la cartella corretta
if [[ $CHECK -eq 1 ]]; then
  BASENAME="$(basename "$PWD")"
  if [[ "$BASENAME" != "$EXPECTED_SLUG" ]]; then
    echo "ERRORE: sei in '$BASENAME', ma lo script va eseguito dentro '${EXPECTED_SLUG}/'."
    echo "Usa --no-check per ignorare questo controllo."
    exit 1
  fi
fi

echo "→ Preparazione struttura cartelle"
mkdir -p includes assets/css assets/js templates

FILES=(
  "wp-weekly-calendar.php"
  "readme.txt"
  "includes/helpers.php"
  "includes/class-wpwc-plugin.php"
  "includes/class-wpwc-post-types.php"
  "includes/class-wpwc-metaboxes.php"
  "includes/class-wpwc-activities.php"
  "includes/class-wpwc-rest.php"
  "includes/class-wpwc-shortcodes.php"
  "includes/template-loader.php"
  "assets/css/calendar.css"
  "assets/js/calendar.js"
  "templates/calendar.php"
)

if [[ $FORCE -ne 1 ]]; then
  echo "→ Controllo file esistenti (usa --force per sovrascrivere)"
  for f in "${FILES[@]}"; do
    if [[ -e "$f" ]]; then
      echo "ERRORE: esiste già '$f'. Interrompo per sicurezza. (--force per sovrascrivere)"
      exit 1
    fi
  done
fi

# --- wp-weekly-calendar.php ---
cat > "wp-weekly-calendar.php" <<'PHP'
<?php
/**
 * Plugin Name:       WP Weekly Calendar (Attività = Categorie)
 * Description:       Calendario settimanale i cui "filtri categoria" derivano 1:1 dal CPT 'attivita'. Ogni attività è una categoria con colore ACF e link /attivita/slug.
 * Version:           2.0.0
 * Author:            WP Weekly Calendar Team
 * Text Domain:       wp-weekly-calendar
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WPWC_FILE', __FILE__ );
define( 'WPWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPWC_URL', plugin_dir_url( __FILE__ ) );
define( 'WPWC_VER', '2.0.0' );

require_once WPWC_DIR . 'includes/helpers.php';
require_once WPWC_DIR . 'includes/class-wpwc-plugin.php';

register_activation_hook( __FILE__, function() {
	\WPWC\Plugin::instance()->activate();
});

register_deactivation_hook( __FILE__, function() {
	\WPWC\Plugin::instance()->deactivate();
});

add_action( 'plugins_loaded', function() {
	load_plugin_textdomain( 'wp-weekly-calendar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	\WPWC\Plugin::instance()->boot();
} );
PHP

# --- readme.txt ---
cat > "readme.txt" <<'TXT'
=== WP Weekly Calendar (Attività = Categorie) ===
Contributors: your-name
Tags: calendar, weekly, eventi, cpt, acf
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Il calendario settimanale in cui le categorie NON sono tassonomie ma i post del CPT 'attivita'. Ogni 'attività' equivale a una categoria del calendario con colore ACF e link /attivita/slug.

== Descrizione ==
- Ogni post del CPT `attivita` è una categoria utilizzabile per gli eventi.
- Il colore è letto dal campo ACF (name: `colore`).
- Gli eventi (`wpwc_event`) hanno: giorno settimana (1-7), ora inizio/fine, attività collegata.
- Shortcode: `[weekly_calendar]`.

== Installazione ==
1. Copia questa cartella in `wp-content/plugins/`
2. Attiva il plugin.
3. Assicurati di avere il CPT `attivita` con campo ACF `colore`.
4. Crea post `attivita` e poi crea eventi associandoli.

== Shortcode ==
[weekly_calendar]

== REST ==
- GET /wp-json/wpwc/v1/attivita
- GET /wp-json/wpwc/v1/events?day=1&attivita=123

== Note ==
Le "categorie" del calendario sono i post `attivita`. I link puntano a /attivita/slug.
TXT

# --- includes/helpers.php ---
cat > "includes/helpers.php" <<'PHP'
<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

function get_attivita_color( $attivita_id ): string {
	$color = get_post_meta( $attivita_id, 'colore', true );
	if ( ! is_string( $color ) || ! preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
		$color = '#777777';
	}
	return $color;
}

function sanitize_time_24h( $time ): string {
	if ( ! is_string( $time ) ) return '';
	if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m ) ) return $m[0];
	return '';
}

function week_days(): array {
	return [ '1' => __( 'Lunedì', 'wp-weekly-calendar' ),
	         '2' => __( 'Martedì', 'wp-weekly-calendar' ),
	         '3' => __( 'Mercoledì', 'wp-weekly-calendar' ),
	         '4' => __( 'Giovedì', 'wp-weekly-calendar' ),
	         '5' => __( 'Venerdì', 'wp-weekly-calendar' ),
	         '6' => __( 'Sabato', 'wp-weekly-calendar' ),
	         '7' => __( 'Domenica', 'wp-weekly-calendar' ) ];
}
PHP

# --- includes/class-wpwc-plugin.php ---
cat > "includes/class-wpwc-plugin.php" <<'PHP'
<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

require_once WPWC_DIR . 'includes/class-wpwc-post-types.php';
require_once WPWC_DIR . 'includes/class-wpwc-metaboxes.php';
require_once WPWC_DIR . 'includes/class-wpwc-activities.php';
require_once WPWC_DIR . 'includes/class-wpwc-rest.php';
require_once WPWC_DIR . 'includes/class-wpwc-shortcodes.php';
require_once WPWC_DIR . 'includes/template-loader.php';

final class Plugin {

	private static $instance = null;

	public static function instance(): self {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	public function boot() {
		add_action( 'init', [ Post_Types::class, 'register' ] );
		add_action( 'init', [ $this, 'register_assets' ] );
		Metaboxes::hooks();
		REST::hooks();
		Shortcodes::hooks();
		Template_Loader::hooks();
	}

	public function register_assets() {
		wp_register_style( 'wpwc-calendar', WPWC_URL . 'assets/css/calendar.css', [], WPWC_VER );
		wp_register_script( 'wpwc-calendar', WPWC_URL . 'assets/js/calendar.js', [ 'wp-element' ], WPWC_VER, true );
		wp_localize_script( 'wpwc-calendar', 'WPWC', [
			'rest'   => [ 'root' => esc_url_raw( rest_url( 'wpwc/v1' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ) ],
			'i18n'   => [ 'all' => __( 'Tutte le attività', 'wp-weekly-calendar' ) ],
		] );
	}

	public function activate() {
		Post_Types::register();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}
}
PHP

# --- includes/class-wpwc-post-types.php ---
cat > "includes/class-wpwc-post-types.php" <<'PHP'
<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class Post_Types {
	public static function register() {
		$labels = [
			'name'               => __( 'Eventi settimanali', 'wp-weekly-calendar' ),
			'singular_name'      => __( 'Evento settimanale', 'wp-weekly-calendar' ),
			'add_new'            => __( 'Aggiungi nuovo', 'wp-weekly-calendar' ),
			'add_new_item'       => __( 'Aggiungi nuovo evento', 'wp-weekly-calendar' ),
			'edit_item'          => __( 'Modifica evento', 'wp-weekly-calendar' ),
			'new_item'           => __( 'Nuovo evento', 'wp-weekly-calendar' ),
			'view_item'          => __( 'Vedi evento', 'wp-weekly-calendar' ),
			'search_items'       => __( 'Cerca eventi', 'wp-weekly-calendar' ),
			'not_found'          => __( 'Nessun evento trovato', 'wp-weekly-calendar' ),
			'not_found_in_trash' => __( 'Nessun evento nel cestino', 'wp-weekly-calendar' ),
			'menu_name'          => __( 'Calendario', 'wp-weekly-calendar' ),
		];

		register_post_type( 'wpwc_event', [
			'labels' => $labels,
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'menu_icon' => 'dashicons-calendar-alt',
			'supports' => [ 'title', 'editor' ],
			'show_in_rest' => true,
			'has_archive' => false,
			'rewrite' => false,
		] );
	}
}
PHP

# --- includes/class-wpwc-metaboxes.php ---
cat > "includes/class-wpwc-metaboxes.php" <<'PHP'
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
PHP

# --- includes/class-wpwc-activities.php ---
cat > "includes/class-wpwc-activities.php" <<'PHP'
<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class Activities {

	public static function all(): array {
		$ids = get_posts( [
			'post_type'   => 'attivita',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
			'fields'      => 'ids',
		] );

		$out = [];
		foreach ( $ids as $id ) {
			$out[] = self::format( $id );
		}
		return $out;
	}

	public static function format( int $id ): array {
		return [
			'id'    => $id,
			'title' => get_the_title( $id ),
			'slug'  => basename( get_permalink( $id ) ),
			'url'   => get_permalink( $id ),
			'color' => get_attivita_color( $id ),
		];
	}
}
PHP

# --- includes/class-wpwc-rest.php ---
cat > "includes/class-wpwc-rest.php" <<'PHP'
<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class REST {

	public static function hooks() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	public static function routes() {
		register_rest_route( 'wpwc/v1', '/attivita', [
			'methods'  => 'GET',
			'callback' => function() {
				return rest_ensure_response( Activities::all() );
			},
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'wpwc/v1', '/events', [
			'methods'  => 'GET',
			'args'     => [
				'day'       => ['validate_callback' => function($v){ return in_array( (string)$v, array_keys( week_days() ), true ); }],
				'attivita'  => ['validate_callback' => function($v){ return empty($v) || is_numeric($v); }],
			],
			'callback' => [ __CLASS__, 'get_events' ],
			'permission_callback' => '__return_true',
		] );
	}

	public static function get_events( \WP_REST_Request $req ) {
		$day  = $req->get_param( 'day' );
		$att  = (int) $req->get_param( 'attivita' );

		$meta_query = [];
		if ( $day ) {
			$meta_query[] = [ 'key' => '_wpwc_day', 'value' => (string) $day ];
		}
		if ( $att ) {
			$meta_query[] = [ 'key' => '_wpwc_attivita', 'value' => $att, 'compare' => '=' ];
		}

		$q = new \WP_Query( [
			'post_type'      => 'wpwc_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => [ 'meta_value' => 'ASC', 'title' => 'ASC' ],
			'meta_key'       => '_wpwc_start',
			'meta_query'     => $meta_query,
			'no_found_rows'  => true,
		] );

		$events = [];
		while ( $q->have_posts() ) {
			$q->the_post();
			$id   = get_the_ID();
			$attv = (int) get_post_meta( $id, '_wpwc_attivita', true );
			$events[] = [
				'id'       => $id,
				'title'    => get_the_title(),
				'content'  => wp_strip_all_tags( get_the_content() ),
				'day'      => get_post_meta( $id, '_wpwc_day', true ),
				'start'    => get_post_meta( $id, '_wpwc_start', true ),
				'end'      => get_post_meta( $id, '_wpwc_end', true ),
				'attivita' => $attv ? Activities::format( $attv ) : null,
				'permalink'=> get_permalink( $id ),
			];
		}
		wp_reset_postdata();

		return rest_ensure_response( $events );
	}
}
PHP

# --- includes/class-wpwc-shortcodes.php ---
cat > "includes/class-wpwc-shortcodes.php" <<'PHP'
<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class Shortcodes {
	public static function hooks() {
		add_shortcode( 'weekly_calendar', [ __CLASS__, 'render' ] );
	}
	public static function render( $atts = [] ) {
		wp_enqueue_style( 'wpwc-calendar' );
		wp_enqueue_script( 'wpwc-calendar' );
		ob_start();
		Template_Loader::template( 'calendar.php', [
			'activities' => Activities::all(),
			'days'       => week_days(),
		] );
		return ob_get_clean();
	}
}
PHP

# --- includes/template-loader.php ---
cat > "includes/template-loader.php" <<'PHP'
<?php
namespace WPWC;

if ( ! defined( 'ABSPATH' ) ) exit;

class Template_Loader {
	public static function hooks() { /* placeholder per override futuri */ }
	public static function path( string $template ): string {
		$default = WPWC_DIR . 'templates/' . ltrim( $template, '/' );
		return apply_filters( 'wpwc_template_' . basename( $template ), $default );
	}
	public static function template( string $template, array $vars = [] ) {
		$path = self::path( $template );
		extract( $vars, EXTR_SKIP );
		include $path;
	}
}
PHP

# --- assets/css/calendar.css ---
cat > "assets/css/calendar.css" <<'CSS'
/* Stili base calendario */
.wpwc-wrap{--gap:10px;--radius:14px;--border:#e2e8f0;--muted:#64748b;--bg:#ffffff}
.wpwc-wrap{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,'Helvetica Neue',Arial,sans-serif}

.wpwc-toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
.wpwc-chip{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:999px;padding:6px 10px;text-decoration:none;color:#0f172a;background:#fff;transition:.15s}
.wpwc-chip:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(0,0,0,.06)}
.wpwc-chip .dot{width:10px;height:10px;border-radius:50%}

.wpwc-grid{display:grid;grid-template-columns:120px repeat(7,1fr);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.wpwc-head{display:contents}
.wpwc-cell{border-bottom:1px solid var(--border);border-right:1px solid var(--border);padding:10px;min-height:72px}
.wpwc-cell:last-child{border-right:none}
.wpwc-time{color:var(--muted);font-size:12px}
.wpwc-day{font-weight:600;text-align:center;background:#f8fafc;padding:12px;border-right:1px solid var(--border)}
.wpwc-day:last-child{border-right:none}
.wpwc-event{border-radius:10px;padding:8px 10px;margin:6px 0;background:#eef2ff}
.wpwc-event .title{font-weight:600}
.wpwc-event .meta{font-size:12px;color:var(--muted)}
@media (max-width:900px){
  .wpwc-grid{grid-template-columns:1fr}
  .wpwc-cell.timecol{display:none}
  .wpwc-day{border-right:none}
}
CSS

# --- assets/js/calendar.js ---
cat > "assets/js/calendar.js" <<'JS'
(function(){
	function setQS(key,val){
		const u=new URL(window.location); if(val==null||val===''){u.searchParams.delete(key);}else{u.searchParams.set(key,val)}
		history.replaceState({},'',u);
	}
	document.addEventListener('click', function(e){
		const t = e.target.closest('[data-wpwc-attivita]');
		if(!t) return;
		e.preventDefault();
		const id = t.getAttribute('data-wpwc-attivita');
		setQS('attivita', id);
		window.location = window.location.href;
	});
})();
JS

# --- templates/calendar.php ---
cat > "templates/calendar.php" <<'PHP'
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
PHP

echo "✓ File generati."

echo
echo "Struttura risultante:"
find . -maxdepth 3 -mindepth 1 | sed 's#^\./#  #g'

echo
echo "Fatto! Attiva il plugin dalla Bacheca oppure via WP-CLI:"
echo "  wp plugin activate wp-weekly-calendar"

