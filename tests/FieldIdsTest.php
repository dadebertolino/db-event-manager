<?php

use PHPUnit\Framework\TestCase;

final class FieldIdsTest extends TestCase {
    private $saved_rows = null;

    protected function setUp(): void {
        $GLOBALS['__dbem_post_meta'] = array(
            10 => array(
                '_dbem_event_name'    => 'Open Day',
                '_dbem_custom_fields' => array(
                    array('type' => 'text', 'label' => 'Classe', 'required' => false, 'options' => array()),
                    array('id' => 'f_lab', 'type' => 'checkbox', 'label' => 'Laboratorio', 'required' => false, 'options' => array('Robotica', 'Chimica')),
                ),
            ),
        );
    }

    protected function tearDown(): void {
        global $wpdb;
        if ($this->saved_rows !== null) {
            $wpdb->set_rows($wpdb->prefix . 'dbem_registrations', $this->saved_rows);
            $this->saved_rows = null;
        }
    }

    private function registration(): object {
        return (object) array(
            'id'    => 1,
            'name'  => 'Alice',
            'email' => 'alice@example.com',
            'token' => 'token-1',
            'data'  => json_encode(array('nome' => 'Alice', 'Classe' => '3A', 'Laboratorio' => array('Robotica', 'Chimica'))),
        );
    }

    public function testFieldsWithoutIdGetOneAndKeepIt(): void {
        $fields = DBEM_CPT::get_custom_fields(10);

        $this->assertMatchesRegularExpression('/^f_[a-z0-9]{8}$/', $fields[0]['id']);
        $this->assertSame('f_lab', $fields[1]['id']);
        // Salvato: alla lettura successiva l'id è lo stesso
        $this->assertSame($fields[0]['id'], DBEM_CPT::get_custom_fields(10)[0]['id']);
    }

    public function testDuplicateIdsAreReplaced(): void {
        $fields = DBEM_CPT::assign_field_ids(array(array('id' => 'x', 'label' => 'A'), array('id' => 'x', 'label' => 'B')));

        $this->assertSame('x', $fields[0]['id']);
        $this->assertNotSame('x', $fields[1]['id']);
    }

    public function testFieldPlaceholderUsesCurrentValue(): void {
        $method = new ReflectionMethod('DBEM_Email', 'replace_placeholders');
        $values = (new ReflectionMethod('DBEM_Email', 'get_placeholders'))->invoke(null, 10, $this->registration());

        $text = $method->invoke(null, 'Lab: {campo:f_lab} - vecchio: {campo:eliminato}', $values);

        $this->assertSame('Lab: Robotica, Chimica - vecchio: ', $text);
    }

    public function testRenamedLabelIsDetectedById(): void {
        $old = array(array('id' => 'f_lab', 'label' => 'Laboratorio'), array('id' => 'f_cls', 'label' => 'Classe'));
        $new = array(array('id' => 'f_lab', 'label' => 'Laboratorio scelto'), array('id' => 'f_cls', 'label' => 'Classe'));

        $this->assertSame(array('Laboratorio' => 'Laboratorio scelto'), DBEM_Admin::find_renamed_labels($old, $new));
    }

    public function testRenameToDuplicateOrReservedLabelIsIgnored(): void {
        $old = array(array('id' => 'a', 'label' => 'Uno'), array('id' => 'b', 'label' => 'Due'), array('id' => 'c', 'label' => 'Tre'));
        $new = array(array('id' => 'a', 'label' => 'Due'), array('id' => 'b', 'label' => 'Due'), array('id' => 'c', 'label' => 'email'));

        $this->assertSame(array(), DBEM_Admin::find_renamed_labels($old, $new));
    }

    public function testOptionRenameFollowsFieldAcrossLabelChange(): void {
        $old = array(array('id' => 'f_lab', 'type' => 'radio', 'label' => 'Turno', 'options' => array('9:00', '10:00')));
        $new = array(array('id' => 'f_lab', 'type' => 'radio', 'label' => 'Turno scelto', 'options' => array('9:30', '10:00')));

        $this->assertSame(array('Turno scelto' => array('9:00' => '9:30')), DBEM_Admin::find_renamed_options($old, $new));
    }

    public function testRegistrationKeysAreRenamedTogether(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $this->saved_rows = $wpdb->set_rows($table, array(
            array('id' => 1, 'event_id' => 10, 'status' => 'confirmed', 'email' => 'a@example.com', 'registered_at' => '2026-01-01 10:00:00',
                  'data' => json_encode(array('nome' => 'A', 'Uno' => '1', 'Due' => '2', 'Tre' => '3'))),
        ));

        $updated = DBEM_DB::rename_data_keys(10, array('Uno' => 'Due', 'Due' => 'Uno', 'Tre' => 'nome'));

        $rows = $wpdb->set_rows($table, array());
        $this->assertSame(1, $updated);
        // Scambio corretto; "Tre" non sovrascrive "nome", che esiste e non viene rinominato
        $this->assertSame(array('nome' => 'A', 'Due' => '1', 'Uno' => '2', 'Tre' => '3'), json_decode($rows[0]['data'], true));
    }
}
