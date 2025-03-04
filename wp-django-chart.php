<?php
if (!defined('ABSPATH')) {
    exit; // 安全性防護，避免直接訪問
}

/**
 * Shortcode: [django_chart]
 * 在 WordPress 前端顯示 Django 分類統計圖表（3D 立體版）
 */
function wp_django_render_chart() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'django_classifications';

    // 查詢分類數據
    $query = "SELECT classification, COUNT(*) as count FROM $table_name GROUP BY classification";
    $results = $wpdb->get_results($query);

    $recyclable = 0;
    $non_recyclable = 0;

    foreach ($results as $record) {
        if ($record->classification == "1") {
            $recyclable = (int)$record->count;
        } else {
            $non_recyclable = (int)$record->count;
        }
    }

    ob_start();
    ?>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/highcharts-3d.js"></script> <!-- 引入 3D 圖表支持 -->
    <div id="classification-chart" style="width:100%; height:400px;"></div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            Highcharts.chart('classification-chart', {
                chart: {
                    type: 'pie',
                    options3d: {
                        enabled: true,
                        alpha: 45, // 調整角度
                        beta: 0
                    }
                },
                title: { text: '垃圾分類統計' },
                plotOptions: {
                    pie: {
                        innerSize: 50, // 內圈大小，增加 3D 效果
                        depth: 45, // 立體深度
                        allowPointSelect: true,
                        cursor: 'pointer',
                        dataLabels: {
                            enabled: true,
                            format: '<b>{point.name}</b>: {point.y}'
                        }
                    }
                },
                series: [{
                    name: '數量',
                    data: [
                        { name: '♻️ 可回收', y: <?php echo $recyclable; ?>, sliced: true, selected: true },
                        { name: '🗑️ 一般垃圾', y: <?php echo $non_recyclable; ?> }
                    ]
                }]
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('django_chart', 'wp_django_render_chart');
