<?php
/**
 * One-page conference landing.
 *
 * @package Fyremezzonine
 */

$conference = fyremezzonine_next_conference_data();

get_header();

$hero_image_style = $conference['hero_image_url'] ? "--hero-image: url('" . esc_url($conference['hero_image_url']) . "');" : '--hero-image: none;';
?>

<main id="primary" class="conference-theme conference-theme-<?php echo esc_attr($conference['visual_theme']); ?>">
    <section class="hero" id="top" style="<?php echo esc_attr($hero_image_style); ?>">
        <div class="theme-atmosphere" aria-hidden="true"></div>
        <div class="section-inner">
            <div class="hero-content">
                <div class="hero-kicker" aria-label="Дата и место">
                    <span><?php echo esc_html($conference['date_range']); ?></span>
                    <span>г. <?php echo esc_html($conference['city']); ?></span>
                </div>
                <p class="hero-label">Научно-практическая конференция</p>
                <h1><?php echo esc_html($conference['title']); ?></h1>
                <div class="hero-actions">
                    <?php if ($conference['registration_closed']) : ?>
                        <span class="button button-disabled" aria-disabled="true">Регистрация закрыта</span>
                    <?php else : ?>
                        <a class="button" href="<?php echo fyremezzonine_link('registration_url'); ?>">Принять участие</a>
                    <?php endif; ?>
                    <a class="button button-red" href="<?php echo esc_url($conference['program_url']); ?>">Программа конференции</a>
                    <?php if (!empty($conference['chat_url'])) : ?>
                        <a class="button button-blue" href="<?php echo esc_url($conference['chat_url']); ?>">Чат конференции</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="section about-band" id="about">
        <div class="section-inner about-layout">
            <div>
                <p class="section-eyebrow">О конференции</p>
                <h2 class="section-title"><?php echo esc_html($conference['about_title']); ?></h2>
                <p class="lead"><?php echo esc_html($conference['about_lead']); ?></p>

                <ul class="benefits">
                    <?php foreach ($conference['benefits'] as $benefit) : ?>
                    <li><?php echo esc_html($benefit); ?></li>
                    <?php endforeach; ?>
                </ul>

                <p>
                    <?php if ($conference['registration_closed']) : ?>
                        <span class="button button-red button-pill button-disabled" aria-disabled="true">Регистрация закрыта</span>
                    <?php else : ?>
                        <a class="button button-red button-pill" href="<?php echo fyremezzonine_link('registration_url'); ?>">Принять участие</a>
                    <?php endif; ?>
                </p>
            </div>

            <aside class="meta-stack" aria-label="Информация о конференции">
                <div class="info-card">
                    <strong>Дата проведения</strong>
                    <p><?php echo esc_html($conference['date_range']); ?></p>
                </div>
                <div class="info-card">
                    <strong>Место проведения</strong>
                    <p><?php echo esc_html($conference['venue']); ?></p>
                </div>
                <div class="info-card">
                    <strong>Дедлайн регистрации</strong>
                    <p><?php echo esc_html($conference['deadline'] ?: 'Уточняется'); ?></p>
                </div>
            </aside>
        </div>
    </section>

    <section class="section" id="participation">
        <div class="section-inner">
            <p class="section-eyebrow">Структура конференции</p>
            <h2 class="section-title">Ключевые темы обсуждения</h2>
            <p class="lead"><?php echo esc_html($conference['topic_intro']); ?></p>

            <div class="topic-grid">
                <?php foreach ($conference['topics'] as $index => $topic) : ?>
                <article class="topic-card">
                    <?php if (!empty($topic['image_url'])) : ?>
                        <img class="topic-media" src="<?php echo esc_url($topic['image_url']); ?>" alt="">
                    <?php endif; ?>
                    <span class="topic-number"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                    <p><?php echo esc_html($topic['title']); ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($conference['speakers'])) : ?>
    <section class="section speakers">
        <div class="section-inner">
            <p class="section-eyebrow">Эксперты</p>
            <h2 class="section-title">Спикеры конференции</h2>
            <div class="speaker-grid">
                <?php foreach ($conference['speakers'] as $speaker) : ?>
                    <article class="speaker-card">
                        <?php if (!empty($speaker['photo_url'])) : ?>
                            <img class="speaker-photo" src="<?php echo esc_url($speaker['photo_url']); ?>" alt="<?php echo esc_attr($speaker['name']); ?>">
                        <?php endif; ?>
                        <div class="speaker-body">
                            <h3><?php echo esc_html($speaker['name']); ?></h3>
                            <?php if (!empty($speaker['position'])) : ?>
                                <p class="speaker-position"><?php echo esc_html($speaker['position']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($speaker['direction'])) : ?>
                                <p class="speaker-direction"><?php echo esc_html($speaker['direction']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($speaker['quote'])) : ?>
                                <blockquote><?php echo esc_html($speaker['quote']); ?></blockquote>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="section materials" id="registration">
        <div class="section-inner">
            <h2 class="section-title">Требования к оформлению материалов</h2>
            <p class="lead"><?php echo esc_html($conference['materials_intro']); ?></p>

            <div class="materials-box">
                <div>
                    <h3>Конференция «<?php echo esc_html($conference['title']); ?>»</h3>
                    <p><?php echo esc_html($conference['date_range']); ?>, <?php echo esc_html($conference['city']); ?></p>
                </div>
                <a class="button button-red" href="<?php echo esc_url($conference['materials_url']); ?>">Скачать требования к оформлению (.doc)</a>
            </div>
        </div>
    </section>

    <?php if (fyremezzonine_partner_groups_have_items($conference['partner_groups'])) : ?>
        <section class="section" id="partners">
            <div class="section-inner">
                <p class="section-eyebrow">Организаторы и партнеры</p>
                <h2 class="section-title">Участники конференции</h2>

                <?php fyremezzonine_render_partner_groups($conference['partner_groups']); ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="section cta-band">
        <div class="section-inner cta-layout">
            <h2>Приглашаем стать официальным партнером, соорганизатором конференции или представителем СМИ</h2>
            <a class="button button-blue" href="<?php echo esc_url($conference['partner_form_url']); ?>">Оставить заявку</a>
        </div>
    </section>

    <section class="section venue">
        <div class="section-inner venue-stack">
            <div class="venue-copy">
                <p class="section-eyebrow">Место проведения</p>
                <h2 class="section-title"><?php echo esc_html($conference['venue_heading']); ?></h2>
                <?php foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $conference['venue_intro']))) as $paragraph) : ?>
                <p><?php echo esc_html($paragraph); ?></p>
                <?php endforeach; ?>
            </div>

            <div class="route-layout">
                <div class="map-card" aria-label="Карта проезда к адресу Оренбургский район, Нижнепавловский сельсовет, Полигонная улица 1">
                    <iframe src="<?php echo esc_url($conference['map_url']); ?>" loading="lazy" allowfullscreen></iframe>
                </div>
                <div class="route-card">
                    <h3>Как добраться?</h3>
                    <p>Адрес для Яндекс Карт: <?php echo esc_html($conference['route_address']); ?></p>
                    <?php foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $conference['route_directions']))) as $paragraph) : ?>
                    <p><?php echo esc_html($paragraph); ?></p>
                    <?php endforeach; ?>
                    <?php if ($conference['registration_closed']) : ?>
                        <span class="button button-outline button-disabled" aria-disabled="true">Регистрация закрыта</span>
                    <?php else : ?>
                        <a class="button button-outline" href="<?php echo fyremezzonine_link('registration_url'); ?>">Зарегистрироваться</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($conference['venue_image_url']) || !empty($conference['collage_image_url'])) : ?>
                <div class="venue-gallery">
                    <?php if (!empty($conference['venue_image_url'])) : ?>
                        <figure class="venue-visual" aria-label="Место проведения" style="background-image: url('<?php echo esc_url($conference['venue_image_url']); ?>');"></figure>
                    <?php endif; ?>
                    <?php if (!empty($conference['collage_image_url'])) : ?>
                        <figure class="conference-collage">
                            <img src="<?php echo esc_url($conference['collage_image_url']); ?>" alt="Дополнительное изображение конференции">
                        </figure>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section contact" id="contacts">
        <div class="section-inner contact-grid">
            <div>
                <p class="section-eyebrow">Свяжитесь с нами</p>
                <h2 class="section-title">Контакты</h2>
                <p class="lead">Оренбургский филиал ФГБУ ВНИИПО МЧС России</p>
            </div>

            <dl class="contact-list">
                <div>
                    <dt>Официальные телефоны филиала</dt>
                    <dd><a href="tel:+73532572295">+7 (3532) 57-22-95</a><br><a href="tel:+73532572484">+7 (3532) 57-24-84</a></dd>
                </div>
                <div>
                    <dt>Адрес в Оренбурге</dt>
                    <dd>г. Оренбург, ул. Советская, 97</dd>
                </div>
                <div>
                    <dt>Дополнительный адрес института</dt>
                    <dd>г. Оренбург, Селивановский переулок, 30/32<br><a href="tel:+73532572715">+7 (3532) 57-27-15</a></dd>
                </div>
                <div>
                    <dt>Почта конференции</dt>
                    <dd><a href="mailto:vniipo.conf@mail.ru">vniipo.conf@mail.ru</a></dd>
                </div>
                <div>
                    <dt>Ответственное контактное лицо</dt>
                    <dd>Мухамеджанов Владислав Нариманович<br><a href="mailto:muhamedganov_vn@vniipo.ru">muhamedganov_vn@vniipo.ru</a><br><a href="tel:+79011133696">+7 (901) 113-36-96</a></dd>
                </div>
                <div>
                    <dt>Сайт филиала</dt>
                    <dd><a href="<?php echo fyremezzonine_link('branch_url'); ?>">oren.vniipo.ru</a></dd>
                </div>
            </dl>
        </div>
    </section>
</main>

<?php
get_footer();
