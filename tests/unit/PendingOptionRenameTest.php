<?php

use PHPUnit\Framework\TestCase;

/**
 * Opzioni rinominate (1.9.0): le iscrizioni si aggiornano solo dopo una conferma
 */
final class PendingOptionRenameTest extends TestCase {
    private const LABEL = 'Laboratorio';

    private $previousRows;

    protected function setUp(): void {
        $GLOBALS['__dbem_post_meta'] = array();
        $GLOBALS['__dbem_current_caps'] = array('edit_post');
        $_POST = array();
        unset($GLOBALS['__dbem_json_success'], $GLOBALS['__dbem_json_error']);
        $this->previousRows = $GLOBALS['wpdb']->set_rows('wp_dbem_registrations', array(
            array('id' => 1, 'event_id' => 10, 'status' => 'confirmed', 'data' => json_encode(array(self::LABEL => 'Lab 10 ott'))),
            array('id' => 2, 'event_id' => 10, 'status' => 'confirmed', 'data' => json_encode(array(self::LABEL => 'Lab 17 ott'))),
        ));
    }

    protected function tearDown(): void {
        $GLOBALS['wpdb']->set_rows('wp_dbem_registrations', $this->previousRows);
        $GLOBALS['__dbem_post_meta'] = array();
        $GLOBALS['__dbem_current_caps'] = array();
        $_POST = array();
    }

    private function fields(array $options): array {
        return array(array('id' => 'f_lab', 'type' => 'radio', 'label' => self::LABEL, 'options' => $options));
    }

    private function rename(array $old, array $new): void {
        dbem_call_private('DBEM_Admin', 'apply_option_renames', 10, $this->fields($old), $this->fields($new));
    }

    private function values(): array {
        $values = array();
        foreach (DBEM_DB::get_registrations(10) as $row) {
            $values[(int) $row->id] = json_decode($row->data, true)[self::LABEL];
        }
        ksort($values);
        return $values;
    }

    public function testRenameWaitsForDecision(): void {
        $this->rename(array('Lab 10 ott', 'Lab 17 ott'), array('Lab 24 ott', 'Lab 17 ott'));

        $this->assertSame(array(1 => 'Lab 10 ott', 2 => 'Lab 17 ott'), $this->values());
        $this->assertSame(
            array(array('label' => self::LABEL, 'from' => 'Lab 10 ott', 'to' => 'Lab 24 ott')),
            DBEM_Admin::get_pending_option_renames(10)
        );
    }

    public function testRenameNobodyChoseIsNotAsked(): void {
        $this->rename(array('Lab 10 ott', 'Lab 31 ott'), array('Lab 10 ott', 'Lab 30 ott'));

        $this->assertSame(array(), DBEM_Admin::get_pending_option_renames(10));
    }

    public function testRenamingAgainFollowsOriginalTextAndRenamingBackClearsIt(): void {
        $this->rename(array('Lab 10 ott', 'Lab 17 ott'), array('Lab 24 ott', 'Lab 17 ott'));
        $this->rename(array('Lab 24 ott', 'Lab 17 ott'), array('Lab 25 ott', 'Lab 17 ott'));
        $this->assertSame('Lab 25 ott', DBEM_Admin::get_pending_option_renames(10)[0]['to']);

        $this->rename(array('Lab 25 ott', 'Lab 17 ott'), array('Lab 10 ott', 'Lab 17 ott'));
        $this->assertSame(array(), DBEM_Admin::get_pending_option_renames(10));
    }

    private function decide(string $decision): void {
        $_POST = array('event_id' => 10, 'label' => self::LABEL, 'from' => 'Lab 10 ott', 'to' => 'Lab 24 ott', 'decision' => $decision);
        try {
            DBEM_Admin::handle_option_rename_decision();
        } catch (RuntimeException $e) {
            // risposta JSON
        }
    }

    public function testApplyUpdatesRegistrations(): void {
        $this->rename(array('Lab 10 ott', 'Lab 17 ott'), array('Lab 24 ott', 'Lab 17 ott'));
        $this->decide('apply');

        $this->assertSame(array(1 => 'Lab 24 ott', 2 => 'Lab 17 ott'), $this->values());
        $this->assertSame(array(), DBEM_Admin::get_pending_option_renames(10));
        $this->assertStringContainsString('1 iscrizione aggiornata', $GLOBALS['__dbem_json_success']['message']);
    }

    public function testKeepLeavesRegistrationsUntouched(): void {
        $this->rename(array('Lab 10 ott', 'Lab 17 ott'), array('Lab 24 ott', 'Lab 17 ott'));
        $this->decide('keep');

        $this->assertSame(array(1 => 'Lab 10 ott', 2 => 'Lab 17 ott'), $this->values());
        $this->assertSame(array(), DBEM_Admin::get_pending_option_renames(10));
    }
}
