<?php

use PHPUnit\Framework\TestCase;

/**
 * Email (1.9.0): gli indirizzi web scritti dall'iscritto non diventano link
 */
final class EmailLinksTest extends TestCase {
    private function reg(): object {
        return (object) array(
            'id'    => 1,
            'name'  => 'Hai vinto https://evil.example',
            'email' => 'vittima@example.com',
            'token' => 'abc',
            'data'  => json_encode(array('nome' => 'Hai vinto https://evil.example', 'Note' => 'vai su www.evil.example', 'Lab' => array('http://x.test'))),
        );
    }

    public function testRegistrantUrlsAreBroken(): void {
        $safe = DBEM_Email::without_links($this->reg());

        $this->assertStringNotContainsString('://', $safe->name);
        $data = json_decode($safe->data, true);
        $this->assertStringNotContainsString('://', $data['nome']);
        $this->assertStringNotContainsString('www.', $data['Note']);
        $this->assertStringNotContainsString('://', $data['Lab'][0]);
        // Il testo resta leggibile: cambia solo un carattere invisibile
        $this->assertSame('Hai vinto https://evil.example', str_replace("\u{2060}", '', $safe->name));
    }

    public function testOriginalRegistrationIsUntouched(): void {
        $reg = $this->reg();
        DBEM_Email::without_links($reg);

        $this->assertSame('Hai vinto https://evil.example', $reg->name);
    }

    public function testEmailBodyLinksOnlyTemplateUrls(): void {
        $GLOBALS['__dbem_sent_mail'] = array();
        DBEM_Email::send_update_confirmation(10, $this->reg(), 'https://example.com/?dbem_action=confirm_update&key=k');

        $html = $GLOBALS['__dbem_sent_mail'][0]['message'];
        $this->assertStringContainsString('<a href="https://example.com/?dbem_action=confirm_update', $html);
        $this->assertStringNotContainsString('href="https://evil.example', $html);
    }
}
