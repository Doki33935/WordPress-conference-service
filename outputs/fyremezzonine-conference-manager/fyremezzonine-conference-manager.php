<?php
/**
 * Plugin Name: Fyremezzonine Conference Manager
 * Description: Adds conferences, conference metadata, public registration forms, and admin registration lists.
 * Version: 1.7.0
 * Author: Codex
 * Text Domain: fyremezzonine-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FYREMEZZONINE_MANAGER_VERSION', '1.7.0');
define('FYREMEZZONINE_MANAGER_SCHEMA_VERSION', '2.0.0');
define('FYREMEZZONINE_EMAIL_CODE_TTL', 10 * MINUTE_IN_SECONDS);
define('FYREMEZZONINE_EMAIL_RESEND_DELAY', MINUTE_IN_SECONDS);
define('FYREMEZZONINE_EMAIL_MAX_ATTEMPTS', 5);
define('FYREMEZZONINE_EMAIL_MAX_SENDS', 3);

function fyremezzonine_manager_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'conference_registrations';
}

function fyremezzonine_manager_partner_requests_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'conference_partner_requests';
}

function fyremezzonine_manager_activity_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'conference_activity_log';
}

function fyremezzonine_manager_env($name, $default = '') {
    $value = getenv($name);
    return $value === false || $value === '' ? $default : trim((string) $value);
}

function fyremezzonine_manager_smtp_is_configured() {
    return fyremezzonine_manager_env('FYREMEZZONINE_SMTP_HOST')
        && fyremezzonine_manager_env('FYREMEZZONINE_SMTP_USERNAME')
        && fyremezzonine_manager_env('FYREMEZZONINE_SMTP_PASSWORD');
}

function fyremezzonine_manager_configure_phpmailer($phpmailer) {
    if (!fyremezzonine_manager_smtp_is_configured()) {
        return;
    }

    $encryption = strtolower(fyremezzonine_manager_env('FYREMEZZONINE_SMTP_ENCRYPTION', 'tls'));
    if (!in_array($encryption, array('ssl', 'tls'), true)) {
        $encryption = 'tls';
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = fyremezzonine_manager_env('FYREMEZZONINE_SMTP_HOST', 'smtp.mail.ru');
    $phpmailer->Port = absint(fyremezzonine_manager_env('FYREMEZZONINE_SMTP_PORT', '2525')) ?: 2525;
    $phpmailer->SMTPAuth = true;
    $phpmailer->SMTPSecure = $encryption;
    $phpmailer->SMTPAutoTLS = true;
    $phpmailer->Username = fyremezzonine_manager_env('FYREMEZZONINE_SMTP_USERNAME');
    $phpmailer->Password = fyremezzonine_manager_env('FYREMEZZONINE_SMTP_PASSWORD');
    $phpmailer->From = fyremezzonine_manager_env('FYREMEZZONINE_SMTP_FROM_EMAIL', $phpmailer->Username);
    $phpmailer->FromName = fyremezzonine_manager_env('FYREMEZZONINE_SMTP_FROM_NAME', 'ВНИИПО Конференции');
}
add_action('phpmailer_init', 'fyremezzonine_manager_configure_phpmailer');

function fyremezzonine_manager_mail_from($email) {
    if (!fyremezzonine_manager_smtp_is_configured()) {
        return $email;
    }

    return fyremezzonine_manager_env('FYREMEZZONINE_SMTP_FROM_EMAIL', fyremezzonine_manager_env('FYREMEZZONINE_SMTP_USERNAME'));
}
add_filter('wp_mail_from', 'fyremezzonine_manager_mail_from');

function fyremezzonine_manager_mail_from_name($name) {
    if (!fyremezzonine_manager_smtp_is_configured()) {
        return $name;
    }

    return fyremezzonine_manager_env('FYREMEZZONINE_SMTP_FROM_NAME', 'ВНИИПО Конференции');
}
add_filter('wp_mail_from_name', 'fyremezzonine_manager_mail_from_name');

function fyremezzonine_manager_log_mail_error($error) {
    if (defined('WP_DEBUG') && WP_DEBUG && is_wp_error($error)) {
        error_log('[fyremezzonine-mail] ' . $error->get_error_message());
    }
}
add_action('wp_mail_failed', 'fyremezzonine_manager_log_mail_error');

function fyremezzonine_manager_partnership_level_options() {
    return array(
        'general_partner' => 'Генеральный партнер',
        'partner' => 'Партнер',
        'media_partner' => 'Информационный партнер',
        'organizer' => 'Соорганизатор',
    );
}

function fyremezzonine_manager_partnership_level_label($level) {
    $options = fyremezzonine_manager_partnership_level_options();
    return $options[$level] ?? $options['partner'];
}

function fyremezzonine_manager_participant_type_options() {
    return array(
        'attendee' => 'Участвующий (слушатель)',
        'speaker' => 'Спикер (докладчик)',
        'coorganizer' => 'Соорганизатор',
        'exhibition' => 'Выставка',
        'mini_demo' => 'Мини-демонстрация (демонстрация на выставочном стенде)',
        'large_demo' => 'Крупномасштабная демонстрация',
    );
}

function fyremezzonine_manager_sanitize_participant_types($raw_types) {
    $raw_types = is_array($raw_types) ? $raw_types : array();
    $allowed = fyremezzonine_manager_participant_type_options();
    $types = array();

    foreach ($raw_types as $type) {
        $type = sanitize_key($type);
        if (isset($allowed[$type])) {
            $types[] = $type;
        }
    }

    return array_values(array_unique($types));
}

function fyremezzonine_manager_participant_types_label($types) {
    $options = fyremezzonine_manager_participant_type_options();
    $types = is_array($types) ? $types : array_filter(array_map('trim', explode(',', (string) $types)));
    $labels = array();

    foreach ($types as $type) {
        if (isset($options[$type])) {
            $labels[] = $options[$type];
        }
    }

    return implode(', ', $labels);
}

function fyremezzonine_manager_activate() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $table = fyremezzonine_manager_table_name();
    $partner_table = fyremezzonine_manager_partner_requests_table_name();
    $activity_table = fyremezzonine_manager_activity_table_name();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        conference_id bigint(20) unsigned NOT NULL,
        full_name varchar(190) NOT NULL,
        last_name varchar(190) NOT NULL DEFAULT '',
        first_name varchar(190) NOT NULL DEFAULT '',
        middle_name varchar(190) NOT NULL DEFAULT '',
        job_position varchar(190) NOT NULL DEFAULT '',
        email varchar(190) NOT NULL,
        phone varchar(60) NOT NULL DEFAULT '',
        organization varchar(190) NOT NULL DEFAULT '',
        participant_types text NULL,
        interest_topics text NULL,
        attended tinyint(1) NOT NULL DEFAULT 0,
        comment text NULL,
        privacy_consent tinyint(1) NOT NULL DEFAULT 0,
        max_policy_consent tinyint(1) NOT NULL DEFAULT 0,
        status varchar(40) NOT NULL DEFAULT 'new',
        email_verified tinyint(1) NOT NULL DEFAULT 0,
        verification_code_hash varchar(255) NOT NULL DEFAULT '',
        verification_token_hash char(64) NOT NULL DEFAULT '',
        verification_expires_at datetime NULL DEFAULT NULL,
        verification_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
        verification_sent_at datetime NULL DEFAULT NULL,
        verification_resend_count smallint(5) unsigned NOT NULL DEFAULT 0,
        verified_at datetime NULL DEFAULT NULL,
        ip_address varchar(80) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY conference_id (conference_id),
        KEY email (email),
        KEY status (status),
        KEY email_verified (email_verified),
        KEY verification_token_hash (verification_token_hash),
        KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta($sql);
    $wpdb->query("UPDATE {$table} SET email_verified = 1, verified_at = created_at WHERE status <> 'pending_email' AND email_verified = 0");

    $partner_sql = "CREATE TABLE {$partner_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        conference_id bigint(20) unsigned NOT NULL DEFAULT 0,
        company_name varchar(190) NOT NULL,
        company_site varchar(190) NOT NULL DEFAULT '',
        company_city varchar(190) NOT NULL DEFAULT '',
        partnership_level varchar(80) NOT NULL DEFAULT 'partner',
        contact_name varchar(190) NOT NULL,
        contact_position varchar(190) NOT NULL DEFAULT '',
        email varchar(190) NOT NULL,
        phone varchar(60) NOT NULL DEFAULT '',
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

    dbDelta($partner_sql);

    $activity_sql = "CREATE TABLE {$activity_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        conference_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        action varchar(60) NOT NULL,
        message text NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY conference_id (conference_id),
        KEY user_id (user_id),
        KEY action (action),
        KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta($activity_sql);
    fyremezzonine_manager_ensure_privacy_policy_page();
    if (!wp_next_scheduled('fyremezzonine_manager_cleanup_pending_registrations')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'fyremezzonine_manager_cleanup_pending_registrations');
    }
    update_option('fyremezzonine_manager_schema_version', FYREMEZZONINE_MANAGER_SCHEMA_VERSION);
}
register_activation_hook(__FILE__, 'fyremezzonine_manager_activate');

function fyremezzonine_manager_deactivate() {
    wp_clear_scheduled_hook('fyremezzonine_manager_cleanup_pending_registrations');
}
register_deactivation_hook(__FILE__, 'fyremezzonine_manager_deactivate');

function fyremezzonine_manager_cleanup_pending_registrations() {
    global $wpdb;

    $cutoff = wp_date('Y-m-d H:i:s', current_datetime()->getTimestamp() - DAY_IN_SECONDS);
    $wpdb->query(
        $wpdb->prepare(
            'DELETE FROM ' . fyremezzonine_manager_table_name() . " WHERE status = 'pending_email' AND created_at < %s",
            $cutoff
        )
    );
}
add_action('fyremezzonine_manager_cleanup_pending_registrations', 'fyremezzonine_manager_cleanup_pending_registrations');

function fyremezzonine_manager_maybe_upgrade_schema() {
    if (get_option('fyremezzonine_manager_schema_version') !== FYREMEZZONINE_MANAGER_SCHEMA_VERSION) {
        fyremezzonine_manager_activate();
    }
}
add_action('plugins_loaded', 'fyremezzonine_manager_maybe_upgrade_schema');

function fyremezzonine_manager_conference_capabilities() {
    return array(
        'manage_conferences',
        'edit_conference',
        'read_conference',
        'delete_conference',
        'edit_conferences',
        'edit_others_conferences',
        'publish_conferences',
        'read_private_conferences',
        'delete_conferences',
        'delete_private_conferences',
        'delete_published_conferences',
        'delete_others_conferences',
        'edit_private_conferences',
        'edit_published_conferences',
        'create_conferences',
    );
}

function fyremezzonine_manager_register_section_manager_role() {
    $role = get_role('conference_section_manager');
    if (!$role) {
        $role = add_role(
            'conference_section_manager',
            'Ответственный за секции',
            array(
                'read' => true,
                'level_0' => true,
                'view_conference_registration_stats' => true,
            )
        );
    }

    if (!$role instanceof WP_Role) {
        return;
    }

    $allowed_capabilities = array('read', 'level_0', 'view_conference_registration_stats');
    foreach (array_keys($role->capabilities) as $capability) {
        if (!in_array($capability, $allowed_capabilities, true)) {
            $role->remove_cap($capability);
        }
    }

    foreach ($allowed_capabilities as $capability) {
        $role->add_cap($capability);
    }
}
add_action('plugins_loaded', 'fyremezzonine_manager_register_section_manager_role');

function fyremezzonine_manager_grant_editor_capabilities() {
    $roles = wp_roles();
    foreach ($roles->role_objects as $role) {
        if (!$role instanceof WP_Role || (!$role->has_cap('edit_posts') && !$role->has_cap('manage_options'))) {
            continue;
        }

        foreach (fyremezzonine_manager_conference_capabilities() as $capability) {
            $role->add_cap($capability);
        }
    }
}
add_action('plugins_loaded', 'fyremezzonine_manager_grant_editor_capabilities');

function fyremezzonine_manager_can_manage_conferences($user = null) {
    if ($user instanceof WP_User) {
        return user_can($user, 'manage_conferences') || user_can($user, 'edit_posts');
    }

    return current_user_can('manage_conferences') || current_user_can('edit_posts');
}

function fyremezzonine_manager_can_view_registration_stats($user = null) {
    if ($user instanceof WP_User) {
        return fyremezzonine_manager_can_manage_conferences($user) || user_can($user, 'view_conference_registration_stats');
    }

    return fyremezzonine_manager_can_manage_conferences() || current_user_can('view_conference_registration_stats');
}

function fyremezzonine_manager_is_section_manager($user = null) {
    if ($user instanceof WP_User) {
        return user_can($user, 'view_conference_registration_stats') && !fyremezzonine_manager_can_manage_conferences($user);
    }

    return is_user_logged_in() && current_user_can('view_conference_registration_stats') && !fyremezzonine_manager_can_manage_conferences();
}

function fyremezzonine_manager_section_statistics_url() {
    return admin_url('admin.php?page=conference-section-statistics');
}

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
            'capability_type' => array('conference', 'conferences'),
            'map_meta_cap' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'has_archive' => true,
            'rewrite' => array('slug' => 'conferences'),
            'show_in_rest' => true,
        )
    );
}
add_action('init', 'fyremezzonine_manager_register_post_type');

function fyremezzonine_manager_use_classic_editor_for_conferences($use_block_editor, $post_type) {
    return $post_type === 'conference' ? false : $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'fyremezzonine_manager_use_classic_editor_for_conferences', 10, 2);

function fyremezzonine_manager_lifecycle_labels() {
    return array(
        'upcoming' => 'Будущая',
        'current' => 'Проходит сейчас',
        'completed' => 'Завершена',
    );
}

function fyremezzonine_manager_lifecycle_status($conference_id) {
    $status = sanitize_key((string) get_post_meta($conference_id, '_conference_lifecycle_status', true));

    return isset(fyremezzonine_manager_lifecycle_labels()[$status]) ? $status : 'upcoming';
}

function fyremezzonine_manager_log_activity($conference_id, $action, $message = '', $user_id = null) {
    global $wpdb;

    $conference_id = absint($conference_id);
    if (!$conference_id) {
        return;
    }

    $wpdb->insert(
        fyremezzonine_manager_activity_table_name(),
        array(
            'conference_id' => $conference_id,
            'user_id' => $user_id === null ? get_current_user_id() : absint($user_id),
            'action' => sanitize_key($action),
            'message' => sanitize_text_field($message),
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%d', '%s', '%s', '%s')
    );
}

function fyremezzonine_manager_current_conference_id() {
    $conference_id = absint(get_option('fyremezzonine_current_conference_id'));

    if (
        $conference_id
        && get_post_type($conference_id) === 'conference'
        && get_post_status($conference_id) === 'publish'
        && fyremezzonine_manager_lifecycle_status($conference_id) === 'current'
    ) {
        return $conference_id;
    }

    if ($conference_id) {
        delete_option('fyremezzonine_current_conference_id');
    }

    return 0;
}

function fyremezzonine_manager_next_queued_conference_id($exclude_id = 0) {
    $conference_ids = get_posts(
        array(
            'post_type' => 'conference',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => array('date' => 'ASC', 'ID' => 'ASC'),
            'fields' => 'ids',
        )
    );

    foreach ($conference_ids as $conference_id) {
        $conference_id = absint($conference_id);
        if ($conference_id === absint($exclude_id)) {
            continue;
        }

        if (fyremezzonine_manager_lifecycle_status($conference_id) === 'upcoming') {
            return $conference_id;
        }
    }

    return 0;
}

function fyremezzonine_manager_set_current_conference($conference_id, $reason = 'Назначена текущей конференцией') {
    $conference_id = absint($conference_id);
    if (!$conference_id || get_post_status($conference_id) !== 'publish') {
        return false;
    }

    $previous_id = fyremezzonine_manager_current_conference_id();
    if ($previous_id && $previous_id !== $conference_id && fyremezzonine_manager_lifecycle_status($previous_id) !== 'completed') {
        update_post_meta($previous_id, '_conference_lifecycle_status', 'upcoming');
        fyremezzonine_manager_log_activity($previous_id, 'moved_to_queue', 'Перенесена в будущие конференции');
    }

    update_post_meta($conference_id, '_conference_lifecycle_status', 'current');
    update_option('fyremezzonine_current_conference_id', $conference_id, false);
    fyremezzonine_manager_log_activity($conference_id, 'made_current', $reason);

    return true;
}

function fyremezzonine_manager_promote_next_conference($exclude_id = 0) {
    delete_option('fyremezzonine_current_conference_id');
    $next_id = fyremezzonine_manager_next_queued_conference_id($exclude_id);

    if ($next_id) {
        fyremezzonine_manager_set_current_conference($next_id, 'Автоматически назначена следующей текущей конференцией');
    }

    return $next_id;
}

function fyremezzonine_manager_ensure_current_conference() {
    if (fyremezzonine_manager_current_conference_id()) {
        return;
    }

    $existing = get_posts(
        array(
            'post_type' => 'conference',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_key' => '_conference_lifecycle_status',
            'meta_value' => 'current',
        )
    );

    if ($existing) {
        update_option('fyremezzonine_current_conference_id', absint($existing[0]), false);
        return;
    }

    $next_id = fyremezzonine_manager_next_queued_conference_id();
    if ($next_id) {
        fyremezzonine_manager_set_current_conference($next_id, 'Назначена текущей при обновлении системы');
    }
}
add_action('init', 'fyremezzonine_manager_ensure_current_conference', 30);

function fyremezzonine_manager_after_insert_conference($post_id, $post, $update, $post_before) {
    if (!$post instanceof WP_Post || $post->post_type !== 'conference' || wp_is_post_revision($post_id)) {
        return;
    }

    if (!get_post_meta($post_id, '_conference_preparation_started_at', true)) {
        update_post_meta($post_id, '_conference_preparation_started_at', $post->post_date ?: current_time('mysql'));
    }

    if (!$update) {
        fyremezzonine_manager_log_activity($post_id, 'created', 'Создана информация о конференции');
        return;
    }

    if ($post_before instanceof WP_Post && $post_before->post_status === $post->post_status) {
        fyremezzonine_manager_log_activity($post_id, 'updated', 'Изменена информация о конференции');
    }
}
add_action('wp_after_insert_post', 'fyremezzonine_manager_after_insert_conference', 10, 4);

function fyremezzonine_manager_track_status_transition($new_status, $old_status, $post) {
    if (!$post instanceof WP_Post || $post->post_type !== 'conference' || $new_status === $old_status) {
        return;
    }

    if ($new_status === 'publish') {
        if (!get_post_meta($post->ID, '_conference_published_at', true)) {
            update_post_meta($post->ID, '_conference_published_at', current_time('mysql'));
        }

        if (!fyremezzonine_manager_current_conference_id()) {
            fyremezzonine_manager_set_current_conference($post->ID, 'Первая опубликованная конференция назначена текущей');
        } else {
            update_post_meta($post->ID, '_conference_lifecycle_status', 'upcoming');
        }
        fyremezzonine_manager_log_activity($post->ID, 'published', 'Конференция опубликована');
        return;
    }

    if ($old_status === 'publish' && $new_status !== 'publish') {
        $was_current = fyremezzonine_manager_lifecycle_status($post->ID) === 'current';
        update_post_meta($post->ID, '_conference_lifecycle_status', 'upcoming');
        fyremezzonine_manager_log_activity($post->ID, 'unpublished', 'Конференция снята с публикации и возвращена в черновики');

        if ($was_current) {
            fyremezzonine_manager_promote_next_conference($post->ID);
        }
    }
}
add_action('transition_post_status', 'fyremezzonine_manager_track_status_transition', 10, 3);

function fyremezzonine_manager_lock_completed_conference($caps, $cap, $user_id, $args) {
    if ($cap !== 'edit_post' || empty($args[0])) {
        return $caps;
    }

    $post_id = absint($args[0]);
    if (get_post_type($post_id) === 'conference' && fyremezzonine_manager_lifecycle_status($post_id) === 'completed') {
        return array('do_not_allow');
    }

    return $caps;
}
add_filter('map_meta_cap', 'fyremezzonine_manager_lock_completed_conference', 10, 4);

function fyremezzonine_manager_meta_keys() {
    return array(
        '_conference_start_date' => array('label' => 'Дата начала', 'type' => 'date'),
        '_conference_end_date' => array('label' => 'Дата окончания', 'type' => 'date'),
        '_conference_city' => array('label' => 'Город', 'type' => 'text'),
        '_conference_venue' => array('label' => 'Место проведения', 'type' => 'text'),
        '_conference_venues' => array('label' => 'Места проведения', 'type' => 'venues'),
        '_conference_registration_deadline' => array('label' => 'Дедлайн регистрации', 'type' => 'date'),
        '_conference_visual_theme' => array(
            'label' => 'Визуальная тема конференции',
            'type' => 'select',
            'options' => array(
                'classic' => 'Статья: светлая спокойная тема',
                'arctic' => 'Арктика: голубой фон, фиолетовые акценты, снежинки',
                'ember' => 'Дым и искры: темная жаркая тема',
            ),
        ),
        '_conference_program_url' => array('label' => 'Кнопка "Программа конференции": ссылка', 'type' => 'url'),
        '_conference_chat_1_url' => array('label' => 'Чат участников: ссылка после регистрации', 'type' => 'url'),
        '_conference_hero_image_url' => array('label' => 'Фон первого экрана: фото или GIF', 'type' => 'url'),
        '_conference_topic_intro' => array('label' => 'Описание блока тем', 'type' => 'textarea'),
        '_conference_topic_1_title' => array('label' => 'Тема 1: текст', 'type' => 'text'),
        '_conference_topic_1_image_url' => array('label' => 'Тема 1: изображение', 'type' => 'url'),
        '_conference_topic_2_title' => array('label' => 'Тема 2: текст', 'type' => 'text'),
        '_conference_topic_2_image_url' => array('label' => 'Тема 2: изображение', 'type' => 'url'),
        '_conference_topic_3_title' => array('label' => 'Тема 3: текст', 'type' => 'text'),
        '_conference_topic_3_image_url' => array('label' => 'Тема 3: изображение', 'type' => 'url'),
        '_conference_about_title' => array('label' => 'Заголовок блока "О конференции"', 'type' => 'text'),
        '_conference_about_lead' => array('label' => 'Лид блока "О конференции"', 'type' => 'textarea'),
        '_conference_benefits' => array('label' => 'Преимущества/тезисы: по одному пункту на строку', 'type' => 'textarea'),
        '_conference_speakers' => array('label' => 'Спикеры конференции', 'type' => 'speakers'),
        '_conference_venue_heading' => array('label' => 'Заголовок блока "Место проведения"', 'type' => 'text'),
        '_conference_venue_intro' => array('label' => 'Описание места проведения под картой', 'type' => 'textarea'),
        '_conference_route_address' => array('label' => 'Адрес для карты/маршрута', 'type' => 'text'),
        '_conference_route_directions' => array('label' => 'Как добраться: текст маршрута', 'type' => 'textarea'),
        '_conference_map_lat' => array('label' => 'Широта метки карты', 'type' => 'text'),
        '_conference_map_lon' => array('label' => 'Долгота метки карты', 'type' => 'text'),
        '_conference_venue_image_url' => array('label' => 'Фото под картой: основное изображение', 'type' => 'url'),
        '_conference_collage_image_url' => array('label' => 'Фото под картой: дополнительное изображение', 'type' => 'url'),
        '_conference_organizers' => array('label' => 'Организаторы', 'type' => 'partners'),
        '_conference_general_partners' => array('label' => 'Генеральные партнеры', 'type' => 'partners'),
        '_conference_partners' => array('label' => 'Партнеры', 'type' => 'partners'),
        '_conference_media_partners' => array('label' => 'Информационные партнеры', 'type' => 'partners'),
        '_conference_topics' => array('label' => 'Темы конференции', 'type' => 'topics'),
    );
}

function fyremezzonine_manager_hidden_editor_meta_keys() {
    return array(
        '_conference_map_lat',
        '_conference_map_lon',
        '_conference_city',
        '_conference_venue',
        '_conference_route_address',
        '_conference_route_directions',
        '_conference_topic_1_title',
        '_conference_topic_1_image_url',
        '_conference_topic_2_title',
        '_conference_topic_2_image_url',
        '_conference_topic_3_title',
        '_conference_topic_3_image_url',
    );
}

function fyremezzonine_manager_partner_meta_keys() {
    return array(
        '_conference_organizers',
        '_conference_general_partners',
        '_conference_partners',
        '_conference_media_partners',
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

function fyremezzonine_manager_repeater_file_count($file_key) {
    if (empty($_FILES[$file_key]) || empty($_FILES[$file_key]['name']) || !is_array($_FILES[$file_key]['name'])) {
        return 0;
    }

    return count($_FILES[$file_key]['name']);
}

function fyremezzonine_manager_upload_repeater_image_for_field($file_key, $index, $post_id = 0) {
    if (empty($_FILES[$file_key]) || empty($_FILES[$file_key]['name']) || !is_array($_FILES[$file_key]['name'])) {
        return '';
    }

    if (empty($_FILES[$file_key]['name'][$index])) {
        return '';
    }

    $error = isset($_FILES[$file_key]['error'][$index]) ? (int) $_FILES[$file_key]['error'][$index] : UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE || $error !== UPLOAD_ERR_OK) {
        return '';
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $single_file_key = $file_key . '_' . $index . '_upload';
    $_FILES[$single_file_key] = array(
        'name' => $_FILES[$file_key]['name'][$index],
        'type' => $_FILES[$file_key]['type'][$index] ?? '',
        'tmp_name' => $_FILES[$file_key]['tmp_name'][$index] ?? '',
        'error' => $error,
        'size' => $_FILES[$file_key]['size'][$index] ?? 0,
    );

    $attachment_id = media_handle_upload($single_file_key, $post_id);
    unset($_FILES[$single_file_key]);

    if (is_wp_error($attachment_id)) {
        return '';
    }

    return wp_get_attachment_url($attachment_id) ?: '';
}

function fyremezzonine_manager_render_uploaded_image_preview($image_url, $label = 'Загруженное изображение') {
    if (!$image_url) {
        return;
    }

    ?>
    <figure class="conference-upload-preview">
        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($label); ?>" loading="lazy" onerror="this.closest('.conference-upload-preview').classList.add('conference-upload-preview-broken'); this.remove();">
        <figcaption>
            <span class="conference-upload-preview-error">Изображение не загрузилось. Проверьте ссылку или загрузите файл заново.</span>
            <a href="<?php echo esc_url($image_url); ?>" target="_blank" rel="noopener">Открыть файл</a>
        </figcaption>
    </figure>
    <?php
}

function fyremezzonine_manager_parse_partner_rows($raw) {
    $items = array();

    foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw))) as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (empty($parts[0])) {
            continue;
        }

        $items[] = array(
            'name' => $parts[0],
            'url' => $parts[1] ?? '',
            'logo_url' => $parts[2] ?? '',
        );
    }

    return $items;
}

function fyremezzonine_manager_partner_rows_from_request($key, $post_id = 0) {
    $names = isset($_POST[$key . '_name']) && is_array($_POST[$key . '_name']) ? wp_unslash($_POST[$key . '_name']) : array();
    $urls = isset($_POST[$key . '_url']) && is_array($_POST[$key . '_url']) ? wp_unslash($_POST[$key . '_url']) : array();
    $logos = isset($_POST[$key . '_logo_url']) && is_array($_POST[$key . '_logo_url']) ? wp_unslash($_POST[$key . '_logo_url']) : array();
    $file_key = $key . '_logo_url_file';
    $rows = array();
    $total = max(count($names), count($urls), count($logos), fyremezzonine_manager_repeater_file_count($file_key));

    for ($index = 0; $index < $total; $index++) {
        $name = isset($names[$index]) ? sanitize_text_field($names[$index]) : '';
        $url = isset($urls[$index]) ? esc_url_raw($urls[$index]) : '';
        $uploaded_logo_url = fyremezzonine_manager_upload_repeater_image_for_field($file_key, $index, $post_id);
        $logo_url = $uploaded_logo_url ?: (isset($logos[$index]) ? esc_url_raw($logos[$index]) : '');

        if (!$name && !$url && !$logo_url) {
            continue;
        }

        if (!$name) {
            $name = $key === '_conference_organizers' ? 'Организатор конференции' : 'Партнер конференции';
        }

        $rows[] = $name . ' | ' . $url . ' | ' . $logo_url;
    }

    return implode("\n", $rows);
}

function fyremezzonine_manager_parse_topic_rows($raw) {
    $items = array();

    foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw))) as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (empty($parts[0])) {
            continue;
        }

        $items[] = array(
            'title' => $parts[0],
            'image_url' => $parts[1] ?? '',
            'sections' => fyremezzonine_manager_decode_topic_sections($parts[2] ?? ''),
        );
    }

    return $items;
}

function fyremezzonine_manager_decode_topic_sections($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return array();
    }

    $decoded = json_decode(rawurldecode($raw), true);
    if (!is_array($decoded)) {
        $decoded = preg_split('/\s*;;\s*/', $raw);
    }

    return array_values(array_unique(array_filter(array_map('sanitize_text_field', $decoded))));
}

