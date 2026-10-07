<?php
/**
 * Posti e invii automatici (bug #8, #16, #17, #24 del piano)
 */
class SeatsAndCronIntegrationTest extends WP_UnitTestCase {

    private $event_id;

    public function set_up() {
        parent::set_up();
        $this->event_id = self::factory()->post->create(array('post_type' => 'dbem_event', 'post_status' => 'publish'));
        update_post_meta($this->event_id, '_dbem_registration_open', '1');
        update_post_meta($this->event_id, '_dbem_date_start', wp_date('Y-m-d\TH:i', time() + 3 * DAY_IN_SECONDS));
        update_post_meta($this->event_id, '_dbem_date_end', wp_date('Y-m-d\TH:i', time() + 3 * DAY_IN_SECONDS + 2 * HOUR_IN_SECONDS));
        reset_phpmailer_instance();
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}dbem_registrations");
        wp_clear_scheduled_hook('dbem_send_reminder', array($this->event_id));
        wp_clear_scheduled_hook('dbem_send_survey_auto', array($this->event_id));
        parent::tear_down();
    }

    private function add_registration($status, $email) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'dbem_registrations', array(
            'event_id'      => $this->event_id,
            'data'          => '{}',
            'email'         => $email,
            'name'          => 'Iscritto',
            'token'         => bin2hex(random_bytes(32)),
            'status'        => $status,
            'registered_at' => current_time('mysql'),
        ));
        return (int) $wpdb->insert_id;
    }

    public function test_i_rifiutati_non_occupano_posti(): void {
        update_post_meta($this->event_id, '_dbem_max_participants', 3);
        $this->add_registration('confirmed', 'a@example.com');
        $this->add_registration('pending', 'b@example.com');
        $this->add_registration('rejected', 'c@example.com');
        $this->add_registration('cancelled', 'd@example.com');

        $this->assertSame(2, DBEM_DB::count_registrations($this->event_id));
        $this->assertTrue(DBEM_CPT::are_registrations_open($this->event_id));
    }

    public function test_un_posto_liberato_riapre_le_iscrizioni(): void {
        update_post_meta($this->event_id, '_dbem_max_participants', 1);
        $id = $this->add_registration('confirmed', 'a@example.com');

        DBEM_Cron::check_events();
        $this->assertFalse(DBEM_CPT::are_registrations_open($this->event_id));
        // Il cron non chiude più per posti esauriti: niente chiusura permanente
        $this->assertSame('1', get_post_meta($this->event_id, '_dbem_registration_open', true));

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'dbem_registrations', array('status' => 'cancelled'), array('id' => $id));
        $this->assertTrue(DBEM_CPT::are_registrations_open($this->event_id));
    }

    public function test_il_cron_chiude_ancora_a_scadenza_passata(): void {
        update_post_meta($this->event_id, '_dbem_registration_deadline', wp_date('Y-m-d\TH:i', time() - HOUR_IN_SECONDS));

        DBEM_Cron::check_events();

        $this->assertSame('0', get_post_meta($this->event_id, '_dbem_registration_open', true));
    }

    public function test_niente_promemoria_per_eventi_nel_cestino_o_in_bozza(): void {
        $this->add_registration('confirmed', 'a@example.com');

        DBEM_Cron::send_reminder($this->event_id);
        $this->assertNotFalse(tests_retrieve_phpmailer_instance()->get_sent(), 'evento pubblicato: il promemoria parte');

        reset_phpmailer_instance();
        wp_trash_post($this->event_id);
        DBEM_Cron::send_reminder($this->event_id);
        $this->assertFalse(tests_retrieve_phpmailer_instance()->get_sent(), 'evento nel cestino');

        wp_untrash_post($this->event_id);
        wp_update_post(array('ID' => $this->event_id, 'post_status' => 'draft'));
        DBEM_Cron::send_reminder($this->event_id);
        $this->assertFalse(tests_retrieve_phpmailer_instance()->get_sent(), 'evento in bozza');
    }

    public function test_la_riattivazione_riprogramma_gli_invii(): void {
        update_post_meta($this->event_id, '_dbem_reminder_hours', 24);
        DBEM_Cron::schedule_event_sends($this->event_id);
        $this->assertNotFalse(wp_next_scheduled('dbem_send_reminder', array($this->event_id)));

        DBEM_Cron::deactivate();
        $this->assertFalse(wp_next_scheduled('dbem_send_reminder', array($this->event_id)));

        DBEM_DB::activate();
        $this->assertNotFalse(wp_next_scheduled('dbem_send_reminder', array($this->event_id)));
    }
}
