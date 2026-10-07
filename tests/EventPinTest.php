<?php

use PHPUnit\Framework\TestCase;

/**
 * PIN delle pagine pubbliche (1.9.0): PIN di sistema o PIN dedicato dell'evento,
 * solo eventi pubblicati
 */
final class EventPinTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__dbem_transients'] = array();
        $GLOBALS['__dbem_logged_in'] = false;
        $GLOBALS['__dbem_post_meta'] = array();
        $GLOBALS['__dbem_options']['dbem_checkin_pin'] = '111111';
        $GLOBALS['__dbem_posts'] = array(10 => 'publish', 20 => 'publish', 30 => 'draft', 40 => 'trash');
        $GLOBALS['__dbem_post_meta'][20]['_dbem_checkin_pin'] = '2222';
        unset($GLOBALS['__dbem_json_error']);
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    }

    protected function tearDown(): void {
        $GLOBALS['__dbem_posts'] = array();
        $GLOBALS['__dbem_post_meta'] = array();
        unset($_POST['pin'], $_SERVER['HTTP_ORIGIN'], $_SERVER['REMOTE_ADDR']);
    }

    public function testEventPinFallsBackToSystemPin(): void {
        $this->assertSame('111111', DBEM_Security::get_event_pin(10));
        $this->assertSame('2222', DBEM_Security::get_event_pin(20));
    }

    public function testSystemPinOpensOnlyPublishedEventsWithoutOwnPin(): void {
        $this->assertSame(array(10), DBEM_Security::events_for_pin('111111'));
    }

    public function testEventPinOpensOnlyItsEvent(): void {
        $this->assertSame(array(20), DBEM_Security::events_for_pin('2222'));
    }

    public function testDraftAndTrashedEventsAreNeverOpened(): void {
        $GLOBALS['__dbem_post_meta'][30]['_dbem_checkin_pin'] = '3333';
        $this->assertSame(array(), DBEM_Security::events_for_pin('3333'));
        $this->assertNotContains(40, DBEM_Security::events_for_pin('111111'));
    }

    public function testEmptyOrWrongPinOpensNothing(): void {
        $this->assertSame(array(), DBEM_Security::events_for_pin(''));
        $this->assertSame(array(), DBEM_Security::events_for_pin('999999'));
    }

    public function testRequestWithEventPinReturnsItsEvents(): void {
        $_POST['pin'] = '2222';
        $this->assertSame(array(20), DBEM_Security::verify_public_request());
    }

    public function testRequestWithUnknownPinIsRejected(): void {
        $_POST['pin'] = '3333'; // PIN di una bozza: non vale
        try {
            DBEM_Security::verify_public_request();
            $this->fail('PIN di una bozza accettato');
        } catch (RuntimeException $e) {
            $this->assertSame('pin_error', $GLOBALS['__dbem_json_error']['status']);
        }
    }

    public function testEventOutsidePinIsForbidden(): void {
        DBEM_Security::require_event_access(10, array(10));
        try {
            DBEM_Security::require_event_access(20, array(10));
            $this->fail('Evento non concesso accettato');
        } catch (RuntimeException $e) {
            $this->assertSame('forbidden', $GLOBALS['__dbem_json_error']['status']);
        }
        $this->expectException(RuntimeException::class);
        DBEM_Security::require_event_access(0, array(10));
    }

    public function testPinFormat(): void {
        $this->assertTrue(DBEM_Security::is_valid_pin('1234'));
        $this->assertTrue(DBEM_Security::is_valid_pin('0123456789'));
        $this->assertFalse(DBEM_Security::is_valid_pin('1'));
        $this->assertFalse(DBEM_Security::is_valid_pin('12345678901'));
        $this->assertFalse(DBEM_Security::is_valid_pin('12a4'));
        $this->assertFalse(DBEM_Security::is_valid_pin(1234));
    }
}
