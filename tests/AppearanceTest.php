<?php

use PHPUnit\Framework\TestCase;

final class AppearanceTest extends TestCase {
    protected function setUp(): void {
        unset($GLOBALS['__dbem_options'][DBEM_Appearance::OPTION], $GLOBALS['__dbem_post_meta'][50]);
    }

    public function testNoColorsLeavesOutputUnchanged(): void {
        $attributes = DBEM_Appearance::wrapper_attributes('dbem-event-wrapper', 50);

        $this->assertSame(' class="dbem-event-wrapper dbem-colors"', $attributes);
    }

    public function testSanitizeKeepsOnlyKnownHexColors(): void {
        $colors = DBEM_Appearance::sanitize_colors(array(
            'color_bg'      => '#ABC',
            'color_primary' => 'red; background:url(x)',
            'color_text'    => '#123456',
            'altro'         => '#ffffff',
        ));

        $this->assertSame(array('color_bg' => '#aabbcc', 'color_primary' => '', 'color_text' => '#123456'), $colors);
    }

    public function testEventColorsOverrideGlobalOnesKeyByKey(): void {
        $GLOBALS['__dbem_options'][DBEM_Appearance::OPTION] = array('color_bg' => '#f5f5f5', 'color_primary' => '#0056b3', 'color_text' => '');
        $GLOBALS['__dbem_post_meta'][50][DBEM_Appearance::META] = array('color_bg' => '', 'color_primary' => '#8a1c1c', 'color_text' => '');

        $this->assertSame(
            array('color_bg' => '#f5f5f5', 'color_primary' => '#8a1c1c', 'color_text' => ''),
            DBEM_Appearance::get_colors(50)
        );
    }

    public function testLightButtonGetsBlackTextAndLighterHover(): void {
        $vars = DBEM_Appearance::get_css_vars(array('color_bg' => '', 'color_primary' => '#ffd400', 'color_text' => '#222222'));

        $this->assertSame('#000000', $vars['--dbem-button-text']);
        $this->assertSame(DBEM_Appearance::mix('#ffd400', '#ffffff', 0.25), $vars['--dbem-primary-hover']);
        // Il giallo sul bianco non è leggibile: i link prendono il colore del testo
        $this->assertSame('#222222', $vars['--dbem-link']);
    }

    public function testDarkBackgroundWithoutTextGetsReadableText(): void {
        $vars = DBEM_Appearance::get_css_vars(array('color_bg' => '#10213a', 'color_primary' => '', 'color_text' => ''));

        $this->assertSame('#ffffff', $vars['--dbem-text']);
        $this->assertSame('#ffffff', $vars['--dbem-text-muted']);
        $this->assertGreaterThanOrEqual(4.5, DBEM_Appearance::contrast($vars['--dbem-text'], '#10213a'));
        $this->assertArrayNotHasKey('--dbem-primary', $vars);
    }

    public function testButtonTextAlwaysMeetsContrast(): void {
        foreach (array('#2271b1', '#767676', '#ffd400', '#00a32a', '#000000', '#ffffff', '#ff00ff') as $color) {
            $vars = DBEM_Appearance::get_css_vars(array('color_bg' => '', 'color_primary' => $color, 'color_text' => ''));
            $this->assertGreaterThanOrEqual(4.5, DBEM_Appearance::contrast($color, $vars['--dbem-button-text']), $color);
        }
    }

    public function testWrapperMarksBackgroundAndCustomColors(): void {
        $GLOBALS['__dbem_post_meta'][50][DBEM_Appearance::META] = array('color_bg' => '#10213a', 'color_primary' => '', 'color_text' => '');

        $attributes = DBEM_Appearance::wrapper_attributes('dbem-single-event', 50);

        $this->assertStringContainsString('class="dbem-single-event dbem-colors dbem-custom-colors dbem-has-bg"', $attributes);
        $this->assertStringContainsString('style="--dbem-bg:#10213a;', $attributes);
    }
}
