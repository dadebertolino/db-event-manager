<?php
if (!defined('ABSPATH')) exit;

class DBEM_Cron {

    /**
     * Hook del plugin: il controllo orario e gli invii singoli, programmati con l'id dell'evento
     */
    const HOOKS = array('dbem_cron_check_events', 'dbem_send_reminder', 'dbem_send_survey_auto');

    /**
     * Programma il controllo orario se manca: activate() non gira con gli aggiornamenti
     */
    public static function schedule() {
        if (!wp_next_scheduled('dbem_cron_check_events')) {
            wp_schedule_event(time(), 'hourly', 'dbem_cron_check_events');
        }
    }

    /**
     * Programma promemoria e survey automatico di un evento dalle sue date; toglie
     * quelli già programmati, così un cambio di data li sposta
     */
    public static function schedule_event_sends($event_id) {
        $event_id = (int) $event_id;

        $reminder_hours = absint(get_post_meta($event_id, '_dbem_reminder_hours', true));
        $start = get_post_meta($event_id, '_dbem_date_start', true);
        wp_clear_scheduled_hook('dbem_send_reminder', array($event_id));
        if ($reminder_hours > 0 && $start) {
            $reminder_time = DBEM_Time::timestamp($start) - ($reminder_hours * 3600);
            if ($reminder_time > time()) {
                wp_schedule_single_event($reminder_time, 'dbem_send_reminder', array($event_id));
            }
        }

        $survey_auto = absint(get_post_meta($event_id, '_dbem_survey_auto_hours', true));
        $end = get_post_meta($event_id, '_dbem_date_end', true);
        wp_clear_scheduled_hook('dbem_send_survey_auto', array($event_id));
        if ($survey_auto > 0 && $end) {
            $send_time = DBEM_Time::timestamp($end) + ($survey_auto * 3600);
            if ($send_time > time()) {
                wp_schedule_single_event($send_time, 'dbem_send_survey_auto', array($event_id));
            }
        }
    }

    /**
     * Riattivazione: deactivate() ha tolto anche gli invii dei singoli eventi, che
     * altrimenti tornerebbero solo risalvando ogni evento
     */
    public static function reschedule_all_events() {
        $events = get_posts(array(
            'post_type'      => 'dbem_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));
        foreach ($events as $event_id) {
            self::schedule_event_sends($event_id);
        }
    }

    /**
     * wp_unschedule_hook rimuove anche gli eventi programmati con argomenti,
     * che wp_clear_scheduled_hook senza argomenti non trova
     */
    public static function deactivate() {
        foreach (self::HOOKS as $hook) {
            wp_unschedule_hook($hook);
        }
    }

    /**
     * Check eventi ogni ora: chiusura automatica delle iscrizioni a scadenza passata.
     * I posti esauriti non chiudono: are_registrations_open() li conta al momento, così
     * un posto liberato (annullamento, rifiuto) riapre le iscrizioni da solo
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
            if ($deadline && DBEM_Time::is_past($deadline)) {
                update_post_meta($event->ID, '_dbem_registration_open', '0');
            }
        }
    }

    /**
     * Invia promemoria evento
     */
    public static function send_reminder($event_id) {
        // Evento nel cestino, tornato in bozza o reso privato: niente invii
        if (get_post_status($event_id) !== 'publish') return;

        DBEM_DB::ensure_tables();
        $regs = DBEM_DB::get_reminder_registrations($event_id);

        foreach ($regs as $reg) {
            DBEM_Email::send_reminder($event_id, $reg);
        }
    }

    /**
     * Invia survey automatico dopo X ore dalla fine evento
     */
    public static function send_survey_auto($event_id) {
        if (get_post_status($event_id) !== 'publish') return;

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
