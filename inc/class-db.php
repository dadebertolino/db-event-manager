<?php
if (!defined('ABSPATH')) exit;

class DBEM_DB {

    public static function activate() {
        self::create_tables();
        self::maybe_upgrade();
        self::create_upload_dir();
        // Schedule cron
        if (!wp_next_scheduled('dbem_cron_check_events')) {
            wp_schedule_event(time(), 'hourly', 'dbem_cron_check_events');
        }
        flush_rewrite_rules();
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $registrations_table = $wpdb->prefix . 'dbem_registrations';
        $survey_table = $wpdb->prefix . 'dbem_survey_responses';

        $sql_registrations = "CREATE TABLE $registrations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            data longtext NOT NULL,
            email varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            token varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'confirmed',
            checked_in_at datetime DEFAULT NULL,
            assigned_time varchar(50) DEFAULT '',
            registered_at datetime NOT NULL,
            ip_address varchar(45) DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY event_id (event_id),
            KEY email (email),
            KEY status (status)
        ) $charset;";

        $sql_survey = "CREATE TABLE $survey_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            registration_id bigint(20) unsigned NOT NULL,
            data longtext NOT NULL,
            submitted_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY registration_id (registration_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_registrations);
        dbDelta($sql_survey);
    }

    public static function create_upload_dir() {
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/dbem/qrcodes';
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
            // Proteggi directory
            file_put_contents($qr_dir . '/.htaccess', 'Options -Indexes');
        }
    }

    /**
     * Aggiorna schema DB per nuove colonne (safe per esecuzioni multiple)
     */
    public static function maybe_upgrade() {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'assigned_time'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN assigned_time varchar(50) DEFAULT '' AFTER checked_in_at");
        }
    }

    public static function ensure_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            self::create_tables();
        }
        self::maybe_upgrade();
    }

    /**
     * Conta iscritti per evento
     */
    public static function count_registrations($event_id, $status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status = %s",
                $event_id, $status
            ));
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status != 'cancelled'",
            $event_id
        ));
    }

    /**
     * Ottieni iscrizione per token
     */
    public static function get_registration_by_token($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token = %s",
            $token
        ));
    }

    /**
     * Ottieni iscrizioni per evento
     */
    public static function get_registrations($event_id, $status = null, $orderby = 'registered_at', $order = 'DESC') {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $allowed_orderby = array('registered_at', 'name', 'email', 'status', 'checked_in_at', 'assigned_time');
        $orderby = in_array($orderby, $allowed_orderby) ? $orderby : 'registered_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE event_id = %d AND status = %s ORDER BY $orderby $order",
                $event_id, $status
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE event_id = %d ORDER BY $orderby $order",
            $event_id
        ));
    }

    /**
     * Cerca iscrizioni per nome/email/token (per evento specifico)
     */
    public static function search_registrations($event_id, $search) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $like = '%' . $wpdb->esc_like($search) . '%';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE event_id = %d AND (name LIKE %s OR email LIKE %s OR token LIKE %s)",
            $event_id, $like, $like, $like
        ));
    }

    /**
     * Cerca iscrizioni per nome/email su TUTTI gli eventi attivi (per check-in pubblico)
     */
    public static function search_registrations_global($search, $limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $like = '%' . $wpdb->esc_like($search) . '%';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE (name LIKE %s OR email LIKE %s) AND status != 'cancelled' ORDER BY registered_at DESC LIMIT %d",
            $like, $like, $limit
        ));
    }

    /**
     * Controlla se esiste già risposta survey
     */
    public static function has_survey_response($registration_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_survey_responses';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE registration_id = %d",
            $registration_id
        ));
    }

    /**
     * Conta risposte survey per evento
     */
    public static function count_survey_responses($event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_survey_responses';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d",
            $event_id
        ));
    }

    /**
     * Ottieni risposte survey per evento
     */
    public static function get_survey_responses($event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_survey_responses';
        $reg_table = $wpdb->prefix . 'dbem_registrations';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, r.name, r.email FROM $table s
             LEFT JOIN $reg_table r ON s.registration_id = r.id
             WHERE s.event_id = %d ORDER BY s.submitted_at DESC",
            $event_id
        ));
    }

    /**
     * Controlla se email già registrata per evento
     */
    public static function email_exists_for_event($event_id, $email) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d AND email = %s AND status != 'cancelled'",
            $event_id, $email
        ));
    }

    /**
     * Aggiorna orario assegnato a una registrazione
     */
    public static function update_assigned_time($registration_id, $time) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        return $wpdb->update($table,
            array('assigned_time' => sanitize_text_field($time)),
            array('id' => $registration_id), array('%s'), array('%d')
        );
    }

}
