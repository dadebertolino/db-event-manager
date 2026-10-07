<?php
/**
 * Plugin Name: DBEM E2E Fixture
 * Description: Condizioni di test deterministiche per gli E2E di DB Event Manager.
 *              Attivo SOLO in wp-env (mu-plugin mappato da .wp-env.json) e solo con
 *              WP_ENVIRONMENT_TYPE "local". NON fa parte del pacchetto distribuito.
 *
 * Fornisce:
 *  - cattura delle email: wp_mail non invia nulla, i messaggi finiscono in
 *    un'opzione letta dai test;
 *  - REST POST /dbem-e2e/v1/reset → stato baseline: niente eventi né iscrizioni,
 *    PIN di sistema noto, rate limit dell'iscrizione spento; crea gli eventi
 *    richiesti dal test (vedi dbem_e2e_reset_state());
 *  - REST GET /dbem-e2e/v1/state → email catturate e iscrizioni, per le asserzioni.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (wp_get_environment_type() !== 'local') {
    return;
}

const DBEM_E2E_PIN = '123456';
const DBEM_E2E_MAILS = 'dbem_e2e_mails';

/**
 * Email catturate invece di essere inviate
 */
add_filter('pre_wp_mail', function ($null, $atts) {
    $mails = get_option(DBEM_E2E_MAILS, array());
    $mails[] = array(
        'to'      => is_array($atts['to']) ? implode(', ', $atts['to']) : (string) $atts['to'],
        'subject' => (string) $atts['subject'],
        'message' => (string) $atts['message'],
    );
    update_option(DBEM_E2E_MAILS, $mails, false);
    return true;
}, 10, 2);

/**
 * Rate limit dell'iscrizione spento salvo richiesta: gli spec inviano molti form
 * dallo stesso IP. Un test che lo verifica imposta rate_limit nel reset.
 */
add_filter('dbem_registration_rate_limit', function ($limit) {
    $override = get_option('dbem_e2e_rate_limit', '');
    return $override === '' ? 0 : (int) $override;
});

/**
 * Meta di un evento baseline: pubblicato, iscrizioni aperte, tra 7 giorni alle 18:00,
 * form integrato, email di conferma con segnaposto
 *
 * @return array
 */
function dbem_e2e_event_meta($title) {
    $day = wp_date('Y-m-d', time() + 7 * DAY_IN_SECONDS);
    return array(
        '_dbem_event_name'         => $title,
        '_dbem_date_start'         => $day . 'T18:00',
        '_dbem_date_end'           => $day . 'T20:00',
        '_dbem_location'           => 'Aula Magna',
        '_dbem_max_participants'   => 0,
        '_dbem_registration_open'  => '1',
        '_dbem_form_source'        => 'builtin',
        '_dbem_approval_mode'      => 'auto',
        '_dbem_confirmation_email' => array(
            'subject' => 'Iscrizione confermata: {evento}',
            'message' => "Ciao {nome},\nsei iscritto a {evento}.",
        ),
    );
}

/**
 * Riporta il plugin allo stato baseline e crea gli eventi richiesti.
 *
 * @param array $opts {
 *     @type array $events     Eventi da creare: [{key, title, status, meta}] (meta sovrascrive la baseline).
 *     @type int   $rate_limit Limite iscrizioni/minuto per IP (assente = spento).
 * }
 * @return array{events: array, pin: string}
 */
function dbem_e2e_reset_state($opts = array()) {
    global $wpdb;

    foreach (get_posts(array('post_type' => 'dbem_event', 'post_status' => 'any,trash', 'posts_per_page' => -1, 'fields' => 'ids')) as $id) {
        wp_delete_post($id, true);
    }
    DBEM_DB::ensure_tables();
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}dbem_registrations");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}dbem_survey_responses");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_dbem\\_%' OR option_name LIKE '\\_transient\\_timeout\\_dbem\\_%'");

    update_option(DBEM_Security::PIN_OPTION, DBEM_E2E_PIN, false);
    update_option(DBEM_E2E_MAILS, array(), false);
    if (isset($opts['rate_limit'])) {
        update_option('dbem_e2e_rate_limit', (string) (int) $opts['rate_limit'], false);
    } else {
        delete_option('dbem_e2e_rate_limit');
    }

    $events = array();
    foreach ((array) ($opts['events'] ?? array()) as $i => $event) {
        $key = (string) ($event['key'] ?? 'event' . $i);
        $title = (string) ($event['title'] ?? 'Evento E2E ' . ($i + 1));
        $id = wp_insert_post(array(
            'post_type'    => 'dbem_event',
            'post_status'  => (string) ($event['status'] ?? 'publish'),
            'post_title'   => $title,
            'post_content' => 'Descrizione di ' . $title,
        ));
        foreach (array_merge(dbem_e2e_event_meta($title), (array) ($event['meta'] ?? array())) as $meta_key => $value) {
            update_post_meta($id, $meta_key, wp_slash($value));
        }
        $events[$key] = array('id' => $id, 'url' => get_permalink($id));
    }

    return array('events' => $events, 'pin' => DBEM_E2E_PIN);
}

add_action('rest_api_init', function () {
    register_rest_route('dbem-e2e/v1', '/reset', array(
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) {
            return dbem_e2e_reset_state((array) $request->get_json_params());
        },
    ));

    register_rest_route('dbem-e2e/v1', '/state', array(
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function () {
            global $wpdb;
            return array(
                'mails'         => get_option(DBEM_E2E_MAILS, array()),
                'registrations' => $wpdb->get_results("SELECT id, event_id, name, email, status, data, gdpr_consent_given FROM {$wpdb->prefix}dbem_registrations ORDER BY id", ARRAY_A),
            );
        },
    ));
});
