<?php

use PHPUnit\Framework\TestCase;

final class ExportCsvTest extends TestCase {
    private function row(array $values): string {
        $output = fopen('php://memory', 'w+');
        $method = new ReflectionMethod('DBEM_Export', 'put_row');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true); // da PHP 8.1 non serve, in 8.5 è deprecato
        }
        $method->invoke(null, $output, $values);
        rewind($output);
        $line = stream_get_contents($output);
        fclose($output);
        return $line;
    }

    public function testQuotesAreDoubledAndBackslashIsPlainText(): void {
        // Con l'escape predefinito "\" la riga usciva "C:\""", non leggibile da Excel
        $this->assertSame("Anna,\"Dice \"\"ciao\"\"\",\"C:\\\"\"\"\n", $this->row(array('Anna', 'Dice "ciao"', 'C:\\"')));
    }
}
