<?php
if (!defined('ABSPATH')) exit;

class DBEM_Survey {

    /**
     * Pagina admin survey
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) wp_die(__('Accesso negato', 'db-event-manager'));
        include DBEM_PLUGIN_DIR . 'templates/admin/survey.php';
    }

    /**
     * Render pagina survey frontend (via query var)
     */
    public static function render_survey_page($token) {
        DBEM_DB::ensure_tables();
        $reg = DBEM_DB::get_registration_by_token($token);

        if (!$reg) {
            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2>❌</h2><p>' . esc_html__('Link non valido.', 'db-event-manager') . '</p></div>',
                __('Survey', 'db-event-manager'), array('response' => 404)
            );
        }

        $event_id = $reg->event_id;
        $survey_enabled = get_post_meta($event_id, '_dbem_survey_enabled', true);

        if ($survey_enabled !== '1') {
            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2>📋</h2><p>' . esc_html__('Il survey per questo evento non è attivo.', 'db-event-manager') . '</p></div>',
                __('Survey', 'db-event-manager'), array('response' => 200)
            );
        }

        // Già risposto?
        if (DBEM_DB::has_survey_response($reg->id)) {
            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2>✅</h2><p>' . esc_html__('Grazie, hai già risposto al questionario!', 'db-event-manager') . '</p></div>',
                __('Survey', 'db-event-manager'), array('response' => 200)
            );
        }

        include DBEM_PLUGIN_DIR . 'templates/frontend/survey.php';
        exit;
    }

    /**
     * Submit survey via AJAX
     */
    public static function handle_submit() {
        if (!isset($_POST['dbem_survey_nonce']) || !wp_verify_nonce($_POST['dbem_survey_nonce'], 'dbem_survey_submit')) {
            wp_send_json_error(__('Richiesta non valida.', 'db-event-manager'));
        }

        $token = sanitize_text_field($_POST['token'] ?? '');
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
                $value = isset($_POST[$field_key]) ? array_map('sanitize_text_field', (array)$_POST[$field_key]) : array();
            } elseif ($field['type'] === 'textarea') {
                $value = sanitize_textarea_field($_POST[$field_key] ?? '');
            } else {
                $value = sanitize_text_field($_POST[$field_key] ?? '');
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
        $wpdb->insert($table, array(
            'event_id'        => $event_id,
            'registration_id' => $reg->id,
            'data'            => wp_json_encode($responses),
            'submitted_at'    => current_time('mysql'),
        ), array('%d', '%d', '%s', '%s'));

        wp_send_json_success(array(
            'message' => __('Grazie per il tuo feedback!', 'db-event-manager'),
        ));
    }

    /**
     * Invio manuale email survey
     */
    public static function handle_send() {
        check_ajax_referer('dbem_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Accesso negato', 'db-event-manager'));

        $event_id = absint($_POST['event_id'] ?? 0);
        $target = sanitize_key($_POST['target'] ?? 'checked_in'); // checked_in | all

        if (!$event_id) wp_send_json_error(__('Evento mancante', 'db-event-manager'));

        DBEM_DB::ensure_tables();

        if ($target === 'all') {
            $regs = DBEM_DB::get_registrations($event_id);
            $regs = array_filter($regs, function($r) { return $r->status !== 'cancelled'; });
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
