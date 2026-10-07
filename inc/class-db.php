<?php
if (!defined('ABSPATH')) exit;

class DBEM_DB {

    public static function activate() {
        self::maybe_upgrade();
        self::create_upload_dir();
        DBEM_CPT::ensure_event_capabilities();
        // Genera il PIN delle pagine pubbliche se non esiste
        DBEM_Security::get_pin();
        DBEM_Cron::schedule();
        DBEM_Cron::reschedule_all_events();
        // Le regole del CPT (pagine evento, archivio /eventi/) entrano nel flush solo se è registrato
        DBEM_CPT::register();
        flush_rewrite_rules();
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $registrations_table = $wpdb->prefix . 'dbem_registrations';
        $survey_table = $wpdb->prefix . 'dbem_survey_responses';

        $sql_registrations = "CREATE TABLE $registrations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            data longtext NOT NULL,
            email varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            token varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'confirmed',
            checked_in_at datetime DEFAULT NULL,
            assigned_time varchar(50) DEFAULT '',
            registered_at datetime NOT NULL,
            ip_address varchar(45) DEFAULT '',
            gdpr_consent_given tinyint(1) DEFAULT NULL,
            gdpr_consent_text text,
            gdpr_consent_timestamp datetime DEFAULT NULL,
            gdpr_consent_privacy_url varchar(500) DEFAULT NULL,
            gdpr_consent_policy_version bigint(20) unsigned DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY event_id (event_id),
            KEY email (email(191)),
            KEY status (status),
            KEY gdpr_consent (gdpr_consent_given, gdpr_consent_timestamp),
            KEY gdpr_consent_policy_version (gdpr_consent_policy_version)
        ) $charset;";

