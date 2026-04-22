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

if ($selected_event) {
    DBEM_DB::ensure_tables();
    $registrations = DBEM_DB::get_registrations($selected_event);
    $event_title = DBEM_CPT::get_event_name($selected_event);
}

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

            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=dbem_export_csv&event_id=' . $selected_event), 'dbem_admin_nonce', 'nonce')); ?>" class="button">
                📥 <?php esc_html_e('Esporta CSV', 'db-event-manager'); ?>
            </a>
        </div>

        <table class="widefat striped dbem-participants-table">
            <thead>
                <tr>
                    <td class="check-column"><input type="checkbox" id="dbem-select-all"></td>
                    <th><?php esc_html_e('Stato', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Nome', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Email', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Data iscrizione', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Check-in', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Orario', 'db-event-manager'); ?></th>
                    <th><?php esc_html_e('Azioni', 'db-event-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registrations)): ?>
                    <tr><td colspan="8"><?php esc_html_e('Nessun iscritto.', 'db-event-manager'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($registrations as $reg):
                        $s = $status_labels[$reg->status] ?? $status_labels['confirmed'];
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
