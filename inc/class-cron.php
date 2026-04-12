<?php
if (!defined('ABSPATH')) exit;

class DBEM_Cron {

    public static function deactivate() {
        wp_clear_scheduled_hook('dbem_cron_check_events');
    }

    /**
     * Check eventi ogni ora: chiusura automatica iscrizioni
     */
    public static function check_events() {
        $events = get_posts(array(
            'post_type'      => 'dbem_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_dbem_registration_open',
                    'value' => '1',
                ),
            ),
        ));

        foreach ($events as $event) {
            // Chiudi se deadline passata
            $deadline = get_post_meta($event->ID, '_dbem_registration_deadline', true);
            if ($deadline && strtotime($deadline) < time()) {
                update_post_meta($event->ID, '_dbem_registration_open', '0');
                continue;
            }

            // Chiudi se posti esauriti
            $max = (int) get_post_meta($event->ID, '_dbem_max_participants', true);
            if ($max > 0) {
                $count = DBEM_DB::count_registrations($event->ID);
                if ($count >= $max) {
                    update_post_meta($event->ID, '_dbem_registration_open', '0');
                }
            }
        }
    }

    /**
     * Invia promemoria evento
     */
    public static function send_reminder($event_id) {
        DBEM_DB::ensure_tables();
        $regs = DBEM_DB::get_registrations($event_id, 'confirmed');

        foreach ($regs as $reg) {
            DBEM_Email::send_reminder($event_id, $reg);
        }
    }

    /**
     * Invia survey automatico dopo X ore dalla fine evento
     */
    public static function send_survey_auto($event_id) {
        $survey_enabled = get_post_meta($event_id, '_dbem_survey_enabled', true);
        if ($survey_enabled !== '1') return;

        DBEM_DB::ensure_tables();
        // Invia solo a chi ha fatto check-in
        $regs = DBEM_DB::get_registrations($event_id, 'checked_in');

        foreach ($regs as $reg) {
            if (!DBEM_DB::has_survey_response($reg->id)) {
                DBEM_Email::send_survey_email($event_id, $reg);
            }
        }
    }
}
