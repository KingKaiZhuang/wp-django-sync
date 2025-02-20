<?php
/**
 * Plugin Name: WP Django Sync
 * Description: 接收 Django 傳送的資料，並存入 WordPress 使用者資料
 * Version: 1.3.0
 * Author: Jun Kai
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 註冊 REST API 端點 (允許 Django 發送資料)
 */
add_action('rest_api_init', function () {
    register_rest_route('django/v1', '/save-data/', array(
        'methods'  => 'POST',
        'callback' => 'wp_django_save_data',
        'permission_callback' => '__return_true' // 測試時開放所有請求，正式環境應該加密
    ));
});

/**
 * 允許 CORS，確保 Django 可以訪問 WordPress API
 */
add_action('init', function () {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: http://127.0.0.1:8000"); // 允許 Django 來訪
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-WP-Nonce");
        header("Access-Control-Allow-Credentials: true");
    }

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        status_header(200);
        exit;
    }
});

/**
 * 接收 Django 發送的數據並存儲到 WordPress 使用者
 */
function wp_django_save_data(WP_REST_Request $request) {
    $params = $request->get_json_params();

    // 確保收到的資料完整
    if (!isset($params['email']) || !isset($params['labels']) || !isset($params['classification'])) {
        return new WP_REST_Response(['error' => '缺少必要參數'], 400);
    }

    $email = sanitize_email($params['email']);
    $labels = json_encode($params['labels']);
    $classification = intval($params['classification']);

    // 根據 Email 找到 WordPress 使用者
    $user = get_user_by('email', $email);

    if (!$user) {
        return new WP_REST_Response(['error' => '找不到對應的使用者'], 404);
    }

    // 儲存資料到 user_meta
    update_user_meta($user->ID, 'django_labels', $labels);
    update_user_meta($user->ID, 'django_classification', $classification);

    return new WP_REST_Response([
        'success' => 'Django 資料已儲存',
        'user_id' => $user->ID
    ], 200);
}

/**
 * 提供 CSRF Token API，確保 Django 可以獲取 CSRF Token
 */
add_action('rest_api_init', function () {
    register_rest_route('django/v1', '/get-csrf-token/', array(
        'methods'  => 'GET',
        'callback' => 'wp_django_get_csrf_token',
        'permission_callback' => '__return_true' // 允許未登入使用者存取
    ));
});

function wp_django_get_csrf_token(WP_REST_Request $request) {
    return new WP_REST_Response([
        'csrf_token' => wp_create_nonce('wp_rest')
    ], 200);
}
