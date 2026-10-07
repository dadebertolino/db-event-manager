<?php
/**
 * Bug #7 del piano: le date sono salvate in ora locale. Con il sito su Europe/Rome
 * scadenze, stato dell'evento, invii programmati e orari mostrati devono seguire
 * l'ora locale, non UTC.
 */
class TimezoneIntegrationTest extends WP_UnitTestCase {

    private $event_id;

    public function set_up() {
        parent::set_up();
        update_option('timezone_string', 'Europe/Rome');
        update_option('gmt_offset', '');
        wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));
        $this->event_id = self::factory()->post->create(array('post_type' => 'dbem_event', 'post_status' => 'publish'));
        update_post_meta($this->event_id, '_dbem_registration_open', '1');
    }

    public function tear_down() {
        $_POST = array();
        wp_clear_scheduled_hook('dbem_send_reminder', array($this->event_id));
        wp_clear_scheduled_hook('dbem_send_survey_auto', array($this->event_id));
        parent::tear_down();
    }

    private function local($offset_seconds) {
        return wp_date('Y-m-d\TH:i', time() + $offset_seconds);
    }

    public function test_scadenza_passata_da_un_ora_chiude_le_iscrizioni(): void {
        update_post_meta($this->event_id, '_dbem_registration_deadline', $this->local(-HOUR_IN_SECONDS));
        update_post_meta($this->event_id, '_dbem_date_start', $this->local(3 * DAY_IN_SECONDS));

        $this->assertFalse(DBEM_CPT::are_registrations_open($this->event_id));

        update_post_meta($this->event_id, '_dbem_registration_deadline', $this->local(HOUR_IN_SECONDS));
        $this->assertTrue(DBEM_CPT::are_registrations_open($this->event_id));
    }

    public function test_stato_dell_evento_in_ora_locale(): void {
        update_post_meta($this->event_id, '_dbem_date_start', $this->local(-2 * HOUR_IN_SECONDS));
        update_post_meta($this->event_id, '_dbem_date_end', $this->local(-HOUR_IN_SECONDS));
        $this->assertSame('past', DBEM_CPT::get_event_status($this->event_id));

        update_post_meta($this->event_id, '_dbem_date_end', $this->local(HOUR_IN_SECONDS));
        $this->assertSame('ongoing', DBEM_CPT::get_event_status($this->event_id));

        update_post_meta($this->event_id, '_dbem_date_start', $this->local(HOUR_IN_SECONDS));
        update_post_meta($this->event_id, '_dbem_date_end', $this->local(2 * HOUR_IN_SECONDS));
        $this->assertSame('upcoming', DBEM_CPT::get_event_status($this->event_id));
    }

    public function test_promemoria_e_survey_programmati_sull_ora_locale(): void {
        $start = wp_date('Y-m-d', time() + 3 * DAY_IN_SECONDS) . 'T18:00';
        $end = wp_date('Y-m-d', time() + 3 * DAY_IN_SECONDS) . 'T20:00';
        $_POST = array(
            'dbem_event_nonce'          => wp_create_nonce('dbem_save_event'),
            '_dbem_date_start'          => $start,
            '_dbem_date_end'            => $end,
            '_dbem_reminder_hours'      => '1',
            '_dbem_survey_auto_hours'   => '2',
        );

        DBEM_Admin::save_metabox($this->event_id, get_post($this->event_id));

        $expected_start = (new DateTimeImmutable($start, new DateTimeZone('Europe/Rome')))->getTimestamp();
        $expected_end = (new DateTimeImmutable($end, new DateTimeZone('Europe/Rome')))->getTimestamp();
        $this->assertSame($expected_start - HOUR_IN_SECONDS, wp_next_scheduled('dbem_send_reminder', array($this->event_id)));
        $this->assertSame($expected_end + 2 * HOUR_IN_SECONDS, wp_next_scheduled('dbem_send_survey_auto', array($this->event_id)));
    }

    public function test_orario_di_check_in_mostrato_come_salvato(): void {
        $now = current_time('mysql');

        $this->assertSame(substr($now, 11, 5), DBEM_Time::format('H:i', $now));
    }
}
