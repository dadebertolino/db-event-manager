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
$registrations = array();
$event_title = '';
$custom_fields = array();
$filter_field = isset($_GET['filter_field']) ? absint($_GET['filter_field']) : -1;
$filter_value = isset($_GET['filter_value']) ? sanitize_text_field(wp_unslash($_GET['filter_value'])) : '';

if ($selected_event) {
    DBEM_DB::ensure_tables();
    $registrations = DBEM_DB::get_registrations($selected_event);
    $event_title = DBEM_CPT::get_event_name($selected_event);
    $custom_fields = get_post_meta($selected_event, '_dbem_custom_fields', true);
    if (!is_array($custom_fields)) $custom_fields = array();

    if ($filter_field >= 0 && $filter_value !== '' && isset($custom_fields[$filter_field])) {
        $filter_label = $custom_fields[$filter_field]['label'] ?? '';
        $registrations = array_values(array_filter($registrations, function ($reg) use ($filter_label, $filter_value) {
            $data = json_decode($reg->data, true);
            if (!is_array($data) || !array_key_exists($filter_label, $data)) return false;

            $values = is_array($data[$filter_label]) ? $data[$filter_label] : array($data[$filter_label]);
            return in_array($filter_value, array_map('strval', $values), true);
        }));
    }
}

$filterable_fields = array();
foreach ($custom_fields as $index => $field) {
    if (!empty($field['options']) && !empty($field['label'])) {
        $filterable_fields[$index] = $field;
    }
}

$get_registration_choices = function ($reg) use ($custom_fields) {
    $data = json_decode($reg->data, true);
    $choices = array();
    if (!is_array($data)) return $choices;

    foreach ($custom_fields as $field) {
        $label = $field['label'] ?? '';
        if ($label === '' || !array_key_exists($label, $data)) continue;

        $values = is_array($data[$label]) ? $data[$label] : array($data[$label]);
        $values = array_filter(array_map('sanitize_text_field', $values), function ($value) {
            return $value !== '';
        });
        if ($values) $choices[$label] = $values;
    }

    return $choices;
};

