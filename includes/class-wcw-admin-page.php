<?php
if ( ! class_exists( 'WCW_Admin_Page' ) ) :
class WCW_Admin_Page {

  public static function init() {
    add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
    // AJAX CRUD eventi (solo admin)
    add_action( 'wp_ajax_wcw_save_event',   [ __CLASS__, 'ajax_save_event' ] );
    add_action( 'wp_ajax_wcw_delete_event', [ __CLASS__, 'ajax_delete_event' ] );
    // Salvataggio impostazioni (chiusure ecc.)
    add_action( 'admin_post_wcw_save_closure', [ __CLASS__, 'save_closure' ] );
  }

  public static function menu() {
    add_menu_page(
      __( 'Calendario settimanale', 'wcw' ),
      __( 'Calendario', 'wcw' ),
      'manage_options',
      'wcw-calendar',
      [ __CLASS__, 'render_page' ],
      'dashicons-calendar-alt',
      56
    );
  }

  /** Pagina principale */
  public static function render_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $events     = WCW_DB::get_events();
    $categories = WCW_DB::get_categories_all();
    $nonce      = wp_create_nonce( 'wcw_admin' );

    ?>
    <div class="wrap">
      <h1 class="wp-heading-inline"><?php esc_html_e( 'Calendario settimanale', 'wcw' ); ?></h1>
      <hr class="wp-header-end" />

      <div id="wcw-admin-root" class="wcw-admin-grid">
        <div class="wcw-card">
          <h2><?php esc_html_e( 'Nuova / Modifica attività', 'wcw' ); ?></h2>
          <form id="wcw-form">
            <input type="hidden" name="action" value="wcw_save_event" />
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
            <input type="hidden" name="id" value="" />

            <p>
              <label><?php esc_html_e( 'Nome', 'wcw' ); ?><br/>
                <input type="text" name="name" required class="regular-text" />
              </label>
            </p>

            <p>
              <label><?php esc_html_e( 'Sottotitolo', 'wcw' ); ?><br/>
                <input type="text" name="subtitle" class="regular-text" />
              </label>
            </p>

            <p>
              <label><?php esc_html_e( 'Giorno', 'wcw' ); ?><br/>
                <select name="weekday" required>
                  <option value="1">Lunedì</option>
                  <option value="2">Martedì</option>
                  <option value="3">Mercoledì</option>
                  <option value="4">Giovedì</option>
                  <option value="5">Venerdì</option>
                  <option value="6">Sabato</option>
                  <option value="7">Domenica</option>
                </select>
              </label>
            </p>

            <p>
              <label><?php esc_html_e( 'Orario inizio', 'wcw' ); ?><br/>
                <input type="time" name="time" required />
              </label>
            </p>

            <p>
              <label><?php esc_html_e( 'Orario fine', 'wcw' ); ?><br/>
                <input type="time" name="time_end" />
              </label>
            </p>

            <p>
              <label><?php esc_html_e( 'Categoria (CPT: attivita)', 'wcw' ); ?><br/>
                <select name="category_id">
                  <option value=""><?php esc_html_e( 'Nessuna', 'wcw' ); ?></option>
                  <?php foreach ( $categories as $c ) : ?>
                    <option value="<?php echo (int) $c->id; ?>">
                      <?php echo esc_html( $c->name ); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
            </p>

            <p>
              <button type="submit" class="button button-primary"><?php esc_html_e( 'Salva', 'wcw' ); ?></button>
              <button type="button" id="wcw-reset" class="button"><?php esc_html_e( 'Pulisci', 'wcw' ); ?></button>
            </p>
          </form>
        </div>

        <div class="wcw-card">
          <h2><?php esc_html_e( 'Eventi', 'wcw' ); ?></h2>
          <table class="widefat fixed striped">
            <thead>
              <tr>
                <th><?php esc_html_e( 'Nome', 'wcw' ); ?></th>
                <th><?php esc_html_e( 'Sottotitolo', 'wcw' ); ?></th>
                <th><?php esc_html_e( 'Giorno', 'wcw' ); ?></th>
                <th><?php esc_html_e( 'Inizio', 'wcw' ); ?></th>
                <th><?php esc_html_e( 'Fine', 'wcw' ); ?></th>
                <th><?php esc_html_e( 'Categoria', 'wcw' ); ?></th>
                <th><?php esc_html_e( 'Azione', 'wcw' ); ?></th>
              </tr>
            </thead>
            <tbody id="wcw-events-tbody">
              <?php foreach ( $events as $e ) :
                $day   = self::day_label( (int) $e->weekday );
                $start = $e->time ? substr( $e->time, 0, 5 ) : '';
                $end   = $e->time_end ? substr( $e->time_end, 0, 5 ) : '';
                ?>
                <tr
                  data-id="<?php echo (int) $e->id; ?>"
                  data-day="<?php echo (int) $e->weekday; ?>"
                  data-time="<?php echo esc_attr( $start ); ?>"
                  data-time_end="<?php echo esc_attr( $end ); ?>"
                  data-subtitle="<?php echo esc_attr( $e->subtitle ?? '' ); ?>"
                  data-cat="<?php echo (int) ( $e->category_id ?: 0 ); ?>"
                >
                  <td class="c-name"><?php echo esc_html( $e->name ); ?></td>
                  <td class="c-subtitle"><?php echo esc_html( $e->subtitle ?? '' ); ?></td>
                  <td class="c-day"><?php echo esc_html( $day ); ?></td>
                  <td class="c-time"><?php echo esc_html( $start ); ?></td>
                  <td class="c-time-end"><?php echo esc_html( $end ); ?></td>
                  <td class="c-cat"><?php echo esc_html( $e->category_name ?? '' ); ?></td>
                  <td class="c-actions">
                    <a href="#" class="button wcw-edit"><?php esc_html_e( 'Modifica', 'wcw' ); ?></a>
                    <a href="#" class="button button-link-delete wcw-del"><?php esc_html_e( 'Elimina', 'wcw' ); ?></a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script>
    (function(){
      const $ = (s, c=document) => c.querySelector(s);
      const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));
      const form = $('#wcw-form');
      const tbody = $('#wcw-events-tbody');

