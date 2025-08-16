<?php
// =================================================
// File: includes/class-wcw-shortcode.php
// =================================================

if (!class_exists('WCW_Shortcode')):
class WCW_Shortcode {

  public static function init(){
    add_shortcode('wcw_schedule', [__CLASS__, 'render']);
    // AJAX filtro pubblico
    add_action('wp_ajax_wpwcf_filter', [__CLASS__, 'ajax_filter']);
    add_action('wp_ajax_nopriv_wpwcf_filter', [__CLASS__, 'ajax_filter']);
  }

  public static function render($atts){
    // nuovo parametro: filters="1|0"
    $atts = shortcode_atts([
      'category' => '',
      'filters'  => '1',
    ], $atts, 'wcw_schedule');

    if (WCW_Closures::is_closed_now()) return WCW_Closures::message_html();

    // Preselezione da query string ?attivita=slug
    $qs = isset($_GET['attivita']) ? sanitize_text_field(wp_unslash($_GET['attivita'])) : '';
    $current = $qs !== '' ? $qs : $atts['category'];

    // Mostra/Nascondi barra filtri
    $show_filters = in_array(strtolower((string)$atts['filters']), ['1','true','yes','on'], true);

    // Dati
    $cats = WCW_DB::get_categories();
    $collapse_id = 'wpwcFilters_' . wp_generate_uuid4();

    // Enqueue Bootstrap 5.3 per la collapse (solo qui)
    wp_enqueue_style('wcw-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3');
    wp_enqueue_script('wcw-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], '5.3.3', true);

    ob_start(); ?>
    <div class="wpwc-wrap">

      <?php if ($show_filters): ?>
        <!-- Toggle visibile solo <1024px -->
        <button class="btn btn-outline-secondary wpwc-filter-toggle" type="button"
                data-bs-toggle="collapse" data-bs-target="#<?php echo esc_attr($collapse_id); ?>"
                aria-expanded="false" aria-controls="<?php echo esc_attr($collapse_id); ?>">
          Filtri attività
        </button>

        <!-- Collapse: sotto 1024px collassata, sopra 1024px sempre visibile via CSS -->
        <div class="collapse wpwc-collapse" id="<?php echo esc_attr($collapse_id); ?>">
          <div class="wpwc-toolbar" role="tablist" aria-label="Filtra per attività">
            <a class="wpwc-chip<?php echo $current==='' ? ' is-active' : ''; ?>" href="#" data-wpwc-cat="">
              <span class="dot" style="background:#999"></span>
              Tutte le attività
            </a>
            <?php foreach ($cats as $c): ?>
              <a class="wpwc-chip<?php echo $current===$c->slug ? ' is-active' : ''; ?>" href="#" data-wpwc-cat="<?php echo esc_attr($c->slug); ?>">
                <span class="dot" style="background:#777777"></span>
                <?php echo esc_html($c->name); ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div id="wpwc-grid">
        <?php echo self::render_grid_html($current); ?>
      </div>

    </div>

    <script>
    (function(){
      const wrap = document.currentScript.closest('.wpwc-wrap');
      if (!wrap) return;
      const grid = wrap.querySelector('#wpwc-grid');
      const chips = wrap.querySelectorAll('.wpwc-chip'); // se filters="0" => lista vuota
      const ajaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";

      function setActive(el){ chips.forEach(c => c.classList.remove('is-active')); el.classList.add('is-active'); }
      function updateURL(slug){
        const url = new URL(window.location);
        if (slug) url.searchParams.set('attivita', slug);
        else url.searchParams.delete('attivita');
        window.history.replaceState({}, '', url);
      }
      async function fetchGrid(slug){
        const fd = new FormData();
        fd.append('action','wpwcf_filter');
        fd.append('category', slug);
        const res = await fetch(ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' });
        if (!res.ok) return;
        grid.innerHTML = await res.text();
      }

      if (chips.length){
        chips.forEach(ch => ch.addEventListener('click', function(e){
          e.preventDefault();
          const slug = this.getAttribute('data-wpwc-cat') || '';
          setActive(this);
          updateURL(slug);
          fetchGrid(slug);
        }));
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }

  // HTML griglia
  private static function render_grid_html($category_slug = ''){
    $by = [1=>[],2=>[],3=>[],4=>[],5=>[],6=>[],7=>[]];
    $rows = WCW_DB::get_events($category_slug);

    foreach ($rows as $r) {
      $d = (int)$r->weekday; if ($d<1 || $d>7) continue; $by[$d][] = $r;
    }
    foreach ($by as $d=>&$items) { usort($items, fn($a,$b)=>strcmp($a->time,$b->time)); }
    unset($items);

    $labels = [1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì',5=>'Venerdì',6=>'Sabato',7=>'Domenica'];

    ob_start(); ?>
    <div class="wpwc-grid">
      <div class="wpwc-head">
        <?php for ($d=1; $d<=7; $d++): ?>
          <div class="wpwc-day"><?php echo esc_html($labels[$d]); ?></div>
        <?php endfor; ?>
      </div>
      <div class="wpwc-cols">
        <?php for ($d=1; $d<=7; $d++): ?>
          <div class="wpwc-cell" data-day="<?php echo (int)$d; ?>">
            <?php if (empty($by[$d])): ?>
            <?php else: foreach ($by[$d] as $ev): ?>
              <div class="wpwc-event" data-cat="<?php echo esc_attr($ev->category_slug ?: ''); ?>">
                <div class="title"><?php echo esc_html($ev->name); ?></div>
                <div class="meta">
                  <?php echo esc_html(substr($ev->time,0,5)); ?>
                  <?php if (!empty($ev->category_name)): ?>
                    • <a href="<?php echo esc_url( home_url('/attivita/' . ($ev->category_slug ?? '')) ); ?>"><?php echo esc_html($ev->category_name); ?></a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
    <?php return ob_get_clean();
  }

  // AJAX: ritorna SOLO l'HTML della griglia
  public static function ajax_filter(){
    $slug = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
    echo self::render_grid_html($slug);
    wp_die();
  }
}
endif;
