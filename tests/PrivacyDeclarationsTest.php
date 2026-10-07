<?php

use PHPUnit\Framework\TestCase;

/**
 * Registro trattamenti (1.9.0): dichiara ciò che il plugin conserva e invia davvero
 */
final class PrivacyDeclarationsTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__dbem_posts'] = array();
        $GLOBALS['__dbem_post_meta'] = array();
    }

    protected function tearDown(): void {
        $GLOBALS['__dbem_posts'] = array();
        $GLOBALS['__dbem_post_meta'] = array();
    }

    private function ids(array $register): array {
        return array_column($register, 'id');
    }

    public function testNothingDeclaredWithoutEvents(): void {
        $this->assertSame(array(), DBEM_Privacy_Declarations::declare_processing(array()));
    }

    public function testDraftOrTrashedEventsStillDeclareRegistrationsAndEmails(): void {
        $GLOBALS['__dbem_posts'] = array(10 => 'draft', 11 => 'trash');

        $ids = $this->ids(DBEM_Privacy_Declarations::declare_processing(array()));

        $this->assertContains('dbem_registrations', $ids);
        $this->assertContains('dbem_email', $ids);
    }

    public function testEmailsDeclaredEvenWithoutCustomConfirmationText(): void {
        $GLOBALS['__dbem_posts'] = array(10 => 'publish');
        $GLOBALS['__dbem_post_meta'][10]['_dbem_approval_mode'] = 'approval';

        $this->assertContains('dbem_email', $this->ids(DBEM_Privacy_Declarations::declare_processing(array())));
    }

    public function testTransfersMentionApproversAndNotifications(): void {
        $GLOBALS['__dbem_posts'] = array(10 => 'publish');
        $register = DBEM_Privacy_Declarations::declare_processing(array());
        $this->assertStringStartsWith('Nessuno', $register[0]['transfers']);

        $GLOBALS['__dbem_post_meta'][10]['_dbem_approval_mode'] = 'approval';
        $GLOBALS['__dbem_post_meta'][10]['_dbem_notify_admin'] = '1';
        $register = DBEM_Privacy_Declarations::declare_processing(array());
        $this->assertStringContainsString('approvatori', $register[0]['transfers']);
        $this->assertStringContainsString('nuova iscrizione', $register[0]['transfers']);
    }
}
