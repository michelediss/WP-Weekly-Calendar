<?php
if (!class_exists('WCW_Shortcode')):
class WCW_Shortcode {

  public static function init(){
    add_shortcode('wcw_schedule', [__CLASS__, 'render']);
    add_action('wp_ajax_wpwcf_filter', [__CLASS__, 'ajax_filter']);
    add_action('wp_ajax_nopriv_wpwcf_filter', [__CLASS__, 'ajax_filter']);
  }

  public static function render($atts){
    $atts = shortcode_atts(['category' => ''], $atts, 'wcw_schedule');
    if (WCW_Closures::is_closed_now()) return WCW_Closures::message_html();

    $qs = isset($_GET['attivita']) ? sanitize_text_field(wp_unslash($_GET['attivita'])) : '';
    $current = $qs !== '' ? $qs : $atts['category'];

    $cats = WCW_DB::get_categories();
    $uid  = 'wpwc_' . wp_generate_uuid4();

    ob_start(); ?>
    <div class="wpwc-wrap" id="<?php echo esc_attr($uid); ?>">

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

      <!-- Contenitore con fade -->
      <div id="wpwc-grid" class="wpwc-fade">
        <?php echo self::render_grid_html($current); ?>
      </div>

      <script>
      (function(){
        const wrap = document.getElementById('<?php echo esc_js($uid); ?>');
        if (!wrap) return;

        const grid  = wrap.querySelector('#wpwc-grid');
        const chips = wrap.querySelectorAll('.wpwc-chip');
        const ajaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";

        function setActive(el){
          chips.forEach(c => c.classList.remove('is-active'));
          el.classList.add('is-active');
        }
        function updateURL(slug){
          const url = new URL(window.location.href);
          if (slug) url.searchParams.set('attivita', slug);
          else url.searchParams.delete('attivita');
          window.history.replaceState({}, '', url);
        }

        function fadeOut(el){
          el.classList.add('is-out');
          return new Promise(r => {
            const onEnd = () => { el.removeEventListener('transitionend', onEnd); r(); };
            // fallback nel caso non scatti transitionend
            setTimeout(onEnd, 300);
            requestAnimationFrame(() => {}); // kick layout
          });
        }
        function fadeIn(el){
          requestAnimationFrame(() => el.classList.remove('is-out'));
        }

        async function fetchGrid(slug){
          await fadeOut(grid);
          const fd = new FormData();
          fd.append('action','wpwcf_filter');
          fd.append('category', slug);
          const res = await fetch(ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' });
          if (res.ok) {
            grid.innerHTML = await res.text();
          }
          fadeIn(grid);
        }

        chips.forEach(ch => ch.addEventListener('click', function(e){
          e.preventDefault();
          const slug = this.getAttribute('data-wpwc-cat') || '';
          setActive(this);
          updateURL(slug);
          fetchGrid(slug);
        }));
      })();
      </script>
    </div>
    <?php
    return ob_get_clean();
  }

  // Mostra SEMPRE i giorni selezionati (anche se vuoti)
  private static function render_grid_html($category_slug = ''){
    $labels = [1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì',5=>'Venerdì',6=>'Sabato',7=>'Domenica'];

    // Giorni visibili da opzione. Se vuota -> tutti i 7 giorni
    $visible = get_option('wcw_visible_days', []);
    if (!is_array($visible) || empty($visible)) $visible = [1,2,3,4,5,6,7];
    $visible = array_values(array_intersect([1,2,3,4,5,6,7], array_map('intval',$visible)));
    if (empty($visible)) $visible = [1,2,3,4,5,6,7];

    $by = [1=>[],2=>[],3=>[],4=>[],5=>[],6=>[],7=>[]];
    $rows = WCW_DB::get_events($category_slug);

    foreach ($rows as $r) {
      $d = (int)$r->weekday; if ($d<1 || $d>7) continue; $by[$d][] = $r;
    }
    foreach ($by as $d=>&$items) { usort($items, fn($a,$b)=>strcmp($a->time,$b->time)); }
    unset($items);

    ob_start(); ?>
    <div class="wpwc-grid">
      <div class="wpwc-head" style="grid-template-columns:repeat(<?php echo count($visible); ?>,minmax(0,1fr))">
        <?php foreach ($visible as $d): ?>
          <div class="wpwc-day"><?php echo esc_html($labels[$d]); ?></div>
        <?php endforeach; ?>
      </div>
      <div class="wpwc-cols" style="grid-template-columns:repeat(<?php echo count($visible); ?>,minmax(0,1fr))">
        <?php foreach ($visible as $d): ?>
          <div class="wpwc-cell" data-day="<?php echo (int)$d; ?>">
            <?php foreach ($by[$d] as $ev): ?>
              <div class="wpwc-event" data-cat="<?php echo esc_attr($ev->category_slug ?: ''); ?>">
                <div class="title"><?php echo esc_html($ev->name); ?></div>
                <div class="meta">
                  <?php echo esc_html(substr($ev->time,0,5)); ?>
                  <?php if (!empty($ev->category_name)): ?>
                    • <a href="<?php echo esc_url( home_url('/attivita/' . ($ev->category_slug ?? '')) ); ?>">
                      <?php echo esc_html($ev->category_name); ?>
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <!-- se vuoto, lascia la colonna vuota -->
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php return ob_get_clean();
  }

  public static function ajax_filter(){
    $slug = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
    echo self::render_grid_html($slug);
    wp_die();
  }
}
endif;
