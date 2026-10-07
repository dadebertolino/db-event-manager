<?php
if (!defined('ABSPATH')) exit;

/**
 * Sicurezza endpoint pubblici (check-in e partecipanti da telefono)
 *
 * Le pagine pubbliche non richiedono login WordPress: la barriera è un PIN
 * condiviso con lo staff. Il PIN è quindi OBBLIGATORIO — se non configurato
 * viene generato automaticamente e mostrato in Impostazioni.
 *
 * Endpoint pubblici e cache di pagina (1.8.0): per i visitatori anonimi non
 * si verifica un nonce stampato nell'HTML (in una pagina in cache scade dopo
 * 12–24h e ogni richiesta fallirebbe con 403), ma l'origine della richiesta
 * (Origin/Referer dello stesso sito) più un rate limit per IP. Gli utenti
 * loggati mantengono il nonce. Vedi verify_request().
 */
class DBEM_Security {

    const PIN_OPTION      = 'dbem_checkin_pin';
    const EVENT_PIN_META  = '_dbem_checkin_pin';
    const NONCE_ACTION    = 'dbem_public';
    const MAX_PIN_FAILS   = 10;
    const PIN_LOCK_WINDOW = 900; // 15 minuti
    const RATE_WINDOW     = 60;  // finestra del rate limit (secondi)
    const PUBLIC_RATE     = 120; // richieste/minuto per IP sulle pagine staff (PIN)

    /**
     * PIN corrente. Se mancante ne genera uno (mai vuoto).
     */
    public static function get_pin() {
        $pin = (string) get_option(self::PIN_OPTION, '');
        if ($pin === '') {
            $pin = self::generate_pin();
            update_option(self::PIN_OPTION, $pin, false);
        }
        return $pin;
    }

    /**
     * PIN numerico a 6 cifre da fonte crittograficamente sicura
     */
    public static function generate_pin() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Formato accettato al salvataggio: da 4 a 10 cifre (la tastiera del telefono
     * è numerica e il campo accetta al massimo 10 caratteri)
     */
    public static function is_valid_pin($pin) {
        return is_string($pin) && preg_match('/^\d{4,10}$/', $pin) === 1;
    }

    /**
     * PIN dell'evento: il suo se impostato, altrimenti quello di sistema
     */
    public static function get_event_pin($event_id) {
        $pin = (string) get_post_meta($event_id, self::EVENT_PIN_META, true);
        return $pin !== '' ? $pin : self::get_pin();
    }

