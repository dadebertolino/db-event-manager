<?php
if (!defined('ABSPATH')) exit;

class DBEM_Survey {

    /**
     * Pagina admin survey
     */
    public static function render_admin_page() {
        if (!DBEM_Admin::can_manage_events()) wp_die(esc_html__('Accesso negato', 'db-event-manager'));
        include DBEM_PLUGIN_DIR . 'templates/admin/survey.php';
    }

    /**
     * Render pagina survey frontend (via query var)
     */
    public static function render_survey_page($token) {
        // Pagina legata al token dell'iscritto: mai in cache di pagina
        DBEM_Security::no_cache_page();
        DBEM_DB::ensure_tables();
        $reg = DBEM_DB::get_registration_by_token($token);

        if (!$reg) {
            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2>❌</h2><p>' . esc_html__('Link non valido.', 'db-event-manager') . '</p></div>',
                esc_html__('Survey', 'db-event-manager'), array('response' => 404)
            );
        }

        $event_id = $reg->event_id;
        $survey_enabled = get_post_meta($event_id, '_dbem_survey_enabled', true);

        if ($survey_enabled !== '1') {
            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2>📋</h2><p>' . esc_html__('Il survey per questo evento non è attivo.', 'db-event-manager') . '</p></div>',
                esc_html__('Survey', 'db-event-manager'), array('response' => 200)
            );
        }

        // Già risposto?
        if (DBEM_DB::has_survey_response($reg->id)) {
            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2>✅</h2><p>' . esc_html__('Grazie, hai già risposto al questionario!', 'db-event-manager') . '</p></div>',
                esc_html__('Survey', 'db-event-manager'), array('response' => 200)
            );
        }

        include DBEM_PLUGIN_DIR . 'templates/frontend/survey.php';
        exit;
    }

    /**
     * Submit survey via AJAX
     */
    public static function handle_submit() {
        // Nonce (utenti loggati) oppure origine + rate limit (visitatori anonimi):
        // l'autorizzazione vera è il token personale dell'iscritto
        DBEM_Security::verify_request(
            'dbem_survey_submit',
            'dbem_survey_nonce',
            'survey',
            5,
            __('Richiesta non valida: invia il questionario dalla sua pagina.', 'db-event-manager')
        );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce (loggati) o origine + rate limit (anonimi) verificati in DBEM_Security::verify_request()

        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        if (empty($token)) wp_send_json_error(__('Token mancante.', 'db-event-manager'));

        DBEM_DB::ensure_tables();
        $reg = DBEM_DB::get_registration_by_token($token);
        if (!$reg) wp_send_json_error(__('Token non valido.', 'db-event-manager'));

        if (DBEM_DB::has_survey_response($reg->id)) {
            wp_send_json_error(__('Hai già risposto a questo questionario.', 'db-event-manager'));
        }

        $event_id = $reg->event_id;
        $survey_fields = get_post_meta($event_id, '_dbem_survey_fields', true);
        if (!is_array($survey_fields)) $survey_fields = array();

        $responses = array();
        foreach ($survey_fields as $i => $field) {
            $field_key = 'dbem_survey_' . $i;
            $value = '';
            if ($field['type'] === 'checkbox') {
                $value = isset($_POST[$field_key]) ? array_map('sanitize_text_field', (array)wp_unslash($_POST[$field_key])) : array();
            } elseif ($field['type'] === 'textarea') {
                $value = sanitize_textarea_field(wp_unslash($_POST[$field_key] ?? ''));
            } else {
                $value = sanitize_text_field(wp_unslash($_POST[$field_key] ?? ''));
            }

            if ($field['required'] && empty($value)) {
                wp_send_json_error(sprintf(
                    __('Il campo "%s" è obbligatorio.', 'db-event-manager'),
                    esc_html($field['label'])
                ));
            }

            $responses[$field['label']] = $value;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dbem_survey_responses';
        $inserted = $wpdb->insert($table, array(
            'event_id'        => $event_id,
            'registration_id' => $reg->id,
            'data'            => wp_json_encode($responses),
            'submitted_at'    => current_time('mysql'),
        ), array('%d', '%d', '%s', '%s'));

        if ($inserted === false) {
            wp_send_json_error(__('Errore durante il salvataggio delle risposte. Riprova.', 'db-event-manager'));
        }

        wp_send_json_success(array(
            'message' => __('Grazie per il tuo feedback!', 'db-event-manager'),
        ));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /**
     * Invio manuale email survey
     */
    public static function handle_send() {
        check_ajax_referer('dbem_admin_nonce', 'nonce');
        if (!DBEM_Admin::can_manage_events()) wp_send_json_error(__('Accesso negato', 'db-event-manager'));

        $event_id = absint($_POST['event_id'] ?? 0);
        $target = sanitize_key($_POST['target'] ?? 'checked_in'); // checked_in | all

        if (!$event_id || get_post_type($event_id) !== 'dbem_event') wp_send_json_error(__('Evento mancante', 'db-event-manager'));

        DBEM_DB::ensure_tables();

        if ($target === 'all') {
            // Solo chi ha un'iscrizione valida: niente sondaggio a chi è in attesa, rifiutato o annullato
            $regs = DBEM_DB::get_reminder_registrations($event_id);
        } else {
            $regs = DBEM_DB::get_registrations($event_id, 'checked_in');
        }

        $sent = 0;
        foreach ($regs as $reg) {
            // Skip se ha già risposto
            if (DBEM_DB::has_survey_response($reg->id)) continue;
            if (DBEM_Email::send_survey_email($event_id, $reg)) $sent++;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Email inviate: %d', 'db-event-manager'), $sent),
        ));
    }
}
