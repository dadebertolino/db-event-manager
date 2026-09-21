<?php

use PHPUnit\Framework\TestCase;

final class ReminderTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__dbem_post_meta'] = array(
            10 => array(
                '_dbem_event_name' => 'Open Day',
                '_dbem_date_start' => '2026-10-10T09:00',
                '_dbem_location'   => 'Aula Magna',
            ),
        );
        $GLOBALS['__dbem_sent_mail'] = array();
    }

    private function registration(): object {
        return (object) array(
            'id'    => 1,
            'name'  => 'Alice',
            'email' => 'alice@example.com',
            'token' => 'token-1',
            'data'  => json_encode(array('nome' => 'Alice', 'Laboratorio' => array('Robotica', 'Chimica'))),
        );
    }

    public function testBuildReminderContainsEventDetails(): void {
        $email = DBEM_Email::build_reminder(10, $this->registration());

        $this->assertSame('Promemoria: Open Day', $email['subject']);
        $this->assertStringContainsString('Ciao Alice', $email['html']);
        $this->assertStringContainsString('10/10/2026 09:00', $email['html']);
        $this->assertStringContainsString('Aula Magna', $email['html']);
        $this->assertStringContainsString('Laboratorio: Robotica, Chimica', $email['html']);
        $this->assertSame(array(), $email['attachments']);
    }

    public function testPreviewMatchesSentReminder(): void {
        $reg = $this->registration();
        $preview = DBEM_Email::build_reminder(10, $reg);

        DBEM_Email::send_reminder(10, $reg);

        $this->assertCount(1, $GLOBALS['__dbem_sent_mail']);
        $this->assertSame('alice@example.com', $GLOBALS['__dbem_sent_mail'][0]['to']);
        $this->assertSame($preview['subject'], $GLOBALS['__dbem_sent_mail'][0]['subject']);
        $this->assertSame($preview['html'], $GLOBALS['__dbem_sent_mail'][0]['message']);
    }

    public function testMissingIdsMeansAllRecipientsButEmptyListMeansNone(): void {
        $this->assertCount(1, DBEM_DB::get_reminder_registrations(10, null));
        $this->assertCount(0, DBEM_DB::get_reminder_registrations(10, array()));
    }

    public function testAssignedTimeIsShownAndEventHourHidden(): void {
        $GLOBALS['__dbem_post_meta'][10]['_dbem_time_slot_enabled'] = '1';
        $reg = $this->registration();
        $reg->assigned_time = '10:30';

        $html = DBEM_Email::build_reminder(10, $reg)['html'];

        $this->assertStringContainsString('Il tuo orario: 10:30', $html);
        $this->assertStringContainsString('Periodo generale: 10/10/2026<br', $html);
        $this->assertStringNotContainsString('09:00', $html);
    }

    public function testNoAssignedTimeLineWhenEmpty(): void {
        $html = DBEM_Email::build_reminder(10, $this->registration())['html'];

        $this->assertStringNotContainsString('Il tuo orario', $html);
        $this->assertStringContainsString('10/10/2026 09:00', $html);
    }

    public function testDateFieldsAreShownInItalianFormat(): void {
        $reg = $this->registration();
        $reg->data = json_encode(array('Giorno' => '2026-10-12', 'Codice' => '2026-13-45'));

        $html = DBEM_Email::build_reminder(10, $reg)['html'];

        $this->assertStringContainsString('Giorno: 12/10/2026', $html);
        $this->assertStringContainsString('Codice: 2026-13-45', $html);
    }

    private function useLabOptions(): object {
        $GLOBALS['__dbem_post_meta'][10]['_dbem_reminder_content'] = 'options';
        $GLOBALS['__dbem_post_meta'][10]['_dbem_custom_fields'] = array(
            array('type' => 'text', 'label' => 'Scuola di provenienza'),
            array('type' => 'checkbox', 'label' => 'Voglio frequentare questi laboratori'),
        );
        $reg = $this->registration();
        $reg->data = json_encode(array(
            'Scuola di provenienza' => 'Media Mondovì',
            'Voglio frequentare questi laboratori' => array(
                'Baruffi CAT 24 Settembre 2026 dalle 14:30 alle 18:30',
                'Garelli MAT 29 Settembre 2026 dalle 14:30 alle 16:30',
            ),
        ));
        return $reg;
    }

    public function testOptionsModeListsChoicesInsteadOfGeneralDate(): void {
        $html = DBEM_Email::build_reminder(10, $this->useLabOptions())['html'];

        $this->assertStringContainsString('Le tue scelte:', $html);
        $this->assertStringContainsString('- Baruffi CAT 24 Settembre 2026 dalle 14:30 alle 18:30', $html);
        $this->assertStringContainsString('- Garelli MAT 29 Settembre 2026 dalle 14:30 alle 16:30', $html);
        $this->assertStringNotContainsString('Periodo generale', $html);
        $this->assertStringNotContainsString('Sede generale', $html);
        $this->assertStringNotContainsString('Media Mondovì', $html);
        $this->assertStringNotContainsString('Voglio frequentare questi laboratori', $html);
    }

    public function testOptionsModeGroupsChoicesWhenSeveralFieldsAreFilled(): void {
        $reg = $this->useLabOptions();
        $GLOBALS['__dbem_post_meta'][10]['_dbem_custom_fields'][] = array('type' => 'radio', 'label' => 'Pranzo');
        $data = json_decode($reg->data, true);
        $data['Pranzo'] = 'Sì';
        $reg->data = json_encode($data);

        $html = DBEM_Email::build_reminder(10, $reg)['html'];

        $this->assertStringContainsString('Voglio frequentare questi laboratori:', $html);
        $this->assertStringContainsString('Pranzo:', $html);
        $this->assertStringContainsString('- Sì', $html);
    }

    public function testOptionsModeFallsBackToDateWithoutChoices(): void {
        $reg = $this->useLabOptions();
        $reg->data = json_encode(array('Scuola di provenienza' => 'Media Mondovì'));

        $html = DBEM_Email::build_reminder(10, $reg)['html'];

        $this->assertStringContainsString('Periodo generale: 10/10/2026 09:00', $html);
        $this->assertStringNotContainsString('Le tue scelte', $html);
    }

    public function testOptionsModeKeepsAssignedTime(): void {
        $reg = $this->useLabOptions();
        $reg->assigned_time = '15:00';

        $html = DBEM_Email::build_reminder(10, $reg)['html'];

        $this->assertStringContainsString('Il tuo orario: 15:00', $html);
    }
}
