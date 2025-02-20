<?php
/**
 * Plugin Name: WP Django Sync (WebSocket)
 * Description: 透過 WebSocket 即時同步 Django 資料
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 在 WordPress Admin Bar 增加 WebSocket 連線按鈕
 */
function wp_django_add_admin_bar_button($wp_admin_bar) {
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
add_action('admin_bar_menu', 'wp_django_add_admin_bar_button', 100);

/**
 * 載入 WebSocket JavaScript
 */
function wp_django_enqueue_websocket_script() {
    wp_enqueue_script('wp-django-ws', plugin_dir_url(__FILE__) . 'wp-django-ws.js', array('jquery'), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'wp_django_enqueue_websocket_script');
add_action('admin_enqueue_scripts', 'wp_django_enqueue_websocket_script');

/**
 * AJAX 儲存 Django WebSocket 傳來的資料
 */
function wp_django_save_data() {
    if (!is_user_logged_in()) {
        wp_send_json_error("❌ 未登入用戶");
    }

    $user_id = get_current_user_id();
    update_user_meta($user_id, 'django_labels', sanitize_text_field($_POST['labels']));
    update_user_meta($user_id, 'django_classification', sanitize_text_field($_POST['classification']));
    update_user_meta($user_id, 'django_analysis', sanitize_textarea_field($_POST['analysis']));

    wp_send_json_success("✅ Django 資料已同步");
}
add_action('wp_ajax_wp_django_save_data', 'wp_django_save_data');

/**
 * 設定 AJAX URL
 */
function wp_django_localize_script() {
    wp_localize_script('wp-django-ws', 'wp_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'wp_django_localize_script');
add_action('admin_enqueue_scripts', 'wp_django_localize_script');

/**
 * Shortcode: [django_data]
 * 顯示 Django WebSocket 傳來的使用者資料
 */
function wp_django_display_user_data() {
    if (!is_user_logged_in()) {
        return "<p>❌ 請先登入以查看 Django 資料</p>";
    }

    $user_id = get_current_user_id();
    $labels = get_user_meta($user_id, 'django_labels', true);
    $classification = get_user_meta($user_id, 'django_classification', true);
    $analysis = get_user_meta($user_id, 'django_analysis', true);

    // ✅ 確保 labels 為陣列
    $labels = json_decode($labels, true);
    if (!is_array($labels)) {
        $labels = ["未接收到標籤"];
    }

    // ✅ HTML 格式化輸出
    ob_start();
    ?>
    <div class="django-data-container">
        <h3>🔍 Django WebSocket 資料</h3>
        <ul>
            <li><strong>標籤：</strong> <?php echo esc_html(implode(", ", $labels)); ?></li>
            <li><strong>分類結果：</strong> <?php echo esc_html($classification ?: "未分類"); ?></li>
            <li><strong>分析結果：</strong> <?php echo esc_html($analysis ?: "沒有分析結果"); ?></li>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('django_data', 'wp_django_display_user_data');