function fyremezzonine_manager_encode_topic_sections($sections) {
    $sections = is_array($sections) ? $sections : array();
    $sections = array_values(array_unique(array_filter(array_map('sanitize_text_field', $sections))));

    return $sections ? rawurlencode(wp_json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : '';
}

function fyremezzonine_manager_topic_rows_from_request($key, $post_id = 0) {
    $titles = isset($_POST[$key . '_title']) && is_array($_POST[$key . '_title']) ? wp_unslash($_POST[$key . '_title']) : array();
    $images = isset($_POST[$key . '_image_url']) && is_array($_POST[$key . '_image_url']) ? wp_unslash($_POST[$key . '_image_url']) : array();
    $section_lists = isset($_POST[$key . '_sections']) && is_array($_POST[$key . '_sections']) ? wp_unslash($_POST[$key . '_sections']) : array();
    $file_key = $key . '_image_url_file';
    $rows = array();
    $total = max(count($titles), count($images), count($section_lists), fyremezzonine_manager_repeater_file_count($file_key));

    for ($index = 0; $index < $total; $index++) {
        $title = isset($titles[$index]) ? sanitize_text_field($titles[$index]) : '';
        $uploaded_image_url = fyremezzonine_manager_upload_repeater_image_for_field($file_key, $index, $post_id);
        $image_url = $uploaded_image_url ?: (isset($images[$index]) ? esc_url_raw($images[$index]) : '');
        $sections = isset($section_lists[$index])
            ? array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $section_lists[$index])))
            : array();

        if (!$title && !$image_url && !$sections) {
            continue;
        }

        if (!$title) {
            $title = 'Тема конференции';
        }

        $rows[] = $title . ' | ' . $image_url . ' | ' . fyremezzonine_manager_encode_topic_sections($sections);
    }

    return implode("\n", $rows);
}

function fyremezzonine_manager_parse_speaker_rows($raw) {
    $items = array();

    foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw))) as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (empty($parts[0])) {
            continue;
        }

        $items[] = array(
            'name' => $parts[0],
            'position' => $parts[1] ?? '',
            'quote' => $parts[2] ?? '',
            'photo_url' => $parts[3] ?? '',
            'direction' => $parts[4] ?? '',
        );
    }

    return $items;
}

function fyremezzonine_manager_speaker_rows_from_request($key, $post_id = 0) {
    $names = isset($_POST[$key . '_name']) && is_array($_POST[$key . '_name']) ? wp_unslash($_POST[$key . '_name']) : array();
    $positions = isset($_POST[$key . '_position']) && is_array($_POST[$key . '_position']) ? wp_unslash($_POST[$key . '_position']) : array();
    $directions = isset($_POST[$key . '_direction']) && is_array($_POST[$key . '_direction']) ? wp_unslash($_POST[$key . '_direction']) : array();
    $quotes = isset($_POST[$key . '_quote']) && is_array($_POST[$key . '_quote']) ? wp_unslash($_POST[$key . '_quote']) : array();
    $photos = isset($_POST[$key . '_photo_url']) && is_array($_POST[$key . '_photo_url']) ? wp_unslash($_POST[$key . '_photo_url']) : array();
    $file_key = $key . '_photo_url_file';
    $rows = array();
    $total = max(count($names), count($positions), count($directions), count($quotes), count($photos), fyremezzonine_manager_repeater_file_count($file_key));

    for ($index = 0; $index < $total; $index++) {
        $name = isset($names[$index]) ? sanitize_text_field($names[$index]) : '';
        $position = isset($positions[$index]) ? sanitize_text_field($positions[$index]) : '';
        $direction = isset($directions[$index]) ? sanitize_text_field($directions[$index]) : '';
        $quote = isset($quotes[$index]) ? sanitize_text_field($quotes[$index]) : '';
        $uploaded_photo_url = fyremezzonine_manager_upload_repeater_image_for_field($file_key, $index, $post_id);
        $photo_url = $uploaded_photo_url ?: (isset($photos[$index]) ? esc_url_raw($photos[$index]) : '');

        if (!$name && !$position && !$direction && !$quote && !$photo_url) {
            continue;
        }

        if (!$name) {
            $name = 'Спикер конференции';
        }

        $rows[] = $name . ' | ' . $position . ' | ' . $quote . ' | ' . $photo_url . ' | ' . $direction;
    }

    return implode("\n", $rows);
}

function fyremezzonine_manager_parse_venue_rows($raw) {
    if (is_array($raw)) {
        $decoded = $raw;
    } else {
        $decoded = json_decode((string) $raw, true);
    }

    if (!is_array($decoded)) {
        return array();
    }

    $rows = array();
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $venue = array(
            'name' => sanitize_text_field($row['name'] ?? ''),
            'city' => sanitize_text_field($row['city'] ?? ''),
            'address' => sanitize_text_field($row['address'] ?? ''),
            'purpose' => sanitize_textarea_field($row['purpose'] ?? ''),
            'directions' => sanitize_textarea_field($row['directions'] ?? ''),
        );

        if (array_filter($venue)) {
            $rows[] = $venue;
        }
    }

    return $rows;
}

function fyremezzonine_manager_venue_rows_from_request($key) {
    $names = isset($_POST[$key . '_name']) ? (array) wp_unslash($_POST[$key . '_name']) : array();
    $cities = isset($_POST[$key . '_city']) ? (array) wp_unslash($_POST[$key . '_city']) : array();
    $addresses = isset($_POST[$key . '_address']) ? (array) wp_unslash($_POST[$key . '_address']) : array();
    $purposes = isset($_POST[$key . '_purpose']) ? (array) wp_unslash($_POST[$key . '_purpose']) : array();
    $directions = isset($_POST[$key . '_directions']) ? (array) wp_unslash($_POST[$key . '_directions']) : array();
    $count = max(count($names), count($cities), count($addresses), count($purposes), count($directions));
    $rows = array();

    for ($index = 0; $index < $count; $index++) {
        $row = array(
            'name' => sanitize_text_field($names[$index] ?? ''),
            'city' => sanitize_text_field($cities[$index] ?? ''),
            'address' => sanitize_text_field($addresses[$index] ?? ''),
            'purpose' => sanitize_textarea_field($purposes[$index] ?? ''),
            'directions' => sanitize_textarea_field($directions[$index] ?? ''),
        );

        if (array_filter($row)) {
            $rows[] = $row;
        }
    }

    return wp_json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function fyremezzonine_manager_legacy_venue_value($conference_id) {
    $conference_id = absint($conference_id);
    if (!$conference_id) {
        return '';
    }

    $row = array(
        'name' => sanitize_text_field(get_post_meta($conference_id, '_conference_venue', true)),
        'city' => sanitize_text_field(get_post_meta($conference_id, '_conference_city', true)),
        'address' => sanitize_text_field(get_post_meta($conference_id, '_conference_route_address', true)),
        'purpose' => '',
        'directions' => sanitize_textarea_field(get_post_meta($conference_id, '_conference_route_directions', true)),
    );

    if (!array_filter($row)) {
        return '';
    }

    return wp_json_encode(array($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function fyremezzonine_manager_render_venue_repeater($key, $label, $value = '') {
    $items = fyremezzonine_manager_parse_venue_rows($value);
    if (!$items) {
        $items = array(array('name' => '', 'city' => '', 'address' => '', 'purpose' => '', 'directions' => ''));
    }

    $render_row = static function($item) use ($key) {
        ?>
        <div class="conference-partner-row conference-venue-row" data-repeater-row>
            <div class="conference-partner-row-head">
                <strong class="conference-partner-row-title">Площадка</strong>
                <button type="button" class="button conference-partner-remove" data-repeater-remove>Удалить</button>
            </div>
            <div class="conference-partner-fields">
                <label>
                    <span>Название места</span>
                    <input type="text" name="<?php echo esc_attr($key); ?>_name[]" value="<?php echo esc_attr($item['name'] ?? ''); ?>" placeholder="Например: испытательный полигон ВНИИПО">
                </label>
                <label>
                    <span>Город</span>
                    <input type="text" name="<?php echo esc_attr($key); ?>_city[]" value="<?php echo esc_attr($item['city'] ?? ''); ?>" placeholder="Например: Оренбург">
                </label>
                <label>
                    <span>Точный адрес для карты</span>
                    <input type="text" name="<?php echo esc_attr($key); ?>_address[]" value="<?php echo esc_attr($item['address'] ?? ''); ?>" placeholder="Например: Нижняя Павловка, Полигонная улица, д. 1">
                </label>
                <label>
                    <span>Цель площадки / что здесь будет проходить</span>
                    <textarea name="<?php echo esc_attr($key); ?>_purpose[]" rows="3" placeholder="Например: крупномасштабные демонстрации техники и практические испытания"><?php echo esc_textarea($item['purpose'] ?? ''); ?></textarea>
                </label>
                <label>
                    <span>Как добраться</span>
                    <textarea name="<?php echo esc_attr($key); ?>_directions[]" rows="3" placeholder="Опишите маршрут для участников"><?php echo esc_textarea($item['directions'] ?? ''); ?></textarea>
                </label>
            </div>
        </div>
        <?php
    };

    echo '<div class="conference-submission-field conference-partner-repeater conference-venue-repeater" data-repeater>';
    echo '<div class="conference-partner-repeater-head">';
    printf('<label>%s</label>', esc_html($label));
    echo '<p class="conference-submission-note">Добавьте одну или несколько площадок. Для каждой укажите, что там будет проходить: сайт покажет цель, адрес, маршрут и отдельную карту.</p>';
    echo '</div>';
    echo '<div class="conference-partner-rows" data-repeater-rows>';
    foreach ($items as $item) {
        $render_row($item);
    }
    echo '</div>';
    echo '<button type="button" class="button conference-partner-add" data-repeater-add>+ Добавить место проведения</button>';
    echo '<template data-repeater-template>';
    $render_row(array('name' => '', 'city' => '', 'address' => '', 'purpose' => '', 'directions' => ''));
    echo '</template>';
    echo '</div>';

    fyremezzonine_manager_render_partner_repeater_assets();
}

function fyremezzonine_manager_render_partner_repeater_assets() {
    static $rendered = false;

    if ($rendered) {
        return;
    }

    $rendered = true;
    ?>
    <style>
        .conference-partner-repeater {
            display: grid;
            gap: 14px;
            min-width: 0;
            margin: 0 0 18px;
        }
        .conference-partner-rows {
            display: grid;
            gap: 14px;
            counter-reset: conference-partner;
        }
        .conference-partner-row {
            display: grid;
            gap: 14px;
            min-width: 0;
            padding: 16px;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(16, 24, 40, 0.06);
            counter-increment: conference-partner;
        }
        .conference-partner-row-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-width: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f1;
        }
        .conference-partner-row-title {
            min-width: 0;
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .conference-partner-row-title::after {
            content: counter(conference-partner);
            display: inline-grid;
            min-width: 24px;
            height: 24px;
            margin-left: 8px;
            place-items: center;
            border-radius: 999px;
            color: #fff;
            background: #525afc;
            font-size: 12px;
        }
        .conference-partner-fields {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            min-width: 0;
        }
        .conference-partner-row label {
            display: grid;
            gap: 6px;
            min-width: 0;
            margin: 0;
            font-weight: 600;
        }
        .conference-partner-row label span {
            color: #1d2327;
            font-size: 13px;
            overflow-wrap: anywhere;
        }
        .conference-partner-row input,
        .conference-partner-row textarea {
            width: 100%;
            min-width: 0;
        }
        .conference-topic-sections {
            display: grid;
            gap: 10px;
            min-width: 0;
            padding: 14px;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            background: #f6f7f7;
        }
        .conference-topic-sections-head {
            display: grid;
            gap: 4px;
        }
        .conference-topic-sections-head strong {
            color: #1d2327;
            font-size: 13px;
        }
        .conference-topic-sections-head small,
        .conference-topic-sections-empty {
            margin: 0;
            color: #646970;
            font-size: 12px;
            line-height: 1.4;
        }
        .conference-topic-section-rows {
            display: grid;
            gap: 8px;
        }
        .conference-topic-section-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
            gap: 10px;
            min-width: 0;
            padding: 10px;
            border: 1px solid #dcdcde;
            border-radius: 7px;
            background: #fff;
        }
        .conference-topic-section-row .button {
            min-height: 38px;
            margin: 0;
        }
        .conference-topic-sections-value[hidden] {
            display: none !important;
        }
        .conference-upload-preview {
            display: grid;
            gap: 7px;
            justify-self: center;
            width: min(280px, 100%);
            margin: 6px auto;
            padding: 10px;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            background: #f6f7f7;
        }
        .conference-upload-preview img {
            width: 100%;
            max-height: 150px;
            object-fit: contain;
            border-radius: 8px;
            background: #fff;
        }
        .conference-upload-preview figcaption {
            color: #646970;
            font-size: 12px;
            line-height: 1.3;
            text-align: center;
        }
        .conference-upload-preview-error {
            display: none;
            margin-bottom: 4px;
            color: #b32d2e;
            font-weight: 700;
        }
        .conference-upload-preview-broken .conference-upload-preview-error {
            display: block;
        }
        .conference-partner-repeater template {
            display: none;
        }
        @media (max-width: 900px) {
            .conference-partner-fields {
                grid-template-columns: 1fr;
            }
            .conference-partner-row-head {
                align-items: flex-start;
                flex-direction: column;
            }
            .conference-topic-section-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        document.addEventListener('click', function(event) {
            const addButton = event.target.closest('[data-repeater-add]');
            if (addButton) {
                const repeater = addButton.closest('[data-repeater]');
                const rows = repeater.querySelector('[data-repeater-rows]');
                const template = repeater.querySelector('[data-repeater-template]');
                rows.insertAdjacentHTML('beforeend', template.innerHTML);
            }

            const removeButton = event.target.closest('[data-repeater-remove]');
            if (removeButton) {
                removeButton.closest('[data-repeater-row]').remove();
            }

            const sectionAddButton = event.target.closest('[data-topic-section-add]');
            if (sectionAddButton) {
                const sectionGroup = sectionAddButton.closest('[data-topic-sections]');
                const sectionRows = sectionGroup.querySelector('[data-topic-section-rows]');
                const sectionTemplate = sectionGroup.querySelector('template');
                sectionRows.insertAdjacentHTML('beforeend', sectionTemplate.innerHTML);
                syncTopicSections(sectionGroup);
            }

            const sectionRemoveButton = event.target.closest('[data-topic-section-remove]');
            if (sectionRemoveButton) {
                const sectionGroup = sectionRemoveButton.closest('[data-topic-sections]');
                sectionRemoveButton.closest('[data-topic-section-row]').remove();
                syncTopicSections(sectionGroup);
            }
        });

        function syncTopicSections(sectionGroup) {
            const values = Array.from(sectionGroup.querySelectorAll('[data-topic-section-input]'))
                .map(function(input) { return input.value.trim(); })
                .filter(Boolean);
            const storage = sectionGroup.querySelector('[data-topic-sections-value]');
            const emptyMessage = sectionGroup.querySelector('[data-topic-sections-empty]');
            storage.value = values.join('\n');
            emptyMessage.hidden = values.length > 0;
        }

        document.addEventListener('input', function(event) {
            if (!event.target.matches('[data-topic-section-input]')) {
                return;
            }
            syncTopicSections(event.target.closest('[data-topic-sections]'));
        });

        document.addEventListener('submit', function(event) {
            event.target.querySelectorAll('[data-topic-sections]').forEach(syncTopicSections);
        });
    </script>
    <?php
}

function fyremezzonine_manager_render_partner_repeater($key, $label, $value = '') {
    $items = fyremezzonine_manager_parse_partner_rows($value);
    if (!$items) {
        $items = array(array('name' => '', 'url' => '', 'logo_url' => ''));
    }

    $entity_title = $key === '_conference_organizers' ? 'Организатор' : 'Партнер';
    $add_button_label = $key === '_conference_organizers' ? '+ Добавить организатора' : '+ Добавить партнера';

    $render_row = static function($item) use ($key, $entity_title) {
        ?>
        <div class="conference-partner-row" data-repeater-row>
            <div class="conference-partner-row-head">
                <strong class="conference-partner-row-title"><?php echo esc_html($entity_title); ?></strong>
                <button type="button" class="button conference-partner-remove" data-repeater-remove>Удалить</button>
            </div>
            <div class="conference-partner-fields">
                <label>
                    <span>Название компании</span>
                    <input type="text" name="<?php echo esc_attr($key); ?>_name[]" value="<?php echo esc_attr($item['name'] ?? ''); ?>" placeholder="Например: Fireproff">
                </label>
                <label>
                    <span>Ссылка на сайт партнера</span>
                    <input type="url" name="<?php echo esc_attr($key); ?>_url[]" value="<?php echo esc_attr($item['url'] ?? ''); ?>" placeholder="https://example.ru/">
                </label>
                <label>
                    <span>Иконка/логотип</span>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>_logo_url[]" value="<?php echo esc_attr($item['logo_url'] ?? ''); ?>">
                    <?php if (!empty($item['logo_url'])) : ?>
                        <?php fyremezzonine_manager_render_uploaded_image_preview($item['logo_url'], 'Логотип ' . ($item['name'] ?? 'организации')); ?>
                        <small>Файл уже загружен. Чтобы заменить, выберите новый.</small>
                    <?php endif; ?>
                    <input type="file" name="<?php echo esc_attr($key); ?>_logo_url_file[]" accept="image/*,.gif">
                </label>
            </div>
        </div>
        <?php
    };

    echo '<div class="conference-submission-field conference-partner-repeater" data-repeater>';
    echo '<div class="conference-partner-repeater-head">';
    printf('<label>%s</label>', esc_html($label));
    echo '<p class="conference-submission-note">Можно оставить список пустым. Каждая карточка - одна организация с названием, сайтом и логотипом.</p>';
    echo '</div>';
    echo '<div class="conference-partner-rows" data-repeater-rows>';
    foreach ($items as $item) {
        $render_row($item);
    }
    echo '</div>';
    printf('<button type="button" class="button conference-partner-add" data-repeater-add>%s</button>', esc_html($add_button_label));
    echo '<template data-repeater-template>';
    $render_row(array('name' => '', 'url' => '', 'logo_url' => ''));
    echo '</template>';
    echo '</div>';

    fyremezzonine_manager_render_partner_repeater_assets();
}

function fyremezzonine_manager_partner_template_meta_keys() {
    return array(
        '_conference_organizers',
        '_conference_general_partners',
        '_conference_partners',
        '_conference_media_partners',
    );
}

function fyremezzonine_manager_topic_template_meta_keys() {
    return array(
        '_conference_topic_intro',
        '_conference_topics',
    );
}

function fyremezzonine_manager_conference_has_partner_template($conference_id) {
    foreach (fyremezzonine_manager_partner_template_meta_keys() as $meta_key) {
        if (trim((string) get_post_meta($conference_id, $meta_key, true)) !== '') {
            return true;
        }
    }

    return false;
}

function fyremezzonine_manager_conference_has_topic_template($conference_id) {
    foreach (fyremezzonine_manager_topic_template_meta_keys() as $meta_key) {
        if (trim((string) get_post_meta($conference_id, $meta_key, true)) !== '') {
            return true;
        }
    }

    return false;
}

function fyremezzonine_manager_latest_template_conference_id($has_template_callback, $exclude_id = 0) {
    $conferences = get_posts(
        array(
            'post_type' => 'conference',
            'post_status' => array('publish', 'draft', 'future', 'private'),
            'numberposts' => -1,
            'meta_key' => '_conference_start_date',
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'fields' => 'ids',
        )
    );

    foreach ($conferences as $conference_id) {
        $conference_id = absint($conference_id);
        if ($conference_id === absint($exclude_id)) {
            continue;
        }

        if (call_user_func($has_template_callback, $conference_id)) {
            return $conference_id;
        }
    }

    $fallback_conferences = get_posts(
        array(
            'post_type' => 'conference',
            'post_status' => array('publish', 'draft', 'future', 'private'),
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        )
    );

    foreach ($fallback_conferences as $conference_id) {
        $conference_id = absint($conference_id);
        if ($conference_id === absint($exclude_id)) {
            continue;
        }

        if (call_user_func($has_template_callback, $conference_id)) {
            return $conference_id;
        }
    }

    return 0;
}

function fyremezzonine_manager_latest_partner_template_conference_id($exclude_id = 0) {
    return fyremezzonine_manager_latest_template_conference_id('fyremezzonine_manager_conference_has_partner_template', $exclude_id);
}

function fyremezzonine_manager_partner_catalog_level_meta_keys() {
    return array(
        'organizer' => '_conference_organizers',
        'general_partner' => '_conference_general_partners',
        'partner' => '_conference_partners',
        'media_partner' => '_conference_media_partners',
    );
}

function fyremezzonine_manager_sanitize_partner_catalog_item($item) {
    $item = is_array($item) ? $item : array();
    $levels = fyremezzonine_manager_partnership_level_options();
    $level = sanitize_key($item['level'] ?? 'partner');

    return array(
        'id' => sanitize_text_field($item['id'] ?? wp_generate_uuid4()),
        'name' => sanitize_text_field($item['name'] ?? ''),
        'level' => isset($levels[$level]) ? $level : 'partner',
        'site' => esc_url_raw($item['site'] ?? ''),
        'logo_url' => esc_url_raw($item['logo_url'] ?? ''),
        'city' => sanitize_text_field($item['city'] ?? ''),
        'contact_name' => sanitize_text_field($item['contact_name'] ?? ''),
        'contact_position' => sanitize_text_field($item['contact_position'] ?? ''),
        'email' => sanitize_email($item['email'] ?? ''),
        'phone' => sanitize_text_field($item['phone'] ?? ''),
        'comment' => sanitize_textarea_field($item['comment'] ?? ''),
        'request_id' => absint($item['request_id'] ?? 0),
        'updated_at' => sanitize_text_field($item['updated_at'] ?? current_time('mysql')),
    );
}

function fyremezzonine_manager_import_partner_catalog() {
    $conference_id = fyremezzonine_manager_latest_partner_template_conference_id();
    if (!$conference_id) {
        return array();
    }

    $catalog = array();
    $seen = array();
    foreach (fyremezzonine_manager_partner_catalog_level_meta_keys() as $level => $meta_key) {
        $rows = fyremezzonine_manager_parse_partner_rows(get_post_meta($conference_id, $meta_key, true));
        foreach ($rows as $row) {
            $identity = strtolower(trim(($row['name'] ?? '') . '|' . ($row['url'] ?? '')));
            if (!$identity || isset($seen[$identity])) {
                continue;
            }

            $seen[$identity] = true;
            $catalog[] = fyremezzonine_manager_sanitize_partner_catalog_item(
                array(
                    'name' => $row['name'] ?? '',
                    'level' => $level,
                    'site' => $row['url'] ?? '',
                    'logo_url' => $row['logo_url'] ?? '',
                )
            );
        }
    }

    return $catalog;
}

function fyremezzonine_manager_get_partner_catalog() {
    $catalog = get_option('fyremezzonine_partner_catalog', null);
    if ($catalog === null) {
        $catalog = fyremezzonine_manager_import_partner_catalog();
        update_option('fyremezzonine_partner_catalog', $catalog, false);
    }

    if (!is_array($catalog)) {
        return array();
    }

    return array_values(array_filter(array_map('fyremezzonine_manager_sanitize_partner_catalog_item', $catalog), static function($item) {
        return !empty($item['name']);
    }));
}

function fyremezzonine_manager_save_partner_catalog($catalog) {
    $catalog = is_array($catalog) ? $catalog : array();
    $catalog = array_values(array_filter(array_map('fyremezzonine_manager_sanitize_partner_catalog_item', $catalog), static function($item) {
        return !empty($item['name']);
    }));
    update_option('fyremezzonine_partner_catalog', $catalog, false);

    return $catalog;
}

function fyremezzonine_manager_find_partner_catalog_item($partner_id) {
    foreach (fyremezzonine_manager_get_partner_catalog() as $item) {
        if (hash_equals((string) $item['id'], (string) $partner_id)) {
            return $item;
        }
    }

    return null;
}

function fyremezzonine_manager_partner_catalog_meta_value($meta_key) {
    $level = array_search($meta_key, fyremezzonine_manager_partner_catalog_level_meta_keys(), true);
    if ($level === false) {
        return '';
    }

    $rows = array();
    foreach (fyremezzonine_manager_get_partner_catalog() as $item) {
        if (($item['level'] ?? 'partner') !== $level) {
            continue;
        }

        $rows[] = ($item['name'] ?? '') . ' | ' . ($item['site'] ?? '') . ' | ' . ($item['logo_url'] ?? '');
    }

    return implode("\n", $rows);
}

function fyremezzonine_manager_latest_topic_template_conference_id($exclude_id = 0) {
    return fyremezzonine_manager_latest_template_conference_id('fyremezzonine_manager_conference_has_topic_template', $exclude_id);
}

function fyremezzonine_manager_render_topic_repeater($key, $label, $value = '') {
    $items = fyremezzonine_manager_parse_topic_rows($value);
    if (!$items) {
        $items = array(array('title' => '', 'image_url' => '', 'sections' => array()));
    }

    $render_row = static function($item) use ($key) {
        $sections = !empty($item['sections']) && is_array($item['sections']) ? array_values($item['sections']) : array();
        $render_section = static function($section = '') {
            ?>
            <div class="conference-topic-section-row" data-topic-section-row>
                <label>
                    <span>Название секции</span>
                    <input type="text" value="<?php echo esc_attr($section); ?>" data-topic-section-input placeholder="Например: автоматические системы пожаротушения">
                </label>
                <button type="button" class="button" data-topic-section-remove>Удалить секцию</button>
            </div>
            <?php
        };
        ?>
        <div class="conference-partner-row conference-topic-row" data-repeater-row>
            <div class="conference-partner-row-head">
                <strong class="conference-partner-row-title">Тема</strong>
                <button type="button" class="button conference-partner-remove" data-repeater-remove>Удалить</button>
            </div>
            <div class="conference-partner-fields">
                <label>
                    <span>Название темы</span>
                    <input type="text" name="<?php echo esc_attr($key); ?>_title[]" value="<?php echo esc_attr($item['title'] ?? ''); ?>" placeholder="Например: Предупреждение техногенных катастроф">
                </label>
                <div class="conference-topic-sections" data-topic-sections>
                    <div class="conference-topic-sections-head">
                        <strong>Секции этой темы</strong>
                        <small>Добавляйте секции отдельно. Если список пуст, сама тема считается единственной секцией.</small>
                    </div>
                    <div class="conference-topic-section-rows" data-topic-section-rows>
                        <?php foreach ($sections as $section) : ?>
                            <?php $render_section($section); ?>
                        <?php endforeach; ?>
                    </div>
                    <p class="conference-topic-sections-empty" data-topic-sections-empty <?php if ($sections) { echo 'hidden'; } ?>>Секции пока не добавлены.</p>
                    <button type="button" class="button conference-partner-add" data-topic-section-add>+ Добавить секцию</button>
                    <textarea class="conference-topic-sections-value" name="<?php echo esc_attr($key); ?>_sections[]" data-topic-sections-value hidden><?php echo esc_textarea(implode("\n", $sections)); ?></textarea>
                    <template>
                        <?php $render_section(''); ?>
                    </template>
                </div>
                <label>
                    <span>Изображение темы</span>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>_image_url[]" value="<?php echo esc_attr($item['image_url'] ?? ''); ?>">
                    <?php if (!empty($item['image_url'])) : ?>
                        <?php fyremezzonine_manager_render_uploaded_image_preview($item['image_url'], 'Изображение темы'); ?>
                        <small>Файл уже загружен. Чтобы заменить, выберите новый.</small>
                    <?php endif; ?>
                    <input type="file" name="<?php echo esc_attr($key); ?>_image_url_file[]" accept="image/*,.gif">
                </label>
            </div>
        </div>
        <?php
    };

    echo '<div class="conference-submission-field conference-partner-repeater conference-topic-repeater" data-repeater>';
    echo '<div class="conference-partner-repeater-head">';
    printf('<label>%s</label>', esc_html($label));
    echo '<p class="conference-submission-note">Добавьте столько тем, сколько нужно. Внутри каждой темы можно указать любое количество секций. Если секций нет, тема считается единственной секцией.</p>';
    echo '</div>';
    echo '<div class="conference-partner-rows" data-repeater-rows>';
    foreach ($items as $item) {
        $render_row($item);
    }
    echo '</div>';
    echo '<button type="button" class="button conference-partner-add" data-repeater-add>+ Добавить тему</button>';
    echo '<template data-repeater-template>';
    $render_row(array('title' => '', 'image_url' => '', 'sections' => array()));
    echo '</template>';
    echo '</div>';

    fyremezzonine_manager_render_partner_repeater_assets();
}

function fyremezzonine_manager_render_speaker_repeater($key, $label, $value = '') {
    $items = fyremezzonine_manager_parse_speaker_rows($value);
    if (!$items) {
        $items = array(array('name' => '', 'position' => '', 'direction' => '', 'quote' => '', 'photo_url' => ''));
    }

    $render_row = static function($item) use ($key) {
        ?>
        <div class="conference-partner-row conference-speaker-row" data-repeater-row>
            <div class="conference-partner-row-head">
                <strong class="conference-partner-row-title">Спикер</strong>
                <button type="button" class="button conference-partner-remove" data-repeater-remove>Удалить</button>
            </div>
            <div class="conference-partner-fields conference-speaker-fields">
                <label>
                    <span>Фото спикера</span>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>_photo_url[]" value="<?php echo esc_attr($item['photo_url'] ?? ''); ?>">
                    <?php if (!empty($item['photo_url'])) : ?>
                        <?php fyremezzonine_manager_render_uploaded_image_preview($item['photo_url'], 'Фото спикера'); ?>
                        <small>Файл уже загружен. Чтобы заменить, выберите новый.</small>
                    <?php endif; ?>
                    <input type="file" name="<?php echo esc_attr($key); ?>_photo_url_file[]" accept="image/*,.gif">
                </label>
                <label>
                    <span>Имя и фамилия</span>
                    <input type="text" name="<?php echo esc_attr($key); ?>_name[]" value="<?php echo esc_attr($item['name'] ?? ''); ?>" placeholder="Например: Иван Петров">
                </label>
                <label>
                    <span>Должность</span>
                    <input type="text" name="<?php echo esc_attr($key); ?>_position[]" value="<?php echo esc_attr($item['position'] ?? ''); ?>" placeholder="Например: руководитель направления">
                </label>
                <label>
                    <span>Направление научного доклада</span>
                    <input type="text" name="<?php echo esc_attr($key); ?>_direction[]" value="<?php echo esc_attr($item['direction'] ?? ''); ?>" placeholder="Например: мониторинг техногенных рисков">
                </label>
                <label>
                    <span>Выдержка из слов</span>
                    <textarea name="<?php echo esc_attr($key); ?>_quote[]" rows="3" placeholder="Короткая цитата или тезис выступления"><?php echo esc_textarea($item['quote'] ?? ''); ?></textarea>
                </label>
            </div>
        </div>
        <?php
    };

    echo '<div class="conference-submission-field conference-partner-repeater conference-speaker-repeater" data-repeater>';
    echo '<div class="conference-partner-repeater-head">';
    printf('<label>%s</label>', esc_html($label));
    echo '<p class="conference-submission-note">Добавьте спикеров конференции: фото, имя, должность, направление научного доклада и короткую цитату.</p>';
    echo '</div>';
    echo '<div class="conference-partner-rows" data-repeater-rows>';
    foreach ($items as $item) {
        $render_row($item);
    }
    echo '</div>';
    echo '<button type="button" class="button conference-partner-add" data-repeater-add>+ Добавить спикера</button>';
    echo '<template data-repeater-template>';
    $render_row(array('name' => '', 'position' => '', 'direction' => '', 'quote' => '', 'photo_url' => ''));
    echo '</template>';
    echo '</div>';

    fyremezzonine_manager_render_partner_repeater_assets();
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

    add_meta_box(
        'fyremezzonine_conference_lifecycle',
        'Управление конференцией',
        'fyremezzonine_manager_render_admin_lifecycle_box',
        'conference',
        'side',
        'high'
    );

    add_meta_box(
        'fyremezzonine_conference_history',
        'История изменений',
        'fyremezzonine_manager_render_admin_history_box',
        'conference',
        'normal',
        'low'
    );
}
add_action('add_meta_boxes', 'fyremezzonine_manager_add_meta_boxes');

function fyremezzonine_manager_render_admin_history_box($post) {
    echo fyremezzonine_manager_render_conference_timeline($post->ID);
    ?>
    <style>
        #fyremezzonine_conference_history .conference-submission-group { margin: 0; }
        #fyremezzonine_conference_history .conference-submission-section-head h2 { margin: 0 0 6px; }
        #fyremezzonine_conference_history .conference-submission-help { margin: 0 0 16px; color: #646970; }
        #fyremezzonine_conference_history .conference-audit-dates {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 18px;
        }
        #fyremezzonine_conference_history .conference-audit-dates div,
        #fyremezzonine_conference_history .conference-audit-list li {
            padding: 12px;
            border: 1px solid #dcdcde;
            border-radius: 6px;
            background: #f6f7f7;
        }
        #fyremezzonine_conference_history .conference-audit-dates dt { color: #646970; font-size: 12px; }
        #fyremezzonine_conference_history .conference-audit-dates dd { margin: 5px 0 0; font-weight: 700; }
        #fyremezzonine_conference_history .conference-audit-list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
        #fyremezzonine_conference_history .conference-audit-list li { display: grid; gap: 4px; margin: 0; }
        @media (max-width: 900px) {
            #fyremezzonine_conference_history .conference-audit-dates { grid-template-columns: 1fr 1fr; }
        }
    </style>
    <?php
}

function fyremezzonine_manager_render_admin_lifecycle_box($post) {
    $post_status = get_post_status($post);
    $lifecycle_status = fyremezzonine_manager_lifecycle_status($post->ID);
    $labels = fyremezzonine_manager_lifecycle_labels();
    $action_url = admin_url('admin-post.php');
    ?>
    <p><strong>Состояние:</strong> <?php echo esc_html($post_status === 'publish' ? ($labels[$lifecycle_status] ?? 'Будущая') : 'Черновик'); ?></p>
    <p>Сохраните несохранённые изменения перед изменением состояния конференции.</p>
    <input type="hidden" name="conference_id" value="<?php echo esc_attr($post->ID); ?>">
    <input type="hidden" id="fyremezzonine-admin-conference-action" name="conference_action" value="">
    <?php wp_nonce_field('fyremezzonine_conference_lifecycle_' . $post->ID); ?>
    <div class="fyremezzonine-admin-lifecycle-actions" style="display:grid;gap:8px;min-width:0">
        <a class="button fyremezzonine-admin-action-button" href="<?php echo esc_url(fyremezzonine_manager_conference_preview_url($post->ID)); ?>" target="_blank" rel="noopener"><strong>Предпросмотр</strong><small>Открыть страницу конференции</small></a>
        <?php if ($post_status === 'publish' && $lifecycle_status === 'current') : ?>
            <button class="button button-primary fyremezzonine-admin-action-button" type="submit" name="action" value="fyremezzonine_conference_lifecycle" formmethod="post" formaction="<?php echo esc_url($action_url); ?>" onclick="document.getElementById('fyremezzonine-admin-conference-action').value='complete'; return confirm('Завершить конференцию и показать на главной следующую?');"><strong>Завершить конференцию</strong><small>Перенести в завершённые</small></button>
        <?php endif; ?>
        <?php if ($post_status === 'publish' && $lifecycle_status !== 'completed') : ?>
            <button class="button fyremezzonine-admin-action-button" type="submit" name="action" value="fyremezzonine_conference_lifecycle" formmethod="post" formaction="<?php echo esc_url($action_url); ?>" onclick="document.getElementById('fyremezzonine-admin-conference-action').value='unpublish'; return confirm('Снять конференцию с публикации и вернуть в черновики?');"><strong>Снять с публикации</strong><small>Переместить в черновики</small></button>
        <?php endif; ?>
        <button class="button fyremezzonine-admin-action-button fyremezzonine-admin-delete-button" type="submit" name="action" value="fyremezzonine_conference_lifecycle" formmethod="post" formaction="<?php echo esc_url($action_url); ?>" onclick="document.getElementById('fyremezzonine-admin-conference-action').value='delete'; return confirm('Удалить конференцию? Она будет перемещена в корзину WordPress.');"><strong>Удалить конференцию</strong><small>Переместить в корзину</small></button>
    </div>
    <style>
        #fyremezzonine_conference_lifecycle .inside { min-width: 0; }
        #fyremezzonine_conference_lifecycle .fyremezzonine-admin-action-button {
            display: grid;
            gap: 2px;
            align-items: center;
            justify-items: center;
            width: 100%;
            max-width: 100%;
            min-height: 46px;
            height: auto;
            margin: 0;
            padding: 7px 10px;
            box-sizing: border-box;
            line-height: 1.25;
            white-space: normal;
            overflow-wrap: anywhere;
            text-align: center;
        }
        #fyremezzonine_conference_lifecycle .fyremezzonine-admin-action-button small {
            color: #646970;
            font-size: 11px;
            font-weight: 400;
        }
        #fyremezzonine_conference_lifecycle .button-primary.fyremezzonine-admin-action-button small {
            color: rgba(255, 255, 255, 0.82);
        }
        #fyremezzonine_conference_lifecycle .fyremezzonine-admin-delete-button {
            border-color: #b32d2e;
            color: #b32d2e;
            background: #fff;
        }
        #fyremezzonine_conference_lifecycle .fyremezzonine-admin-delete-button:hover {
            border-color: #8a2424;
            color: #8a2424;
            background: #fcf0f1;
        }
        #fyremezzonine_conference_lifecycle .fyremezzonine-admin-delete-button small { color: #8a2424; }
    </style>
    <?php
}

