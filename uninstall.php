<?php
/**
 * Disinstallazione DB Event Manager
 *
 * I dati vengono rimossi solo se l'amministratore ha attivato l'opzione
 * "Elimina tutti i dati" in Impostazioni. Le opzioni del plugin e i transient
 * di servizio vengono sempre ripuliti.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

require_once __DIR__ . '/inc/class-cpt.php';

/**
 * Pulizia di un singolo sito. Niente current_user_can(): da WP-CLI non c'è un utente
 * e la pulizia verrebbe saltata.
 */
function dbem_uninstall_site() {
    global $wpdb;

    $options = array(
        'dbem_events_page_id',
        'dbem_events_page_title',
        'dbem_checkin_pin',
        'dbem_delete_data_on_uninstall',
        'dbem_caps_version',
    );

    $delete_data = get_option('dbem_delete_data_on_uninstall', '0') === '1';

    // Cron: anche gli invii singoli programmati con l'id dell'evento
    foreach (array('dbem_cron_check_events', 'dbem_send_reminder', 'dbem_send_survey_auto') as $hook) {
        wp_unschedule_hook($hook);
    }

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

        // Categorie evento: la tassonomia non è registrata durante l'uninstall, quindi via SQL
        $term_taxonomy_ids = $wpdb->get_col("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'dbem_category'");
        if ($term_taxonomy_ids) {
            $in = implode(',', array_map('absint', $term_taxonomy_ids));
            $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($in)");
            $wpdb->query("DELETE t FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.term_taxonomy_id IN ($in)");
            $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ($in)");
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

    // Capability dei gestori eventi, su ruoli e singoli utenti
    $capabilities = DBEM_CPT::get_event_capabilities();

    foreach (wp_roles()->role_objects as $role) {
        foreach ($capabilities as $capability) {
            $role->remove_cap($capability);
        }
    }

    $delegates = get_users(array(
        'blog_id'      => get_current_blog_id(),
        'meta_key'     => $wpdb->get_blog_prefix() . 'capabilities',
        'meta_value'   => DBEM_CPT::EVENT_MANAGER_CAP,
        'meta_compare' => 'LIKE',
    ));
    foreach ($delegates as $user) {
        foreach ($capabilities as $capability) {
            $user->remove_cap($capability);
        }
        if (get_user_meta($user->ID, 'dbem_granted_upload_files', true)) {
            $user->remove_cap('upload_files');
        }
    }
    foreach ($options as $option) {
        delete_option($option);
    }
}

if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $site_id) {
        switch_to_blog($site_id);
        dbem_uninstall_site();
        restore_current_blog();
    }
} else {
    dbem_uninstall_site();
}

// Il meta utente è unico per tutta la rete: si cancella dopo aver ripulito ogni sito
delete_metadata('user', 0, 'dbem_granted_upload_files', '', true);
