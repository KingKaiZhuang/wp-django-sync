<?php
// 確保 WordPress 允許移除插件
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 取得 WordPress 資料庫物件
global $wpdb;
$table_name = $wpdb->prefix . 'django_classifications';

// 刪除資料表
$wpdb->query("DROP TABLE IF EXISTS $table_name");