function fyremezzonine_manager_render_meta_box($post) {
    wp_nonce_field('fyremezzonine_manager_save_meta', 'fyremezzonine_manager_meta_nonce');

    echo '<p><em>Эти поля управляют главной страницей, страницей конференции, формой регистрации и атмосферой события. Изображения загружаются через кнопку выбора файла.</em></p>';

    $image_fields = fyremezzonine_manager_image_meta_keys();

    $hidden_fields = fyremezzonine_manager_hidden_editor_meta_keys();

    foreach (fyremezzonine_manager_meta_keys() as $key => $field) {
        if (in_array($key, $hidden_fields, true)) {
            continue;
        }

        $value = get_post_meta($post->ID, $key, true);
        if ($key === '_conference_venues' && !$value) {
            $value = fyremezzonine_manager_legacy_venue_value($post->ID);
        }
        if ($field['type'] === 'partners' && !$value && get_post_status($post->ID) === 'auto-draft') {
            $value = fyremezzonine_manager_partner_catalog_meta_value($key);
        }
        echo '<p>';
        printf('<label for="%1$s"><strong>%2$s</strong></label><br>', esc_attr($key), esc_html($field['label']));

        if (in_array($key, $image_fields, true)) {
            printf('<input type="hidden" id="%1$s" name="%1$s" value="%2$s">', esc_attr($key), esc_attr($value));
            if ($value) {
                fyremezzonine_manager_render_uploaded_image_preview($value, $field['label']);
                printf('<span class="description">Текущий файл: <a href="%1$s" target="_blank" rel="noopener">открыть</a></span><br>', esc_url($value));
            }
            printf(
                '<label for="%1$s_file">Выбрать файл</label><br><input type="file" id="%1$s_file" name="%1$s_file" accept="image/*,.gif" class="widefat">',
                esc_attr($key)
            );
            echo '</p>';
            continue;
        }

        if ($field['type'] === 'partners') {
            echo '</p>';
            fyremezzonine_manager_render_partner_repeater($key, $field['label'], $value);
            continue;
        }

        if ($field['type'] === 'venues') {
            echo '</p>';
            fyremezzonine_manager_render_venue_repeater($key, $field['label'], $value);
            continue;
        }

        if ($field['type'] === 'topics') {
            echo '</p>';
            fyremezzonine_manager_render_topic_repeater($key, $field['label'], $value);
            continue;
        }

        if ($field['type'] === 'speakers') {
            echo '</p>';
            fyremezzonine_manager_render_speaker_repeater($key, $field['label'], $value);
            continue;
        }

        if ($field['type'] === 'select') {
            printf('<select id="%1$s" name="%1$s" class="widefat">', esc_attr($key));
            foreach ($field['options'] as $option_value => $option_label) {
                printf(
                    '<option value="%1$s"%3$s>%2$s</option>',
                    esc_attr($option_value),
                    esc_html($option_label),
                    selected($value ?: 'classic', $option_value, false)
                );
            }
            echo '</select>';
        } elseif ($field['type'] === 'textarea') {
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

    $hidden_fields = fyremezzonine_manager_hidden_editor_meta_keys();

    foreach (fyremezzonine_manager_meta_keys() as $key => $field) {
        if (in_array($key, $hidden_fields, true)) {
            continue;
        }

        if ($field['type'] === 'partners') {
            update_post_meta($post_id, $key, fyremezzonine_manager_partner_rows_from_request($key, $post_id));
            continue;
        }
        if ($field['type'] === 'topics') {
            update_post_meta($post_id, $key, fyremezzonine_manager_topic_rows_from_request($key, $post_id));
            continue;
        }
        if ($field['type'] === 'speakers') {
            update_post_meta($post_id, $key, fyremezzonine_manager_speaker_rows_from_request($key, $post_id));
            continue;
        }
        if ($field['type'] === 'venues') {
            update_post_meta($post_id, $key, fyremezzonine_manager_venue_rows_from_request($key));
            continue;
        }

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
        } elseif ($field['type'] === 'select') {
            $value = fyremezzonine_manager_sanitize_select_value($raw_value, $field);
        } else {
            $value = sanitize_text_field($raw_value);
        }

        update_post_meta($post_id, $key, $value);
    }
}
add_action('save_post_conference', 'fyremezzonine_manager_save_meta');

function fyremezzonine_manager_post_edit_form_tag() {
    global $post;

    if ($post && $post->post_type === 'conference') {
        echo ' enctype="multipart/form-data"';
    }
}
add_action('post_edit_form_tag', 'fyremezzonine_manager_post_edit_form_tag');

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
            'title' => 'Даты и места проведения',
            'description' => 'Даты выводятся посетителям как информация. Статус конференции и регистрация управляются редактором отдельно.',
            'fields' => array(
                '_conference_start_date',
                '_conference_end_date',
                '_conference_registration_deadline',
                '_conference_visual_theme',
                '_conference_venues',
            ),
        ),
        'content' => array(
            'title' => 'Содержимое страницы',
            'description' => 'Тексты для тематических блоков, материалов и раздела "О конференции".',
            'fields' => array(
                '_conference_topic_intro',
                '_conference_topics',
                '_conference_about_title',
                '_conference_about_lead',
                '_conference_benefits',
                '_conference_speakers',
            ),
        ),
        'links' => array(
            'title' => 'Кнопки и первый экран',
            'description' => 'Здесь настраиваются кнопки в верхней части сайта и фон первого экрана конференции.',
            'tips' => array(
                'Ссылка на программу показывает кнопку "Программа конференции".',
                'Ссылка на чат показывается участнику сразу после успешной регистрации на текущую конференцию.',
                'Фон первого экрана можно оставить пустым или загрузить фото/GIF через выбор файла.',
            ),
            'fields' => array(
                '_conference_program_url',
                '_conference_chat_1_url',
                '_conference_hero_image_url',
            ),
        ),
        'venue_photos' => array(
            'title' => 'Место проведения и фото',
            'description' => 'Этот блок выводится рядом с Яндекс.Картой и сразу под ней. Если фото не выбраны, галерея не появится.',
            'tips' => array(
                'Заголовок и описание объясняют площадку проведения конференции.',
                'Основное и дополнительное фото показываются под картой; можно загрузить только одно фото.',
                'Все уже загруженные изображения отображаются в форме как превью.',
            ),
            'fields' => array(
                '_conference_venue_heading',
                '_conference_venue_intro',
                '_conference_venue_image_url',
                '_conference_collage_image_url',
            ),
        ),
        'partners' => array(
            'title' => 'Спонсоры и партнеры',
            'description' => 'Добавляйте организации карточками: название, сайт и логотип. Список можно оставить пустым.',
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

    if ($type === 'partners') {
        return sanitize_textarea_field($raw_value);
    }

    if ($type === 'topics') {
        return sanitize_textarea_field($raw_value);
    }

    if ($type === 'speakers') {
        return sanitize_textarea_field($raw_value);
    }

    if ($type === 'venues') {
        return sanitize_textarea_field($raw_value);
    }

    if ($type === 'textarea') {
        return sanitize_textarea_field($raw_value);
    }

    if ($type === 'url') {
        return esc_url_raw($raw_value);
    }

    return sanitize_text_field($raw_value);
}

function fyremezzonine_manager_sanitize_select_value($value, $field) {
    $raw_value = sanitize_key(wp_unslash($value));

    return isset($field['options'][$raw_value]) ? $raw_value : 'classic';
}

function fyremezzonine_manager_submission_placeholder($name) {
    $placeholders = array(
        'conference_title' => 'Например: Предупреждение техногенных катастроф',
        'conference_excerpt' => 'Коротко опишите конференцию в 1-2 предложениях',
        'conference_content' => 'Полное описание, которое увидит посетитель',
        '_conference_city' => 'Например: Оренбург',
        '_conference_venue' => 'Например: испытательный полигон ВНИИПО',
        '_conference_route_address' => 'Например: Нижняя Павловка, Полигонная улица, д. 1',
        '_conference_route_directions' => 'Кратко опишите, как добраться до места проведения',
        '_conference_visual_theme' => '',
        '_conference_program_url' => 'https://...',
        '_conference_chat_1_url' => 'https://...',
        '_conference_hero_image_url' => 'https://.../hero.gif',
        '_conference_topic_1_image_url' => 'https://.../image.jpg',
        '_conference_topic_2_image_url' => 'https://.../image.jpg',
        '_conference_topic_3_image_url' => 'https://.../image.jpg',
        '_conference_map_lat' => '51.768199',
        '_conference_map_lon' => '55.096955',
        '_conference_venue_image_url' => 'https://.../photo.jpg',
        '_conference_collage_image_url' => 'https://.../photo.jpg',
    );

    return $placeholders[$name] ?? '';
}

function fyremezzonine_manager_render_submission_field($name, $field, $value = '') {
    $required = !empty($field['required']) ? ' required' : '';
    $label = isset($field['label']) ? $field['label'] : $name;
    $type = isset($field['type']) ? $field['type'] : 'text';
    $is_image_field = in_array($name, fyremezzonine_manager_image_meta_keys(), true);
    $placeholder = fyremezzonine_manager_submission_placeholder($name);

    if ($type === 'partners') {
        fyremezzonine_manager_render_partner_repeater($name, $label, $value);
        return;
    }

    if ($type === 'topics') {
        fyremezzonine_manager_render_topic_repeater($name, $label, $value);
        return;
    }

    if ($type === 'speakers') {
        fyremezzonine_manager_render_speaker_repeater($name, $label, $value);
        return;
    }

    if ($type === 'venues') {
        fyremezzonine_manager_render_venue_repeater($name, $label, $value);
        return;
    }

    echo '<p class="conference-submission-field">';
    printf('<label for="%1$s">%2$s</label>', esc_attr($name), esc_html($label));

    if ($is_image_field) {
        printf('<input id="%1$s" type="hidden" name="%1$s" value="%2$s">', esc_attr($name), esc_attr($value));
        if ($value) {
            fyremezzonine_manager_render_uploaded_image_preview($value, $label);
            printf('<span class="conference-submission-note">Текущий файл уже загружен: <a href="%1$s" target="_blank" rel="noopener">открыть</a>. Чтобы заменить его, выберите новый файл ниже.</span>', esc_url($value));
        } else {
            $empty_image_note = $name === '_conference_hero_image_url'
                ? 'Выберите изображение с компьютера. Для заставки первого экрана можно выбрать GIF.'
                : 'Выберите фотографию с компьютера. Если поле оставить пустым, это изображение на сайте не появится.';
            printf('<span class="conference-submission-note">%s</span>', esc_html($empty_image_note));
        }
        printf(
            '<input id="%1$s_file" type="file" name="%1$s_file" accept="image/*,.gif">',
            esc_attr($name)
        );
        echo '</p>';
        return;
    }

    if ($type === 'select') {
        printf('<select id="%1$s" name="%1$s"%2$s>', esc_attr($name), $required);
        foreach ($field['options'] as $option_value => $option_label) {
            printf(
                '<option value="%1$s"%3$s>%2$s</option>',
                esc_attr($option_value),
                esc_html($option_label),
                selected($value ?: 'classic', $option_value, false)
            );
        }
        echo '</select>';
    } elseif ($type === 'textarea') {
        printf(
            '<textarea id="%1$s" name="%1$s" rows="4" placeholder="%4$s"%3$s>%2$s</textarea>',
            esc_attr($name),
            esc_textarea($value),
            $required,
            esc_attr($placeholder)
        );
    } else {
        printf(
            '<input id="%1$s" type="%2$s" name="%1$s" value="%3$s" placeholder="%5$s"%4$s>',
            esc_attr($name),
            esc_attr($type),
            esc_attr($value),
            $required,
            esc_attr($placeholder)
        );
    }

    echo '</p>';
}

