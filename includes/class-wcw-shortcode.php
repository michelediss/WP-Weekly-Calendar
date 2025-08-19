<?php
if (!class_exists('WCW_Shortcode')):
  class WCW_Shortcode
  {

    public static function init()
    {
      add_shortcode('wcw_schedule', [__CLASS__, 'render']);
      add_shortcode('weekly_calendar', [__CLASS__, 'render']); // alias
    }

    public static function render($atts)
    {
      // filters: 1|0
      $atts = shortcode_atts(['category' => '', 'filters' => '1'], $atts, 'wcw_schedule');
      if (WCW_Closures::is_closed_now()) {
        return WCW_Closures::message_html();
      }

      // Parametri e contesto
      $qs = isset($_GET['attivita']) ? sanitize_text_field(wp_unslash($_GET['attivita'])) : '';
      $current = $qs !== '' ? $qs : $atts['category'];
      $show_filters = in_array(strtolower((string) $atts['filters']), ['1', 'true', 'yes', 'on'], true);

      // Siamo dentro una singola pagina del CPT "attivita"?
      $is_attivita_ctx = false;
      if (function_exists('get_queried_object')) {
        $qo = get_queried_object();
        if ($qo instanceof WP_Post && isset($qo->post_type) && $qo->post_type === 'attivita') {
          $is_attivita_ctx = true;
        }
      }

      $uid = 'wpwc_' . wp_generate_uuid4();
      $cats = $show_filters ? WCW_DB::get_filter_categories() : [];
      $rows = WCW_DB::get_events(''); // carico tutto e filtro client-side

      // Bucket per giorno
      $by = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []];
      foreach ($rows as $r) {
        $d = (int) $r->weekday;
        if ($d < 1 || $d > 7)
          continue;
        $by[$d][] = $r;
      }
      foreach ($by as $d => &$items) {
        usort($items, fn($a, $b) => strcmp($a->time, $b->time));
      }
      unset($items);

      // Giorni visibili
      $labels = [1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica'];
      $visible = get_option('wcw_visible_days', []);
      if (!is_array($visible) || empty($visible))
        $visible = [1, 2, 3, 4, 5, 6, 7];
      $visible = array_values(array_intersect([1, 2, 3, 4, 5, 6, 7], array_map('intval', $visible)));
      if (empty($visible))
        $visible = [1, 2, 3, 4, 5, 6, 7];

      ob_start(); ?>
      <div class="wpwc-wrap" id="<?php echo esc_attr($uid); ?>">

        <?php if ($is_attivita_ctx): ?>
          <style>
            /* Nascondi il link alla categoria quando siamo dentro una singola 'attivita' */
            #<?php echo esc_html($uid); ?> .wpwc-hide-catlink {
              display: none !important;
            }
          </style>
        <?php endif; ?>

        <?php if ($show_filters): ?>
          <?php $collapse_id = 'wpwcFilters_' . wp_generate_uuid4(); ?>
          <p class="wpwc-filter-toggle heading text-nero text-lg mb-4" type="button" data-bs-toggle="collapse"
            data-bs-target="#<?php echo esc_attr($collapse_id); ?>" aria-expanded="false"
            aria-controls="<?php echo esc_attr($collapse_id); ?>">
            Filtri attività >
          </p>

          <div class="collapse wpwc-collapse" id="<?php echo esc_attr($collapse_id); ?>">
            <div class="wpwc-toolbar d-flex justify-content-center mb-5" role="tablist" aria-label="Filtra per attività">
              <a class="wpwc-chip d-inline-block button button-hover rounded-pill border-button bg-bianco text-nero px-4 py-2 text-xs text-uppercase heading <?php echo $current === '' ? ' is-active' : ''; ?>"
                href="#" data-wpwc-cat="">
                <span class="dot me-2" style="background:#999"></span>
                Tutte le attività
              </a>
              <?php foreach ($cats as $c):
                $color = sanitize_hex_color($c->color) ?: '#777777';
                ?>
                <a class="wpwc-chip d-inline-block button button-hover rounded-pill border-button bg-bianco text-nero px-4 py-2 text-xs text-uppercase heading<?php echo $current === $c->slug ? ' is-active' : ''; ?>"
                  href="#" data-wpwc-cat="<?php echo esc_attr($c->slug); ?>">
                  <span class="dot me-2" style="background:<?php echo esc_attr($color); ?>"></span>
                  <?php echo esc_html($c->name); ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div id="wpwc-grid" class="wpwc-fade">
          <?php $cols = count($visible); ?>
          <div class="wpwc-grid d-block border-container rounded-4 border-button bg-bianco-puro text-nero px-0 py-0">
            <div class="wpwc-cols" style="--wpwc-cols: repeat(<?php echo (int) $cols; ?>,minmax(0,1fr));">

              <?php foreach ($visible as $d): ?>
                <div class="wpwc-col" data-day="<?php echo (int) $d; ?>">
                  <div class="wpwc-day heading text-nero text-lg py-3 px-2 bg-azzurro-chiaro">
                    <?php echo esc_html($labels[$d]); ?>
                  </div>

                  <div class="wpwc-cell px-2 py-3">
                    <?php foreach ($by[$d] as $ev):
                      $color = sanitize_hex_color($ev->category_color ?? '') ?: '#777777';
                      $link_style = $color ? ' style="border-bottom:2px solid ' . esc_attr($color) . ';"' : '';
                      $hide_cls = $is_attivita_ctx ? ' wpwc-hide-catlink' : '';
                      $start = $ev->time ? substr($ev->time, 0, 5) : '';
                      $end = !empty($ev->time_end) ? substr($ev->time_end, 0, 5) : '';
                      $when = $end ? ($start . ' – ' . $end) : $start;
                      $title_style = ' style="color:' . esc_attr($color) . ';"';
                      ?>
                      <div class="wpwc-event mb-3" data-cat="<?php echo esc_attr($ev->category_slug ?: ''); ?>">
                        <h3 class="title text-base heading" <?php echo $title_style; ?>>
                          <?php echo esc_html($ev->name); ?>
                        </h3>
                        <?php if (!empty($ev->subtitle)): ?>
                          <div class="subtitle text-sm text-grigio"><?php echo esc_html($ev->subtitle); ?></div>
                        <?php endif; ?>
                        <p class="meta paragraph text-sm text-nero text-capitalize">
                          <?php echo esc_html($when); ?>
                          <?php if (!empty($ev->category_name)): ?>
                            <a href="<?php echo esc_url(home_url('/attivita/' . ($ev->category_slug ?? ''))); ?>"
                              class="text-grigio<?php echo esc_attr($hide_cls); ?>" <?php echo $link_style; ?>>
                              • <?php echo esc_html($ev->category_name); ?>
                            </a>
                          <?php endif; ?>
                        </p>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>

            </div>
          </div>
        </div>





        <script>
          (function () {
            const wrap = document.getElementById('<?php echo esc_js($uid); ?>');
            if (!wrap) return;
            const grid = wrap.querySelector('#wpwc-grid');
            const chips = wrap.querySelectorAll('.wpwc-chip');
            const events = wrap.querySelectorAll('.wpwc-event');

            // Fade veloce e fluido
            grid.style.willChange = 'opacity';

            function setActive(el) {
              chips.forEach(c => c.classList.remove('is-active'));
              if (el) el.classList.add('is-active');
            }
            function updateURL(slug) {
              const url = new URL(window.location.href);
              if (slug) url.searchParams.set('attivita', slug);
              else url.searchParams.delete('attivita');
              history.replaceState({}, '', url);
            }
            function applyFilter(slug) {
              grid.classList.add('is-out'); // fade-out
              requestAnimationFrame(() => {
                events.forEach(ev => {
                  const match = !slug || (ev.getAttribute('data-cat') || '') === slug;
                  ev.classList.toggle('is-hidden', !match);
                });
                requestAnimationFrame(() => grid.classList.remove('is-out')); // fade-in
              });
            }

            // Applica filtro da URL o da attributo shortcode
            const initialSlug = '<?php echo esc_js($current); ?>';
            if (initialSlug) {
              const current = Array.from(chips).find(c => (c.getAttribute('data-wpwc-cat') || '') === initialSlug);
              if (current) setActive(current);
              applyFilter(initialSlug);
            }

            chips.forEach(ch => ch.addEventListener('click', function (e) {
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
