<?php
/**
 * Template single evento
 * Iniettato automaticamente dal plugin via template_include
 */
if (!defined('ABSPATH')) exit;
get_header();

while (have_posts()) : the_post();
    $event_id = get_the_ID();
    $event_name = DBEM_CPT::get_event_name($event_id);
    $event_desc = DBEM_CPT::get_event_description($event_id);

    wp_enqueue_style('dbem-frontend');
    wp_enqueue_script('dbem-frontend');
    wp_enqueue_script('dbem-header-fix');
?>

<main class="dbem-single-wrap">

    <article class="dbem-single-event">
        <h1 class="dbem-event-title" style="margin-bottom:16px;"><?php echo esc_html($event_name); ?></h1>

        <?php if (has_post_thumbnail()): ?>
            <div class="dbem-event-thumbnail" style="margin-bottom:20px;">
                <?php the_post_thumbnail('large', array('style' => 'width:100%;height:auto;border-radius:10px;')); ?>
            </div>
        <?php endif; ?>

        <?php if ($event_desc): ?>
            <div class="dbem-event-description" style="margin-bottom:24px;line-height:1.7;">
                <?php echo wp_kses_post(apply_filters('the_content', $event_desc)); ?>
            </div>
        <?php endif; ?>

        <?php echo DBEM_Frontend::render_event_details($event_id); ?>
    </article>

</main>

<?php
endwhile;
get_footer();
