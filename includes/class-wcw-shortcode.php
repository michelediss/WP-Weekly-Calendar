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
      if (class_exists('WCW_Closures') && WCW_Closures::is_closed_now()) {
        return WCW_Closures::message_html();
      }

      // Parametri e contesto
      $qs = isset($_GET['attivita']) ? sanitize_text_field(wp_unslash($_GET['attivita'])) : '';
      $current = ($qs !== '') ? $qs : $atts['category'];
      $show_filters = in_array(strtolower((string) $atts['filters']), ['1', 'true', 'yes', 'on'], true);

      // Siamo dentro una singola pagina del CPT "attivita"?
      $is_attivita_ctx = function_exists('is_singular') && is_singular('attivita');

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

      // SOLO switch dell'allineamento (niente d-flex qui)
      $grid_outer_classes = 'wpwc-fade d-flex ' . ($is_attivita_ctx ? 'justify-content-start' : 'justify-content-center');

      ob_start(); ?>
      <div class="wpwc-wrap" id="<?php echo esc_attr($uid); ?>" data-initial-slug="<?php echo esc_attr($current); ?>">

        <?php if ($show_filters): ?>
          <?php
          // chip riutilizzabili
          ob_start(); ?>
          <a class="wpwc-chip d-inline-block button button-hover rounded-pill border-button bg-bianco-puro text-nero px-4 py-2 text-xs text-uppercase heading <?php echo $current === '' ? ' is-active' : ''; ?>"
            href="#" data-wpwc-cat="" data-bs-dismiss="offcanvas">
            <span class="dot me-2 bg-grigio"></span>
            Tutte le attività
          </a>
          <?php foreach ($cats as $c):
            $color = sanitize_hex_color($c->color) ?: '#777777'; ?>
            <a class="wpwc-chip d-inline-block button button-hover rounded-pill border-button bg-bianco-puro text-nero px-4 py-2 text-xs text-uppercase heading<?php echo $current === $c->slug ? ' is-active' : ''; ?>"
              href="#" data-wpwc-cat="<?php echo esc_attr($c->slug); ?>"
              data-bs-dismiss="offcanvas">
              <span class="dot me-2" style="background:<?php echo esc_attr($color); ?>"></span>
              <?php echo esc_html($c->name); ?>
            </a>
          <?php endforeach;
          $chips_html = ob_get_clean();
          ?>

          <!-- Offcanvas: SOLO tablet/mobile (ID hardcoded) -->
          <div class="offcanvas offcanvas-start d-lg-none bg-azzurro-chiaro border-container rounded-end-4 align-self-center" tabindex="-1" id="wpwcOffcanvas"
            aria-labelledby="wpwcOffcanvas_label">
            <div class="offcanvas-header position-absolute right-0 top-0 w-100">
              <h5 class="offcanvas-title visually-hidden" id="wpwcOffcanvas_label">Filtri attività</h5>
              <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Chiudi"></button>
            </div>
            <div class="offcanvas-body">
              <div class="wpwc-toolbar d-flex flex-wrap justify-content-start gap-2" role="tablist"
                aria-label="Filtra per attività">
                <?php echo $chips_html; ?>
              </div>
            </div>
          </div>

          <!-- Toolbar inline: SOLO desktop -->
          <div class="wpwc-toolbar d-none d-lg-flex justify-content-center mb-5" role="tablist"
            aria-label="Filtra per attività">
            <?php echo $chips_html; ?>
          </div>
        <?php endif; ?>


        <div id="wpwc-grid" class="<?php echo esc_attr($grid_outer_classes); ?>">
          <div class="wpwc-grid d-inline-block border-container rounded-4 border-button bg-bianco-puro text-nero px-0 py-0">
            <!-- ⬇️ Container GRID -->
            <div class="wpwc-row">
              <?php foreach ($visible as $d): ?>
                <!-- ⬇️ Ciascuna colonna/giorno è un item Grid -->
                <div class="wpwc-col px-0 text-center h-100" data-day="<?php echo (int) $d; ?>">
                  <div class="wpwc-day heading text-nero text-lg py-3 px-2 bg-azzurro-chiaro">
                    <?php echo esc_html($labels[$d]); ?>
                  </div>

                  <div class="wpwc-cell px-2 py-3">
                    <?php foreach ($by[$d] as $ev):
                      $color = sanitize_hex_color($ev->category_color ?? '') ?: '#777777';
                      $link_style = $color ? ' style="border-bottom:2px solid ' . esc_attr($color) . ';"' : '';
                      $start = $ev->time ? substr($ev->time, 0, 5) : '';
                      $end = !empty($ev->time_end) ? substr($ev->time_end, 0, 5) : '';
                      $when = $end ? ($start . ' – ' . $end) : $start;
                      $title_style = ' style="color:' . esc_attr($color) . ';"';
                      ?>
                      <div class="wpwc-event mb-4" data-cat="<?php echo esc_attr($ev->category_slug ?: ''); ?>">
                        <h3 class="title text-base heading" <?php echo $title_style; ?>>
                          <?php echo esc_html($ev->name); ?>
                        </h3>
                        <?php if (!empty($ev->subtitle)): ?>
                          <div class="paragraph text-sm text-grigio my-1"><?php echo esc_html($ev->subtitle); ?></div>
                        <?php endif; ?>
                        <p class="meta paragraph text-sm text-nero text-capitalize">
                          <span class="bold"><?php echo esc_html($when); ?></span>
                          <?php if (!empty($ev->category_name) && !$is_attivita_ctx): // niente link nel single 'attivita' ?>
                            <a href="<?php echo esc_url(home_url('/attivita/' . ($ev->category_slug ?? ''))); ?>"
                              class="text-grigio" <?php echo $link_style; ?>>
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
            <!-- /wpwc-row -->
          </div>
        </div>

      </div>
      <?php
      return ob_get_clean();
    }
  }
endif;
