<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Django_Ultrasonic {
    /**
     * AJAX 儲存 Django WebSocket 傳來的超音波數據（先刪除舊數據）
     */
    public static function save_ultrasonic() {
        // ✅ 檢查請求是否帶有數據
        if (!isset($_POST['ultrasonic1']) || !isset($_POST['ultrasonic2'])) {
            wp_send_json_error(["message" => "❌ 缺少 ultrasonic 數據"]);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'django_ultrasonic_data';

        // ✅ 先刪除所有舊數據
        $wpdb->query("DELETE FROM $table_name");

        // ✅ 插入最新的超音波數據
        $result = $wpdb->insert($table_name, [
            "ultrasonic1" => intval($_POST["ultrasonic1"]),
            "ultrasonic2" => intval($_POST["ultrasonic2"]),
            "created_at" => current_time("mysql")
        ]);

        if ($result === false) {
            wp_send_json_error(["message" => "❌ 資料儲存失敗"]);
        } else {
            wp_send_json_success(["message" => "✅ 超音波數據已更新"]);
        }

        exit(); // ❗ 確保 WordPress 停止執行，防止意外回傳 `0`
    }
    
    public static function add_ultrasonic_status($wp_admin_bar) {
        if (is_user_logged_in()) {
            $args = array(
                'id'    => 'wp_django_ultrasonic_status',
                'title' => '📡 超音波感測: --',
                'href'  => '#'
            );
            $wp_admin_bar->add_node($args);
        }
    }

    /**
     * 更新 WordPress Admin Bar 中的超音波感測數據
     */
    public static function update_admin_bar() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'django_ultrasonic_data';

        $latest = $wpdb->get_row("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 1");
        if ($latest) {
            echo "<script>
                document.getElementById('wp-admin-bar-wp_django_ultrasonic_status').innerText = '📡 超音波感測: {$latest->ultrasonic1}, {$latest->ultrasonic2}';
            </script>";
        }
    }
}

// ✅ 註冊 AJAX API
add_action("wp_ajax_wp_django_save_ultrasonic", array('WP_Django_Ultrasonic', 'save_ultrasonic'));
add_action("wp_ajax_nopriv_wp_django_save_ultrasonic", array('WP_Django_Ultrasonic', 'save_ultrasonic')); // 允許未登入用戶請求
// ✅ 註冊 WordPress Admin Bar 顯示超音波數據
add_action('admin_bar_menu', array('WP_Django_Ultrasonic', 'add_ultrasonic_status'), 100);
add_action('wp_footer', array('WP_Django_Ultrasonic', 'update_admin_bar'));
