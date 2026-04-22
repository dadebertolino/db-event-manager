<?php
if (!defined('ABSPATH')) exit;

class DBEM_Checkin {

    /**
     * Pagina admin check-in
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Accesso negato', 'db-event-manager'));
        }
        include DBEM_PLUGIN_DIR . 'templates/admin/checkin.php';
    }

    /**
     * Check-in via AJAX (admin)
     */
    public static function handle_checkin() {
        check_ajax_referer('dbem_checkin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Accesso negato', 'db-event-manager'));

        $token = sanitize_text_field($_POST['token'] ?? '');
        if (empty($token)) wp_send_json_error(__('Token mancante', 'db-event-manager'));

        DBEM_DB::ensure_tables();
        $reg = DBEM_DB::get_registration_by_token($token);
        if (!$reg) {
            wp_send_json_error(array(
                'status'  => 'invalid',
                'message' => __('QR code non valido', 'db-event-manager'),
                'icon'    => '❌',
            ));
        }

        $event_title = DBEM_CPT::get_event_name($reg->event_id);

        switch ($reg->status) {
            case 'confirmed':
                global $wpdb;
                $table = $wpdb->prefix . 'dbem_registrations';
                $now = current_time('mysql');
                $wpdb->update($table,
                    array('status' => 'checked_in', 'checked_in_at' => $now),
                    array('id' => $reg->id),
                    array('%s', '%s'),
                    array('%d')
                );
                wp_send_json_success(array(
                    'status'  => 'checked_in',
                    'message' => sprintf(__('Check-in effettuato per %s', 'db-event-manager'), $reg->name),
                    'name'    => $reg->name,
                    'email'   => $reg->email,
                    'event'   => $event_title,
                    'time'    => wp_date('H:i', strtotime($now)),
                    'icon'    => '✅',
                ));
                break;

            case 'checked_in':
                $time = $reg->checked_in_at ? wp_date('H:i', strtotime($reg->checked_in_at)) : '—';
                wp_send_json_success(array(
                    'status'  => 'already',
                    'message' => sprintf(__('Già registrato alle %s', 'db-event-manager'), $time),
                    'name'    => $reg->name,
                    'email'   => $reg->email,
                    'event'   => $event_title,
                    'time'    => $time,
                    'icon'    => '⚠️',
                ));
                break;

            case 'cancelled':
                wp_send_json_success(array(
                    'status'  => 'cancelled',
                    'message' => __('Iscrizione annullata', 'db-event-manager'),
                    'name'    => $reg->name,
                    'event'   => $event_title,
                    'icon'    => '❌',
                ));
                break;
        }
    }

    /**
     * Ricerca partecipanti AJAX
     */
    public static function handle_search() {
        check_ajax_referer('dbem_checkin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Accesso negato', 'db-event-manager'));

        $event_id = absint($_POST['event_id'] ?? 0);
        $search = sanitize_text_field($_POST['search'] ?? '');
        if (!$event_id || empty($search)) wp_send_json_error(__('Parametri mancanti', 'db-event-manager'));

        DBEM_DB::ensure_tables();
        $results = DBEM_DB::search_registrations($event_id, $search);

        $items = array();
        foreach ($results as $r) {
            $items[] = array(
                'id'     => $r->id,
                'name'   => $r->name,
                'email'  => $r->email,
                'token'  => $r->token,
                'status' => $r->status,
                'time'   => $r->checked_in_at ? wp_date('H:i', strtotime($r->checked_in_at)) : '',
            );
        }

        wp_send_json_success($items);
    }

    /**
     * Gestione check-in da URL frontend (QR scan diretto)
     */
    public static function handle_frontend_checkin($token) {
        // Redirect alla pagina check-in pubblica con token
        wp_redirect(home_url('/?dbem_checkin_page=1&token=' . urlencode($token)));
        exit;
    }

    /**
     * Pagina pubblica check-in (da telefono, senza login WP)
     */
    public static function render_public_page() {
        include DBEM_PLUGIN_DIR . 'templates/frontend/checkin.php';
        exit;
    }

    /**
     * Check-in AJAX pubblico (protetto da PIN)
     */
    public static function handle_public_checkin() {
        // Verifica PIN
        $pin_stored = get_option('dbem_checkin_pin', '');
        if ($pin_stored) {
            $pin_sent = sanitize_text_field($_POST['pin'] ?? '');
            if ($pin_sent !== $pin_stored) {
                wp_send_json_error(array('message' => __('PIN non valido', 'db-event-manager'), 'status' => 'pin_error'));
            }
        }

        $token = sanitize_text_field($_POST['token'] ?? '');
        if (empty($token)) wp_send_json_error(array('message' => __('Token mancante', 'db-event-manager'), 'status' => 'invalid'));

        DBEM_DB::ensure_tables();
        $reg = DBEM_DB::get_registration_by_token($token);
        if (!$reg) {
            wp_send_json_error(array(
                'status'  => 'invalid',
                'message' => __('QR code non valido', 'db-event-manager'),
                'icon'    => '❌',
            ));
        }

        $event_title = DBEM_CPT::get_event_name($reg->event_id);

        switch ($reg->status) {
            case 'confirmed':
                global $wpdb;
                $table = $wpdb->prefix . 'dbem_registrations';
                $now = current_time('mysql');
                $wpdb->update($table,
                    array('status' => 'checked_in', 'checked_in_at' => $now),
                    array('id' => $reg->id),
                    array('%s', '%s'),
                    array('%d')
                );
                wp_send_json_success(array(
                    'status'  => 'checked_in',
                    'message' => sprintf(__('Check-in effettuato', 'db-event-manager')),
                    'name'    => $reg->name,
                    'event'   => $event_title,
                    'time'    => wp_date('H:i', strtotime($now)),
                    'icon'    => '✅',
                ));
                break;

            case 'checked_in':
                $time = $reg->checked_in_at ? wp_date('H:i', strtotime($reg->checked_in_at)) : '—';
                wp_send_json_success(array(
                    'status'  => 'already',
                    'message' => sprintf(__('Già registrato alle %s', 'db-event-manager'), $time),
                    'name'    => $reg->name,
                    'event'   => $event_title,
                    'time'    => $time,
                    'icon'    => '⚠️',
                ));
                break;

            case 'cancelled':
                wp_send_json_success(array(
                    'status'  => 'cancelled',
                    'message' => __('Iscrizione annullata', 'db-event-manager'),
                    'name'    => $reg->name,
                    'event'   => $event_title,
                    'icon'    => '❌',
                ));
                break;
        }
    }

    /**
     * Ricerca pubblica partecipanti (protetta da PIN)
     */
    public static function handle_public_search() {
        $pin_stored = get_option('dbem_checkin_pin', '');
        if ($pin_stored) {
            $pin_sent = sanitize_text_field($_POST['pin'] ?? '');
            if ($pin_sent !== $pin_stored) {
                wp_send_json_error(array('message' => __('PIN non valido', 'db-event-manager'), 'status' => 'pin_error'));
            }
        }

        $search = sanitize_text_field($_POST['search'] ?? '');
        if (strlen($search) < 2) {
            wp_send_json_error(array('message' => __('Inserisci almeno 2 caratteri', 'db-event-manager')));
        }

        DBEM_DB::ensure_tables();
        $results = DBEM_DB::search_registrations_global($search);

        $items = array();
        foreach ($results as $r) {
            $items[] = array(
                'name'   => $r->name,
                'email'  => $r->email,
                'token'  => $r->token,
                'status' => $r->status,
                'event'  => DBEM_CPT::get_event_name($r->event_id),
                'time'   => $r->checked_in_at ? wp_date('H:i', strtotime($r->checked_in_at)) : '',
            );
        }

        wp_send_json_success($items);
    }
}