function fyremezzonine_manager_handle_conference_submission() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['fyremezzonine_conference_submission_nonce'])) {
        return '';
    }

    if (!fyremezzonine_manager_can_manage_conferences()) {
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
    if ($editing_post_id && fyremezzonine_manager_lifecycle_status($editing_post_id) === 'completed') {
        return '<div class="registration-message registration-error">Завершенную конференцию редактировать нельзя. Она сохранена в архиве без возможности изменения.</div>';
    }
    if ($editing_post_id && (!get_post($editing_post_id) || !current_user_can('edit_post', $editing_post_id))) {
        return '<div class="registration-message registration-error">У вас нет прав для редактирования этой конференции.</div>';
    }

    $submission_action = isset($_POST['conference_submission_action']) ? sanitize_key(wp_unslash($_POST['conference_submission_action'])) : 'save_draft';
    $current_status = $editing_post_id ? get_post_status($editing_post_id) : '';
    $publish_now = $submission_action === 'publish' && $editing_post_id;
    $post_status = $publish_now || $current_status === 'publish' ? 'publish' : 'draft';

    $post_data = array(
        'post_type' => 'conference',
        'post_status' => $post_status,
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

    $GLOBALS['fyremezzonine_manager_last_saved_conference_id'] = absint($post_id);

    foreach (fyremezzonine_manager_meta_keys() as $key => $field) {
        if ($field['type'] === 'partners') {
            update_post_meta($post_id, $key, fyremezzonine_manager_partner_rows_from_request($key, $post_id));
            continue;
        }
        if ($field['type'] === 'topics') {
            update_post_meta($post_id, $key, fyremezzonine_manager_topic_rows_from_request($key, $post_id));
            continue;
        }
        if ($field['type'] === 'speakers') {
            update_post_meta($post_id, $key, fyremezzonine_manager_speaker_rows_from_request($key, $post_id));
            continue;
        }
        if ($field['type'] === 'venues') {
            update_post_meta($post_id, $key, fyremezzonine_manager_venue_rows_from_request($key));
            continue;
        }

        $uploaded_url = in_array($key, fyremezzonine_manager_image_meta_keys(), true) ? fyremezzonine_manager_upload_image_for_field($key, $post_id) : '';
        if ($uploaded_url) {
            $value = $uploaded_url;
        } elseif (isset($_POST[$key])) {
            $value = $field['type'] === 'select'
                ? fyremezzonine_manager_sanitize_select_value($_POST[$key], $field)
                : fyremezzonine_manager_sanitize_submission_value($_POST[$key], $field['type']);
        } else {
            $value = '';
        }
        update_post_meta($post_id, $key, $value);
    }

    $preview_link = fyremezzonine_manager_conference_preview_url($post_id);
    $editor_link = add_query_arg('conference_id', $post_id, fyremezzonine_manager_editor_page_url('conferences'));
    $view_link = get_permalink($post_id);
    $is_published = get_post_status($post_id) === 'publish';
    $is_editing = (bool) $editing_post_id;
    $message = $is_editing
        ? '<strong>Изменения успешно сохранены</strong><p>Информация о конференции обновлена.</p>'
        : ($is_published
            ? 'Конференция опубликована и применена на сайте.'
            : 'Черновик конференции сохранен. Откройте предпросмотр, проверьте страницу и затем нажмите "Опубликовать конференцию".');

    $links = array();
    if ($is_editing) {
        $links[] = '<a class="button button-outline" href="' . esc_url(fyremezzonine_manager_editor_page_url('conferences')) . '">К списку конференций</a>';
    } elseif ($editor_link) {
        $links[] = '<a href="' . esc_url($editor_link) . '">Продолжить редактирование</a>';
    }
    if ($preview_link) {
        $links[] = '<a class="button button-blue" href="' . esc_url($preview_link) . '" target="_blank" rel="noopener">Предпросмотр</a>';
    }
    if ($is_published && $view_link) {
        $links[] = '<a class="button button-red" href="' . esc_url($view_link) . '">Посмотреть на сайте</a>';
    }

    if ($links) {
        $message .= $is_editing
            ? '<div class="conference-submission-result-actions">' . implode('', $links) . '</div>'
            : ' ' . implode(' | ', $links);
    }

    $classes = $is_editing
        ? 'registration-message registration-success registration-complete conference-submission-result'
        : 'registration-message registration-success';

    return '<div class="' . esc_attr($classes) . '">' . wp_kses_post($message) . '</div>';
}

function fyremezzonine_manager_conference_preview_url($post_id) {
    return add_query_arg('conference_id', absint($post_id), fyremezzonine_manager_editor_page_url('conference-preview'));
}

function fyremezzonine_manager_conference_submission_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="registration-message registration-error">Войдите в WordPress, чтобы создать конференцию.</div>';
    }

    if (!fyremezzonine_manager_can_manage_conferences()) {
        return '<div class="registration-message registration-error">У вашей учетной записи нет прав для создания конференций.</div>';
    }

    $message = fyremezzonine_manager_handle_conference_submission();
    $lifecycle_notice = isset($_GET['lifecycle_notice']) ? sanitize_key(wp_unslash($_GET['lifecycle_notice'])) : '';
    if ($lifecycle_notice === 'unpublished') {
        $message = '<div class="registration-message registration-success">Конференция снята с публикации и сохранена в черновиках.</div>' . $message;
    } elseif ($lifecycle_notice === 'deleted') {
        $message = '<div class="registration-message registration-success">Конференция перемещена в корзину WordPress.</div>' . $message;
    } elseif ($lifecycle_notice === 'invalid') {
        $message = '<div class="registration-message registration-error">Действие недоступно для текущего состояния конференции.</div>' . $message;
    }
    $editing_conference_id = isset($_GET['conference_id']) ? absint($_GET['conference_id']) : 0;
    if (isset($_POST['conference_id'])) {
        $editing_conference_id = absint($_POST['conference_id']);
    }
    if (!empty($GLOBALS['fyremezzonine_manager_last_saved_conference_id'])) {
        $editing_conference_id = absint($GLOBALS['fyremezzonine_manager_last_saved_conference_id']);
    }

    $editing_submission_succeeded = !empty($GLOBALS['fyremezzonine_manager_last_saved_conference_id'])
        && !empty($_POST['conference_id']);
    if ($editing_submission_succeeded) {
        return $message;
    }

    if ($editing_conference_id && get_post_type($editing_conference_id) === 'conference' && fyremezzonine_manager_lifecycle_status($editing_conference_id) === 'completed') {
        return '<div class="registration-message registration-closed"><strong>Конференция завершена</strong><br>Редактирование завершенных конференций заблокировано. Информация доступна только для просмотра в архиве.</div>';
    }

    if ($editing_conference_id && (!get_post($editing_conference_id) || !current_user_can('edit_post', $editing_conference_id))) {
        return '<div class="registration-message registration-error">У вас нет прав для редактирования этой конференции.</div>';
    }

    $editor_path = trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    if (!$editing_conference_id && in_array($editor_path, array('editor/conferences', 'editor/edit-conference'), true)) {
        return $message . fyremezzonine_manager_render_edit_picker();
    }

    $meta_fields = fyremezzonine_manager_meta_keys();
    $groups = fyremezzonine_manager_submission_field_groups();
    $current_status = $editing_conference_id ? get_post_status($editing_conference_id) : '';
    $status_object = $current_status ? get_post_status_object($current_status) : null;
    $status_label = $status_object ? $status_object->label : $current_status;
    $preview_link = $editing_conference_id ? fyremezzonine_manager_conference_preview_url($editing_conference_id) : '';
    $is_published = $current_status === 'publish';
    $lifecycle_status = $editing_conference_id ? fyremezzonine_manager_lifecycle_status($editing_conference_id) : 'upcoming';
    $lifecycle_labels = fyremezzonine_manager_lifecycle_labels();
    $partner_catalog_count = (!$editing_conference_id && $_SERVER['REQUEST_METHOD'] !== 'POST')
        ? count(fyremezzonine_manager_get_partner_catalog())
        : 0;
    $topic_template_conference_id = (!$editing_conference_id && $_SERVER['REQUEST_METHOD'] !== 'POST')
        ? fyremezzonine_manager_latest_topic_template_conference_id()
        : 0;
    $GLOBALS['fyremezzonine_manager_topic_template_conference_id'] = $topic_template_conference_id;

    ob_start();
    echo wp_kses_post($message);
    if ($editing_conference_id) {
        echo fyremezzonine_manager_render_lifecycle_controls($editing_conference_id);
    }
    ?>
    <form class="conference-submission-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('fyremezzonine_create_conference', 'fyremezzonine_conference_submission_nonce'); ?>
        <?php if ($editing_conference_id) : ?>
            <input type="hidden" name="conference_id" value="<?php echo esc_attr($editing_conference_id); ?>">
        <?php endif; ?>
        <div class="registration-conference-title">
            <span><?php echo $editing_conference_id ? 'Редактирование конференции' : 'Новая конференция'; ?></span>
            <strong><?php echo $editing_conference_id ? esc_html(get_the_title($editing_conference_id)) : 'Заполните форму, как анкету'; ?></strong>
            <?php if ($editing_conference_id) : ?>
                <div class="conference-submission-state">
                    <span>Статус: <?php echo esc_html($status_label); ?></span>
                    <?php if ($is_published) : ?>
                        <span>На сайте: <?php echo esc_html($lifecycle_labels[$lifecycle_status] ?? 'Будущая'); ?></span>
                    <?php endif; ?>
                    <?php if ($preview_link) : ?>
                        <a class="button button-blue" href="<?php echo esc_url($preview_link); ?>" target="_blank" rel="noopener">Предпросмотр</a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <p class="conference-submission-note">Сначала сохраните черновик. После этого появится предпросмотр и кнопка публикации.</p>
                <?php if ($partner_catalog_count) : ?>
                    <p class="conference-submission-note">Организаторы и партнеры автоматически заполнены из раздела «Партнерство»: <?php echo esc_html($partner_catalog_count); ?>. Для изменения общего списка откройте «Редактор → Партнерство».</p>
                <?php endif; ?>
                <?php if ($topic_template_conference_id) : ?>
                    <p class="conference-submission-note">Темы конференции автоматически заполнены по прошлой конференции: <?php echo esc_html(get_the_title($topic_template_conference_id)); ?>. Их можно изменить, удалить или дополнить.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php foreach ($groups as $group_key => $group) : ?>
            <fieldset class="conference-submission-group conference-submission-group-<?php echo esc_attr($group_key); ?>">
                <div class="conference-submission-section-head">
                    <h2><?php echo esc_html($group['title']); ?></h2>
                    <p class="conference-submission-help"><?php echo esc_html($group['description']); ?></p>
                    <?php if (!empty($group['tips'])) : ?>
                        <ul class="conference-submission-tips">
                            <?php foreach ($group['tips'] as $tip) : ?>
                                <li><?php echo esc_html($tip); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
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

        <p class="conference-submission-actions">
            <button class="button button-blue" type="submit" name="conference_submission_action" value="save_draft">
                <?php echo $is_published ? 'Сохранить изменения' : 'Сохранить черновик'; ?>
            </button>
            <?php if ($preview_link) : ?>
                <a class="button button-outline" href="<?php echo esc_url($preview_link); ?>" target="_blank" rel="noopener">Предпросмотр</a>
            <?php endif; ?>
            <?php if ($editing_conference_id) : ?>
                <button class="button button-red" type="submit" name="conference_submission_action" value="publish">
                    <?php echo $is_published ? 'Сохранить опубликованную' : 'Опубликовать конференцию'; ?>
                </button>
            <?php endif; ?>
        </p>
    </form>
    <?php if ($editing_conference_id) : ?>
        <?php echo fyremezzonine_manager_render_conference_timeline($editing_conference_id); ?>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('conference_submission_form', 'fyremezzonine_manager_conference_submission_shortcode');

function fyremezzonine_manager_editor_page_url($page = 'conferences') {
    return home_url('/editor/' . trim($page, '/') . '/');
}

function fyremezzonine_manager_render_conference_timeline($conference_id) {
    global $wpdb;

    $conference = get_post($conference_id);
    if (!$conference) {
        return '';
    }

    $prepared_at = get_post_meta($conference_id, '_conference_preparation_started_at', true) ?: $conference->post_date;
    $published_at = get_post_meta($conference_id, '_conference_published_at', true);
    $completed_at = get_post_meta($conference_id, '_conference_completed_at', true);
    $events = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT log.*, users.display_name FROM ' . fyremezzonine_manager_activity_table_name() . ' log LEFT JOIN ' . $wpdb->users . ' users ON users.ID = log.user_id WHERE log.conference_id = %d ORDER BY log.created_at DESC, log.id DESC LIMIT 20',
            $conference_id
        )
    );

    ob_start();
    ?>
    <section class="conference-audit-card" aria-labelledby="conference-audit-title">
        <div class="conference-submission-section-head">
            <h2 id="conference-audit-title">История конференции</h2>
            <p class="conference-submission-help">Создание, публикация, изменения и действия редакторов фиксируются автоматически.</p>
        </div>
        <dl class="conference-audit-dates">
            <div><dt>Начало подготовки</dt><dd><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($prepared_at))); ?></dd></div>
            <div><dt>Первая публикация</dt><dd><?php echo $published_at ? esc_html(date_i18n('d.m.Y H:i', strtotime($published_at))) : 'Ещё не опубликована'; ?></dd></div>
            <div><dt>Последнее изменение</dt><dd><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($conference->post_modified))); ?></dd></div>
            <div><dt>Завершение</dt><dd><?php echo $completed_at ? esc_html(date_i18n('d.m.Y H:i', strtotime($completed_at))) : 'Не завершена'; ?></dd></div>
        </dl>
        <?php if ($events) : ?>
            <ol class="conference-audit-list">
                <?php foreach ($events as $event) : ?>
                    <li>
                        <time datetime="<?php echo esc_attr(mysql2date('c', $event->created_at)); ?>"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($event->created_at))); ?></time>
                        <strong><?php echo esc_html($event->message ?: $event->action); ?></strong>
                        <span><?php echo esc_html($event->display_name ?: 'Система'); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else : ?>
            <p class="conference-submission-note">Журнал начнет заполняться при следующем изменении конференции.</p>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