      const reset = () => {
        form.id.value = '';
        form.name.value = '';
        form.subtitle.value = '';
        form.weekday.value = '1';
        form.time.value = '';
        form.time_end.value = '';
        form.category_id.value = '';
      };
      $('#wcw-reset').addEventListener('click', reset);

      // Riempie il form dai dati della riga
      tbody.addEventListener('click', function(e){
        const t = e.target.closest('a');
        if (!t) return;

        const tr = e.target.closest('tr');
        if (t.classList.contains('wcw-edit')) {
          e.preventDefault();
          form.id.value = tr.dataset.id || '';
          form.name.value = (tr.querySelector('.c-name')?.textContent || '').trim();
          form.subtitle.value = (tr.querySelector('.c-subtitle')?.textContent || '').trim();
          form.weekday.value = tr.dataset.day || '1';
          form.time.value = tr.dataset.time || '';
          form.time_end.value = tr.dataset.time_end || '';
          form.category_id.value = tr.dataset.cat || '';
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        if (t.classList.contains('wcw-del')) {
          e.preventDefault();
          if (!confirm('Eliminare questo evento?')) return;
          const fd = new FormData();
          fd.append('action', 'wcw_delete_event');
          fd.append('_wpnonce', '<?php echo esc_js( $nonce ); ?>');
          fd.append('id', tr.dataset.id || '');
          fetch(ajaxurl, { method:'POST', body: fd })
            .then(r => r.json())
            .then(json => {
              if (json.success) tr.remove();
              else alert(json.data?.message || 'Errore');
            })
            .catch(() => alert('Errore di rete'));
        }
      });

      // Salva
      form.addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(form);
        fetch(ajaxurl, { method:'POST', body: fd })
          .then(r => r.json())
          .then(json => {
            if (json.success) location.reload();
            else alert(json.data?.message || 'Errore');
          })
          .catch(() => alert('Errore di rete'));
      });
    })();
    </script>
    <?php
  }

  /** AJAX: salva/aggiorna evento */
  public static function ajax_save_event() {
    self::check_caps_and_nonce();

    $id       = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name     = sanitize_text_field( $_POST['name']     ?? '' );
    $subtitle = sanitize_text_field( $_POST['subtitle'] ?? '' );
    $day      = isset($_POST['weekday']) ? intval($_POST['weekday']) : 0;
    $time     = preg_replace( '/[^0-9:]/', '', $_POST['time']      ?? '' );
    $time_end = preg_replace( '/[^0-9:]/', '', $_POST['time_end']  ?? '' );
    $cat      = intval( $_POST['category_id'] ?? 0 ) ?: null;

    if ( $name === '' || $time === '' || $day < 1 || $day > 7 ) {
      wp_send_json_error( [ 'message' => 'Dati mancanti o non validi' ], 400 );
    }

    // opzionale: impedisci fine < inizio
    if ( $time_end && strcmp($time_end, $time) < 0 ) {
      wp_send_json_error( [ 'message' => 'L\'orario di fine non può precedere quello di inizio' ], 400 );
    }

    $ok = $id
      ? WCW_DB::update_event( $id, $name, $day, $time, $cat, $subtitle, $time_end )
      : WCW_DB::insert_event( $name, $day, $time, $cat, $subtitle, $time_end );

    $ok ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Errore DB' ], 500 );
  }

  /** AJAX: elimina */
  public static function ajax_delete_event() {
    self::check_caps_and_nonce();
    $id = intval( $_POST['id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( [ 'message' => 'ID mancante' ], 400 );
    $ok = WCW_DB::delete_event( $id );
    $ok ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Errore DB' ], 500 );
  }

  /** Salvataggio impostazioni di chiusura (se presenti nel form) */
  public static function save_closure() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    check_admin_referer( 'wcw_admin' );
    update_option( 'wcw_closure_enabled', isset( $_POST['wcw_closure_enabled'] ) ? 1 : 0 );
    update_option( 'wcw_closure_start', sanitize_text_field( $_POST['wcw_closure_start'] ?? '' ) );
    update_option( 'wcw_closure_end',   sanitize_text_field( $_POST['wcw_closure_end']   ?? '' ) );
    update_option( 'wcw_closure_message', sanitize_text_field( $_POST['wcw_closure_message'] ?? '' ) );
    wp_safe_redirect( admin_url( 'admin.php?page=wcw-calendar&updated=1' ) );
    exit;
  }

  private static function check_caps_and_nonce() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Permessi insufficienti' ], 403 );
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'wcw_admin' ) ) {
      wp_send_json_error( [ 'message' => 'Nonce non valido' ], 403 );
    }
  }

  private static function day_label( $d ) {
    $m = [ 1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica' ];
    return $m[ $d ] ?? '';
  }
}
endif;
