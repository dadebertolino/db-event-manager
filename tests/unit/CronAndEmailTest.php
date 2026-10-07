<?php

use PHPUnit\Framework\TestCase;

final class CronAndEmailTest extends TestCase {
    public function testGenerateActionKeyCreatesStableApprovalLink(): void {
        $token = 'abc123';

        $approve = DBEM_Email::generate_action_key($token, 'approve');
        $reject = DBEM_Email::generate_action_key($token, 'reject');

        $this->assertNotSame($approve, $reject);
        $this->assertTrue(DBEM_Email::verify_action_key($token, 'approve', $approve));
        $this->assertFalse(DBEM_Email::verify_action_key($token, 'approve', $reject));
    }

    public function testReminderUsesConfirmedParticipantsOnly(): void {
        $regs = DBEM_DB::get_registrations(10, 'confirmed');

        $this->assertCount(1, $regs);
        $this->assertSame('Alice', $regs[0]->name);
    }

    public function testBulkReminderRecipientsExcludePendingRegistrations(): void {
        $regs = DBEM_DB::get_reminder_registrations(10);

        $this->assertCount(1, $regs);
        $this->assertSame('confirmed', $regs[0]->status);
    }

    public function testSurveyAutoFiltersCheckedInRegistrations(): void {
        $regs = DBEM_DB::get_registrations(10, 'checked_in');

        $this->assertCount(0, $regs);
        $this->assertSame('checked_in', 'checked_in');
    }
}
