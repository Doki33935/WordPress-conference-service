<?php
/**
 * Header template.
 *
 * @package Fyremezzonine
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$header_conference_id = is_singular('conference') ? get_the_ID() : fyremezzonine_next_conference_id();
$header_registration_url = fyremezzonine_link('registration_url');
$header_registration_closed = !$header_conference_id;

if ($header_conference_id) {
    $header_registration_url = add_query_arg('conference_id', $header_conference_id, $header_registration_url);
    $header_registration_closed = fyremezzonine_registration_closed($header_conference_id);
}
?>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?php echo fyremezzonine_link('branch_url'); ?>" aria-label="Официальный сайт Оренбургского филиала ФГБУ ВНИИПО МЧС России">
            <span class="brand-mark">
                <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/oren-vniipo-logo.png'); ?>" alt="">
            </span>
            <span class="brand-text">Оренбургский филиал<br>ФГБУ ВНИИПО МЧС России</span>
        </a>

        <div class="header-navigation">
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu(
                    array(
                        'theme_location' => 'primary',
                        'container' => 'nav',
                        'container_class' => 'primary-nav',
                        'depth' => 1,
                    )
                );
            } else {
                fyremezzonine_nav_fallback();
            }
            ?>

            <?php if ($header_registration_closed) : ?>
                <span class="header-cta header-cta-disabled" aria-disabled="true">Регистрация закрыта</span>
            <?php else : ?>
                <a class="header-cta" href="<?php echo esc_url($header_registration_url); ?>">Принять участие</a>
            <?php endif; ?>

            <?php if (function_exists('fyremezzonine_manager_can_manage_conferences') && fyremezzonine_manager_can_manage_conferences()) : ?>
                <details class="editor-menu">
                    <summary>Редактор</summary>
                    <div class="editor-menu-panel">
                        <a href="<?php echo esc_url(home_url('/editor/conferences/')); ?>">Конференции</a>
                        <a href="<?php echo esc_url(home_url('/editor/registrations/')); ?>">Заявки на участие</a>
                        <a href="<?php echo esc_url(home_url('/editor/partnership/')); ?>">Партнерство</a>
                        <?php if (current_user_can('manage_options')) : ?>
                            <a href="<?php echo esc_url(admin_url()); ?>">Админка</a>
                        <?php endif; ?>
                        <a class="editor-menu-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Выйти из профиля</a>
                    </div>
                </details>
            <?php elseif (function_exists('fyremezzonine_manager_is_section_manager') && fyremezzonine_manager_is_section_manager()) : ?>
                <details class="editor-menu">
                    <summary>Секции</summary>
                    <div class="editor-menu-panel">
                        <a href="<?php echo esc_url(fyremezzonine_manager_section_statistics_url()); ?>">Статистика секций</a>
                        <a class="editor-menu-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Выйти из профиля</a>
                    </div>
                </details>
            <?php else : ?>
                <a class="login-link" href="<?php echo esc_url(home_url('/editor/login/')); ?>">Войти</a>
            <?php endif; ?>
        </div>
    </div>
</header>
