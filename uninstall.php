<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('wp_email_blocker_list');
?>
