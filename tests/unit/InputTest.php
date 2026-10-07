<?php

use PHPUnit\Framework\TestCase;

/**
 * Bug #31 del piano: un parametro inviato come array non manda in errore la richiesta
 */
final class InputTest extends TestCase {
    protected function tearDown(): void {
        $_POST = array();
        $_GET = array();
    }

    public function testArraysBecomeTheDefault(): void {
        $_POST['dbem_email'] = array('a@example.com');
        $this->assertSame('', DBEM_Security::input('dbem_email'));
        $this->assertSame('x', DBEM_Security::input('dbem_email', 'x'));
    }

    public function testScalarsAreUnslashedAndCleaned(): void {
        $_POST['dbem_name'] = "  O\\'Brien  ";
        $this->assertSame("O'Brien", DBEM_Security::input('dbem_name'));
        $_GET['dbem_action'] = 'approve';
        $this->assertSame('approve', DBEM_Security::input('dbem_action', '', 'get'));
        $this->assertSame('auto', DBEM_Security::input('assente', 'auto'));
    }
}
