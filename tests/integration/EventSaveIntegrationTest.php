<?php
/**
 * Salvataggio della scheda evento (bug #28 del piano): i testi arrivano com'erano scritti
 */
class EventSaveIntegrationTest extends WP_UnitTestCase {

    public function test_le_barre_rovesciate_restano(): void {
        wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));
        $event_id = self::factory()->post->create(array('post_type' => 'dbem_event', 'post_status' => 'publish'));

        // $_POST arriva con le barre aggiunte da WordPress
        $_POST = wp_slash(array(
            'dbem_event_nonce'          => wp_create_nonce('dbem_save_event'),
            '_dbem_event_name'          => 'Corso C:\\temp',
            '_dbem_location'            => 'Aula B\\2',
            '_dbem_confirmation_email'  => array('subject' => 'Oggetto \\ prova', 'message' => "Riga\\1"),
        ));
        DBEM_Admin::save_metabox($event_id, get_post($event_id));
        $_POST = array();

        $this->assertSame('Aula B\\2', get_post_meta($event_id, '_dbem_location', true));
        $this->assertSame('Corso C:\\temp', get_post_meta($event_id, '_dbem_event_name', true));
        $this->assertSame('Corso C:\\temp', get_post($event_id)->post_title);
        $this->assertSame('Oggetto \\ prova', get_post_meta($event_id, '_dbem_confirmation_email', true)['subject']);
    }
}
