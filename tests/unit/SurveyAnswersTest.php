<?php

use PHPUnit\Framework\TestCase;

/**
 * Risposte al survey per id della domanda (bug #37 del piano)
 */
final class SurveyAnswersTest extends TestCase {
    private const FIELDS = array(
        array('id' => 's_voto', 'type' => 'radio', 'label' => 'Giudizio', 'required' => true, 'options' => array()),
        array('id' => 's_note', 'type' => 'textarea', 'label' => 'Note', 'required' => false, 'options' => array()),
    );

    public function testAnswerByIdWithLabelFallback(): void {
        $this->assertSame('Ottimo', DBEM_Survey::answer(array('s_voto' => 'Ottimo'), self::FIELDS[0]));
        // Risposta non ancora convertita, salvata sotto l'etichetta
        $this->assertSame('Ok', DBEM_Survey::answer(array('Note' => 'Ok'), self::FIELDS[1]));
        $this->assertSame('', DBEM_Survey::answer(array('altro' => 'x'), self::FIELDS[1]));
    }

    public function testColumnsUseCurrentLabelsAndKeepDeletedQuestions(): void {
        $responses = array(
            (object) array('data' => json_encode(array('s_voto' => 'Ottimo', 'Domanda eliminata' => 'Sì'))),
            (object) array('data' => json_encode(array('Note' => 'Vecchia risposta'))),
        );

        $columns = DBEM_Survey::columns(self::FIELDS, $responses);

        $this->assertSame(array('s_voto' => 'Giudizio', 's_note' => 'Note', 'Domanda eliminata' => 'Domanda eliminata'), $columns);
        $this->assertSame('Vecchia risposta', DBEM_Survey::column_value(array('Note' => 'Vecchia risposta'), 's_note', self::FIELDS));
        $this->assertSame('Sì', DBEM_Survey::column_value(array('Domanda eliminata' => 'Sì'), 'Domanda eliminata', self::FIELDS));
    }
}
