<?php
/**
 * Theme bootstrap.
 *
 * @package Fyremezzonine
 */

if (!defined('ABSPATH')) {
    exit;
}

function fyremezzonine_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));

    register_nav_menus(
        array(
            'primary' => __('Primary Menu', 'fyremezzonine'),
        )
    );
}
add_action('after_setup_theme', 'fyremezzonine_setup');

function fyremezzonine_assets() {
    wp_enqueue_style(
        'fyremezzonine-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get('Version')
    );

    wp_enqueue_script(
        'fyremezzonine-theme-effects',
        get_template_directory_uri() . '/assets/theme-effects.js',
        array(),
        wp_get_theme()->get('Version'),
        true
    );
}
add_action('wp_enqueue_scripts', 'fyremezzonine_assets');

function fyremezzonine_conference_visual_theme($conference_id) {
    $theme = sanitize_key(fyremezzonine_conference_meta($conference_id, '_conference_visual_theme', 'classic'));
    $allowed_themes = array('classic', 'arctic', 'ember');

    return in_array($theme, $allowed_themes, true) ? $theme : 'classic';
}

function fyremezzonine_conference_hero_image($conference_id, $visual_theme = 'classic') {
    return fyremezzonine_conference_meta($conference_id, '_conference_hero_image_url');
}

function fyremezzonine_customize_register($wp_customize) {
    $wp_customize->add_section(
        'fyremezzonine_links',
        array(
            'title' => __('Conference Links', 'fyremezzonine'),
            'priority' => 30,
        )
    );

    $settings = array(
        'registration_url' => array(
            'label' => __('Registration URL', 'fyremezzonine'),
            'default' => '/registration',
        ),
        'program_url' => array(
            'label' => __('Program URL', 'fyremezzonine'),
            'default' => '',
        ),
        'materials_url' => array(
            'label' => __('Materials requirements URL', 'fyremezzonine'),
            'default' => '',
        ),
        'branch_url' => array(
            'label' => __('Branch website URL', 'fyremezzonine'),
            'default' => 'https://oren.vniipo.ru/',
        ),
    );

    foreach ($settings as $setting_id => $args) {
        $wp_customize->add_setting(
            $setting_id,
            array(
                'default' => $args['default'],
                'sanitize_callback' => 'fyremezzonine_sanitize_link',
            )
        );

        $wp_customize->add_control(
            $setting_id,
            array(
                'label' => $args['label'],
                'section' => 'fyremezzonine_links',
                'type' => 'text',
            )
        );
    }
}
add_action('customize_register', 'fyremezzonine_customize_register');

function fyremezzonine_sanitize_link($value) {
    $value = trim((string) $value);

    if (substr($value, 0, 1) === '#' || substr($value, 0, 1) === '/') {
        return sanitize_text_field($value);
    }

    return esc_url_raw($value);
}

function fyremezzonine_link($setting_id) {
    $defaults = array(
        'registration_url' => '/registration',
        'program_url' => '',
        'materials_url' => '',
        'branch_url' => 'https://oren.vniipo.ru/',
    );

    return esc_url(get_theme_mod($setting_id, $defaults[$setting_id] ?? ''));
}

function fyremezzonine_asset($path) {
    return get_template_directory_uri() . '/assets/' . ltrim($path, '/');
}

function fyremezzonine_nav_fallback() {
    $items = array(
        home_url('/#about') => 'О конференции',
        home_url('/#participation') => 'Темы',
        home_url('/#partners') => 'Партнеры',
        home_url('/#contacts') => 'Контакты',
        home_url('/conferences/') => 'Конференции',
    );

    echo '<nav class="primary-nav" aria-label="Основная навигация">';
    foreach ($items as $href => $label) {
        printf('<a href="%s">%s</a>', esc_url($href), esc_html($label));
    }
    echo '</nav>';
}

function fyremezzonine_next_conference_id() {
    if (function_exists('fyremezzonine_manager_current_conference_id')) {
        return fyremezzonine_manager_current_conference_id();
    }

    $conference_id = absint(get_option('fyremezzonine_current_conference_id'));
    return $conference_id && get_post_status($conference_id) === 'publish' ? $conference_id : 0;
}

function fyremezzonine_conference_meta($conference_id, $key, $fallback = '') {
    $value = $conference_id ? get_post_meta($conference_id, $key, true) : '';

    return $value !== '' ? $value : $fallback;
}

function fyremezzonine_format_conference_date($date) {
    if (!$date) {
        return '';
    }

    return date_i18n('d.m.Y', strtotime($date));
}

function fyremezzonine_registration_closed($conference_id) {
    if (absint($conference_id) !== fyremezzonine_next_conference_id() || get_post_status($conference_id) !== 'publish') {
        return true;
    }

    $deadline = fyremezzonine_conference_meta($conference_id, '_conference_registration_deadline');

    return $deadline && $deadline < current_time('Y-m-d');
}

