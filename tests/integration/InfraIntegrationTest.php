<?php
/**
 * Smoke test dell'infrastruttura di integrazione: plugin caricato, tabelle
 * create, hook principali agganciati. Se questi falliscono, i risultati degli
 * altri integration test non sono attendibili.
 */
class InfraIntegrationTest extends WP_UnitTestCase {

    public function test_il_plugin_e_caricato(): void {
        $this->assertTrue(defined('DBEM_VERSION'));
        $this->assertTrue(class_exists('DB_Event_Manager'));
        $this->assertTrue(post_type_exists('dbem_event'));
    }

    public function test_le_tabelle_esistono(): void {
        global $wpdb;

        foreach (array('dbem_registrations', 'dbem_survey_responses') as $table) {
            $name = $wpdb->prefix . $table;
            $this->assertSame($name, $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $name)), $name);
        }
    }

    public function test_endpoint_ajax_registrati(): void {
        foreach (array('dbem_register', 'dbem_register_dbfb', 'dbem_public_pin_check', 'dbem_submit_survey') as $action) {
            $this->assertNotFalse(has_action('wp_ajax_nopriv_' . $action), $action);
        }
        $this->assertNotFalse(has_action('wp_ajax_dbem_bulk_action'));
    }

    /**
     * Bug #6 del piano: l'hook di attivazione era registrato dentro plugins_loaded
     * e non partiva mai, quindi niente flush delle regole del CPT
     */
    public function test_attivazione_registrata_e_regole_degli_eventi(): void {
        $file = DBEM_PLUGIN_DIR . 'db-event-manager.php';
        $this->assertNotFalse(has_action('activate_' . plugin_basename($file)));
        $this->assertNotFalse(has_action('deactivate_' . plugin_basename($file)));

        $this->set_permalink_structure('/%postname%/');
        delete_option('rewrite_rules');
        DBEM_DB::activate();

        $rules = get_option('rewrite_rules');
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('eventi/?$', $rules);
    }
}
