<?php
/**
 * Plugin Name: DB Event Manager
 * Plugin URI: https://github.com/dadebertolino/db-event-manager
 * Description: Gestione eventi con iscrizione, QR code personale, check-in e survey post-evento. Niente Eventbrite, niente SaaS, niente abbonamenti.
 * Version: 1.0.0
 * Author: Davide Bertolino
 * Author URI: https://www.davidebertolino.it
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: db-event-manager
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('DBEM_VERSION', '1.0.0');
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
        return $vars;
    }

    public function handle_endpoints() {
        // Approvazione/rifiuto via link email
        $action = isset($_GET['dbem_action']) ? sanitize_key($_GET['dbem_action']) : '';
        if ($action && in_array($action, array('approve', 'reject'))) {
            $this->handle_approval_action($action);
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
}

// Init
add_action('plugins_loaded', function() {
    DB_Event_Manager::get_instance();
});
