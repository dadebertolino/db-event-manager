<?php
if (!defined('ABSPATH')) exit;

/**
 * Generatore QR Code — wrapper per phpqrcode con namespace isolato
 *
 * Genera il QR in un file temporaneo (evita problemi di output buffering
 * con WordPress), poi lo sposta nella cartella finale.
 */
class DBEM_QRCode {

    /**
     * Genera e salva QR code PNG per un token
     */
    public static function generate($token) {
        $url = home_url('/?dbem_checkin=' . $token);

        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/dbem/qrcodes';
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
            @file_put_contents($qr_dir . '/.htaccess', 'Options -Indexes');
        }

        $filepath = $qr_dir . '/' . $token . '.png';

        // Se esiste già con header PNG valido, non rigenerare
        if (file_exists($filepath) && filesize($filepath) > 100) {
            $header = @file_get_contents($filepath, false, null, 0, 4);
            if ($header === "\x89PNG") {
                return $filepath;
            }
            @unlink($filepath);
        }

        // Carica libreria phpqrcode isolata
        if (!class_exists('DBEM_QRcode_Lib', false)) {
            $lib = DBEM_PLUGIN_DIR . 'inc/lib/phpqrcode.php';
            if (!file_exists($lib)) {
                error_log('DB Event Manager: phpqrcode.php non trovato in ' . $lib);
                return false;
            }
            require $lib;
        }

        if (!class_exists('DBEM_QRcode_Lib', false) || !method_exists('DBEM_QRcode_Lib', 'png')) {
            error_log('DB Event Manager: classe DBEM_QRcode_Lib non disponibile');
            return false;
        }

        // Genera in un file temporaneo (evita problemi di output buffering)
        $tmpfile = tempnam(sys_get_temp_dir(), 'dbem_qr_');
        if ($tmpfile === false) {
            // Fallback: usa la stessa cartella qrcodes
            $tmpfile = $qr_dir . '/tmp_' . $token . '.png';
        }

        // phpqrcode scrive direttamente su file quando gli passi un filename
        @DBEM_QRcode_Lib::png($url, $tmpfile, QR_ECLEVEL_M, 10, 2);

        // Verifica che il file temp sia stato creato ed è un PNG valido
        if (!file_exists($tmpfile) || filesize($tmpfile) < 100) {
            error_log('DB Event Manager: QR temp non creato in ' . $tmpfile);
            @unlink($tmpfile);
            return false;
        }

        $header = @file_get_contents($tmpfile, false, null, 0, 4);
        if ($header !== "\x89PNG") {
            error_log('DB Event Manager: QR temp non è PNG (header: ' . bin2hex(substr($header, 0, 8)) . ')');
            @unlink($tmpfile);
            return false;
        }

        // Sposta nella posizione finale
        if ($tmpfile !== $filepath) {
            $moved = @rename($tmpfile, $filepath);
            if (!$moved) {
                // rename può fallire cross-filesystem, usa copy+delete
                $copied = @copy($tmpfile, $filepath);
                @unlink($tmpfile);
                if (!$copied) {
                    error_log('DB Event Manager: impossibile spostare QR da temp a ' . $filepath);
                    return false;
                }
            }
        }

        return file_exists($filepath) ? $filepath : false;
    }

    /**
     * Ottieni URL del QR code
     */
    public static function get_url($token) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/dbem/qrcodes/' . $token . '.png';
    }

    /**
     * Ottieni path del QR code
     */
    public static function get_path($token) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/dbem/qrcodes/' . $token . '.png';
    }
}
