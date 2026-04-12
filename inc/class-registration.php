<?php
if (!defined('ABSPATH')) exit;

class DBEM_Registration {

    /**
     * Gestisci iscrizione da form frontend
     */
    public static function handle_registration() {
        // Verifica nonce
        if (!isset($_POST['dbem_nonce']) || !wp_verify_nonce($_POST['dbem_nonce'], 'dbem_registration_nonce')) {
            wp_send_json_error(__('Richiesta non valida.', 'db-event-manager'));
        }

        // Honeypot
        if (!empty($_POST['dbem_website_url'])) {
            wp_send_json_error(__('Richiesta non valida.', 'db-event-manager'));
        }

        // Rate limiting
        $ip = self::get_client_ip();
        $rate_key = 'dbem_rate_' . md5($ip);
        $rate_count = (int) get_transient($rate_key);
        if ($rate_count >= 5) {
            wp_send_json_error(__('Troppe richieste. Riprova tra qualche minuto.', 'db-event-manager'));
        }
        set_transient($rate_key, $rate_count + 1, 60);

        $event_id = absint($_POST['event_id'] ?? 0);
        if (!$event_id || get_post_type($event_id) !== 'dbem_event') {
            wp_send_json_error(__('Evento non valido.', 'db-event-manager'));
        }

        // Verifica iscrizioni aperte
        if (!DBEM_CPT::are_registrations_open($event_id)) {
            wp_send_json_error(__('Le iscrizioni per questo evento sono chiuse.', 'db-event-manager'));
        }

        // Valida campi obbligatori
        $name = sanitize_text_field($_POST['dbem_name'] ?? '');
        $email = sanitize_email($_POST['dbem_email'] ?? '');

        if (empty($name)) {
            wp_send_json_error(__('Il nome è obbligatorio.', 'db-event-manager'));
        }
        if (!is_email($email)) {
            wp_send_json_error(__('Inserisci un indirizzo email valido.', 'db-event-manager'));
        }

        // GDPR
        if (empty($_POST['dbem_privacy'])) {
            wp_send_json_error(__('Devi accettare l\'informativa sulla privacy.', 'db-event-manager'));
        }

        // Controlla duplicati
        DBEM_DB::ensure_tables();
        if (DBEM_DB::email_exists_for_event($event_id, $email)) {
            wp_send_json_error(__('Questo indirizzo email è già registrato per questo evento.', 'db-event-manager'));
        }

        // Campi custom
        $custom_fields = get_post_meta($event_id, '_dbem_custom_fields', true);
        $custom_data = array();
        if (is_array($custom_fields)) {
            foreach ($custom_fields as $i => $field) {
                $field_key = 'dbem_custom_' . $i;
                $value = '';
                if ($field['type'] === 'checkbox') {
                    $value = isset($_POST[$field_key]) ? array_map('sanitize_text_field', (array)$_POST[$field_key]) : array();
                } else {
                    $value = sanitize_text_field($_POST[$field_key] ?? '');
                }
                if ($field['required'] && empty($value)) {
                    wp_send_json_error(sprintf(
                        __('Il campo "%s" è obbligatorio.', 'db-event-manager'),
                        esc_html($field['label'])
                    ));
                }
                $custom_data[$field['label']] = $value;
            }
        }

        // Ricontrolla posti (race condition)
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);
        if ($max > 0) {
            $count = DBEM_DB::count_registrations($event_id);
            if ($count >= $max) {
                wp_send_json_error(__('I posti sono esauriti.', 'db-event-manager'));
            }
        }

        // Genera token
        $token = bin2hex(random_bytes(32));

        // Determina status in base alla modalità
        $approval_mode = get_post_meta($event_id, '_dbem_approval_mode', true) ?: 'auto';
        $initial_status = ($approval_mode === 'approval') ? 'pending' : 'confirmed';

