<?php
if (!defined('ABSPATH')) exit;

class DBEM_Registration {

    /**
     * Gestisci iscrizione da form frontend
     */
    public static function handle_registration() {
        // Nonce (utenti loggati) oppure origine + rate limit (visitatori anonimi)
        self::verify_registration_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce (loggati) o origine + rate limit (anonimi) verificati in verify_registration_request()

        // Honeypot
        if (!empty($_POST['dbem_website_url'])) {
            wp_send_json_error(__('Richiesta non valida.', 'db-event-manager'));
        }

        $ip = self::get_client_ip();
        self::check_rate_limit();

        $event_id = absint($_POST['event_id'] ?? 0);
        self::require_event($event_id, 'builtin');

        // Valida campi obbligatori
        $name = sanitize_text_field(wp_unslash($_POST['dbem_name'] ?? ''));
        $email = strtolower(sanitize_email(wp_unslash($_POST['dbem_email'] ?? '')));

        if (empty($name)) {
            wp_send_json_error(__('Il nome è obbligatorio.', 'db-event-manager'));
        }
        if (!is_email($email)) {
            wp_send_json_error(__('Inserisci un indirizzo email valido.', 'db-event-manager'));
        }

        // GDPR — validazione solo se checkbox attiva per questo evento
        $gdpr_enabled = get_post_meta($event_id, '_dbem_gdpr_enabled', true);
        if ($gdpr_enabled === '1' && empty($_POST['dbem_privacy'])) {
            wp_send_json_error(__('Devi accettare l\'informativa sulla privacy.', 'db-event-manager'));
        }

        // Iscrizioni chiuse: vale per tutti, anche per chi è già iscritto (vedi handle_existing)
        DBEM_DB::ensure_tables();
        self::require_open($event_id);
        $existing = DBEM_DB::get_registration_by_email($event_id, $email);

        // Campi custom
        $custom_fields = get_post_meta($event_id, '_dbem_custom_fields', true);
        $custom_data = array();
        if (is_array($custom_fields)) {
            foreach ($custom_fields as $i => $field) {
                $field_key = 'dbem_custom_' . $i;
                $raw = isset($_POST[$field_key]) ? wp_unslash($_POST[$field_key]) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizzato e validato da field_value()
                $result = self::field_value($field, $raw);
                if ($result['error'] !== '') {
                    wp_send_json_error($result['error']);
                }
                $custom_data[$field['label']] = $result['value'];
            }
        }

        // Ricontrolla posti (race condition)
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);
        if ($max > 0) {
            $count = DBEM_DB::count_registrations($event_id);
            if ($count >= $max && !$existing) {
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

        // Cattura prova del consenso (Capability 5 — art. 7.1 GDPR)
        $gdpr_consent_given          = null;
        $gdpr_consent_text           = null;
        $gdpr_consent_timestamp      = null;
        $gdpr_consent_privacy_url    = null;
        $gdpr_consent_policy_version = 0;

        // $gdpr_enabled è già stato letto e validato sopra
        if ($gdpr_enabled === '1') {
            $gdpr_consent_given     = 1;
            $gdpr_consent_text      = get_post_meta($event_id, '_dbem_gdpr_text', true);
            if (empty($gdpr_consent_text)) {
                $gdpr_consent_text = __('Acconsento al trattamento dei dati personali secondo la Privacy Policy', 'db-event-manager');
            }
            $gdpr_consent_timestamp = current_time('mysql');

            // URL privacy: configurata per evento → fallback a quella globale WP
            $privacy_url = get_post_meta($event_id, '_dbem_gdpr_link', true);
            if (empty($privacy_url) && function_exists('get_privacy_policy_url')) {
                $privacy_url = (string) get_privacy_policy_url();
            }
            $gdpr_consent_privacy_url = $privacy_url ?: null;

            // Link alla versione esatta della Privacy Policy (Privacy Hub)
            if (class_exists('DBPH_Policy_Archive') && method_exists('DBPH_Policy_Archive', 'get_current_version_id')) {
                $gdpr_consent_policy_version = (int) DBPH_Policy_Archive::get_current_version_id();
            }
        }

        $registration_data = array(
            'data'          => wp_json_encode(array('nome' => $name, 'email' => $email) + $custom_data),
            'email'         => $email,
            'name'          => $name,
            'gdpr_consent_given'          => $gdpr_consent_given,
            'gdpr_consent_text'           => $gdpr_consent_text,
            'gdpr_consent_timestamp'      => $gdpr_consent_timestamp,
            'gdpr_consent_privacy_url'    => $gdpr_consent_privacy_url,
            'gdpr_consent_policy_version' => $gdpr_consent_policy_version,
            'ip_address'    => $ip,
        );

        if ($existing) {
            self::handle_existing($event_id, $existing, $registration_data);
        }

        $result = $wpdb->insert($table, array(
            'event_id'      => $event_id,
            'data'          => $registration_data['data'],
            'email'         => $registration_data['email'],
            'name'          => $registration_data['name'],
            'token'         => $token,
            'status'        => $initial_status,
            'registered_at' => current_time('mysql'),
            'gdpr_consent_given'          => $registration_data['gdpr_consent_given'],
            'gdpr_consent_text'           => $registration_data['gdpr_consent_text'],
            'gdpr_consent_timestamp'      => $registration_data['gdpr_consent_timestamp'],
            'gdpr_consent_privacy_url'    => $registration_data['gdpr_consent_privacy_url'],
            'gdpr_consent_policy_version' => $registration_data['gdpr_consent_policy_version'],
            'ip_address'    => $registration_data['ip_address'],
        ), array(
            '%d', // event_id
            '%s', // data
            '%s', // email
            '%s', // name
            '%s', // token
            '%s', // status
            '%s', // registered_at
            '%d', // gdpr_consent_given
            '%s', // gdpr_consent_text
            '%s', // gdpr_consent_timestamp
            '%s', // gdpr_consent_privacy_url
            '%d', // gdpr_consent_policy_version
            '%s', // ip_address
        ));
        $reg_id = $wpdb->insert_id;

        if ($result === false) {
            wp_send_json_error(__('Errore durante la registrazione. Riprova.', 'db-event-manager'));
        }

        $reg = DBEM_DB::get_registration($reg_id);
        if (!$reg) {
            wp_send_json_error(__('Errore durante la registrazione. Riprova.', 'db-event-manager'));
        }
        self::send_registration_emails($event_id, $reg);

        wp_send_json_success(array(
            'message' => self::received_message(),
        ));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /**
     * Valore di un campo del form integrato, sanitizzato e validato sul tipo: le scelte
     * solo tra le opzioni definite, email, numeri e date nel loro formato. Il browser fa
     * gli stessi controlli, ma un invio diretto all'endpoint li salterebbe.
     *
     * @param array        $field Campo come salvato in _dbem_custom_fields
     * @param string|array $raw   Valore inviato, già unslash
     * @return array{value: string|string[], error: string} error vuoto se valido
     */
    public static function field_value($field, $raw) {
        $type = $field['type'] ?? 'text';
        $label = (string) ($field['label'] ?? '');
        $options = array_map('strval', (array) ($field['options'] ?? array()));
        /* translators: %s: etichetta del campo */
        $invalid = sprintf(__('Il valore del campo "%s" non è valido.', 'db-event-manager'), $label);

        if ($type === 'checkbox') {
            $value = array_values(array_filter(array_map('sanitize_text_field', array_filter((array) $raw, 'is_scalar')), 'strlen'));
            if ($options && array_diff($value, $options)) return array('value' => $value, 'error' => $invalid);
        } else {
            $value = is_scalar($raw)
                ? ($type === 'textarea' ? sanitize_textarea_field((string) $raw) : sanitize_text_field((string) $raw))
                : '';
            if ($value !== '') {
                $valid = true;
                if (in_array($type, array('select', 'radio'), true) && $options) {
                    $valid = in_array($value, $options, true);
                } elseif ($type === 'email') {
                    $valid = (bool) is_email($value);
                } elseif ($type === 'number') {
                    $valid = is_numeric($value);
                } elseif ($type === 'date') {
                    $valid = (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
                }
                if (!$valid) return array('value' => $value, 'error' => $invalid);
            }
        }

        // "0" è un valore valido; il messaggio va a .text(), niente esc_html
        if (!empty($field['required']) && ($value === '' || $value === array())) {
            /* translators: %s: etichetta del campo */
            return array('value' => $value, 'error' => sprintf(__('Il campo "%s" è obbligatorio.', 'db-event-manager'), $label));
        }
        return array('value' => $value, 'error' => '');
    }

    /**
     * Risposta a ogni iscrizione accettata, nuova o con un indirizzo già iscritto: il
     * modulo non deve rivelare a terzi se una persona è iscritta. Cosa è successo lo
     * dice l'email, che arriva solo a chi legge quella casella.
     */
    public static function received_message() {
        return __('Richiesta ricevuta! Ti abbiamo inviato un\'email con i dettagli: controlla la tua casella di posta, anche nello spam.', 'db-event-manager');
    }

    /**
     * Ci si iscrive solo a eventi pubblicati e con il form a cui l'endpoint corrisponde:
     * l'endpoint di DB Form Builder non deve aggirare consenso e campi obbligatori del
     * form integrato (e viceversa)
     *
     * @param int    $event_id
     * @param string $form_source 'builtin' o 'dbfb'
     */
    public static function require_event($event_id, $form_source) {
        $source = $event_id ? (get_post_meta($event_id, '_dbem_form_source', true) ?: 'builtin') : '';
        // Evento protetto da password: serve il cookie di chi l'ha inserita
        if (!$event_id || get_post_type($event_id) !== 'dbem_event' || get_post_status($event_id) !== 'publish' || $source !== $form_source || post_password_required($event_id)) {
            wp_send_json_error(__('Evento non valido.', 'db-event-manager'));
        }
    }

    /**
     * Iscrizioni chiuse (manualmente, per scadenza, posti esauriti o evento concluso)
     */
    public static function require_open($event_id) {
        if (!DBEM_CPT::are_registrations_open($event_id)) {
            wp_send_json_error(__('Le iscrizioni per questo evento sono chiuse.', 'db-event-manager'));
        }
    }

    /**
     * Indirizzo già iscritto all'evento. Risponde come a una nuova iscrizione; all'indirizzo
     * arriva il link per confermare la modifica (reiscrizione attiva) oppure un avviso che
     * l'iscrizione esiste già. Chi è stato rifiutato non riapre la richiesta di approvazione.
     */
    public static function handle_existing($event_id, $existing, $registration_data) {
        $allow_update = get_post_meta($event_id, '_dbem_allow_registration_update', true) === '1';
        if ($allow_update && $existing->status !== 'rejected') {
            self::request_update_confirmation($event_id, $existing, $registration_data);
        }

        // Un avviso all'ora per iscrizione: i reinvii non riempiono la casella di chi è iscritto
        $notice_key = 'dbem_dup_notice_' . (int) $existing->id;
        if (!get_transient($notice_key)) {
            set_transient($notice_key, 1, HOUR_IN_SECONDS);
            DBEM_Email::send_already_registered($event_id, $existing);
        }

        wp_send_json_success(array('message' => self::received_message()));
    }

    /**
     * Verifica la richiesta di iscrizione; termina con errore JSON se non valida.
     *
     * Utenti loggati: nonce classico. Visitatori anonimi (1.8.0): niente nonce
     * (in una pagina in cache scadrebbe dopo 12–24h), ma origine dello stesso
     * sito. Logica condivisa con gli altri endpoint pubblici in
     * DBEM_Security::verify_request(). Il rate limit per IP resta applicato
     * dagli handler (check_rate_limit) dopo l'honeypot, anche ai loggati.
     */
    private static function verify_registration_request() {
        DBEM_Security::verify_request(
            'dbem_registration_nonce',
            'dbem_nonce',
            'registration',
            0,
            __('Richiesta non valida: inviare il modulo dalla pagina dell\'evento.', 'db-event-manager')
        );
    }

    /**
     * @deprecated 1.8.0 Usare DBEM_Security::origin_matches().
     *
     * @param string $origin    Valore dell'header Origin o Referer.
     * @param array  $site_urls URL del sito (home_url, site_url).
     * @return bool
     */
    public static function origin_matches($origin, $site_urls) {
        return DBEM_Security::origin_matches($origin, $site_urls);
    }

    /**
     * Al massimo N invii al minuto per indirizzo IP (default 5, filtro
     * 'dbem_registration_rate_limit'). Vedi DBEM_Security::check_rate_limit().
     */
    private static function check_rate_limit() {
        DBEM_Security::check_rate_limit('registration', 5);
    }

    /**
     * Email all'iscritto e ai responsabili dopo il salvataggio dell'iscrizione
     */
    public static function send_registration_emails($event_id, $reg) {
        if (in_array($reg->status, array('confirmed', 'checked_in'), true)) {
            // Auto: QR + email conferma subito
            DBEM_QRCode::generate($reg->token);
            DBEM_Email::send_confirmation($event_id, $reg);
        } else {
            // Approvazione: email "in attesa" all'iscritto + email approvazione al responsabile
            DBEM_Email::send_pending_notification($event_id, $reg);
            DBEM_Email::send_approval_request($event_id, $reg);
        }

        if (get_post_meta($event_id, '_dbem_notify_admin', true) === '1') {
            DBEM_Email::notify_admin($event_id, $reg);
        }
    }

    /**
     * I nuovi dati non sostituiscono subito l'iscrizione esistente: all'indirizzo
     * dell'iscrizione arriva un link di conferma, così può modificarla solo chi legge
     * quella casella e non chiunque ne conosca l'indirizzo.
     */
    public static function request_update_confirmation($event_id, $existing, $registration_data) {
        $key = wp_generate_password(32, false);
        set_transient('dbem_update_' . $key, array(
            'registration_id' => (int) $existing->id,
            'email'           => $existing->email,
            'data'            => $registration_data,
        ), DAY_IN_SECONDS);

        $url = home_url('/?dbem_action=confirm_update&key=' . $key);
        // Un errore di invio non si segnala: rivelerebbe che l'indirizzo è iscritto
        if (!DBEM_Email::send_update_confirmation($event_id, $existing, $url)) {
            delete_transient('dbem_update_' . $key);
        }

        wp_send_json_success(array('message' => self::received_message()));
    }

    /**
     * Link della modifica: con GET mostra il pulsante di conferma, con POST applica i nuovi dati.
     * Il pulsante evita che i sistemi che aprono in anticipo i link delle email confermino da soli.
     */
    public static function handle_update_link() {
        $key = preg_replace('/[^A-Za-z0-9]/', '', sanitize_text_field(wp_unslash($_GET['key'] ?? '')));
        $pending = $key !== '' ? get_transient('dbem_update_' . $key) : false;
        if (!is_array($pending)) {
            self::update_page(__('Link non valido o scaduto. Se vuoi modificare l\'iscrizione, compila di nuovo il modulo.', 'db-event-manager'), 404);
        }

        DBEM_DB::ensure_tables();
        $reg = DBEM_DB::get_registration($pending['registration_id']);
        if (!$reg || $reg->email !== $pending['email'] || in_array($reg->status, array('cancelled', 'rejected'), true)) {
            delete_transient('dbem_update_' . $key);
            self::update_page(__('Questa iscrizione non si può più modificare.', 'db-event-manager'), 410);
        }

        $event_title = DBEM_CPT::get_event_name($reg->event_id);

        if (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $form = '<form method="post" action="' . esc_url(home_url('/?dbem_action=confirm_update&key=' . $key)) . '">'
                . '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('dbem_confirm_update_' . $key)) . '">'
                . '<button type="submit" style="padding:14px 32px;background:#1d6e3f;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;">'
                . esc_html__('Conferma la modifica', 'db-event-manager') . '</button></form>';
            self::update_page(
                sprintf(__('Vuoi sostituire la tua iscrizione a "%s" con i dati appena inviati?', 'db-event-manager'), $event_title),
                200,
                $form
            );
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'dbem_confirm_update_' . $key)) {
            self::update_page(__('Richiesta scaduta. Apri di nuovo il link dall\'email.', 'db-event-manager'), 403);
        }

        // Il link vale una volta sola
        delete_transient('dbem_update_' . $key);
        if (DBEM_DB::replace_registration($reg->id, $pending['data']) === false) {
            self::update_page(__('Errore durante l\'aggiornamento. Riprova.', 'db-event-manager'), 500);
        }

        $reg = DBEM_DB::get_registration($reg->id);
        self::send_registration_emails($reg->event_id, $reg);

        self::update_page(sprintf(__('Iscrizione a "%s" aggiornata. Controlla la tua email per i dati aggiornati.', 'db-event-manager'), $event_title));
    }

    /**
     * Pagina di esito per il link di modifica
     */
    private static function update_page($message, $status = 200, $extra_html = '') {
        // Pagina legata a un link personale: mai in cache di pagina
        DBEM_Security::no_cache_page();
        wp_die(
            '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
            . '<p>' . esc_html($message) . '</p>'
            . $extra_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML costruito con valori escapati
            . '</div>',
            esc_html__('Modifica iscrizione', 'db-event-manager'),
            array('response' => (int) $status)
        );
    }

    private static function get_client_ip() {
        return DBEM_Security::client_ip();
    }

    /**
     * Gestisci iscrizione da form DB Form Builder
     * DBFB gestisce il submit del form (validazione, email DBFB, salvataggio in dbfb_submissions).
     * Questo handler crea l'iscrizione evento (registrations, QR code, email conferma).
     */
    public static function handle_dbfb_registration() {
        // Nonce (utenti loggati) oppure origine + rate limit (visitatori anonimi)
        self::verify_registration_request();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce (loggati) o origine + rate limit (anonimi) verificati in verify_registration_request()

        // L'endpoint si può chiamare anche senza passare dal form DBFB e dal suo antispam
        if (!empty($_POST['dbem_website_url'])) {
            wp_send_json_error(__('Richiesta non valida.', 'db-event-manager'));
        }
        self::check_rate_limit();

        $event_id = absint($_POST['event_id'] ?? 0);
        self::require_event($event_id, 'dbfb');

        $name = sanitize_text_field(wp_unslash($_POST['dbem_name'] ?? ''));
        $email = strtolower(sanitize_email(wp_unslash($_POST['dbem_email'] ?? '')));

        if (empty($name) || !is_email($email)) {
            wp_send_json_error(__('Nome e email sono obbligatori.', 'db-event-manager'));
        }

        DBEM_DB::ensure_tables();
        self::require_open($event_id);
        $existing = DBEM_DB::get_registration_by_email($event_id, $email);

        // Posti
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);
        if ($max > 0 && DBEM_DB::count_registrations($event_id) >= $max && !$existing) {
            wp_send_json_error(__('I posti sono esauriti.', 'db-event-manager'));
        }

        // Dati extra dal form DBFB
        $extra_data = array();
        $raw_data = wp_unslash($_POST['dbem_data'] ?? '{}'); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON: ogni chiave e valore viene sanitizzato sotto
        $decoded = json_decode($raw_data, true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                $extra_data[sanitize_text_field($k)] = sanitize_text_field($v);
            }
        }

        $token = bin2hex(random_bytes(32));
        $ip = self::get_client_ip();

        $approval_mode = get_post_meta($event_id, '_dbem_approval_mode', true) ?: 'auto';
        $initial_status = ($approval_mode === 'approval') ? 'pending' : 'confirmed';

        // Cattura prova del consenso.
        // La checkbox privacy è gestita dal form DBFB: registriamo la prova SOLO se
        // il campo privacy è stato mappato nelle impostazioni evento ed è risultato
        // spuntato. Senza una spunta verificabile il consenso resta NULL — non si
        // fabbrica una prova che non abbiamo (art. 7.1 GDPR).
        $gdpr_consent_given          = null;
        $gdpr_consent_text           = null;
        $gdpr_consent_timestamp      = null;
        $gdpr_consent_privacy_url    = null;
        $gdpr_consent_policy_version = 0;

        $gdpr_enabled  = get_post_meta($event_id, '_dbem_gdpr_enabled', true);
        $privacy_field = get_post_meta($event_id, '_dbem_dbfb_privacy_field', true);
        $privacy_given = !empty($_POST['dbem_privacy']);

        // Se il campo privacy è mappato ma non risulta spuntato, l'iscrizione è rifiutata
        if ($gdpr_enabled === '1' && $privacy_field && !$privacy_given) {
            wp_send_json_error(__('Devi accettare l\'informativa sulla privacy.', 'db-event-manager'));
        }

        if ($gdpr_enabled === '1' && $privacy_given) {
            $gdpr_consent_given     = 1;
            $gdpr_consent_text      = get_post_meta($event_id, '_dbem_gdpr_text', true);
            if (empty($gdpr_consent_text)) {
                $gdpr_consent_text = __('Acconsento al trattamento dei dati personali secondo la Privacy Policy', 'db-event-manager');
            }
            $gdpr_consent_timestamp = current_time('mysql');
            $privacy_url = get_post_meta($event_id, '_dbem_gdpr_link', true);
            if (empty($privacy_url) && function_exists('get_privacy_policy_url')) {
                $privacy_url = (string) get_privacy_policy_url();
            }
            $gdpr_consent_privacy_url = $privacy_url ?: null;
            if (class_exists('DBPH_Policy_Archive') && method_exists('DBPH_Policy_Archive', 'get_current_version_id')) {
                $gdpr_consent_policy_version = (int) DBPH_Policy_Archive::get_current_version_id();
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $registration_data = array(
            'data'          => wp_json_encode(array('nome' => $name, 'email' => $email) + $extra_data),
            'email'         => $email,
            'name'          => $name,
            'gdpr_consent_given'          => $gdpr_consent_given,
            'gdpr_consent_text'           => $gdpr_consent_text,
            'gdpr_consent_timestamp'      => $gdpr_consent_timestamp,
            'gdpr_consent_privacy_url'    => $gdpr_consent_privacy_url,
            'gdpr_consent_policy_version' => $gdpr_consent_policy_version,
            'ip_address'    => $ip,
        );

        if ($existing) {
            self::handle_existing($event_id, $existing, $registration_data);
        }

        $result = $wpdb->insert($table, array(
            'event_id'      => $event_id,
            'data'          => $registration_data['data'],
            'email'         => $registration_data['email'],
            'name'          => $registration_data['name'],
            'token'         => $token,
            'status'        => $initial_status,
            'registered_at' => current_time('mysql'),
            'gdpr_consent_given'          => $registration_data['gdpr_consent_given'],
            'gdpr_consent_text'           => $registration_data['gdpr_consent_text'],
            'gdpr_consent_timestamp'      => $registration_data['gdpr_consent_timestamp'],
            'gdpr_consent_privacy_url'    => $registration_data['gdpr_consent_privacy_url'],
            'gdpr_consent_policy_version' => $registration_data['gdpr_consent_policy_version'],
            'ip_address'    => $registration_data['ip_address'],
        ), array(
            '%d', // event_id
            '%s', // data
            '%s', // email
            '%s', // name
            '%s', // token
            '%s', // status
            '%s', // registered_at
            '%d', // gdpr_consent_given
            '%s', // gdpr_consent_text
            '%s', // gdpr_consent_timestamp
            '%s', // gdpr_consent_privacy_url
            '%d', // gdpr_consent_policy_version
            '%s', // ip_address
        ));
        $reg_id = $wpdb->insert_id;

        if ($result === false) {
            wp_send_json_error(__('Errore durante la registrazione.', 'db-event-manager'));
        }

        $reg = DBEM_DB::get_registration($reg_id);
        if (!$reg) {
            wp_send_json_error(__('Errore durante la registrazione.', 'db-event-manager'));
        }
        self::send_registration_emails($event_id, $reg);

        wp_send_json_success(array(
            'message' => self::received_message(),
        ));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
}
