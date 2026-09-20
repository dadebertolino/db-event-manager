<?php

use PHPUnit\Framework\TestCase;

final class DbRegistrationTest extends TestCase {
    public function testCountRegistrationsPerEvent(): void {
        $count = DBEM_DB::count_registrations(10, 'confirmed');

        $this->assertSame(1, $count);
    }

    public function testEmailExistsForEventIgnoresCancelled(): void {
        $this->assertTrue(DBEM_DB::email_exists_for_event(10, 'alice@example.com'));
    }

    public function testSearchRegistrationsReturnsOnlyMatchingEvent(): void {
        $results = DBEM_DB::get_registrations(10);

        $this->assertCount(2, $results);
        foreach ($results as $row) {
            $this->assertSame(10, (int) $row->event_id);
        }
    }
}
