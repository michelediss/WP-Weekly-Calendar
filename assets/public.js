(function(){
  function computeEmptyDays(){
    var gridRoot = document.getElementById('wpwc-grid') || document;
    var cols = gridRoot.querySelectorAll('.wpwc-cols .wpwc-col');
    var anyVisible = false;
    cols.forEach(function(col){
      var events = col.querySelectorAll('.wpwc-event');
      var hasVisible = false;
      for(var i=0;i<events.length;i++){
        var ev = events[i];
        if(!ev.classList.contains('is-hidden') && ev.offsetParent !== null){
          hasVisible = true; break;
        }
      }
      col.classList.toggle('is-empty', !hasVisible);
      col.setAttribute('aria-hidden', String(!hasVisible));
      if(hasVisible) anyVisible = true;
    });
    gridRoot.classList.toggle('wpwc-all-empty', !anyVisible);
    document.dispatchEvent(new CustomEvent('wpwc:empties-updated'));
  }

  function debounce(fn, wait){
    var t; return function(){ var ctx=this, args=arguments;
      clearTimeout(t); t=setTimeout(function(){ fn.apply(ctx,args); }, wait);
    };
  }

  var recompute = debounce(computeEmptyDays, 16);

  document.addEventListener('DOMContentLoaded', function(){
    computeEmptyDays();

    // Ricalcola dopo interazioni sui filtri (chip/toggle) – compatibile con filtri client-side
    document.addEventListener('click', function(e){
      var el = e.target.closest('.wpwc-chip, [data-wpwc-filter], .wpwc-filter-toggle');
      if(el) setTimeout(recompute, 0);
    }, true);

    // Ricalcola quando altri script annunciano la fine dei filtri
    document.addEventListener('wpwc:filters-applied', recompute);

    // Osserva cambiamenti DOM (aggiunta/rimozione eventi, classi .is-hidden, ecc.)
    var grid = document.querySelector('#wpwc-grid .wpwc-cols') || document.querySelector('.wpwc-cols');
    if(grid && 'MutationObserver' in window){
      var mo = new MutationObserver(function(){
        recompute();
      });
      mo.observe(grid, { attributes:true, attributeFilter:['class','style'], childList:true, subtree:true });
    }

    // Espone una API manuale
    window.wpwcUpdateEmptyDays = computeEmptyDays;
  });
})();