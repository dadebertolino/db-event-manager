<?php
/**
 * Export e cancellazione dei dati personali (bug #34, #35 del piano)
 */
class DsarIntegrationTest extends WP_UnitTestCase {

    private $event_id;

    public function set_up() {
        parent::set_up();
        $this->event_id = self::factory()->post->create(array('post_type' => 'dbem_event', 'post_status' => 'publish'));
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}dbem_registrations");
        parent::tear_down();
    }

    private function add_registrations($email, $count) {
        global $wpdb;
        for ($i = 0; $i < $count; $i++) {
            $wpdb->insert($wpdb->prefix . 'dbem_registrations', array(
                'event_id'      => $this->event_id,
                'data'          => '{}',
                'email'         => $email,
                'name'          => 'Anna',
                'token'         => bin2hex(random_bytes(32)),
                'status'        => 'confirmed',
                'registered_at' => current_time('mysql'),
            ));
        }
    }

    public function test_export_a_pagine_oltre_le_100_iscrizioni(): void {
        $this->add_registrations('anna@example.com', 150);
        $this->add_registrations('altro@example.com', 3);

        $first = DBEM_Privacy_DSAR::exporter_callback('Anna@Example.com', 1);
        $second = DBEM_Privacy_DSAR::exporter_callback('Anna@Example.com', 2);

        $this->assertCount(100, $first['data']);
        $this->assertFalse($first['done']);
        $this->assertCount(50, $second['data']);
        $this->assertTrue($second['done']);
    }

    public function test_cancellazione_completa_e_ripetibile(): void {
        $this->add_registrations('anna@example.com', 120);
        $this->add_registrations('altro@example.com', 2);

        $first = DBEM_Privacy_DSAR::eraser_callback('anna@example.com', 1);
        $this->assertTrue($first['items_removed']);
        $this->assertFalse($first['done']);
        $second = DBEM_Privacy_DSAR::eraser_callback('anna@example.com', 2);
        $this->assertTrue($second['done']);
        $again = DBEM_Privacy_DSAR::eraser_callback('anna@example.com', 1);
        $this->assertFalse($again['items_removed']);
        $this->assertTrue($again['done']);

        global $wpdb;
        $this->assertSame('2', $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dbem_registrations"));
    }

    public function test_la_modifica_in_attesa_viene_cancellata(): void {
        set_transient('dbem_update_abc', array('registration_id' => 1, 'email' => 'anna@example.com', 'data' => array('name' => 'Anna')), DAY_IN_SECONDS);
        set_transient('dbem_update_def', array('registration_id' => 2, 'email' => 'altro@example.com', 'data' => array()), DAY_IN_SECONDS);

        $result = DBEM_Privacy_DSAR::eraser_callback('anna@example.com', 1);

        $this->assertTrue($result['items_removed']);
        $this->assertFalse(get_transient('dbem_update_abc'));
        $this->assertIsArray(get_transient('dbem_update_def'));
    }
}
