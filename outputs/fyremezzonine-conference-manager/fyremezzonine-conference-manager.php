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
define('FYREMEZZONINE_MANAGER_SCHEMA_VERSION', '1.1.0');

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
        job_position varchar(190) NOT NULL DEFAULT '',
        email varchar(190) NOT NULL,
        phone varchar(60) NOT NULL DEFAULT '',
        organization varchar(190) NOT NULL DEFAULT '',
        comment text NULL,
        privacy_consent tinyint(1) NOT NULL DEFAULT 0,
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
    update_option('fyremezzonine_manager_schema_version', FYREMEZZONINE_MANAGER_SCHEMA_VERSION);
}
register_activation_hook(__FILE__, 'fyremezzonine_manager_activate');

function fyremezzonine_manager_maybe_upgrade_schema() {
    if (get_option('fyremezzonine_manager_schema_version') !== FYREMEZZONINE_MANAGER_SCHEMA_VERSION) {
        fyremezzonine_manager_activate();
    }
}
add_action('plugins_loaded', 'fyremezzonine_manager_maybe_upgrade_schema');

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
        '_conference_chat_1_url' => array('label' => 'Ссылка на чат участников', 'type' => 'url'),
        '_conference_chat_2_url' => array('label' => 'Ссылка на чат оргкомитета', 'type' => 'url'),
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
        '_conference_venue_heading' => array('label' => 'Заголовок блока места проведения', 'type' => 'text'),
        '_conference_venue_intro' => array('label' => 'Описание места проведения', 'type' => 'textarea'),
        '_conference_route_address' => array('label' => 'Адрес для карты/маршрута', 'type' => 'text'),
        '_conference_route_directions' => array('label' => 'Как добраться: текст маршрута', 'type' => 'textarea'),
        '_conference_map_embed_url' => array('label' => 'Яндекс.Карта: готовый embed URL iframe', 'type' => 'url'),
        '_conference_map_lat' => array('label' => 'Широта метки карты', 'type' => 'text'),
        '_conference_map_lon' => array('label' => 'Долгота метки карты', 'type' => 'text'),
        '_conference_venue_image_url' => array('label' => 'Фото места проведения: URL изображения', 'type' => 'url'),
        '_conference_collage_image_url' => array('label' => 'Коллаж/доп. изображение: URL изображения', 'type' => 'url'),
        '_conference_organizers' => array('label' => 'Организаторы: название | ссылка | URL логотипа', 'type' => 'textarea'),
        '_conference_general_partners' => array('label' => 'Генеральные партнеры: название | ссылка | URL логотипа', 'type' => 'textarea'),
        '_conference_partners' => array('label' => 'Партнеры: название | ссылка | URL логотипа', 'type' => 'textarea'),
        '_conference_media_partners' => array('label' => 'Информационные партнеры: название | ссылка | URL логотипа', 'type' => 'textarea'),
    );
}

function fyremezzonine_manager_image_meta_keys() {
    return array(
        '_conference_hero_image_url',
        '_conference_topic_1_image_url',
        '_conference_topic_2_image_url',
        '_conference_topic_3_image_url',
        '_conference_venue_image_url',
        '_conference_collage_image_url',
    );
}

