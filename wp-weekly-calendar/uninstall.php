<?php
// Mantiene i dati. Pulisce solo le opzioni.
if (defined('WP_UNINSTALL_PLUGIN')) {
  delete_option('wcw_closure_enabled');
  delete_option('wcw_closure_start');
  delete_option('wcw_closure_end');
  delete_option('wcw_closure_message');
}
