<?php

use PHPUnit\Framework\TestCase;

final class OptionRenameTest extends TestCase {
    private const LABEL = 'Voglio frequentare questi laboratori';

    private $previousRows;

    protected function tearDown(): void {
        if ($this->previousRows !== null) {
            $GLOBALS['wpdb']->set_rows('wp_dbem_registrations', $this->previousRows);
            $this->previousRows = null;
        }
    }

    private function field(array $options, string $type = 'checkbox', string $label = self::LABEL): array {
        return array('type' => $type, 'label' => $label, 'options' => $options);
    }

    public function testEditedLineIsDetectedAsRename(): void {
        $old = array($this->field(array('CAT 14:30', 'AFM 14:30', 'MAT 14:30')));
        $new = array($this->field(array('CAT 14:30', 'AFM 15:00', 'MAT 14:30')));

        $this->assertSame(
            array(self::LABEL => array('AFM 14:30' => 'AFM 15:00')),
            DBEM_Admin::find_renamed_options($old, $new)
        );
    }

    public function testAddedRemovedAndMovedLinesAreNotRenames(): void {
        $old = array($this->field(array('CAT', 'AFM', 'MAT')));

        $added = array($this->field(array('CAT', 'NUOVO', 'AFM', 'MAT')));
        $removed = array($this->field(array('CAT', 'MAT')));
        $moved = array($this->field(array('MAT', 'CAT', 'AFM')));

        $this->assertSame(array(), DBEM_Admin::find_renamed_options($old, $added));
        $this->assertSame(array(), DBEM_Admin::find_renamed_options($old, $removed));
        $this->assertSame(array(), DBEM_Admin::find_renamed_options($old, $moved));
    }

    public function testEditAndInsertInSameGapIsNotGuessed(): void {
        $old = array($this->field(array('CAT', 'AFM', 'MAT')));
        $new = array($this->field(array('CAT', 'AFM 15:00', 'ALTRO', 'MAT')));

        $this->assertSame(array(), DBEM_Admin::find_renamed_options($old, $new));
    }

    public function testSeveralEditsAndReorderedFieldsAreMatchedByLabel(): void {
        $old = array(
            $this->field(array('Sì', 'No'), 'radio', 'Pranzo'),
            $this->field(array('CAT 14:30', 'AFM 14:30', 'MAT 14:30')),
        );
        $new = array(
            $this->field(array('CAT 15:00', 'AFM 15:00', 'MAT 14:30')),
            $this->field(array('Sì', 'No'), 'radio', 'Pranzo'),
        );

        $this->assertSame(
            array(self::LABEL => array('CAT 14:30' => 'CAT 15:00', 'AFM 14:30' => 'AFM 15:00')),
            DBEM_Admin::find_renamed_options($old, $new)
        );
    }

    public function testRenamedLabelOrTextFieldIsIgnored(): void {
        $old = array($this->field(array('CAT')), array('type' => 'text', 'label' => 'Scuola', 'options' => array()));
        $new = array($this->field(array('CAT 15:00'), 'checkbox', 'Laboratori'), array('type' => 'text', 'label' => 'Scuola', 'options' => array()));

        $this->assertSame(array(), DBEM_Admin::find_renamed_options($old, $new));
    }

    public function testRegistrationsAreUpdatedWithNewOptionText(): void {
        $this->previousRows = $GLOBALS['wpdb']->set_rows('wp_dbem_registrations', array(
            array('id' => 1, 'event_id' => 10, 'status' => 'confirmed', 'data' => json_encode(array(self::LABEL => array('CAT 14:30', 'AFM 14:30'), 'Pranzo' => 'AFM 14:30'))),
            array('id' => 2, 'event_id' => 10, 'status' => 'pending', 'data' => json_encode(array(self::LABEL => 'AFM 14:30'))),
            array('id' => 3, 'event_id' => 10, 'status' => 'confirmed', 'data' => json_encode(array(self::LABEL => array('MAT 14:30')))),
        ));

        $counts = DBEM_DB::rename_option_values(10, self::LABEL, array('AFM 14:30' => 'AFM 15:00'));

        $this->assertSame(array('AFM 14:30' => 2), $counts);
        $rows = DBEM_DB::get_registrations(10);
        $data = array();
        foreach ($rows as $row) {
            $data[$row->id] = json_decode($row->data, true);
        }
        $this->assertSame(array('CAT 14:30', 'AFM 15:00'), $data[1][self::LABEL]);
        $this->assertSame('AFM 14:30', $data[1]['Pranzo']);
        $this->assertSame('AFM 15:00', $data[2][self::LABEL]);
        $this->assertSame(array('MAT 14:30'), $data[3][self::LABEL]);
    }
}