function fyremezzonine_manager_render_lifecycle_controls($conference_id) {
    $post_status = get_post_status($conference_id);
    $lifecycle_status = fyremezzonine_manager_lifecycle_status($conference_id);
    $is_published = $post_status === 'publish';
    $is_current = $lifecycle_status === 'current';
    $is_completed = $lifecycle_status === 'completed';
    $action_url = admin_url('admin-post.php');
    ob_start();
    ?>
    <section class="conference-lifecycle-card" aria-labelledby="conference-lifecycle-title">
        <div>
            <h2 id="conference-lifecycle-title">Управление публикацией</h2>
            <p>
                <?php
                if ($is_completed) {
                    echo 'Конференция завершена и доступна в архиве только для просмотра.';
                } elseif ($is_current) {
                    echo 'Эта конференция сейчас показывается на главной, и только на неё открыта регистрация.';
                } elseif ($is_published) {
                    echo 'Эта конференция опубликована в разделе будущих и ожидает своей очереди.';
                } else {
                    echo 'Конференция сохранена как черновик и не видна посетителям сайта.';
                }
                ?>
            </p>
        </div>
        <div class="conference-lifecycle-actions">
            <?php if ($is_current && !$is_completed) : ?>
                <form method="post" action="<?php echo esc_url($action_url); ?>" onsubmit="return confirm('Завершить конференцию и показать на главной следующую?');">
                    <input type="hidden" name="action" value="fyremezzonine_conference_lifecycle">
                    <input type="hidden" name="conference_id" value="<?php echo esc_attr($conference_id); ?>">
                    <input type="hidden" name="conference_action" value="complete">
                    <?php wp_nonce_field('fyremezzonine_conference_lifecycle_' . $conference_id); ?>
                    <button class="button button-red" type="submit">Завершить конференцию</button>
                </form>
            <?php endif; ?>
            <?php if ($is_published && !$is_completed) : ?>
                <form method="post" action="<?php echo esc_url($action_url); ?>" onsubmit="return confirm('Снять конференцию с публикации и вернуть в черновики?');">
                    <input type="hidden" name="action" value="fyremezzonine_conference_lifecycle">
                    <input type="hidden" name="conference_id" value="<?php echo esc_attr($conference_id); ?>">
                    <input type="hidden" name="conference_action" value="unpublish">
                    <?php wp_nonce_field('fyremezzonine_conference_lifecycle_' . $conference_id); ?>
                    <button class="button button-outline conference-secondary-action" type="submit"><strong>Снять с публикации</strong><small>Переместить в черновики</small></button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url($action_url); ?>" onsubmit="return confirm('Удалить конференцию? Она будет перемещена в корзину WordPress.');">
                <input type="hidden" name="action" value="fyremezzonine_conference_lifecycle">
                <input type="hidden" name="conference_id" value="<?php echo esc_attr($conference_id); ?>">
                <input type="hidden" name="conference_action" value="delete">
                <?php wp_nonce_field('fyremezzonine_conference_lifecycle_' . $conference_id); ?>
                <button class="button conference-delete-button conference-secondary-action" type="submit"><strong>Удалить конференцию</strong><small>Переместить в корзину</small></button>
            </form>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function fyremezzonine_manager_handle_lifecycle_action() {
    $conference_id = isset($_POST['conference_id']) ? absint($_POST['conference_id']) : 0;
    $conference_action = isset($_POST['conference_action']) ? sanitize_key(wp_unslash($_POST['conference_action'])) : '';

    if (!$conference_id || get_post_type($conference_id) !== 'conference' || !fyremezzonine_manager_can_manage_conferences()) {
        wp_die('Недостаточно прав для управления конференцией.', 403);
    }

    check_admin_referer('fyremezzonine_conference_lifecycle_' . $conference_id);

    if ($conference_action === 'delete') {
        fyremezzonine_manager_log_activity($conference_id, 'deleted', 'Конференция перемещена в корзину редактором');
        $trashed = wp_trash_post($conference_id);
        $notice = $trashed ? 'deleted' : 'invalid';
        wp_safe_redirect(add_query_arg('lifecycle_notice', $notice, fyremezzonine_manager_editor_page_url('conferences')));
        exit;
    }

    if (fyremezzonine_manager_lifecycle_status($conference_id) === 'completed') {
        wp_safe_redirect(add_query_arg(array('conference_id' => $conference_id, 'lifecycle_notice' => 'already_completed'), fyremezzonine_manager_editor_page_url('conferences')));
        exit;
    }

    if ($conference_action === 'unpublish' && get_post_status($conference_id) === 'publish') {
        wp_update_post(array('ID' => $conference_id, 'post_status' => 'draft'));
        wp_safe_redirect(add_query_arg(array('conference_id' => $conference_id, 'lifecycle_notice' => 'unpublished'), fyremezzonine_manager_editor_page_url('conferences')));
        exit;
    }

    if ($conference_action === 'complete' && get_post_status($conference_id) === 'publish' && fyremezzonine_manager_lifecycle_status($conference_id) === 'current') {
        update_post_meta($conference_id, '_conference_lifecycle_status', 'completed');
        update_post_meta($conference_id, '_conference_completed_at', current_time('mysql'));
        fyremezzonine_manager_log_activity($conference_id, 'completed', 'Конференция завершена редактором');
        fyremezzonine_manager_promote_next_conference($conference_id);
        wp_safe_redirect(add_query_arg('lifecycle_notice', 'completed', get_post_type_archive_link('conference')));
        exit;
    }

    wp_safe_redirect(add_query_arg(array('conference_id' => $conference_id, 'lifecycle_notice' => 'invalid'), fyremezzonine_manager_editor_page_url('conferences')));
    exit;
}
add_action('admin_post_fyremezzonine_conference_lifecycle', 'fyremezzonine_manager_handle_lifecycle_action');

function fyremezzonine_manager_submission_value($field_name, $conference_id = 0) {
    if (isset($_POST[$field_name])) {
        return wp_unslash($_POST[$field_name]);
    }

    if (!$conference_id) {
        if (in_array($field_name, fyremezzonine_manager_partner_template_meta_keys(), true)) {
            return fyremezzonine_manager_partner_catalog_meta_value($field_name);
        }

        $topic_template_conference_id = !empty($GLOBALS['fyremezzonine_manager_topic_template_conference_id'])
            ? absint($GLOBALS['fyremezzonine_manager_topic_template_conference_id'])
            : 0;

        if ($topic_template_conference_id && in_array($field_name, fyremezzonine_manager_topic_template_meta_keys(), true)) {
            return get_post_meta($topic_template_conference_id, $field_name, true);
        }

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

    $value = get_post_meta($conference_id, $field_name, true);
    if ($field_name === '_conference_venues' && !$value) {
        return fyremezzonine_manager_legacy_venue_value($conference_id);
    }

    return $value;
}

function fyremezzonine_manager_render_edit_picker() {
    $conferences = fyremezzonine_manager_get_conference_options();
    $groups = array(
        'current' => array('title' => 'Проходит сейчас', 'items' => array()),
        'upcoming' => array('title' => 'Будущие конференции', 'items' => array()),
        'draft' => array('title' => 'Черновики', 'items' => array()),
        'completed' => array('title' => 'Завершённые', 'items' => array()),
    );

    foreach ($conferences as $conference) {
        $post_status = get_post_status($conference);
        $lifecycle_status = fyremezzonine_manager_lifecycle_status($conference->ID);

        if ($lifecycle_status === 'completed') {
            $groups['completed']['items'][] = $conference;
        } elseif ($post_status !== 'publish') {
            $groups['draft']['items'][] = $conference;
        } elseif ($lifecycle_status === 'current') {
            $groups['current']['items'][] = $conference;
        } else {
            $groups['upcoming']['items'][] = $conference;
        }
    }

    ob_start();
    ?>
    <div class="conference-editor-picker">
        <div class="conference-editor-picker-toolbar">
            <div>
                <h2>Управление конференциями</h2>
                <p>Выберите конференцию для редактирования или создайте новую.</p>
            </div>
            <a class="button button-red" href="<?php echo esc_url(fyremezzonine_manager_editor_page_url('new-conference')); ?>">Создать новую</a>
        </div>
        <?php if ($conferences) : ?>
            <?php foreach ($groups as $group_key => $group) : ?>
                <?php if (!$group['items']) { continue; } ?>
                <section class="conference-editor-picker-group conference-editor-picker-group-<?php echo esc_attr($group_key); ?>">
                    <div class="conference-editor-picker-group-head">
                        <h3><?php echo esc_html($group['title']); ?></h3>
                        <span><?php echo esc_html(count($group['items'])); ?></span>
                    </div>
                    <div class="conference-editor-picker-list">
                        <?php foreach ($group['items'] as $conference) : ?>
                            <?php
                            $start_date = fyremezzonine_manager_format_date(get_post_meta($conference->ID, '_conference_start_date', true));
                            $end_date = fyremezzonine_manager_format_date(get_post_meta($conference->ID, '_conference_end_date', true));
                            $date_label = $start_date;
                            if ($end_date && $end_date !== $start_date) {
                                $date_label .= ' - ' . $end_date;
                            }
                            if (!$date_label) {
                                $date_label = 'Дата не указана';
                            }
                            $is_completed = $group_key === 'completed';
                            $status_label = $group_key === 'draft' ? 'Черновик' : ($is_completed ? 'Только просмотр' : 'Редактировать');
                            ?>
                            <?php if ($is_completed) : ?>
                                <div class="conference-editor-picker-item conference-editor-picker-item-locked">
                            <?php else : ?>
                                <a class="conference-editor-picker-item" href="<?php echo esc_url(add_query_arg('conference_id', $conference->ID, fyremezzonine_manager_editor_page_url('conferences'))); ?>">
                            <?php endif; ?>
                                <span class="conference-editor-picker-content">
                                    <strong><?php echo esc_html(get_the_title($conference)); ?></strong>
                                    <small><?php echo esc_html($date_label); ?></small>
                                </span>
                                <span class="conference-editor-picker-status"><?php echo esc_html($status_label); ?></span>
                            <?php if ($is_completed) : ?>
                                </div>
                            <?php else : ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="conference-editor-picker-empty">
                <strong>Конференций пока нет</strong>
                <p>Создайте первую конференцию и сохраните её как черновик.</p>
            </div>
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
    if (absint($conference_id) !== fyremezzonine_manager_current_conference_id() || get_post_status($conference_id) !== 'publish') {
        return true;
    }

    $deadline = get_post_meta($conference_id, '_conference_registration_deadline', true);

    return $deadline && $deadline < current_time('Y-m-d');
}

function fyremezzonine_manager_closed_registration_message($conference_id) {
    if (absint($conference_id) !== fyremezzonine_manager_current_conference_id() || get_post_status($conference_id) !== 'publish') {
        return '<div class="registration-message registration-closed"><strong>Регистрация недоступна</strong><br>Регистрация открыта только на конференцию, которая сейчас показывается на главной странице.</div>';
    }

    $deadline = get_post_meta($conference_id, '_conference_registration_deadline', true);
    $deadline_text = fyremezzonine_manager_format_date($deadline);

    $text = $deadline_text ? 'Дедлайн регистрации: ' . $deadline_text . '.' : 'Прием заявок на эту конференцию завершен.';

    return '<div class="registration-message registration-closed"><strong>Регистрация закрыта</strong><br>' . esc_html($text) . '</div>';
}

function fyremezzonine_manager_privacy_policy_url() {
    $policy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';

    return $policy_url ?: home_url('/privacy-policy/');
}

function fyremezzonine_manager_max_privacy_policy_url() {
    return 'https://legal.max.ru/pp';
}

function fyremezzonine_manager_privacy_policy_content() {
    return <<<'HTML'
<p><strong>Редакция от 20 июля 2026 года</strong></p>
<p>Настоящая Политика определяет порядок обработки и защиты персональных данных посетителей сайта научно-практических конференций Оренбургского филиала ФГБУ ВНИИПО МЧС России.</p>

<h2>1. Оператор персональных данных</h2>
<p>Оператор: Оренбургский филиал ФГБУ ВНИИПО МЧС России.</p>
<p>Адрес: Оренбургский район, Нижнепавловский сельсовет, Полигонная улица, дом 1.</p>
<p>Электронная почта для обращений по вопросам персональных данных: <a href="mailto:vniipo.conf@mail.ru">vniipo.conf@mail.ru</a>.</p>

<h2>2. Правовые основания и принципы обработки</h2>
<p>Обработка выполняется в соответствии с Конституцией Российской Федерации, Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных», иными применимыми нормативными актами и согласием субъекта персональных данных.</p>
<p>Оператор обрабатывает только данные, необходимые для заранее определённых законных целей, не объединяет базы данных с несовместимыми целями и принимает меры для обеспечения точности, конфиденциальности и безопасности данных.</p>

<h2>3. Какие данные обрабатываются</h2>
<p>При регистрации на конференцию могут обрабатываться: фамилия, имя, отчество, должность, адрес электронной почты, номер телефона, организация, тип участия, выбранные тематики и секции, сведения о прибытии на конференцию, дата и время подачи заявки, IP-адрес и технические сведения, необходимые для защиты формы от злоупотреблений.</p>
<p>Сайт не предназначен для сбора специальных категорий персональных данных и биометрических персональных данных. Просим не указывать такие сведения в формах сайта.</p>

<h2>4. Цели обработки</h2>
<ul>
<li>приём, проверка и учёт заявок на участие в конференциях;</li>
<li>формирование программы, списков участников, спикеров и посетителей тематических секций;</li>
<li>связь с участником по организационным вопросам;</li>
<li>предоставление после успешной регистрации ссылки на конференционный чат в MAX;</li>
<li>учёт фактического прибытия участников;</li>
<li>подготовка статистической, организационной и отчётной информации;</li>
<li>обеспечение работоспособности и безопасности сайта.</li>
</ul>

<h2>5. Операции с персональными данными</h2>
<p>Оператор может осуществлять сбор, запись, систематизацию, накопление, хранение, уточнение, извлечение, использование, предоставление уполномоченным сотрудникам, блокирование, удаление и уничтожение персональных данных с использованием средств автоматизации и без них.</p>
<p>Доступ предоставляется только сотрудникам и привлечённым лицам, которым данные необходимы для организации конференции, технической поддержки сайта либо исполнения требований законодательства. Такие лица обязаны соблюдать конфиденциальность и требования безопасности.</p>

<h2>6. Сроки хранения и прекращение обработки</h2>
<p>Данные заявок хранятся в период подготовки и проведения конференции, а после её завершения — не более пяти лет для организационной отчётности и рассмотрения возможных обращений, если более длительный срок не установлен законодательством. Технические журналы безопасности хранятся не более одного года.</p>
<p>По достижении целей обработки, истечении срока хранения или при отзыве согласия данные удаляются либо обезличиваются, если у Оператора отсутствует иное законное основание для продолжения обработки.</p>

<h2>7. Использование MAX</h2>
<p>После успешной регистрации сайт показывает ссылку на конференционный чат в мессенджере MAX. Сайт не создаёт аккаунт MAX и не передаёт введённые в регистрационную форму данные в MAX автоматически. При переходе по ссылке и использовании мессенджера MAX самостоятельно обрабатывает информацию пользователя в соответствии со своей <a href="https://legal.max.ru/pp" target="_blank" rel="noopener">Политикой конфиденциальности сервиса MAX</a>.</p>

<h2>8. Файлы cookie и технические данные</h2>
<p>Сайт может использовать строго необходимые cookie WordPress для работы авторизации редакторов, защиты форм и сохранения технического состояния сеанса. Ограничение таких cookie в браузере может привести к некорректной работе отдельных функций.</p>

<h2>9. Защита данных</h2>
<p>Оператор применяет необходимые правовые, организационные и технические меры: разграничение прав доступа, аутентификацию пользователей, журналирование действий, резервное копирование, обновление программного обеспечения и защиту каналов связи при промышленном размещении сайта.</p>

<h2>10. Права пользователя</h2>
<p>Пользователь вправе получать сведения об обработке своих данных, требовать их уточнения, блокирования или удаления, отозвать согласие, а также обжаловать действия Оператора в Роскомнадзоре или в суде.</p>
<p>Для реализации прав или отзыва согласия направьте обращение на <a href="mailto:vniipo.conf@mail.ru">vniipo.conf@mail.ru</a>. В обращении укажите ФИО, контакт для ответа, конференцию и суть требования. Оператор вправе запросить сведения, необходимые для подтверждения личности заявителя.</p>

<h2>11. Согласие и последствия отказа</h2>
<p>Пользователь предоставляет согласие активным действием — самостоятельно устанавливает отметку в регистрационной форме. Без согласия на обработку данных заявка не может быть принята, поскольку Оператор не сможет идентифицировать участника, включить его в списки и связаться с ним.</p>

<h2>12. Изменение Политики</h2>
<p>Оператор может обновлять Политику при изменении законодательства, состава сервисов или процессов обработки. Действующая редакция всегда публикуется на этой странице.</p>
HTML;
}

function fyremezzonine_manager_ensure_privacy_policy_page() {
    $page_id = absint(get_option('wp_page_for_privacy_policy'));
    $page = $page_id ? get_post($page_id) : null;

    if (!$page || $page->post_type !== 'page') {
        $page = get_page_by_path('privacy-policy', OBJECT, 'page');
        $page_id = $page ? absint($page->ID) : 0;
    }

    $legacy_policy = $page && str_contains(
        (string) $page->post_content,
        'Настоящая политика определяет порядок обработки персональных данных участников конференций.'
    );
    if (!$page_id || $legacy_policy || trim((string) $page->post_content) === '') {
        $page_data = array(
            'post_title' => 'Политика обработки персональных данных',
            'post_name' => 'privacy-policy',
            'post_content' => wp_slash(fyremezzonine_manager_privacy_policy_content()),
            'post_status' => 'publish',
            'post_type' => 'page',
        );

        if ($page_id) {
            $page_data['ID'] = $page_id;
            $saved_page_id = wp_update_post($page_data, true);
        } else {
            $saved_page_id = wp_insert_post($page_data, true);
        }

        if (!is_wp_error($saved_page_id)) {
            $page_id = absint($saved_page_id);
            update_post_meta($page_id, '_fyremezzonine_managed_privacy_policy', 1);
        }
    }

    if ($page_id) {
        update_option('wp_page_for_privacy_policy', $page_id);
    }

    return $page_id;
}

function fyremezzonine_manager_registration_interest_groups($conference_id) {
    $topics = fyremezzonine_manager_parse_topic_rows(get_post_meta($conference_id, '_conference_topics', true));
    if (!$topics) {
        for ($index = 1; $index <= 3; $index++) {
            $legacy_title = sanitize_text_field(get_post_meta($conference_id, '_conference_topic_' . $index . '_title', true));
            if ($legacy_title) {
                $topics[] = array('title' => $legacy_title, 'sections' => array());
            }
        }
    }
    $groups = array();

    foreach ($topics as $topic_index => $topic) {
        $topic_title = sanitize_text_field($topic['title'] ?? '');
        if (!$topic_title) {
            continue;
        }

        $sections = !empty($topic['sections']) && is_array($topic['sections']) ? $topic['sections'] : array();
        $source_options = $sections ?: array($topic_title);
        $options = array();

        foreach ($source_options as $section_index => $section) {
            $section = sanitize_text_field($section);
            if (!$section) {
                continue;
            }

            $display = $sections ? $topic_title . ' — ' . $section : $topic_title;
            $key = substr(hash('sha256', $conference_id . '|' . $topic_index . '|' . $section_index . '|' . $display), 0, 20);
            $options[] = array(
                'key' => $key,
                'label' => $section,
                'display' => $display,
            );
        }

        if ($options) {
            $groups[] = array(
                'title' => $topic_title,
                'has_sections' => (bool) $sections,
                'options' => $options,
            );
        }
    }

    return $groups;
}

function fyremezzonine_manager_sanitize_interest_topics($conference_id, $raw_values) {
    $raw_values = is_array($raw_values) ? array_map('sanitize_key', $raw_values) : array();
    $allowed = array();

    foreach (fyremezzonine_manager_registration_interest_groups($conference_id) as $group) {
        foreach ($group['options'] as $option) {
            $allowed[$option['key']] = $option['display'];
        }
    }

    $selected = array();
    foreach ($raw_values as $value) {
        if (isset($allowed[$value])) {
            $selected[] = $allowed[$value];
        }
    }

    return array_values(array_unique($selected));
}

function fyremezzonine_manager_interest_topics_label($stored) {
    $stored = trim((string) $stored);
    if ($stored === '') {
        return '';
    }

    $values = json_decode($stored, true);
    if (!is_array($values)) {
        $values = preg_split('/\s*[,;\n]\s*/', $stored);
    }

    return implode('; ', array_values(array_filter(array_map('sanitize_text_field', $values))));
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

    if (!empty($GLOBALS['fyremezzonine_manager_registration_succeeded'])) {
        return $message;
    }

    $interest_groups = fyremezzonine_manager_registration_interest_groups($conference_id);
    $selected_interest_keys = isset($_POST['interest_topics']) && is_array($_POST['interest_topics'])
        ? array_map('sanitize_key', wp_unslash($_POST['interest_topics']))
        : array();

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
        <div class="registration-name-grid">
            <p>
                <label>Фамилия<br>
                    <input type="text" name="last_name" required>
                </label>
            </p>
            <p>
                <label>Имя<br>
                    <input type="text" name="first_name" required>
                </label>
            </p>
            <p>
                <label>Отчество<br>
                    <input type="text" name="middle_name">
                </label>
            </p>
        </div>
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
        <fieldset class="registration-participant-types">
            <legend>Тип участника</legend>
            <div class="registration-checkbox-grid">
                <?php foreach (fyremezzonine_manager_participant_type_options() as $type_key => $type_label) : ?>
                    <label>
                        <input type="checkbox" name="participant_types[]" value="<?php echo esc_attr($type_key); ?>">
                        <span><?php echo esc_html($type_label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php if ($interest_groups) : ?>
            <fieldset class="registration-participant-types registration-interest-topics">
                <legend>Интересующие темы</legend>
                <p class="registration-field-hint">Можно выбрать несколько вариантов.</p>
                <div class="registration-interest-groups">
                    <?php foreach ($interest_groups as $interest_group) : ?>
                        <div class="registration-interest-group">
                            <?php if ($interest_group['has_sections']) : ?>
                                <strong><?php echo esc_html($interest_group['title']); ?></strong>
                            <?php endif; ?>
                            <div class="registration-checkbox-grid">
                                <?php foreach ($interest_group['options'] as $interest_option) : ?>
                                    <label>
                                        <input type="checkbox" name="interest_topics[]" value="<?php echo esc_attr($interest_option['key']); ?>" <?php checked(in_array($interest_option['key'], $selected_interest_keys, true)); ?>>
                                        <span><?php echo esc_html($interest_option['label']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>
        <p class="registration-consent">
            <label>
                <input type="checkbox" name="privacy_consent" value="1" required aria-required="true" <?php checked(isset($_POST['privacy_consent']) && $_POST['privacy_consent'] === '1'); ?>>
                <span>Я даю согласие Оренбургскому филиалу ФГБУ ВНИИПО МЧС России на обработку указанных в форме персональных данных для регистрации и участия в конференции и принимаю <a href="<?php echo esc_url(fyremezzonine_manager_privacy_policy_url()); ?>" target="_blank" rel="noopener">политику обработки персональных данных сайта</a>.</span>
            </label>
        </p>
        <p class="registration-consent">
            <label>
                <input type="checkbox" name="max_policy_consent" value="1" required aria-required="true" <?php checked(isset($_POST['max_policy_consent']) && $_POST['max_policy_consent'] === '1'); ?>>
                <span>Я ознакомлен(а) и согласен(а) с <a href="<?php echo esc_url(fyremezzonine_manager_max_privacy_policy_url()); ?>" target="_blank" rel="noopener">политикой конфиденциальности мессенджера MAX</a>, в котором размещён чат конференции.</span>
            </label>
        </p>
        <p><button class="button button-red" type="submit">Отправить заявку</button></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('conference_registration_form', 'fyremezzonine_manager_registration_shortcode');

function fyremezzonine_manager_latest_conference_id() {
    return fyremezzonine_manager_current_conference_id();
}

function fyremezzonine_manager_verification_datetime($timestamp) {
    return wp_date('Y-m-d H:i:s', $timestamp);
}

function fyremezzonine_manager_verification_timestamp($datetime) {
    if (!$datetime) {
        return 0;
    }

    $value = date_create_immutable_from_format('Y-m-d H:i:s', (string) $datetime, wp_timezone());
    return $value ? $value->getTimestamp() : 0;
}

function fyremezzonine_manager_verification_token_hash($token) {
    return hash_hmac('sha256', (string) $token, wp_salt('auth'));
}

function fyremezzonine_manager_generate_verification_credentials() {
    return array(
        'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        'token' => bin2hex(random_bytes(32)),
    );
}

function fyremezzonine_manager_verification_url($token) {
    return add_query_arg('token', $token, home_url('/registration/verify/'));
}

function fyremezzonine_manager_mask_email($email) {
    $parts = explode('@', (string) $email, 2);
    if (count($parts) !== 2) {
        return '';
    }

    $name = $parts[0];
    $visible = function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
    return $visible . str_repeat('*', max(3, strlen($name) - 1)) . '@' . $parts[1];
}

function fyremezzonine_manager_send_verification_email($email, $conference_id, $code, $token) {
    if (!fyremezzonine_manager_smtp_is_configured()) {
        return false;
    }

    $conference_title = get_the_title($conference_id);
    $verification_url = fyremezzonine_manager_verification_url($token);
    $subject = '[ВНИИПО] Код подтверждения регистрации';
    $message = '<!doctype html><html><body style="margin:0;background:#f3f5f7;font-family:Arial,sans-serif;color:#17212b">';
    $message .= '<div style="max-width:620px;margin:0 auto;padding:32px 16px"><div style="background:#fff;border:1px solid #dfe5ea;border-radius:8px;padding:32px">';
    $message .= '<p style="margin:0 0 8px;color:#687480;font-size:13px;font-weight:700;text-transform:uppercase">Оренбургский филиал ФГБУ ВНИИПО МЧС России</p>';
    $message .= '<h1 style="margin:0 0 18px;font-size:24px;line-height:1.25">Подтверждение регистрации</h1>';
    $message .= '<p style="margin:0 0 18px;line-height:1.6">Вы регистрируетесь на конференцию «' . esc_html($conference_title) . '».</p>';
    $message .= '<p style="margin:0 0 8px;color:#687480">Код подтверждения:</p>';
    $message .= '<div style="margin:0 0 18px;padding:16px;background:#f0f4f7;border-radius:6px;font-size:32px;font-weight:800;letter-spacing:8px;text-align:center">' . esc_html($code) . '</div>';
    $message .= '<p style="margin:0 0 22px;line-height:1.6">Код действует 10 минут. Никому его не сообщайте.</p>';
    $message .= '<p style="margin:0"><a href="' . esc_url($verification_url) . '" style="display:inline-block;padding:12px 18px;background:#b51f29;color:#fff;text-decoration:none;border-radius:6px;font-weight:700">Ввести код на сайте</a></p>';
    $message .= '</div><p style="margin:16px 0 0;color:#77828d;font-size:12px;line-height:1.5">Если вы не отправляли заявку, просто проигнорируйте это письмо.</p></div></body></html>';

    return wp_mail(
        $email,
        $subject,
        $message,
        array('Content-Type: text/html; charset=UTF-8')
    );
}

function fyremezzonine_manager_find_pending_registration($token) {
    global $wpdb;

    $token = strtolower(trim((string) $token));
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    return $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM ' . fyremezzonine_manager_table_name() . " WHERE verification_token_hash = %s AND status = 'pending_email' LIMIT 1",
            fyremezzonine_manager_verification_token_hash($token)
        )
    );
}

function fyremezzonine_manager_create_pending_registration($data) {
    global $wpdb;

    $table = fyremezzonine_manager_table_name();
    $conference_id = absint($data['conference_id']);
    $email = sanitize_email($data['email']);
    $confirmed_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE conference_id = %d AND email = %s AND email_verified = 1 AND status <> 'pending_email' LIMIT 1",
            $conference_id,
            $email
        )
    );
    if ($confirmed_id) {
        return new WP_Error('already_registered', 'На этот email уже оформлена регистрация на выбранную конференцию.');
    }

    $pending = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE conference_id = %d AND email = %s AND status = 'pending_email' ORDER BY id DESC LIMIT 1",
            $conference_id,
            $email
        )
    );
    $now = current_datetime()->getTimestamp();
    if ($pending && $pending->verification_sent_at) {
        $seconds_since_send = $now - fyremezzonine_manager_verification_timestamp($pending->verification_sent_at);
        if ($seconds_since_send < FYREMEZZONINE_EMAIL_RESEND_DELAY) {
            return new WP_Error('recently_sent', 'Код уже отправлен. Проверьте входящие письма и папку «Спам».');
        }
    }

    $credentials = fyremezzonine_manager_generate_verification_credentials();
    $expires_at = fyremezzonine_manager_verification_datetime($now + FYREMEZZONINE_EMAIL_CODE_TTL);
    $send_count = 1;
    if ($pending && fyremezzonine_manager_verification_timestamp($pending->verification_expires_at) > $now) {
        $send_count = (int) $pending->verification_resend_count + 1;
        if ($send_count > FYREMEZZONINE_EMAIL_MAX_SENDS) {
            return new WP_Error('send_limit', 'Лимит отправки кодов исчерпан. Подождите 10 минут и отправьте заявку снова.');
        }
    }

    $data = array_merge(
        $data,
        array(
            'status' => 'pending_email',
            'email_verified' => 0,
            'verification_code_hash' => wp_hash_password($credentials['code']),
            'verification_token_hash' => fyremezzonine_manager_verification_token_hash($credentials['token']),
            'verification_expires_at' => $expires_at,
            'verification_attempts' => 0,
            'verification_sent_at' => fyremezzonine_manager_verification_datetime($now),
            'verification_resend_count' => $send_count,
            'verified_at' => null,
        )
    );

    if ($pending) {
        $saved = $wpdb->update($table, $data, array('id' => absint($pending->id)));
        $registration_id = absint($pending->id);
    } else {
        $data['created_at'] = current_time('mysql');
        $saved = $wpdb->insert($table, $data);
        $registration_id = absint($wpdb->insert_id);
    }

    if ($saved === false) {
        return new WP_Error('database_error', 'Заявку не удалось сохранить. Попробуйте отправить форму еще раз.');
    }

    return array(
        'registration_id' => $registration_id,
        'token' => $credentials['token'],
        'mail_sent' => fyremezzonine_manager_send_verification_email($email, $conference_id, $credentials['code'], $credentials['token']),
        'email' => $email,
    );
}

function fyremezzonine_manager_verification_form($token, $email = '', $notice = '', $error = '') {
    ob_start();
    ?>
    <div class="registration-verification-card">
        <div class="registration-verification-icon" aria-hidden="true">@</div>
        <h2>Подтвердите email</h2>
        <?php if ($notice) : ?>
            <div class="registration-message registration-pending"><?php echo esc_html($notice); ?></div>
        <?php endif; ?>
        <?php if ($error) : ?>
            <div class="registration-message registration-error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>
        <p>Введите шестизначный код из письма<?php echo $email ? ' для ' . esc_html(fyremezzonine_manager_mask_email($email)) : ''; ?>.</p>
        <form class="registration-verification-form" method="post" action="<?php echo esc_url(fyremezzonine_manager_verification_url($token)); ?>">
            <?php wp_nonce_field('fyremezzonine_verify_email_' . $token, 'fyremezzonine_verification_nonce'); ?>
            <input type="hidden" name="verification_action" value="verify">
            <input type="hidden" name="verification_token" value="<?php echo esc_attr($token); ?>">
            <label for="fyremezzonine-email-code">Код подтверждения</label>
            <input id="fyremezzonine-email-code" class="registration-code-input" type="text" name="verification_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
            <button class="button button-red" type="submit">Подтвердить регистрацию</button>
        </form>
        <form class="registration-resend-form" method="post" action="<?php echo esc_url(fyremezzonine_manager_verification_url($token)); ?>">
            <?php wp_nonce_field('fyremezzonine_resend_email_' . $token, 'fyremezzonine_resend_nonce'); ?>
            <input type="hidden" name="verification_action" value="resend">
            <input type="hidden" name="verification_token" value="<?php echo esc_attr($token); ?>">
            <button class="button button-outline" type="submit">Отправить код повторно</button>
        </form>
        <p class="registration-verification-help">Код действует 10 минут. Если письма нет, проверьте папку «Спам».</p>
    </div>
    <?php
    return ob_get_clean();
}

function fyremezzonine_manager_verify_registration_email($token, $code) {
    global $wpdb;

    $registration = fyremezzonine_manager_find_pending_registration($token);
    if (!$registration) {
        return new WP_Error('invalid_token', 'Ссылка подтверждения недействительна или заявка уже подтверждена.');
    }

    $now = current_datetime()->getTimestamp();
    if (fyremezzonine_manager_verification_timestamp($registration->verification_expires_at) < $now) {
        return new WP_Error('expired_code', 'Срок действия кода истек. Отправьте новый код.');
    }

    if ((int) $registration->verification_attempts >= FYREMEZZONINE_EMAIL_MAX_ATTEMPTS) {
        return new WP_Error('attempt_limit', 'Слишком много неверных попыток. Отправьте новый код.');
    }

    $code = preg_replace('/\D+/', '', (string) $code);
    if (strlen($code) !== 6 || !wp_check_password($code, $registration->verification_code_hash)) {
        $wpdb->update(
            fyremezzonine_manager_table_name(),
            array('verification_attempts' => (int) $registration->verification_attempts + 1),
            array('id' => absint($registration->id)),
            array('%d'),
            array('%d')
        );
        return new WP_Error('invalid_code', 'Неверный код подтверждения. Проверьте цифры и попробуйте снова.');
    }

    $updated = $wpdb->update(
        fyremezzonine_manager_table_name(),
        array(
            'status' => 'new',
            'email_verified' => 1,
            'verification_code_hash' => '',
            'verification_token_hash' => '',
            'verification_expires_at' => null,
            'verification_attempts' => 0,
            'verified_at' => current_time('mysql'),
        ),
        array('id' => absint($registration->id))
    );
    if ($updated === false) {
        return new WP_Error('database_error', 'Не удалось подтвердить заявку. Попробуйте еще раз.');
    }

    do_action('fyremezzonine_registration_created', absint($registration->id), absint($registration->conference_id));
    return $registration;
}

