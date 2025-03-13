<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 插件啟動時，建立 Django 相關資料表
 */
function wp_django_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // ✅ 創建 Django WebSocket 資料表
    $table_name = $wpdb->prefix . 'django_classifications';
    $sql1 = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        labels TEXT NOT NULL,
        classification VARCHAR(10) NOT NULL,
        analysis TEXT NOT NULL,
        image_url TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // ✅ 創建超音波感測數據資料表
    $ultrasonic_table = $wpdb->prefix . 'django_ultrasonic_data';
    $sql2 = "CREATE TABLE IF NOT EXISTS $ultrasonic_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        ultrasonic1 INT NOT NULL,
        ultrasonic2 INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);

    // ✅ 創建 Django 資料統計頁面
    wp_django_create_chart_page();
}

/**
 * 啟動插件時建立「Django 資料統計」頁面
 */
function wp_django_create_chart_page() {
    $page_title = 'Django 資料統計';
    $page_slug = 'django-chart';
    $page_content = '[django_chart]'; // 透過 shortcode 顯示圖表

    // 確保頁面不存在
    $existing_page = get_page_by_path($page_slug);
    if (!$existing_page) {
        wp_insert_post(array(
            'post_title'    => $page_title,
            'post_content'  => $page_content,
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => $page_slug,
        ));
    }
}

// ✅ 註冊插件啟動時執行
register_activation_hook(__FILE__, 'wp_django_install');
