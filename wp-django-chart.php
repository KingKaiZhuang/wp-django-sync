<?php
if (!defined('ABSPATH')) {
    exit; // 安全性防護，避免直接訪問
}

/**
 * Shortcode: [django_chart]
 * 在 WordPress 前端顯示垃圾分類統計圖表 + 最新超音波感測數據（3D 柱狀圖）
 */
function wp_django_render_chart() {
    global $wpdb;
    $table_classification = $wpdb->prefix . 'django_classifications';
    $table_ultrasonic = $wpdb->prefix . 'django_ultrasonic_data';

    // ✅ 取得垃圾分類統計數據
    $query = "SELECT classification, COUNT(*) as count FROM $table_classification GROUP BY classification";
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

    // ✅ 取得最新的超音波數據
    $latest = $wpdb->get_row("SELECT ultrasonic1, ultrasonic2, created_at FROM $table_ultrasonic ORDER BY created_at DESC LIMIT 1");

    $ultrasonic1 = $latest ? (int)$latest->ultrasonic1 : 0;
    $ultrasonic2 = $latest ? (int)$latest->ultrasonic2 : 0;
    $timestamp = $latest ? esc_js($latest->created_at) : '無數據';

    ob_start();
    ?>
    
    <!-- Highcharts 圖表庫 -->
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/highcharts-3d.js"></script> <!-- 3D 圖表支持 -->

    <!-- 📊 垃圾分類統計圖 -->
    <div id="classification-chart" style="width:100%; height:400px;"></div>

    <!-- 📡 最新超音波數據 3D 柱狀圖 -->
    <div id="ultrasonic-chart" style="width:100%; height:400px; margin-top: 50px;"></div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // 📊 垃圾分類統計圖
            Highcharts.chart('classification-chart', {
                chart: {
                    type: 'pie',
                    options3d: {
                        enabled: true,
                        alpha: 45,
                        beta: 0
                    }
                },
                title: { text: '垃圾分類統計' },
                plotOptions: {
                    pie: {
                        innerSize: 50,
                        depth: 45,
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

            // 📡 最新超音波數據 3D 柱狀圖
            const chart = new Highcharts.Chart({
                chart: {
                    renderTo: 'ultrasonic-chart',
                    type: 'column',
                    options3d: {
                        enabled: true,
                        alpha: 15,
                        beta: 15,
                        depth: 50,
                        viewDistance: 25
                    }
                },
                xAxis: {
                    categories: ['超音波 1', '超音波 2']
                },
                yAxis: {
                    title: { text: '距離 (cm)' }
                },
                title: {
                    text: '最新超音波感測數據'
                },
                subtitle: {
                    text: '更新時間: <?php echo $timestamp; ?>'
                },
                legend: {
                    enabled: false
                },
                plotOptions: {
                    column: { depth: 25 }
                },
                series: [{
                    name: '距離 (cm)',
                    data: [<?php echo $ultrasonic1; ?>, <?php echo $ultrasonic2; ?>],
                    colorByPoint: true
                }]
            });

            function updateChart() {
                fetch(wp_ajax.ajax_url, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({ "action": "wp_django_get_latest_ultrasonic" })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        chart.series[0].setData([data.data.ultrasonic1, data.data.ultrasonic2]);
                        chart.setTitle(null, { text: '更新時間: ' + data.data.timestamp });
                    }
                })
                .catch(error => console.error("🚨 AJAX 錯誤:", error));
            }

            // 每 10 秒更新一次
            setInterval(updateChart, 10000);
        });
    </script>

    <?php
    return ob_get_clean();
}

// ✅ 註冊 Shortcode
add_shortcode('django_chart', 'wp_django_render_chart');


