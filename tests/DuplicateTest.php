<?php

use PHPUnit\Framework\TestCase;

final class DuplicateTest extends TestCase {
    public function testCopiesEventSettingsAndSkipsRevisionMeta(): void {
        $copy = DBEM_Duplicate::meta_to_copy(array(
            '_dbem_location'      => array('Aula magna'),
            '_dbem_custom_fields' => array('a:0:{}'),
            '_thumbnail_id'       => array('12'),
            '_edit_lock'          => array('1700000000:1'),
            '_edit_last'          => array('1'),
            '_wp_old_slug'        => array('evento-vecchio'),
        ));

        $this->assertSame(array('Aula magna'), $copy['_dbem_location']);
        $this->assertSame(array('a:0:{}'), $copy['_dbem_custom_fields']);
        $this->assertSame(array('12'), $copy['_thumbnail_id']);
        $this->assertArrayNotHasKey('_edit_lock', $copy);
        $this->assertArrayNotHasKey('_edit_last', $copy);
        $this->assertArrayNotHasKey('_wp_old_slug', $copy);
    }

    public function testRegistrationsReopenOnTheCopy(): void {
        $copy = DBEM_Duplicate::meta_to_copy(array('_dbem_registration_open' => array('0')));
        $this->assertSame(array('1'), $copy['_dbem_registration_open']);

        $copy = DBEM_Duplicate::meta_to_copy(array());
        $this->assertSame(array('1'), $copy['_dbem_registration_open']);
    }
}
