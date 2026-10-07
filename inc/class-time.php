<?php
if (!defined('ABSPATH')) exit;

/**
 * Date degli eventi e delle iscrizioni.
 *
 * Il plugin salva in ora locale del sito: le date dell'evento come le scrive il campo
 * datetime-local ('2026-10-10T18:00'), gli orari di iscrizione e check-in con
 * current_time('mysql'). WordPress imposta il fuso di PHP a UTC, quindi strtotime()
 * le leggerebbe come UTC: confrontate con time() scattano in ritardo (2 ore a Roma in
 * estate) e wp_date() aggiunge l'offset una seconda volta agli orari mostrati.
 * Ogni conversione passa da qui.
 */
class DBEM_Time {

    /**
     * Timestamp Unix di una data salvata in ora locale; 0 se vuota o non valida
     */
    public static function timestamp($local) {
        $local = trim((string) $local);
        if ($local === '') return 0;

        try {
            $date = new DateTimeImmutable($local, wp_timezone());
        } catch (Exception $e) {
            return 0;
        }
        return $date->getTimestamp();
    }

    /**
     * Data salvata in ora locale formattata per la visualizzazione (nomi localizzati,
     * nessuno spostamento di fuso); '' se vuota o non valida
     */
    public static function format($format, $local) {
        $timestamp = self::timestamp($local);
        return $timestamp ? wp_date($format, $timestamp) : '';
    }

    /**
     * Formato delle date mostrate a visitatori e nelle email (#40), dalle Impostazioni del
     * plugin: vuoto = 07/10/2026 come prima della 1.11.0, 'wp' = formato di WordPress
     * (Impostazioni generali), altrimenti un formato PHP personalizzato
     */
    const DATE_FORMAT_OPTION = 'dbem_date_format';
    const DEFAULT_DATE_FORMAT = 'd/m/Y';

    public static function date_format() {
        $option = (string) get_option(self::DATE_FORMAT_OPTION, '');
        if ($option === 'wp') return (string) get_option('date_format') ?: self::DEFAULT_DATE_FORMAT;
        return $option !== '' ? $option : self::DEFAULT_DATE_FORMAT;
    }

    public static function time_format() {
        return get_option(self::DATE_FORMAT_OPTION, '') === 'wp' ? ((string) get_option('time_format') ?: 'H:i') : 'H:i';
    }

    /** Data salvata in ora locale, nel formato scelto */
    public static function format_date($local) {
        return self::format(self::date_format(), $local);
    }

    /** Data e ora salvate in ora locale, nel formato scelto */
    public static function format_datetime($local) {
        return self::format(self::date_format() . ' ' . self::time_format(), $local);
    }

    /**
     * True se la data locale è già passata
     */
    public static function is_past($local) {
        $timestamp = self::timestamp($local);
        return $timestamp > 0 && $timestamp < time();
    }
}
