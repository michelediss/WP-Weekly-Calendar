<?php
if (!class_exists('WCW_Shortcode')):
class WCW_Shortcode {

  public static function init(){
    add_shortcode('wcw_schedule', [__CLASS__, 'render']);
    add_shortcode('weekly_calendar', [__CLASS__, 'render']); // alias
  }

  public static function render($atts){
    // filters: 1|0
    $atts = shortcode_atts(['category' => '', 'filters' => '1'], $atts, 'wcw_schedule');
    if (WCW_Closures::is_closed_now()) return WCW_Closures::message_html();

    $qs      = isset($_GET['attivita']) ? sanitize_text_field(wp_unslash($_GET['attivita'])) : '';
    $current = $qs !== '' ? $qs : $atts['category'];
    $show_filters = in_array(strtolower((string)$atts['filters']), ['1','true','yes','on'], true);

    $uid  = 'wpwc_' . wp_generate_uuid4();
    $cats = $show_filters ? WCW_DB::get_filter_categories() : [];
    $rows = WCW_DB::get_events(''); // carico tutto e filtro client-side

    // Bootstrap solo se mostro filtri
    if ($show_filters) {
      wp_enqueue_style('wcw-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3');
      wp_enqueue_script('wcw-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], '5.3.3', true);
    }

    // Bucket per giorno
    $by = [1=>[],2=>[],3=>[],4=>[],5=>[],6=>[],7=>[]];
    foreach ($rows as $r) {
      $d = (int)$r->weekday; if ($d<1 || $d>7) continue;
      $by[$d][] = $r;
    }
    foreach ($by as $d=>&$items) usort($items, fn($a,$b)=>strcmp($a->time,$b->time));
    unset($items);

    // Giorni visibili
    $labels  = [1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì',5=>'Venerdì',6=>'Sabato',7=>'Domenica'];
    $visible = get_option('wcw_visible_days', []);
    if (!is_array($visible) || empty($visible)) $visible = [1,2,3,4,5,6,7];
    $visible = array_values(array_intersect([1,2,3,4,5,6,7], array_map('intval',$visible)));
    if (empty($visible)) $visible = [1,2,3,4,5,6,7];

    ob_start(); ?>
    <div class="wpwc-wrap" id="<?php echo esc_attr($uid); ?>">

      <?php if ($show_filters): ?>
        <?php $collapse_id = 'wpwcFilters_' . wp_generate_uuid4(); ?>
        <button class="btn btn-outline-secondary wpwc-filter-toggle" type="button"
                data-bs-toggle="collapse" data-bs-target="#<?php echo esc_attr($collapse_id); ?>"
                aria-expanded="false" aria-controls="<?php echo esc_attr($collapse_id); ?>">
          Filtri attività
        </button>

        <div class="collapse wpwc-collapse" id="<?php echo esc_attr($collapse_id); ?>">
          <div class="wpwc-toolbar" role="tablist" aria-label="Filtra per attività">
            <a class="wpwc-chip<?php echo $current==='' ? ' is-active' : ''; ?>" href="#" data-wpwc-cat="">
              <span class="dot" style="background:#999"></span>
              Tutte le attività
            </a>
            <?php foreach ($cats as $c):
              $color = sanitize_hex_color($c->color) ?: '#777777';
            ?>
              <a class="wpwc-chip<?php echo $current===$c->slug ? ' is-active' : ''; ?>" href="#" data-wpwc-cat="<?php echo esc_attr($c->slug); ?>">
                <span class="dot" style="background:<?php echo esc_attr($color); ?>"></span>
                <?php echo esc_html($c->name); ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div id="wpwc-grid" class="wpwc-fade">
        <?php
          $cols = count($visible);
        ?>
        <div class="wpwc-grid">
          <div class="wpwc-head" style="grid-template-columns:repeat(<?php echo (int)$cols; ?>,minmax(0,1fr))">
            <?php foreach ($visible as $d): ?>
              <div class="wpwc-day"><?php echo esc_html($labels[$d]); ?></div>
            <?php endforeach; ?>
          </div>
          <div class="wpwc-cols" style="grid-template-columns:repeat(<?php echo (int)$cols; ?>,minmax(0,1fr))">
            <?php foreach ($visible as $d): ?>
              <div class="wpwc-cell" data-day="<?php echo (int)$d; ?>">
                <?php foreach ($by[$d] as $ev):
                  $color = sanitize_hex_color($ev->category_color ?? '') ?: '#777777';
                  $bg    = (strlen($color)===7) ? $color.'1A' : '#0000000D';
                ?>
                  <div class="wpwc-event"
                       data-cat="<?php echo esc_attr($ev->category_slug ?: ''); ?>"
                       style="border-left:6px solid <?php echo esc_attr($color); ?>;background:linear-gradient(0deg,<?php echo esc_attr($bg); ?>,<?php echo esc_attr($bg); ?>),#fff">
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
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <script>
      (function(){
        const wrap  = document.getElementById('<?php echo esc_js($uid); ?>');
        if (!wrap) return;
        const grid  = wrap.querySelector('#wpwc-grid');
        const chips = wrap.querySelectorAll('.wpwc-chip');
        const events= wrap.querySelectorAll('.wpwc-event');

        // Fade veloce e fluido
        grid.style.willChange = 'opacity';

        // Mostra solo filtri con eventi: già lato PHP, qui niente

        function setActive(el){ chips.forEach(c => c.classList.remove('is-active')); el.classList.add('is-active'); }
        function updateURL(slug){
          const url = new URL(window.location.href);
          if (slug) url.searchParams.set('attivita', slug);
          else url.searchParams.delete('attivita');
          history.replaceState({}, '', url);
        }
        function applyFilter(slug){
          grid.classList.add('is-out');               // fade-out 80ms
          requestAnimationFrame(() => {
            events.forEach(ev => {
              const match = !slug || ev.dataset.cat === slug;
              ev.classList.toggle('is-hidden', !match);
            });
            requestAnimationFrame(() => grid.classList.remove('is-out')); // fade-in
          });
        }

        // Applica filtro da URL o da attributo shortcode
        const initialSlug = '<?php echo esc_js($current); ?>';
        if (initialSlug) {
          const current = Array.from(chips).find(c => (c.getAttribute('data-wpwc-cat')||'') === initialSlug);
          if (current) setActive(current);
          applyFilter(initialSlug);
        }

        chips.forEach(ch => ch.addEventListener('click', function(e){
          e.preventDefault();
          const slug = this.getAttribute('data-wpwc-cat') || '';
          setActive(this);
          updateURL(slug);
          applyFilter(slug);
        }));
      })();
      </script>
    </div>
    <?php
    return ob_get_clean();
  }
}
endif;
