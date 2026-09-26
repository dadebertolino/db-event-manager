<?php

use PHPUnit\Framework\TestCase;

/**
 * Verifica cache-safe degli endpoint pubblici (DBEM_Security, 1.8.0):
 * nonce per i loggati, origine + rate limit per gli anonimi.
 */
final class PublicRequestTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__dbem_transients'] = array();
        $GLOBALS['__dbem_filters'] = array();
        $GLOBALS['__dbem_logged_in'] = false;
        unset($GLOBALS['__dbem_json_error'], $_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    }

    protected function tearDown(): void {
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER'], $_SERVER['REMOTE_ADDR']);
    }

    public function testOriginMatchesSameHostOnly(): void {
        $site = array('https://example.com', 'https://example.com/wp');

        $this->assertTrue(DBEM_Security::origin_matches('https://example.com', $site));
        $this->assertTrue(DBEM_Security::origin_matches('http://EXAMPLE.com:8080/evento/?x=1', $site));
        $this->assertFalse(DBEM_Security::origin_matches('https://example.com.evil.test', $site));
        $this->assertFalse(DBEM_Security::origin_matches('https://evil.test/?https://example.com', $site));
        $this->assertFalse(DBEM_Security::origin_matches('null', $site));
        $this->assertFalse(DBEM_Security::origin_matches('', $site));
        $this->assertFalse(DBEM_Security::origin_matches(null, $site));
    }

    public function testRegistrationWrapperDelegates(): void {
        $this->assertTrue(DBEM_Registration::origin_matches('https://example.com/x', array('https://example.com')));
        $this->assertFalse(DBEM_Registration::origin_matches('https://evil.test', array('https://example.com')));
    }

    public function testSameSiteUsesOriginThenReferer(): void {
        $this->assertFalse(DBEM_Security::is_same_site_request());

        $_SERVER['HTTP_REFERER'] = 'https://example.com/evento/';
        $this->assertTrue(DBEM_Security::is_same_site_request());

        // Origin, se presente, prevale sul Referer
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.test';
        $this->assertFalse(DBEM_Security::is_same_site_request());
    }

    public function testAnonymousCrossOriginIsRejected(): void {
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.test';

        try {
            DBEM_Security::verify_request('dbem_survey_submit', 'dbem_survey_nonce', 'survey', 5, 'Origine non valida');
            $this->fail('Richiesta cross-origin accettata');
        } catch (RuntimeException $e) {
            $this->assertSame('bad_origin', $GLOBALS['__dbem_json_error']['code']);
            $this->assertSame('Origine non valida', $GLOBALS['__dbem_json_error']['message']);
        }
    }

    public function testAnonymousSameOriginIsRateLimited(): void {
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue(DBEM_Security::verify_request('a', 'f', 'survey', 3));
        }

        try {
            DBEM_Security::verify_request('a', 'f', 'survey', 3);
            $this->fail('Rate limit non applicato');
        } catch (RuntimeException $e) {
            $this->assertSame('rate_limited', $GLOBALS['__dbem_json_error']['code']);
        }

        // Contesti separati: il limite del questionario non blocca le pagine staff
        $this->assertTrue(DBEM_Security::verify_request('a', 'f', 'public', 3));
    }

    public function testRateLimitKeyIsSaltedAndPerContext(): void {
        $key = DBEM_Security::rate_limit_key('survey', '203.0.113.7');

        $this->assertStringStartsWith('dbem_rate_', $key);
        $this->assertStringNotContainsString('203.0.113.7', $key);
        $this->assertNotSame($key, DBEM_Security::rate_limit_key('registration', '203.0.113.7'));
    }

    public function testRateLimitFilterCanDisable(): void {
        $GLOBALS['__dbem_filters']['dbem_survey_rate_limit'] = 0;

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue(DBEM_Security::check_rate_limit('survey', 1));
        }
        $this->assertSame(array(), $GLOBALS['__dbem_transients']);
    }

    public function testZeroLimitSkipsRateLimitInVerify(): void {
        $_SERVER['HTTP_REFERER'] = 'https://example.com/evento/';

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue(DBEM_Security::verify_request('a', 'f', 'registration', 0));
        }
        $this->assertSame(array(), $GLOBALS['__dbem_transients']);
    }

    public function testLoggedInUsesNonceWithoutOriginCheck(): void {
        $GLOBALS['__dbem_logged_in'] = true;
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.test';

        // Lo stub di check_ajax_referer accetta: nessun controllo di origine né rate limit
        $this->assertTrue(DBEM_Security::verify_request('a', 'f', 'survey', 1));
        $this->assertTrue(DBEM_Security::verify_request('a', 'f', 'survey', 1));
        $this->assertSame(array(), $GLOBALS['__dbem_transients']);
    }

    public function testPublicNonceOnlyForLoggedInUsers(): void {
        $this->assertSame('', DBEM_Security::public_nonce());

        $GLOBALS['__dbem_logged_in'] = true;
        $this->assertNotSame('', DBEM_Security::public_nonce());
    }

    public function testPublicRequestStillRequiresPin(): void {
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $GLOBALS['__dbem_options']['dbem_checkin_pin'] = '123456';
        $_POST['pin'] = '000000';

        try {
            DBEM_Security::verify_public_request();
            $this->fail('PIN errato accettato');
        } catch (RuntimeException $e) {
            $this->assertSame('pin_error', $GLOBALS['__dbem_json_error']['status']);
        } finally {
            unset($_POST['pin']);
        }

        $_POST['pin'] = '123456';
        try {
            $this->assertTrue(DBEM_Security::verify_public_request());
        } finally {
            unset($_POST['pin']);
        }
    }
}
