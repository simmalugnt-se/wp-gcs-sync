<?php

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('gcs_sync_options');

// Clean up post meta data
global $wpdb;
$wpdb->delete($wpdb->postmeta, array(
    'meta_key' => 'gcs_synced'
));
$wpdb->delete($wpdb->postmeta, array(
    'meta_key' => 'gcs_url'
));
$wpdb->delete($wpdb->postmeta, array(
    'meta_key' => 'gcs_urls'
));

// Clean up any transients
delete_transient('gcs_sync_activated');
