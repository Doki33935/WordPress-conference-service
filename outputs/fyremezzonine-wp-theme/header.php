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
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?php echo fyremezzonine_link('branch_url'); ?>" aria-label="Официальный сайт Оренбургского филиала ФГБУ ВНИИПО МЧС России">
            <span class="brand-mark">
                <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/oren-vniipo-logo.png'); ?>" alt="">
            </span>
            <span class="brand-text">Оренбургский филиал ФГБУ ВНИИПО МЧС России</span>
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

            <?php if (current_user_can('edit_posts')) : ?>
                <details class="editor-menu">
                    <summary>Редактор</summary>
                    <div class="editor-menu-panel">
                        <a href="<?php echo esc_url(home_url('/editor/new-conference/')); ?>">Создать конференцию</a>
                        <a href="<?php echo esc_url(home_url('/editor/edit-conference/')); ?>">Изменить конференцию</a>
                        <a href="<?php echo esc_url(home_url('/editor/registrations/')); ?>">Заявки и экспорт</a>
                        <a href="<?php echo esc_url(admin_url()); ?>">Админка</a>
                    </div>
                </details>
            <?php else : ?>
                <a class="login-link" href="<?php echo esc_url(admin_url()); ?>">Войти</a>
            <?php endif; ?>
        </div>
    </div>
</header>
