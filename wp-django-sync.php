<?php
/**
 * Plugin Name: WP Django Sync (WebSocket)
 * Description: 透過 WebSocket 即時同步 Django 資料
 * Version: 1.0.2
 * Author: 莊鈞凱
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/install.php';



/**
 * 停用插件時刪除 Django 資料表
 */
register_deactivation_hook(__FILE__, 'wp_django_remove_table');

// 引入 Chart 頁面

require_once plugin_dir_path(__FILE__) . 'includes/class-websocket.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-admin.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-ajax-api.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-ultrasonic.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-shortcodes.php';

require_once plugin_dir_path(__FILE__) . 'wp-django-chart.php';

require_once plugin_dir_path(__FILE__) . 'includes/install.php';