    /**
     * Eventi pubblicati che il PIN apre, dal più recente. Un evento con un PIN proprio
     * si apre solo con quello; gli altri con il PIN di sistema.
     *
     * @return int[]
     */
    public static function events_for_pin($pin) {
        $pin = (string) $pin;
        if ($pin === '') return array();

        $ids = get_posts(array(
            'post_type'      => 'dbem_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => '_dbem_date_start',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ));

        $allowed = array();
        foreach ($ids as $id) {
            if (hash_equals(self::get_event_pin($id), $pin)) {
                $allowed[] = (int) $id;
            }
        }
        return $allowed;
    }

    /**
     * Termina con 403 se l'evento non è tra quelli aperti dal PIN della richiesta
     *
     * @param int   $event_id
     * @param int[] $allowed  Restituito da verify_public_request()
     */
    public static function require_event_access($event_id, $allowed) {
        if (!$event_id || !in_array((int) $event_id, (array) $allowed, true)) {
            wp_send_json_error(array(
                'message' => __('Questo PIN non dà accesso a questo evento.', 'db-event-manager'),
                'status'  => 'forbidden',
            ), 403);
        }
    }

    /**
     * Parametro di $_POST (o $_GET) come stringa ripulita. Un array (dbem_email[]=x)
     * diventa $default: sanitize_email() e, su WordPress 6.0, sanitize_key() vanno in
     * TypeError con un array e la richiesta finirebbe con un errore 500.
     * Nonce e origine li verificano i chiamanti.
     */
    public static function input($key, $default = '', $method = 'post') {
        // phpcs:disable WordPress.Security.NonceVerification -- verificati dai chiamanti
        $source = $method === 'get' ? $_GET : $_POST;
        $value = isset($source[$key]) ? wp_unslash($source[$key]) : $default; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ripulito sotto
        // phpcs:enable WordPress.Security.NonceVerification
        return is_scalar($value) ? sanitize_text_field((string) $value) : $default;
    }

    /**
     * IP del client (solo REMOTE_ADDR: gli header proxy sono falsificabili)
     */
    public static function client_ip() {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Verifica completa di una richiesta AJAX pubblica delle pagine staff
     * (check-in, partecipanti): nonce (loggati) o origine + rate limit
     * (anonimi), poi PIN con blocco dopo MAX_PIN_FAILS tentativi errati.
     * Il PIN resta l'autorizzazione vera: è valido se è quello di sistema o quello
     * di un evento, e apre solo gli eventi pubblicati che gli corrispondono.
     * Termina con un errore JSON se un controllo fallisce.
     *
     * @return int[] Eventi a cui il PIN dà accesso (vedi require_event_access())
     */
    public static function verify_public_request() {
        self::verify_request(
            self::NONCE_ACTION,
            '_ajax_nonce',
            'public',
            self::PUBLIC_RATE,
            __('Richiesta non valida: apri la pagina dal sito.', 'db-event-manager')
        );

        // Lettura del contatore, confronto e incremento sotto lock: una raffica di richieste
        // parallele leggerebbe tutta lo stesso contatore e supererebbe i 10 tentativi
        $fail_key = 'dbem_pinfail_' . md5(self::client_ip());
        $lock     = self::lock_pin_check($fail_key);
        $fails    = (int) get_transient($fail_key);

        if ($fails >= self::MAX_PIN_FAILS) {
            self::unlock_pin_check($lock);
            wp_send_json_error(array(
                'message' => __('Troppi tentativi errati. Riprova tra 15 minuti.', 'db-event-manager'),
                'status'  => 'pin_error',
            ), 429);
        }

        $pin_sent = (string) sanitize_text_field(wp_unslash($_POST['pin'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce (loggati) o origine + rate limit (anonimi) verificati in verify_request()

        $allowed = self::events_for_pin($pin_sent);
        if (!$allowed && !hash_equals(self::get_pin(), $pin_sent)) {
            set_transient($fail_key, $fails + 1, self::PIN_LOCK_WINDOW);
            self::unlock_pin_check($lock);
            wp_send_json_error(array(
                'message' => __('PIN non valido', 'db-event-manager'),
                'status'  => 'pin_error',
            ), 403);
        }

        delete_transient($fail_key);
        self::unlock_pin_check($lock);
        return $allowed;
    }

    /**
     * Lock MySQL sul contatore dei tentativi di un IP; null se non disponibile (si
     * procede comunque: il lock rende esatto il conteggio, non è l'autorizzazione)
     */
    private static function lock_pin_check($fail_key) {
        global $wpdb;
        if (!isset($wpdb) || !method_exists($wpdb, 'get_var')) return null;
        $name = substr($wpdb->prefix . $fail_key, 0, 64);
        return (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $name)) === '1' ? $name : null;
    }

    private static function unlock_pin_check($name) {
        global $wpdb;
        if ($name !== null) {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }

    /**
     * Verifica cache-safe di una richiesta a un endpoint pubblico; termina
     * con un errore JSON se non valida.
     *
     * - Utenti loggati: nonce classico (le loro pagine non finiscono in cache
     *   e il nonce è legato alla sessione → protezione CSRF reale).
     * - Visitatori anonimi: niente nonce (in una pagina in cache scadrebbe
     *   e per un anonimo è comunque identico per tutti). Si richiede
     *   Origin/Referer dello stesso sito e si applica il rate limit per IP.
     *
     * @param string $nonce_action   Azione del nonce (utenti loggati).
     * @param string $nonce_field    Campo POST con il nonce.
     * @param string $context        Contesto del rate limit (chiave e filtro 'dbem_{context}_rate_limit').
     * @param int    $default_limit  Richieste/minuto per IP per gli anonimi; 0 = nessun rate limit qui
     *                               (il chiamante lo applica da sé con check_rate_limit()).
     * @param string $origin_message Messaggio mostrato se l'origine non è valida.
     * @return true
     */
    public static function verify_request($nonce_action, $nonce_field, $context, $default_limit, $origin_message = '') {
        if (is_user_logged_in()) {
            if (!check_ajax_referer($nonce_action, $nonce_field, false)) {
                wp_send_json_error(array(
                    'message' => __('Sessione scaduta. Ricarica la pagina e riprova.', 'db-event-manager'),
                    'code'    => 'bad_nonce',
                ), 403);
            }
            return true;
        }

        if (!self::is_same_site_request()) {
            wp_send_json_error(array(
                'message' => $origin_message !== '' ? $origin_message : __('Richiesta non valida.', 'db-event-manager'),
                'code'    => 'bad_origin',
            ), 403);
        }

        if ($default_limit > 0) {
            self::check_rate_limit($context, $default_limit);
        }
        return true;
    }

    /**
     * True se Origin (o, in mancanza, Referer) della richiesta corrente
     * appartiene allo stesso sito (home_url / site_url).
     */
    public static function is_same_site_request() {
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- confrontati solo come host via wp_parse_url()
        $origin = '';
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $origin = wp_unslash($_SERVER['HTTP_ORIGIN']);
        } elseif (!empty($_SERVER['HTTP_REFERER'])) {
            $origin = wp_unslash($_SERVER['HTTP_REFERER']);
        }
        // phpcs:enable

        return self::origin_matches($origin, array(home_url(), site_url()));
    }

    /**
     * True se l'host di $origin coincide con l'host di uno degli URL del sito.
     * Confronto sul solo host (case-insensitive): schema e porta possono
     * differire dietro proxy. Origin vuoto o 'null' → false.
     *
     * @param string $origin    Valore dell'header Origin o Referer.
     * @param array  $site_urls URL del sito (home_url, site_url).
     * @return bool
     */
    public static function origin_matches($origin, $site_urls) {
        $origin = is_string($origin) ? trim($origin) : '';
        if ($origin === '' || $origin === 'null') {
            return false;
        }
        $host = wp_parse_url($origin, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);
        foreach ((array) $site_urls as $url) {
            $site_host = wp_parse_url((string) $url, PHP_URL_HOST);
            if (is_string($site_host) && strtolower($site_host) === $host) {
                return true;
            }
        }
        return false;
    }

    /**
     * Rate limit per IP: al massimo N richieste per RATE_WINDOW secondi
     * (filtro 'dbem_{context}_rate_limit', 0 o negativo = disattivato).
     * La chiave è un hash salato di contesto + IP: l'indirizzo non finisce
     * in chiaro nella tabella delle opzioni. Termina con 429 se superato.
     *
     * @param string $context       Contesto (es. 'registration', 'survey', 'public').
     * @param int    $default_limit Limite predefinito.
     * @return true
     */
    public static function check_rate_limit($context, $default_limit) {
        $context = sanitize_key($context);
        $limit   = (int) apply_filters('dbem_' . $context . '_rate_limit', (int) $default_limit);
        if ($limit <= 0) {
            return true;
        }
        $rate_key   = self::rate_limit_key($context, self::client_ip());
        $rate_count = (int) get_transient($rate_key);
        if ($rate_count >= $limit) {
            wp_send_json_error(array(
                'message' => __('Troppe richieste. Riprova tra qualche minuto.', 'db-event-manager'),
                'code'    => 'rate_limited',
            ), 429);
        }
        set_transient($rate_key, $rate_count + 1, self::RATE_WINDOW);
        return true;
    }

    /**
     * Chiave transient del rate limit (hash salato, mai l'IP in chiaro)
     */
    public static function rate_limit_key($context, $ip) {
        return 'dbem_rate_' . substr(hash('sha256', $context . '|' . $ip . wp_salt('nonce')), 0, 32);
    }

    /**
     * Pagina per-visitatore o legata a un token: niente cache di pagina
     * (header no-cache + DONOTCACHEPAGE per WP Rocket, LiteSpeed, W3TC...).
     */
    public static function no_cache_page() {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
    }

    /**
     * Nonce da incorporare nelle pagine pubbliche: solo per gli utenti
     * loggati (per gli anonimi vale il controllo di origine, vedi verify_request)
     */
    public static function public_nonce() {
        return is_user_logged_in() ? wp_create_nonce(self::NONCE_ACTION) : '';
    }

    /**
     * Endpoint dedicato alla sola validazione del PIN (schermata di accesso)
     */
    public static function handle_pin_check() {
        $allowed = self::verify_public_request();

        $events = array();
        foreach ($allowed as $event_id) {
            $start = get_post_meta($event_id, '_dbem_date_start', true);
            $events[] = array(
                'id'    => $event_id,
                'name'  => DBEM_CPT::get_event_name($event_id),
                'date'  => DBEM_Time::format_date($start),
            );
        }

        wp_send_json_success(array(
            'message' => __('Accesso consentito', 'db-event-manager'),
            'events'  => $events,
        ));
    }
}
