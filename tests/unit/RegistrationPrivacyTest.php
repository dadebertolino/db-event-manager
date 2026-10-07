<?php

use PHPUnit\Framework\TestCase;

/**
 * Iscrizione (1.9.0): il modulo non rivela chi è iscritto, ogni endpoint vale solo
 * per gli eventi pubblicati con il suo form, la modifica conserva la prova del consenso
 */
final class RegistrationPrivacyTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__dbem_sent_mail'] = array();
        $GLOBALS['__dbem_transients'] = array();
        $GLOBALS['__dbem_post_meta'] = array();
        $GLOBALS['__dbem_posts'] = array();
        unset($GLOBALS['__dbem_json_error'], $GLOBALS['__dbem_json_success']);
    }

    protected function tearDown(): void {
        $GLOBALS['__dbem_post_meta'] = array();
        $GLOBALS['__dbem_posts'] = array();
    }

    private function existing($status = 'confirmed') {
        return (object) array('id' => 7, 'event_id' => 10, 'name' => 'Alice', 'email' => 'alice@example.com', 'status' => $status);
    }

    private function handleExisting($existing) {
        try {
            DBEM_Registration::handle_existing(10, $existing, array('data' => '{}'));
            $this->fail('handle_existing deve terminare con una risposta JSON');
        } catch (RuntimeException $e) {
            return $GLOBALS['__dbem_json_success'] ?? null;
        }
    }

    public function testExistingAddressGetsSameAnswerAsNewRegistration(): void {
        $response = $this->handleExisting($this->existing());

        $this->assertSame(DBEM_Registration::received_message(), $response['message']);
        $this->assertCount(1, $GLOBALS['__dbem_sent_mail']);
        $this->assertSame('alice@example.com', $GLOBALS['__dbem_sent_mail'][0]['to']);
        $this->assertStringContainsString('esiste già un', $GLOBALS['__dbem_sent_mail'][0]['message']);
    }

    public function testNoticeIsSentAtMostOncePerHour(): void {
        $this->handleExisting($this->existing());
        $response = $this->handleExisting($this->existing());

        $this->assertSame(DBEM_Registration::received_message(), $response['message']);
        $this->assertCount(1, $GLOBALS['__dbem_sent_mail']);
    }

    public function testRejectedRegistrationIsNotReopenedEvenWithUpdatesAllowed(): void {
        $GLOBALS['__dbem_post_meta'][10]['_dbem_allow_registration_update'] = '1';

        $this->handleExisting($this->existing('rejected'));

        $this->assertStringContainsString('esiste già un', $GLOBALS['__dbem_sent_mail'][0]['message']);
        $this->assertStringNotContainsString('rifiut', $GLOBALS['__dbem_sent_mail'][0]['message']);
    }

    public function testUpdatesAllowedSendConfirmationLinkWithNeutralAnswer(): void {
        $GLOBALS['__dbem_post_meta'][10]['_dbem_allow_registration_update'] = '1';

        $response = $this->handleExisting($this->existing());

        $this->assertSame(DBEM_Registration::received_message(), $response['message']);
        $this->assertStringContainsString('Conferma la modifica', $GLOBALS['__dbem_sent_mail'][0]['subject']);
    }

    public function testEndpointMustMatchFormSourceAndPublishedEvent(): void {
        $GLOBALS['__dbem_posts'] = array(10 => 'publish', 11 => 'publish', 12 => 'draft');
        $GLOBALS['__dbem_post_meta'][11]['_dbem_form_source'] = 'dbfb';

        DBEM_Registration::require_event(10, 'builtin');
        DBEM_Registration::require_event(11, 'dbfb');

        foreach (array(array(10, 'dbfb'), array(11, 'builtin'), array(12, 'builtin'), array(0, 'builtin')) as $case) {
            try {
                DBEM_Registration::require_event($case[0], $case[1]);
                $this->fail('Evento ' . $case[0] . ' accettato dall\'endpoint ' . $case[1]);
            } catch (RuntimeException $e) {
                $this->assertSame('Evento non valido.', $GLOBALS['__dbem_json_error']);
            }
        }
    }

    public function testUpdateWithoutConsentKeepsOriginalProof(): void {
        $spy = new class {
            public $prefix = 'wp_';
            public $fields;
            public function update($table, $data, $where, $format = null, $where_format = null) {
                $this->fields = $data;
                return 1;
            }
        };
        $saved = $GLOBALS['wpdb'];
        $GLOBALS['wpdb'] = $spy;
        try {
            $data = array('data' => '{}', 'email' => 'a@example.com', 'name' => 'A', 'ip_address' => '1.2.3.4',
                'gdpr_consent_given' => null, 'gdpr_consent_text' => null, 'gdpr_consent_timestamp' => null,
                'gdpr_consent_privacy_url' => null, 'gdpr_consent_policy_version' => 0);
            DBEM_DB::replace_registration(7, $data);
            $this->assertArrayNotHasKey('gdpr_consent_given', $spy->fields);
            $this->assertArrayNotHasKey('gdpr_consent_timestamp', $spy->fields);

            $data['gdpr_consent_given'] = 1;
            $data['gdpr_consent_timestamp'] = '2026-10-07 10:00:00';
            DBEM_DB::replace_registration(7, $data);
            $this->assertSame(1, $spy->fields['gdpr_consent_given']);
            $this->assertSame('2026-10-07 10:00:00', $spy->fields['gdpr_consent_timestamp']);
        } finally {
            $GLOBALS['wpdb'] = $saved;
        }
    }
}
