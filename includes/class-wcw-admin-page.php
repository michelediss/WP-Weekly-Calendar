<?php
// =================================================
// File: includes/class-wcw-admin-page.php
// =================================================

if (!class_exists('WCW_Admin_Page')):
class WCW_Admin_Page {
  public static function init(){
    add_action('admin_menu', [__CLASS__, 'menu']);
  }

  public static function menu(){
    add_menu_page(
      __('Calendario settimanale', 'wcw'),
      __('Calendario', 'wcw'),
      'manage_options',
      'wcw-calendar',
      [__CLASS__, 'render_page'],
      'dashicons-calendar-alt',
      56
    );
  }

  public static function handle_post(){
    if (!isset($_POST['wcw_action'])) return;
    if (!current_user_can('manage_options')) return;
    if (!check_admin_referer('wcw_nonce_action', 'wcw_nonce')) return;

    $action = sanitize_text_field($_POST['wcw_action']);

    if ($action === 'save_event') {
      $post_id = intval($_POST['id'] ?? 0);
      $title = sanitize_text_field($_POST['name'] ?? '');
      $weekday = max(1, min(4, intval($_POST['weekday'] ?? 1)));
      $time = preg_replace('/[^0-9:]/', '', $_POST['time'] ?? '00:00');
      $term_id = intval($_POST['category_term'] ?? 0);

      $postarr = [
        'post_type' => WCW_CPT::POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $title,
      ];

      if ($post_id) {
        $postarr['ID'] = $post_id;
        wp_update_post($postarr);
      } else {
        $post_id = wp_insert_post($postarr);
      }

      if ($post_id && !is_wp_error($post_id)) {
        update_post_meta($post_id, WCW_CPT::META_WEEKDAY, $weekday);
        update_post_meta($post_id, WCW_CPT::META_TIME, $time);
        if ($term_id) {
          wp_set_object_terms($post_id, [$term_id], WCW_CPT::TAXONOMY, false);
        } else {
          wp_set_object_terms($post_id, [], WCW_CPT::TAXONOMY, false);
        }
      }
    }

    if ($action === 'delete_event' && !empty($_POST['id'])) {
      wp_delete_post(intval($_POST['id']), true);
    }

    if ($action === 'save_closure') {
      update_option('wcw_closure_enabled', isset($_POST['closure_enabled']) ? 1 : 0);
      update_option('wcw_closure_start', sanitize_text_field($_POST['closure_start'] ?? ''));
      update_option('wcw_closure_end', sanitize_text_field($_POST['closure_end'] ?? ''));
      update_option('wcw_closure_message', sanitize_text_field($_POST['closure_message'] ?? 'Le attività riprenderanno il giorno {date}'));
    }

    if ($action === 'add_term') {
      $new_term = sanitize_text_field($_POST['new_term'] ?? '');
      if ($new_term) {
        wp_insert_term($new_term, WCW_CPT::TAXONOMY);
      }
    }
  }