$status_labels = array(
    'pending'    => array('label' => __('In attesa', 'db-event-manager'), 'icon' => '🕐', 'class' => 'pending'),
    'confirmed'  => array('label' => __('Confermato', 'db-event-manager'), 'icon' => '⏳', 'class' => 'confirmed'),
    'checked_in' => array('label' => __('Presente', 'db-event-manager'), 'icon' => '✅', 'class' => 'checked-in'),
    'cancelled'  => array('label' => __('Annullato', 'db-event-manager'), 'icon' => '❌', 'class' => 'cancelled'),
    'rejected'   => array('label' => __('Rifiutato', 'db-event-manager'), 'icon' => '🚫', 'class' => 'rejected'),
);
?>
<div class="wrap">
    <h1><?php esc_html_e('Gestione Partecipanti', 'db-event-manager'); ?></h1>

    <div class="dbem-event-selector">
        <form method="get">
            <input type="hidden" name="post_type" value="dbem_event">
            <input type="hidden" name="page" value="dbem-participants">
            <label for="event_id"><?php esc_html_e('Evento:', 'db-event-manager'); ?></label>
            <select name="event_id" id="event_id" onchange="this.form.submit()">
                <option value=""><?php esc_html_e('— Seleziona evento —', 'db-event-manager'); ?></option>
                <?php foreach ($events as $e): ?>
                    <option value="<?php echo esc_attr($e->ID); ?>" <?php selected($selected_event, $e->ID); ?>>
                        <?php echo esc_html($e->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($selected_event && $filterable_fields): ?>
                <label for="dbem-filter-field"><?php esc_html_e('Filtra attività:', 'db-event-manager'); ?></label>
                <select name="filter_field" id="dbem-filter-field">
                    <option value="-1"><?php esc_html_e('Tutti i campi', 'db-event-manager'); ?></option>
                    <?php foreach ($filterable_fields as $index => $field): ?>
                        <option value="<?php echo esc_attr($index); ?>" <?php selected($filter_field, $index); ?>>
                            <?php echo esc_html($field['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="filter_value" id="dbem-filter-value">
                    <option value=""><?php esc_html_e('Tutte le opzioni', 'db-event-manager'); ?></option>
                    <?php if (isset($filterable_fields[$filter_field])): ?>
                        <?php foreach ($filterable_fields[$filter_field]['options'] as $option): ?>
                            <option value="<?php echo esc_attr($option); ?>" <?php selected($filter_value, $option); ?>>
                                <?php echo esc_html($option); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button type="submit" class="button"><?php esc_html_e('Filtra', 'db-event-manager'); ?></button>
                <?php if ($filter_value !== ''): ?>
                    <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=dbem_event&page=dbem-participants&event_id=' . $selected_event)); ?>">
                        <?php esc_html_e('Azzera filtro', 'db-event-manager'); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($selected_event && $event_title): ?>
        <h2><?php echo esc_html($event_title); ?> — <?php echo esc_html(count($registrations)); ?> <?php esc_html_e('iscritti', 'db-event-manager'); ?></h2>

        <div class="dbem-toolbar">
            <div class="dbem-bulk-actions">
                <select id="dbem-bulk-select">
                    <option value=""><?php esc_html_e('Azioni in blocco', 'db-event-manager'); ?></option>
                    <option value="confirm"><?php esc_html_e('Conferma', 'db-event-manager'); ?></option>
                    <option value="cancel"><?php esc_html_e('Annulla', 'db-event-manager'); ?></option>
                    <option value="checkin"><?php esc_html_e('Segna presente', 'db-event-manager'); ?></option>
                    <option value="reject"><?php esc_html_e('Rifiuta', 'db-event-manager'); ?></option>
                    <option value="delete"><?php esc_html_e('Elimina', 'db-event-manager'); ?></option>
                </select>
                <button type="button" class="button" id="dbem-bulk-apply"><?php esc_html_e('Applica', 'db-event-manager'); ?></button>
            </div>

            <?php $export_url = wp_nonce_url(admin_url('admin-ajax.php?action=dbem_export_csv&event_id=' . $selected_event), 'dbem_admin_nonce', 'nonce'); ?>
            <label for="dbem-export-scope">
                <?php esc_html_e('Export:', 'db-event-manager'); ?>
                <select id="dbem-export-scope">
                    <option value="all"><?php esc_html_e('tutti i partecipanti', 'db-event-manager'); ?></option>
                    <option value="visible"><?php esc_html_e('solo quelli visualizzati', 'db-event-manager'); ?></option>
                </select>
            </label>
            <a id="dbem-export-csv" href="<?php echo esc_url($export_url); ?>" data-base="<?php echo esc_attr($export_url); ?>" class="button">
                📥 <?php esc_html_e('Esporta CSV', 'db-event-manager'); ?>
            </a>
            <label for="dbem-reminder-scope">
                <?php esc_html_e('Reminder:', 'db-event-manager'); ?>
                <select id="dbem-reminder-scope">
                    <option value="all"><?php esc_html_e('tutti i partecipanti validi', 'db-event-manager'); ?></option>
                    <option value="visible"><?php esc_html_e('solo quelli visualizzati', 'db-event-manager'); ?></option>
                </select>
            </label>
            <button type="button" class="button" id="dbem-send-reminder" data-event="<?php echo esc_attr($selected_event); ?>">
                📧 <?php esc_html_e('Invia reminder a tutti', 'db-event-manager'); ?>
            </button>
            <span id="dbem-reminder-feedback" aria-live="polite"></span>
        </div>

        <table class="widefat striped dbem-participants-table">
            <thead>
                <tr>
                    <td class="check-column"><input type="checkbox" id="dbem-select-all"></td>
                    <th><?php esc_html_e('Stato', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Nome', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Email', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Attività prenotate', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Data iscrizione', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Check-in', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Orario', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Azioni', 'db-event-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registrations)): ?>
                    <tr><td colspan="9"><?php esc_html_e('Nessun iscritto.', 'db-event-manager'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($registrations as $reg):
                        $s = $status_labels[$reg->status] ?? $status_labels['confirmed'];
                        $choices = $get_registration_choices($reg);
                    ?>
                    <tr data-id="<?php echo esc_attr($reg->id); ?>">
                        <td><input type="checkbox" class="dbem-row-check" value="<?php echo esc_attr($reg->id); ?>"></td>
                        <td>
                            <span class="dbem-status-badge dbem-status-<?php echo esc_attr($s['class']); ?>">
                                <?php echo esc_html($s['icon'] . ' ' . $s['label']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($reg->name); ?></td>
                        <td><a href="mailto:<?php echo esc_attr($reg->email); ?>"><?php echo esc_html($reg->email); ?></a></td>
                        <td class="dbem-registration-choices">
                            <?php if ($choices): ?>
                                <?php foreach ($choices as $label => $values): ?>
                                    <div><strong><?php echo esc_html($label); ?>:</strong> <?php echo esc_html(implode(', ', $values)); ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($reg->registered_at))); ?></td>
                        <td><?php echo $reg->checked_in_at ? esc_html(wp_date('d/m/Y H:i', strtotime($reg->checked_in_at))) : '—'; ?></td>
                        <td><?php echo !empty($reg->assigned_time) ? esc_html($reg->assigned_time) : '—'; ?></td>
                        <td class="dbem-actions">
                            <?php if ($reg->status === 'pending'): ?>
                                <button class="button button-small dbem-action-btn dbem-tip" data-action="confirm" data-id="<?php echo esc_attr($reg->id); ?>" aria-label="<?php esc_attr_e('Approva iscrizione', 'db-event-manager'); ?>" data-tooltip="<?php esc_attr_e('Approva', 'db-event-manager'); ?>">✅</button>
                                <button class="button button-small dbem-action-btn dbem-tip" data-action="reject" data-id="<?php echo esc_attr($reg->id); ?>" aria-label="<?php esc_attr_e('Rifiuta iscrizione', 'db-event-manager'); ?>" data-tooltip="<?php esc_attr_e('Rifiuta', 'db-event-manager'); ?>">🚫</button>
                            <?php elseif ($reg->status === 'confirmed'): ?>
                                <button class="button button-small dbem-action-btn dbem-tip" data-action="checkin" data-id="<?php echo esc_attr($reg->id); ?>" aria-label="<?php esc_attr_e('Segna presente', 'db-event-manager'); ?>" data-tooltip="<?php esc_attr_e('Segna presente', 'db-event-manager'); ?>">✅</button>
                                <button class="button button-small dbem-action-btn dbem-tip" data-action="cancel" data-id="<?php echo esc_attr($reg->id); ?>" aria-label="<?php esc_attr_e('Annulla iscrizione', 'db-event-manager'); ?>" data-tooltip="<?php esc_attr_e('Annulla iscrizione', 'db-event-manager'); ?>">❌</button>
                            <?php elseif ($reg->status === 'cancelled'): ?>
                                <button class="button button-small dbem-action-btn dbem-tip" data-action="confirm" data-id="<?php echo esc_attr($reg->id); ?>" aria-label="<?php esc_attr_e('Riconferma', 'db-event-manager'); ?>" data-tooltip="<?php esc_attr_e('Riconferma', 'db-event-manager'); ?>">🔄</button>
                            <?php endif; ?>
                            <button class="button button-small dbem-resend-btn dbem-tip" data-id="<?php echo esc_attr($reg->id); ?>" aria-label="<?php esc_attr_e('Reinvia email conferma', 'db-event-manager'); ?>" data-tooltip="<?php esc_attr_e('Reinvia email', 'db-event-manager'); ?>">📧</button>
                            <button class="button button-small dbem-action-btn dbem-delete-btn dbem-tip" data-action="delete" data-id="<?php echo esc_attr($reg->id); ?>" aria-label="<?php esc_attr_e('Elimina iscrizione', 'db-event-manager'); ?>" data-tooltip="<?php esc_attr_e('Elimina', 'db-event-manager'); ?>">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
