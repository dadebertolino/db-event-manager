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

        if (!self::is_enabled($event_id) || !self::can_answer($reg)) {
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
     * Domande del survey, ciascuna con un id stabile (come i campi del form integrato):
     * le risposte si salvano sotto l'id, così rinominare una domanda non le fa sparire
     * e due domande con la stessa etichetta non si sovrascrivono
     */
    public static function get_fields($event_id) {
        $fields = get_post_meta($event_id, '_dbem_survey_fields', true);
        if (!is_array($fields)) return array();

        $with_ids = DBEM_CPT::assign_field_ids($fields);
        if ($with_ids !== $fields) {
            update_post_meta($event_id, '_dbem_survey_fields', wp_slash($with_ids));
        }
        return $with_ids;
    }

    /**
     * Risposta a una domanda: sotto l'id; prima della 1.11.0 sotto l'etichetta
     * (risposte non ancora convertite, vedi migrate_label_keys)
     */
    public static function answer($data, $field) {
        if (!is_array($data)) return '';
        if (isset($field['id']) && array_key_exists($field['id'], $data)) return $data[$field['id']];
        return $data[$field['label'] ?? ''] ?? '';
    }

    /**
     * Colonne per tabelle ed export: le domande attuali (etichetta attuale) più le chiavi
     * di risposte a domande eliminate nel frattempo, che restano visibili
     *
     * @return array<string, string> chiave nei dati => intestazione
     */
    public static function columns($fields, $responses) {
        $columns = array();
        $known = array();
        foreach ($fields as $field) {
            $columns[$field['id']] = $field['label'];
            $known[$field['id']] = true;
            $known[$field['label']] = true;
        }
        foreach ($responses as $resp) {
            $data = json_decode($resp->data, true);
            foreach (is_array($data) ? array_keys($data) : array() as $key) {
                if (!isset($known[$key])) {
                    $columns[$key] = (string) $key;
                    $known[$key] = true;
                }
            }
        }
        return $columns;
    }

    /**
     * Valore di una colonna (vedi columns) per una risposta
     */
    public static function column_value($data, $key, $fields) {
        foreach ($fields as $field) {
            if ($field['id'] === $key) return self::answer($data, $field);
        }
        return is_array($data) ? ($data[$key] ?? '') : '';
    }

    /**
     * 1.11.0: le risposte salvate sotto l'etichetta passano sotto l'id della domanda
     * con quell'etichetta. Le chiavi di domande che non esistono più restano com'erano.
     * Gira una volta, con l'aggiornamento dello schema (DBEM_DB::maybe_upgrade).
     */
    public static function migrate_label_keys() {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_survey_responses';
        $fields_by_event = array();

        foreach ($wpdb->get_results("SELECT id, event_id, data FROM $table") as $row) {
            $event_id = (int) $row->event_id;
            if (!isset($fields_by_event[$event_id])) {
                $map = array();
                foreach (self::get_fields($event_id) as $field) {
                    if (!isset($map[$field['label']])) $map[$field['label']] = $field['id'];
                }
                $fields_by_event[$event_id] = $map;
            }
            $data = json_decode($row->data, true);
            if (!is_array($data)) continue;

            $converted = array();
            foreach ($data as $key => $value) {
                $new_key = $fields_by_event[$event_id][$key] ?? $key;
                // Un id già presente (risposta nuova) vince sull'etichetta
                if (!array_key_exists($new_key, $converted)) $converted[$new_key] = $value;
            }
            if ($converted !== $data) {
                $wpdb->update($table, array('data' => wp_json_encode($converted)), array('id' => (int) $row->id), array('%s'), array('%d'));
            }
        }
    }

    public static function is_enabled($event_id) {
        return get_post_meta($event_id, '_dbem_survey_enabled', true) === '1';
    }

    /**
     * Rispondono solo le iscrizioni valide (confermate o presenti), come per l'invio
     * del link: chi è in attesa, rifiutato o annullato ha comunque il token del QR
     */
    public static function can_answer($reg) {
        return in_array($reg->status ?? '', array('confirmed', 'checked_in'), true);
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
        // Gli stessi controlli della pagina: un POST diretto non deve aggirarli
        if (!self::is_enabled($reg->event_id) || !self::can_answer($reg)) {
            wp_send_json_error(__('Il survey per questo evento non è attivo.', 'db-event-manager'));
        }

        if (DBEM_DB::has_survey_response($reg->id)) {
            wp_send_json_error(__('Hai già risposto a questo questionario.', 'db-event-manager'));
        }

        $event_id = $reg->event_id;
        $survey_fields = self::get_fields($event_id);

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

            // "0" è una risposta valida; il messaggio va a textContent, niente esc_html
            if ($field['required'] && ($value === '' || $value === array())) {
                wp_send_json_error(sprintf(
                    __('Il campo "%s" è obbligatorio.', 'db-event-manager'),
                    $field['label']
                ));
            }

            $responses[$field['id']] = $value;
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
        $target = sanitize_key(DBEM_Security::input('target', 'checked_in')); // checked_in | all

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
