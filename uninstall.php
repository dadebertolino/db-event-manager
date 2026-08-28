<?php
/**
 * Disinstallazione DB Event Manager
 *
 * I dati vengono rimossi solo se l'amministratore ha attivato l'opzione
 * "Elimina tutti i dati" in Impostazioni. Le opzioni del plugin e i transient
 * di servizio vengono sempre ripuliti.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

$options = array(
    'dbem_events_page_id',
    'dbem_events_page_title',
    'dbem_checkin_pin',
    'dbem_delete_data_on_uninstall',
);

$delete_data = get_option('dbem_delete_data_on_uninstall', '0') === '1';

// Cron
wp_clear_scheduled_hook('dbem_cron_check_events');

// Transient di rate limiting e cache updater
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_dbem\\_%'
        OR option_name LIKE '_transient_timeout_dbem\\_%'
        OR option_name LIKE '_transient_dbgu\\_%'
        OR option_name LIKE '_transient_timeout_dbgu\\_%'"
);

if ($delete_data) {
    // Tabelle
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dbem_survey_responses");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dbem_registrations");

    // Eventi e relativi meta
    $events = get_posts(array(
        'post_type'      => 'dbem_event',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));
    foreach ($events as $event_id) {
        wp_delete_post($event_id, true);
    }

    // File QR code
    $upload_dir = wp_upload_dir();
    $qr_dir = trailingslashit($upload_dir['basedir']) . 'dbem/qrcodes';
    if (is_dir($qr_dir)) {
        foreach ((array) glob($qr_dir . '/*') as $file) {
            if (is_file($file)) @unlink($file);
        }
        @rmdir($qr_dir);
        @rmdir(dirname($qr_dir));
    }
}

foreach ($options as $option) {
    delete_option($option);
}
