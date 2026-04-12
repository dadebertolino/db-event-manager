<?php
if (!defined('ABSPATH')) exit;

class DBEM_CPT {

    public static function register() {
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
            'capability_type'    => 'post',
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
            'rewrite'           => array('slug' => 'eventi-categoria'),
        ));
    }

    /**
     * Ottieni nome evento (meta o fallback a post_title)
     */
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
