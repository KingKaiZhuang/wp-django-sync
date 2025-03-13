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
        $page_id = wp_insert_post(array(
            'post_title'    => $page_title,
            'post_content'  => $page_content,
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => $page_slug,
        ));
    }
}
register_activation_hook(__FILE__, 'wp_django_create_chart_page');


/**
 * 停用插件時刪除 Django 資料表
 */
register_deactivation_hook(__FILE__, 'wp_django_remove_table');

// 引入 Chart 頁面
require_once plugin_dir_path(__FILE__) . 'wp-django-chart.php';

/**
 * 載入自訂 CSS
 */
function wp_django_enqueue_custom_styles() {
    wp_enqueue_style('wp-django-custom-styles', plugin_dir_url(__FILE__) . 'styles.css', array(), '1.0.3');
}
add_action('wp_enqueue_scripts', 'wp_django_enqueue_custom_styles');

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
function wp_django_display_user_data($atts) {
    if (!is_user_logged_in()) {
        return "<p>❌ 請先登入以查看 Django 資料</p>";
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'django_classifications';

    // 取得目前頁數
    $paged = isset($_GET['django_page']) ? max(1, intval($_GET['django_page'])) : 1;
    $per_page = 9; // 每頁顯示 9 筆
    $offset = ($paged - 1) * $per_page;

    // 取得資料並計算總筆數
    $records = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $user_id, $per_page, $offset
    ));

    $total_records = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    if (!$records) {
        return "<p>📭 尚無偵測記錄</p>";
    }

    ob_start();
    ?>
    <div class="container django-data-container">
        <h3 class="text-center my-4">🔍 Django WebSocket 偵測記錄</h3>
        
        <div class="row">
            <?php foreach ($records as $record) : ?>
                <?php
                    $image_url = isset($record->image_url) && !empty($record->image_url) 
                        ? esc_url($record->image_url) 
                        : 'https://via.placeholder.com/250';

                    $classification_text = ($record->classification == "1") ? "♻️ 可回收" : "🗑️ 一般垃圾";

                    // 生成詳細頁面 URL
                    $detail_url = get_permalink(get_page_by_path('django-detail')) . "?id=" . esc_attr($record->id);
                ?>
                <div class="col-md-4">
                    <a href="<?php echo $detail_url; ?>" class="text-decoration-none" aria-label="查看詳細偵測記錄">
                        <div class="card cool-card mb-4 shadow-lg wow animate__animated animate__fadeInUp" data-wow-delay="0.2s">
                            <img src="<?php echo $image_url; ?>" class="card-img-top" alt="偵測圖片">
                            <div class="card-body text-center">
                                <h5 class="card-title"><?php echo $classification_text; ?></h5>
                                <p class="text-muted"><i class="far fa-clock"></i> <?php echo esc_html($record->created_at); ?></p>
                            </div>
                            <div class="card-footer text-center">
                                <p class="btn btn-danger delete-record-btn" data-record-id="<?php echo esc_attr($record->id); ?>">
                                    刪除
                                </p>
                            </div>
                        </div>
                    </a>
                    <!-- ✅ 加入刪除按鈕 -->
                </div>
            <?php endforeach; ?>

        </div>


        <!-- 分頁按鈕 -->
        <div class="d-flex justify-content-center">
            <?php
            $total_pages = ceil($total_records / $per_page);
            if ($total_pages > 1) {
                echo '<nav><ul class="pagination">';
                for ($i = 1; $i <= $total_pages; $i++) {
                    echo '<li class="page-item ' . ($i == $paged ? 'active' : '') . '">';
                    echo '<a class="page-link" href="' . esc_url(add_query_arg('django_page', $i)) . '">' . $i . '</a>';
                    echo '</li>';
                }
                echo '</ul></nav>';
            }
            ?>
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

/**
 * AJAX 刪除記錄
 */
function wp_django_delete_record() {
    if (!is_user_logged_in()) {
        wp_send_json_error("❌ 未登入用戶");
    }

    if (!isset($_POST['record_id'])) {
        wp_send_json_error("❌ 缺少記錄 ID");
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'django_classifications';

    // 驗證記錄是否屬於該用戶
    $record_id = intval($_POST['record_id']);
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $record_id, $user_id));

    if (!$record) {
        wp_send_json_error("❌ 記錄不存在或無權限刪除");
    }

    // 刪除記錄
    $wpdb->delete($table_name, array('id' => $record_id));

    wp_send_json_success("✅ 記錄已刪除");
}
add_action('wp_ajax_wp_django_delete_record', 'wp_django_delete_record');

// 超音波距離

/**
 * 創建超音波感測資料表
 */
function wp_django_create_ultrasonic_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'django_ultrasonic_data';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        ultrasonic1 INT NOT NULL,
        ultrasonic2 INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'wp_django_create_ultrasonic_table');

/**
 * AJAX 儲存 Django WebSocket 傳來的超音波數據
 */
function wp_django_save_ultrasonic() {
    // ✅ 檢查請求是否帶有數據
    if (!isset($_POST['ultrasonic1']) || !isset($_POST['ultrasonic2'])) {
        wp_send_json_error(["message" => "❌ 缺少 ultrasonic 數據"]);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'django_ultrasonic_data';

    // ✅ 插入數據
    $result = $wpdb->insert($table_name, [
        "ultrasonic1" => intval($_POST["ultrasonic1"]),
        "ultrasonic2" => intval($_POST["ultrasonic2"]),
        "created_at" => current_time("mysql")
    ]);

    if ($result === false) {
        wp_send_json_error(["message" => "❌ 資料儲存失敗"]);
    } else {
        wp_send_json_success(["message" => "✅ 超音波數據已儲存"]);
    }

    exit(); // ❗ 確保 WordPress 停止執行，防止意外回傳 `0`
}

// ✅ 註冊 AJAX API
add_action("wp_ajax_wp_django_save_ultrasonic", "wp_django_save_ultrasonic");
add_action("wp_ajax_nopriv_wp_django_save_ultrasonic", "wp_django_save_ultrasonic"); // 允許未登入用戶請求


/**
 * 在 WordPress Admin Bar 顯示超音波感測數據
 */
function wp_django_add_ultrasonic_status($wp_admin_bar) {
    if (is_user_logged_in()) {
        $args = array(
            'id'    => 'wp_django_ultrasonic_status',
            'title' => '📡 超音波感測: --',
            'href'  => '#'
        );
        $wp_admin_bar->add_node($args);
    }
}
add_action('admin_bar_menu', 'wp_django_add_ultrasonic_status', 100);

/**
 * 更新 Admin Bar 顯示的超音波感測數據
 */
function wp_django_update_admin_bar() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'django_ultrasonic_data';

    $latest = $wpdb->get_row("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 1");
    if ($latest) {
        echo "<script>
            document.getElementById('wp-admin-bar-wp_django_ultrasonic_status').innerText = '📡 超音波感測: {$latest->ultrasonic1}, {$latest->ultrasonic2}';
        </script>";
    }
}
add_action('wp_footer', 'wp_django_update_admin_bar');
