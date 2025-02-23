let socket;
function connectWebSocket() {
  if (socket && socket.readyState === WebSocket.OPEN) {
    console.log("✅ WebSocket 已連線");
    return;
  }

  socket = new WebSocket("ws://127.0.0.1:8000/ws/wordpress/");
  console.log("🌐 嘗試連接 Django WebSocket...");

  socket.onopen = function () {
    console.log("✅ WebSocket 連線成功！");
  };

  socket.onmessage = function (event) {
    console.log("📩 [WordPress] 接收到 Django 訊息: ", event.data);
    const data = JSON.parse(event.data);

    // ✅ 將 Django 傳來的資料儲存到 WordPress
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
  // ✅ 先確保 `data.image` 存在
  if (!data.image || data.image.trim() === "") {
    console.log("❌ [WordPress] 未收到圖片數據，跳過圖片上傳");
    data.image_url = "";
    sendDataToServer(data); // 直接儲存
    return;
  }

  // ✅ 先上傳圖片，取得 URL 後再存入 WordPress
  wp_django_upload_image(data.image, function (image_url) {
    console.log("📸 [WordPress] 圖片已成功上傳: ", image_url);
    data.image_url = image_url; // ✅ 新增 image_url
    sendDataToServer(data);
  });
}

// ✅ 將資料傳送至 WordPress AJAX
function sendDataToServer(data) {
  const ajaxurl = wp_ajax.ajax_url;
  const requestData = {
    action: "wp_django_save_data",
    labels: JSON.stringify(data.labels),
    classification: data.classification,
    analysis: data.analysis,
    image_url: data.image_url || "", // ✅ 確保不為 undefined
  };

  jQuery
    .post(ajaxurl, requestData, function (response) {
      console.log("✅ [WordPress] Django 資料已儲存: ", response);
    })
    .fail(function (error) {
      console.log("❌ [WordPress] Django 資料儲存失敗: ", error);
    });
}

function wp_django_upload_image(imageBase64, callback) {
  const formData = new FormData();
  formData.append("action", "wp_django_upload_image");
  formData.append("image", imageBase64);

  jQuery.ajax({
    url: wp_ajax.ajax_url,
    type: "POST",
    data: formData,
    processData: false,
    contentType: false,
    success: function (response) {
      console.log("📸 圖片已上傳: ", response);
      callback(response.data.image_url);
    },
    error: function (error) {
      console.log("❌ 圖片上傳失敗: ", error);
      callback("");
    },
  });
}