function fyremezzonine_conference_date_range($conference_id) {
    $start = fyremezzonine_conference_meta($conference_id, '_conference_start_date');
    $end = fyremezzonine_conference_meta($conference_id, '_conference_end_date');

    if ($start && $end && $start !== $end) {
        return fyremezzonine_format_conference_date($start) . ' - ' . fyremezzonine_format_conference_date($end);
    }

    return fyremezzonine_format_conference_date($start);
}

function fyremezzonine_conference_venues($conference_id) {
    $rows = json_decode((string) fyremezzonine_conference_meta($conference_id, '_conference_venues'), true);
    $venues = array();

    if (is_array($rows)) {
        foreach ($rows as $row) {
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
            if ($venue['name'] || $venue['address']) {
                $venue['map_url'] = fyremezzonine_yandex_map_url('', '', '', $venue['address'] ?: $venue['name']);
                $venues[] = $venue;
            }
        }
    }

    if (!$venues) {
        $name = fyremezzonine_conference_meta($conference_id, '_conference_venue');
        $city = fyremezzonine_conference_meta($conference_id, '_conference_city');
        $address = fyremezzonine_conference_meta($conference_id, '_conference_route_address', $name);
        $directions = fyremezzonine_conference_meta($conference_id, '_conference_route_directions');
        if ($name || $address) {
            $venues[] = array(
                'name' => $name,
                'city' => $city,
                'address' => $address,
                'purpose' => '',
                'directions' => $directions,
                'map_url' => fyremezzonine_yandex_map_url('', '', '', $address ?: $name),
            );
        }
    }

    return $venues;
}

function fyremezzonine_empty_partner_groups() {
    return array(
        'organizers' => array(
            'label' => 'Организатор',
            'featured' => true,
            'items' => array(),
        ),
        'general_partners' => array(
            'label' => 'Генеральные партнеры',
            'wide' => true,
            'items' => array(),
        ),
        'partners' => array(
            'label' => 'Партнеры',
            'items' => array(),
        ),
        'media_partners' => array(
            'label' => 'Информационные партнеры',
            'items' => array(),
        ),
    );
}

function fyremezzonine_parse_partner_list($raw) {
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

function fyremezzonine_parse_topic_list($raw) {
    $items = array();
    foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw))) as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (empty($parts[0])) {
            continue;
        }
        $items[] = array(
            'title' => $parts[0],
            'image_url' => $parts[1] ?? '',
            'sections' => fyremezzonine_decode_topic_sections($parts[2] ?? ''),
        );
    }

    return $items;
}

