<?php
if (!defined('ABSPATH')) exit;

class DBEM_Email {

    /**
     * Invia email di conferma iscrizione
     */
    public static function send_confirmation($event_id, $reg) {
        $email_data = get_post_meta($event_id, '_dbem_confirmation_email', true);
        if (!$email_data || empty($email_data['subject']) || empty($email_data['message'])) return false;

        $placeholders = self::get_placeholders($event_id, $reg);
        $subject = self::replace_placeholders($email_data['subject'], $placeholders);
        $message = self::replace_placeholders($email_data['message'], $placeholders);

        // Se c'è un orario assegnato e il template non usa {orario}, aggiungilo in fondo
        $assigned_time = isset($reg->assigned_time) ? $reg->assigned_time : '';
        if (!empty($assigned_time) && strpos($email_data['message'], '{orario}') === false) {
            $message .= "\n\n🕐 " . __('Orario assegnato:', 'db-event-manager') . ' ' . $assigned_time;
        }

        // QR code
        $upload_dir = wp_upload_dir();
        $qr_path = $upload_dir['basedir'] . '/dbem/qrcodes/' . $reg->token . '.png';
        $qr_url = $upload_dir['baseurl'] . '/dbem/qrcodes/' . $reg->token . '.png';

        // URL pubblico per <img> nel corpo (funziona su tutti i client)
        $html = self::build_html_email($message, file_exists($qr_path) ? $qr_url : '');

        $headers = self::get_headers();

        // Allega anche il PNG per chi non carica immagini esterne
        $attachments = array();
        if (file_exists($qr_path)) {
            $attachments[] = $qr_path;
        }

        return wp_mail($reg->email, self::clean_subject($subject), $html, $headers, $attachments);
    }

    /**
     * Notifica admin nuova iscrizione
     */
    public static function notify_admin($event_id, $reg) {
        $event_title = DBEM_CPT::get_event_name($event_id);

        // Email destinatario personalizzata per evento, fallback a admin del sito
        $admin_email = get_post_meta($event_id, '_dbem_admin_email', true);
        if (!$admin_email) $admin_email = get_option('admin_email');

        // Supporta più email separate da virgola
        $recipients = array_map('trim', explode(',', $admin_email));
        $recipients = array_filter($recipients, 'is_email');
        if (empty($recipients)) return false;

        $subject = sprintf(__('[%s] Nuova iscrizione: %s', 'db-event-manager'), $event_title, $reg->name);

        $count = DBEM_DB::count_registrations($event_id);
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);
        $spots_info = $max > 0 ? "$count / $max" : "$count";

        $message = sprintf(
            __("Nuova iscrizione per \"%s\"\n\nNome: %s\nEmail: %s\nIscritti: %s\n\nGestisci: %s", 'db-event-manager'),
            $event_title,
            $reg->name,
            $reg->email,
            $spots_info,
            admin_url('edit.php?post_type=dbem_event&page=dbem-participants&event_id=' . $event_id)
        );

        $headers = self::get_headers();

        $html = self::build_html_email($message);

