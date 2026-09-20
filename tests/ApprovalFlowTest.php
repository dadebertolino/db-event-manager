<?php

use PHPUnit\Framework\TestCase;

final class ApprovalFlowTest extends TestCase {
    public function testActionKeyAndVerificationAreStable(): void {
        $token = 'registration-token-123';

        $approve_key = DBEM_Email::generate_action_key($token, 'approve');
        $reject_key = DBEM_Email::generate_action_key($token, 'reject');

        $this->assertNotSame($approve_key, $reject_key);
        $this->assertTrue(DBEM_Email::verify_action_key($token, 'approve', $approve_key));
        $this->assertFalse(DBEM_Email::verify_action_key($token, 'approve', $reject_key));
    }

    public function testPendingRegistrationCanBeValidatedAsPending(): void {
        $reg = DBEM_DB::get_registration_by_token('token-2');

        $this->assertNotNull($reg);
        $this->assertSame('pending', $reg->status);
    }
}
