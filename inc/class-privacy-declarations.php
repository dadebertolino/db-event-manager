<?php
if (!defined('ABSPATH')) exit;

/**
 * Privacy Declarations — Capability 2
 * 
 * Dichiara i trattamenti dati al Privacy Hub e al legacy SEO Manager.
 * Analizza la configurazione reale degli eventi per dichiarare solo
 * i trattamenti effettivamente attivi.
 */
class DBEM_Privacy_Declarations {

    public static function init() {
        add_filter('dbph_processing_register',  array(__CLASS__, 'declare_processing'), 10, 1);
        add_filter('dbseo_processing_register', array(__CLASS__, 'declare_processing'), 10, 1);
        add_filter('dbph_consents_register', array(__CLASS__, 'declare_consents_source'));
    }

    /**
     * Analizza la configurazione degli eventi pubblicati
     */
    private static function analyze_events() {
        $features = array(
            'has_published_events' => false,
            'has_email'            => false,
            'has_survey'           => false,
            'has_approval_mode'    => false,
            'stores_ip'            => true,
            'retention_text'       => __('I dati vengono conservati fino alla cancellazione manuale da parte dell\'amministratore o fino a richiesta di cancellazione dell\'interessato.', 'db-event-manager'),
        );

        $events = get_posts(array(
            'post_type'      => 'dbem_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        if (empty($events)) return $features;

        $features['has_published_events'] = true;

        foreach ($events as $event_id) {
            $email_data = get_post_meta($event_id, '_dbem_confirmation_email', true);
            if (!empty($email_data['subject']) || !empty($email_data['message'])) {
                $features['has_email'] = true;
            }

            $survey_enabled = get_post_meta($event_id, '_dbem_survey_enabled', true);
            if ($survey_enabled === '1') {
                $features['has_survey'] = true;
            }

            $approval_mode = get_post_meta($event_id, '_dbem_approval_mode', true);
            if ($approval_mode === 'approval') {
                $features['has_approval_mode'] = true;
            }
        }

        return $features;
    }

    /**
     * Dichiara i trattamenti in base alla configurazione reale
     */
    public static function declare_processing($register) {
        $features = self::analyze_events();

        if (!$features['has_published_events']) {
            return $register;
        }

        // Sempre: registrazioni eventi
        $register[] = array(
            'id'             => 'dbem_registrations',
            'label'          => __('Iscrizioni eventi (DB Event Manager)', 'db-event-manager'),
            'status'         => 'active',
            'purpose'        => __('Gestire le iscrizioni agli eventi pubblicati sul sito: raccolta dati partecipanti, generazione QR code per check-in, gestione presenze.', 'db-event-manager'),
            'legal_basis'    => __('Consenso esplicito (art. 6.1.a GDPR) tramite checkbox privacy nel form di iscrizione, oppure esecuzione della richiesta di partecipazione (art. 6.1.b GDPR).', 'db-event-manager'),
            'data_collected' => sprintf(
    		__('Nome, email del partecipante. Il QR code allegato contiene un token univoco (non dati personali in chiaro). Mittente configurato: %s. Le email vengono inviate via wp_mail() — il trasporto effettivo dipende dalla configurazione SMTP del sito.', 'db-event-manager'),
    			get_option('admin_email')
		),
	    'retention'      => $features['retention_text'],
            'transfers'      => __('Nessuno. Tutti i dati sono salvati nel database WordPress locale.', 'db-event-manager'),
        );

        // Condizionale: email
        if ($features['has_email']) {
            $register[] = array(
                'id'             => 'dbem_email',
                'label'          => __('Email transazionali eventi (DB Event Manager)', 'db-event-manager'),
                'status'         => 'active',
                'purpose'        => __('Inviare email di conferma iscrizione con QR code, promemoria evento, notifiche di approvazione/rifiuto, inviti survey post-evento.', 'db-event-manager'),
                'legal_basis'    => __('Esecuzione della richiesta di partecipazione (art. 6.1.b GDPR). L\'utente ha fornito l\'email al momento dell\'iscrizione.', 'db-event-manager'),
                'data_collected' => sprintf(
                    __('Nome, email del partecipante. Il QR code allegato contiene un token univoco (non dati personali in chiaro). Mittente configurato: %s. Le email vengono inviate via wp_mail() — il trasporto effettivo dipende dalla configurazione SMTP del sito.', 'db-event-manager'),
                    get_option('admin_email')
                ),
                'retention'      => __('Le email inviate non sono conservate nel sito. I log di invio dipendono dalla configurazione del server SMTP.', 'db-event-manager'),
                'transfers'      => __('L\'email transita attraverso il server SMTP configurato nel sito WordPress. Nessun servizio email terzo è integrato nel plugin.', 'db-event-manager'),
            );
        }

        // Condizionale: survey
        if ($features['has_survey']) {
            $register[] = array(
                'id'             => 'dbem_survey',
                'label'          => __('Survey post-evento (DB Event Manager)', 'db-event-manager'),
                'status'         => 'active',
                'purpose'        => __('Raccogliere feedback dai partecipanti dopo la conclusione dell\'evento per migliorare l\'organizzazione futura.', 'db-event-manager'),
                'legal_basis'    => __('Consenso esplicito (art. 6.1.a GDPR). Il partecipante sceglie volontariamente di compilare il survey.', 'db-event-manager'),
                'data_collected' => __('Risposte ai campi survey configurati dall\'amministratore. Le risposte sono collegate all\'iscrizione originale (nome, email).', 'db-event-manager'),
                'retention'      => $features['retention_text'],
                'transfers'      => __('Nessuno. Database WordPress locale.', 'db-event-manager'),
            );
        }

        return $register;
    }


    /**
     * Esponi consensi al Privacy Hub Registro consensi
     */
    public static function declare_consents_source($sources) {
        $sources['dbem_registration_consents'] = array(
            'label'  => __('Event Manager — Consensi iscrizioni', 'db-event-manager'),
            'icon'   => 'calendar-alt',
            'count'  => array(__CLASS__, 'hub_count_consents'),
            'query'  => array(__CLASS__, 'hub_query_consents'),
            'export' => array(__CLASS__, 'hub_query_consents'),
        );
        return $sources;
    }

    public static function hub_count_consents($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        list($where, $params) = self::build_consent_where($args);
        $sql = "SELECT COUNT(*) FROM $table $where";
        return !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($sql, $params)) : (int) $wpdb->get_var($sql);
    }

    public static function hub_query_consents($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        list($where, $params) = self::build_consent_where($args);
        $limit = isset($args['_internal_limit']) ? (int) $args['_internal_limit'] : 1000;

        $sql = "SELECT id, email, name, gdpr_consent_text, gdpr_consent_timestamp,
                       gdpr_consent_privacy_url, gdpr_consent_policy_version, event_id
                FROM $table $where ORDER BY gdpr_consent_timestamp DESC LIMIT $limit";
        $rows = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);

        $out = array();
        foreach ((array) $rows as $r) {
            $out[] = array(
                'id'             => 'dbem-' . (int) $r->id,
                'timestamp'      => $r->gdpr_consent_timestamp,
                'subject'        => self::mask_email($r->email),
                'consent_type'   => sprintf(__('iscrizione evento #%d', 'db-event-manager'), (int) $r->event_id),
                'consent_text'   => (string) $r->gdpr_consent_text,
                'policy_version' => (int) $r->gdpr_consent_policy_version,
                'extra'          => array('event_id' => (int) $r->event_id, 'name' => $r->name),
            );
        }
        return $out;
    }

    private static function build_consent_where($args) {
        $where = array('gdpr_consent_given = 1');
        $params = array();
        if (!empty($args['date_from'])) { $where[] = 'gdpr_consent_timestamp >= %s'; $params[] = $args['date_from'] . ' 00:00:00'; }
        if (!empty($args['date_to']))   { $where[] = 'gdpr_consent_timestamp <= %s'; $params[] = $args['date_to'] . ' 23:59:59'; }
        if (!empty($args['subject']))   { $where[] = 'email LIKE %s'; $params[] = '%' . $args['subject'] . '%'; }
        return array('WHERE ' . implode(' AND ', $where), $params);
    }

    private static function mask_email($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***';
        $local = $parts[0];
        $masked = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 0));
        return $masked . '@' . $parts[1];
    }
}
