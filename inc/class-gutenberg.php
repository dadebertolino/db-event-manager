<?php
if (!defined('ABSPATH')) exit;

class DBEM_Gutenberg {

    public static function register_blocks() {
        if (!function_exists('register_block_type')) return;

        // Blocco server-side rendering
        register_block_type('dbem/event', array(
            'attributes' => array(
                'eventId' => array('type' => 'number', 'default' => 0),
            ),
            'render_callback' => array(__CLASS__, 'render_event_block'),
        ));

        register_block_type('dbem/events-list', array(
            'attributes' => array(
                'showPast' => array('type' => 'boolean', 'default' => false),
                'limit'    => array('type' => 'number', 'default' => 10),
            ),
            'render_callback' => array(__CLASS__, 'render_events_list_block'),
        ));

        // Editor script
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueue_editor'));
    }

    public static function enqueue_editor() {
        wp_enqueue_script(
            'dbem-blocks',
            DBEM_PLUGIN_URL . 'assets/js/blocks.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render'),
            DBEM_VERSION,
            true
        );

        // Passa lista eventi all'editor
        $events = get_posts(array(
            'post_type'      => 'dbem_event',
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
        $options = array(array('label' => __('— Seleziona evento —', 'db-event-manager'), 'value' => 0));
        foreach ($events as $e) {
            $options[] = array('label' => $e->post_title, 'value' => $e->ID);
        }
        wp_localize_script('dbem-blocks', 'dbemBlocks', array('events' => $options));
    }

    public static function render_event_block($atts) {
        $event_id = absint($atts['eventId'] ?? 0);
        if (!$event_id) return '<p>' . esc_html__('Seleziona un evento.', 'db-event-manager') . '</p>';
        return do_shortcode('[dbem_event id="' . $event_id . '"]');
    }

    public static function render_events_list_block($atts) {
        $past = !empty($atts['showPast']) ? '1' : '0';
        $limit = absint($atts['limit'] ?? 10);
        return do_shortcode('[dbem_events past="' . $past . '" limit="' . $limit . '"]');
    }
}
