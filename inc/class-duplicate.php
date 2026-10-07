<?php
if (!defined('ABSPATH')) exit;

/**
 * Duplica un evento: impostazioni, form, email, survey, categorie e immagine.
 * I partecipanti e le risposte al survey stanno nelle tabelle del plugin e non si copiano.
 */
class DBEM_Duplicate {

    const ACTION = 'dbem_duplicate_event';

    /**
     * Meta di WordPress legati alla singola revisione, non all'evento, e PIN dedicato
     */
    const SKIPPED_META = array('_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug',
        // Il PIN dedicato è dato a chi gestisce quell'evento: non deve aprire anche la copia
        '_dbem_checkin_pin');

    public static function init() {
        add_filter('post_row_actions', array(__CLASS__, 'add_row_action'), 10, 2);
        add_action('post_submitbox_misc_actions', array(__CLASS__, 'render_submitbox_link'));
        add_action('admin_action_' . self::ACTION, array(__CLASS__, 'handle'));
        add_action('admin_notices', array(__CLASS__, 'render_notice'));
    }

    public static function url($event_id) {
        return wp_nonce_url(
            admin_url('admin.php?action=' . self::ACTION . '&post=' . absint($event_id)),
            self::ACTION . '_' . absint($event_id)
        );
    }

    public static function can_duplicate($event_id) {
        $type = get_post_type_object('dbem_event');
        return $type && current_user_can($type->cap->create_posts) && current_user_can('edit_post', $event_id);
    }

    public static function add_row_action($actions, $post) {
        if ($post->post_type !== 'dbem_event' || $post->post_status === 'trash' || !self::can_duplicate($post->ID)) {
            return $actions;
        }
        $actions['dbem_duplicate'] = sprintf(
            '<a href="%s" aria-label="%s">%s</a>',
            esc_url(self::url($post->ID)),
            /* translators: %s: titolo dell'evento */
            esc_attr(sprintf(__('Duplica «%s»', 'db-event-manager'), get_the_title($post))),
            esc_html__('Duplica', 'db-event-manager')
        );
        return $actions;
    }

    public static function render_submitbox_link($post) {
        if ($post->post_type !== 'dbem_event' || $post->post_status === 'auto-draft' || !self::can_duplicate($post->ID)) return;
        ?>
        <div class="misc-pub-section">
            <span class="dashicons dashicons-admin-page" aria-hidden="true" style="color:#8c8f94"></span>
            <a href="<?php echo esc_url(self::url($post->ID)); ?>"><?php esc_html_e('Duplica evento', 'db-event-manager'); ?></a>
            <p class="description"><?php esc_html_e('Copia impostazioni, form ed email, senza partecipanti.', 'db-event-manager'); ?></p>
        </div>
        <?php
    }

    public static function handle() {
        $source_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        check_admin_referer(self::ACTION . '_' . $source_id);

        $source = get_post($source_id);
        if (!$source || $source->post_type !== 'dbem_event') {
            wp_die(esc_html__('Evento non trovato.', 'db-event-manager'), '', array('response' => 404));
        }
        if (!self::can_duplicate($source_id)) {
            wp_die(esc_html__('Non hai i permessi per duplicare questo evento.', 'db-event-manager'), '', array('response' => 403));
        }

        $new_id = self::duplicate($source);
        if (is_wp_error($new_id)) {
            wp_die(esc_html($new_id->get_error_message()));
        }

        wp_safe_redirect(add_query_arg(
            array('post' => $new_id, 'action' => 'edit', 'dbem_duplicated' => 1),
            admin_url('post.php')
        ));
        exit;
    }

    /**
     * Crea la copia in bozza e restituisce il suo id
     */
    public static function duplicate($source) {
        $new_id = wp_insert_post(wp_slash(array(
            'post_type'      => 'dbem_event',
            'post_status'    => 'draft',
            'post_author'    => get_current_user_id(),
            /* translators: %s: titolo dell'evento originale */
            'post_title'     => sprintf(__('%s (copia)', 'db-event-manager'), $source->post_title),
            'post_content'   => $source->post_content,
            'post_excerpt'   => $source->post_excerpt,
            'comment_status' => $source->comment_status,
            'ping_status'    => $source->ping_status,
            'menu_order'     => $source->menu_order,
        )), true);
        if (is_wp_error($new_id)) return $new_id;

        foreach (self::meta_to_copy(get_post_meta($source->ID)) as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($new_id, $key, wp_slash(maybe_unserialize($value)));
            }
        }

        foreach (get_object_taxonomies('dbem_event') as $taxonomy) {
            $terms = wp_get_object_terms($source->ID, $taxonomy, array('fields' => 'ids'));
            if (!is_wp_error($terms) && $terms) {
                wp_set_object_terms($new_id, $terms, $taxonomy);
            }
        }

        do_action('dbem_event_duplicated', $new_id, $source->ID);

        return $new_id;
    }

    /**
     * Filtra i meta dell'evento originale (formato di get_post_meta senza chiave).
     * Le iscrizioni ripartono aperte: sull'originale le chiude spesso il cron a scadenza o posti esauriti.
     */
    public static function meta_to_copy($meta) {
        $skipped = (array) apply_filters('dbem_duplicate_skipped_meta', self::SKIPPED_META);
        $copy = array();
        foreach ((array) $meta as $key => $values) {
            if (in_array($key, $skipped, true)) continue;
            $copy[$key] = (array) $values;
        }
        $copy['_dbem_registration_open'] = array('1');
        return $copy;
    }

    public static function render_notice() {
        if (empty($_GET['dbem_duplicated'])) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo visualizzazione
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'dbem_event') return;
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php esc_html_e('Evento duplicato in bozza.', 'db-event-manager'); ?></strong>
            <?php esc_html_e('Aggiorna date, luogo e scadenza iscrizioni prima di pubblicarlo. I partecipanti non sono stati copiati.', 'db-event-manager'); ?></p>
        </div>
        <?php
    }
}
