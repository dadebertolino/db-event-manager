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

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        // Allega anche il PNG per chi non carica immagini esterne
        $attachments = array();
        if (file_exists($qr_path)) {
            $attachments[] = $qr_path;
        }

        return wp_mail($reg->email, $subject, $html, $headers, $attachments);
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

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        $html = self::build_html_email($message);

        return wp_mail($recipients, $subject, $html, $headers);
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

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        return wp_mail($reg->email, $subject, $html, $headers);
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

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        return wp_mail($reg->email, $subject, $html, $headers);
    }

    /**
     * Invia email promemoria
     */
    public static function send_reminder($event_id, $reg) {
        $event_title = DBEM_CPT::get_event_name($event_id);
        $start = get_post_meta($event_id, '_dbem_date_start', true);
        $location = get_post_meta($event_id, '_dbem_location', true);

        $subject = sprintf(__('Promemoria: %s', 'db-event-manager'), $event_title);

        $date_formatted = $start ? date('d/m/Y H:i', strtotime($start)) : '';

        $message = sprintf(
            __("Ciao %s,\n\nti ricordiamo che l'evento \"%s\" è in programma!\n\n📅 Data: %s\n📍 Luogo: %s\n\nNon dimenticare di portare il QR code per il check-in.\n\nA presto!", 'db-event-manager'),
            $reg->name,
            $event_title,
            $date_formatted,
            $location
        );

        // QR code URL + allegato
        $upload_dir = wp_upload_dir();
        $qr_path = $upload_dir['basedir'] . '/dbem/qrcodes/' . $reg->token . '.png';
        $qr_url = $upload_dir['baseurl'] . '/dbem/qrcodes/' . $reg->token . '.png';

        $html = self::build_html_email($message, file_exists($qr_path) ? $qr_url : '');

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        $attachments = array();
        if (file_exists($qr_path)) {
            $attachments[] = $qr_path;
        }

        return wp_mail($reg->email, $subject, $html, $headers, $attachments);
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

        return array(
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

    private static function replace_placeholders($text, $placeholders) {
        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
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
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        return wp_mail($reg->email, $subject, $html, $headers);
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

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        return wp_mail($recipients, $subject, $html, $headers);
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
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        return wp_mail($reg->email, $subject, $html, $headers);
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