<?php

use PHPUnit\Framework\TestCase;

final class ReplaceConfirmationTest extends TestCase {
    protected function setUp(): void {
        $_POST = array();
    }

    public function testNewRegistrationNeedsNoConfirmation(): void {
        DBEM_Registration::require_replace_confirmation(null);

        $this->addToAssertionCount(1);
    }

    public function testExistingRegistrationAsksForConfirmation(): void {
        try {
            DBEM_Registration::require_replace_confirmation((object) array('id' => 1));
            $this->fail('La sostituzione è avvenuta senza conferma');
        } catch (RuntimeException $e) {
            $this->assertSame('confirm_replace', $GLOBALS['__dbem_json_error']['code']);
            $this->assertStringContainsString('Vuoi sostituire la prenotazione precedente', $GLOBALS['__dbem_json_error']['message']);
        }
    }

    public function testConfirmedReplacementProceeds(): void {
        $_POST['dbem_confirm_replace'] = '1';

        DBEM_Registration::require_replace_confirmation((object) array('id' => 1));

        $this->addToAssertionCount(1);
    }
}
