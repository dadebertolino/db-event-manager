<?php
if (!defined('ABSPATH')) exit;

/**
 * Privacy DSAR — Capability 3
 * 
 * Gestisce le richieste di accesso e cancellazione dati personali (GDPR art. 15, 17).
 * Dual channel: Privacy Hub (primario) + WP core (fallback).
 * Copre: registrazioni eventi, risposte survey, file QR code.
 */
class DBEM_Privacy_DSAR {

    public static function init() {
        // Canale primario: Privacy Hub
        add_filter('dbph_user_data_exporters', array(__CLASS__, 'register_exporter_via_hub'));
        add_filter('dbph_user_data_erasers',   array(__CLASS__, 'register_eraser_via_hub'));

        // Canale fallback: WP core (attivo solo quando Hub NON è installato)
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers',   array(__CLASS__, 'register_eraser'));
    }

    /* === Canale Hub === */

    public static function register_exporter_via_hub($exporters) {
        $exporters['db-event-manager'] = array(
            'label'    => __('DB Event Manager — Iscrizioni eventi', 'db-event-manager'),
            'callback' => array(__CLASS__, 'exporter_callback'),
        );
        return $exporters;
    }

    public static function register_eraser_via_hub($erasers) {
        $erasers['db-event-manager'] = array(
            'label'    => __('DB Event Manager — Iscrizioni eventi', 'db-event-manager'),
            'callback' => array(__CLASS__, 'eraser_callback'),
        );
        return $erasers;
    }

    /* === Canale WP fallback === */

    public static function register_exporter($exporters) {
        if (class_exists('DBPH_DSAR')) {
            return $exporters; // Hub presente, skip
        }
        $exporters['db-event-manager'] = array(
            'exporter_friendly_name' => __('DB Event Manager — Iscrizioni eventi', 'db-event-manager'),
            'callback'               => array(__CLASS__, 'exporter_callback'),
        );
        return $exporters;
    }

    public static function register_eraser($erasers) {
        if (class_exists('DBPH_DSAR')) {
            return $erasers;
        }
        $erasers['db-event-manager'] = array(
            'eraser_friendly_name' => __('DB Event Manager — Iscrizioni eventi', 'db-event-manager'),
            'callback'             => array(__CLASS__, 'eraser_callback'),
        );
        return $erasers;
    }

    /* === Callback export === */

