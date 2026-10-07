<?php

use PHPUnit\Framework\TestCase;

/**
 * Azioni sulle iscrizioni (1.9.0): solo dagli stati di partenza ammessi
 */
final class BulkTransitionsTest extends TestCase {
    public function testConfirmDoesNotTouchCheckedInOrConfirmed(): void {
        list($from, $to) = DBEM_Admin::bulk_transitions()['confirm'];

        $this->assertSame('confirmed', $to);
        $this->assertNotContains('checked_in', $from);
        $this->assertNotContains('confirmed', $from);
    }

    public function testRejectOnlyFromPending(): void {
        $this->assertSame(array(array('pending'), 'rejected'), DBEM_Admin::bulk_transitions()['reject']);
    }

    public function testCheckinOnlyFromConfirmed(): void {
        $this->assertSame(array(array('confirmed'), 'checked_in'), DBEM_Admin::bulk_transitions()['checkin']);
    }

    public function testCancelLeavesCheckedInAndRejected(): void {
        list($from) = DBEM_Admin::bulk_transitions()['cancel'];

        $this->assertNotContains('checked_in', $from);
        $this->assertNotContains('rejected', $from);
    }
}
