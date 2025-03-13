<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Django_WebSocket {
    /**
     * 載入 WebSocket JavaScript 和自訂 CSS
     */
    public static function enqueue_scripts() {
        // ✅ 載入 WebSocket JS
        wp_enqueue_script('wp-django-ws', plugin_dir_url(__FILE__) . '../wp-django-ws.js', array('jquery'), '1.0.2', true);
        
        // ✅ 載入自訂 CSS
        wp_enqueue_style('wp-django-custom-styles', plugin_dir_url(__FILE__) . '../styles.css', array(), '1.0.3');

        // ✅ 設定 AJAX URL，讓 JavaScript 可以透過 `wp_ajax.ajax_url` 呼叫 AJAX API
        wp_localize_script('wp-django-ws', 'wp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }
}

// ✅ 註冊 WordPress 前端資源載入
add_action('wp_enqueue_scripts', array('WP_Django_WebSocket', 'enqueue_scripts'));
add_action('admin_enqueue_scripts', array('WP_Django_WebSocket', 'enqueue_scripts'));