function fyremezzonine_manager_resend_verification_email($token) {
    global $wpdb;

    $registration = fyremezzonine_manager_find_pending_registration($token);
    if (!$registration) {
        return new WP_Error('invalid_token', 'Ссылка подтверждения недействительна или заявка уже подтверждена.');
    }

    $now = current_datetime()->getTimestamp();
    $last_sent = fyremezzonine_manager_verification_timestamp($registration->verification_sent_at);
    if ($last_sent && ($now - $last_sent) < FYREMEZZONINE_EMAIL_RESEND_DELAY) {
        $wait = FYREMEZZONINE_EMAIL_RESEND_DELAY - ($now - $last_sent);
        return new WP_Error('resend_delay', 'Повторная отправка будет доступна через ' . max(1, $wait) . ' сек.');
    }

    $send_count = (int) $registration->verification_resend_count;
    if (fyremezzonine_manager_verification_timestamp($registration->verification_expires_at) < $now) {
        $send_count = 0;
    }
    if ($send_count >= FYREMEZZONINE_EMAIL_MAX_SENDS) {
        return new WP_Error('send_limit', 'Лимит отправки кодов исчерпан. Дождитесь окончания текущих 10 минут.');
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $updated = $wpdb->update(
        fyremezzonine_manager_table_name(),
        array(
            'verification_code_hash' => wp_hash_password($code),
            'verification_expires_at' => fyremezzonine_manager_verification_datetime($now + FYREMEZZONINE_EMAIL_CODE_TTL),
            'verification_attempts' => 0,
            'verification_sent_at' => fyremezzonine_manager_verification_datetime($now),
            'verification_resend_count' => $send_count + 1,
        ),
        array('id' => absint($registration->id))
    );
    if ($updated === false) {
        return new WP_Error('database_error', 'Не удалось создать новый код. Попробуйте еще раз.');
    }

    if (!fyremezzonine_manager_send_verification_email($registration->email, $registration->conference_id, $code, $token)) {
        return new WP_Error('mail_error', 'Письмо не отправлено. Попробуйте еще раз позже или обратитесь к организаторам.');
    }

    return true;
}

function fyremezzonine_manager_handle_registration($fallback_conference_id) {
    $conference_id = isset($_POST['conference_id']) ? absint($_POST['conference_id']) : $fallback_conference_id;

    if (!$conference_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fyremezzonine_registration_nonce'])), 'fyremezzonine_register_' . $conference_id)) {
        return '<div class="registration-message registration-error">Не удалось проверить форму. Обновите страницу и попробуйте еще раз.</div>';
    }

    if (fyremezzonine_manager_registration_is_closed($conference_id)) {
        return fyremezzonine_manager_closed_registration_message($conference_id);
    }

    $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $middle_name = isset($_POST['middle_name']) ? sanitize_text_field(wp_unslash($_POST['middle_name'])) : '';
    $full_name = trim(preg_replace('/\s+/', ' ', $last_name . ' ' . $first_name . ' ' . $middle_name));
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $job_position = isset($_POST['job_position']) ? sanitize_text_field(wp_unslash($_POST['job_position'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $organization = isset($_POST['organization']) ? sanitize_text_field(wp_unslash($_POST['organization'])) : '';
    $participant_types = fyremezzonine_manager_sanitize_participant_types(isset($_POST['participant_types']) ? wp_unslash($_POST['participant_types']) : array());
    $interest_topics = fyremezzonine_manager_sanitize_interest_topics($conference_id, isset($_POST['interest_topics']) ? wp_unslash($_POST['interest_topics']) : array());
    $privacy_consent = isset($_POST['privacy_consent']) && $_POST['privacy_consent'] === '1';
    $max_policy_consent = isset($_POST['max_policy_consent']) && $_POST['max_policy_consent'] === '1';

    if (!$last_name || !$first_name || !$email || !is_email($email)) {
        return '<div class="registration-message registration-error">Заполните фамилию, имя и корректный email.</div>';
    }

    if (!$participant_types) {
        return '<div class="registration-message registration-error">Выберите хотя бы один тип участника.</div>';
    }

    if (fyremezzonine_manager_registration_interest_groups($conference_id) && !$interest_topics) {
        return '<div class="registration-message registration-error">Выберите хотя бы одну интересующую тему или секцию.</div>';
    }

    if (!$privacy_consent) {
        return '<div class="registration-message registration-error">Подтвердите согласие на обработку персональных данных и с политикой сайта.</div>';
    }

    if (!$max_policy_consent) {
        return '<div class="registration-message registration-error">Подтвердите согласие с политикой конфиденциальности мессенджера MAX.</div>';
    }

    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $pending = fyremezzonine_manager_create_pending_registration(
        array(
            'conference_id' => $conference_id,
            'full_name' => $full_name,
            'last_name' => $last_name,
            'first_name' => $first_name,
            'middle_name' => $middle_name,
            'job_position' => $job_position,
            'email' => $email,
            'phone' => $phone,
            'organization' => $organization,
            'participant_types' => implode(',', $participant_types),
            'interest_topics' => wp_json_encode($interest_topics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'privacy_consent' => 1,
            'max_policy_consent' => 1,
            'ip_address' => $ip_address,
        )
    );

    if (is_wp_error($pending)) {
        return '<div class="registration-message registration-error">' . esc_html($pending->get_error_message()) . '</div>';
    }

    $GLOBALS['fyremezzonine_manager_registration_succeeded'] = true;
    $notice = $pending['mail_sent']
        ? 'Мы отправили код подтверждения на указанный email.'
        : 'Заявка сохранена, но письмо пока не отправлено. Повторите отправку кода через минуту.';

    return fyremezzonine_manager_verification_form($pending['token'], $pending['email'], $notice);
}

function fyremezzonine_manager_registration_success_message($conference_id, $registration_id = 0) {
    $chat_url = esc_url(get_post_meta($conference_id, '_conference_chat_1_url', true));
    $message = '<div class="registration-message registration-success registration-complete">';
    $message .= '<strong>Регистрация прошла успешно</strong>';
    $message .= '<p>Ваша заявка сохранена. Присоединяйтесь к чату участников конференции.</p>';
    if ($chat_url) {
        $message .= '<div class="registration-chat-actions"><a class="button button-blue" href="' . esc_url($chat_url) . '" target="_blank" rel="noopener">Перейти в чат участников</a></div>';
    }
    $message .= '</div>';

    return $message;
}

function fyremezzonine_manager_partner_request_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'conference_id' => 0,
        ),
        $atts,
        'conference_partner_request_form'
    );

    $conference_id = absint($atts['conference_id']) ?: fyremezzonine_manager_latest_conference_id();
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fyremezzonine_partner_request_nonce'])) {
        $message = fyremezzonine_manager_handle_partner_request($conference_id);
    }

    ob_start();
    if ($message) {
        echo wp_kses_post($message);
    }
    ?>
    <form class="conference-registration-form conference-partner-request-form" method="post">
        <?php wp_nonce_field('fyremezzonine_partner_request_' . $conference_id, 'fyremezzonine_partner_request_nonce'); ?>
        <input type="hidden" name="conference_id" value="<?php echo esc_attr($conference_id); ?>">
        <div class="registration-conference-title">
            <span>Заявка на партнерство</span>
            <strong><?php echo $conference_id ? esc_html(get_the_title($conference_id)) : 'Конференция ВНИИПО'; ?></strong>
        </div>
        <p>
            <label>Название компании<br>
                <input type="text" name="company_name" required placeholder="Например: ООО &quot;Пожарные технологии&quot;">
            </label>
        </p>
        <p>
            <label>Сайт компании<br>
                <input type="url" name="company_site" placeholder="https://example.ru/">
            </label>
        </p>
        <p>
            <label>Город<br>
                <input type="text" name="company_city" placeholder="Например: Оренбург">
            </label>
        </p>
        <p>
            <label>Степень партнерства<br>
                <select name="partnership_level" required>
                    <?php foreach (fyremezzonine_manager_partnership_level_options() as $level_key => $level_label) : ?>
                        <option value="<?php echo esc_attr($level_key); ?>" <?php selected($level_key, 'partner'); ?>>
                            <?php echo esc_html($level_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <p>
            <label>Контактное лицо<br>
                <input type="text" name="contact_name" required placeholder="Фамилия Имя Отчество">
            </label>
        </p>
        <p>
            <label>Должность<br>
                <input type="text" name="contact_position" placeholder="Например: директор по развитию">
            </label>
        </p>
        <p>
            <label>Email<br>
                <input type="email" name="email" required placeholder="mail@example.ru">
            </label>
        </p>
        <p>
            <label>Телефон<br>
                <input type="tel" name="phone" placeholder="+7">
            </label>
        </p>
        <p><button class="button button-red" type="submit">Отправить заявку</button></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('conference_partner_request_form', 'fyremezzonine_manager_partner_request_shortcode');

function fyremezzonine_manager_handle_partner_request($fallback_conference_id) {
    global $wpdb;

    $conference_id = isset($_POST['conference_id']) ? absint($_POST['conference_id']) : $fallback_conference_id;

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fyremezzonine_partner_request_nonce'])), 'fyremezzonine_partner_request_' . $conference_id)) {
        return '<div class="registration-message registration-error">Не удалось проверить форму. Обновите страницу и попробуйте еще раз.</div>';
    }

    $company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
    $company_site = isset($_POST['company_site']) ? esc_url_raw(wp_unslash($_POST['company_site'])) : '';
    $company_city = isset($_POST['company_city']) ? sanitize_text_field(wp_unslash($_POST['company_city'])) : '';
    $partnership_level = isset($_POST['partnership_level']) ? sanitize_key(wp_unslash($_POST['partnership_level'])) : 'partner';
    $contact_name = isset($_POST['contact_name']) ? sanitize_text_field(wp_unslash($_POST['contact_name'])) : '';
    $contact_position = isset($_POST['contact_position']) ? sanitize_text_field(wp_unslash($_POST['contact_position'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

    if (!$company_name || !$contact_name || !$email || !is_email($email)) {
        return '<div class="registration-message registration-error">Заполните название компании, контактное лицо и корректный email.</div>';
    }

    if (!array_key_exists($partnership_level, fyremezzonine_manager_partnership_level_options())) {
        $partnership_level = 'partner';
    }

    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

    $wpdb->insert(
        fyremezzonine_manager_partner_requests_table_name(),
        array(
            'conference_id' => $conference_id,
            'company_name' => $company_name,
            'company_site' => $company_site,
            'company_city' => $company_city,
            'partnership_level' => $partnership_level,
            'contact_name' => $contact_name,
            'contact_position' => $contact_position,
            'email' => $email,
            'phone' => $phone,
            'status' => 'new',
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    return '<div class="registration-message registration-success">Спасибо! Заявка на партнерство отправлена. Представители ВНИИПО свяжутся с вами.</div>';
}

function fyremezzonine_manager_render_simple_create_page() {
    ?>
    <div class="wrap fyremezzonine-simple-create">
        <h1>Создать конференцию через форму</h1>
        <p>Эта страница работает как анкета: сохраните черновик, проверьте предпросмотр и только затем опубликуйте конференцию.</p>
        <style>
            .fyremezzonine-simple-create .conference-submission-form {
                display: grid;
                gap: 18px;
                max-width: 860px;
                margin-top: 20px;
                padding: 0;
                border: 0;
                background: transparent;
            }
            .fyremezzonine-simple-create fieldset {
                display: grid;
                grid-template-columns: 1fr;
                gap: 16px;
                min-width: 0;
                margin: 0;
                padding: 0 20px 20px;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                background: #fff;
                overflow: hidden;
            }
            .fyremezzonine-simple-create .conference-submission-section-head {
                display: grid;
                grid-column: 1 / -1;
                gap: 6px;
                min-width: 0;
                margin: 0 -20px 4px;
                padding: 18px 20px 14px;
                border-bottom: 1px solid #dcdcde;
                background: #f6f7f7;
            }
            .fyremezzonine-simple-create .conference-submission-section-head h2 {
                margin: 0;
                font-size: 20px;
                font-weight: 700;
                line-height: 1.2;
                overflow-wrap: anywhere;
            }
            .fyremezzonine-simple-create .registration-conference-title {
                display: grid;
                gap: 6px;
                padding: 20px;
                border: 1px solid #dcdcde;
                border-left: 6px solid #525afc;
                border-radius: 8px;
                background: #fff;
            }
            .fyremezzonine-simple-create .registration-conference-title span {
                color: #646970;
                font-weight: 700;
                text-transform: uppercase;
            }
            .fyremezzonine-simple-create .registration-conference-title strong {
                font-size: 22px;
            }
            .fyremezzonine-simple-create .conference-submission-state,
            .fyremezzonine-simple-create .conference-submission-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
            }
            .fyremezzonine-simple-create .conference-submission-actions {
                justify-content: flex-end;
            }
            .fyremezzonine-simple-create .conference-submission-state span {
                display: inline-flex;
                align-items: center;
                min-height: 36px;
                padding: 7px 10px;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                color: #646970;
                background: #f6f7f7;
                font-weight: 700;
            }
            .fyremezzonine-simple-create .conference-submission-help {
                margin: 0;
                color: #646970;
                overflow-wrap: anywhere;
            }
            .fyremezzonine-simple-create .conference-submission-field {
                display: grid;
                gap: 7px;
                min-width: 0;
                margin: 0;
            }
            .fyremezzonine-simple-create .conference-submission-field:has(textarea),
            .fyremezzonine-simple-create .conference-submission-field:has(input[type="file"]),
            .fyremezzonine-simple-create .conference-partner-repeater {
                grid-column: 1 / -1;
            }
            .fyremezzonine-simple-create .conference-submission-field label {
                font-weight: 700;
                min-width: 0;
                overflow-wrap: anywhere;
            }
            .fyremezzonine-simple-create input,
            .fyremezzonine-simple-create textarea,
            .fyremezzonine-simple-create select {
                width: 100%;
                min-width: 0;
                max-width: 100%;
            }
        </style>
        <?php echo do_shortcode('[conference_submission_form]'); ?>
    </div>
    <?php
}

function fyremezzonine_manager_admin_menu() {
    if (fyremezzonine_manager_is_section_manager()) {
        add_menu_page(
            'Статистика секций',
            'Статистика секций',
            'view_conference_registration_stats',
            'conference-section-statistics',
            'fyremezzonine_manager_render_section_statistics_page',
            'dashicons-chart-bar',
            3
        );
    }

    add_submenu_page(
        'edit.php?post_type=conference',
        'Создать через форму',
        'Создать через форму',
        'manage_conferences',
        'conference-create-simple',
        'fyremezzonine_manager_render_simple_create_page'
    );

    add_submenu_page(
        'edit.php?post_type=conference',
        'Заявки на участие',
        'Заявки на участие',
        'manage_conferences',
        'conference-registrations',
        'fyremezzonine_manager_render_registrations_page'
    );

    add_submenu_page(
        'edit.php?post_type=conference',
        'Партнерство',
        'Партнерство',
        'manage_conferences',
        'conference-partner-requests',
        'fyremezzonine_manager_render_partner_requests_page'
    );

    add_submenu_page(
        'edit.php?post_type=conference',
        'Как редактировать сайт',
        'Как редактировать сайт',
        'manage_conferences',
        'conference-editing-guide',
        'fyremezzonine_manager_render_guide_page'
    );
}
add_action('admin_menu', 'fyremezzonine_manager_admin_menu');

function fyremezzonine_manager_conference_admin_columns($columns) {
    $result = array();
    foreach ($columns as $key => $label) {
        $result[$key] = $label;
        if ($key === 'title') {
            $result['conference_lifecycle'] = 'Состояние';
            $result['conference_modified'] = 'Последнее изменение';
        }
    }

    return $result;
}
add_filter('manage_conference_posts_columns', 'fyremezzonine_manager_conference_admin_columns');

function fyremezzonine_manager_render_conference_admin_column($column, $post_id) {
    if ($column === 'conference_lifecycle') {
        if (get_post_status($post_id) !== 'publish') {
            echo '<strong>Черновик</strong>';
            return;
        }

        $status = fyremezzonine_manager_lifecycle_status($post_id);
        $labels = fyremezzonine_manager_lifecycle_labels();
        echo '<strong>' . esc_html($labels[$status] ?? 'Будущая') . '</strong>';
        if ($status !== 'completed') {
            $editor_url = add_query_arg('conference_id', $post_id, fyremezzonine_manager_editor_page_url('conferences'));
            echo '<br><a href="' . esc_url($editor_url) . '">Управление</a>';
        }
        return;
    }

    if ($column === 'conference_modified') {
        $post = get_post($post_id);
        echo $post ? esc_html(date_i18n('d.m.Y H:i', strtotime($post->post_modified))) : '—';
    }
}
add_action('manage_conference_posts_custom_column', 'fyremezzonine_manager_render_conference_admin_column', 10, 2);

function fyremezzonine_manager_is_frontend_editor_user($user = null) {
    if ($user instanceof WP_User) {
        return fyremezzonine_manager_can_manage_conferences($user) && !user_can($user, 'manage_options');
    }

    return is_user_logged_in() && fyremezzonine_manager_can_manage_conferences() && !current_user_can('manage_options');
}

function fyremezzonine_manager_redirect_editor_after_login($redirect_to, $requested_redirect_to, $user) {
    if ($user instanceof WP_User && fyremezzonine_manager_is_section_manager($user)) {
        return fyremezzonine_manager_section_statistics_url();
    }

    if (fyremezzonine_manager_is_frontend_editor_user($user)) {
        return home_url('/');
    }

    return $redirect_to;
}
add_filter('login_redirect', 'fyremezzonine_manager_redirect_editor_after_login', 10, 3);

function fyremezzonine_manager_limit_section_manager_admin_access() {
    if (wp_doing_ajax() || !fyremezzonine_manager_is_section_manager()) {
        return;
    }

    global $pagenow;

    $is_statistics_page = $pagenow === 'admin.php'
        && isset($_GET['page'])
        && sanitize_key(wp_unslash($_GET['page'])) === 'conference-section-statistics';

    if ($is_statistics_page) {
        return;
    }

    wp_safe_redirect(fyremezzonine_manager_section_statistics_url());
    exit;
}
add_action('admin_init', 'fyremezzonine_manager_limit_section_manager_admin_access', 5);

function fyremezzonine_manager_limit_editor_admin_access() {
    if (wp_doing_ajax() || !fyremezzonine_manager_is_frontend_editor_user()) {
        return;
    }

    global $pagenow;

    $allowed = in_array($pagenow, array('admin-post.php', 'admin-ajax.php', 'async-upload.php', 'media-upload.php', 'upload.php'), true);
    if ($pagenow === 'edit.php' || $pagenow === 'post-new.php') {
        $allowed = isset($_GET['post_type']) && sanitize_key(wp_unslash($_GET['post_type'])) === 'conference';
    }
    if ($pagenow === 'post.php' && isset($_GET['post'])) {
        $allowed = get_post_type(absint($_GET['post'])) === 'conference';
    }

    if ($allowed) {
        return;
    }

    wp_safe_redirect(admin_url('edit.php?post_type=conference'));
    exit;
}
add_action('admin_init', 'fyremezzonine_manager_limit_editor_admin_access');

function fyremezzonine_manager_trim_editor_admin_menu() {
    if (!fyremezzonine_manager_is_frontend_editor_user()) {
        return;
    }

    foreach (array('index.php', 'edit.php', 'upload.php', 'edit.php?post_type=page', 'edit-comments.php', 'themes.php', 'plugins.php', 'users.php', 'tools.php', 'options-general.php') as $menu_slug) {
        remove_menu_page($menu_slug);
    }
}
add_action('admin_menu', 'fyremezzonine_manager_trim_editor_admin_menu', 999);

function fyremezzonine_manager_trim_section_manager_admin_menu() {
    if (!fyremezzonine_manager_is_section_manager()) {
        return;
    }

    global $menu;
    foreach ((array) $menu as $menu_item) {
        $menu_slug = isset($menu_item[2]) ? (string) $menu_item[2] : '';
        if ($menu_slug && $menu_slug !== 'conference-section-statistics') {
            remove_menu_page($menu_slug);
        }
    }
}
add_action('admin_menu', 'fyremezzonine_manager_trim_section_manager_admin_menu', 1000);

function fyremezzonine_manager_hide_admin_bar_for_editor($show) {
    if (fyremezzonine_manager_is_frontend_editor_user()) {
        return false;
    }

    return $show;
}
add_filter('show_admin_bar', 'fyremezzonine_manager_hide_admin_bar_for_editor');

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

function fyremezzonine_manager_registration_topic_sql_value($topic) {
    return wp_json_encode(sanitize_text_field($topic), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function fyremezzonine_manager_registrations_query($conference_id = 0, $limit = 0, $interest_topic = '') {
    global $wpdb;

    $table = fyremezzonine_manager_table_name();
    $sql = "SELECT r.*, p.post_title AS conference_title FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.conference_id";
    $params = array();
    $where = array("r.email_verified = 1", "r.status <> 'pending_email'");

    if ($conference_id) {
        $where[] = 'r.conference_id = %d';
        $params[] = $conference_id;
    }

    $interest_topic = sanitize_text_field($interest_topic);
    if ($interest_topic) {
        $where[] = 'r.interest_topics LIKE %s';
        $params[] = '%' . $wpdb->esc_like(fyremezzonine_manager_registration_topic_sql_value($interest_topic)) . '%';
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
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
    if (!fyremezzonine_manager_can_manage_conferences()) {
        wp_die('Недостаточно прав.');
    }

    check_admin_referer('fyremezzonine_export_registrations');

    $conference_id = isset($_GET['conference_id']) ? absint($_GET['conference_id']) : 0;
    $interest_topic = isset($_GET['interest_topic']) ? sanitize_text_field(wp_unslash($_GET['interest_topic'])) : '';
    $items = fyremezzonine_manager_registrations_query($conference_id, 0, $interest_topic);
    $conference_slug = $conference_id ? sanitize_title(get_the_title($conference_id)) : 'all';
    $topic_slug = $interest_topic ? '-' . sanitize_title($interest_topic) : '';
    $filename = 'conference-registrations-' . $conference_slug . $topic_slug . '-' . gmdate('Y-m-d') . '.xls';

    nocache_headers();
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "\xEF\xBB\xBF";
    echo '<!doctype html><html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<thead><tr>';
    $headings = apply_filters(
        'fyremezzonine_registration_export_headings',
        array('ID', 'Дата', 'Конференция', 'Фамилия', 'Имя', 'Отчество', 'Должность', 'Email', 'Телефон', 'Организация', 'Тип участника', 'Интересующие темы', 'Прибыл', 'Статус', 'IP')
    );
    foreach ($headings as $heading) {
        echo '<th>' . esc_html($heading) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($items as $item) {
        list($last_name, $first_name, $middle_name) = fyremezzonine_manager_registration_name_parts($item);
        echo '<tr>';
        $row = apply_filters(
            'fyremezzonine_registration_export_row',
            array(
                $item->id,
                $item->created_at,
                $item->conference_title,
                $last_name,
                $first_name,
                $middle_name,
                $item->job_position,
                $item->email,
                $item->phone,
                $item->organization,
                fyremezzonine_manager_participant_types_label($item->participant_types ?? ''),
                fyremezzonine_manager_interest_topics_label($item->interest_topics ?? ''),
                !empty($item->attended) ? 'Да' : 'Нет',
                $item->status,
                $item->ip_address,
            ),
            $item
        );
        foreach ($row as $cell) {
            echo '<td>' . esc_html((string) $cell) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></body></html>';
    exit;
}
add_action('admin_post_fyremezzonine_export_registrations', 'fyremezzonine_manager_export_registrations');

function fyremezzonine_manager_registrations_return_url($admin_mode, $args = array()) {
    if ($admin_mode) {
        $args = array_merge(
            array(
                'post_type' => 'conference',
                'page' => 'conference-registrations',
            ),
            $args
        );

        return add_query_arg($args, admin_url('edit.php'));
    }

    return add_query_arg($args, fyremezzonine_manager_editor_page_url('registrations'));
}

function fyremezzonine_manager_handle_registration_attendance() {
    global $wpdb;

    if (!fyremezzonine_manager_can_manage_conferences()) {
        wp_die('Недостаточно прав для изменения отметки о прибытии.', 403);
    }

    $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
    check_admin_referer('fyremezzonine_registration_attendance_' . $registration_id);

    $admin_mode = !empty($_POST['registrations_admin']);
    $conference_id = isset($_POST['conference_id']) ? absint($_POST['conference_id']) : 0;
    $interest_topic = isset($_POST['interest_topic']) ? sanitize_text_field(wp_unslash($_POST['interest_topic'])) : '';
    $attended = isset($_POST['attended']) && (string) wp_unslash($_POST['attended']) === '1' ? 1 : 0;
    $updated = false;

    $registration_exists = $registration_id
        ? (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . fyremezzonine_manager_table_name() . ' WHERE id = %d', $registration_id))
        : 0;

    if ($registration_exists) {
        $updated = $wpdb->update(
            fyremezzonine_manager_table_name(),
            array('attended' => $attended),
            array('id' => $registration_id),
            array('%d'),
            array('%d')
        );
    }

    $redirect_args = array(
        'conference_id' => $conference_id,
        'interest_topic' => $interest_topic,
        'attendance_notice' => $updated === false ? 'error' : 'updated',
    );
    wp_safe_redirect(fyremezzonine_manager_registrations_return_url($admin_mode, $redirect_args));
    exit;
}
add_action('admin_post_fyremezzonine_registration_attendance', 'fyremezzonine_manager_handle_registration_attendance');

function fyremezzonine_manager_registration_name_parts($item) {
    $last_name = isset($item->last_name) ? trim((string) $item->last_name) : '';
    $first_name = isset($item->first_name) ? trim((string) $item->first_name) : '';
    $middle_name = isset($item->middle_name) ? trim((string) $item->middle_name) : '';

    if (!$last_name && !$first_name && !$middle_name && !empty($item->full_name)) {
        $parts = preg_split('/\s+/', trim((string) $item->full_name), 3);
        $last_name = $parts[0] ?? '';
        $first_name = $parts[1] ?? '';
        $middle_name = $parts[2] ?? '';
    }

    return array($last_name, $first_name, $middle_name);
}

function fyremezzonine_manager_status_badge($status) {
    $status = sanitize_key($status ?: 'new');
    $labels = array(
        'new' => 'Новая',
        'pending_email' => 'Ожидает email',
        'confirmed' => 'Подтверждена',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
        'archived' => 'Архив',
    );
    $label = $labels[$status] ?? $status;

    return sprintf(
        '<span class="request-status request-status-%1$s">%2$s</span>',
        esc_attr($status),
        esc_html($label)
    );
}

function fyremezzonine_manager_print_controls($label = 'Распечатать список') {
    static $printed_assets = false;

    ob_start();

    if (!$printed_assets) :
        $printed_assets = true;
        ?>
        <style>
            .conference-print-title {
                display: none;
            }

            .registration-attendance-form {
                margin: 0;
            }

            .registration-attendance-form label {
                display: grid;
                justify-items: center;
                gap: 4px;
                cursor: pointer;
            }

            .registration-attendance-form input[type="checkbox"] {
                width: 18px;
                height: 18px;
                margin: 0;
            }

            .registration-attendance-form span {
                font-size: 11px;
                font-weight: 700;
                line-height: 1.2;
                text-align: center;
            }

            .registration-attendance-print {
                display: none;
            }

            @media print {
                body.conference-print-mode * {
                    visibility: hidden !important;
                }

                body.conference-print-mode .conference-print-area,
                body.conference-print-mode .conference-print-area * {
                    visibility: visible !important;
                }

                body.conference-print-mode .conference-print-area {
                    position: absolute !important;
                    top: 0 !important;
                    left: 0 !important;
                    width: 100% !important;
                    padding: 0 !important;
                    background: #fff !important;
                    color: #000 !important;
                    font-family: Calibri, Arial, sans-serif !important;
                }

                body.conference-print-mode .conference-print-actions,
                body.conference-print-mode .conference-editor-filter,
                body.conference-print-mode .conference-admin-filter {
                    display: none !important;
                }

                body.conference-print-mode .registration-attendance-form {
                    display: none !important;
                }

                body.conference-print-mode .registration-attendance-print {
                    display: inline !important;
                }

                body.conference-print-mode .conference-print-title {
                    display: block !important;
                    margin: 0 0 10px !important;
                    color: #000 !important;
                    font-family: Calibri, Arial, sans-serif !important;
                    font-size: 16px !important;
                    font-weight: 700 !important;
                    line-height: 1.2 !important;
                }

                body.conference-print-mode .conference-registrations-scroll {
                    overflow: visible !important;
                    margin: 0 !important;
                }

                body.conference-print-mode table {
                    width: 100% !important;
                    min-width: 0 !important;
                    border-collapse: collapse !important;
                    box-shadow: none !important;
                    table-layout: fixed !important;
                    color: #000 !important;
                    font-family: Calibri, Arial, sans-serif !important;
                    font-size: 9pt !important;
                    line-height: 1.15 !important;
                }

                body.conference-print-mode thead {
                    display: table-header-group !important;
                }

                body.conference-print-mode tfoot {
                    display: table-footer-group !important;
                }

                body.conference-print-mode tr {
                    break-inside: avoid !important;
                    page-break-inside: avoid !important;
                }

                body.conference-print-mode th,
                body.conference-print-mode td {
                    padding: 4px 5px !important;
                    border: 1px solid #9e9e9e !important;
                    color: #000 !important;
                    background: #fff !important;
                    text-align: left !important;
                    vertical-align: top !important;
                    white-space: normal !important;
                    overflow-wrap: anywhere !important;
                }

                body.conference-print-mode th {
                    background: #d9eaf7 !important;
                    font-weight: 700 !important;
                    text-transform: none !important;
                }

                body.conference-print-mode tbody tr:nth-child(even) td {
                    background: #f8fbfd !important;
                }

                body.conference-print-mode a {
                    color: #000 !important;
                    text-decoration: none !important;
                }

                body.conference-print-mode .request-status {
                    display: inline !important;
                    min-height: 0 !important;
                    padding: 0 !important;
                    border-radius: 0 !important;
                    background: transparent !important;
                    color: #000 !important;
                    font: inherit !important;
                    white-space: normal !important;
                }

                @page {
                    size: landscape;
                    margin: 12mm;
                }
            }
        </style>
        <script>
            (function () {
                if (window.fyremezzoninePrintList) {
                    return;
                }

                window.fyremezzoninePrintList = function () {
                    document.body.classList.add('conference-print-mode');
                    window.print();
                };

                window.addEventListener('afterprint', function () {
                    document.body.classList.remove('conference-print-mode');
                });
            }());
        </script>
        <?php
    endif;
    ?>
    <div class="conference-print-actions">
        <button type="button" class="button button-outline conference-print-button" onclick="window.fyremezzoninePrintList()"><?php echo esc_html($label); ?></button>
    </div>
    <?php
    return ob_get_clean();
}

function fyremezzonine_manager_default_conference_filter() {
    return fyremezzonine_manager_current_conference_id();
}

function fyremezzonine_manager_registrations_interface($admin_mode = false) {
    $statistics_only = fyremezzonine_manager_is_section_manager();
    $conference_id = isset($_GET['conference_id']) ? absint($_GET['conference_id']) : fyremezzonine_manager_default_conference_filter();
    if ($statistics_only && !$conference_id) {
        $conference_id = fyremezzonine_manager_default_conference_filter();
    }
    $interest_topic = isset($_GET['interest_topic']) ? sanitize_text_field(wp_unslash($_GET['interest_topic'])) : '';
    $attendance_notice = isset($_GET['attendance_notice']) ? sanitize_key(wp_unslash($_GET['attendance_notice'])) : '';
    $interest_options = fyremezzonine_manager_registration_interest_filter_options($conference_id);
    $items = $statistics_only ? array() : fyremezzonine_manager_registrations_query($conference_id, 200, $interest_topic);
    $total_items = $statistics_only && !$interest_topic ? 0 : fyremezzonine_manager_registrations_count($conference_id, $interest_topic);
    $conferences = fyremezzonine_manager_get_conference_options();
    $export_url = '';
    if (!$statistics_only) {
        $export_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'fyremezzonine_export_registrations',
                    'conference_id' => $conference_id,
                    'interest_topic' => $interest_topic,
                ),
                admin_url('admin-post.php')
            ),
            'fyremezzonine_export_registrations'
        );
    }
    $form_action = $admin_mode ? admin_url('edit.php') : fyremezzonine_manager_editor_page_url('registrations');
    if ($admin_mode && $statistics_only) {
        $form_action = admin_url('admin.php');
    }
    $table_class = ($admin_mode ? 'widefat fixed striped' : 'conference-registrations-table') . ' conference-registrations-table-participants';
    ob_start();
    ?>
    <?php if ($statistics_only) : ?>
        <p>Выберите конференцию и свою тематику или секцию. Система покажет количество участников, которые отметили ее в заявке.</p>
    <?php else : ?>
        <p>Регистрации не удаляются после завершения конференции: старые заявки остаются в архиве и доступны по фильтрам.</p>
    <?php endif; ?>

    <form class="<?php echo $admin_mode ? 'conference-admin-filter' : 'conference-editor-filter'; ?>" method="get" action="<?php echo esc_url($form_action); ?>">
        <?php if ($admin_mode) : ?>
            <?php if ($statistics_only) : ?>
                <input type="hidden" name="page" value="conference-section-statistics">
            <?php else : ?>
                <input type="hidden" name="post_type" value="conference">
                <input type="hidden" name="page" value="conference-registrations">
            <?php endif; ?>
        <?php endif; ?>
        <p>
            <label for="conference_id"><strong>Конференция</strong></label>
            <select id="conference_id" name="conference_id" onchange="this.form.elements.interest_topic.value=''; this.form.submit()">
                <?php if (!$statistics_only) : ?><option value="0">Все конференции</option><?php endif; ?>
                <?php foreach ($conferences as $conference) : ?>
                    <option value="<?php echo esc_attr($conference->ID); ?>" <?php selected($conference_id, $conference->ID); ?>>
                        <?php echo esc_html(get_the_title($conference)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="interest_topic"><strong>Тематика или секция</strong></label>
            <select id="interest_topic" name="interest_topic" onchange="this.form.submit()">
                <option value=""><?php echo $statistics_only ? 'Выберите тематику или секцию' : 'Все темы и секции'; ?></option>
                <?php foreach ($interest_options as $interest_option) : ?>
                    <option value="<?php echo esc_attr($interest_option); ?>" <?php selected($interest_topic, $interest_option); ?>><?php echo esc_html($interest_option); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php if (!$statistics_only) : ?>
            <a class="<?php echo $admin_mode ? 'button button-primary' : 'button button-red'; ?>" href="<?php echo esc_url($export_url); ?>">Выгрузить Excel</a>
        <?php endif; ?>
    </form>

    <div class="conference-registration-summary" role="status">
        <span><?php echo $statistics_only && !$interest_topic ? 'Выберите тематику или секцию для просмотра статистики' : ($interest_topic ? 'Потенциальных участников по выбранной тематике' : 'Заявок по выбранной конференции'); ?></span>
        <strong><?php echo $statistics_only && !$interest_topic ? '—' : esc_html(number_format_i18n($total_items)); ?></strong>
        <?php if ($interest_topic) : ?><small><?php echo esc_html($interest_topic); ?></small><?php endif; ?>
    </div>

    <?php if ($statistics_only) : ?>
        <div class="section-statistics-privacy-note">Персональные данные участников, экспорт и изменение заявок для этой роли недоступны.</div>
    <?php endif; ?>

    <?php if ($attendance_notice === 'updated') : ?>
        <?php if ($admin_mode) : ?>
            <div class="notice notice-success is-dismissible"><p>Отметка о прибытии сохранена.</p></div>
        <?php else : ?>
            <div class="registration-message registration-success">Отметка о прибытии сохранена.</div>
        <?php endif; ?>
    <?php elseif ($attendance_notice === 'error') : ?>
        <?php if ($admin_mode) : ?>
            <div class="notice notice-error"><p>Не удалось сохранить отметку о прибытии.</p></div>
        <?php else : ?>
            <div class="registration-message registration-error">Не удалось сохранить отметку о прибытии.</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$statistics_only) : ?>
        <?php echo fyremezzonine_manager_print_controls('Распечатать заявки на участие'); ?>
        <?php do_action('fyremezzonine_registrations_interface_notices', $items, $conference_id, $admin_mode); ?>

        <div class="conference-print-area">
        <h2 class="conference-print-title">Список заявок на участие<?php echo $interest_topic ? ': ' . esc_html($interest_topic) : ''; ?> (<?php echo esc_html(number_format_i18n($total_items)); ?>)</h2>
        <div class="conference-registrations-scroll">
        <table class="<?php echo esc_attr($table_class); ?>">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Конференция</th>
                    <th>Фамилия</th>
                    <th>Имя</th>
                    <th>Отчество</th>
                    <th>Должность</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Организация</th>
                    <th>Тип участника</th>
                    <th>Интересующие темы</th>
                    <th>Прибыл</th>
                    <th>Статус</th>
                    <?php do_action('fyremezzonine_registrations_table_header', $admin_mode); ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($items) : ?>
                    <?php foreach ($items as $item) : ?>
                        <?php list($last_name, $first_name, $middle_name) = fyremezzonine_manager_registration_name_parts($item); ?>
                        <tr>
                            <td><?php echo esc_html($item->created_at); ?></td>
                            <td><?php echo esc_html($item->conference_title); ?></td>
                            <td><?php echo esc_html($last_name); ?></td>
                            <td><?php echo esc_html($first_name); ?></td>
                            <td><?php echo esc_html($middle_name); ?></td>
                            <td><?php echo esc_html($item->job_position); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($item->email); ?>"><?php echo esc_html($item->email); ?></a></td>
                            <td><?php echo esc_html($item->phone); ?></td>
                            <td><?php echo esc_html($item->organization); ?></td>
                            <td><?php echo esc_html(fyremezzonine_manager_participant_types_label($item->participant_types ?? '')); ?></td>
                            <td><?php echo esc_html(fyremezzonine_manager_interest_topics_label($item->interest_topics ?? '')); ?></td>
                            <td>
                                <form class="registration-attendance-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="fyremezzonine_registration_attendance">
                                    <input type="hidden" name="registration_id" value="<?php echo esc_attr($item->id); ?>">
                                    <input type="hidden" name="conference_id" value="<?php echo esc_attr($conference_id); ?>">
                                    <input type="hidden" name="interest_topic" value="<?php echo esc_attr($interest_topic); ?>">
                                    <?php if ($admin_mode) : ?><input type="hidden" name="registrations_admin" value="1"><?php endif; ?>
                                    <?php wp_nonce_field('fyremezzonine_registration_attendance_' . $item->id); ?>
                                    <label>
                                        <input type="checkbox" name="attended" value="1" <?php checked(!empty($item->attended)); ?> onchange="this.form.submit()">
                                        <span><?php echo !empty($item->attended) ? 'Прибыл' : 'Не прибыл'; ?></span>
                                    </label>
                                    <noscript><button class="button" type="submit">Сохранить</button></noscript>
                                </form>
                                <span class="registration-attendance-print"><?php echo !empty($item->attended) ? 'Да' : 'Нет'; ?></span>
                            </td>
                            <td><?php echo fyremezzonine_manager_status_badge($item->status); ?></td>
                            <?php do_action('fyremezzonine_registrations_table_row', $item, $admin_mode); ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="<?php echo esc_attr(apply_filters('fyremezzonine_registrations_table_column_count', 13)); ?>">Пока заявок нет.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

function fyremezzonine_manager_registrations_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="registration-message registration-error">Войдите в WordPress, чтобы посмотреть заявки.</div>';
    }

    if (!fyremezzonine_manager_can_view_registration_stats()) {
        return '<div class="registration-message registration-error">У вашей учетной записи нет прав для просмотра заявок.</div>';
    }

    return fyremezzonine_manager_registrations_interface(false);
}
add_shortcode('conference_registrations_archive', 'fyremezzonine_manager_registrations_shortcode');

function fyremezzonine_manager_partner_requests_query($conference_id = 0, $limit = 200) {
    global $wpdb;

    $table = fyremezzonine_manager_partner_requests_table_name();
    $posts_table = $wpdb->posts;
    $sql = "SELECT p.*, COALESCE(c.post_title, '') AS conference_title
            FROM {$table} p
            LEFT JOIN {$posts_table} c ON c.ID = p.conference_id";
    $params = array();

    if ($conference_id) {
        $sql .= ' WHERE p.conference_id = %d';
        $params[] = $conference_id;
    }

    $sql .= ' ORDER BY p.created_at DESC';

    if ($limit) {
        $sql .= ' LIMIT %d';
        $params[] = $limit;
    }

    if ($params) {
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    return $wpdb->get_results($sql);
}

function fyremezzonine_manager_registrations_count($conference_id = 0, $interest_topic = '') {
    global $wpdb;

    $table = fyremezzonine_manager_table_name();
    $sql = "SELECT COUNT(*) FROM {$table} r";
    $params = array();
    $where = array("r.email_verified = 1", "r.status <> 'pending_email'");

    if ($conference_id) {
        $where[] = 'r.conference_id = %d';
        $params[] = $conference_id;
    }

    $interest_topic = sanitize_text_field($interest_topic);
    if ($interest_topic) {
        $where[] = 'r.interest_topics LIKE %s';
        $params[] = '%' . $wpdb->esc_like(fyremezzonine_manager_registration_topic_sql_value($interest_topic)) . '%';
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    return (int) ($params ? $wpdb->get_var($wpdb->prepare($sql, $params)) : $wpdb->get_var($sql));
}

function fyremezzonine_manager_registration_interest_filter_options($conference_id = 0) {
    global $wpdb;

    $options = array();
    $conferences = $conference_id ? array(get_post($conference_id)) : fyremezzonine_manager_get_conference_options();
    foreach (array_filter($conferences) as $conference) {
        foreach (fyremezzonine_manager_registration_interest_groups($conference->ID) as $group) {
            foreach ($group['options'] as $option) {
                $options[$option['display']] = $option['display'];
            }
        }
    }

    $table = fyremezzonine_manager_table_name();
    if ($conference_id) {
        $stored_values = $wpdb->get_col($wpdb->prepare("SELECT interest_topics FROM {$table} WHERE conference_id = %d AND email_verified = 1 AND status <> 'pending_email' AND interest_topics IS NOT NULL AND interest_topics <> ''", $conference_id));
    } else {
        $stored_values = $wpdb->get_col("SELECT interest_topics FROM {$table} WHERE email_verified = 1 AND status <> 'pending_email' AND interest_topics IS NOT NULL AND interest_topics <> ''");
    }

    foreach ($stored_values as $stored) {
        $values = json_decode((string) $stored, true);
        if (!is_array($values)) {
            continue;
        }
        foreach ($values as $value) {
            $value = sanitize_text_field($value);
            if ($value) {
                $options[$value] = $value;
            }
        }
    }

    natcasesort($options);

    return array_values($options);
}

function fyremezzonine_manager_partnership_url($args = array()) {
    return add_query_arg($args, fyremezzonine_manager_editor_page_url('partnership'));
}

function fyremezzonine_manager_admin_partnership_url($args = array()) {
    $base_url = add_query_arg(
        array(
            'post_type' => 'conference',
            'page' => 'conference-partner-requests',
        ),
        admin_url('edit.php')
    );

    return add_query_arg($args, $base_url);
}

function fyremezzonine_manager_partnership_management_url($args = array(), $admin_mode = false) {
    return $admin_mode
        ? fyremezzonine_manager_admin_partnership_url($args)
        : fyremezzonine_manager_partnership_url($args);
}

function fyremezzonine_manager_partnership_post_uses_admin() {
    return !empty($_POST['partnership_admin']);
}

function fyremezzonine_manager_handle_partner_catalog_save() {
    if (!fyremezzonine_manager_can_manage_conferences()) {
        wp_die('Недостаточно прав для управления партнерами.', 403);
    }

    check_admin_referer('fyremezzonine_partner_catalog_save');
    $partner_id = isset($_POST['partner_id']) ? sanitize_text_field(wp_unslash($_POST['partner_id'])) : '';
    $catalog = fyremezzonine_manager_get_partner_catalog();
    $existing_index = null;
    $existing = null;

    foreach ($catalog as $index => $item) {
        if ($partner_id && hash_equals((string) $item['id'], $partner_id)) {
            $existing_index = $index;
            $existing = $item;
            break;
        }
    }

    $name = isset($_POST['partner_name']) ? sanitize_text_field(wp_unslash($_POST['partner_name'])) : '';
    if (!$name) {
        wp_safe_redirect(fyremezzonine_manager_partnership_management_url(array('partner_id' => $partner_id ?: 'new', 'partnership_notice' => 'name_required'), fyremezzonine_manager_partnership_post_uses_admin()));
        exit;
    }

    $uploaded_logo = fyremezzonine_manager_upload_image_for_field('partner_logo', 0);
    $item = fyremezzonine_manager_sanitize_partner_catalog_item(
        array(
            'id' => $existing['id'] ?? wp_generate_uuid4(),
            'name' => $name,
            'level' => isset($_POST['partner_level']) ? wp_unslash($_POST['partner_level']) : 'partner',
            'site' => isset($_POST['partner_site']) ? wp_unslash($_POST['partner_site']) : '',
            'logo_url' => $uploaded_logo ?: (isset($_POST['partner_logo_url']) ? wp_unslash($_POST['partner_logo_url']) : ($existing['logo_url'] ?? '')),
            'city' => isset($_POST['partner_city']) ? wp_unslash($_POST['partner_city']) : '',
            'contact_name' => isset($_POST['partner_contact_name']) ? wp_unslash($_POST['partner_contact_name']) : '',
            'contact_position' => isset($_POST['partner_contact_position']) ? wp_unslash($_POST['partner_contact_position']) : '',
            'email' => isset($_POST['partner_email']) ? wp_unslash($_POST['partner_email']) : '',
            'phone' => isset($_POST['partner_phone']) ? wp_unslash($_POST['partner_phone']) : '',
            'comment' => isset($_POST['partner_comment']) ? wp_unslash($_POST['partner_comment']) : '',
            'request_id' => $existing['request_id'] ?? 0,
            'updated_at' => current_time('mysql'),
        )
    );

    if ($existing_index === null) {
        $catalog[] = $item;
    } else {
        $catalog[$existing_index] = $item;
    }

    fyremezzonine_manager_save_partner_catalog($catalog);
    wp_safe_redirect(fyremezzonine_manager_partnership_management_url(array('partnership_notice' => 'partner_saved'), fyremezzonine_manager_partnership_post_uses_admin()));
    exit;
}
add_action('admin_post_fyremezzonine_partner_catalog_save', 'fyremezzonine_manager_handle_partner_catalog_save');

function fyremezzonine_manager_handle_partner_catalog_delete() {
    if (!fyremezzonine_manager_can_manage_conferences()) {
        wp_die('Недостаточно прав для управления партнерами.', 403);
    }

    $partner_id = isset($_POST['partner_id']) ? sanitize_text_field(wp_unslash($_POST['partner_id'])) : '';
    check_admin_referer('fyremezzonine_partner_catalog_delete_' . $partner_id);
    $catalog = array_values(array_filter(fyremezzonine_manager_get_partner_catalog(), static function($item) use ($partner_id) {
        return !hash_equals((string) $item['id'], (string) $partner_id);
    }));
    fyremezzonine_manager_save_partner_catalog($catalog);
    wp_safe_redirect(fyremezzonine_manager_partnership_management_url(array('partnership_notice' => 'partner_deleted'), fyremezzonine_manager_partnership_post_uses_admin()));
    exit;
}
add_action('admin_post_fyremezzonine_partner_catalog_delete', 'fyremezzonine_manager_handle_partner_catalog_delete');

function fyremezzonine_manager_handle_partner_request_approve() {
    global $wpdb;

    if (!fyremezzonine_manager_can_manage_conferences()) {
        wp_die('Недостаточно прав для обработки заявок.', 403);
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    check_admin_referer('fyremezzonine_partner_request_approve_' . $request_id);
    $request = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . fyremezzonine_manager_partner_requests_table_name() . ' WHERE id = %d', $request_id)
    );

    if (!$request) {
        wp_safe_redirect(fyremezzonine_manager_partnership_management_url(array('partnership_notice' => 'request_missing'), fyremezzonine_manager_partnership_post_uses_admin()));
        exit;
    }

    $catalog = fyremezzonine_manager_get_partner_catalog();
    $catalog_index = null;
    foreach ($catalog as $index => $item) {
        if (absint($item['request_id'] ?? 0) === $request_id || strcasecmp(trim($item['name'] ?? ''), trim($request->company_name)) === 0) {
            $catalog_index = $index;
            break;
        }
    }

    $existing = $catalog_index === null ? array() : $catalog[$catalog_index];
    $partner = fyremezzonine_manager_sanitize_partner_catalog_item(
        array(
            'id' => $existing['id'] ?? wp_generate_uuid4(),
            'name' => $request->company_name,
            'level' => $request->partnership_level,
            'site' => $request->company_site,
            'logo_url' => $existing['logo_url'] ?? '',
            'city' => $request->company_city,
            'contact_name' => $request->contact_name,
            'contact_position' => $request->contact_position,
            'email' => $request->email,
            'phone' => $request->phone,
            'comment' => $existing['comment'] ?? '',
            'request_id' => $request_id,
            'updated_at' => current_time('mysql'),
        )
    );

    if ($catalog_index === null) {
        $catalog[] = $partner;
    } else {
        $catalog[$catalog_index] = $partner;
    }
    fyremezzonine_manager_save_partner_catalog($catalog);
    $wpdb->update(
        fyremezzonine_manager_partner_requests_table_name(),
        array('status' => 'approved'),
        array('id' => $request_id),
        array('%s'),
        array('%d')
    );

    wp_safe_redirect(fyremezzonine_manager_partnership_management_url(array('partnership_notice' => 'request_approved'), fyremezzonine_manager_partnership_post_uses_admin()));
    exit;
}
add_action('admin_post_fyremezzonine_partner_request_approve', 'fyremezzonine_manager_handle_partner_request_approve');

function fyremezzonine_manager_render_partner_catalog($admin_mode = false) {
    $catalog = fyremezzonine_manager_get_partner_catalog();
    $edit_id = isset($_GET['partner_id']) ? sanitize_text_field(wp_unslash($_GET['partner_id'])) : '';
    $editing = $edit_id === 'new' ? array() : ($edit_id ? fyremezzonine_manager_find_partner_catalog_item($edit_id) : null);
    $notice = isset($_GET['partnership_notice']) ? sanitize_key(wp_unslash($_GET['partnership_notice'])) : '';
    $messages = array(
        'partner_saved' => array('success', 'Данные партнера сохранены.'),
        'partner_deleted' => array('success', 'Партнер удален из общего списка.'),
        'request_approved' => array('success', 'Заявка одобрена. Компания добавлена в список партнеров.'),
        'name_required' => array('error', 'Укажите название компании.'),
        'request_missing' => array('error', 'Заявка не найдена.'),
    );

    ob_start();
    if (isset($messages[$notice])) :
        ?>
        <div class="registration-message registration-<?php echo esc_attr($messages[$notice][0]); ?>"><?php echo esc_html($messages[$notice][1]); ?></div>
        <?php
    endif;

    if ($edit_id && ($edit_id === 'new' || $editing)) :
        $editing = is_array($editing) ? $editing : array();
        ?>
        <section class="partnership-editor-card">
            <div class="partnership-section-head">
                <div>
                    <span>Карточка партнера</span>
                    <h2><?php echo $edit_id === 'new' ? 'Новый партнер' : esc_html($editing['name'] ?? 'Партнер'); ?></h2>
                </div>
                <a class="button button-outline" href="<?php echo esc_url(fyremezzonine_manager_partnership_management_url(array(), $admin_mode)); ?>">Закрыть</a>
            </div>
            <form class="partnership-editor-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fyremezzonine_partner_catalog_save">
                <input type="hidden" name="partner_id" value="<?php echo esc_attr($editing['id'] ?? ''); ?>">
                <?php if ($admin_mode) : ?><input type="hidden" name="partnership_admin" value="1"><?php endif; ?>
                <?php wp_nonce_field('fyremezzonine_partner_catalog_save'); ?>
                <label><span>Название компании *</span><input type="text" name="partner_name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" required></label>
                <label><span>Степень партнерства</span><select name="partner_level"><?php foreach (fyremezzonine_manager_partnership_level_options() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($editing['level'] ?? 'partner', $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><span>Сайт компании</span><input type="url" name="partner_site" value="<?php echo esc_attr($editing['site'] ?? ''); ?>" placeholder="https://example.ru/"></label>
                <label><span>Город</span><input type="text" name="partner_city" value="<?php echo esc_attr($editing['city'] ?? ''); ?>"></label>
                <label><span>Контактное лицо</span><input type="text" name="partner_contact_name" value="<?php echo esc_attr($editing['contact_name'] ?? ''); ?>"></label>
                <label><span>Должность контактного лица</span><input type="text" name="partner_contact_position" value="<?php echo esc_attr($editing['contact_position'] ?? ''); ?>"></label>
                <label><span>Email</span><input type="email" name="partner_email" value="<?php echo esc_attr($editing['email'] ?? ''); ?>"></label>
                <label><span>Телефон</span><input type="text" name="partner_phone" value="<?php echo esc_attr($editing['phone'] ?? ''); ?>"></label>
                <label class="partnership-logo-field">
                    <span>Логотип</span>
                    <input type="hidden" name="partner_logo_url" value="<?php echo esc_attr($editing['logo_url'] ?? ''); ?>">
                    <?php if (!empty($editing['logo_url'])) : ?><?php fyremezzonine_manager_render_uploaded_image_preview($editing['logo_url'], 'Логотип партнера'); ?><?php endif; ?>
                    <input type="file" name="partner_logo_file" accept="image/*,.gif">
                </label>
                <label class="partnership-comment-field"><span>Комментарий</span><textarea name="partner_comment" rows="4"><?php echo esc_textarea($editing['comment'] ?? ''); ?></textarea></label>
                <div class="partnership-editor-actions"><button class="button button-red" type="submit">Сохранить партнера</button></div>
            </form>
        </section>
        <?php
    endif;
    ?>
    <section class="partnership-catalog-section">
        <div class="partnership-section-head">
            <div>
                <span>Общий список</span>
                <h2>Текущие партнеры</h2>
                <p>Этот список автоматически подставляется в каждую новую конференцию вместе с сайтами и логотипами.</p>
            </div>
            <a class="button button-red" href="<?php echo esc_url(fyremezzonine_manager_partnership_management_url(array('partner_id' => 'new'), $admin_mode)); ?>">Добавить партнера</a>
        </div>
        <?php if ($catalog) : ?>
            <div class="partnership-catalog-grid">
                <?php foreach ($catalog as $partner) : ?>
                    <article class="partnership-partner-card">
                        <a class="partnership-partner-main" href="<?php echo esc_url(fyremezzonine_manager_partnership_management_url(array('partner_id' => $partner['id']), $admin_mode)); ?>">
                            <span class="partnership-partner-logo">
                                <?php if (!empty($partner['logo_url'])) : ?><img src="<?php echo esc_url($partner['logo_url']); ?>" alt="" loading="lazy"><?php else : ?><strong><?php echo esc_html(mb_substr($partner['name'], 0, 1)); ?></strong><?php endif; ?>
                            </span>
                            <span class="partnership-partner-info">
                                <strong><?php echo esc_html($partner['name']); ?></strong>
                                <small><?php echo esc_html(fyremezzonine_manager_partnership_level_label($partner['level'])); ?></small>
                                <?php if (!empty($partner['site'])) : ?><span><?php echo esc_html(wp_parse_url($partner['site'], PHP_URL_HOST) ?: $partner['site']); ?></span><?php endif; ?>
                            </span>
                            <span class="partnership-partner-edit">Изменить</span>
                        </a>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Удалить партнера из общего списка? В уже созданных конференциях он останется.');">
                            <input type="hidden" name="action" value="fyremezzonine_partner_catalog_delete">
                            <input type="hidden" name="partner_id" value="<?php echo esc_attr($partner['id']); ?>">
                            <?php if ($admin_mode) : ?><input type="hidden" name="partnership_admin" value="1"><?php endif; ?>
                            <?php wp_nonce_field('fyremezzonine_partner_catalog_delete_' . $partner['id']); ?>
                            <button class="partnership-partner-delete" type="submit" aria-label="Удалить партнера" title="Удалить партнера">&times;</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="conference-editor-picker-empty"><strong>Список партнеров пуст</strong><p>Добавьте партнера вручную или одобрите заявку ниже.</p></div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

function fyremezzonine_manager_partner_requests_interface($admin_mode = false) {
    $conference_id = isset($_GET['conference_id']) ? absint($_GET['conference_id']) : fyremezzonine_manager_default_conference_filter();
    $items = fyremezzonine_manager_partner_requests_query($conference_id, 200);
    $conferences = fyremezzonine_manager_get_conference_options();
    $form_action = $admin_mode ? admin_url('edit.php') : fyremezzonine_manager_editor_page_url('partnership');
    $table_class = ($admin_mode ? 'widefat fixed striped' : 'conference-registrations-table') . ' conference-registrations-table-partners';

    ob_start();
    ?>
    <?php echo fyremezzonine_manager_render_partner_catalog($admin_mode); ?>
    <section class="partnership-requests-section">
    <div class="partnership-section-head">
        <div><span>Входящие обращения</span><h2>Заявки на партнерство</h2><p>Одобрение переносит компанию в общий список партнеров. После этого карточку можно дополнить логотипом.</p></div>
    </div>
    <form class="<?php echo $admin_mode ? 'conference-admin-filter' : 'conference-editor-filter'; ?>" method="get" action="<?php echo esc_url($form_action); ?>">
        <?php if ($admin_mode) : ?>
            <input type="hidden" name="post_type" value="conference">
            <input type="hidden" name="page" value="conference-partner-requests">
        <?php endif; ?>
        <p>
            <label for="partner_conference_id"><strong>Конференция</strong></label>
            <select id="partner_conference_id" name="conference_id" onchange="this.form.submit()">
                <option value="0">Все конференции</option>
                <?php foreach ($conferences as $conference) : ?>
                    <option value="<?php echo esc_attr($conference->ID); ?>" <?php selected($conference_id, $conference->ID); ?>>
                        <?php echo esc_html(get_the_title($conference)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
    </form>
    <?php echo fyremezzonine_manager_print_controls('Распечатать заявки на партнерство'); ?>

    <div class="conference-print-area">
        <h2 class="conference-print-title">Список заявок на партнерство</h2>
        <div class="conference-registrations-scroll">
        <table class="<?php echo esc_attr($table_class); ?>">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Конференция</th>
                    <th>Компания</th>
                    <th>Степень</th>
                    <th>Контактное лицо</th>
                    <th>Контакты</th>
                    <th>Статус и действие</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items) : ?>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->created_at); ?></td>
                            <td><?php echo esc_html($item->conference_title ?: 'Не указана'); ?></td>
                            <td><strong><?php echo esc_html($item->company_name); ?></strong><?php if (!empty($item->company_city)) : ?><small><?php echo esc_html($item->company_city); ?></small><?php endif; ?><?php if (!empty($item->company_site)) : ?><a href="<?php echo esc_url($item->company_site); ?>" target="_blank" rel="noopener">Открыть сайт</a><?php endif; ?></td>
                            <td><?php echo esc_html(fyremezzonine_manager_partnership_level_label($item->partnership_level ?? 'partner')); ?></td>
                            <td><strong><?php echo esc_html($item->contact_name); ?></strong><small><?php echo esc_html($item->contact_position); ?></small></td>
                            <td><a href="mailto:<?php echo esc_attr($item->email); ?>"><?php echo esc_html($item->email); ?></a><small><?php echo esc_html($item->phone); ?></small></td>
                            <td>
                                <?php echo fyremezzonine_manager_status_badge($item->status); ?>
                                <?php if (($item->status ?? 'new') !== 'approved') : ?>
                                    <form class="partnership-approve-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="fyremezzonine_partner_request_approve">
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr($item->id); ?>">
                                        <?php if ($admin_mode) : ?><input type="hidden" name="partnership_admin" value="1"><?php endif; ?>
                                        <?php wp_nonce_field('fyremezzonine_partner_request_approve_' . $item->id); ?>
                                        <button class="button button-red" type="submit">Одобрить</button>
                                    </form>
                                <?php else : ?>
                                    <small>Добавлена в партнеры</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="7">Пока заявок на партнерство нет.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    </section>
    <?php
    return ob_get_clean();
}

function fyremezzonine_manager_partner_requests_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="registration-message registration-error">Войдите в WordPress, чтобы посмотреть заявки.</div>';
    }

    if (!fyremezzonine_manager_can_manage_conferences()) {
        return '<div class="registration-message registration-error">У вашей учетной записи нет прав для просмотра заявок.</div>';
    }

    return fyremezzonine_manager_partner_requests_interface(false);
}
add_shortcode('conference_partner_requests_archive', 'fyremezzonine_manager_partner_requests_shortcode');
add_shortcode('conference_partnership_manager', 'fyremezzonine_manager_partner_requests_shortcode');

function fyremezzonine_manager_render_frontend_editor_page($title, $content, $eyebrow = 'Редактор') {
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
                <p class="section-eyebrow"><?php echo esc_html($eyebrow); ?></p>
                <h1 class="section-title"><?php echo esc_html($title); ?></h1>
                <?php echo do_shortcode($content); ?>
            </div>
        </section>
    </main>
    <?php
    get_footer();
    exit;
}

function fyremezzonine_manager_render_editor_login() {
    global $wp_query;

    if (is_user_logged_in()) {
        wp_safe_redirect(fyremezzonine_manager_is_section_manager() ? fyremezzonine_manager_section_statistics_url() : home_url('/'));
        exit;
    }

    $error_message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fyremezzonine_editor_login_nonce'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fyremezzonine_editor_login_nonce'])), 'fyremezzonine_editor_login')) {
            $error_message = 'Сессия формы истекла. Обновите страницу и попробуйте снова.';
        } else {
            $credentials = array(
                'user_login' => isset($_POST['editor_login']) ? sanitize_text_field(wp_unslash($_POST['editor_login'])) : '',
                'user_password' => isset($_POST['editor_password']) ? (string) wp_unslash($_POST['editor_password']) : '',
                'remember' => !empty($_POST['editor_remember']),
            );
            $user = wp_signon($credentials, is_ssl());

            if (is_wp_error($user)) {
                $error_message = 'Не удалось войти. Проверьте логин и пароль.';
            } else {
                wp_safe_redirect(fyremezzonine_manager_is_section_manager($user) ? fyremezzonine_manager_section_statistics_url() : home_url('/'));
                exit;
            }
        }
    }

    if ($wp_query) {
        $wp_query->is_404 = false;
    }
    status_header(200);
    nocache_headers();
    get_header();
    ?>
    <main id="primary" class="editor-login-page">
        <section class="editor-login-shell">
            <div class="editor-login-brand">
                <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/oren-vniipo-logo.png'); ?>" alt="Логотип ВНИИПО">
                <span>Оренбургский филиал</span>
                <strong>ФГБУ ВНИИПО МЧС России</strong>
            </div>
            <form class="editor-login-form" method="post" autocomplete="on">
                <?php wp_nonce_field('fyremezzonine_editor_login', 'fyremezzonine_editor_login_nonce'); ?>
                <div class="editor-login-heading">
                    <span>Портал редактора</span>
                    <h1>Вход в систему</h1>
                    <p>Управление конференциями, публикациями и заявками участников.</p>
                </div>
                <?php if ($error_message) : ?>
                    <div class="registration-message registration-error" role="alert"><?php echo esc_html($error_message); ?></div>
                <?php endif; ?>
                <label>
                    <span>Логин или email</span>
                    <input type="text" name="editor_login" autocomplete="username" required autofocus>
                </label>
                <label>
                    <span>Пароль</span>
                    <input type="password" name="editor_password" autocomplete="current-password" required>
                </label>
                <label class="editor-login-remember">
                    <input type="checkbox" name="editor_remember" value="1">
                    <span>Оставаться в системе</span>
                </label>
                <button class="button button-red" type="submit">Войти</button>
                <a class="editor-login-back" href="<?php echo esc_url(home_url('/')); ?>">Вернуться на сайт</a>
            </form>
        </section>
    </main>
    <?php
    get_footer();
    exit;
}

function fyremezzonine_manager_render_conference_preview() {
    global $post, $wp_query;

    $conference_id = isset($_GET['conference_id']) ? absint($_GET['conference_id']) : 0;
    $conference = $conference_id ? get_post($conference_id) : null;

    if (!$conference || $conference->post_type !== 'conference') {
        fyremezzonine_manager_render_frontend_editor_page('Предпросмотр конференции', '<div class="registration-message registration-error">Конференция не найдена.</div>');
    }

    if (!is_user_logged_in()) {
        fyremezzonine_manager_render_frontend_editor_page('Предпросмотр конференции', '<div class="registration-message registration-error">Войдите в WordPress, чтобы посмотреть черновик конференции.</div>');
    }

    if (!current_user_can('edit_post', $conference_id)) {
        fyremezzonine_manager_render_frontend_editor_page('Предпросмотр конференции', '<div class="registration-message registration-error">У вашей учетной записи нет прав для просмотра этого черновика.</div>');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fyremezzonine_preview_publish_nonce'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fyremezzonine_preview_publish_nonce'])), 'fyremezzonine_preview_publish_' . $conference_id)) {
            fyremezzonine_manager_render_frontend_editor_page('Предпросмотр конференции', '<div class="registration-message registration-error">Не удалось проверить публикацию. Обновите предпросмотр и попробуйте еще раз.</div>');
        }

        $published_id = wp_update_post(
            array(
                'ID' => $conference_id,
                'post_status' => 'publish',
            ),
            true
        );

        if (is_wp_error($published_id)) {
            fyremezzonine_manager_render_frontend_editor_page('Предпросмотр конференции', '<div class="registration-message registration-error">Конференцию не удалось опубликовать. Вернитесь к редактированию и попробуйте еще раз.</div>');
        }

        wp_safe_redirect(get_permalink($conference_id));
        exit;
    }

    status_header(200);
    nocache_headers();

    $post = $conference;
    setup_postdata($post);

    $wp_query = new WP_Query();
    $wp_query->posts = array($conference);
    $wp_query->post_count = 1;
    $wp_query->current_post = -1;
    $wp_query->in_the_loop = false;
    $wp_query->queried_object = $conference;
    $wp_query->queried_object_id = $conference_id;
    $wp_query->is_single = true;
    $wp_query->is_singular = true;
    $wp_query->is_preview = true;
    $wp_query->is_404 = false;

    $GLOBALS['fyremezzonine_manager_preview_conference_id'] = $conference_id;
    add_action('wp_body_open', 'fyremezzonine_manager_render_preview_toolbar');

    $template = locate_template('single-conference.php');
    if ($template) {
        include $template;
        exit;
    }

    fyremezzonine_manager_render_frontend_editor_page(
        'Предпросмотр конференции',
        '<div class="registration-message registration-error">Шаблон конференции не найден в активной теме.</div>'
    );
}

