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
  if (!data.image || data.image.trim() === "") {
    console.log("❌ [WordPress] 未收到圖片數據，跳過圖片上傳");
    data.image_url = "";
    sendDataToServer(data);
    return;
  }

  wp_django_upload_image(data.image, function (image_url) {
    console.log("📸 [WordPress] 圖片已成功上傳: ", image_url);
    data.image_url = image_url;
    sendDataToServer(data);
  });
}

function sendDataToServer(data) {
  const ajaxurl = wp_ajax.ajax_url;
  const requestData = {
    action: "wp_django_save_data",
    labels: JSON.stringify(data.labels),
    classification: data.classification,
    analysis: data.analysis,
    image_url: data.image_url || "",
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

/**
 * ✅ 初始化所有刪除按鈕
 */
document.addEventListener("DOMContentLoaded", function () {
  initializeDeleteButtons();
});

/**
 * ✅ 綁定刪除按鈕點擊事件
 */
function initializeDeleteButtons() {
  document.querySelectorAll(".delete-record-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const recordId = this.getAttribute("data-record-id");
      deleteRecord(recordId);
    });
  });
}

/**
 * ✅ 刪除記錄函式
 * @param {number} recordId - 要刪除的記錄 ID
 */
function deleteRecord(recordId) {
  if (!confirm("確定要刪除此記錄嗎？")) return;

  fetch(wp_ajax.ajax_url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      action: "wp_django_delete_record",
      record_id: recordId,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        alert(data.data);
        location.reload(); // ✅ 重新整理頁面以更新記錄列表
      } else {
        alert(data.data);
      }
    })
    .catch((error) => {
      console.error("❌ 刪除失敗:", error);
      alert("❌ 刪除失敗，請稍後再試");
    });
}

// 超音波距離
document.addEventListener("DOMContentLoaded", function () {
  const djangoSocket = new WebSocket("ws://127.0.0.1:8000/ws/ultrasonic/");

  djangoSocket.onopen = function () {
    console.log("✅ 連接到 Django WebSocket - Ultrasonic Sensor");
  };

  djangoSocket.onmessage = function (event) {
    let data = JSON.parse(event.data);
    console.log("📩 收到 Django WebSocket 數據:", data);

    // 在 WordPress WebSocket 內部觸發事件（如果插件支援）
    if (typeof window.WordPressWebSocket !== "undefined") {
      window.WordPressWebSocket.send(JSON.stringify(data));
    }

    // ✅ 發送 AJAX，儲存最新的超音波數據
    saveUltrasonicDataToWordPress(data);
  };

  djangoSocket.onclose = function (event) {
    console.log("❌ Django WebSocket 斷線:", event);
  };

  djangoSocket.onerror = function (error) {
    console.error("🚨 Django WebSocket 錯誤:", error);
  };
});

function saveUltrasonicDataToWordPress(data) {
  if (!data.ultrasonic1 || !data.ultrasonic2) {
    console.error("🚨 無效的超音波數據:", data);
    return;
  }

  fetch(wp_ajax.ajax_url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      action: "wp_django_save_ultrasonic", // ✅ 確保 `action` 參數存在
      ultrasonic1: data.ultrasonic1,
      ultrasonic2: data.ultrasonic2,
    }),
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`❌ HTTP 錯誤! 狀態碼: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => console.log("✅ WordPress API 回應:", data))
    .catch((error) => console.error("🚨 AJAX 失敗:", error));
}
