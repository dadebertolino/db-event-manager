<?php
/**
 * Bug #9 del piano: controllo dei posti e salvataggio di un'iscrizione sotto un lock
 * per evento, così due invii insieme non superano i posti
 */
class RegistrationLockIntegrationTest extends WP_UnitTestCase {

    private function other_connection() {
        $host = DB_HOST;
        $port = null;
        if (strpos($host, ':') !== false) {
            list($host, $port) = explode(':', $host, 2);
        }
        $db = new mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port ? (int) $port : null);
        $this->assertSame(0, $db->connect_errno, (string) $db->connect_error);
        return $db;
    }

    private function try_lock($db, $event_id) {
        global $wpdb;
        $name = $db->real_escape_string($wpdb->prefix . 'dbem_registration_' . $event_id);
        return (string) $db->query("SELECT GET_LOCK('$name', 0)")->fetch_row()[0];
    }

    public function test_il_lock_blocca_una_seconda_richiesta_sullo_stesso_evento(): void {
        $other = $this->other_connection();

        $this->assertTrue(DBEM_DB::lock_event(42));
        $this->assertSame('0', $this->try_lock($other, 42), 'stesso evento: la seconda richiesta aspetta');
        $this->assertSame('1', $this->try_lock($other, 43), 'altro evento: nessuna attesa');

        DBEM_DB::unlock_event(42);
        $this->assertSame('1', $this->try_lock($other, 42), 'dopo il rilascio il lock è libero');
        $other->close();
    }
}
