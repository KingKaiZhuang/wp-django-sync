<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Django_Ajax_API {
    /**
     * AJAX 儲存 Django WebSocket 傳來的數據
     */
    public static function save_data() {
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

    /**
     * AJAX 接收 Django 傳來的 Base64 圖片，存入 WordPress 媒體庫
     */
    public static function upload_image() {
        if (!isset($_POST['image']) || empty($_POST['image'])) {
            wp_send_json_error("❌ 圖片資料缺失");
        }

        $image_data = base64_decode($_POST['image']);
        if (!$image_data) {
            wp_send_json_error("❌ 圖片解碼失敗");
        }

        // ✅ 產生唯一的檔案名稱
        $upload_dir = wp_upload_dir();
        $filename = 'django_image_' . time() . '.jpg';
        $file_path = $upload_dir['path'] . '/' . $filename;
        $file_url = $upload_dir['url'] . '/' . $filename;

        // ✅ 儲存圖片
        file_put_contents($file_path, $image_data);

        // ✅ 插入 WordPress 媒體庫
        $attachment_id = wp_insert_attachment([
            'guid'           => $file_url,
            'post_mime_type' => 'image/jpeg',
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ], $file_path);

        // ✅ 產生圖片 metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        wp_send_json_success(["image_url" => $file_url]);
    }

    /**
     * AJAX 刪除記錄
     */
    public static function delete_record() {
        if (!is_user_logged_in()) {
            wp_send_json_error("❌ 未登入用戶");
        }

        if (!isset($_POST['record_id'])) {
            wp_send_json_error("❌ 缺少記錄 ID");
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'django_classifications';

        // ✅ 驗證記錄是否屬於該用戶
        $record_id = intval($_POST['record_id']);
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $record_id, $user_id));

        if (!$record) {
            wp_send_json_error("❌ 記錄不存在或無權限刪除");
        }

        // ✅ 刪除記錄
        $wpdb->delete($table_name, array('id' => $record_id));

        wp_send_json_success("✅ 記錄已刪除");
    }

    /**
     * AJAX 儲存 Django WebSocket 傳來的超音波數據
     */
    public static function save_ultrasonic() {
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
}


// ✅ 註冊 AJAX API
add_action('wp_ajax_wp_django_save_data', array('WP_Django_Ajax_API', 'save_data'));
add_action('wp_ajax_nopriv_wp_django_save_data', array('WP_Django_Ajax_API', 'save_data')); // 允許未登入用戶請求

// ✅ 註冊 AJAX API
add_action('wp_ajax_wp_django_upload_image', array('WP_Django_Ajax_API', 'upload_image'));
add_action('wp_ajax_nopriv_wp_django_upload_image', array('WP_Django_Ajax_API', 'upload_image')); // 允許未登入用戶請求

// ✅ 註冊 AJAX API
add_action('wp_ajax_wp_django_delete_record', array('WP_Django_Ajax_API', 'delete_record'));

// ✅ 註冊 AJAX API
add_action("wp_ajax_wp_django_save_ultrasonic", array('WP_Django_Ajax_API', 'save_ultrasonic'));
add_action("wp_ajax_nopriv_wp_django_save_ultrasonic", array('WP_Django_Ajax_API', 'save_ultrasonic')); // 允許未登入用戶請求

