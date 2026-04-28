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


    /**
     * Pagina pubblica partecipanti (protetta da PIN)
     */
    public static function render_public_participants_page() {
        include DBEM_PLUGIN_DIR . 'templates/frontend/participants.php';
        exit;
    }

    /**
     * AJAX: lista partecipanti pubblica (protetta da PIN)
     */
    public static function handle_public_participants() {
        // Verifica PIN
        $pin_stored = get_option('dbem_checkin_pin', '');
        if ($pin_stored) {
            $pin_sent = sanitize_text_field($_POST['pin'] ?? '');
            if ($pin_sent !== $pin_stored) {
                wp_send_json_error(array('message' => __('PIN non valido', 'db-event-manager'), 'status' => 'pin_error'));
            }
        }

        $event_id = absint($_POST['event_id'] ?? 0);
        if (!$event_id) wp_send_json_error(array('message' => __('Evento mancante', 'db-event-manager')));

        DBEM_DB::ensure_tables();
        $regs = DBEM_DB::get_registrations($event_id, null, 'registered_at', 'ASC');
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);

        $items = array();
        $stats = array('total' => 0, 'checked_in' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'max' => $max);

        foreach ($regs as $r) {
            $items[] = array(
                'id'            => $r->id,
                'name'          => $r->name,
                'email'         => $r->email,
                'status'        => $r->status,
                'assigned_time' => isset($r->assigned_time) ? $r->assigned_time : '',
                'registered_at' => date('d/m/Y H:i', strtotime($r->registered_at)),
                'checked_in_at' => $r->checked_in_at ? date('H:i', strtotime($r->checked_in_at)) : '',
            );
            if (isset($stats[$r->status])) $stats[$r->status]++;
            if ($r->status !== 'cancelled' && $r->status !== 'rejected') $stats['total']++;
        }

        wp_send_json_success(array('registrations' => $items, 'stats' => $stats));
    }

    /**
     * AJAX: azione su partecipante da pagina pubblica (protetta da PIN)
     */
    public static function handle_public_participant_action() {
        // Verifica PIN
        $pin_stored = get_option('dbem_checkin_pin', '');
        if ($pin_stored) {
            $pin_sent = sanitize_text_field($_POST['pin'] ?? '');
            if ($pin_sent !== $pin_stored) {
                wp_send_json_error(array('message' => __('PIN non valido', 'db-event-manager')));
            }
        }

        $action = sanitize_key($_POST['participant_action'] ?? '');
        $reg_id = absint($_POST['registration_id'] ?? 0);
        if (!$action || !$reg_id) wp_send_json_error(array('message' => __('Parametri mancanti', 'db-event-manager')));

        DBEM_DB::ensure_tables();
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg_id));
        if (!$reg) wp_send_json_error(array('message' => __('Iscrizione non trovata', 'db-event-manager')));

        switch ($action) {
            case 'confirm':
                $wpdb->update($table, array('status' => 'confirmed'), array('id' => $reg_id), array('%s'), array('%d'));
                // Genera QR + email se era pending
                if ($reg->status === 'pending') {
                    DBEM_QRCode::generate($reg->token);
                    $reg_updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg_id));
                    DBEM_Email::send_confirmation($reg->event_id, $reg_updated);
                }
                wp_send_json_success(array('message' => sprintf(__('%s approvato', 'db-event-manager'), $reg->name)));
                break;

            case 'reject':
                $wpdb->update($table, array('status' => 'rejected'), array('id' => $reg_id), array('%s'), array('%d'));
                $reg_updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg_id));
                DBEM_Email::send_rejection($reg->event_id, $reg_updated);
                wp_send_json_success(array('message' => sprintf(__('%s rifiutato', 'db-event-manager'), $reg->name)));
                break;

            case 'checkin':
                $wpdb->update($table,
                    array('status' => 'checked_in', 'checked_in_at' => current_time('mysql')),
                    array('id' => $reg_id), array('%s', '%s'), array('%d'));
                wp_send_json_success(array('message' => sprintf(__('%s — check-in effettuato', 'db-event-manager'), $reg->name)));
                break;

            case 'cancel':
                $wpdb->update($table, array('status' => 'cancelled'), array('id' => $reg_id), array('%s'), array('%d'));
                wp_send_json_success(array('message' => sprintf(__('%s annullato', 'db-event-manager'), $reg->name)));
                break;

            case 'resend':
                if ($reg->status === 'confirmed' || $reg->status === 'checked_in') {
                    DBEM_QRCode::generate($reg->token);
                    DBEM_Email::send_confirmation($reg->event_id, $reg);
                    wp_send_json_success(array('message' => sprintf(__('Email reinviata a %s', 'db-event-manager'), $reg->name)));
                } else {
                    wp_send_json_error(array('message' => __('Email non inviata: iscrizione non confermata', 'db-event-manager')));
                }
                break;

            default:
                wp_send_json_error(array('message' => __('Azione non valida', 'db-event-manager')));
        }
    }


    /**
     * AJAX: iscrizione manuale da pagina pubblica (protetta da PIN)
     */
    public static function handle_public_add_participant() {
        // Verifica PIN
        $pin_stored = get_option('dbem_checkin_pin', '');
        if ($pin_stored) {
            $pin_sent = sanitize_text_field($_POST['pin'] ?? '');
            if ($pin_sent !== $pin_stored) {
                wp_send_json_error(array('message' => __('PIN non valido', 'db-event-manager')));
            }
        }

        $event_id = absint($_POST['event_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $assigned_time = sanitize_text_field($_POST['assigned_time'] ?? '');

        if (!$event_id || !$name || !$email) {
            wp_send_json_error(array('message' => __('Nome, email e evento sono obbligatori', 'db-event-manager')));
        }
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Email non valida', 'db-event-manager')));
        }

        DBEM_DB::ensure_tables();

        // Controlla duplicato
        if (DBEM_DB::email_exists_for_event($event_id, $email)) {
            wp_send_json_error(array('message' => sprintf(__('%s è già iscritto a questo evento', 'db-event-manager'), $email)));
        }

        // Controlla posti
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);
        if ($max > 0) {
            $count = DBEM_DB::count_registrations($event_id);
            if ($count >= $max) {
                wp_send_json_error(array('message' => __('Posti esauriti', 'db-event-manager')));
            }
        }

        // Genera token
        $token = bin2hex(random_bytes(32));

        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $wpdb->insert($table, array(
            'event_id'      => $event_id,
            'name'          => $name,
            'email'         => $email,
            'token'         => $token,
            'status'        => 'confirmed',
            'data'          => json_encode(array('nome' => $name, 'email' => $email)),
            'assigned_time' => $assigned_time,
            'registered_at' => current_time('mysql'),
            'ip_address'    => 'manual',
        ), array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));

        if (!$wpdb->insert_id) {
            wp_send_json_error(array('message' => __('Errore nel salvataggio', 'db-event-manager')));
        }

        $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $wpdb->insert_id));

        // Genera QR + invia email
        DBEM_QRCode::generate($token);
        DBEM_Email::send_confirmation($event_id, $reg);

        wp_send_json_success(array('message' => sprintf(__('%s iscritto con successo', 'db-event-manager'), $name)));
    }

    /**
     * AJAX: modifica orario assegnato da pagina pubblica (protetta da PIN)
     */
    public static function handle_public_update_time() {
        // Verifica PIN
        $pin_stored = get_option('dbem_checkin_pin', '');
        if ($pin_stored) {
            $pin_sent = sanitize_text_field($_POST['pin'] ?? '');
            if ($pin_sent !== $pin_stored) {
                wp_send_json_error(array('message' => __('PIN non valido', 'db-event-manager')));
            }
        }

        $reg_id = absint($_POST['registration_id'] ?? 0);
        $assigned_time = sanitize_text_field($_POST['assigned_time'] ?? '');

        if (!$reg_id) {
            wp_send_json_error(array('message' => __('ID iscrizione mancante', 'db-event-manager')));
        }

        DBEM_DB::ensure_tables();
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg_id));

        if (!$reg) {
            wp_send_json_error(array('message' => __('Iscrizione non trovata', 'db-event-manager')));
        }

        $wpdb->update($table,
            array('assigned_time' => $assigned_time),
            array('id' => $reg_id),
            array('%s'),
            array('%d')
        );

        $label = $assigned_time ? $assigned_time : __('rimosso', 'db-event-manager');
        wp_send_json_success(array('message' => sprintf(__('Orario di %s aggiornato: %s  — per notificare, premi 📧', 'db-event-manager'), $reg->name, $label)));
    }
}
