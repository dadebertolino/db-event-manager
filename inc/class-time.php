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
     * True se la data locale è già passata
     */
    public static function is_past($local) {
        $timestamp = self::timestamp($local);
        return $timestamp > 0 && $timestamp < time();
    }
}
