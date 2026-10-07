<?php
/**
 * Bug #44 del piano: registro degli invii; l'invio automatico salta chi ha già
 * ricevuto lo stesso invio a mano
 */
class SendLogIntegrationTest extends WP_UnitTestCase {

    private $event_id;

    public function set_up() {
        parent::set_up();
        $this->event_id = self::factory()->post->create(array('post_type' => 'dbem_event', 'post_status' => 'publish'));
        update_post_meta($this->event_id, '_dbem_survey_enabled', '1');
        update_post_meta($this->event_id, '_dbem_survey_email', array('subject' => 'Com\'è andata?', 'message' => 'Rispondi: {survey_link}'));
        reset_phpmailer_instance();
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}dbem_registrations");
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

    private function sent_count() {
        $mailer = tests_retrieve_phpmailer_instance();
        $n = 0;
        while ($mailer->get_sent($n)) $n++;
        return $n;
    }

    public function test_promemoria_automatico_salta_chi_lo_ha_gia_ricevuto(): void {
        $manual = $this->add_registration('confirmed', 'a@example.com');
        $this->add_registration('confirmed', 'b@example.com');
        DBEM_DB::mark_sent($manual, 'reminder');

        DBEM_Cron::send_reminder($this->event_id);
        $this->assertSame(1, $this->sent_count());

        $log = DBEM_DB::get_send_log($this->event_id, 'reminder');
        $this->assertCount(1, $log);
        $this->assertSame(array('auto', 1, 1), array($log[0]['mode'], $log[0]['sent'], $log[0]['already']));

        // Un secondo giro non scrive più a nessuno
        reset_phpmailer_instance();
        DBEM_Cron::send_reminder($this->event_id);
        $this->assertSame(0, $this->sent_count());
    }

    public function test_survey_automatico_salta_chi_e_gia_stato_invitato(): void {
        $invited = $this->add_registration('checked_in', 'a@example.com');
        $this->add_registration('checked_in', 'b@example.com');
        DBEM_DB::mark_sent($invited, 'survey');

        DBEM_Cron::send_survey_auto($this->event_id);

        $this->assertSame(1, $this->sent_count());
        $this->assertNotEmpty(DBEM_DB::get_registration($invited)->survey_sent_at);
        $this->assertSame(1, DBEM_DB::get_send_log($this->event_id, 'survey')[0]['already']);
    }

    public function test_il_registro_tiene_gli_ultimi_invii(): void {
        for ($i = 0; $i < DBEM_DB::SEND_LOG_MAX + 5; $i++) {
            DBEM_DB::log_send($this->event_id, 'reminder', 'manual', $i, 0);
        }
        $log = DBEM_DB::get_send_log($this->event_id);
        $this->assertCount(DBEM_DB::SEND_LOG_MAX, $log);
        $this->assertSame(DBEM_DB::SEND_LOG_MAX + 4, end($log)['sent']);
    }
}
