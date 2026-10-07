<?php
/**
 * Bug #37 del piano: risposte al survey salvate sotto l'etichetta convertite all'id
 */
class SurveyMigrationIntegrationTest extends WP_UnitTestCase {

    public function test_le_risposte_vecchie_passano_sotto_l_id(): void {
        global $wpdb;
        $event_id = self::factory()->post->create(array('post_type' => 'dbem_event', 'post_status' => 'publish'));
        // Domande salvate prima della 1.11.0: senza id
        update_post_meta($event_id, '_dbem_survey_fields', array(
            array('type' => 'radio', 'label' => 'Voto', 'required' => true, 'options' => array('Ottimo', 'Buono')),
            array('type' => 'textarea', 'label' => 'Commenti', 'required' => false, 'options' => array()),
        ));
        $wpdb->insert($wpdb->prefix . 'dbem_survey_responses', array(
            'event_id'        => $event_id,
            'registration_id' => 1,
            'data'            => wp_json_encode(array('Voto' => 'Ottimo', 'Commenti' => 'Bene', 'Domanda eliminata' => 'Sì')),
            'submitted_at'    => current_time('mysql'),
        ));
        $response_id = (int) $wpdb->insert_id;

        DBEM_Survey::migrate_label_keys();

        $fields = DBEM_Survey::get_fields($event_id);
        $data = json_decode($wpdb->get_var($wpdb->prepare("SELECT data FROM {$wpdb->prefix}dbem_survey_responses WHERE id = %d", $response_id)), true);
        $this->assertSame('Ottimo', $data[$fields[0]['id']]);
        $this->assertSame('Bene', $data[$fields[1]['id']]);
        $this->assertSame('Sì', $data['Domanda eliminata'], 'le domande eliminate restano com\'erano');
        $this->assertArrayNotHasKey('Voto', $data);

        // Rinominare la domanda non fa più sparire la risposta
        $fields[0]['label'] = 'Giudizio';
        update_post_meta($event_id, '_dbem_survey_fields', $fields);
        $this->assertSame('Ottimo', DBEM_Survey::answer($data, DBEM_Survey::get_fields($event_id)[0]));
    }
}
