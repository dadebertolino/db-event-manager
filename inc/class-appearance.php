<?php
if (!defined('ABSPATH')) exit;

/**
 * Colori personalizzabili: sfondo, pulsanti e testo, globali e per evento.
 * Gli altri colori (testo dei pulsanti, hover, link, bordi) vengono calcolati
 * per restare WCAG 2.1 AA. Senza colori impostati l'output non cambia: il CSS
 * usa come ripiego i colori di sempre.
 */
class DBEM_Appearance {

    const KEYS = array('color_bg', 'color_primary', 'color_text');
    const OPTION = 'dbem_appearance';
    const META = '_dbem_appearance';

    /** Colore dei pulsanti quando non è impostato (quello di sempre, 5:1 con il bianco) */
    const DEFAULT_PRIMARY = '#2271b1';

    public static function sanitize_color($color) {
        $color = sanitize_hex_color(trim((string) $color));
        if (!$color) return '';
        if (strlen($color) === 4) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }
        return strtolower($color);
    }

    /**
     * Solo le tre chiavi previste, ciascuna vuota o esadecimale a 6 cifre
     */
    public static function sanitize_colors($raw) {
        $raw = is_array($raw) ? $raw : array();
        $colors = array();
        foreach (self::KEYS as $key) {
            $colors[$key] = self::sanitize_color($raw[$key] ?? '');
        }
        return $colors;
    }

    public static function get_global_colors() {
        return self::sanitize_colors(get_option(self::OPTION, array()));
    }

    /**
     * Colore dell'evento → colore globale → '' (ripiego del CSS). $event_id = 0: solo globali.
     */
    public static function get_colors($event_id = 0) {
        $global = self::get_global_colors();
        if (!$event_id) return $global;

        $event = self::sanitize_colors(get_post_meta($event_id, self::META, true));
        $colors = array();
        foreach (self::KEYS as $key) {
            $colors[$key] = $event[$key] !== '' ? $event[$key] : $global[$key];
        }
        return $colors;
    }

    /**
     * Variabili CSS da applicare al contenitore. Vuoto se nessun colore è impostato.
     */
    public static function get_css_vars($colors) {
        $bg = $colors['color_bg'];
        $primary = $colors['color_primary'];
        $vars = array();

        // Testo: quello scelto, oppure nero o bianco secondo lo sfondo.
        // Senza sfondo né testo resta il colore del tema.
        $text = $colors['color_text'];
        if ($text === '' && $bg !== '') {
            $text = self::best_text_color($bg);
        }

        if ($bg !== '') {
            $vars['--dbem-bg'] = $bg;
            $vars['--dbem-surface'] = $bg;
            $vars['--dbem-border'] = self::mix($bg, $text, 0.2);
        }
        if ($text !== '') {
            // Un grigio derivato rischierebbe di scendere sotto 4,5:1
            $vars['--dbem-text'] = $text;
            $vars['--dbem-text-muted'] = $text;
        }
        if ($primary !== '') {
            $on = self::best_text_color($primary);
            $vars['--dbem-primary'] = $primary;
            $vars['--dbem-button-text'] = $on;
            $vars['--dbem-primary-hover'] = $on === '#ffffff' ? self::mix($primary, '#000000', 0.2) : self::mix($primary, '#ffffff', 0.25);
        }
        if ($vars) {
            // Link nel colore dei pulsanti solo se leggibili sullo sfondo, altrimenti nel colore del testo
            $page_bg = $bg !== '' ? $bg : '#ffffff';
            $link = $primary !== '' ? $primary : self::DEFAULT_PRIMARY;
            if (self::contrast($link, $page_bg) >= 4.5) {
                $vars['--dbem-link'] = $link;
            } elseif ($text !== '') {
                $vars['--dbem-link'] = $text;
            }
        }
        return $vars;
    }

    /**
     * Attributi class e style già escapati per il contenitore di un evento o di un elenco
     */
    public static function wrapper_attributes($base_class, $event_id = 0) {
        $colors = self::get_colors($event_id);
        $vars = self::get_css_vars($colors);

        $classes = $base_class . ' dbem-colors';
        if ($vars) $classes .= ' dbem-custom-colors';
        if ($colors['color_bg'] !== '') $classes .= ' dbem-has-bg';

        return ' class="' . esc_attr($classes) . '"' . self::style_attribute($vars);
    }

    /**
     * Attributi class e style già escapati per una card dell'elenco: colori del suo evento
     */
    public static function card_attributes($base_class, $event_id) {
        $vars = self::get_css_vars(self::get_colors($event_id));
        $classes = $base_class . ($vars ? ' dbem-custom-colors' : '');
        return ' class="' . esc_attr($classes) . '"' . self::style_attribute($vars);
    }

    /**
     * Attributo style con le variabili CSS, oppure ''
     */
    public static function style_attribute($vars) {
        $css = '';
        foreach ($vars as $name => $value) {
            $css .= $name . ':' . $value . ';';
        }
        return $css === '' ? '' : ' style="' . esc_attr($css) . '"';
    }

    public static function luminance($hex) {
        $lin = array_map(function ($c) {
            $c = $c / 255;
            return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }, self::to_rgb($hex));
        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }

    public static function contrast($a, $b) {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Bianco o nero, quello con più contrasto: uno dei due supera sempre 4,5:1
     */
    public static function best_text_color($bg) {
        return self::contrast($bg, '#ffffff') >= self::contrast($bg, '#000000') ? '#ffffff' : '#000000';
    }

    public static function mix($a, $b, $amount) {
        $ra = self::to_rgb($a);
        $rb = self::to_rgb($b);
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $out .= sprintf('%02x', (int) round($ra[$i] + ($rb[$i] - $ra[$i]) * $amount));
        }
        return $out;
    }

    private static function to_rgb($hex) {
        $hex = ltrim(self::sanitize_color($hex) ?: '#000000', '#');
        return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }
}
