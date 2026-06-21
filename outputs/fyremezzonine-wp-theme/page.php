<?php
/**
 * Default page template.
 *
 * @package Fyremezzonine
 */

get_header();
?>

<main id="primary">
    <?php while (have_posts()) : the_post(); ?>
        <article class="section page-section">
            <div class="section-inner page-layout">
                <p class="section-eyebrow">Информация</p>
                <h1 class="section-title"><?php the_title(); ?></h1>
                <div class="page-content">
                    <?php the_content(); ?>
                </div>
            </div>
        </article>
    <?php endwhile; ?>
</main>

<?php
get_footer();
