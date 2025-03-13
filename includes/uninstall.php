<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'django_classifications';
$ultrasonic_table = $wpdb->prefix . 'django_ultrasonic_data';

// ✅ 刪除 Django 分類資料表
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// ✅ 刪除超音波感測數據表
$wpdb->query("DROP TABLE IF EXISTS $ultrasonic_table");

// ✅ 刪除插件設定
delete_option('wp_django_ws_url');
?>
