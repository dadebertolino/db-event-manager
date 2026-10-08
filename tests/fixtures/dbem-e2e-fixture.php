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
 *     @type array $events     Eventi da creare: [{key, title, status, meta, registrations}] (meta sovrascrive
 *                             la baseline; registrations: [{name, email, status, data}] iscrizioni già salvate,
 *                             data = campi compilati, etichetta => valore).
 *     @type bool  $manager    Crea (o ripristina) l'utente "gestore" con i soli permessi sugli eventi.
 *     Un evento con dbfb: true usa un form DB Form Builder creato qui (campi nome, email, telefono).
 *     @type int   $rate_limit Limite iscrizioni/minuto per IP (assente = spento).
 * }
 * @return array{events: array, pin: string}
 */
function dbem_e2e_reset_state($opts = array()) {
    global $wpdb;

    foreach (get_posts(array('post_type' => array('dbem_event', 'dbfb_form'), 'post_status' => 'any,trash', 'posts_per_page' => -1, 'fields' => 'ids')) as $id) {
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
        $meta = array_merge(dbem_e2e_event_meta($title), (array) ($event['meta'] ?? array()));
        if (!empty($event['dbfb']) && post_type_exists('dbfb_form')) {
            $meta = array_merge($meta, array(
                '_dbem_form_source'       => 'dbfb',
                '_dbem_dbfb_form_id'      => dbem_e2e_dbfb_form($title),
                '_dbem_dbfb_name_field'   => 'nome',
                '_dbem_dbfb_email_field'  => 'email',
            ));
        }
        foreach ($meta as $meta_key => $value) {
            update_post_meta($id, $meta_key, wp_slash($value));
        }
        $registrations = array();
        foreach ((array) ($event['registrations'] ?? array()) as $j => $reg) {
            $token = bin2hex(random_bytes(32));
            $wpdb->insert($wpdb->prefix . 'dbem_registrations', array(
                'event_id'      => $id,
                'data'          => wp_json_encode(array('nome' => $reg['name'] ?? 'Iscritto ' . $j, 'email' => $reg['email'] ?? 'iscritto' . $j . '@example.com') + (array) ($reg['data'] ?? array())),
                'email'         => strtolower($reg['email'] ?? 'iscritto' . $j . '@example.com'),
                'name'          => $reg['name'] ?? 'Iscritto ' . $j,
                'token'         => $token,
                'status'        => $reg['status'] ?? 'confirmed',
                'registered_at' => current_time('mysql'),
                'checked_in_at' => ($reg['status'] ?? '') === 'checked_in' ? current_time('mysql') : null,
            ));
            $registrations[] = array('id' => (int) $wpdb->insert_id, 'token' => $token);
        }
        $events[$key] = array('id' => $id, 'url' => get_permalink($id), 'registrations' => $registrations);
    }

    // Gestore delegato: solo i permessi sugli eventi (Utenti → Gestione eventi), non manage_options
    if (!empty($opts['manager'])) {
        $user = get_user_by('login', 'gestore');
        $user_id = $user ? $user->ID : wp_insert_user(array('user_login' => 'gestore', 'user_pass' => 'password', 'user_email' => 'gestore@example.com', 'role' => 'subscriber'));
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        foreach (DBEM_CPT::get_event_capabilities() as $capability) {
            $user->add_cap($capability);
        }
        $user->add_cap('upload_files');
    }

    return array('events' => $events, 'pin' => DBEM_E2E_PIN);
}

/**
 * Form DB Form Builder per un evento: nome ed email obbligatori, telefono facoltativo
 */
function dbem_e2e_dbfb_form($title) {
    $form_id = wp_insert_post(array('post_type' => 'dbfb_form', 'post_status' => 'publish', 'post_title' => 'Form ' . $title));
    update_post_meta($form_id, '_dbfb_fields', array(
        array('id' => 'nome', 'type' => 'text', 'label' => 'Nome', 'placeholder' => '', 'required' => true, 'options' => array()),
        array('id' => 'email', 'type' => 'email', 'label' => 'Email', 'placeholder' => '', 'required' => true, 'options' => array()),
        array('id' => 'telefono', 'type' => 'text', 'label' => 'Telefono', 'placeholder' => '', 'required' => false, 'options' => array()),
    ));
    update_post_meta($form_id, '_dbfb_settings', array('success_message' => 'Modulo inviato'));
    return $form_id;
}

add_action('rest_api_init', function () {
    register_rest_route('dbem-e2e/v1', '/reset', array(
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) {
            return dbem_e2e_reset_state((array) $request->get_json_params());
        },
    ));

    // Cambia un meta di un evento dopo il reset (es. rinominare una domanda del survey)
    register_rest_route('dbem-e2e/v1', '/meta', array(
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) {
            $params = (array) $request->get_json_params();
            update_post_meta((int) $params['event_id'], (string) $params['key'], wp_slash($params['value']));
            return array('ok' => true);
        },
    ));

    // Exporter ed eraser privacy registrati (con o senza Privacy Hub) e export di un indirizzo
    register_rest_route('dbem-e2e/v1', '/privacy', array(
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) {
            $exporters = apply_filters('wp_privacy_personal_data_exporters', array());
            $erasers = apply_filters('wp_privacy_personal_data_erasers', array());
            $mine = array_filter(array_keys($exporters), function ($key) { return strpos($key, 'event-manager') !== false || strpos($key, 'dbem') !== false; });
            $export = array();
            foreach ($mine as $key) {
                $export[$key] = call_user_func($exporters[$key]['callback'], (string) $request->get_param('email'), 1);
            }
            return array(
                'exporters' => array_values($mine),
                'erasers'   => array_values(array_filter(array_keys($erasers), function ($key) { return strpos($key, 'event-manager') !== false || strpos($key, 'dbem') !== false; })),
                'export'    => $export,
            );
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
                'survey'        => $wpdb->get_results("SELECT id, event_id, registration_id, data FROM {$wpdb->prefix}dbem_survey_responses ORDER BY id", ARRAY_A),
                'events'        => array_map(function ($post) {
                    return array('id' => $post->ID, 'status' => $post->post_status, 'title' => $post->post_title);
                }, get_posts(array('post_type' => 'dbem_event', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC'))),
            );
        },
    ));
});
