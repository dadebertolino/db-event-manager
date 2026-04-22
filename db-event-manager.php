<?php
/**
 * Plugin Name: DB Event Manager
 * Plugin URI: https://github.com/dadebertolino/db-event-manager
 * Description: Gestione eventi con iscrizione, QR code personale, check-in e survey post-evento. Niente Eventbrite, niente SaaS, niente abbonamenti.
 * Version: 1.1.0
 * Author: Davide Bertolino
 * Author URI: https://www.davidebertolino.it
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: db-event-manager
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('DBEM_VERSION', '1.1.0');
define('DBEM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DBEM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DBEM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principale singleton
 */
final class DB_Event_Manager {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once DBEM_PLUGIN_DIR . 'inc/class-db.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-cpt.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-admin.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-frontend.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-registration.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-email.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-qrcode.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-checkin.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-survey.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-export.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-cron.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-shortcodes.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-gutenberg.php';
        require_once DBEM_PLUGIN_DIR . 'inc/class-updater.php';

        // GitHub auto-updater
        new DB_GitHub_Updater(__FILE__, 'dadebertolino', 'db-event-manager');
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, array('DBEM_DB', 'activate'));
        register_deactivation_hook(__FILE__, array('DBEM_Cron', 'deactivate'));

        add_action('init', array('DBEM_CPT', 'register'));
        add_action('admin_menu', array('DBEM_Admin', 'register_menus'));
        add_action('admin_enqueue_scripts', array('DBEM_Admin', 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array('DBEM_Frontend', 'enqueue_scripts'));

        // AJAX
        add_action('wp_ajax_dbem_save_event_meta', array('DBEM_Admin', 'save_event_meta'));
        add_action('wp_ajax_nopriv_dbem_register', array('DBEM_Registration', 'handle_registration'));
        add_action('wp_ajax_dbem_register', array('DBEM_Registration', 'handle_registration'));
        add_action('wp_ajax_nopriv_dbem_register_dbfb', array('DBEM_Registration', 'handle_dbfb_registration'));
        add_action('wp_ajax_dbem_register_dbfb', array('DBEM_Registration', 'handle_dbfb_registration'));
        add_action('wp_ajax_dbem_checkin', array('DBEM_Checkin', 'handle_checkin'));
        add_action('wp_ajax_dbem_checkin_search', array('DBEM_Checkin', 'handle_search'));
        add_action('wp_ajax_nopriv_dbem_public_checkin', array('DBEM_Checkin', 'handle_public_checkin'));
        add_action('wp_ajax_dbem_public_checkin', array('DBEM_Checkin', 'handle_public_checkin'));
        add_action('wp_ajax_nopriv_dbem_public_search', array('DBEM_Checkin', 'handle_public_search'));
        add_action('wp_ajax_dbem_public_search', array('DBEM_Checkin', 'handle_public_search'));
        add_action('wp_ajax_nopriv_dbem_public_participants', array('DBEM_Checkin', 'handle_public_participants'));
        add_action('wp_ajax_dbem_public_participants', array('DBEM_Checkin', 'handle_public_participants'));
        add_action('wp_ajax_nopriv_dbem_public_participant_action', array('DBEM_Checkin', 'handle_public_participant_action'));
        add_action('wp_ajax_dbem_public_participant_action', array('DBEM_Checkin', 'handle_public_participant_action'));
        add_action('wp_ajax_dbem_bulk_action', array('DBEM_Admin', 'handle_bulk_action'));
        add_action('wp_ajax_dbem_resend_email', array('DBEM_Admin', 'handle_resend_email'));
        add_action('wp_ajax_dbem_export_csv', array('DBEM_Export', 'handle_export'));
        add_action('wp_ajax_nopriv_dbem_submit_survey', array('DBEM_Survey', 'handle_submit'));
        add_action('wp_ajax_dbem_submit_survey', array('DBEM_Survey', 'handle_submit'));
        add_action('wp_ajax_dbem_send_survey', array('DBEM_Survey', 'handle_send'));
        add_action('wp_ajax_dbem_export_survey', array('DBEM_Export', 'handle_survey_export'));

        // Shortcodes
        add_action('init', array('DBEM_Shortcodes', 'register'));

        // Gutenberg
        add_action('init', array('DBEM_Gutenberg', 'register_blocks'));

        // Cron
        add_action('dbem_cron_check_events', array('DBEM_Cron', 'check_events'));
        add_action('dbem_send_reminder', array('DBEM_Cron', 'send_reminder'), 10, 1);
        add_action('dbem_send_survey_auto', array('DBEM_Cron', 'send_survey_auto'), 10, 1);

        // Query vars per check-in e survey
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_endpoints'));

        // Metabox save
        add_action('save_post_dbem_event', array('DBEM_Admin', 'save_metabox'), 10, 2);

        // Template automatici per single e archive
        add_filter('template_include', array($this, 'load_templates'));

        // Ordina archivio per data evento (non data pubblicazione)
        add_action('pre_get_posts', array($this, 'order_archive'));
    }

    /**
     * Carica template plugin se il tema non li ha
     */
    public function load_templates($template) {
        if (is_singular('dbem_event')) {
            $theme_template = locate_template('single-dbem_event.php');
            if ($theme_template) return $theme_template;
            return DBEM_PLUGIN_DIR . 'templates/single-dbem_event.php';
        }
        if (is_post_type_archive('dbem_event')) {
            // Se c'è una pagina eventi custom configurata, redirect lì
            $page_id = (int) get_option('dbem_events_page_id', 0);
            if ($page_id && get_post_status($page_id) === 'publish') {
                wp_redirect(get_permalink($page_id), 301);
                exit;
            }
            $theme_template = locate_template('archive-dbem_event.php');
            if ($theme_template) return $theme_template;
            return DBEM_PLUGIN_DIR . 'templates/archive-dbem_event.php';
        }
        return $template;
    }

    /**
     * Ordina archivio eventi per data inizio (prossimi prima)
     */
    public function order_archive($query) {
        if (!is_admin() && $query->is_main_query() && is_post_type_archive('dbem_event')) {
            $query->set('meta_key', '_dbem_date_start');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'ASC');
            // Mostra solo eventi futuri o in corso
            $query->set('meta_query', array(
                'relation' => 'OR',
                array(
                    'key'     => '_dbem_date_end',
                    'value'   => current_time('mysql'),
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ),
                array(
                    'key'     => '_dbem_date_end',
                    'compare' => 'NOT EXISTS',
                ),
            ));
        }
    }

    public function add_query_vars($vars) {
        $vars[] = 'dbem_checkin';
        $vars[] = 'dbem_checkin_page';
        $vars[] = 'dbem_survey';
        $vars[] = 'dbem_action';
        $vars[] = 'dbem_participants_page';
        return $vars;
    }

    public function handle_endpoints() {
        // Approvazione/rifiuto via link email
        $action = isset($_GET['dbem_action']) ? sanitize_key($_GET['dbem_action']) : '';
        if ($action && in_array($action, array('approve', 'reject'))) {
            $this->handle_approval_action($action);
            exit;
        }

        // Conferma approvazione con orario (POST dal form)
        if ($action === 'approve_confirm') {
            $this->handle_approve_confirm();
            exit;
        }

        // Pagina pubblica partecipanti
        $participants_page = get_query_var('dbem_participants_page');
        if ($participants_page) {
            DBEM_Checkin::render_public_participants_page();
            exit;
        }

        // Pagina check-in pubblica
        $checkin_page = get_query_var('dbem_checkin_page');
        if ($checkin_page) {
            DBEM_Checkin::render_public_page();
            exit;
        }
        $checkin_token = get_query_var('dbem_checkin');
        if ($checkin_token) {
            DBEM_Checkin::handle_frontend_checkin(sanitize_text_field($checkin_token));
            exit;
        }
        $survey_token = get_query_var('dbem_survey');
        if ($survey_token) {
            DBEM_Survey::render_survey_page(sanitize_text_field($survey_token));
            exit;
        }
    }

    /**
     * Gestisci approvazione/rifiuto da link email
     */
    private function handle_approval_action($action) {
        $token = sanitize_text_field($_GET['token'] ?? '');
        $key = sanitize_text_field($_GET['key'] ?? '');

        if (!$token || !$key) {
            wp_die(__('Link non valido.', 'db-event-manager'), __('Errore', 'db-event-manager'), array('response' => 403));
        }

        if (!DBEM_Email::verify_action_key($token, $action, $key)) {
            wp_die(__('Link non valido o scaduto.', 'db-event-manager'), __('Errore', 'db-event-manager'), array('response' => 403));
        }

        DBEM_DB::ensure_tables();
        $reg = DBEM_DB::get_registration_by_token($token);

        if (!$reg) {
            wp_die(__('Iscrizione non trovata.', 'db-event-manager'), __('Errore', 'db-event-manager'), array('response' => 404));
        }

        if ($reg->status !== 'pending') {
            $status_labels = array(
                'confirmed'  => __('già approvata', 'db-event-manager'),
                'checked_in' => __('già presente all\'evento', 'db-event-manager'),
                'cancelled'  => __('annullata', 'db-event-manager'),
                'rejected'   => __('già rifiutata', 'db-event-manager'),
            );
            $label = $status_labels[$reg->status] ?? $reg->status;
            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2>ℹ️</h2>'
                . '<p>' . sprintf(esc_html__('Questa iscrizione è %s.', 'db-event-manager'), esc_html($label)) . '</p>'
                . '</div>',
                __('Iscrizione', 'db-event-manager'),
                array('response' => 200)
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $event_title = DBEM_CPT::get_event_name($reg->event_id);

        if ($action === 'approve') {
            // Se l'evento ha assegnazione orario, mostra form
            $time_slot_enabled = get_post_meta($reg->event_id, '_dbem_time_slot_enabled', true);
            if ($time_slot_enabled === '1') {
                $this->render_approve_with_time_form($reg, $event_title, $token, $key);
                return;
            }

            // Approvazione diretta (comportamento originale)
            $wpdb->update($table, array('status' => 'confirmed'), array('id' => $reg->id), array('%s'), array('%d'));
            // Rigenera oggetto con status aggiornato
            $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg->id));
            // Genera QR e invia email conferma
            DBEM_QRCode::generate($reg->token);
            DBEM_Email::send_confirmation($reg->event_id, $reg);

            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2 style="color:#1d6e3f;">✅</h2>'
                . '<h3>' . esc_html($reg->name) . '</h3>'
                . '<p>' . sprintf(esc_html__('Iscrizione a "%s" approvata.', 'db-event-manager'), esc_html($event_title)) . '</p>'
                . '<p style="color:#666;">' . esc_html__('L\'iscritto riceverà l\'email di conferma con il QR code.', 'db-event-manager') . '</p>'
                . '</div>',
                __('Iscrizione approvata', 'db-event-manager'),
                array('response' => 200)
            );
        } else {
            $wpdb->update($table, array('status' => 'rejected'), array('id' => $reg->id), array('%s'), array('%d'));
            $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg->id));
            DBEM_Email::send_rejection($reg->event_id, $reg);

            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2 style="color:#d63638;">❌</h2>'
                . '<h3>' . esc_html($reg->name) . '</h3>'
                . '<p>' . sprintf(esc_html__('Iscrizione a "%s" rifiutata.', 'db-event-manager'), esc_html($event_title)) . '</p>'
                . '<p style="color:#666;">' . esc_html__('L\'iscritto riceverà una notifica.', 'db-event-manager') . '</p>'
                . '</div>',
                __('Iscrizione rifiutata', 'db-event-manager'),
                array('response' => 200)
            );
        }
    }

    /**
     * Mostra form per inserire orario prima di approvare
     */
    private function render_approve_with_time_form($reg, $event_title, $token, $key) {
        $site_name = get_bloginfo('name');
        $event_start = get_post_meta($reg->event_id, '_dbem_date_start', true);
        $event_end = get_post_meta($reg->event_id, '_dbem_date_end', true);
        $location = get_post_meta($reg->event_id, '_dbem_location', true);

        $data = json_decode($reg->data, true);
        $riepilogo_html = '';
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (is_array($v)) $v = implode(', ', $v);
                $riepilogo_html .= '<tr><td style="padding:4px 12px 4px 0;color:#666;white-space:nowrap;">'
                    . esc_html($k) . '</td><td style="padding:4px 0;font-weight:500;">'
                    . esc_html($v) . '</td></tr>';
            }
        }

        $confirm_url = home_url('/?dbem_action=approve_confirm');
        $nonce = wp_create_nonce('dbem_approve_confirm_' . $token);

        $html = '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>' . esc_html__('Approva iscrizione', 'db-event-manager') . ' — ' . esc_html($site_name) . '</title>
        <style>
            *{box-sizing:border-box;margin:0;padding:0}
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f0f1;color:#1d2327;padding:20px}
            .dbem-aw{max-width:500px;margin:0 auto}
            .dbem-ah{background:#2271b1;color:#fff;padding:20px;border-radius:8px 8px 0 0;text-align:center}
            .dbem-ah h1{font-size:20px;margin:0}
            .dbem-ab{background:#fff;padding:24px;border:1px solid #ddd;border-top:none;border-radius:0 0 8px 8px}
            .dbem-ib{background:#f9f9f9;padding:16px;border-radius:6px;margin-bottom:20px}
            .dbem-ib h3{font-size:16px;margin-bottom:8px}
            .dbem-ib p{font-size:14px;color:#666;margin:4px 0}
            .dbem-ib table{font-size:14px}
            .dbem-f{margin-bottom:20px}
            .dbem-f label{display:block;font-weight:600;margin-bottom:6px;font-size:14px}
            .dbem-f input{width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:16px}
            .dbem-f input:focus{border-color:#2271b1;outline:none;box-shadow:0 0 0 2px rgba(34,113,177,.2)}
            .dbem-f .dbem-hint{font-size:13px;color:#666;margin-top:4px}
            .dbem-btns{display:flex;gap:12px;margin-top:24px}
            .dbem-btn{flex:1;padding:14px;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;text-align:center}
            .dbem-btn-a{background:#1d6e3f;color:#fff}.dbem-btn-a:hover{background:#155a32}
            .dbem-btn-r{background:#d63638;color:#fff}.dbem-btn-r:hover{background:#b32d2e}
            @media(max-width:480px){body{padding:10px}.dbem-btns{flex-direction:column}}
        </style></head><body>
        <div class="dbem-aw"><div class="dbem-ah"><h1>' . esc_html($site_name) . '</h1></div>
        <div class="dbem-ab">
            <div class="dbem-ib">
                <h3>' . esc_html__('Richiesta iscrizione', 'db-event-manager') . '</h3>
                <p><strong>' . esc_html__('Evento:', 'db-event-manager') . '</strong> ' . esc_html($event_title) . '</p>';
        if ($event_start) {
            $html .= '<p><strong>📅</strong> ' . esc_html(wp_date('d/m/Y H:i', strtotime($event_start)));
            if ($event_end) $html .= ' — ' . esc_html(wp_date('d/m/Y H:i', strtotime($event_end)));
            $html .= '</p>';
        }
        if ($location) $html .= '<p><strong>📍</strong> ' . esc_html($location) . '</p>';
        $html .= '</div>
            <div class="dbem-ib">
                <h3>👤 ' . esc_html($reg->name) . '</h3>
                <p>' . esc_html($reg->email) . '</p>';
        if ($riepilogo_html) $html .= '<table style="margin-top:8px">' . $riepilogo_html . '</table>';
        $html .= '</div>
            <form method="POST" action="' . esc_url($confirm_url) . '">
                <input type="hidden" name="token" value="' . esc_attr($token) . '">
                <input type="hidden" name="key" value="' . esc_attr($key) . '">
                <input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">
                <div class="dbem-f">
                    <label for="assigned_time">🕐 ' . esc_html__('Orario assegnato', 'db-event-manager') . '</label>
                    <input type="text" id="assigned_time" name="assigned_time"
                        placeholder="' . esc_attr__('Es. 10:30, 14:00-14:30, Turno A ore 9:00', 'db-event-manager') . '">
                    <p class="dbem-hint">' . esc_html__('Inserisci l\'orario da comunicare al partecipante. Lascia vuoto per approvare senza orario.', 'db-event-manager') . '</p>
                </div>
                <div class="dbem-btns">
                    <button type="submit" name="confirm_action" value="approve" class="dbem-btn dbem-btn-a">✅ ' . esc_html__('Approva', 'db-event-manager') . '</button>
                    <button type="submit" name="confirm_action" value="reject" class="dbem-btn dbem-btn-r">❌ ' . esc_html__('Rifiuta', 'db-event-manager') . '</button>
                </div>
            </form>
        </div></div></body></html>';
        echo $html;
        exit;
    }

    /**
     * Gestisci conferma approvazione con orario (POST dal form)
     */
    private function handle_approve_confirm() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $key = sanitize_text_field($_POST['key'] ?? '');
        $confirm_action = sanitize_key($_POST['confirm_action'] ?? 'approve');

        if (!$token || !$key) {
            wp_die(__('Dati mancanti.', 'db-event-manager'), __('Errore', 'db-event-manager'), array('response' => 403));
        }
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'dbem_approve_confirm_' . $token)) {
            wp_die(__('Richiesta scaduta. Riclicca il link dall\'email.', 'db-event-manager'), __('Errore', 'db-event-manager'), array('response' => 403));
        }
        if (!DBEM_Email::verify_action_key($token, 'approve', $key)) {
            wp_die(__('Link non valido o scaduto.', 'db-event-manager'), __('Errore', 'db-event-manager'), array('response' => 403));
        }

        DBEM_DB::ensure_tables();
        $reg = DBEM_DB::get_registration_by_token($token);
        if (!$reg) {
            wp_die(__('Iscrizione non trovata.', 'db-event-manager'), __('Errore', 'db-event-manager'), array('response' => 404));
        }
        if ($reg->status !== 'pending') {
            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2>ℹ️</h2><p>' . esc_html__('Questa iscrizione è già stata gestita.', 'db-event-manager') . '</p></div>',
                __('Iscrizione', 'db-event-manager'), array('response' => 200)
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $event_title = DBEM_CPT::get_event_name($reg->event_id);

        if ($confirm_action === 'reject') {
            $wpdb->update($table, array('status' => 'rejected'), array('id' => $reg->id), array('%s'), array('%d'));
            $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg->id));
            DBEM_Email::send_rejection($reg->event_id, $reg);
            wp_die(
                '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
                . '<h2 style="color:#d63638;">❌</h2>'
                . '<h3>' . esc_html($reg->name) . '</h3>'
                . '<p>' . sprintf(esc_html__('Iscrizione a "%s" rifiutata.', 'db-event-manager'), esc_html($event_title)) . '</p>'
                . '</div>',
                __('Iscrizione rifiutata', 'db-event-manager'), array('response' => 200)
            );
        }

        // Approvazione con orario
        $assigned_time = sanitize_text_field($_POST['assigned_time'] ?? '');
        $update_data = array('status' => 'confirmed');
        $update_format = array('%s');
        if (!empty($assigned_time)) {
            $update_data['assigned_time'] = $assigned_time;
            $update_format[] = '%s';
        }

        $wpdb->update($table, $update_data, array('id' => $reg->id), $update_format, array('%d'));
        $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg->id));
        DBEM_QRCode::generate($reg->token);
        DBEM_Email::send_confirmation($reg->event_id, $reg);

        $time_msg = !empty($assigned_time)
            ? '<p style="font-size:18px;margin-top:12px;">🕐 <strong>' . esc_html($assigned_time) . '</strong></p>'
            : '';

        wp_die(
            '<div style="text-align:center;padding:40px;font-family:sans-serif;">'
            . '<h2 style="color:#1d6e3f;">✅</h2>'
            . '<h3>' . esc_html($reg->name) . '</h3>'
            . '<p>' . sprintf(esc_html__('Iscrizione a "%s" approvata.', 'db-event-manager'), esc_html($event_title)) . '</p>'
            . $time_msg
            . '<p style="color:#666;">' . esc_html__('L\'iscritto riceverà l\'email di conferma con il QR code.', 'db-event-manager') . '</p>'
            . '</div>',
            __('Iscrizione approvata', 'db-event-manager'), array('response' => 200)
        );
    }
}

// Init
add_action('plugins_loaded', function() {
    DB_Event_Manager::get_instance();
});
