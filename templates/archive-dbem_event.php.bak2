<?php
/**
 * Template archivio eventi
 * Iniettato automaticamente dal plugin via template_include
 * URL: /eventi/
 */
if (!defined('ABSPATH')) exit;
get_header();

wp_enqueue_style('dbem-frontend');
wp_enqueue_script('dbem-header-fix');
?>

<main class="dbem-archive-wrap">

    <h1 style="margin-bottom:24px;"><?php echo esc_html(get_option('dbem_events_page_title', __('Eventi', 'db-event-manager'))); ?></h1>

    <?php if (have_posts()): ?>

        <div class="dbem-events-list" role="list">
        <?php while (have_posts()) : the_post();
            $eid = get_the_ID();
            $event_name = DBEM_CPT::get_event_name($eid);
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
                    <h2 class="dbem-card-title" style="font-size:18px;margin:0 0 4px;">
                        <a href="<?php the_permalink(); ?>"><?php echo esc_html($event_name); ?></a>
                    </h2>
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
        <?php endwhile; ?>
        </div>

        <?php
        // Paginazione
        the_posts_pagination(array(
            'mid_size'  => 2,
            'prev_text' => '← ' . __('Precedenti', 'db-event-manager'),
            'next_text' => __('Successivi', 'db-event-manager') . ' →',
        ));
        ?>

    <?php else: ?>
        <p class="dbem-no-events"><?php esc_html_e('Nessun evento in programma.', 'db-event-manager'); ?></p>
    <?php endif; ?>

</main>

<?php
get_footer();