        // Salva
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $result = $wpdb->insert($table, array(
            'event_id'      => $event_id,
            'data'          => wp_json_encode(array_merge(array('nome' => $name, 'email' => $email), $custom_data)),
            'email'         => $email,
            'name'          => $name,
            'token'         => $token,
            'status'        => $initial_status,
            'registered_at' => current_time('mysql'),
            'ip_address'    => $ip,
        ), array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));

        if ($result === false) {
            wp_send_json_error(__('Errore durante la registrazione. Riprova.', 'db-event-manager'));
        }

        $reg_id = $wpdb->insert_id;
        $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg_id));

        if ($initial_status === 'confirmed') {
            // Auto: QR + email conferma subito
            DBEM_QRCode::generate($token);
            DBEM_Email::send_confirmation($event_id, $reg);
        } else {
            // Approvazione: email "in attesa" all'iscritto + email approvazione al responsabile
            DBEM_Email::send_pending_notification($event_id, $reg);
            DBEM_Email::send_approval_request($event_id, $reg);
        }

        // Notifica admin
        $notify = get_post_meta($event_id, '_dbem_notify_admin', true);
        if ($notify === '1') {
            DBEM_Email::notify_admin($event_id, $reg);
        }

        $success_message = ($initial_status === 'confirmed')
            ? __('Iscrizione completata! Controlla la tua email per la conferma e il QR code.', 'db-event-manager')
            : __('Iscrizione ricevuta! Riceverai una email quando sarà approvata.', 'db-event-manager');

        wp_send_json_success(array(
            'message' => $success_message,
        ));
    }

    private static function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Gestisci iscrizione da form DB Form Builder
     * DBFB gestisce il submit del form (validazione, email DBFB, salvataggio in dbfb_submissions).
     * Questo handler crea l'iscrizione evento (registrations, QR code, email conferma).
     */
    public static function handle_dbfb_registration() {
        if (!isset($_POST['dbem_nonce']) || !wp_verify_nonce($_POST['dbem_nonce'], 'dbem_registration_nonce')) {
            wp_send_json_error(__('Richiesta non valida.', 'db-event-manager'));
        }

        $event_id = absint($_POST['event_id'] ?? 0);
        if (!$event_id || get_post_type($event_id) !== 'dbem_event') {
            wp_send_json_error(__('Evento non valido.', 'db-event-manager'));
        }

        if (!DBEM_CPT::are_registrations_open($event_id)) {
            wp_send_json_error(__('Le iscrizioni per questo evento sono chiuse.', 'db-event-manager'));
        }

        $name = sanitize_text_field($_POST['dbem_name'] ?? '');
        $email = sanitize_email($_POST['dbem_email'] ?? '');

        if (empty($name) || !is_email($email)) {
            wp_send_json_error(__('Nome e email sono obbligatori.', 'db-event-manager'));
        }

        DBEM_DB::ensure_tables();

        if (DBEM_DB::email_exists_for_event($event_id, $email)) {
            wp_send_json_error(__('Questo indirizzo email è già registrato per questo evento.', 'db-event-manager'));
        }

        // Posti
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);
        if ($max > 0 && DBEM_DB::count_registrations($event_id) >= $max) {
            wp_send_json_error(__('I posti sono esauriti.', 'db-event-manager'));
        }

        // Dati extra dal form DBFB
        $extra_data = array();
        $raw_data = $_POST['dbem_data'] ?? '{}';
        $decoded = json_decode(stripslashes($raw_data), true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                $extra_data[sanitize_text_field($k)] = sanitize_text_field($v);
            }
        }

        $token = bin2hex(random_bytes(32));
        $ip = self::get_client_ip();

        $approval_mode = get_post_meta($event_id, '_dbem_approval_mode', true) ?: 'auto';
        $initial_status = ($approval_mode === 'approval') ? 'pending' : 'confirmed';

        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $result = $wpdb->insert($table, array(
            'event_id'      => $event_id,
            'data'          => wp_json_encode(array_merge(array('nome' => $name, 'email' => $email), $extra_data)),
            'email'         => $email,
            'name'          => $name,
            'token'         => $token,
            'status'        => $initial_status,
            'registered_at' => current_time('mysql'),
            'ip_address'    => $ip,
        ), array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));

        if ($result === false) {
            wp_send_json_error(__('Errore durante la registrazione.', 'db-event-manager'));
        }

        $reg_id = $wpdb->insert_id;
        $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg_id));

        if ($initial_status === 'confirmed') {
            DBEM_QRCode::generate($token);
            DBEM_Email::send_confirmation($event_id, $reg);
        } else {
            DBEM_Email::send_pending_notification($event_id, $reg);
            DBEM_Email::send_approval_request($event_id, $reg);
        }

        $notify = get_post_meta($event_id, '_dbem_notify_admin', true);
        if ($notify === '1') {
            DBEM_Email::notify_admin($event_id, $reg);
        }

        $success_message = ($initial_status === 'confirmed')
            ? __('Iscrizione all\'evento completata! Controlla la tua email per la conferma e il QR code.', 'db-event-manager')
            : __('Iscrizione ricevuta! Riceverai una email quando sarà approvata.', 'db-event-manager');

        wp_send_json_success(array(
            'message' => $success_message,
        ));
    }
}
