let socket;
function connectWebSocket() {
  if (socket && socket.readyState === WebSocket.OPEN) {
    console.log("✅ WebSocket 已連線");
    alert("WebSocket 已經連接，不需要重複連接！");
    return;
  }

  socket = new WebSocket("ws://127.0.0.1:8000/ws/django-to-wordpress/");
  console.log("🌐 嘗試連接 Django WebSocket...");

  socket.onopen = function () {
    console.log("✅ WebSocket 連線成功！");
    alert("已成功連接 Django WebSocket！");
    socket.send(JSON.stringify({ request: "get_data" })); // ✅ 向 Django 請求資料
  };

  socket.onmessage = function (event) {
    console.log("📩 [WordPress] 接收到 Django 訊息: ", event.data);
    const data = JSON.parse(event.data);

    // ✅ 儲存 Django 傳來的資料到 WordPress 當前用戶
    wp_django_save_to_user(data);
  };

  socket.onerror = function (error) {
    console.log("❌ WebSocket 錯誤: ", error);
    alert("WebSocket 連線發生錯誤，請檢查伺服器狀態！");
  };

  socket.onclose = function () {
    console.log("🔴 WebSocket 連線已中斷");
    alert("WebSocket 連線已斷開！");
  };
}

function wp_django_save_to_user(data) {
  const ajaxurl = wp_ajax.ajax_url; // ✅ 透過 WordPress AJAX 處理
  const requestData = {
    action: "wp_django_save_data",
    labels: JSON.stringify(data.labels),
    classification: data.classification,
    analysis: data.analysis,
  };

  jQuery.post(ajaxurl, requestData, function (response) {
    console.log("✅ [WordPress] Django 資料已儲存: ", response);
    alert("Django 資料已更新！");
  });
}
