<?php
/**
 * Azioni sulle iscrizioni (bug #12, #13 C, #14 del piano): solo dagli stati di
 * partenza ammessi, una volta sola, con le email giuste
 */
class StatusTransitionsIntegrationTest extends WP_UnitTestCase {

    private $event_id;

    public function set_up() {
        parent::set_up();
        $this->event_id = self::factory()->post->create(array('post_type' => 'dbem_event', 'post_status' => 'publish'));
        update_post_meta($this->event_id, '_dbem_confirmation_email', array('subject' => 'Confermato', 'message' => 'Ciao {nome}'));
        reset_phpmailer_instance();
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}dbem_registrations");
        parent::tear_down();
    }

    private function add_registration($status) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'dbem_registrations', array(
            'event_id'      => $this->event_id,
            'data'          => '{}',
            'email'         => $status . '@example.com',
            'name'          => ucfirst($status),
            'token'         => bin2hex(random_bytes(32)),
            'status'        => $status,
            'registered_at' => current_time('mysql'),
            'checked_in_at' => $status === 'checked_in' ? '2026-10-07 10:00:00' : null,
        ));
        return (int) $wpdb->insert_id;
    }

    private function status($id) {
        return DBEM_DB::get_registration($id)->status;
    }

    private function sent_to() {
        $mailer = tests_retrieve_phpmailer_instance();
        $to = array();
        for ($i = 0; ($mail = $mailer->get_sent($i)); $i++) {
            $to[] = $mail->to[0][0];
        }
        return $to;
    }

    public function test_conferma_in_blocco_non_tocca_presenti_e_confermati(): void {
        $ids = array(
            'pending'    => $this->add_registration('pending'),
            'checked_in' => $this->add_registration('checked_in'),
            'confirmed'  => $this->add_registration('confirmed'),
        );

        $changed = DBEM_Admin::apply_transition(array_values($ids), 'confirm');

        $this->assertSame(array($ids['pending']), $changed);
        $this->assertSame('checked_in', $this->status($ids['checked_in']));
        $this->assertSame('2026-10-07 10:00:00', DBEM_DB::get_registration($ids['checked_in'])->checked_in_at);
        $this->assertSame(array('pending@example.com'), $this->sent_to());
    }

    public function test_rifiuto_solo_da_in_attesa_e_una_volta(): void {
        $pending = $this->add_registration('pending');

        $this->assertSame(array($pending), DBEM_Admin::apply_transition(array($pending), 'reject'));
        $this->assertSame(array(), DBEM_Admin::apply_transition(array($pending), 'reject'));
        $this->assertCount(1, $this->sent_to());
    }

    public function test_check_in_doppio_registra_una_volta(): void {
        $id = $this->add_registration('confirmed');

        $this->assertSame(array($id), DBEM_Admin::apply_transition(array($id), 'checkin'));
        $first = DBEM_DB::get_registration($id)->checked_in_at;
        $this->assertSame(array(), DBEM_Admin::apply_transition(array($id), 'checkin'));
        $this->assertSame($first, DBEM_DB::get_registration($id)->checked_in_at);
    }

    public function test_presente_non_tocca_annullati_e_rifiutati(): void {
        $cancelled = $this->add_registration('cancelled');
        $rejected = $this->add_registration('rejected');

        $this->assertSame(array(), DBEM_Admin::apply_transition(array($cancelled, $rejected), 'checkin'));
        $this->assertSame('cancelled', $this->status($cancelled));
        $this->assertSame('rejected', $this->status($rejected));
    }

    public function test_orario_troppo_lungo_rifiutato_prima_del_salvataggio(): void {
        $this->assertSame('10:30', DBEM_DB::clean_assigned_time(' 10:30 '));
        $this->assertFalse(DBEM_DB::clean_assigned_time(str_repeat('x', DBEM_DB::ASSIGNED_TIME_MAX + 1)));
        $this->assertSame(str_repeat('è', DBEM_DB::ASSIGNED_TIME_MAX), DBEM_DB::clean_assigned_time(str_repeat('è', DBEM_DB::ASSIGNED_TIME_MAX)));
    }

    public function test_annullamento_avvisa_solo_con_la_spunta(): void {
        $silent = $this->add_registration('confirmed');
        DBEM_Admin::apply_transition(array($silent), 'cancel');
        $this->assertSame(array(), $this->sent_to());

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'dbem_registrations', array('email' => 'avvisato@example.com'), array('id' => $this->add_registration('pending')));
        $notified = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}dbem_registrations WHERE email = 'avvisato@example.com'");
        DBEM_Admin::apply_transition(array($notified), 'cancel', true);
        $this->assertSame(array('avvisato@example.com'), $this->sent_to());
        $this->assertSame('cancelled', $this->status($notified));
    }
}
