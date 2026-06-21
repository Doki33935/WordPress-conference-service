<?php
/**
 * Plugin Name: Fyremezzonine Conference Manager
 * Description: Adds conferences, conference metadata, public registration forms, and admin registration lists.
 * Version: 1.0.0
 * Author: Codex
 * Text Domain: fyremezzonine-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FYREMEZZONINE_MANAGER_VERSION', '1.0.0');

function fyremezzonine_manager_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'conference_registrations';
}

function fyremezzonine_manager_activate() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $table = fyremezzonine_manager_table_name();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        conference_id bigint(20) unsigned NOT NULL,
        full_name varchar(190) NOT NULL,
        email varchar(190) NOT NULL,
        phone varchar(60) NOT NULL DEFAULT '',
        organization varchar(190) NOT NULL DEFAULT '',
        comment text NULL,
        status varchar(40) NOT NULL DEFAULT 'new',
        ip_address varchar(80) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY conference_id (conference_id),
        KEY email (email),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta($sql);
}
register_activation_hook(__FILE__, 'fyremezzonine_manager_activate');

function fyremezzonine_manager_register_post_type() {
    register_post_type(
        'conference',
        array(
            'labels' => array(
                'name' => 'Конференции',
                'singular_name' => 'Конференция',
                'add_new_item' => 'Добавить конференцию',
                'edit_item' => 'Редактировать конференцию',
                'new_item' => 'Новая конференция',
                'view_item' => 'Посмотреть конференцию',
                'search_items' => 'Искать конференции',
                'not_found' => 'Конференции не найдены',
            ),
            'public' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'has_archive' => true,
            'rewrite' => array('slug' => 'conferences'),
            'show_in_rest' => true,
        )
    );
}
add_action('init', 'fyremezzonine_manager_register_post_type');

function fyremezzonine_manager_meta_keys() {
    return array(
        '_conference_start_date' => array('label' => 'Дата начала', 'type' => 'date'),
        '_conference_end_date' => array('label' => 'Дата окончания', 'type' => 'date'),
        '_conference_city' => array('label' => 'Город', 'type' => 'text'),
        '_conference_venue' => array('label' => 'Место проведения', 'type' => 'text'),
        '_conference_registration_deadline' => array('label' => 'Дедлайн регистрации', 'type' => 'date'),
        '_conference_program_url' => array('label' => 'Ссылка на программу', 'type' => 'url'),
        '_conference_materials_url' => array('label' => 'Ссылка на материалы', 'type' => 'url'),
        '_conference_hero_image_url' => array('label' => 'Фон первого экрана: URL изображения', 'type' => 'url'),
        '_conference_topic_intro' => array('label' => 'Описание блока тем', 'type' => 'textarea'),
        '_conference_topic_1_title' => array('label' => 'Тема 1: текст', 'type' => 'text'),
        '_conference_topic_1_image_url' => array('label' => 'Тема 1: URL изображения', 'type' => 'url'),
        '_conference_topic_2_title' => array('label' => 'Тема 2: текст', 'type' => 'text'),
        '_conference_topic_2_image_url' => array('label' => 'Тема 2: URL изображения', 'type' => 'url'),
        '_conference_topic_3_title' => array('label' => 'Тема 3: текст', 'type' => 'text'),
        '_conference_topic_3_image_url' => array('label' => 'Тема 3: URL изображения', 'type' => 'url'),
        '_conference_about_title' => array('label' => 'Заголовок блока "О конференции"', 'type' => 'text'),
        '_conference_about_lead' => array('label' => 'Лид блока "О конференции"', 'type' => 'textarea'),
        '_conference_benefits' => array('label' => 'Преимущества/тезисы: по одному пункту на строку', 'type' => 'textarea'),
        '_conference_materials_intro' => array('label' => 'Описание блока материалов', 'type' => 'textarea'),
        '_conference_venue_heading' => array('label' => 'Заголовок блока места проведения', 'type' => 'text'),
        '_conference_venue_intro' => array('label' => 'Описание места проведения', 'type' => 'textarea'),
        '_conference_route_address' => array('label' => 'Адрес для карты/маршрута', 'type' => 'text'),
        '_conference_route_directions' => array('label' => 'Как добраться: текст маршрута', 'type' => 'textarea'),
        '_conference_map_embed_url' => array('label' => 'Яндекс.Карта: готовый embed URL iframe', 'type' => 'url'),
        '_conference_map_lat' => array('label' => 'Широта метки карты', 'type' => 'text'),
        '_conference_map_lon' => array('label' => 'Долгота метки карты', 'type' => 'text'),
        '_conference_venue_image_url' => array('label' => 'Фото места проведения: URL изображения', 'type' => 'url'),
        '_conference_collage_image_url' => array('label' => 'Коллаж/доп. изображение: URL изображения', 'type' => 'url'),
    );
}

function fyremezzonine_manager_add_meta_boxes() {
    add_meta_box(
        'fyremezzonine_conference_details',
        'Данные конференции',
        'fyremezzonine_manager_render_meta_box',
        'conference',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'fyremezzonine_manager_add_meta_boxes');

function fyremezzonine_manager_render_meta_box($post) {
    wp_nonce_field('fyremezzonine_manager_save_meta', 'fyremezzonine_manager_meta_nonce');

    echo '<p><em>Эти поля управляют главной страницей, страницей конференции, формой регистрации и атмосферой события. Изображения можно загрузить в "Медиафайлы" и вставить сюда URL.</em></p>';

    foreach (fyremezzonine_manager_meta_keys() as $key => $field) {
        $value = get_post_meta($post->ID, $key, true);
        echo '<p>';
        printf('<label for="%1$s"><strong>%2$s</strong></label><br>', esc_attr($key), esc_html($field['label']));

        if ($field['type'] === 'textarea') {
            printf(
                '<textarea id="%1$s" name="%1$s" rows="4" class="widefat">%2$s</textarea>',
                esc_attr($key),
                esc_textarea($value)
            );
        } else {
            printf(
                '<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" class="widefat">',
                esc_attr($field['type']),
                esc_attr($key),
                esc_attr($value)
            );
        }

        echo '</p>';
    }
}

function fyremezzonine_manager_save_meta($post_id) {
    if (!isset($_POST['fyremezzonine_manager_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fyremezzonine_manager_meta_nonce'])), 'fyremezzonine_manager_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    foreach (fyremezzonine_manager_meta_keys() as $key => $field) {
        if (!isset($_POST[$key])) {
            update_post_meta($post_id, $key, '');
            continue;
        }

        $raw_value = wp_unslash($_POST[$key]);
        if ($field['type'] === 'textarea') {
            $value = sanitize_textarea_field($raw_value);
        } elseif ($field['type'] === 'url') {
            $value = esc_url_raw($raw_value);
        } else {
            $value = sanitize_text_field($raw_value);
        }

        update_post_meta($post_id, $key, $value);
    }
}
add_action('save_post_conference', 'fyremezzonine_manager_save_meta');

function fyremezzonine_manager_format_date($date) {
    if (!$date) {
        return '';
    }

    return date_i18n('d.m.Y', strtotime($date));
}

function fyremezzonine_manager_registration_is_closed($conference_id) {
    $deadline = get_post_meta($conference_id, '_conference_registration_deadline', true);

    return $deadline && $deadline < current_time('Y-m-d');
}

function fyremezzonine_manager_closed_registration_message($conference_id) {
    $deadline = get_post_meta($conference_id, '_conference_registration_deadline', true);
    $deadline_text = fyremezzonine_manager_format_date($deadline);

    $text = $deadline_text ? 'Дедлайн регистрации: ' . $deadline_text . '.' : 'Прием заявок на эту конференцию завершен.';

    return '<div class="registration-message registration-closed"><strong>Регистрация закрыта</strong><br>' . esc_html($text) . '</div>';
}

function fyremezzonine_manager_registration_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'conference_id' => 0,
        ),
        $atts,
        'conference_registration_form'
    );

    $conference_id = absint($atts['conference_id']);
    if (!$conference_id && is_singular('conference')) {
        $conference_id = get_the_ID();
    }
    if (!$conference_id) {
        $conference_id = fyremezzonine_manager_latest_conference_id();
    }

    $message = '';

    if (!$conference_id) {
        return '<div class="registration-message registration-error">Сейчас нет опубликованных конференций для регистрации.</div>';
    }

    if (fyremezzonine_manager_registration_is_closed($conference_id)) {
        return fyremezzonine_manager_closed_registration_message($conference_id);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fyremezzonine_registration_nonce'])) {
        $message = fyremezzonine_manager_handle_registration($conference_id);
    }

    ob_start();
    if ($message) {
        echo wp_kses_post($message);
    }
    ?>
    <form class="conference-registration-form" method="post">
        <?php wp_nonce_field('fyremezzonine_register_' . $conference_id, 'fyremezzonine_registration_nonce'); ?>
        <input type="hidden" name="conference_id" value="<?php echo esc_attr($conference_id); ?>">
        <div class="registration-conference-title">
            <span>Регистрация на конференцию</span>
            <strong><?php echo esc_html(get_the_title($conference_id)); ?></strong>
        </div>
        <p>
            <label>ФИО<br>
                <input type="text" name="full_name" required>
            </label>
        </p>
        <p>
            <label>Email<br>
                <input type="email" name="email" required>
            </label>
        </p>
        <p>
            <label>Телефон<br>
                <input type="tel" name="phone">
            </label>
        </p>
        <p>
            <label>Организация<br>
                <input type="text" name="organization">
            </label>
        </p>
        <p>
            <label>Комментарий<br>
                <textarea name="comment" rows="4"></textarea>
            </label>
        </p>
        <p><button class="button button-red" type="submit">Отправить заявку</button></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('conference_registration_form', 'fyremezzonine_manager_registration_shortcode');

function fyremezzonine_manager_latest_conference_id() {
    $today = current_time('Y-m-d');
    $query = new WP_Query(
        array(
            'post_type' => 'conference',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'meta_value',
            'meta_key' => '_conference_start_date',
            'order' => 'ASC',
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_conference_start_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'DATE',
                ),
            ),
        )
    );

    if ($query->posts) {
        return absint($query->posts[0]);
    }

    $fallback = new WP_Query(
        array(
            'post_type' => 'conference',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'meta_value',
            'meta_key' => '_conference_start_date',
            'order' => 'DESC',
            'fields' => 'ids',
        )
    );

    return $fallback->posts ? absint($fallback->posts[0]) : 0;
}

function fyremezzonine_manager_handle_registration($fallback_conference_id) {
    global $wpdb;

    $conference_id = isset($_POST['conference_id']) ? absint($_POST['conference_id']) : $fallback_conference_id;

    if (!$conference_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fyremezzonine_registration_nonce'])), 'fyremezzonine_register_' . $conference_id)) {
        return '<div class="registration-message registration-error">Не удалось проверить форму. Обновите страницу и попробуйте еще раз.</div>';
    }

    if (fyremezzonine_manager_registration_is_closed($conference_id)) {
        return fyremezzonine_manager_closed_registration_message($conference_id);
    }

    $full_name = isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $organization = isset($_POST['organization']) ? sanitize_text_field(wp_unslash($_POST['organization'])) : '';
    $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

    if (!$full_name || !$email || !is_email($email)) {
        return '<div class="registration-message registration-error">Заполните ФИО и корректный email.</div>';
    }

    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

    $wpdb->insert(
        fyremezzonine_manager_table_name(),
        array(
            'conference_id' => $conference_id,
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'organization' => $organization,
            'comment' => $comment,
            'status' => 'new',
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    return '<div class="registration-message registration-success">Спасибо! Заявка отправлена.</div>';
}

function fyremezzonine_manager_append_form_to_conference($content) {
    if (!is_singular('conference') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    return $content . do_shortcode('[conference_registration_form]');
}
add_filter('the_content', 'fyremezzonine_manager_append_form_to_conference');

function fyremezzonine_manager_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=conference',
        'Заявки',
        'Заявки',
        'edit_posts',
        'conference-registrations',
        'fyremezzonine_manager_render_registrations_page'
    );

    add_submenu_page(
        'edit.php?post_type=conference',
        'Как редактировать сайт',
        'Как редактировать сайт',
        'edit_posts',
        'conference-editing-guide',
        'fyremezzonine_manager_render_guide_page'
    );
}
add_action('admin_menu', 'fyremezzonine_manager_admin_menu');

function fyremezzonine_manager_render_guide_page() {
    ?>
    <div class="wrap">
        <h1>Как редактировать сайт конференций</h1>
        <p>Главная страница автоматически показывает ближайшую будущую конференцию: берется опубликованная конференция с минимальной датой начала, которая не меньше сегодняшней даты.</p>
        <h2>Где менять контент</h2>
        <ol>
            <li>Откройте <strong>Конференции -> Все конференции</strong>.</li>
            <li>Выберите конференцию или нажмите <strong>Добавить конференцию</strong>.</li>
            <li>Заполните название, описание, краткое описание и блок <strong>Данные конференции</strong>.</li>
            <li>Для картинок загрузите файл в <strong>Медиафайлы</strong>, скопируйте URL файла и вставьте его в нужное поле конференции.</li>
            <li>Для карты можно указать готовый iframe URL Яндекс.Карт, точные координаты метки или адрес для поиска.</li>
        </ol>
        <h2>Что управляется из карточки конференции</h2>
        <p>Дата, город, место, дедлайн, ссылки, первый экран, темы, изображения тем, блок "О конференции", преимущества, материалы, место проведения, маршрут, карта и галерея.</p>
        <h2>Где смотреть заявки</h2>
        <p>Заявки находятся в <strong>Конференции -> Заявки</strong>. Данные хранятся в таблице <code>wp_conference_registrations</code>.</p>
    </div>
    <?php
}

function fyremezzonine_manager_render_registrations_page() {
    global $wpdb;

    $table = fyremezzonine_manager_table_name();
    $items = $wpdb->get_results("SELECT r.*, p.post_title AS conference_title FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.conference_id ORDER BY r.created_at DESC LIMIT 200");
    ?>
    <div class="wrap">
        <h1>Заявки на конференции</h1>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Конференция</th>
                    <th>ФИО</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Организация</th>
                    <th>Комментарий</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items) : ?>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->created_at); ?></td>
                            <td><?php echo esc_html($item->conference_title); ?></td>
                            <td><?php echo esc_html($item->full_name); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($item->email); ?>"><?php echo esc_html($item->email); ?></a></td>
                            <td><?php echo esc_html($item->phone); ?></td>
                            <td><?php echo esc_html($item->organization); ?></td>
                            <td><?php echo esc_html($item->comment); ?></td>
                            <td><?php echo esc_html($item->status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="8">Пока заявок нет.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
