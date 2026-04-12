<?php
if (!defined('ABSPATH')) exit;

$events = get_posts(array(
    'post_type'      => 'dbem_event',
    'post_status'    => array('publish', 'draft'),
    'posts_per_page' => 100,
    'orderby'        => 'date',
    'order'          => 'DESC',
));

$selected_event = absint($_GET['event_id'] ?? 0);
$event_title = '';
$responses = array();
$survey_fields = array();
$total_checked_in = 0;
$total_regs = 0;

if ($selected_event) {
    DBEM_DB::ensure_tables();
    $event_title = DBEM_CPT::get_event_name($selected_event);
    $responses = DBEM_DB::get_survey_responses($selected_event);
    $survey_fields = get_post_meta($selected_event, '_dbem_survey_fields', true);
    if (!is_array($survey_fields)) $survey_fields = array();
    $total_checked_in = DBEM_DB::count_registrations($selected_event, 'checked_in');
    $total_regs = DBEM_DB::count_registrations($selected_event);
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Survey Post-Evento', 'db-event-manager'); ?></h1>

    <div class="dbem-event-selector">
        <form method="get">
            <input type="hidden" name="post_type" value="dbem_event">
            <input type="hidden" name="page" value="dbem-survey">
            <label for="event_id"><?php esc_html_e('Evento:', 'db-event-manager'); ?></label>
            <select name="event_id" id="event_id" onchange="this.form.submit()">
                <option value=""><?php esc_html_e('— Seleziona evento —', 'db-event-manager'); ?></option>
                <?php foreach ($events as $e): ?>
                    <option value="<?php echo esc_attr($e->ID); ?>" <?php selected($selected_event, $e->ID); ?>>
                        <?php echo esc_html($e->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($selected_event && $event_title): ?>
        <h2><?php echo esc_html($event_title); ?></h2>

        <?php
        $survey_enabled = get_post_meta($selected_event, '_dbem_survey_enabled', true);
        if ($survey_enabled !== '1'):
        ?>
            <div class="notice notice-warning"><p><?php esc_html_e('Il survey non è attivo per questo evento. Attivalo dalla scheda dell\'evento.', 'db-event-manager'); ?></p></div>
        <?php else: ?>

        <!-- Azioni invio -->
        <div class="dbem-survey-actions" style="margin-bottom:20px;">
            <p>
                <?php printf(
                    esc_html__('Risposte: %d | Presenti (checked-in): %d | Iscritti totali: %d', 'db-event-manager'),
                    count($responses), $total_checked_in, $total_regs
                ); ?>
            </p>
            <button type="button" class="button button-primary" id="dbem-send-survey" data-event="<?php echo esc_attr($selected_event); ?>" data-target="checked_in">
                📧 <?php esc_html_e('Invia survey ai presenti', 'db-event-manager'); ?>
            </button>
            <button type="button" class="button" id="dbem-send-survey-all" data-event="<?php echo esc_attr($selected_event); ?>" data-target="all">
                📧 <?php esc_html_e('Invia survey a tutti gli iscritti', 'db-event-manager'); ?>
            </button>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=dbem_export_survey&event_id=' . $selected_event), 'dbem_admin_nonce', 'nonce')); ?>" class="button">
                📥 <?php esc_html_e('Esporta CSV', 'db-event-manager'); ?>
            </a>
            <span id="dbem-survey-feedback" style="margin-left:10px;"></span>
        </div>

        <?php if (empty($responses)): ?>
            <p><?php esc_html_e('Nessuna risposta ricevuta.', 'db-event-manager'); ?></p>
        <?php else: ?>
            <!-- Riepilogo -->
            <?php if (!empty($survey_fields)): ?>
            <div class="dbem-survey-summary">
                <h3><?php esc_html_e('Riepilogo risposte', 'db-event-manager'); ?></h3>
                <?php foreach ($survey_fields as $field):
                    $label = $field['label'];
                    $type = $field['type'];
                    // Raccogli valori
                    $values = array();
                    foreach ($responses as $resp) {
                        $data = json_decode($resp->data, true);
                        if (isset($data[$label])) {
                            $val = $data[$label];
                            if (is_array($val)) {
                                foreach ($val as $v) $values[] = $v;
                            } else {
                                $values[] = $val;
                            }
                        }
                    }
                ?>
                <div class="dbem-summary-field" style="margin-bottom:15px;">
                    <h4><?php echo esc_html($label); ?></h4>
                    <?php if (in_array($type, array('select', 'radio', 'checkbox'))): ?>
                        <?php
                        $counts = array_count_values($values);
                        arsort($counts);
                        ?>
                        <table class="widefat" style="max-width:400px;">
                            <thead><tr><th><?php esc_html_e('Risposta', 'db-event-manager'); ?></th><th><?php esc_html_e('Conteggio', 'db-event-manager'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($counts as $val => $cnt): ?>
                                <tr><td><?php echo esc_html($val); ?></td><td><?php echo esc_html($cnt); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="description"><?php echo esc_html(count($values)); ?> <?php esc_html_e('risposte testuali', 'db-event-manager'); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Tabella risposte -->
            <h3><?php esc_html_e('Tutte le risposte', 'db-event-manager'); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Nome', 'db-event-manager'); ?></th>
                        <th><?php esc_html_e('Email', 'db-event-manager'); ?></th>
                        <th><?php esc_html_e('Data', 'db-event-manager'); ?></th>
                        <?php foreach ($survey_fields as $f): ?>
                            <th><?php echo esc_html($f['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($responses as $resp):
                        $data = json_decode($resp->data, true);
                    ?>
                    <tr>
                        <td><?php echo esc_html($resp->name); ?></td>
                        <td><?php echo esc_html($resp->email); ?></td>
                        <td><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($resp->submitted_at))); ?></td>
                        <?php foreach ($survey_fields as $f):
                            $val = $data[$f['label']] ?? '';
                            if (is_array($val)) $val = implode(', ', $val);
                        ?>
                            <td><?php echo esc_html($val); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