function fyremezzonine_decode_topic_sections($raw) {
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

function fyremezzonine_parse_speaker_list($raw) {
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

function fyremezzonine_conference_partner_groups($conference_id) {
    $groups = fyremezzonine_empty_partner_groups();
    $meta_map = array(
        'organizers' => '_conference_organizers',
        'general_partners' => '_conference_general_partners',
        'partners' => '_conference_partners',
        'media_partners' => '_conference_media_partners',
    );

    foreach ($meta_map as $group_key => $meta_key) {
        $items = fyremezzonine_parse_partner_list(fyremezzonine_conference_meta($conference_id, $meta_key));
        if ($items) {
            $groups[$group_key]['items'] = $items;
        }
    }

    return $groups;
}

function fyremezzonine_partner_groups_have_items($partner_groups) {
    foreach ($partner_groups as $group) {
        if (!empty($group['items'])) {
            return true;
        }
    }

    return false;
}

function fyremezzonine_render_partner_groups($partner_groups) {
    ?>
    <div class="partner-groups">
        <?php foreach ($partner_groups as $group) : ?>
            <?php if (empty($group['items'])) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <section class="partner-group<?php echo !empty($group['featured']) ? ' partner-group-featured' : ''; ?>" aria-label="<?php echo esc_attr($group['label']); ?>">
                <div class="partner-group-head">
                    <span class="partner-label"><?php echo esc_html($group['label']); ?></span>
                    <?php if (!empty($group['featured']) && !empty($group['items'][0]['name'])) : ?>
                        <h3><?php echo esc_html($group['items'][0]['name']); ?></h3>
                    <?php endif; ?>
                </div>
                <div class="partner-logo-grid<?php echo !empty($group['wide']) ? ' partner-logo-grid-two' : ''; ?>">
                    <?php foreach ($group['items'] as $item) : ?>
                        <?php
                        $name = $item['name'] ?: 'Партнер конференции';
                        $url = $item['url'] ?? '';
                        ?>
                        <?php if ($url) : ?>
                            <a class="partner-logo<?php echo !empty($group['featured']) ? ' partner-logo-wide' : ''; ?>" href="<?php echo esc_url($url); ?>">
                        <?php else : ?>
                            <div class="partner-logo<?php echo !empty($group['featured']) ? ' partner-logo-wide' : ''; ?>">
                        <?php endif; ?>
                            <?php if (!empty($item['logo_url'])) : ?>
                                <img src="<?php echo esc_url($item['logo_url']); ?>" alt="<?php echo esc_attr($name); ?>">
                            <?php else : ?>
                                <span class="partner-logo-name"><?php echo esc_html($name); ?></span>
                            <?php endif; ?>
                        <?php if ($url) : ?>
                            </a>
                        <?php else : ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
    <?php
}

function fyremezzonine_next_conference_data($conference_id = 0) {
    if (!$conference_id) {
        $conference_id = fyremezzonine_next_conference_id();
    }

    if (!$conference_id) {
        return array('id' => 0);
    }

    $post = get_post($conference_id);
    $topics = fyremezzonine_parse_topic_list(fyremezzonine_conference_meta($conference_id, '_conference_topics'));
    if (!$topics) {
        for ($index = 1; $index <= 3; $index++) {
            $legacy_topic = array(
                'title' => fyremezzonine_conference_meta($conference_id, '_conference_topic_' . $index . '_title'),
                'image_url' => fyremezzonine_conference_meta($conference_id, '_conference_topic_' . $index . '_image_url'),
                'sections' => array(),
            );
            if ($legacy_topic['title'] || $legacy_topic['image_url']) {
                $topics[] = $legacy_topic;
            }
        }
    }

    $benefits_raw = fyremezzonine_conference_meta($conference_id, '_conference_benefits');
    $benefits = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $benefits_raw ?: '')));
    $venues = fyremezzonine_conference_venues($conference_id);
    $primary_venue = $venues ? $venues[0] : array('name' => '', 'city' => '', 'address' => '', 'purpose' => '', 'directions' => '', 'map_url' => '');
    $route_address = $primary_venue['address'];
    $visual_theme = fyremezzonine_conference_visual_theme($conference_id);

    return array(
        'id' => $conference_id,
        'title' => get_the_title($conference_id),
        'excerpt' => has_excerpt($conference_id) ? get_the_excerpt($conference_id) : wp_trim_words(wp_strip_all_tags($post->post_content), 34),
        'content' => wp_strip_all_tags($post->post_content),
        'date_range' => fyremezzonine_conference_date_range($conference_id),
        'city' => $primary_venue['city'],
        'venue' => $primary_venue['name'],
        'deadline' => fyremezzonine_format_conference_date(fyremezzonine_conference_meta($conference_id, '_conference_registration_deadline')),
        'registration_closed' => fyremezzonine_registration_closed($conference_id),
        'visual_theme' => $visual_theme,
        'program_url' => fyremezzonine_conference_meta($conference_id, '_conference_program_url', fyremezzonine_link('program_url')),
        'chat_url' => fyremezzonine_conference_meta($conference_id, '_conference_chat_1_url'),
        'partner_form_url' => home_url('/partnership/'),
        'materials_url' => fyremezzonine_link('materials_url'),
        'hero_image_url' => fyremezzonine_conference_hero_image($conference_id, $visual_theme),
        'topic_intro' => fyremezzonine_conference_meta($conference_id, '_conference_topic_intro'),
        'topics' => $topics,
        'about_title' => fyremezzonine_conference_meta($conference_id, '_conference_about_title', get_the_title($conference_id)),
        'about_lead' => fyremezzonine_conference_meta($conference_id, '_conference_about_lead', has_excerpt($conference_id) ? get_the_excerpt($conference_id) : wp_trim_words(wp_strip_all_tags($post->post_content), 34)),
        'speakers' => fyremezzonine_parse_speaker_list(fyremezzonine_conference_meta($conference_id, '_conference_speakers')),
        'benefits' => $benefits,
        'materials_intro' => fyremezzonine_conference_meta($conference_id, '_conference_materials_intro'),
        'venue_heading' => fyremezzonine_conference_meta($conference_id, '_conference_venue_heading'),
        'venue_intro' => fyremezzonine_conference_meta($conference_id, '_conference_venue_intro'),
        'venues' => $venues,
        'route_address' => $route_address,
        'route_directions' => $primary_venue['directions'],
        'map_url' => $primary_venue['map_url'],
        'venue_image_url' => fyremezzonine_conference_meta($conference_id, '_conference_venue_image_url'),
        'collage_image_url' => fyremezzonine_conference_meta($conference_id, '_conference_collage_image_url'),
        'partner_groups' => fyremezzonine_conference_partner_groups($conference_id),
    );
}

function fyremezzonine_yandex_map_url($embed_url, $lat, $lon, $query) {
    if ($embed_url) {
        return $embed_url;
    }

    if ($lat && $lon) {
        $lat = rawurlencode($lat);
        $lon = rawurlencode($lon);
        return 'https://yandex.ru/map-widget/v1/?ll=' . $lon . '%2C' . $lat . '&z=15&pt=' . $lon . '%2C' . $lat . '%2Cpm2rdm';
    }

    return 'https://yandex.ru/map-widget/v1/?mode=search&text=' . rawurlencode($query);
}
