<?php
/**
 * Conference archive template.
 *
 * @package Fyremezzonine
 */

get_header();

$conferences = get_posts(
    array(
        'post_type' => 'conference',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'date',
        'order' => 'ASC',
    )
);

$conference_groups = array(
    'current' => array(
        'eyebrow' => 'Идут сейчас',
        'title' => 'Проходящие сейчас',
        'description' => 'Текущая конференция показывается на главной странице, и только на нее открыта регистрация.',
        'items' => array(),
    ),
    'future' => array(
        'eyebrow' => 'Скоро',
        'title' => 'Будущие конференции',
        'description' => 'Опубликованные события в очереди. Регистрация откроется, когда конференция станет текущей.',
        'items' => array(),
    ),
    'completed' => array(
        'eyebrow' => 'Архив',
        'title' => 'Завершенные конференции',
        'description' => 'Прошедшие мероприятия остаются доступными для просмотра материалов и общей информации.',
        'items' => array(),
    ),
);

foreach ($conferences as $conference_post) {
    $lifecycle_status = function_exists('fyremezzonine_manager_lifecycle_status')
        ? fyremezzonine_manager_lifecycle_status($conference_post->ID)
        : 'future';
    $group_key = $lifecycle_status === 'current' ? 'current' : ($lifecycle_status === 'completed' ? 'completed' : 'future');
    $conference_groups[$group_key]['items'][] = $conference_post;
}

$conference_groups['completed']['items'] = array_reverse($conference_groups['completed']['items']);
?>

<main id="primary">
    <section class="section">
        <div class="section-inner">
            <p class="section-eyebrow">Календарь</p>
            <h1 class="section-title">Конференции</h1>
            <p class="lead">Все опубликованные конференции разделены по статусу: что проходит сейчас, что запланировано дальше и что уже завершилось.</p>
            <?php if (isset($_GET['lifecycle_notice']) && sanitize_key(wp_unslash($_GET['lifecycle_notice'])) === 'completed') : ?>
                <div class="registration-message registration-success">Конференция завершена. Следующая опубликованная конференция назначена текущей автоматически.</div>
            <?php endif; ?>

            <div class="conference-archive-groups">
                <?php foreach ($conference_groups as $group_key => $group) : ?>
                    <section class="conference-archive-group conference-archive-group-<?php echo esc_attr($group_key); ?>" aria-labelledby="conference-group-<?php echo esc_attr($group_key); ?>">
                        <div class="conference-archive-group-head">
                            <span><?php echo esc_html($group['eyebrow']); ?></span>
                            <h2 id="conference-group-<?php echo esc_attr($group_key); ?>"><?php echo esc_html($group['title']); ?></h2>
                            <p><?php echo esc_html($group['description']); ?></p>
                        </div>

                        <?php if ($group['items']) : ?>
                            <div class="conference-archive-grid">
                                <?php foreach ($group['items'] as $conference_post) : ?>
                                    <?php
                                    $conference_id = $conference_post->ID;
                                    $venue_rows = function_exists('fyremezzonine_conference_venues') ? fyremezzonine_conference_venues($conference_id) : array();
                                    $city = $venue_rows ? $venue_rows[0]['city'] : get_post_meta($conference_id, '_conference_city', true);
                                    $venue = $venue_rows ? $venue_rows[0]['name'] : get_post_meta($conference_id, '_conference_venue', true);
                                    $deadline = get_post_meta($conference_id, '_conference_registration_deadline', true);
                                    ?>
                                    <article class="conference-archive-card">
                                        <div class="conference-archive-card-top">
                                            <span class="conference-archive-date"><?php echo esc_html(fyremezzonine_conference_date_range($conference_id) ?: 'Дата уточняется'); ?></span>
                                            <?php if ($city) : ?>
                                                <span class="conference-archive-city"><?php echo esc_html($city); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h3><a href="<?php echo esc_url(get_permalink($conference_id)); ?>"><?php echo esc_html(get_the_title($conference_id)); ?></a></h3>
                                        <?php if (has_excerpt($conference_id)) : ?>
                                            <p><?php echo esc_html(get_the_excerpt($conference_id)); ?></p>
                                        <?php endif; ?>
                                        <dl class="conference-archive-meta">
                                            <?php if ($venue) : ?>
                                                <div>
                                                    <dt>Место</dt>
                                                    <dd><?php echo esc_html($venue); ?></dd>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($deadline && $group_key !== 'completed') : ?>
                                                <div>
                                                    <dt>Регистрация до</dt>
                                                    <dd><?php echo esc_html(fyremezzonine_format_conference_date($deadline)); ?></dd>
                                                </div>
                                            <?php endif; ?>
                                        </dl>
                                        <a class="button button-blue" href="<?php echo esc_url(get_permalink($conference_id)); ?>">Открыть конференцию</a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="conference-archive-empty">В этом разделе пока нет конференций.</p>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
