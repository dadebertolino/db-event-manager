<?php

use PHPUnit\Framework\TestCase;

/**
 * Survey (bug #21 del piano): risponde solo chi ha un'iscrizione valida, con il survey attivo
 */
final class SurveyAccessTest extends TestCase {
    protected function tearDown(): void {
        $GLOBALS['__dbem_post_meta'] = array();
    }

    public function testOnlyConfirmedOrCheckedInCanAnswer(): void {
        foreach (array('confirmed' => true, 'checked_in' => true, 'pending' => false, 'rejected' => false, 'cancelled' => false) as $status => $allowed) {
            $this->assertSame($allowed, DBEM_Survey::can_answer((object) array('status' => $status)), $status);
        }
    }

    public function testSurveyMustBeEnabled(): void {
        $this->assertFalse(DBEM_Survey::is_enabled(10));
        $GLOBALS['__dbem_post_meta'][10]['_dbem_survey_enabled'] = '1';
        $this->assertTrue(DBEM_Survey::is_enabled(10));
    }
}
