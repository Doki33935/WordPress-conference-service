<?php
/**
 * Single conference template.
 *
 * @package Fyremezzonine
 */

get_header();
?>

<main id="primary">
    <?php while (have_posts()) : the_post(); ?>
        <article class="section about-band">
            <div class="section-inner about-layout">
                <div>
                    <p class="section-eyebrow">Конференция</p>
                    <h1 class="section-title"><?php the_title(); ?></h1>
                    <div class="lead"><?php the_excerpt(); ?></div>
                    <?php the_content(); ?>
                </div>

                <aside class="meta-stack" aria-label="Данные конференции">
                    <?php
                    $fields = array(
                        '_conference_start_date' => 'Дата начала',
                        '_conference_end_date' => 'Дата окончания',
                        '_conference_city' => 'Город',
                        '_conference_venue' => 'Место проведения',
                        '_conference_registration_deadline' => 'Дедлайн регистрации',
                    );
                    foreach ($fields as $key => $label) :
                        $value = get_post_meta(get_the_ID(), $key, true);
                        if (!$value) {
                            continue;
                        }
                        if (in_array($key, array('_conference_start_date', '_conference_end_date', '_conference_registration_deadline'), true)) {
                            $value = fyremezzonine_format_conference_date($value);
                        }
                        ?>
                        <div class="info-card">
                            <strong><?php echo esc_html($label); ?></strong>
                            <p><?php echo esc_html($value); ?></p>
                        </div>
                    <?php endforeach; ?>
                </aside>
            </div>
        </article>
    <?php endwhile; ?>
</main>

<?php
get_footer();
