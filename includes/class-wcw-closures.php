<?php
if (!class_exists('WCW_Closures')):
class WCW_Closures {

  /**
   * Se 'wcw_closure_enabled' è attivo:
   * - Se start/end sono valorizzati, chiude solo entro l'intervallo [start, end];
   * - Se mancano start/end, chiude SEMPRE (comportamento richiesto dall'utente).
   */
  public static function is_closed_now(){
    if (!get_option('wcw_closure_enabled', 0)) return false;

    $start = trim((string)get_option('wcw_closure_start', ''));
    $end   = trim((string)get_option('wcw_closure_end', ''));
    if (!$start || !$end) {
      // Nessun intervallo specificato: chiusura sempre attiva
      return true;
    }

    $tz = new DateTimeZone('Europe/Rome');
    $today = new DateTime('today', $tz);

    // Accetta sia Y-m-d che d/m/Y per robustezza
    $s = DateTime::createFromFormat('Y-m-d', $start, $tz) ?: DateTime::createFromFormat('d/m/Y', $start, $tz);
    $e = DateTime::createFromFormat('Y-m-d', $end,   $tz) ?: DateTime::createFromFormat('d/m/Y', $end,   $tz);
    if (!$s || !$e) return true; // se le date sono mal formattate, meglio chiudere per evitare ambiguità

    // Inclusivo
    return $today >= $s && $today <= $e;
  }

  public static function message_html(){
    $tpl = (string)get_option('wcw_closure_message', 'Le attività riprenderanno il giorno {date}');
    $end = trim((string)get_option('wcw_closure_end', ''));
    $tz  = new DateTimeZone('Europe/Rome');

    $months = [1=>'gennaio',2=>'febbraio',3=>'marzo',4=>'aprile',5=>'maggio',6=>'giugno',7=>'luglio',8=>'agosto',9=>'settembre',10=>'ottobre',11=>'novembre',12=>'dicembre'];

    $date_it = '';
    if ($end) {
      $e = DateTime::createFromFormat('Y-m-d', $end, $tz) ?: DateTime::createFromFormat('d/m/Y', $end, $tz);
      if ($e) {
        $date_it = intval($e->format('j')) . ' ' . $months[intval($e->format('n'))] . ' ' . $e->format('Y');
      }
    }

    $msg = $date_it ? str_replace('{date}', $date_it, $tpl) : ( $tpl === 'Le attività riprenderanno il giorno {date}' ? 'Siamo chiusi.' : str_replace('{date}', '', $tpl) );
    return '<p class="wcw-closure-message paragraph bold text-base text-center">'.esc_html($msg).'</p>';
  }
}
endif;
