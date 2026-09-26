<?php

use PHPUnit\Framework\TestCase;

final class SecurityFixesTest extends TestCase {
    private $saved_tables = array();

    protected function setUp(): void {
        $_POST = array();
        $GLOBALS['__dbem_sent_mail'] = array();
        $GLOBALS['__dbem_transients'] = array();
        $GLOBALS['__dbem_cleared_hooks'] = array();
        unset($GLOBALS['__dbem_json_error'], $GLOBALS['__dbem_json_success']);
    }

    protected function tearDown(): void {
        global $wpdb;
        foreach ($this->saved_tables as $table => $rows) {
            $wpdb->set_rows($table, $rows);
        }
        $this->saved_tables = array();
        unset($GLOBALS['__dbem_options']['admin_email']);
    }

    private function set_table($name, $rows) {
        global $wpdb;
        $table = $wpdb->prefix . $name;
        $previous = $wpdb->set_rows($table, $rows);
        if (!array_key_exists($table, $this->saved_tables)) {
            $this->saved_tables[$table] = $previous;
        }
    }

    private function call_private($class, $method, ...$args) {
        $reflection = new ReflectionMethod($class, $method);
        return $reflection->invoke(null, ...$args);
    }

    public function testPlaceholdersAreReplacedInOnePass(): void {
        $text = $this->call_private('DBEM_Email', 'replace_placeholders', 'Ciao {nome}', array(
            '{nome}'  => '{token}',
            '{token}' => 'SEGRETO',
        ));

        $this->assertSame('Ciao {token}', $text);
    }

    public function testSubjectStaysOnOneLine(): void {
        $subject = $this->call_private('DBEM_Email', 'clean_subject', "Iscrizione\r\nBcc: x@example.com");

        $this->assertSame('Iscrizione Bcc: x@example.com', $subject);
    }

    public function testHeadersUseSiteNameAndAdminEmail(): void {
        $GLOBALS['__dbem_options']['admin_email'] = 'info@example.com';

        $this->assertContains('From: "Sito di prova" <info@example.com>', DBEM_Email::get_headers());
    }

    public function testHeadersOmitFromWithoutValidSender(): void {
        $GLOBALS['__dbem_options']['admin_email'] = 'non-valida';

        $this->assertSame(array('Content-Type: text/html; charset=UTF-8'), DBEM_Email::get_headers());
    }

    public function testRejectedRegistrationCannotRegisterAgain(): void {
        try {
            DBEM_Registration::reject_duplicate((object) array('id' => 5, 'status' => 'rejected'), true);
            $this->fail('Un iscritto rifiutato ha potuto reiscriversi');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('già registrato', $GLOBALS['__dbem_json_error']);
        }
    }

    public function testActiveRegistrationCanBeUpdatedWhenAllowed(): void {
        DBEM_Registration::reject_duplicate((object) array('id' => 1, 'status' => 'confirmed'), true);
        DBEM_Registration::reject_duplicate(null, false);

        $this->addToAssertionCount(1);
    }

    public function testUpdateWaitsForEmailConfirmation(): void {
        $existing = (object) array('id' => 1, 'event_id' => 10, 'email' => 'alice@example.com', 'name' => 'Alice', 'status' => 'confirmed');
        $data = array('name' => 'Intruso', 'email' => 'alice@example.com', 'data' => '{}');

        try {
            DBEM_Registration::request_update_confirmation(10, $existing, $data);
            $this->fail('Nessuna risposta inviata');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('email', $GLOBALS['__dbem_json_success']['message']);
        }

        $this->assertCount(1, $GLOBALS['__dbem_sent_mail']);
        $mail = $GLOBALS['__dbem_sent_mail'][0];
        $this->assertSame('alice@example.com', $mail['to']);
        $this->assertStringContainsString('dbem_action=confirm_update&amp;key=', $mail['message']);

        $pending = array_values($GLOBALS['__dbem_transients']);
        $this->assertCount(1, $pending);
        $this->assertSame(1, $pending[0]['registration_id']);
        $this->assertSame('Intruso', $pending[0]['data']['name']);

        // Finché il link non viene confermato, l'iscrizione resta quella di prima
        $this->assertSame('Alice', DBEM_DB::get_registration_by_email(10, 'alice@example.com')->name);
    }

    public function testCheckinIsRefusedForPendingRegistration(): void {
        try {
            $this->call_private('DBEM_Checkin', 'send_not_admitted', (object) array('status' => 'pending', 'name' => 'Bob'), 'Evento');
            $this->fail('Nessuna risposta inviata');
        } catch (RuntimeException $e) {
            $this->assertSame('not_admitted', $GLOBALS['__dbem_json_success']['status']);
            $this->assertStringContainsString('attesa di approvazione', $GLOBALS['__dbem_json_success']['message']);
        }
    }

    public function testDeletingRegistrationsRemovesSurveyResponses(): void {
        global $wpdb;
        $this->set_table('dbem_registrations', array(
            array('id' => 2, 'event_id' => 10, 'email' => 'bob@example.com', 'status' => 'pending', 'name' => 'Bob', 'token' => 'token-2'),
        ));
        $this->set_table('dbem_survey_responses', array(
            array('id' => 7, 'event_id' => 10, 'registration_id' => 2),
            array('id' => 8, 'event_id' => 10, 'registration_id' => 99),
        ));

        $this->assertSame(1, DBEM_DB::delete_registrations(array(2)));

        $remaining = $wpdb->set_rows($wpdb->prefix . 'dbem_survey_responses', array());
        $this->assertSame(array(8), array_column($remaining, 'id'));
    }

    public function testDeletingEventClearsScheduledSends(): void {
        $this->set_table('dbem_registrations', array());
        $this->set_table('dbem_survey_responses', array());

        DBEM_DB::delete_event_data(10);

        $this->assertContains(array('dbem_send_reminder', array(10)), $GLOBALS['__dbem_cleared_hooks']);
        $this->assertContains(array('dbem_send_survey_auto', array(10)), $GLOBALS['__dbem_cleared_hooks']);
    }

    public function testQrDeleteRefusesPathsOutsideQrFolder(): void {
        $this->assertFalse(DBEM_QRCode::delete('../../wp-config'));
    }

    public function testRequestTextLosesWordPressSlashes(): void {
        // WordPress aggiunge le barre alle virgolette in $_POST
        $_POST['template_subject'] = "Promemoria per l\\'evento";
        $_POST['template_message'] = "Ciao {nome}, ci vediamo all\\'ingresso";

        $template = $this->call_private('DBEM_Admin', 'reminder_template_from_request');

        $this->assertSame("Promemoria per l'evento", $template['subject']);
        $this->assertSame("Ciao {nome}, ci vediamo all'ingresso", $template['message']);
    }
}