    public static function exporter_callback($email_address, $page = 1) {
        $email = strtolower(trim((string) $email_address));
        $per_page = 100;
        $offset = ($page - 1) * $per_page;

        DBEM_DB::ensure_tables();
        global $wpdb;
        $reg_table = $wpdb->prefix . 'dbem_registrations';
        $survey_table = $wpdb->prefix . 'dbem_survey_responses';

        // Registrazioni
        $regs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $reg_table WHERE LOWER(email) = %s ORDER BY id ASC LIMIT %d OFFSET %d",
            $email, $per_page, $offset
        ));

        $data = array();

        foreach ($regs as $reg) {
            $event_title = DBEM_CPT::get_event_name($reg->event_id);
            $fields = json_decode($reg->data, true);

            $export_data = array(
                array('name' => __('Evento', 'db-event-manager'), 'value' => $event_title),
                array('name' => __('Nome', 'db-event-manager'), 'value' => $reg->name),
                array('name' => __('Email', 'db-event-manager'), 'value' => $reg->email),
                array('name' => __('Stato', 'db-event-manager'), 'value' => $reg->status),
                array('name' => __('Data iscrizione', 'db-event-manager'), 'value' => $reg->registered_at),
                array('name' => __('Check-in', 'db-event-manager'), 'value' => $reg->checked_in_at ?: __('Non presente', 'db-event-manager')),
                array('name' => __('Orario assegnato', 'db-event-manager'), 'value' => (isset($reg->assigned_time) && $reg->assigned_time) ? $reg->assigned_time : '—'),
                array('name' => __('IP', 'db-event-manager'), 'value' => $reg->ip_address),
            );

            // Campi custom del form
            if (is_array($fields)) {
                foreach ($fields as $key => $val) {
                    if (in_array($key, array('nome', 'email'))) continue;
                    if (is_array($val)) $val = implode(', ', $val);
                    $export_data[] = array('name' => $key, 'value' => $val);
                }
            }

            // Campi consenso (se presenti)
            if (isset($reg->gdpr_consent_given) && $reg->gdpr_consent_given !== null) {
                $export_data[] = array('name' => __('Consenso GDPR', 'db-event-manager'), 'value' => $reg->gdpr_consent_given ? __('Sì', 'db-event-manager') : __('No', 'db-event-manager'));
                $export_data[] = array('name' => __('Testo consenso', 'db-event-manager'), 'value' => $reg->gdpr_consent_text ?? '');
                $export_data[] = array('name' => __('Timestamp consenso', 'db-event-manager'), 'value' => $reg->gdpr_consent_timestamp ?? '');
                $export_data[] = array('name' => __('URL privacy', 'db-event-manager'), 'value' => $reg->gdpr_consent_privacy_url ?? '');
                $export_data[] = array(
                    'name'  => __('Versione informativa privacy', 'db-event-manager'),
                    'value' => !empty($reg->gdpr_consent_policy_version)
                        /* translators: %d: ID dello snapshot della Privacy Policy in DB Privacy Hub */
                        ? sprintf(__('v#%d (snapshot DB Privacy Hub)', 'db-event-manager'), (int) $reg->gdpr_consent_policy_version)
                        : __('Non registrata (Privacy Hub assente al momento del consenso)', 'db-event-manager'),
                );
            }

            $data[] = array(
                'group_id'    => 'db-event-manager-registrations',
                'group_label' => __('DB Event Manager — Iscrizioni eventi', 'db-event-manager'),
                'item_id'     => 'registration-' . $reg->id,
                'data'        => $export_data,
            );

            // Survey associato
            $survey = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $survey_table WHERE registration_id = %d",
                $reg->id
            ));

            if ($survey) {
                $survey_data_parsed = json_decode($survey->data, true);
                $survey_export = array(
                    array('name' => __('Evento', 'db-event-manager'), 'value' => $event_title),
                    array('name' => __('Data risposta', 'db-event-manager'), 'value' => $survey->submitted_at),
                );
                if (is_array($survey_data_parsed)) {
                    foreach ($survey_data_parsed as $key => $val) {
                        if (is_array($val)) $val = implode(', ', $val);
                        $survey_export[] = array('name' => $key, 'value' => $val);
                    }
                }

                $data[] = array(
                    'group_id'    => 'db-event-manager-surveys',
                    'group_label' => __('DB Event Manager — Risposte survey', 'db-event-manager'),
                    'item_id'     => 'survey-' . $survey->id,
                    'data'        => $survey_export,
                );
            }
        }

        return array(
            'data' => $data,
            'done' => count($regs) < $per_page,
        );
    }

    /* === Callback erase === */

    public static function eraser_callback($email_address, $page = 1) {
        $email = strtolower(trim((string) $email_address));
        $per_page = 100;

        DBEM_DB::ensure_tables();
        global $wpdb;
        $reg_table = $wpdb->prefix . 'dbem_registrations';

        $regs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, token FROM $reg_table WHERE LOWER(email) = %s ORDER BY id ASC LIMIT %d",
            $email, $per_page
        ));

        $messages = array();

        // Iscrizione, risposte ai sondaggi e file del QR code
        $items_removed = DBEM_DB::delete_registrations(wp_list_pluck($regs, 'id'));

        if ($items_removed > 0) {
            $messages[] = sprintf(
                __('%d iscrizione/i e relativi dati (QR code, survey) cancellati.', 'db-event-manager'),
                $items_removed
            );
        }

        return array(
            'items_removed'  => $items_removed,
            'items_retained' => 0,
            'messages'       => $messages,
            'done'           => count($regs) < $per_page,
        );
    }
}
