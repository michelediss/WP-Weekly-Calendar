<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
  delete_option('wcw_closure_enabled');
  delete_option('wcw_closure_start');
  delete_option('wcw_closure_end');
  delete_option('wcw_closure_message');
  delete_option('wcw_visible_days');
}