  public static function render_page(){
    if (!current_user_can('manage_options')) return;
    self::handle_post();

    $events = get_posts([
      'post_type' => WCW_CPT::POST_TYPE,
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby' => 'meta_value',
      'meta_key' => WCW_CPT::META_TIME,
      'order' => 'ASC',
    ]);

    $terms = get_terms([
      'taxonomy' => WCW_CPT::TAXONOMY,
      'hide_empty' => false,
    ]);

    ?>
    <div class="wrap">
      <h1>Calendario settimanale</h1>

      <h2 class="title">Nuovo evento</h2>
      <form method="post" class="wcw-form">
        <?php wp_nonce_field('wcw_nonce_action', 'wcw_nonce'); ?>
        <input type="hidden" name="wcw_action" value="save_event">
        <p><label>Nome <input type="text" name="name" required></label></p>
        <p><label>Giorno
          <select name="weekday">
            <option value="1">Lunedì</option>
            <option value="2">Martedì</option>
            <option value="3">Mercoledì</option>
            <option value="4">Giovedì</option>
          </select>
        </label></p>
        <p><label>Orario <input type="time" name="time" required></label></p>
        <p><label>Categoria
          <select name="category_term">
            <option value="0">— nessuna —</option>
            <?php foreach ($terms as $t): ?>
              <option value="<?php echo intval($t->term_id); ?>"><?php echo esc_html($t->name); ?></option>
            <?php endforeach; ?>
          </select>
        </label></p>
        <p><button class="button button-primary" type="submit">Salva</button></p>
      </form>

      <form method="post" class="wcw-inline">
        <?php wp_nonce_field('wcw_nonce_action', 'wcw_nonce'); ?>
        <input type="hidden" name="wcw_action" value="add_term">
        <label>Nuova categoria <input type="text" name="new_term" placeholder="Nome categoria"></label>
        <button class="button" type="submit">Aggiungi</button>
        <a class="button-link" href="<?php echo admin_url('edit-tags.php?taxonomy='.WCW_CPT::TAXONOMY.'&post_type='.WCW_CPT::POST_TYPE); ?>">Gestisci categorie</a>
      </form>

      <h2>Periodo di chiusura</h2>
      <form method="post" class="wcw-form">
        <?php wp_nonce_field('wcw_nonce_action', 'wcw_nonce'); ?>
        <input type="hidden" name="wcw_action" value="save_closure">
        <?php $enabled = (bool) get_option('wcw_closure_enabled', 0);
              $start = get_option('wcw_closure_start', '');
              $end   = get_option('wcw_closure_end', '');
              $msg   = get_option('wcw_closure_message', 'Le attività riprenderanno il giorno {date}');
        ?>
        <p><label><input type="checkbox" name="closure_enabled" <?php checked($enabled); ?>> Abilita chiusura</label></p>
        <p><label>Dal <input type="date" name="closure_start" value="<?php echo esc_attr($start); ?>"></label></p>
        <p><label>Al <input type="date" name="closure_end" value="<?php echo esc_attr($end); ?>"></label></p>
        <p><label>Messaggio <input type="text" name="closure_message" size="60" value="<?php echo esc_attr($msg); ?>"></label></p>
        <p><small>Usa {date} per inserire la data di riapertura.</small></p>
        <p><button class="button" type="submit">Salva impostazioni</button></p>
      </form>

      <hr>
      <h2>Eventi</h2>
      <table class="widefat">
        <thead><tr>
          <th>Nome</th><th>Giorno</th><th>Orario</th><th>Categoria</th><th>Azione</th>
        </tr></thead>
        <tbody>
        <?php if (empty($events)): ?>
          <tr><td colspan="5">Nessun evento</td></tr>
        <?php else: foreach ($events as $e): ?>
          <?php $weekday = intval(get_post_meta($e->ID, WCW_CPT::META_WEEKDAY, true));
                $time = get_post_meta($e->ID, WCW_CPT::META_TIME, true);
                $terms_e = get_the_terms($e, WCW_CPT::TAXONOMY);
                $term_name = ($terms_e && !is_wp_error($terms_e)) ? $terms_e[0]->name : '';
          ?>
          <tr>
            <td><?php echo esc_html($e->post_title); ?></td>
            <td><?php echo esc_html(WCW_CPT::day_label($weekday)); ?></td>
            <td><?php echo esc_html(substr($time,0,5)); ?></td>
            <td><?php echo esc_html($term_name); ?></td>
            <td>
              <form method="post" style="display:inline">
                <?php wp_nonce_field('wcw_nonce_action', 'wcw_nonce'); ?>
                <input type="hidden" name="wcw_action" value="delete_event">
                <input type="hidden" name="id" value="<?php echo intval($e->ID); ?>">
                <button class="button-link-delete" type="submit">Elimina</button>
              </form>
              <a href="<?php echo get_edit_post_link($e->ID); ?>">Modifica</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>

      <h2>Anteprima</h2>
      <div class="wcw-preview">
        <?php echo WCW_Shortcode::render_table_html(''); ?>
      </div>
    </div>
    <?php
  }
}
endif;
