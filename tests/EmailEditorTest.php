<?php

use PHPUnit\Framework\TestCase;

final class EmailEditorTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__dbem_post_meta'] = array(
            10 => array(
                '_dbem_event_name' => 'Open Day',
                '_dbem_date_start' => '2026-10-10T09:00',
                '_dbem_location'   => 'Aula Magna',
            ),
        );
        unset($GLOBALS['__dbem_options']['dbem_from_email'], $GLOBALS['__dbem_options']['dbem_from_name'], $GLOBALS['__dbem_options']['admin_email']);
    }

    protected function tearDown(): void {
        unset($GLOBALS['__dbem_options']['dbem_from_email'], $GLOBALS['__dbem_options']['dbem_from_name'], $GLOBALS['__dbem_options']['admin_email']);
    }

    private function registration(): object {
        return (object) array(
            'id'    => 1,
            'name'  => 'Alice',
            'email' => 'alice@example.com',
            'token' => 'token-1',
            'data'  => json_encode(array('nome' => 'Alice', 'Laboratorio' => 'Robotica')),
        );
    }

    public function testConfirmationPlaceholdersAreAllReplaced(): void {
        $method = new ReflectionMethod('DBEM_Email', 'get_placeholders');
        $values = $method->invoke(null, 10, $this->registration());

        foreach (array_keys(DBEM_Email::placeholders_for('confirmation')) as $token) {
            $this->assertArrayHasKey($token, $values, $token . ' è mostrato nell\'editor ma non viene sostituito');
        }
    }

    public function testReminderPlaceholdersAreAllReplaced(): void {
        $tokens = implode(' ', array_keys(DBEM_Email::placeholders_for('reminder')));

        $email = DBEM_Email::build_reminder(10, $this->registration(), array('subject' => $tokens, 'message' => $tokens));

        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $email['subject']);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $email['html']);
    }

    public function testSurveyOffersSurveyLink(): void {
        $this->assertArrayHasKey('{survey_link}', DBEM_Email::placeholders_for('survey'));
        $this->assertArrayNotHasKey('{survey_link}', DBEM_Email::placeholders_for('confirmation'));
    }

    public function testSenderFallsBackToAdminEmailAndSiteName(): void {
        $GLOBALS['__dbem_options']['admin_email'] = 'admin@example.com';

        $this->assertContains('From: "Sito di prova" <admin@example.com>', DBEM_Email::get_headers());
    }

    public function testConfiguredSenderIsUsed(): void {
        $GLOBALS['__dbem_options']['admin_email'] = 'admin@example.com';
        $GLOBALS['__dbem_options']['dbem_from_email'] = 'eventi@scuola.it';
        $GLOBALS['__dbem_options']['dbem_from_name'] = 'Segreteria "Eventi"';

        $this->assertContains('From: "Segreteria Eventi" <eventi@scuola.it>', DBEM_Email::get_headers());
    }
}
