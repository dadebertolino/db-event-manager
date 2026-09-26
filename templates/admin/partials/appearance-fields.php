<?php
/**
 * Campi "Colori": sfondo, pulsanti, testo, con anteprima e avviso di contrasto.
 * Usato dalle Impostazioni (valori globali) e dai Dettagli Evento.
 *
 * Variabili attese:
 * @var string $appearance_name     Nome del campo nel form (es. 'dbem_appearance').
 * @var string $appearance_prefix   Prefisso degli id degli input.
 * @var array  $appearance_values   Valori correnti {color_bg, color_primary, color_text}.
 * @var array  $appearance_inherit  Valori usati se il campo è vuoto (i globali per l'evento, [] per i globali).
 * @var string $appearance_empty    Cosa succede lasciando vuoto un campo.
 */
if (!defined('ABSPATH')) exit;

$appearance_fields = array(
    'color_bg'      => __('Colore di sfondo', 'db-event-manager'),
    'color_primary' => __('Colore dei pulsanti', 'db-event-manager'),
    'color_text'    => __('Colore del testo', 'db-event-manager'),
);
?>
<div class="dbem-appearance"
     data-inherit-bg="<?php echo esc_attr($appearance_inherit['color_bg'] ?? ''); ?>"
     data-inherit-primary="<?php echo esc_attr($appearance_inherit['color_primary'] ?? ''); ?>"
     data-inherit-text="<?php echo esc_attr($appearance_inherit['color_text'] ?? ''); ?>">

    <div class="dbem-appearance-fields">
        <?php foreach ($appearance_fields as $key => $label):
            $input_id = $appearance_prefix . str_replace('_', '-', $key); ?>
            <div class="dbem-appearance-field">
                <label for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($label); ?></label>
                <input type="text"
                       id="<?php echo esc_attr($input_id); ?>"
                       name="<?php echo esc_attr($appearance_name . '[' . $key . ']'); ?>"
                       class="dbem-color-input"
                       data-color-key="<?php echo esc_attr(str_replace('color_', '', $key)); ?>"
                       value="<?php echo esc_attr($appearance_values[$key] ?? ''); ?>">
            </div>
        <?php endforeach; ?>
    </div>

    <p class="description"><?php echo esc_html($appearance_empty); ?></p>
    <p class="description">
        <?php esc_html_e('Il colore del testo dei pulsanti (bianco o nero), quello al passaggio del mouse e quello dei link vengono calcolati automaticamente per garantire il contrasto minimo WCAG AA. Con uno sfondo e senza colore del testo, il testo diventa nero o bianco secondo lo sfondo.', 'db-event-manager'); ?>
    </p>

    <div class="dbem-appearance-preview" aria-hidden="true">
        <span class="dbem-appearance-preview-date"><strong>12</strong> <?php esc_html_e('ott', 'db-event-manager'); ?></span>
        <span class="dbem-appearance-preview-label"><?php esc_html_e('Nome e Cognome', 'db-event-manager'); ?></span>
        <span class="dbem-appearance-preview-input"><?php esc_html_e('Campo di testo', 'db-event-manager'); ?></span>
        <span class="dbem-appearance-preview-text">
            <?php esc_html_e('Testo con', 'db-event-manager'); ?>
            <span class="dbem-appearance-preview-link"><?php esc_html_e('un link', 'db-event-manager'); ?></span>
        </span>
        <span class="dbem-appearance-preview-button"><?php esc_html_e('Iscriviti', 'db-event-manager'); ?></span>
    </div>

    <div class="dbem-appearance-warning" role="status" aria-live="polite"></div>
</div>
