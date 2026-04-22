<?php
if (!defined('ABSPATH')) exit;

class DBEM_Export {

    /**
     * Export CSV partecipanti
     */
    public static function handle_export() {
        check_ajax_referer('dbem_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(__('Accesso negato', 'db-event-manager'));

        $event_id = absint($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
        if (!$event_id) wp_die(__('Evento mancante', 'db-event-manager'));

        DBEM_DB::ensure_tables();
        $regs = DBEM_DB::get_registrations($event_id, null, 'registered_at', 'ASC');
        $event_title = sanitize_file_name(DBEM_CPT::get_event_name($event_id));

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="partecipanti-' . $event_title . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

        // Header
        $headers = array('ID', 'Nome', 'Email', 'Stato', 'Data Iscrizione', 'Check-in', 'Orario assegnato', 'IP');

        // Determina campi custom dalle iscrizioni
        $custom_keys = array();
        foreach ($regs as $reg) {
            $data = json_decode($reg->data, true);
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    if (!in_array($k, array('nome', 'email')) && !in_array($k, $custom_keys)) {
                        $custom_keys[] = $k;
                    }
                }
            }
        }
        $headers = array_merge($headers, $custom_keys);
        fputcsv($output, $headers);

        // Dati
        $status_labels = array(
            'confirmed'  => 'Confermato',
            'cancelled'  => 'Annullato',
            'checked_in' => 'Presente',
            'pending'    => 'In attesa',
            'rejected'   => 'Rifiutato',
        );

        foreach ($regs as $reg) {
            $data = json_decode($reg->data, true);
            $row = array(
                $reg->id,
                $reg->name,
                $reg->email,
                $status_labels[$reg->status] ?? $reg->status,
                $reg->registered_at,
                $reg->checked_in_at ?: '',
                isset($reg->assigned_time) ? $reg->assigned_time : '',
                $reg->ip_address,
            );
            foreach ($custom_keys as $ck) {
                $val = $data[$ck] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                $row[] = $val;
            }
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Export CSV survey
     */
    public static function handle_survey_export() {
        check_ajax_referer('dbem_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(__('Accesso negato', 'db-event-manager'));

        $event_id = absint($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
        if (!$event_id) wp_die(__('Evento mancante', 'db-event-manager'));

        DBEM_DB::ensure_tables();
        $responses = DBEM_DB::get_survey_responses($event_id);
        $event_title = sanitize_file_name(DBEM_CPT::get_event_name($event_id));

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="survey-' . $event_title . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Raccogli headers dalle risposte
        $survey_keys = array();
        foreach ($responses as $resp) {
            $data = json_decode($resp->data, true);
            if (is_array($data)) {
                foreach (array_keys($data) as $k) {
                    if (!in_array($k, $survey_keys)) $survey_keys[] = $k;
                }
            }
        }

        $headers = array_merge(array('Nome', 'Email', 'Data risposta'), $survey_keys);
        fputcsv($output, $headers);

        foreach ($responses as $resp) {
            $data = json_decode($resp->data, true);
            $row = array($resp->name, $resp->email, $resp->submitted_at);
            foreach ($survey_keys as $k) {
                $val = $data[$k] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                $row[] = $val;
            }
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