        return wp_mail($recipients, self::clean_subject($subject), $html, $headers);
    }

    /**
     * Invia email survey
     */
    public static function send_survey_email($event_id, $reg) {
        $survey_email = get_post_meta($event_id, '_dbem_survey_email', true);
        if (!$survey_email || empty($survey_email['subject'])) return false;

        $survey_link = home_url('/?dbem_survey=' . $reg->token);
        $placeholders = self::get_placeholders($event_id, $reg);
        $placeholders['{survey_link}'] = $survey_link;

        $subject = self::replace_placeholders($survey_email['subject'], $placeholders);
        $message = self::replace_placeholders($survey_email['message'], $placeholders);

        $html = self::build_html_email($message);

        $headers = self::get_headers();

        return wp_mail($reg->email, self::clean_subject($subject), $html, $headers);
    }

    /**
     * Invia email annullamento
     */
    public static function send_cancellation($event_id, $reg) {
        $event_title = DBEM_CPT::get_event_name($event_id);
        $subject = sprintf(__('Iscrizione annullata: %s', 'db-event-manager'), $event_title);

        $message = sprintf(
            __("Ciao %s,\n\nla tua iscrizione all'evento \"%s\" è stata annullata.\n\nSe ritieni sia un errore, contattaci.", 'db-event-manager'),
            $reg->name,
            $event_title
        );

        $html = self::build_html_email($message);

        $headers = self::get_headers();

        return wp_mail($reg->email, self::clean_subject($subject), $html, $headers);
    }

    /**
     * Invia email promemoria
     */
    public static function send_reminder($event_id, $reg) {
        $email = self::build_reminder($event_id, $reg);

        $headers = self::get_headers();

        return wp_mail($reg->email, self::clean_subject($email['subject']), $email['html'], $headers, $email['attachments']);
    }

    /**
     * Testo predefinito del promemoria. {dettagli} è il blocco con data, sede, orario
     * e attività, oppure con le opzioni scelte, secondo il "Contenuto del promemoria".
     */
    public static function default_reminder_template() {
        return array(
            'subject' => __('Promemoria: {evento}', 'db-event-manager'),
            'message' => __("Ciao {nome},\n\nti ricordiamo che l'evento \"{evento}\" è in programma!\n\n{dettagli}\nNon dimenticare di portare il QR code per il check-in.\n\nA presto!", 'db-event-manager'),
        );
    }

    /**
     * Testo del promemoria salvato per l'evento, o quello predefinito
     */
    public static function get_reminder_template($event_id) {
        $saved = get_post_meta($event_id, '_dbem_reminder_email', true);
        if (is_array($saved) && !empty($saved['subject']) && !empty($saved['message'])) {
            return array('subject' => $saved['subject'], 'message' => $saved['message'], 'custom' => true);
        }
        return self::default_reminder_template() + array('custom' => false);
    }

    /**
     * Compone il promemoria senza inviarlo: usato dall'invio e dall'anteprima.
     * $template (oggetto e messaggio) serve all'anteprima di un testo non ancora salvato.
     */
    public static function build_reminder($event_id, $reg, $template = null) {
        if (!$template) {
            $template = self::get_reminder_template($event_id);
        }

        $assigned_time = isset($reg->assigned_time) ? trim($reg->assigned_time) : '';
        $time_block = $assigned_time !== ''
            ? "\n🕐 " . __('Il tuo orario:', 'db-event-manager') . ' ' . $assigned_time
            : '';

        // Con l'assegnazione orario l'ora di inizio evento trarrebbe in inganno: solo la data
        $time_slot_enabled = get_post_meta($event_id, '_dbem_time_slot_enabled', true) === '1';
        $period = self::format_event_date_range(
            get_post_meta($event_id, '_dbem_date_start', true),
            get_post_meta($event_id, '_dbem_date_end', true),
            $time_slot_enabled
        );
        $location = get_post_meta($event_id, '_dbem_location', true);
        $choices = self::get_selected_choices($event_id, $reg);
        $registration_details = self::get_registration_details($reg);

        // Se le opzioni del form portano già data e orario, mostrano quelle al posto del periodo generale
        if ($choices !== '' && get_post_meta($event_id, '_dbem_reminder_content', true) === 'options') {
            $details = "📌 " . __('Le tue scelte:', 'db-event-manager') . "\n" . $choices . $time_block . "\n";
        } else {
            $details_block = $registration_details
                ? "\n📌 " . __('Le tue attività prenotate:', 'db-event-manager') . "\n" . $registration_details . "\n"
                : '';
            $details = sprintf(
                __("📅 Periodo generale: %s\n📍 Sede generale: %s", 'db-event-manager'),
                $period,
                $location
            ) . $time_block . $details_block;
        }

        $placeholders = array_merge(self::get_placeholders($event_id, $reg), array(
            '{periodo}'   => $period,
            '{scelte}'    => $choices,
            '{attivita}'  => $registration_details,
            '{dettagli}'  => $details,
        ));
        $subject = self::replace_placeholders($template['subject'], $placeholders);
        $message = self::replace_placeholders($template['message'], $placeholders);

        // QR code URL + allegato
        $upload_dir = wp_upload_dir();
        $qr_path = $upload_dir['basedir'] . '/dbem/qrcodes/' . $reg->token . '.png';
        $qr_url = $upload_dir['baseurl'] . '/dbem/qrcodes/' . $reg->token . '.png';

        $attachments = array();
        if (file_exists($qr_path)) {
            $attachments[] = $qr_path;
        }

        return array(
            'subject'     => $subject,
            'html'        => self::build_html_email($message, file_exists($qr_path) ? $qr_url : ''),
            'attachments' => $attachments,
        );
    }

    /**
     * Opzioni scelte nei campi a scelta del form (selezione, scelta singola, scelta multipla).
     * Un solo campo compilato: un'opzione per riga. Più campi: ogni gruppo sotto la sua etichetta.
     */
    private static function get_selected_choices($event_id, $reg) {
        $fields = get_post_meta($event_id, '_dbem_custom_fields', true);
        $data = json_decode($reg->data, true);
        if (!is_array($fields) || !is_array($data)) return '';

        $groups = array();
        foreach ($fields as $field) {
            if (!in_array($field['type'] ?? '', array('select', 'radio', 'checkbox'), true)) continue;

            $label = $field['label'] ?? '';
            $values = array_filter(array_map('sanitize_text_field', (array) ($data[$label] ?? array())), 'strlen');
            if ($values) {
                $groups[$label] = $values;
            }
        }

        $lines = array();
        foreach ($groups as $label => $values) {
            if (count($groups) > 1) {
                $lines[] = $label . ':';
            }
            foreach ($values as $value) {
                $lines[] = '- ' . $value;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Riepilogo delle attività selezionate nel form dal singolo partecipante
     */
    private static function get_registration_details($reg) {
        $data = json_decode($reg->data, true);
        if (!is_array($data)) return '';

        $lines = array();
        foreach ($data as $label => $value) {
            if (in_array($label, array('nome', 'email'), true) || $value === '' || $value === array()) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('sanitize_text_field', $value)));
            } else {
                $value = self::format_field_date(sanitize_text_field($value));
            }

            if ($value !== '') {
                $lines[] = '- ' . $label . ': ' . $value;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Formatta data singola o intervallo dell'evento
     */
    private static function format_event_date_range($start, $end, $date_only = false) {
        if (!$start) return '';

        $format = $date_only ? 'd/m/Y' : 'd/m/Y H:i';
        $start_timestamp = strtotime($start);
        $start_formatted = date($format, $start_timestamp);
        if (!$end || date('Y-m-d', $start_timestamp) === date('Y-m-d', strtotime($end))) {
            return $start_formatted;
        }

        return $start_formatted . ' - ' . date($format, strtotime($end));
    }

    /**
     * I campi Data del form arrivano come aaaa-mm-gg: li riporta a gg/mm/aaaa
     */
    private static function format_field_date($value) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) return $value;
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return $value;

        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    /**
     * Placeholder per email
     */
    private static function get_placeholders($event_id, $reg) {
        $event_title = DBEM_CPT::get_event_name($event_id);
        $start = get_post_meta($event_id, '_dbem_date_start', true);
        $location = get_post_meta($event_id, '_dbem_location', true);

        // Formato data: il valore datetime-local è già in ora locale, usiamo date() non wp_date()
        // Se l'evento ha assegnazione orario, mostra solo la data
        $time_slot_enabled = get_post_meta($event_id, '_dbem_time_slot_enabled', true);
        if ($start) {
            $ts = strtotime($start);
            $date_formatted = ($time_slot_enabled === '1') ? date('d/m/Y', $ts) : date('d/m/Y H:i', $ts);
        } else {
            $date_formatted = '';
        }

        // Riepilogo dati
        $data = json_decode($reg->data, true);
        $riepilogo = '';
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                if (is_array($val)) $val = implode(', ', $val);
                $riepilogo .= "$key: $val\n";
            }
        }

        $upload_dir = wp_upload_dir();
        $qr_url = $upload_dir['baseurl'] . '/dbem/qrcodes/' . $reg->token . '.png';

        // Orario assegnato (vuoto se non impostato)
        $assigned_time = isset($reg->assigned_time) ? $reg->assigned_time : '';

        return self::field_placeholders($event_id, $reg) + array(
            '{nome}'           => $reg->name,
            '{email}'          => $reg->email,
            '{evento}'         => $event_title,
            '{data_evento}'    => $date_formatted,
            '{luogo}'          => $location,
            '{orario}'         => $assigned_time,
            '{riepilogo_dati}' => $riepilogo,
            '{qrcode_url}'     => $qr_url,
            '{token}'          => $reg->token,
            '{sito}'           => home_url(),
        );
    }

    /**
     * Un segnaposto {campo:id} per ogni campo del form integrato, con il valore compilato.
     * L'id non cambia se si rinomina l'etichetta: il segnaposto continua a funzionare.
     */
    private static function field_placeholders($event_id, $reg) {
        $data = json_decode($reg->data ?? '', true);
        $data = is_array($data) ? $data : array();

        $placeholders = array();
        foreach (DBEM_CPT::get_custom_fields($event_id) as $field) {
            $value = $data[$field['label'] ?? ''] ?? '';
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value), 'strlen'));
            } else {
                $value = self::format_field_date((string) $value);
            }
            $placeholders['{campo:' . $field['id'] . '}'] = $value;
        }
        return $placeholders;
    }

    /**
     * Sostituzione in un solo passaggio: un valore inserito dall'utente che contiene
     * a sua volta un segnaposto (es. un nome "{token}") non viene espanso.
     * I {campo:id} di campi eliminati spariscono invece di restare nel testo.
     */
    private static function replace_placeholders($text, $placeholders) {
        $text = preg_replace_callback('/\{campo:[a-z0-9_-]+\}/', function ($m) use ($placeholders) {
            return isset($placeholders[$m[0]]) ? $m[0] : '';
        }, (string) $text);
        return strtr($text, array_map('strval', $placeholders));
    }

    /**
     * Header comuni: HTML e mittente con il nome del sito, ripulito da caratteri
     * che romperebbero l'header (virgolette, parentesi angolari, a capo)
     */
    public static function get_headers() {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $from_email = self::from_email();
        if (is_email($from_email)) {
            $from_name = trim(str_replace(array('"', '<', '>', "\r", "\n"), '', self::from_name()));
            $headers[] = $from_name !== '' ? 'From: "' . $from_name . '" <' . $from_email . '>' : 'From: ' . $from_email;
        }
        return $headers;
    }

    /**
     * Indirizzo del mittente: quello delle Impostazioni, altrimenti l'email dell'amministratore.
     * Un indirizzo di un altro dominio può far finire le email nello spam (SPF/DMARC).
     */
    public static function from_email() {
        $email = sanitize_email(get_option('dbem_from_email', ''));
        return is_email($email) ? $email : sanitize_email(get_option('admin_email'));
    }

    /**
     * Nome del mittente: quello delle Impostazioni, altrimenti il nome del sito
     */
    public static function from_name() {
        $name = trim((string) get_option('dbem_from_name', ''));
        return $name !== '' ? $name : html_entity_decode(get_bloginfo('name'), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Segnaposto di ogni email, con la descrizione mostrata nell'editor.
     * $context: 'confirmation', 'survey' o 'reminder'.
     */
    public static function placeholders_for($context) {
        $placeholders = array(
            '{nome}'        => __('Nome dell\'iscritto', 'db-event-manager'),
            '{email}'       => __('Email dell\'iscritto', 'db-event-manager'),
            '{evento}'      => __('Nome dell\'evento', 'db-event-manager'),
            '{data_evento}' => __('Data di inizio (solo la data se l\'evento assegna gli orari)', 'db-event-manager'),
            '{luogo}'       => __('Luogo dell\'evento', 'db-event-manager'),
            '{orario}'      => __('Orario assegnato all\'approvazione', 'db-event-manager'),
        );

        if ($context === 'confirmation') {
            $placeholders += array(
                '{riepilogo_dati}' => __('Tutti i campi compilati nel form', 'db-event-manager'),
                '{qrcode_url}'     => __('Indirizzo dell\'immagine del QR code', 'db-event-manager'),
                '{token}'          => __('Codice personale dell\'iscrizione', 'db-event-manager'),
            );
        } elseif ($context === 'survey') {
            $placeholders['{survey_link}'] = __('Link al sondaggio', 'db-event-manager');
        } elseif ($context === 'reminder') {
            $placeholders += array(
                '{periodo}'  => __('Date di inizio e fine', 'db-event-manager'),
                '{dettagli}' => __('Blocco con data, sede, orario e attività, secondo il "Contenuto del promemoria"', 'db-event-manager'),
                '{scelte}'   => __('Opzioni scelte nei campi a scelta', 'db-event-manager'),
                '{attivita}' => __('Tutti i campi compilati', 'db-event-manager'),
            );
        }

        $placeholders['{sito}'] = __('Indirizzo del sito', 'db-event-manager');
        return $placeholders;
    }

    /**
     * Oggetto su una riga sola
     */
    private static function clean_subject($subject) {
        return trim(preg_replace('/[\r\n]+/', ' ', (string) $subject));
    }

    /**
     * Link di conferma per modificare un'iscrizione esistente, inviato all'indirizzo dell'iscrizione
     */
    public static function send_update_confirmation($event_id, $reg, $confirm_url) {
        $event_title = DBEM_CPT::get_event_name($event_id);
        $subject = sprintf(__('Conferma la modifica della tua iscrizione: %s', 'db-event-manager'), $event_title);
        $message = sprintf(
            __("Ciao %s,\n\nabbiamo ricevuto una richiesta di modifica della tua iscrizione all'evento \"%s\".\n\nPer confermarla apri questo link entro 24 ore:\n%s\n\nSe non hai chiesto tu la modifica, ignora questa email: la tua iscrizione resta com'è.", 'db-event-manager'),
            $reg->name,
            $event_title,
            $confirm_url
        );

        return wp_mail($reg->email, self::clean_subject($subject), self::build_html_email($message), self::get_headers());
    }

    private static function build_html_email($message, $qr_url = '') {
        $site_name = get_bloginfo('name');
        $message_html = nl2br(esc_html($message));
        $message_html = preg_replace('/(https?:\/\/[^\s<]+)/', '<a href="$1" style="color:#2271b1;">$1</a>', $message_html);

        $qr_block = '';
        if ($qr_url) {
            $qr_block = '<div style="text-align:center;margin:20px 0;">
                <img src="' . esc_url($qr_url) . '" alt="QR Code" style="width:200px;height:200px;border:1px solid #ddd;border-radius:8px;">
                <p style="color:#666;font-size:13px;">' . esc_html__('Presenta questo QR code all\'ingresso', 'db-event-manager') . '</p>
            </div>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333;">
            <div style="background:#2271b1;color:#fff;padding:20px;border-radius:8px 8px 0 0;text-align:center;">
                <h2 style="margin:0;font-size:20px;">' . esc_html($site_name) . '</h2>
            </div>
            <div style="background:#fff;padding:24px;border:1px solid #ddd;border-top:none;border-radius:0 0 8px 8px;">
                ' . $message_html . '
                ' . $qr_block . '
            </div>
            <p style="text-align:center;color:#999;font-size:12px;margin-top:16px;">' . esc_html($site_name) . '</p>
        </body></html>';
    }

    /**
     * Email "iscrizione in attesa di approvazione" all'iscritto
     */
    public static function send_pending_notification($event_id, $reg) {
        $event_title = DBEM_CPT::get_event_name($event_id);

        $subject = sprintf(__('Iscrizione ricevuta: %s', 'db-event-manager'), $event_title);
        $message = sprintf(
            __("Ciao %s,\n\nla tua iscrizione all'evento \"%s\" è stata ricevuta ed è in attesa di approvazione.\n\nRiceverai una email di conferma quando l'iscrizione sarà approvata.\n\nGrazie!", 'db-event-manager'),
            $reg->name,
            $event_title
        );

        $html = self::build_html_email($message);
        $headers = self::get_headers();

        return wp_mail($reg->email, self::clean_subject($subject), $html, $headers);
    }

    /**
     * Email richiesta approvazione all'approvatore (con link approva/rifiuta)
     */
    public static function send_approval_request($event_id, $reg) {
        $event_title = DBEM_CPT::get_event_name($event_id);

        // Determina destinatario
        $approver = get_post_meta($event_id, '_dbem_approver_email', true);
        if (!$approver) {
            $approver = get_post_meta($event_id, '_dbem_admin_email', true);
        }
        if (!$approver) {
            $approver = get_option('admin_email');
        }
        $recipients = array_filter(array_map('trim', explode(',', $approver)), 'is_email');
        if (empty($recipients)) return false;

        // Link approvazione e rifiuto
        $approve_url = home_url('/?dbem_action=approve&token=' . $reg->token . '&key=' . self::generate_action_key($reg->token, 'approve'));
        $reject_url = home_url('/?dbem_action=reject&token=' . $reg->token . '&key=' . self::generate_action_key($reg->token, 'reject'));

        $subject = sprintf(__('[%s] Richiesta approvazione: %s', 'db-event-manager'), $event_title, $reg->name);

        $data = json_decode($reg->data, true);
        $riepilogo = '';
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                if (is_array($val)) $val = implode(', ', $val);
                $riepilogo .= "$key: $val\n";
            }
        }

        $message = sprintf(
            __("Nuova richiesta di iscrizione per \"%s\"\n\nNome: %s\nEmail: %s\n\n%s\n\n✅ APPROVA:\n%s\n\n❌ RIFIUTA:\n%s\n\nPuoi anche gestire le iscrizioni dal pannello admin:\n%s", 'db-event-manager'),
            $event_title,
            $reg->name,
            $reg->email,
            $riepilogo,
            $approve_url,
            $reject_url,
            admin_url('edit.php?post_type=dbem_event&page=dbem-participants&event_id=' . $event_id)
        );

        $html = self::build_html_email_with_buttons($message, $approve_url, $reject_url);

        $headers = self::get_headers();

        return wp_mail($recipients, self::clean_subject($subject), $html, $headers);
    }

    /**
     * Email rifiuto iscrizione
     */
    public static function send_rejection($event_id, $reg) {
        $event_title = DBEM_CPT::get_event_name($event_id);

        $subject = sprintf(__('Iscrizione non approvata: %s', 'db-event-manager'), $event_title);
        $message = sprintf(
            __("Ciao %s,\n\nci dispiace, la tua iscrizione all'evento \"%s\" non è stata approvata.\n\nPer informazioni, puoi contattarci.", 'db-event-manager'),
            $reg->name,
            $event_title
        );

        $html = self::build_html_email($message);
        $headers = self::get_headers();

        return wp_mail($reg->email, self::clean_subject($subject), $html, $headers);
    }

    /**
     * Genera chiave di sicurezza per link approvazione/rifiuto
     */
    public static function generate_action_key($token, $action) {
        return hash_hmac('sha256', $token . $action, wp_salt('auth'));
    }

    /**
     * Verifica chiave di sicurezza
     */
    public static function verify_action_key($token, $action, $key) {
        return hash_equals(self::generate_action_key($token, $action), $key);
    }

    /**
     * Email HTML con bottoni approva/rifiuta
     */
    private static function build_html_email_with_buttons($message, $approve_url, $reject_url) {
        $site_name = get_bloginfo('name');
        $message_html = nl2br(esc_html($message));
        // Rimuovi i link plain text dagli URL approvazione (li sostituiamo con bottoni)
        $message_html = str_replace(
            array(esc_html($approve_url), esc_html($reject_url)),
            array('', ''),
            $message_html
        );

        $buttons = '<div style="text-align:center;margin:24px 0;">
            <a href="' . esc_url($approve_url) . '" style="display:inline-block;padding:14px 32px;background:#1d6e3f;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px;margin:6px;">✅ Approva</a>
            <a href="' . esc_url($reject_url) . '" style="display:inline-block;padding:14px 32px;background:#d63638;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px;margin:6px;">❌ Rifiuta</a>
        </div>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333;">
            <div style="background:#2271b1;color:#fff;padding:20px;border-radius:8px 8px 0 0;text-align:center;">
                <h2 style="margin:0;font-size:20px;">' . esc_html($site_name) . '</h2>
            </div>
            <div style="background:#fff;padding:24px;border:1px solid #ddd;border-top:none;border-radius:0 0 8px 8px;">
                ' . $message_html . '
                ' . $buttons . '
            </div>
            <p style="text-align:center;color:#999;font-size:12px;margin-top:16px;">' . esc_html($site_name) . '</p>
        </body></html>';
    }
}