function fyremezzonine_manager_upload_image_for_field($field_name, $post_id = 0) {
    $file_key = $field_name . '_file';

    if (empty($_FILES[$file_key]) || !isset($_FILES[$file_key]['error']) || (int) $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ((int) $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_upload($file_key, $post_id);

    if (is_wp_error($attachment_id)) {
        return '';
    }

    return wp_get_attachment_url($attachment_id) ?: '';
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

    $image_fields = fyremezzonine_manager_image_meta_keys();

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

        if (in_array($key, $image_fields, true)) {
            printf(
                '<br><label for="%1$s_file">Загрузить новое изображение</label><br><input type="file" id="%1$s_file" name="%1$s_file" accept="image/*" class="widefat">',
                esc_attr($key)
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

        $uploaded_url = in_array($key, fyremezzonine_manager_image_meta_keys(), true) ? fyremezzonine_manager_upload_image_for_field($key, $post_id) : '';
        if ($uploaded_url) {
            update_post_meta($post_id, $key, esc_url_raw($uploaded_url));
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

function fyremezzonine_manager_submission_field_groups() {
    return array(
        'event' => array(
            'title' => 'Основные данные',
            'description' => 'То, что посетитель увидит в заголовке и кратком описании конференции.',
            'fields' => array(
                'conference_title' => array('label' => 'Название конференции', 'type' => 'text', 'required' => true),
                'conference_excerpt' => array('label' => 'Краткое описание', 'type' => 'textarea'),
                'conference_content' => array('label' => 'Полное описание', 'type' => 'textarea'),
            ),
        ),
        'schedule' => array(
            'title' => 'Даты и место',
            'description' => 'Эти поля управляют карточкой события, регистрацией и блоком места проведения.',
            'fields' => array(
                '_conference_start_date',
                '_conference_end_date',
                '_conference_city',
                '_conference_venue',
                '_conference_registration_deadline',
                '_conference_route_address',
                '_conference_route_directions',
            ),
        ),
        'content' => array(
            'title' => 'Содержимое страницы',
            'description' => 'Тексты для тематических блоков, материалов и раздела "О конференции".',
            'fields' => array(
                '_conference_topic_intro',
                '_conference_topic_1_title',
                '_conference_topic_2_title',
                '_conference_topic_3_title',
                '_conference_about_title',
                '_conference_about_lead',
                '_conference_benefits',
            ),
        ),
        'links' => array(
            'title' => 'Ссылки, изображения и карта',
            'description' => 'Файлы можно загрузить в "Медиафайлы", затем вставить сюда URL файла.',
            'fields' => array(
                '_conference_program_url',
                '_conference_chat_1_url',
                '_conference_chat_2_url',
                '_conference_hero_image_url',
                '_conference_topic_1_image_url',
                '_conference_topic_2_image_url',
                '_conference_topic_3_image_url',
                '_conference_venue_heading',
                '_conference_venue_intro',
                '_conference_map_embed_url',
                '_conference_map_lat',
                '_conference_map_lon',
                '_conference_venue_image_url',
                '_conference_collage_image_url',
            ),
        ),
        'partners' => array(
            'title' => 'Спонсоры и партнеры',
            'description' => 'Одна строка - один участник. Формат: Название | ссылка | URL логотипа.',
            'fields' => array(
                '_conference_organizers',
                '_conference_general_partners',
                '_conference_partners',
                '_conference_media_partners',
            ),
        ),
    );
}

function fyremezzonine_manager_sanitize_submission_value($value, $type) {
    $raw_value = wp_unslash($value);

    if ($type === 'textarea') {
        return sanitize_textarea_field($raw_value);
    }

    if ($type === 'url') {
        return esc_url_raw($raw_value);
    }

    return sanitize_text_field($raw_value);
}

function fyremezzonine_manager_render_submission_field($name, $field, $value = '') {
    $required = !empty($field['required']) ? ' required' : '';
    $label = isset($field['label']) ? $field['label'] : $name;
    $type = isset($field['type']) ? $field['type'] : 'text';
    $is_image_field = in_array($name, fyremezzonine_manager_image_meta_keys(), true);

    echo '<p class="conference-submission-field">';
    printf('<label for="%1$s">%2$s</label>', esc_attr($name), esc_html($label));

    if ($type === 'textarea') {
        printf(
            '<textarea id="%1$s" name="%1$s" rows="4"%3$s>%2$s</textarea>',
            esc_attr($name),
            esc_textarea($value),
            $required
        );
    } else {
        printf(
            '<input id="%1$s" type="%2$s" name="%1$s" value="%3$s"%4$s>',
            esc_attr($name),
            esc_attr($type),
            esc_attr($value),
            $required
        );
    }

    if ($is_image_field) {
        printf(
            '<span class="conference-submission-note">Можно вставить готовый URL или загрузить новый файл ниже.</span><input id="%1$s_file" type="file" name="%1$s_file" accept="image/*">',
            esc_attr($name)
        );
    }

    echo '</p>';
}

function fyremezzonine_manager_handle_conference_submission() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['fyremezzonine_conference_submission_nonce'])) {
        return '';
    }

    if (!current_user_can('edit_posts')) {
        return '<div class="registration-message registration-error">У вас нет прав для создания конференции.</div>';
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fyremezzonine_conference_submission_nonce'])), 'fyremezzonine_create_conference')) {
        return '<div class="registration-message registration-error">Не удалось проверить форму. Обновите страницу и попробуйте еще раз.</div>';
    }

    $title = isset($_POST['conference_title']) ? sanitize_text_field(wp_unslash($_POST['conference_title'])) : '';
    if (!$title) {
        return '<div class="registration-message registration-error">Заполните название конференции.</div>';
    }

    $editing_post_id = isset($_POST['conference_id']) ? absint($_POST['conference_id']) : 0;
    if ($editing_post_id && (!get_post($editing_post_id) || !current_user_can('edit_post', $editing_post_id))) {
        return '<div class="registration-message registration-error">У вас нет прав для редактирования этой конференции.</div>';
    }

    $status = $editing_post_id ? get_post_status($editing_post_id) : 'draft';
    if (isset($_POST['conference_status']) && $_POST['conference_status'] === 'publish' && current_user_can('publish_posts')) {
        $status = 'publish';
    }

    $post_data = array(
        'post_type' => 'conference',
        'post_status' => $status,
        'post_title' => $title,
        'post_excerpt' => isset($_POST['conference_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['conference_excerpt'])) : '',
        'post_content' => isset($_POST['conference_content']) ? wp_kses_post(wp_unslash($_POST['conference_content'])) : '',
    );

    if ($editing_post_id) {
        $post_data['ID'] = $editing_post_id;
        $post_id = wp_update_post($post_data, true);
    } else {
        $post_id = wp_insert_post($post_data, true);
    }

    if (is_wp_error($post_id)) {
        return '<div class="registration-message registration-error">Конференцию не удалось сохранить. Попробуйте еще раз.</div>';
    }

    foreach (fyremezzonine_manager_meta_keys() as $key => $field) {
        $uploaded_url = in_array($key, fyremezzonine_manager_image_meta_keys(), true) ? fyremezzonine_manager_upload_image_for_field($key, $post_id) : '';
        $value = $uploaded_url ?: (isset($_POST[$key]) ? fyremezzonine_manager_sanitize_submission_value($_POST[$key], $field['type']) : '');
        update_post_meta($post_id, $key, $value);
    }

    $edit_link = get_edit_post_link($post_id, '');
    $view_link = get_permalink($post_id);
    $message = $editing_post_id ? 'Конференция обновлена.' : ($status === 'publish' ? 'Конференция опубликована.' : 'Конференция сохранена как черновик.');

    $links = array();
    if ($edit_link) {
        $links[] = '<a href="' . esc_url($edit_link) . '">Открыть карточку</a>';
    }
    if ($status === 'publish' && $view_link) {
        $links[] = '<a href="' . esc_url($view_link) . '">Посмотреть на сайте</a>';
    }

    if ($links) {
        $message .= ' ' . implode(' | ', $links);
    }

    return '<div class="registration-message registration-success">' . wp_kses_post($message) . '</div>';
}

function fyremezzonine_manager_conference_submission_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="registration-message registration-error">Войдите в WordPress, чтобы создать конференцию.</div>';
    }

    if (!current_user_can('edit_posts')) {
        return '<div class="registration-message registration-error">У вашей учетной записи нет прав для создания конференций.</div>';
    }

    $message = fyremezzonine_manager_handle_conference_submission();
    $editing_conference_id = isset($_GET['conference_id']) ? absint($_GET['conference_id']) : 0;
    if (isset($_POST['conference_id'])) {
        $editing_conference_id = absint($_POST['conference_id']);
    }

    if ($editing_conference_id && (!get_post($editing_conference_id) || !current_user_can('edit_post', $editing_conference_id))) {
        return '<div class="registration-message registration-error">У вас нет прав для редактирования этой конференции.</div>';
    }

    if (!$editing_conference_id && trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/') === 'editor/edit-conference') {
        return fyremezzonine_manager_render_edit_picker();
    }

    $meta_fields = fyremezzonine_manager_meta_keys();
    $groups = fyremezzonine_manager_submission_field_groups();

    ob_start();
    echo wp_kses_post($message);
    ?>
    <form class="conference-submission-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('fyremezzonine_create_conference', 'fyremezzonine_conference_submission_nonce'); ?>
        <?php if ($editing_conference_id) : ?>
            <input type="hidden" name="conference_id" value="<?php echo esc_attr($editing_conference_id); ?>">
        <?php endif; ?>
        <div class="registration-conference-title">
            <span><?php echo $editing_conference_id ? 'Редактирование конференции' : 'Новая конференция'; ?></span>
            <strong><?php echo $editing_conference_id ? esc_html(get_the_title($editing_conference_id)) : 'Заполните форму, как анкету'; ?></strong>
        </div>

        <?php foreach ($groups as $group) : ?>
            <fieldset>
                <legend><?php echo esc_html($group['title']); ?></legend>
                <p class="conference-submission-help"><?php echo esc_html($group['description']); ?></p>
                <?php foreach ($group['fields'] as $field_name => $field) : ?>
                    <?php
                    if (is_int($field_name)) {
                        $field_name = $field;
                        $field = isset($meta_fields[$field_name]) ? $meta_fields[$field_name] : null;
                    }

                    if (!$field) {
                        continue;
                    }

                    $value = fyremezzonine_manager_submission_value($field_name, $editing_conference_id);
                    fyremezzonine_manager_render_submission_field($field_name, $field, $value);
                    ?>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>

        <fieldset>
            <legend>Публикация</legend>
            <p class="conference-submission-field">
                <label for="conference_status">Как сохранить</label>
                <select id="conference_status" name="conference_status">
                    <?php $current_status = $editing_conference_id ? get_post_status($editing_conference_id) : 'draft'; ?>
                    <option value="draft" <?php selected($current_status, 'draft'); ?>>Черновик для проверки</option>
                    <?php if (current_user_can('publish_posts')) : ?>
                        <option value="publish" <?php selected($current_status, 'publish'); ?>>Опубликовать сразу</option>
                    <?php endif; ?>
                </select>
            </p>
        </fieldset>

        <p class="conference-submission-actions">
            <button class="button button-red" type="submit">Сохранить конференцию</button>
        </p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('conference_submission_form', 'fyremezzonine_manager_conference_submission_shortcode');

function fyremezzonine_manager_editor_page_url($page = 'new-conference') {
    return home_url('/editor/' . trim($page, '/') . '/');
}

function fyremezzonine_manager_submission_value($field_name, $conference_id = 0) {
    if (isset($_POST[$field_name])) {
        return wp_unslash($_POST[$field_name]);
    }

    if (!$conference_id) {
        return '';
    }

    $post = get_post($conference_id);
    if (!$post) {
        return '';
    }

    if ($field_name === 'conference_title') {
        return $post->post_title;
    }

    if ($field_name === 'conference_excerpt') {
        return $post->post_excerpt;
    }

    if ($field_name === 'conference_content') {
        return $post->post_content;
    }

    return get_post_meta($conference_id, $field_name, true);
}

function fyremezzonine_manager_render_edit_picker() {
    $conferences = fyremezzonine_manager_get_conference_options();

    ob_start();
    ?>
    <div class="conference-editor-picker">
        <p>Выберите конференцию, которую нужно изменить. После выбора откроется такая же анкета, но уже с заполненными данными.</p>
        <?php if ($conferences) : ?>
            <div class="conference-editor-picker-list">
                <?php foreach ($conferences as $conference) : ?>
                    <?php
                    $status_object = get_post_status_object(get_post_status($conference));
                    $status_label = $status_object ? $status_object->label : get_post_status($conference);
                    ?>
                    <a class="conference-editor-picker-item" href="<?php echo esc_url(add_query_arg('conference_id', $conference->ID, fyremezzonine_manager_editor_page_url('edit-conference'))); ?>">
                        <strong><?php echo esc_html(get_the_title($conference)); ?></strong>
                        <span><?php echo esc_html($status_label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="registration-message registration-error">Пока нет конференций для редактирования.</div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

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

function fyremezzonine_manager_privacy_policy_url() {
    $policy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';

    return $policy_url ?: home_url('/privacy-policy/');
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
    if (!$conference_id && isset($_GET['conference_id'])) {
        $conference_id = absint($_GET['conference_id']);
    }
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
            <label>Должность<br>
                <input type="text" name="job_position" placeholder="Например: генеральный директор">
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
        <p class="registration-consent">
            <label>
                <input type="checkbox" name="privacy_consent" value="1" required>
                <span>Я согласен(а) с <a href="<?php echo esc_url(fyremezzonine_manager_privacy_policy_url()); ?>" target="_blank" rel="noopener">политикой обработки персональных данных</a>.</span>
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
    $job_position = isset($_POST['job_position']) ? sanitize_text_field(wp_unslash($_POST['job_position'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $organization = isset($_POST['organization']) ? sanitize_text_field(wp_unslash($_POST['organization'])) : '';
    $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
    $privacy_consent = isset($_POST['privacy_consent']) && $_POST['privacy_consent'] === '1';

    if (!$full_name || !$email || !is_email($email)) {
        return '<div class="registration-message registration-error">Заполните ФИО и корректный email.</div>';
    }

    if (!$privacy_consent) {
        return '<div class="registration-message registration-error">Подтвердите согласие с политикой обработки персональных данных.</div>';
    }

    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

    $wpdb->insert(
        fyremezzonine_manager_table_name(),
        array(
            'conference_id' => $conference_id,
            'full_name' => $full_name,
            'job_position' => $job_position,
            'email' => $email,
            'phone' => $phone,
            'organization' => $organization,
            'comment' => $comment,
            'privacy_consent' => 1,
            'status' => 'new',
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
    );

    return '<div class="registration-message registration-success">Спасибо! Заявка отправлена.</div>';
}

function fyremezzonine_manager_render_simple_create_page() {
    ?>
    <div class="wrap fyremezzonine-simple-create">
        <h1>Создать конференцию через форму</h1>
        <p>Эта страница работает как анкета: заполните поля, выберите режим сохранения и отправьте форму.</p>
        <style>
            .fyremezzonine-simple-create .conference-submission-form {
                display: grid;
                gap: 22px;
                max-width: 920px;
                margin-top: 20px;
                padding: 24px;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                background: #fff;
            }
            .fyremezzonine-simple-create fieldset {
                display: grid;
                gap: 14px;
                margin: 0;
                padding: 0 0 22px;
                border: 0;
                border-bottom: 1px solid #dcdcde;
            }
            .fyremezzonine-simple-create legend {
                margin-bottom: 4px;
                font-size: 20px;
                font-weight: 700;
            }
            .fyremezzonine-simple-create .registration-conference-title {
                display: grid;
                gap: 6px;
                padding-bottom: 16px;
                border-bottom: 1px solid #dcdcde;
            }
            .fyremezzonine-simple-create .registration-conference-title span {
                color: #646970;
                font-weight: 700;
                text-transform: uppercase;
            }
            .fyremezzonine-simple-create .registration-conference-title strong {
                font-size: 22px;
            }
            .fyremezzonine-simple-create .conference-submission-help {
                margin: 0;
                color: #646970;
            }
            .fyremezzonine-simple-create .conference-submission-field {
                display: grid;
                gap: 7px;
                margin: 0;
            }
            .fyremezzonine-simple-create .conference-submission-field label {
                font-weight: 700;
            }
            .fyremezzonine-simple-create input,
            .fyremezzonine-simple-create textarea,
            .fyremezzonine-simple-create select {
                width: 100%;
                max-width: 100%;
            }
        </style>
        <?php echo do_shortcode('[conference_submission_form]'); ?>
    </div>
    <?php
}

function fyremezzonine_manager_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=conference',
        'Создать через форму',
        'Создать через форму',
        'edit_posts',
        'conference-create-simple',
        'fyremezzonine_manager_render_simple_create_page'
    );

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

function fyremezzonine_manager_get_conference_options() {
    return get_posts(
        array(
            'post_type' => 'conference',
            'post_status' => array('publish', 'draft', 'future', 'private'),
            'numberposts' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_conference_start_date',
            'order' => 'DESC',
        )
    );
}

function fyremezzonine_manager_registrations_query($conference_id = 0, $limit = 0) {
    global $wpdb;

    $table = fyremezzonine_manager_table_name();
    $sql = "SELECT r.*, p.post_title AS conference_title FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.conference_id";
    $params = array();

    if ($conference_id) {
        $sql .= ' WHERE r.conference_id = %d';
        $params[] = $conference_id;
    }

    $sql .= ' ORDER BY r.created_at DESC';

    if ($limit) {
        $sql .= ' LIMIT %d';
        $params[] = $limit;
    }

    if ($params) {
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    return $wpdb->get_results($sql);
}

function fyremezzonine_manager_export_registrations() {
    if (!current_user_can('edit_posts')) {
        wp_die('Недостаточно прав.');
    }

    check_admin_referer('fyremezzonine_export_registrations');

    $conference_id = isset($_GET['conference_id']) ? absint($_GET['conference_id']) : 0;
    $items = fyremezzonine_manager_registrations_query($conference_id);
    $conference_slug = $conference_id ? sanitize_title(get_the_title($conference_id)) : 'all';
    $filename = 'conference-registrations-' . $conference_slug . '-' . gmdate('Y-m-d') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv(
        $output,
        array('ID', 'Дата', 'Конференция', 'ФИО', 'Должность', 'Email', 'Телефон', 'Организация', 'Комментарий', 'Согласие', 'Статус', 'IP'),
        ';'
    );

    foreach ($items as $item) {
        fputcsv(
            $output,
            array(
                $item->id,
                $item->created_at,
                $item->conference_title,
                $item->full_name,
                $item->job_position,
                $item->email,
                $item->phone,
                $item->organization,
                $item->comment,
                $item->privacy_consent ? 'Да' : 'Нет',
                $item->status,
                $item->ip_address,
            ),
            ';'
        );
    }

    fclose($output);
    exit;
}
add_action('admin_post_fyremezzonine_export_registrations', 'fyremezzonine_manager_export_registrations');

function fyremezzonine_manager_registrations_interface($admin_mode = false) {
    $conference_id = isset($_GET['conference_id']) ? absint($_GET['conference_id']) : 0;
    $items = fyremezzonine_manager_registrations_query($conference_id, 200);
    $conferences = fyremezzonine_manager_get_conference_options();
    $export_url = wp_nonce_url(
        add_query_arg(
            array(
                'action' => 'fyremezzonine_export_registrations',
                'conference_id' => $conference_id,
            ),
            admin_url('admin-post.php')
        ),
        'fyremezzonine_export_registrations'
    );
    $form_action = $admin_mode ? admin_url('edit.php') : fyremezzonine_manager_editor_page_url('registrations');
    $table_class = $admin_mode ? 'widefat fixed striped' : 'conference-registrations-table';
    ob_start();
    ?>
    <p>Регистрации не удаляются после завершения конференции: старые заявки остаются в архиве и доступны по фильтру.</p>

    <form class="<?php echo $admin_mode ? 'conference-admin-filter' : 'conference-editor-filter'; ?>" method="get" action="<?php echo esc_url($form_action); ?>">
        <?php if ($admin_mode) : ?>
            <input type="hidden" name="post_type" value="conference">
            <input type="hidden" name="page" value="conference-registrations">
        <?php endif; ?>
        <p>
            <label for="conference_id"><strong>Конференция</strong></label>
            <select id="conference_id" name="conference_id">
                <option value="0">Все конференции</option>
                <?php foreach ($conferences as $conference) : ?>
                    <option value="<?php echo esc_attr($conference->ID); ?>" <?php selected($conference_id, $conference->ID); ?>>
                        <?php echo esc_html(get_the_title($conference)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <button type="submit" class="<?php echo $admin_mode ? 'button' : 'button button-outline'; ?>">Показать</button>
        <a class="<?php echo $admin_mode ? 'button button-primary' : 'button button-red'; ?>" href="<?php echo esc_url($export_url); ?>">Выгрузить CSV</a>
    </form>

    <div class="conference-registrations-scroll">
        <table class="<?php echo esc_attr($table_class); ?>">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Конференция</th>
                    <th>ФИО</th>
                    <th>Должность</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Организация</th>
                    <th>Комментарий</th>
                    <th>Согласие</th>
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
                            <td><?php echo esc_html($item->job_position); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($item->email); ?>"><?php echo esc_html($item->email); ?></a></td>
                            <td><?php echo esc_html($item->phone); ?></td>
                            <td><?php echo esc_html($item->organization); ?></td>
                            <td><?php echo esc_html($item->comment); ?></td>
                            <td><?php echo $item->privacy_consent ? 'Да' : 'Нет'; ?></td>
                            <td><?php echo esc_html($item->status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="10">Пока заявок нет.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

function fyremezzonine_manager_registrations_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="registration-message registration-error">Войдите в WordPress, чтобы посмотреть заявки.</div>';
    }

    if (!current_user_can('edit_posts')) {
        return '<div class="registration-message registration-error">У вашей учетной записи нет прав для просмотра заявок.</div>';
    }

    return fyremezzonine_manager_registrations_interface(false);
}
add_shortcode('conference_registrations_archive', 'fyremezzonine_manager_registrations_shortcode');

function fyremezzonine_manager_render_frontend_editor_page($title, $content) {
    global $wp_query;

    if ($wp_query) {
        $wp_query->is_404 = false;
    }

    status_header(200);
    get_header();
    ?>
    <main id="primary">
        <section class="section editor-page">
            <div class="section-inner page-layout">
                <p class="section-eyebrow">Редактор</p>
                <h1 class="section-title"><?php echo esc_html($title); ?></h1>
                <?php echo do_shortcode($content); ?>
            </div>
        </section>
    </main>
    <?php
    get_footer();
    exit;
}

function fyremezzonine_manager_frontend_editor_routes() {
    $path = isset($_SERVER['REQUEST_URI']) ? trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH), '/') : '';

    if ($path === 'editor/new-conference') {
        fyremezzonine_manager_render_frontend_editor_page('Создать конференцию', '[conference_submission_form]');
    }

    if ($path === 'editor/edit-conference') {
        fyremezzonine_manager_render_frontend_editor_page('Изменить конференцию', '[conference_submission_form]');
    }

    if ($path === 'editor/registrations') {
        fyremezzonine_manager_render_frontend_editor_page('Заявки на конференции', '[conference_registrations_archive]');
    }
}
add_action('template_redirect', 'fyremezzonine_manager_frontend_editor_routes');

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
        <h2>Упрощенная форма для редактора</h2>
        <p>На сайте после входа редактора откройте меню <strong>Редактор -> Создать конференцию</strong>. Это форма в стиле анкеты: она создает конференцию без работы с обычным редактором WordPress. Адрес страницы: <code>/editor/new-conference/</code>.</p>
        <h2>Что управляется из карточки конференции</h2>
        <p>Дата, город, место, дедлайн, ссылка на программу, первый экран, темы, изображения тем, блок "О конференции", преимущества, партнеры и спонсоры, место проведения, маршрут, карта и галерея.</p>
        <h2>Где смотреть заявки</h2>
        <p>На сайте после входа редактора откройте <strong>Редактор -> Заявки и экспорт</strong>. Там можно выбрать конференцию, посмотреть архив старых регистраций и выгрузить CSV. Данные хранятся в таблице <code>wp_conference_registrations</code>.</p>
    </div>
    <?php
}

function fyremezzonine_manager_render_registrations_page() {
    ?>
    <div class="wrap">
        <h1>Заявки на конференции</h1>
        <?php echo fyremezzonine_manager_registrations_interface(true); ?>
    </div>
    <?php
}
