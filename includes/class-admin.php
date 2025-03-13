<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Django_Admin {
    /**
     * 註冊 WordPress Admin 選單 & Admin Bar 按鈕
     */
    public static function add_admin_menu() {
        add_menu_page(
            'Django WebSocket 設定',
            'Django WebSocket',
            'manage_options',
            'wp-django-settings',
            array('WP_Django_Admin', 'render_admin_page'),
            'dashicons-admin-generic',
            99
        );
    }

    /**
     * 渲染 Django WebSocket 設定頁面
     */
    public static function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Django WebSocket 設定</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_django_options');
                do_settings_sections('wp-django-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * 註冊 WordPress Admin 設定
     */
    public static function register_settings() {
        register_setting('wp_django_options', 'wp_django_ws_url');
        add_settings_section('wp_django_main_section', '主要設定', null, 'wp-django-settings');
        add_settings_field('wp_django_ws_url', 'Django WebSocket 伺服器 URL', array('WP_Django_Admin', 'wp_django_ws_url_callback'), 'wp-django-settings', 'wp_django_main_section');
    }

    /**
     * 渲染 Django WebSocket 伺服器 URL 設定輸入框
     */
    public static function wp_django_ws_url_callback() {
        $ws_url = get_option('wp_django_ws_url', 'ws://127.0.0.1:8000/ws/wordpress/');
        echo "<input type='text' name='wp_django_ws_url' value='$ws_url' class='regular-text'>";
    }

    /**
     * 在 WordPress Admin Bar 增加 WebSocket 連線按鈕
     */
    public static function add_admin_bar_button($wp_admin_bar) {
        if (is_user_logged_in()) {
            $args = array(
                'id'    => 'wp_django_ws_connect',
                'title' => '連接 Django WebSocket',
                'href'  => '#',
                'meta'  => ['onclick' => 'connectWebSocket(); return false;']
            );
            $wp_admin_bar->add_node($args);
        }
    }
}

// ✅ 註冊 Admin 設定選單 & 設定 Admin Bar 按鈕
add_action('admin_menu', array('WP_Django_Admin', 'add_admin_menu'));
add_action('admin_init', array('WP_Django_Admin', 'register_settings'));
add_action('admin_bar_menu', array('WP_Django_Admin', 'add_admin_bar_button'), 100);