        $sql_survey = "CREATE TABLE $survey_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            registration_id bigint(20) unsigned NOT NULL,
            data longtext NOT NULL,
            submitted_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY registration_id (registration_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_registrations);
        dbDelta($sql_survey);
    }

    public static function create_upload_dir() {
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/dbem/qrcodes';
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
            // Proteggi directory
            file_put_contents($qr_dir . '/.htaccess', 'Options -Indexes');
        }
    }

    /**
     * Versione dello schema: va incrementata quando cambia create_tables()
     */
    const DB_VERSION = '3';
    const DB_VERSION_OPTION = 'dbem_db_version';

    /**
     * Porta le tabelle alla versione corrente: dbDelta aggiunge tabelle, colonne e indici
     * mancanti (anche la tabella dei survey se manca da sola). Gira solo quando la versione
     * salvata è diversa: prima ogni richiesta eseguiva SHOW TABLES e SHOW COLUMNS, e una
     * colonna o un indice nuovi non arrivavano a chi aggiornava il plugin.
     */
    public static function maybe_upgrade() {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';

        // 2: l'indice sull'email diventa sui primi 191 caratteri (utf8mb4 su MySQL 5.6 /
        // MariaDB 10.1 supera il limite di 767 byte). dbDelta non modifica un indice
        // esistente: quello vecchio si toglie e lo ricrea dbDelta
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $email_index = $wpdb->get_row("SHOW INDEX FROM $table WHERE Key_name = 'email'");
            if ($email_index && $email_index->Sub_part === null) {
                $wpdb->query("ALTER TABLE $table DROP INDEX email");
            }
        }

        $previous = (int) get_option(self::DB_VERSION_OPTION, 0);
        self::create_tables();
        // 3: risposte al survey sotto l'id della domanda invece che sotto l'etichetta
        if ($previous < 3 && class_exists('DBEM_Survey')) {
            DBEM_Survey::migrate_label_keys();
        }
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, true);
    }

    public static function ensure_tables() {
        if (get_option(self::DB_VERSION_OPTION) !== self::DB_VERSION) {
            self::maybe_upgrade();
        }
    }

    /**
     * Lunghezza della colonna assigned_time: un valore più lungo farebbe fallire l'UPDATE
     */
    const ASSIGNED_TIME_MAX = 50;

    /**
     * Orario assegnato ripulito; false se più lungo della colonna
     */
    public static function clean_assigned_time($raw) {
        $time = trim(sanitize_text_field((string) $raw));
        return mb_strlen($time) > self::ASSIGNED_TIME_MAX ? false : $time;
    }

    /**
     * Lock MySQL per evento (GET_LOCK, legato alla connessione): serializza controllo dei
     * posti, controllo dell'email e salvataggio di un'iscrizione. Funziona su ogni MySQL e
     * MariaDB senza vincoli UNIQUE, che fallirebbero sulle installazioni con doppioni già
     * presenti. Si libera anche da solo a fine richiesta, quando la connessione si chiude.
     *
     * @return bool false se non si ottiene entro 10 secondi
     */
    public static function lock_event($event_id) {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', self::lock_name($event_id))) === '1';
    }

    public static function unlock_event($event_id) {
        global $wpdb;
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lock_name($event_id)));
    }

    private static function lock_name($event_id) {
        global $wpdb;
        return $wpdb->prefix . 'dbem_registration_' . (int) $event_id;
    }

    /**
     * Conta iscritti per evento
     */
    public static function count_registrations($event_id, $status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status = %s",
                $event_id, $status
            ));
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d AND status NOT IN ('cancelled', 'rejected')",
            $event_id
        ));
    }

    /**
     * Ottieni iscrizione per token
     */
    public static function get_registration_by_token($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token = %s",
            $token
        ));
    }

    /**
     * Ottieni iscrizione per id
     */
    public static function get_registration($registration_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $registration_id
        ));
    }

    /**
     * Ottieni iscrizioni per evento
     */
    public static function get_registrations($event_id, $status = null, $orderby = 'registered_at', $order = 'DESC') {
        return self::get_registrations_filtered($event_id, $status, $orderby, $order, null);
    }

    public static function get_registrations_filtered($event_id, $status = null, $orderby = 'registered_at', $order = 'DESC', $registration_ids = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $allowed_orderby = array('registered_at', 'name', 'email', 'status', 'checked_in_at', 'assigned_time');
        $orderby = in_array($orderby, $allowed_orderby) ? $orderby : 'registered_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $query = "SELECT * FROM $table WHERE event_id = %d";
        $args = array($event_id);

        if ($status) {
            $query .= ' AND status = %s';
            $args[] = $status;
        }

        if ($registration_ids !== null) {
            $registration_ids = array_values(array_filter(array_map('absint', (array) $registration_ids)));
            if (!$registration_ids) return array();

            $placeholders = implode(',', array_fill(0, count($registration_ids), '%d'));
            $query .= " AND id IN ($placeholders)";
            $args = array_merge($args, $registration_ids);
        }

        $query .= " ORDER BY $orderby $order";
        return $wpdb->get_results($wpdb->prepare($query, ...$args));
    }
    /**
     * Ottieni destinatari validi per il promemoria
     */
    public static function get_reminder_registrations($event_id, $registration_ids = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $query = "SELECT * FROM $table WHERE event_id = %d AND status IN ('confirmed', 'checked_in')";
        $args = array($event_id);

        if ($registration_ids !== null) {
            $registration_ids = array_values(array_filter(array_map('absint', (array) $registration_ids)));
            if (!$registration_ids) return array();

            $placeholders = implode(',', array_fill(0, count($registration_ids), '%d'));
            $query .= " AND id IN ($placeholders)";
            $args = array_merge($args, $registration_ids);
        }

        $query .= ' ORDER BY registered_at ASC';
        return $wpdb->get_results($wpdb->prepare($query, ...$args));
    }

    /**
     * Cerca iscrizioni per nome/email/token (per evento specifico)
     */
    public static function search_registrations($event_id, $search) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $like = '%' . $wpdb->esc_like($search) . '%';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE event_id = %d AND (name LIKE %s OR email LIKE %s OR token LIKE %s)",
            $event_id, $like, $like, $like
        ));
    }

    /**
     * Cerca iscrizioni per nome/email su TUTTI gli eventi attivi (per check-in pubblico)
     */
    public static function search_registrations_global($search, $limit = 10, $event_ids = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where_events = '';
        $args = array($like, $like);
        // Solo gli eventi indicati (pagina pubblica: quelli aperti dal PIN)
        if (is_array($event_ids)) {
            $event_ids = array_values(array_filter(array_map('absint', $event_ids)));
            if (!$event_ids) return array();
            $where_events = ' AND event_id IN (' . implode(',', array_fill(0, count($event_ids), '%d')) . ')';
            $args = array_merge($args, $event_ids);
        }
        $args[] = $limit;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE (name LIKE %s OR email LIKE %s) AND status != 'cancelled'$where_events ORDER BY registered_at DESC LIMIT %d",
            ...$args
        ));
    }

    /**
     * Controlla se esiste già risposta survey
     */
    public static function has_survey_response($registration_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_survey_responses';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE registration_id = %d",
            $registration_id
        ));
    }

    /**
     * Conta risposte survey per evento
     */
    public static function count_survey_responses($event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_survey_responses';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d",
            $event_id
        ));
    }

    /**
     * Ottieni risposte survey per evento
     */
    public static function get_survey_responses($event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_survey_responses';
        $reg_table = $wpdb->prefix . 'dbem_registrations';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, r.name, r.email FROM $table s
             LEFT JOIN $reg_table r ON s.registration_id = r.id
             WHERE s.event_id = %d ORDER BY s.submitted_at DESC",
            $event_id
        ));
    }

    /**
     * Controlla se email già registrata per evento
     */
    public static function email_exists_for_event($event_id, $email) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE event_id = %d AND email = %s AND status != 'cancelled'",
            $event_id, $email
        ));
    }

    /**
     * Ottieni iscrizione attiva per email ed evento
     */
    public static function get_registration_by_email($event_id, $email) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE event_id = %d AND email = %s AND status != 'cancelled' ORDER BY id DESC LIMIT 1",
            $event_id, strtolower(trim($email))
        ));
    }

    /**
     * Sostituisce i dati modificabili di un'iscrizione esistente
     */
    public static function replace_registration($registration_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';

        $fields = array(
            'data'          => $data['data'],
            'email'         => $data['email'],
            'name'          => $data['name'],
            'registered_at' => current_time('mysql'),
            'ip_address'    => $data['ip_address'],
        );
        $formats = array('%s', '%s', '%s', '%s', '%s');

        // Un nuovo consenso sostituisce il precedente; una modifica senza consenso (GDPR
        // disattivato nel frattempo, campo privacy DBFB non mappato) non cancella la prova
        // di quello originale (art. 7.1)
        if (!empty($data['gdpr_consent_given'])) {
            $fields += array(
                'gdpr_consent_given'          => $data['gdpr_consent_given'],
                'gdpr_consent_text'           => $data['gdpr_consent_text'],
                'gdpr_consent_timestamp'      => $data['gdpr_consent_timestamp'],
                'gdpr_consent_privacy_url'    => $data['gdpr_consent_privacy_url'],
                'gdpr_consent_policy_version' => $data['gdpr_consent_policy_version'],
            );
            $formats = array_merge($formats, array('%d', '%s', '%s', '%s', '%d'));
        }

        return $wpdb->update($table, $fields, array('id' => $registration_id), $formats, array('%d'));
    }

    /**
     * Sostituisce i testi delle opzioni rinominate nelle iscrizioni di un evento.
     * $map è testo vecchio => testo nuovo per il campo $label. Restituisce le iscrizioni
     * aggiornate per ciascun testo vecchio; con $dry_run le conta senza modificarle.
     */
    public static function rename_option_values($event_id, $label, $map, $dry_run = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $counts = array_fill_keys(array_keys($map), 0);

        foreach (self::get_registrations($event_id) as $reg) {
            $data = json_decode($reg->data, true);
            if (!is_array($data) || !array_key_exists($label, $data)) continue;

            $changed = false;
            $values = is_array($data[$label]) ? $data[$label] : array($data[$label]);
            foreach ($values as $i => $value) {
                if (is_string($value) && isset($map[$value])) {
                    $counts[$value]++;
                    $values[$i] = $map[$value];
                    $changed = true;
                }
            }
            if (!$changed || $dry_run) continue;

            $data[$label] = is_array($data[$label]) ? $values : $values[0];
            $wpdb->update($table, array('data' => wp_json_encode($data)), array('id' => $reg->id), array('%s'), array('%d'));
        }

        return $counts;
    }

    /**
     * Cancella le iscrizioni con i dati collegati: risposte ai sondaggi e file del QR code
     */
    public static function delete_registrations($registration_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $survey_table = $wpdb->prefix . 'dbem_survey_responses';

        $deleted = 0;
        foreach (array_unique(array_filter(array_map('absint', (array) $registration_ids))) as $id) {
            $reg = self::get_registration($id);
            if (!$reg) continue;

            $wpdb->delete($survey_table, array('registration_id' => $id), array('%d'));
            DBEM_QRCode::delete($reg->token);
            if ($wpdb->delete($table, array('id' => $id), array('%d'))) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Evento eliminato definitivamente: via iscrizioni, sondaggi, QR code e invii programmati
     */
    public static function delete_event_data($post_id) {
        if (get_post_type($post_id) !== 'dbem_event') return;

        wp_clear_scheduled_hook('dbem_send_reminder', array((int) $post_id));
        wp_clear_scheduled_hook('dbem_send_survey_auto', array((int) $post_id));

        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) return;

        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE event_id = %d", $post_id));
        self::delete_registrations($ids);
        // Risposte rimaste senza iscrizione (iscrizioni cancellate prima della 1.6.5)
        $wpdb->delete($wpdb->prefix . 'dbem_survey_responses', array('event_id' => (int) $post_id), array('%d'));
    }

    /**
     * Rinomina le chiavi dei dati delle iscrizioni di un evento ($map: vecchia => nuova),
     * tutte insieme: due etichette scambiate tra loro restano corrette. Una chiave nuova
     * già presente e non rinominata non viene sovrascritta. Restituisce le iscrizioni aggiornate.
     */
    public static function rename_data_keys($event_id, $map) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $updated = 0;

        foreach (self::get_registrations($event_id) as $reg) {
            $data = json_decode($reg->data, true);
            if (!is_array($data) || !array_intersect_key($map, $data)) continue;

            $renamed = array();
            foreach ($data as $key => $value) {
                $new_key = $map[$key] ?? $key;
                $target_taken = $new_key !== $key && array_key_exists($new_key, $data) && !isset($map[$new_key]);
                $renamed[$target_taken ? $key : $new_key] = $value;
            }
            if ($renamed === $data) continue;

            $wpdb->update($table, array('data' => wp_json_encode($renamed)), array('id' => $reg->id), array('%s'), array('%d'));
            $updated++;
        }
        return $updated;
    }

    /**
     * Aggiorna orario assegnato a una registrazione
     */
    public static function update_assigned_time($registration_id, $time) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        return $wpdb->update($table,
            array('assigned_time' => sanitize_text_field($time)),
            array('id' => $registration_id), array('%s'), array('%d')
        );
    }

}
