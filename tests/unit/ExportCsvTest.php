<?php

use PHPUnit\Framework\TestCase;

final class ExportCsvTest extends TestCase {
    private function row(array $values): string {
        $output = fopen('php://memory', 'w+');
        dbem_call_private('DBEM_Export', 'put_row', $output, $values);
        rewind($output);
        $line = stream_get_contents($output);
        fclose($output);
        return $line;
    }

    public function testQuotesAreDoubledAndBackslashIsPlainText(): void {
        // Con l'escape predefinito "\" la riga usciva "C:\""", non leggibile da Excel
        $this->assertSame("Anna;\"Dice \"\"ciao\"\"\";\"C:\\\"\"\"\n", $this->row(array('Anna', 'Dice "ciao"', 'C:\\"')));
    }

    public function testEveryCellIsNeutralizedHeadersIncluded(): void {
        $this->assertSame("ID;\"'=HYPERLINK(\"\"http://x\"\")\";'+1;'@a\n", $this->row(array('ID', '=HYPERLINK("http://x")', '+1', '@a')));
    }
}
