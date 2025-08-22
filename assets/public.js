/* WP Weekly Calendar - no URL sync + hide empty columns + tablet cols auto(1/2) */
(function (w, d) {
  const initWrap = (wrap) => {
    if (!wrap || wrap.dataset.wcInitialized) return;
    wrap.dataset.wcInitialized = '1';

    const grid = wrap.querySelector('#wpwc-grid, .wpwc-grid');
    if (!grid) return;

    const chips  = wrap.querySelectorAll('.wpwc-chip');
    const events = wrap.querySelectorAll('.wpwc-event');
    const cols   = wrap.querySelectorAll('.wpwc-col');
    const row    = wrap.querySelector('.wpwc-row');
    grid.style.willChange = 'opacity';

    const isTablet = () => w.matchMedia('(min-width:768px) and (max-width:1023.98px)').matches;
    const visibleColsCount = () => wrap.querySelectorAll('.wpwc-col:not(.d-none)').length;

    // ⬇️ imposta dinamicamente 1 o 2 colonne su tablet, rimuove lo style fuori da tablet
    const setTabletColumns = () => {
      if (!row) return;
      if (isTablet()) {
        const n = Math.min(2, Math.max(1, visibleColsCount())); // clamp a [1,2]
        row.style.gridTemplateColumns = `repeat(${n}, var(--wpwc-colw-md))`;
      } else {
        row.style.removeProperty('grid-template-columns');
      }
    };

    const setActive = (el) => chips.forEach(c => c.classList.toggle('is-active', c === el));

    // ⬇️ aggiorna la visibilità delle colonne in base agli eventi visibili
    const updateColumnsVisibility = () => {
      cols.forEach(col => {
        const hasVisibleEvent = !!col.querySelector('.wpwc-cell .wpwc-event:not(.is-hidden)');
        col.classList.toggle('d-none', !hasVisibleEvent);
      });
      setTabletColumns(); // aggiorna il numero di tracce su tablet
    };

    const applyFilter = (slug) => {
      grid.classList.add('is-out');
      requestAnimationFrame(() => {
        const s = slug || '';
        events.forEach(ev => {
          ev.classList.toggle('is-hidden', s && (ev.getAttribute('data-cat') || '') !== s);
        });
        updateColumnsVisibility();
        requestAnimationFrame(() => grid.classList.remove('is-out'));
      });
    };

    // stato iniziale
    const initial = wrap.dataset.initialSlug || '';
    if (initial) {
      const current = Array.from(chips).find(c => (c.getAttribute('data-wpwc-cat') || '') === initial);
      if (current) setActive(current);
      applyFilter(initial);
    } else {
      updateColumnsVisibility(); // nasconde colonne vuote e setta le tracce su tablet
    }

    // delega click sui chip (senza aggiornare l'URL)
    wrap.addEventListener('click', (e) => {
      const chip = e.target.closest('.wpwc-chip');
      if (!chip || !wrap.contains(chip)) return;
      e.preventDefault();
      const slug = chip.getAttribute('data-wpwc-cat') || '';
      setActive(chip);
      applyFilter(slug);
    });

    // aggiorna dinamicamente su resize/orientamento
    let raf;
    const onResize = () => {
      if (raf) return;
      raf = w.requestAnimationFrame(() => {
        raf = null;
        setTabletColumns();
      });
    };
    w.addEventListener('resize', onResize, { passive: true });
    w.addEventListener('orientationchange', onResize);
  };

  const init = (root) => (root || d).querySelectorAll('.wpwc-wrap').forEach(initWrap);
  d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', () => init()) : init();
  w.WPWC = w.WPWC || {}; w.WPWC.init = init;
})(window, document);
