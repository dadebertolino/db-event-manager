<?php

use PHPUnit\Framework\TestCase;

/**
 * Testi delle email (bug #29 del piano): testo semplice, niente HTML letterale
 */
final class EmailPlainTextTest extends TestCase {
    public function testOldHtmlTextBecomesPlainText(): void {
        $this->assertSame("Ciao <nome>\nVieni & partecipa", DBEM_Email::plain_text('<b>Ciao</b> &lt;nome&gt;<br>Vieni &amp; partecipa'));
        $this->assertSame("Primo\n\nSecondo", DBEM_Email::plain_text('<p>Primo</p><p>Secondo</p>'));
    }

    public function testPlainTextIsUntouched(): void {
        $this->assertSame("Ciao {nome},\n\na presto!", DBEM_Email::plain_text("Ciao {nome},\n\na presto!"));
    }
}
