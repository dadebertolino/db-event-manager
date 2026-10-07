<?php

use PHPUnit\Framework\TestCase;

/**
 * Campi del form integrato (bug #18 del piano): validazione lato server
 */
final class FieldValueTest extends TestCase {
    private function check(array $field, $raw) {
        return DBEM_Registration::field_value($field + array('label' => 'Campo', 'required' => false, 'options' => array()), $raw);
    }

    public function testChoicesOnlyFromDefinedOptions(): void {
        $radio = array('type' => 'radio', 'options' => array('Lab A', 'Lab B'));
        $this->assertSame('', $this->check($radio, 'Lab A')['error']);
        $this->assertNotSame('', $this->check($radio, 'Lab Z')['error']);

        $checkbox = array('type' => 'checkbox', 'options' => array('Lab A', 'Lab B'));
        $this->assertSame(array('Lab A', 'Lab B'), $this->check($checkbox, array('Lab A', 'Lab B'))['value']);
        $this->assertNotSame('', $this->check($checkbox, array('Lab A', 'Inventato'))['error']);
    }

    public function testTypedFields(): void {
        $this->assertNotSame('', $this->check(array('type' => 'email'), 'non-email')['error']);
        $this->assertSame('', $this->check(array('type' => 'email'), 'a@example.com')['error']);
        $this->assertNotSame('', $this->check(array('type' => 'number'), 'dieci')['error']);
        $this->assertSame('', $this->check(array('type' => 'number'), '10')['error']);
        $this->assertNotSame('', $this->check(array('type' => 'date'), '2026-02-30')['error']);
        $this->assertSame('', $this->check(array('type' => 'date'), '2026-02-28')['error']);
    }

    public function testRequiredAcceptsZeroAndRejectsEmpty(): void {
        $required = array('type' => 'number', 'required' => true);
        $this->assertSame('', $this->check($required, '0')['error']);
        $this->assertStringContainsString('obbligatorio', $this->check($required, '')['error']);
        $this->assertStringContainsString('obbligatorio', $this->check(array('type' => 'checkbox', 'required' => true), array())['error']);
    }

    public function testArraysWhereTextIsExpectedAreEmpty(): void {
        $this->assertSame('', $this->check(array('type' => 'text'), array('x'))['value']);
    }

    public function testOptionalEmptyFieldIsValid(): void {
        $this->assertSame(array('value' => '', 'error' => ''), $this->check(array('type' => 'email'), ''));
    }
}
