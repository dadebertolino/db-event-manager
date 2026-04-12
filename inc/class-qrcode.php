<?php
if (!defined('ABSPATH')) exit;

/**
 * Generatore QR Code — wrapper per phpqrcode
 * Usa la libreria phpqrcode (LGPL 3) per generare QR code PNG affidabili.
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
        }

        $filepath = $qr_dir . '/' . $token . '.png';

        // Se esiste già, non rigenerare
        if (file_exists($filepath)) return $filepath;

        // Carica phpqrcode
        if (!class_exists('QRcode')) {
            require_once DBEM_PLUGIN_DIR . 'inc/lib/phpqrcode.php';
        }

        // Genera PNG: ECL M (buon equilibrio), pixel size 10, margin 2
        QRcode::png($url, $filepath, QR_ECLEVEL_M, 10, 2);

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
