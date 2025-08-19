<?php
if (!class_exists('WCW_Closures')):
class WCW_Closures {
  public static function is_closed_now(){
    if (!get_option('wcw_closure_enabled', 0)) return false;
    $start = get_option('wcw_closure_start', '');
    $end   = get_option('wcw_closure_end', '');
    if (!$start || !$end) return false;
    $tz = new DateTimeZone('Europe/Rome');
    $today = new DateTime('today', $tz);
    $s = DateTime::createFromFormat('Y-m-d', $start, $tz);
    $e = DateTime::createFromFormat('Y-m-d', $end, $tz);
    if (!$s || !$e) return false;
    return $today >= $s && $today <= $e;
  }
  public static function message_html(){
    $end = get_option('wcw_closure_end', '');
    $tpl = get_option('wcw_closure_message', 'Le attività riprenderanno il giorno {date}');
    if (!$end) return '';
    $tz = new DateTimeZone('Europe/Rome');
    $e  = DateTime::createFromFormat('Y-m-d', $end, $tz);
    $months = [1=>'gennaio',2=>'febbraio',3=>'marzo',4=>'aprile',5=>'maggio',6=>'giugno',7=>'luglio',8=>'agosto',9=>'settembre',10=>'ottobre',11=>'novembre',12=>'dicembre'];
    $date_it = intval($e->format('j')) . ' ' . $months[intval($e->format('n'))] . ' ' . $e->format('Y');
    return '<h2 class="wcw-closure-message paragraph text-xl d-inline-block text-nero px-2">'.esc_html(str_replace('{date}', $date_it, $tpl)).'</h2>';
  }
}
endif;
