<?php
if (!defined('ABSPATH')) exit;

class DBEM_CPT {

    const EVENT_MANAGER_CAP = 'manage_dbem_events';

    /**
     * Da incrementare quando cambia l'elenco delle capability,
     * così vengono riassegnate all'amministratore una sola volta.
     */
    const CAPS_VERSION = '1';

    public static function get_event_capabilities() {
        return array(
            self::EVENT_MANAGER_CAP,
            'edit_dbem_event',
            'read_dbem_event',
            'delete_dbem_event',
            'edit_dbem_events',
            'edit_others_dbem_events',
            'publish_dbem_events',
            'read_private_dbem_events',
            'delete_dbem_events',
            'delete_private_dbem_events',
            'delete_published_dbem_events',
            'delete_others_dbem_events',
            'edit_private_dbem_events',
            'edit_published_dbem_events',
            'manage_dbem_event_categories',
            'edit_dbem_event_categories',
            'delete_dbem_event_categories',
            'assign_dbem_event_categories',
        );
    }

    public static function ensure_event_capabilities() {
        if (get_option('dbem_caps_version') === self::CAPS_VERSION) return;

        $administrator = get_role('administrator');
        if (!$administrator) return;

        foreach (self::get_event_capabilities() as $capability) {
            $administrator->add_cap($capability);
        }
        update_option('dbem_caps_version', self::CAPS_VERSION);
    }

    public static function register() {
        self::ensure_event_capabilities();

        $labels = array(
            'name'               => __('Eventi', 'db-event-manager'),
            'singular_name'      => __('Evento', 'db-event-manager'),
            'add_new'            => __('Aggiungi Evento', 'db-event-manager'),
            'add_new_item'       => __('Aggiungi Nuovo Evento', 'db-event-manager'),
            'edit_item'          => __('Modifica Evento', 'db-event-manager'),
            'new_item'           => __('Nuovo Evento', 'db-event-manager'),
            'view_item'          => __('Vedi Evento', 'db-event-manager'),
            'search_items'       => __('Cerca Eventi', 'db-event-manager'),
            'not_found'          => __('Nessun evento trovato', 'db-event-manager'),
            'not_found_in_trash' => __('Nessun evento nel cestino', 'db-event-manager'),
            'all_items'          => __('Tutti gli Eventi', 'db-event-manager'),
            'menu_name'          => __('Event Manager', 'db-event-manager'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-calendar-alt',
            'capability_type'    => array('dbem_event', 'dbem_events'),
            'map_meta_cap'       => true,
            'has_archive'        => 'eventi',
            'hierarchical'       => false,
            'supports'           => array('title', 'editor', 'thumbnail'),
            'rewrite'            => array('slug' => 'evento'),
        );

        register_post_type('dbem_event', $args);

        // Tassonomia: Categorie Evento
        $cat_labels = array(
            'name'              => __('Categorie Evento', 'db-event-manager'),
            'singular_name'     => __('Categoria Evento', 'db-event-manager'),
            'search_items'      => __('Cerca categorie', 'db-event-manager'),
            'all_items'         => __('Tutte le categorie', 'db-event-manager'),
            'parent_item'       => __('Categoria genitore', 'db-event-manager'),
            'parent_item_colon' => __('Categoria genitore:', 'db-event-manager'),
            'edit_item'         => __('Modifica categoria', 'db-event-manager'),
            'update_item'       => __('Aggiorna categoria', 'db-event-manager'),
            'add_new_item'      => __('Aggiungi nuova categoria', 'db-event-manager'),
            'new_item_name'     => __('Nome nuova categoria', 'db-event-manager'),
            'menu_name'         => __('Categorie', 'db-event-manager'),
            'not_found'         => __('Nessuna categoria trovata', 'db-event-manager'),
        );

        register_taxonomy('dbem_category', 'dbem_event', array(
            'labels'            => $cat_labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'capabilities'      => array(
                'manage_terms' => 'manage_dbem_event_categories',
                'edit_terms'   => 'edit_dbem_event_categories',
                'delete_terms' => 'delete_dbem_event_categories',
                'assign_terms' => 'assign_dbem_event_categories',
            ),
            'rewrite'           => array('slug' => 'eventi-categoria'),
        ));
    }

    /**
     * Ottieni nome evento (meta o fallback a post_title)
     */
    /**
     * Campi del form integrato, ciascuno con un id stabile usato dai segnaposto {campo:id}.
     * I campi salvati prima della 1.7.0 non hanno id: lo ricevono qui e vengono salvati.
     */
    public static function get_custom_fields($event_id) {
        $fields = get_post_meta($event_id, '_dbem_custom_fields', true);
        if (!is_array($fields)) return array();

        $with_ids = self::assign_field_ids($fields);
        if ($with_ids !== $fields) {
            update_post_meta($event_id, '_dbem_custom_fields', wp_slash($with_ids));
        }
        return $with_ids;
    }

    /**
     * Mantiene gli id validi e unici, genera quelli mancanti
     */
    public static function assign_field_ids($fields) {
        $used = array();
        foreach ($fields as $i => $field) {
            $id = strtolower((string) ($field['id'] ?? ''));
            if (!preg_match('/^[a-z0-9_-]{1,40}$/', $id) || isset($used[$id])) {
                do {
                    $id = 'f_' . strtolower(wp_generate_password(8, false));
                } while (isset($used[$id]));
            }
            $used[$id] = true;
            $fields[$i] = array_merge(array('id' => $id), $field, array('id' => $id));
        }
        return array_values($fields);
    }

    public static function get_event_name($event_id) {
        $name = get_post_meta($event_id, '_dbem_event_name', true);
        return $name ? $name : get_the_title($event_id);
    }

    /**
     * Ottieni descrizione evento (post_content, fallback a meta)
     */
    public static function get_event_description($event_id) {
        $post = get_post($event_id);
        if ($post && $post->post_content) return $post->post_content;
        return get_post_meta($event_id, '_dbem_event_description', true);
    }

    /**
     * Ottieni stato evento basato su date
     */
    public static function get_event_status($event_id) {
        $post_status = get_post_status($event_id);
        if ($post_status === 'draft') return 'draft';

        $end = get_post_meta($event_id, '_dbem_date_end', true);
        if ($end && strtotime($end) < time()) return 'past';

        $start = get_post_meta($event_id, '_dbem_date_start', true);
        if ($start && strtotime($start) <= time() && (!$end || strtotime($end) >= time())) return 'ongoing';

        return 'upcoming';
    }

    /**
     * Controlla se le iscrizioni sono aperte
     */
    public static function are_registrations_open($event_id) {
        $open = get_post_meta($event_id, '_dbem_registration_open', true);
        if ($open !== '1') return false;

        // Controlla deadline
        $deadline = get_post_meta($event_id, '_dbem_registration_deadline', true);
        if ($deadline && strtotime($deadline) < time()) return false;

        // Controlla posti
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);
        if ($max > 0) {
            $count = DBEM_DB::count_registrations($event_id);
            if ($count >= $max) return false;
        }

        // Controlla se evento passato
        $status = self::get_event_status($event_id);
        if ($status === 'past') return false;

        return true;
    }

