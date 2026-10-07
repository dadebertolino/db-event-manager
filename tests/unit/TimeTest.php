<?php

use PHPUnit\Framework\TestCase;

/**
 * Date in ora locale (bug #7 del piano): sito Europe/Rome, PHP in UTC come in WordPress
 */
final class TimeTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__dbem_options']['timezone_string'] = 'Europe/Rome';
    }

    protected function tearDown(): void {
        unset($GLOBALS['__dbem_options']['timezone_string']);
    }

    public function testLocalDateIsReadInSiteTimezone(): void {
        // 18:00 di ottobre a Roma (ora legale, UTC+2) = 16:00 UTC
        $this->assertSame(gmmktime(16, 0, 0, 10, 10, 2026), DBEM_Time::timestamp('2026-10-10T18:00'));
        // Stesso orario d'inverno (UTC+1) = 17:00 UTC
        $this->assertSame(gmmktime(17, 0, 0, 12, 10, 2026), DBEM_Time::timestamp('2026-12-10 18:00:00'));
    }

    public function testFormatShowsTheStoredTimeWithoutShifting(): void {
        $this->assertSame('10:15', DBEM_Time::format('H:i', '2026-10-07 10:15:00'));
        $this->assertSame('10/10/2026 18:00', DBEM_Time::format('d/m/Y H:i', '2026-10-10T18:00'));
    }

    public function testEmptyOrInvalidDates(): void {
        $this->assertSame(0, DBEM_Time::timestamp(''));
        $this->assertSame(0, DBEM_Time::timestamp('non è una data'));
        $this->assertSame('', DBEM_Time::format('H:i', ''));
        $this->assertFalse(DBEM_Time::is_past(''));
    }

    public function testIsPastUsesLocalTime(): void {
        // Un'ora fa in ora locale: con strtotime() risultava ancora nel futuro (+2 ore a Roma)
        $this->assertTrue(DBEM_Time::is_past(wp_date('Y-m-d\TH:i', time() - 3600)));
        $this->assertFalse(DBEM_Time::is_past(wp_date('Y-m-d\TH:i', time() + 3600)));
    }
}
