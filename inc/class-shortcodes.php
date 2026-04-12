<?php
if (!defined('ABSPATH')) exit;

class DBEM_Shortcodes {

    public static function register() {
        add_shortcode('dbem_event', array(__CLASS__, 'render_event'));
        add_shortcode('dbem_events', array(__CLASS__, 'render_events_list'));
    }

    /**
     * [dbem_event id="X"]
     */
    public static function render_event($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts, 'dbem_event');
        $event_id = absint($atts['id']);

        if (!$event_id || get_post_type($event_id) !== 'dbem_event') {
            return '<p class="dbem-error-msg">' . esc_html__('Evento non trovato.', 'db-event-manager') . '</p>';
        }

        wp_enqueue_style('dbem-frontend');
        wp_enqueue_script('dbem-frontend');

        $post = get_post($event_id);
        if (!$post || $post->post_status !== 'publish') {
            return '<p class="dbem-error-msg">' . esc_html__('Evento non disponibile.', 'db-event-manager') . '</p>';
        }

        $event_name = get_post_meta($event_id, '_dbem_event_name', true);
        $event_desc = $post->post_content;
        // Fallback: se qualcuno ha usato il meta descrizione (versione precedente)
        if (!$event_desc) $event_desc = get_post_meta($event_id, '_dbem_event_description', true);
        if (!$event_name) $event_name = $post->post_title;

        ob_start();
        ?>
        <div class="dbem-event-wrapper">
            <h2 class="dbem-event-title"><?php echo esc_html($event_name); ?></h2>

            <?php if ($event_desc): ?>
                <div class="dbem-event-description">
                    <?php echo wp_kses_post(apply_filters('the_content', $event_desc)); ?>
                </div>
            <?php endif; ?>

            <?php echo DBEM_Frontend::render_event_details($event_id); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [dbem_events past="0" limit="10" cols="1" category=""]
     */
    public static function render_events_list($atts) {
        $atts = shortcode_atts(array(
            'past'     => '0',
            'limit'    => 10,
            'cols'     => '1',
            'category' => '',
        ), $atts, 'dbem_events');

        $show_past = $atts['past'] === '1';
        $limit = absint($atts['limit']) ?: 10;
        $cols = absint($atts['cols']);
        if ($cols < 1 || $cols > 4) $cols = 1;
        $category = sanitize_text_field($atts['category']);

        wp_enqueue_style('dbem-frontend');

        $args = array(
            'post_type'      => 'dbem_event',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_key'       => '_dbem_date_start',
            'orderby'        => 'meta_value',
            'order'          => $show_past ? 'DESC' : 'ASC',
        );

        // Filtro per categoria
        if ($category) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'dbem_category',
                    'field'    => 'slug',
                    'terms'    => array_map('trim', explode(',', $category)),
                ),
            );
        }

        if ($show_past) {
            $args['meta_query'] = array(
                array(
                    'key'     => '_dbem_date_end',
                    'value'   => current_time('mysql'),
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ),
            );
        } else {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_dbem_date_end',
                    'value'   => current_time('mysql'),
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ),
                array(
                    'key'     => '_dbem_date_end',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return '<p class="dbem-no-events">' . esc_html__('Nessun evento disponibile.', 'db-event-manager') . '</p>';
        }

        ob_start();
        ?>
        <div class="dbem-events-list dbem-cols-<?php echo esc_attr($cols); ?>" role="list">
            <?php while ($query->have_posts()): $query->the_post();
                $eid = get_the_ID();
                $start = get_post_meta($eid, '_dbem_date_start', true);
                $location = get_post_meta($eid, '_dbem_location', true);
                $max = (int) get_post_meta($eid, '_dbem_max_participants', true);
                $count = DBEM_DB::count_registrations($eid);
                $remaining = DBEM_CPT::get_remaining_spots($eid);
                $reg_open = DBEM_CPT::are_registrations_open($eid);
                $event_status = DBEM_CPT::get_event_status($eid);
            ?>
            <article class="dbem-event-card dbem-status-<?php echo esc_attr($event_status); ?>" role="listitem">
                <div class="dbem-card-date">
                    <?php if ($start): ?>
                        <span class="dbem-card-day"><?php echo esc_html(wp_date('d', strtotime($start))); ?></span>
                        <span class="dbem-card-month"><?php echo esc_html(wp_date('M', strtotime($start))); ?></span>
                        <span class="dbem-card-year"><?php echo esc_html(wp_date('Y', strtotime($start))); ?></span>
                    <?php endif; ?>
                </div>
                <div class="dbem-card-content">
                    <h3 class="dbem-card-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>
                    <?php if ($start): ?>
                        <p class="dbem-card-meta">
                            <span>📅 <?php echo esc_html(wp_date('d/m/Y H:i', strtotime($start))); ?></span>
                            <?php if ($location): ?><span>📍 <?php echo esc_html($location); ?></span><?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php
                    $categories = get_the_terms($eid, 'dbem_category');
                    if ($categories && !is_wp_error($categories)): ?>
                        <p class="dbem-card-categories">
                            <?php foreach ($categories as $cat): ?>
                                <span class="dbem-badge dbem-badge-category"><?php echo esc_html($cat->name); ?></span>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                    <div class="dbem-card-status">
                        <?php if ($event_status === 'past'): ?>
                            <span class="dbem-badge dbem-badge-past"><?php esc_html_e('Concluso', 'db-event-manager'); ?></span>
                        <?php elseif ($remaining === -1): ?>
                            <span class="dbem-badge dbem-badge-full"><?php esc_html_e('Posti esauriti', 'db-event-manager'); ?></span>
                        <?php elseif ($reg_open): ?>
                            <span class="dbem-badge dbem-badge-open"><?php esc_html_e('Iscrizioni aperte', 'db-event-manager'); ?></span>
                            <?php if ($max > 0): ?>
                                <span class="dbem-spots"><?php echo esc_html($count); ?>/<?php echo esc_html($max); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="dbem-badge dbem-badge-closed"><?php esc_html_e('Iscrizioni chiuse', 'db-event-manager'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