function fyremezzonine_manager_render_preview_toolbar() {
    $conference_id = !empty($GLOBALS['fyremezzonine_manager_preview_conference_id']) ? absint($GLOBALS['fyremezzonine_manager_preview_conference_id']) : 0;
    if (!$conference_id || !current_user_can('edit_post', $conference_id)) {
        return;
    }

    $status = get_post_status($conference_id);
    $status_object = $status ? get_post_status_object($status) : null;
    $status_label = $status_object ? $status_object->label : $status;
    $edit_url = add_query_arg('conference_id', $conference_id, fyremezzonine_manager_editor_page_url('conferences'));
    ?>
    <div class="conference-preview-toolbar">
        <div>
            <strong>Предпросмотр конференции</strong>
            <span>Статус: <?php echo esc_html($status_label); ?></span>
        </div>
        <div class="conference-preview-toolbar-actions">
            <a class="button button-outline" href="<?php echo esc_url($edit_url); ?>">Редактировать</a>
            <?php if ($status !== 'publish') : ?>
                <form method="post" action="<?php echo esc_url(fyremezzonine_manager_conference_preview_url($conference_id)); ?>">
                    <?php wp_nonce_field('fyremezzonine_preview_publish_' . $conference_id, 'fyremezzonine_preview_publish_nonce'); ?>
                    <button class="button button-red" type="submit">Опубликовать конференцию</button>
                </form>
            <?php else : ?>
                <a class="button button-red" href="<?php echo esc_url(get_permalink($conference_id)); ?>">Открыть опубликованную</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function fyremezzonine_manager_render_email_verification_page() {
    $token = isset($_POST['verification_token'])
        ? sanitize_text_field(wp_unslash($_POST['verification_token']))
        : (isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '');
    $registration = fyremezzonine_manager_find_pending_registration($token);
    $notice = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['verification_action']) ? sanitize_key(wp_unslash($_POST['verification_action'])) : '';
        if ($action === 'verify') {
            $nonce = isset($_POST['fyremezzonine_verification_nonce']) ? sanitize_text_field(wp_unslash($_POST['fyremezzonine_verification_nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'fyremezzonine_verify_email_' . $token)) {
                $error = 'Сессия формы истекла. Обновите страницу и попробуйте снова.';
            } else {
                $code = isset($_POST['verification_code']) ? sanitize_text_field(wp_unslash($_POST['verification_code'])) : '';
                $result = fyremezzonine_manager_verify_registration_email($token, $code);
                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                } else {
                    fyremezzonine_manager_render_frontend_editor_page(
                        'Регистрация подтверждена',
                        fyremezzonine_manager_registration_success_message(absint($result->conference_id), absint($result->id)),
                        'Регистрация'
                    );
                }
            }
        } elseif ($action === 'resend') {
            $nonce = isset($_POST['fyremezzonine_resend_nonce']) ? sanitize_text_field(wp_unslash($_POST['fyremezzonine_resend_nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'fyremezzonine_resend_email_' . $token)) {
                $error = 'Сессия формы истекла. Обновите страницу и попробуйте снова.';
            } else {
                $result = fyremezzonine_manager_resend_verification_email($token);
                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                } else {
                    $notice = 'Новый код отправлен. Используйте только последнее письмо.';
                }
            }
        }
        $registration = fyremezzonine_manager_find_pending_registration($token);
    }

    if (!$registration) {
        $content = '<div class="registration-message registration-error">Ссылка подтверждения недействительна или заявка уже подтверждена.</div>';
    } else {
        $content = fyremezzonine_manager_verification_form($token, $registration->email, $notice, $error);
    }

    nocache_headers();
    fyremezzonine_manager_render_frontend_editor_page('Подтверждение email', $content, 'Регистрация');
}

function fyremezzonine_manager_frontend_editor_routes() {
    $path = isset($_SERVER['REQUEST_URI']) ? trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH), '/') : '';

    if ($path === 'registration/verify') {
        fyremezzonine_manager_render_email_verification_page();
    }

    if ($path === 'partnership') {
        fyremezzonine_manager_render_frontend_editor_page('Заявка на партнерство', '[conference_partner_request_form]', 'Партнерство');
    }

    if ($path === 'editor/login') {
        fyremezzonine_manager_render_editor_login();
    }

    if ($path === 'editor/new-conference') {
        fyremezzonine_manager_render_frontend_editor_page('Создать новую конференцию', '[conference_submission_form]');
    }

    if ($path === 'editor/conferences' || $path === 'editor/edit-conference') {
        fyremezzonine_manager_render_frontend_editor_page('Конференции', '[conference_submission_form]');
    }

    if ($path === 'editor/conference-preview') {
        fyremezzonine_manager_render_conference_preview();
    }

    if ($path === 'editor/registrations') {
        fyremezzonine_manager_render_frontend_editor_page('Заявки на участие', '[conference_registrations_archive]');
    }

    if ($path === 'editor/partnership' || $path === 'editor/partner-requests') {
        fyremezzonine_manager_render_frontend_editor_page('Партнерство', '[conference_partnership_manager]');
    }
}
add_action('template_redirect', 'fyremezzonine_manager_frontend_editor_routes');

