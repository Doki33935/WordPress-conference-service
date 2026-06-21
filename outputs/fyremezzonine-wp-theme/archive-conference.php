<?php
/**
 * Conference archive template.
 *
 * @package Fyremezzonine
 */

get_header();
?>

<main id="primary">
    <section class="section">
        <div class="section-inner">
            <p class="section-eyebrow">Архив</p>
            <h1 class="section-title">Конференции</h1>

            <div class="topic-grid">
                <?php if (have_posts()) : ?>
                    <?php while (have_posts()) : the_post(); ?>
                        <article class="topic-card">
                            <span class="topic-number"><?php echo esc_html(fyremezzonine_format_conference_date(get_post_meta(get_the_ID(), '_conference_start_date', true))); ?></span>
                            <p><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></p>
                            <p><?php echo esc_html(get_post_meta(get_the_ID(), '_conference_city', true)); ?></p>
                        </article>
                    <?php endwhile; ?>
                <?php else : ?>
                    <p>Конференций пока нет.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
