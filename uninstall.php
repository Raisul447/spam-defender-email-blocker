<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
// Delete the option used by the plugin
delete_option('sdef_email_blocker_list');
?>