function fyremezzonine_manager_render_guide_page() {
    ?>
    <div class="wrap">
        <h1>Как редактировать сайт конференций</h1>
        <p>Главная страница показывает конференцию со статусом <strong>Проходит сейчас</strong>. Даты конференции являются информационными и сами не переключают главную страницу.</p>
        <h2>Где менять контент</h2>
        <ol>
            <li>Откройте <strong>Конференции -> Все конференции</strong>.</li>
            <li>Выберите конференцию или нажмите <strong>Добавить конференцию</strong>.</li>
            <li>Заполните название, описание, краткое описание и блок <strong>Данные конференции</strong>.</li>
            <li>Для картинок используйте кнопку <strong>Выбрать файл</strong> в нужном поле конференции.</li>
            <li>В блоке <strong>Места проведения</strong> добавьте одну или несколько площадок и укажите точный адрес каждой для Яндекс.Карты.</li>
            <li>В каждой теме заполните секции по одной на строку. Если секций нет, участники будут выбирать саму тему.</li>
            <li>После окончания текущей конференции нажмите <strong>Завершить конференцию</strong>: следующая опубликованная конференция станет текущей.</li>
        </ol>
        <h2>Упрощенная форма для редактора</h2>
        <p>На сайте после входа редактора откройте меню <strong>Редактор -> Конференции</strong>. На странице находится список всех конференций, кнопка создания и переход к редактированию текущих, будущих и черновиков.</p>
        <h2>Что управляется из карточки конференции</h2>
        <p>Даты, визуальная тема, программа, чат после регистрации, фон первого экрана, неограниченные темы, спикеры, места проведения, карты, галерея, организаторы, спонсоры и партнеры. Здесь же доступны предпросмотр, публикация, снятие в черновики, завершение, удаление и журнал изменений.</p>
        <h2>Заявки на участие и партнерство в WordPress</h2>
        <p>Откройте <strong>Конференции -> Заявки на участие</strong>, чтобы фильтровать заявки по конференции и тематике, видеть количество потенциальных участников, печатать и выгружать выбранный список в Excel. В разделе <strong>Конференции -> Партнерство</strong> находятся заявки на партнерство и общий каталог партнеров с логотипами и контактами.</p>
        <p>Весь рабочий функционал редактора доступен внутри WordPress. Внешнее меню «Редактор» остается упрощенным альтернативным интерфейсом.</p>
    </div>
    <?php
}

function fyremezzonine_manager_render_section_statistics_page() {
    if (!fyremezzonine_manager_is_section_manager()) {
        wp_die('Недостаточно прав для просмотра статистики секций.', 403);
    }
    ?>
    <div class="wrap fyremezzonine-section-statistics-admin">
        <div class="section-statistics-heading">
            <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
            <div>
                <h1>Статистика секций</h1>
                <p>Количество потенциальных участников рассчитывается по выбранным ими тематикам и секциям.</p>
            </div>
        </div>
        <?php echo fyremezzonine_manager_registrations_interface(true); ?>
    </div>
    <style>
        .fyremezzonine-section-statistics-admin { max-width: 980px; }
        .fyremezzonine-section-statistics-admin .section-statistics-heading {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 22px 0 18px;
        }
        .fyremezzonine-section-statistics-admin .section-statistics-heading > .dashicons {
            display: grid;
            width: 48px;
            height: 48px;
            place-items: center;
            border-radius: 8px;
            background: #dff2e8;
            color: #12633c;
            font-size: 26px;
        }
        .fyremezzonine-section-statistics-admin h1 { margin: 0 0 4px; }
        .fyremezzonine-section-statistics-admin .section-statistics-heading p { margin: 0; color: #646970; }
        .fyremezzonine-section-statistics-admin .conference-admin-filter {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin: 22px 0;
            padding: 20px;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .fyremezzonine-section-statistics-admin .conference-admin-filter p { display: grid; gap: 8px; margin: 0; }
        .fyremezzonine-section-statistics-admin .conference-admin-filter select { width: 100%; max-width: none; min-height: 42px; }
        .fyremezzonine-section-statistics-admin .conference-registration-summary {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 6px 20px;
            min-height: 104px;
            padding: 22px 24px;
            border: 1px solid #b8d8c7;
            border-radius: 8px;
            background: #f1faf5;
        }
        .fyremezzonine-section-statistics-admin .conference-registration-summary span { color: #315744; font-size: 15px; font-weight: 700; }
        .fyremezzonine-section-statistics-admin .conference-registration-summary strong {
            grid-row: 1 / span 2;
            grid-column: 2;
            color: #12633c;
            font-size: 44px;
            line-height: 1;
        }
        .fyremezzonine-section-statistics-admin .conference-registration-summary small { font-weight: 600; overflow-wrap: anywhere; }
        .fyremezzonine-section-statistics-admin .section-statistics-privacy-note {
            margin-top: 14px;
            padding: 12px 14px;
            border-left: 3px solid #72a78a;
            background: #ffffff;
            color: #50575e;
        }
        @media (max-width: 720px) {
            .fyremezzonine-section-statistics-admin .conference-admin-filter { grid-template-columns: 1fr; }
            .fyremezzonine-section-statistics-admin .conference-registration-summary { grid-template-columns: 1fr; }
            .fyremezzonine-section-statistics-admin .conference-registration-summary strong { grid-row: auto; grid-column: auto; }
        }
    </style>
    <?php
}

function fyremezzonine_manager_render_registrations_page() {
    ?>
    <div class="wrap fyremezzonine-registrations-admin">
        <h1>Заявки на участие</h1>
        <?php echo fyremezzonine_manager_registrations_interface(true); ?>
    </div>
    <style>
        .fyremezzonine-registrations-admin .conference-admin-filter {
            display: grid;
            grid-template-columns: minmax(240px, 1fr) minmax(280px, 1.4fr) auto;
            align-items: end;
            gap: 14px;
            margin: 20px 0;
            padding: 16px;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            background: #fff;
        }
        .fyremezzonine-registrations-admin .conference-admin-filter p { display: grid; gap: 7px; margin: 0; }
        .fyremezzonine-registrations-admin .conference-admin-filter select { width: 100%; max-width: none; min-height: 38px; }
        .fyremezzonine-registrations-admin .conference-registration-summary {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 5px 18px;
            margin: 18px 0;
            padding: 16px 18px;
            border: 1px solid #c3c4c7;
            border-left: 5px solid #2271b1;
            border-radius: 6px;
            background: #fff;
        }
        .fyremezzonine-registrations-admin .conference-registration-summary span { color: #646970; font-weight: 600; }
        .fyremezzonine-registrations-admin .conference-registration-summary strong { grid-row: 1 / span 2; grid-column: 2; color: #2271b1; font-size: 30px; line-height: 1; }
        .fyremezzonine-registrations-admin .conference-registration-summary small { font-weight: 600; overflow-wrap: anywhere; }
        .fyremezzonine-registrations-admin table { table-layout: fixed; }
        .fyremezzonine-registrations-admin th,
        .fyremezzonine-registrations-admin td { overflow-wrap: anywhere; vertical-align: top; }
        @media (max-width: 900px) {
            .fyremezzonine-registrations-admin .conference-admin-filter { grid-template-columns: 1fr; }
        }
    </style>
    <?php
}

function fyremezzonine_manager_render_partner_requests_page() {
    ?>
    <div class="wrap fyremezzonine-partnership-admin">
        <h1>Партнерство</h1>
        <?php echo fyremezzonine_manager_partner_requests_interface(true); ?>
    </div>
    <style>
        .fyremezzonine-partnership-admin .partnership-catalog-section,
        .fyremezzonine-partnership-admin .partnership-requests-section,
        .fyremezzonine-partnership-admin .partnership-editor-card { display: grid; gap: 18px; margin-top: 24px; }
        .fyremezzonine-partnership-admin .partnership-section-head { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
        .fyremezzonine-partnership-admin .partnership-section-head h2,
        .fyremezzonine-partnership-admin .partnership-section-head p { margin: 4px 0 0; }
        .fyremezzonine-partnership-admin .partnership-section-head span { color: #b32d2e; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .fyremezzonine-partnership-admin .partnership-catalog-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .fyremezzonine-partnership-admin .partnership-partner-card { position: relative; min-width: 0; border: 1px solid #dcdcde; border-radius: 8px; background: #fff; }
        .fyremezzonine-partnership-admin .partnership-partner-main { display: grid; grid-template-columns: 56px minmax(0, 1fr); align-items: center; gap: 12px; padding: 14px 48px 14px 14px; color: #1d2327; text-decoration: none; }
        .fyremezzonine-partnership-admin .partnership-partner-logo { display: grid; width: 56px; height: 56px; place-items: center; overflow: hidden; border: 1px solid #dcdcde; border-radius: 50%; background: #fff; }
        .fyremezzonine-partnership-admin .partnership-partner-logo img { width: 100%; height: 100%; padding: 6px; box-sizing: border-box; object-fit: contain; }
        .fyremezzonine-partnership-admin .partnership-partner-info { display: grid; gap: 3px; min-width: 0; overflow-wrap: anywhere; }
        .fyremezzonine-partnership-admin .partnership-partner-edit { grid-column: 1 / -1; color: #b32d2e; font-weight: 700; }
        .fyremezzonine-partnership-admin .partnership-partner-card > form { position: absolute; top: 9px; right: 9px; }
        .fyremezzonine-partnership-admin .partnership-partner-delete { width: 30px; height: 30px; border: 1px solid #d63638; border-radius: 50%; color: #b32d2e; background: #fff; cursor: pointer; }
        .fyremezzonine-partnership-admin .partnership-editor-card { padding: 20px; border: 1px solid #dcdcde; border-radius: 8px; background: #fff; }
        .fyremezzonine-partnership-admin .partnership-editor-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .fyremezzonine-partnership-admin .partnership-editor-form label { display: grid; gap: 6px; font-weight: 600; }
        .fyremezzonine-partnership-admin .partnership-editor-form input,
        .fyremezzonine-partnership-admin .partnership-editor-form select,
        .fyremezzonine-partnership-admin .partnership-editor-form textarea { width: 100%; max-width: 100%; }
        .fyremezzonine-partnership-admin .partnership-logo-field,
        .fyremezzonine-partnership-admin .partnership-comment-field,
        .fyremezzonine-partnership-admin .partnership-editor-actions { grid-column: 1 / -1; }
        .fyremezzonine-partnership-admin .partnership-requests-section { margin-top: 40px; padding-top: 24px; border-top: 1px solid #dcdcde; }
        .fyremezzonine-partnership-admin .conference-admin-filter { margin: 0; }
        @media (max-width: 960px) {
            .fyremezzonine-partnership-admin .partnership-catalog-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 700px) {
            .fyremezzonine-partnership-admin .partnership-section-head { align-items: stretch; flex-direction: column; }
            .fyremezzonine-partnership-admin .partnership-catalog-grid,
            .fyremezzonine-partnership-admin .partnership-editor-form { grid-template-columns: 1fr; }
            .fyremezzonine-partnership-admin .partnership-logo-field,
            .fyremezzonine-partnership-admin .partnership-comment-field,
            .fyremezzonine-partnership-admin .partnership-editor-actions { grid-column: auto; }
        }
    </style>
    <?php
}
