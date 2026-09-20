<?php

use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase {
    public function testGeneratePinReturnsSixDigits(): void {
        $pin = DBEM_Security::generate_pin();

        $this->assertSame(6, strlen($pin));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pin);
    }

    public function testGetPinCreatesAndPersistsValue(): void {
        unset($GLOBALS['__dbem_options']['dbem_checkin_pin']);

        $pin = DBEM_Security::get_pin();

        $this->assertSame(6, strlen($pin));
        $this->assertSame($pin, $GLOBALS['__dbem_options']['dbem_checkin_pin']);
    }

    public function testClientIpFallsBackToDefault(): void {
        unset($_SERVER['REMOTE_ADDR']);

        $this->assertSame('0.0.0.0', DBEM_Security::client_ip());
    }
}
