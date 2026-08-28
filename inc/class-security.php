<?php
if (!defined('ABSPATH')) exit;

/**
 * Sicurezza endpoint pubblici (check-in e partecipanti da telefono)
 *
 * Le pagine pubbliche non richiedono login WordPress: la barriera è un PIN
 * condiviso con lo staff. Il PIN è quindi OBBLIGATORIO — se non configurato
 * viene generato automaticamente e mostrato in Impostazioni.
 */
class DBEM_Security {

    const PIN_OPTION      = 'dbem_checkin_pin';
    const NONCE_ACTION    = 'dbem_public';
    const MAX_PIN_FAILS   = 10;
    const PIN_LOCK_WINDOW = 900; // 15 minuti

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
     * IP del client (solo REMOTE_ADDR: gli header proxy sono falsificabili)
     */
    public static function client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Verifica completa di una richiesta AJAX pubblica: nonce + PIN + rate limit.
     * Termina la richiesta con un errore JSON se il controllo fallisce.
     */
    public static function verify_public_request() {
        if (!check_ajax_referer(self::NONCE_ACTION, '_ajax_nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Sessione scaduta. Ricarica la pagina.', 'db-event-manager'),
                'status'  => 'nonce_error',
            ), 403);
        }

        $fail_key = 'dbem_pinfail_' . md5(self::client_ip());
        $fails    = (int) get_transient($fail_key);

        if ($fails >= self::MAX_PIN_FAILS) {
            wp_send_json_error(array(
                'message' => __('Troppi tentativi errati. Riprova tra 15 minuti.', 'db-event-manager'),
                'status'  => 'pin_error',
            ), 429);
        }

        $pin_sent = (string) sanitize_text_field($_POST['pin'] ?? '');

        if (!hash_equals(self::get_pin(), $pin_sent)) {
            set_transient($fail_key, $fails + 1, self::PIN_LOCK_WINDOW);
            wp_send_json_error(array(
                'message' => __('PIN non valido', 'db-event-manager'),
                'status'  => 'pin_error',
            ), 403);
        }

        delete_transient($fail_key);
        return true;
    }

    /**
     * Nonce da incorporare nelle pagine pubbliche
     */
    public static function public_nonce() {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    /**
     * Endpoint dedicato alla sola validazione del PIN (schermata di accesso)
     */
    public static function handle_pin_check() {
        self::verify_public_request();
        wp_send_json_success(array('message' => __('Accesso consentito', 'db-event-manager')));
    }
}
