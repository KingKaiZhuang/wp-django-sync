<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Django_Shortcodes {
    /**
     * 註冊 Shortcode
     */
    public static function register_shortcodes() {
        add_shortcode('django_data', array('WP_Django_Shortcodes', 'display_user_data'));
    }

    /**
     * Shortcode: [django_data]
     * Elementor 顯示 Django WebSocket 數據
     */
    public static function display_user_data($atts) {
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
}

// ✅ 註冊 Shortcode
add_action('init', array('WP_Django_Shortcodes', 'register_shortcodes'));