    /**
     * Parti del riquadro data usato nelle card evento.
     *
     * Se l'evento si estende su più giorni il riquadro mostra un intervallo
     * (es. "21-25 SET") invece del solo giorno di inizio, che da solo faceva
     * sembrare l'evento di un giorno.
     *
     * Con _dbem_hide_card_day attivo il giorno viene omesso del tutto e il
     * riquadro riporta solo mese e anno.
     *
     * @return array|null null se l'evento non ha data di inizio
     */
    public static function get_card_date($event_id) {
        $start = get_post_meta($event_id, '_dbem_date_start', true);
        if (!$start) return null;

        $end = get_post_meta($event_id, '_dbem_date_end', true);
        $ts_start = strtotime($start);
        $ts_end   = $end ? strtotime($end) : 0;

        $time_slot = get_post_meta($event_id, '_dbem_time_slot_enabled', true) === '1';

        $card = array(
            'day'      => date('d', $ts_start),
            'month'    => date_i18n('M', $ts_start),
            'year'     => date('Y', $ts_start),
            'multiday' => false,
            'hide_day' => get_post_meta($event_id, '_dbem_hide_card_day', true) === '1',
            'range'    => $time_slot ? date('d/m/Y', $ts_start) : date('d/m/Y H:i', $ts_start),
        );

        // Evento di un solo giorno (o senza data di fine): riquadro invariato
        if (!$ts_end || date('Y-m-d', $ts_end) === date('Y-m-d', $ts_start)) {
            return $card;
        }

        $card['multiday'] = true;
        $card['range']    = date('d/m/Y', $ts_start) . ' — ' . date('d/m/Y', $ts_end);
        $card['day']      = date('d', $ts_start) . '-' . date('d', $ts_end);

        if (date('Y-m', $ts_start) !== date('Y-m', $ts_end)) {
            $card['month'] = date_i18n('M', $ts_start) . '-' . date_i18n('M', $ts_end);
        }
        if (date('Y', $ts_start) !== date('Y', $ts_end)) {
            $card['year'] = date('Y', $ts_start) . '-' . date('Y', $ts_end);
        }

        return $card;
    }

    /**
     * Posti rimanenti (0 = illimitati, -1 = esauriti)
     */
    public static function get_remaining_spots($event_id) {
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);
        if ($max === 0) return 0; // illimitati
        $count = DBEM_DB::count_registrations($event_id);
        $remaining = $max - $count;
        return $remaining > 0 ? $remaining : -1;
    }
}
