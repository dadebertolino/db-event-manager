<?php

use PHPUnit\Framework\TestCase;

/**
 * Formato delle date (#40 del piano): predefinito invariato, formato di WordPress o personalizzato
 */
final class DateFormatTest extends TestCase {
    protected function tearDown(): void {
        unset($GLOBALS['__dbem_options']['dbem_date_format'], $GLOBALS['__dbem_options']['date_format'], $GLOBALS['__dbem_options']['time_format']);
    }

    public function testDefaultIsUnchanged(): void {
        $this->assertSame('10/10/2026', DBEM_Time::format_date('2026-10-10T18:00'));
        $this->assertSame('10/10/2026 18:00', DBEM_Time::format_datetime('2026-10-10T18:00'));
    }

    public function testWordPressFormat(): void {
        $GLOBALS['__dbem_options']['dbem_date_format'] = 'wp';
        $GLOBALS['__dbem_options']['date_format'] = 'Y-m-d';
        $GLOBALS['__dbem_options']['time_format'] = 'g:i a';

        $this->assertSame('2026-10-10 6:00 pm', DBEM_Time::format_datetime('2026-10-10T18:00'));
    }

    public function testCustomFormat(): void {
        $GLOBALS['__dbem_options']['dbem_date_format'] = 'd.m.y';

        $this->assertSame('10.10.26', DBEM_Time::format_date('2026-10-10'));
        $this->assertSame('', DBEM_Time::format_date(''));
    }
}
