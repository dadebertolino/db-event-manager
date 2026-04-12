<?php
if (!defined('ABSPATH')) exit;

$events = get_posts(array(
    'post_type'      => 'dbem_event',
    'post_status'    => 'publish',
    'posts_per_page' => 50,
    'orderby'        => 'date',
    'order'          => 'DESC',
));

$selected_event = absint($_GET['event_id'] ?? 0);
$preloaded_token = sanitize_text_field($_GET['token'] ?? '');
?>
<div class="wrap dbem-checkin-wrap">
    <h1><?php esc_html_e('Check-in Evento', 'db-event-manager'); ?></h1>

    <div class="dbem-checkin-select">
        <label for="dbem-event-select"><?php esc_html_e('Seleziona evento:', 'db-event-manager'); ?></label>
        <select id="dbem-event-select">
            <option value=""><?php esc_html_e('— Seleziona —', 'db-event-manager'); ?></option>
            <?php foreach ($events as $e): ?>
                <option value="<?php echo esc_attr($e->ID); ?>" <?php selected($selected_event, $e->ID); ?>>
                    <?php echo esc_html($e->post_title); ?>
                    <?php
                    $start = get_post_meta($e->ID, '_dbem_date_start', true);
                    if ($start) echo ' (' . esc_html(wp_date('d/m/Y', strtotime($start))) . ')';
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="dbem-checkin-panel" style="<?php echo $selected_event ? '' : 'display:none;'; ?>">
        <!-- Contatore -->
        <div class="dbem-checkin-counter" id="dbem-counter">
            <span class="dbem-counter-num" id="dbem-counter-checkedin">0</span>
            <span class="dbem-counter-sep">/</span>
            <span class="dbem-counter-num" id="dbem-counter-total">0</span>
            <span class="dbem-counter-label"><?php esc_html_e('presenti', 'db-event-manager'); ?></span>
        </div>

        <!-- Feedback area -->
        <div id="dbem-checkin-feedback" class="dbem-feedback" role="alert" aria-live="assertive" style="display:none;">
            <span class="dbem-feedback-icon"></span>
            <div class="dbem-feedback-text">
                <span class="dbem-feedback-name"></span>
                <span class="dbem-feedback-message"></span>
            </div>
        </div>

        <!-- Azioni -->
        <div class="dbem-checkin-actions">
            <button type="button" class="button button-primary button-hero" id="dbem-scan-btn">
                📷 <?php esc_html_e('Scansiona QR', 'db-event-manager'); ?>
            </button>

            <div class="dbem-search-box">
                <label for="dbem-search-input" class="screen-reader-text"><?php esc_html_e('Cerca partecipante', 'db-event-manager'); ?></label>
                <input type="text" id="dbem-search-input" placeholder="<?php esc_attr_e('Cerca per nome, email o token...', 'db-event-manager'); ?>" class="regular-text">
                <button type="button" class="button" id="dbem-search-btn"><?php esc_html_e('Cerca', 'db-event-manager'); ?></button>
            </div>
        </div>

        <!-- Scanner QR -->
        <div id="dbem-qr-scanner" style="display:none;">
            <div id="dbem-qr-reader" style="width:100%;max-width:400px;margin:0 auto;"></div>
            <button type="button" class="button" id="dbem-stop-scan"><?php esc_html_e('Chiudi scanner', 'db-event-manager'); ?></button>
        </div>

        <!-- Risultati ricerca -->
        <div id="dbem-search-results" style="display:none;">
            <h3><?php esc_html_e('Risultati ricerca', 'db-event-manager'); ?></h3>
            <div id="dbem-results-list"></div>
        </div>

        <!-- Lista partecipanti -->
        <div class="dbem-participants-list">
            <h3><?php esc_html_e('Partecipanti', 'db-event-manager'); ?></h3>
            <table class="widefat striped" id="dbem-checkin-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Stato', 'db-event-manager'); ?></th>
                        <th><?php esc_html_e('Nome', 'db-event-manager'); ?></th>
                        <th><?php esc_html_e('Email', 'db-event-manager'); ?></th>
                        <th><?php esc_html_e('Check-in', 'db-event-manager'); ?></th>
                        <th><?php esc_html_e('Azione', 'db-event-manager'); ?></th>
                    </tr>
                </thead>
                <tbody id="dbem-checkin-tbody">
                    <tr><td colspan="5"><?php esc_html_e('Seleziona un evento', 'db-event-manager'); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($preloaded_token): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof dbemCheckinProcessToken === 'function') {
            dbemCheckinProcessToken('<?php echo esc_js($preloaded_token); ?>');
        }
    });
</script>
<?php endif; ?>
