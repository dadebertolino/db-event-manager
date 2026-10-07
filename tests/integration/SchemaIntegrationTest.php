<?php
/**
 * Schema versionato (bug #32, #33 del piano)
 */
class SchemaIntegrationTest extends WP_UnitTestCase {

    private function email_index() {
        global $wpdb;
        return $wpdb->get_row("SHOW INDEX FROM {$wpdb->prefix}dbem_registrations WHERE Key_name = 'email'");
    }

    public function test_schema_corrente_e_indici(): void {
        global $wpdb;
        $this->assertSame(DBEM_DB::DB_VERSION, get_option(DBEM_DB::DB_VERSION_OPTION));
        $this->assertSame('191', (string) $this->email_index()->Sub_part);
        $this->assertNotNull($wpdb->get_row("SHOW INDEX FROM {$wpdb->prefix}dbem_registrations WHERE Key_name = 'gdpr_consent'"));
    }

    public function test_aggiornamento_da_uno_schema_senza_versione(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        // Come un'installazione precedente: indice sull'email completo, nessuna versione
        $wpdb->query("ALTER TABLE $table DROP INDEX email");
        $wpdb->query("ALTER TABLE $table ADD INDEX email (email)");
        delete_option(DBEM_DB::DB_VERSION_OPTION);
        $this->assertNull($this->email_index()->Sub_part);

        DBEM_DB::ensure_tables();

        $this->assertSame('191', (string) $this->email_index()->Sub_part);
        $this->assertSame(DBEM_DB::DB_VERSION, get_option(DBEM_DB::DB_VERSION_OPTION));
    }

    public function test_nessuna_query_di_controllo_con_lo_schema_aggiornato(): void {
        global $wpdb;
        // Allinea lo stato: l'ALTER TABLE di un altro test fa un commit implicito e il
        // rollback di fine test può lasciare l'opzione di versione cancellata
        DBEM_DB::ensure_tables();
        $before = $wpdb->num_queries;
        DBEM_DB::ensure_tables();
        $this->assertSame($before, $wpdb->num_queries);
    }
}
