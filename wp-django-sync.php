<?php
/**
 * Plugin Name: WP Django Sync (WebSocket)
 * Description: 透過 WebSocket 即時同步 Django 資料
 * Version: 1.0.2
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 停用插件時刪除 Django 資料表
 */
register_deactivation_hook(__FILE__, 'wp_django_remove_table');

function wp_django_remove_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'django_classifications';

    // 刪除資料表
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
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
 * 創建 Django 資料表
 */
function wp_django_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'django_classifications';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        labels TEXT NOT NULL,
        classification VARCHAR(10) NOT NULL,
        analysis TEXT NOT NULL,
        image_url TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'wp_django_create_table');

/**
 * 載入 WebSocket JavaScript
 */
function wp_django_enqueue_websocket_script() {
    wp_enqueue_script('wp-django-ws', plugin_dir_url(__FILE__) . 'wp-django-ws.js', array('jquery'), '1.0.2', true);
}
add_action('wp_enqueue_scripts', 'wp_django_enqueue_websocket_script');
add_action('admin_enqueue_scripts', 'wp_django_enqueue_websocket_script');

/**
 * AJAX 儲存 Django WebSocket 傳來的數據
 */
function wp_django_save_data() {
    if (!is_user_logged_in()) {
        wp_send_json_error("❌ 未登入用戶");
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'django_classifications';

    // 確保 labels 為 JSON
    $labels = json_decode(stripslashes($_POST['labels']), true);
    if (!is_array($labels)) {
        wp_send_json_error("❌ 標籤資料格式錯誤");
    }

    // 儲存新的記錄
    $wpdb->insert($table_name, array(
        'user_id'       => $user_id,
        'labels'        => json_encode($labels),
        'classification'=> sanitize_text_field($_POST['classification']),
        'analysis'      => sanitize_textarea_field($_POST['analysis']),
        'image_url'     => esc_url($_POST['image_url']),
        'created_at'    => current_time('mysql')
    ));

    wp_send_json_success("✅ Django 資料已儲存");
}
add_action('wp_ajax_wp_django_save_data', 'wp_django_save_data');
add_action('wp_ajax_nopriv_wp_django_save_data', 'wp_django_save_data');

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
 * Elementor 顯示 Django WebSocket 數據
 */
function wp_django_display_user_data() {
    if (!is_user_logged_in()) {
        return "<p>❌ 請先登入以查看 Django 資料</p>";
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'django_classifications';

    $records = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
        $user_id
    ));

    if (!$records) {
        return "<p>📭 尚無偵測記錄</p>";
    }

    ob_start();
    ?>
    <div class="django-data-container">
        <h3>🔍 Django WebSocket 資料</h3>
        <div class="django-card-list">
            <?php foreach ($records as $record) : ?>
                <?php
                    // ✅ 避免 `Undefined property`
                    $image_url = isset($record->image_url) && !empty($record->image_url) 
                        ? esc_url($record->image_url) 
                        : 'https://via.placeholder.com/250';
                ?>
                <div class="django-card" onclick="window.location.href='<?php echo get_permalink(get_page_by_path('django-detail')); ?>?id=<?php echo esc_attr($record->id); ?>'">
                    <img src="<?php echo $image_url; ?>" alt="偵測圖片">
                    <div class="django-card-content">
                        <h4><?php echo $record->classification == "1" ? "♻️ 可回收" : "🗑️ 一般垃圾"; ?></h4>
                        <p><?php echo esc_html($record->created_at); ?></p>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('django_data', 'wp_django_display_user_data');


/**
 * 接收 Django 傳來的 Base64 圖片，存入 WordPress 媒體庫
 */
function wp_django_upload_image() {
    if (!isset($_POST['image']) || empty($_POST['image'])) {
        wp_send_json_error("❌ 圖片資料缺失");
    }

    $image_data = base64_decode($_POST['image']);
    if (!$image_data) {
        wp_send_json_error("❌ 圖片解碼失敗");
    }

    // 產生唯一的檔案名稱
    $upload_dir = wp_upload_dir();
    $filename = 'django_image_' . time() . '.jpg';
    $file_path = $upload_dir['path'] . '/' . $filename;
    $file_url = $upload_dir['url'] . '/' . $filename;

    // 儲存圖片
    file_put_contents($file_path, $image_data);

    // 插入 WordPress 媒體庫
    $attachment_id = wp_insert_attachment([
        'guid'           => $file_url,
        'post_mime_type' => 'image/jpeg',
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit'
    ], $file_path);

    // 產生圖片 metadata
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
    wp_update_attachment_metadata($attachment_id, $attach_data);

    wp_send_json_success(["image_url" => $file_url]);
}
add_action('wp_ajax_wp_django_upload_image', 'wp_django_upload_image');
add_action('wp_ajax_nopriv_wp_django_upload_image', 'wp_django_upload_image');
