<?php
/**
 * Conference archive template.
 *
 * @package Fyremezzonine
 */

get_header();

$today = current_time('Y-m-d');
$conferences = get_posts(
    array(
        'post_type' => 'conference',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_key' => '_conference_start_date',
        'orderby' => 'meta_value',
        'order' => 'ASC',
    )
);

$conference_groups = array(
    'current' => array(
        'eyebrow' => 'Идут сейчас',
        'title' => 'Проходящие сейчас',
        'description' => 'Конференции, которые уже начались и еще не завершились.',
        'items' => array(),
    ),
    'future' => array(
        'eyebrow' => 'Скоро',
        'title' => 'Будущие конференции',
        'description' => 'Ближайшие опубликованные события, на которые можно ориентироваться при планировании участия.',
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
    $start_date = (string) get_post_meta($conference_post->ID, '_conference_start_date', true);
    $end_date = (string) get_post_meta($conference_post->ID, '_conference_end_date', true);
    $compare_end_date = $end_date ?: $start_date;

    if ($start_date && $start_date <= $today && $compare_end_date >= $today) {
        $conference_groups['current']['items'][] = $conference_post;
    } elseif ($compare_end_date && $compare_end_date < $today) {
        $conference_groups['completed']['items'][] = $conference_post;
    } else {
        $conference_groups['future']['items'][] = $conference_post;
    }
}

$conference_groups['completed']['items'] = array_reverse($conference_groups['completed']['items']);
?>

<main id="primary">
    <section class="section">
        <div class="section-inner">
            <p class="section-eyebrow">Календарь</p>
            <h1 class="section-title">Конференции</h1>
            <p class="lead">Все опубликованные конференции разделены по статусу: что проходит сейчас, что запланировано дальше и что уже завершилось.</p>

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
                                    $city = get_post_meta($conference_id, '_conference_city', true);
                                    $venue = get_post_meta($conference_id, '_conference_venue', true);
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
