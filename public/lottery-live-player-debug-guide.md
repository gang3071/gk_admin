# 摸獎券直播播放器 - 調試指南

## 🔍 調試面板功能說明

播放器已集成完整的調試系統，可以實時查看播放狀態、URL 參數、事件日誌等信息。

---

## 📖 使用方法

### 方法 1：點擊調試按鈕

頁面右上角有一個 **🐛 調試** 按鈕，點擊即可打開/關閉調試面板。

### 方法 2：URL 參數自動打開

在播放器 URL 中添加 `debug=1` 參數，頁面加載時自動打開調試面板：

```
http://localhost:8789/lottery-live-player.html?url=...&debug=1
```

### 方法 3：鍵盤快捷鍵

按 **D 鍵** 即可快速切換調試面板。

---

## 📊 調試面板內容

### 1️⃣ URL 參數區域

顯示當前頁面的所有 URL 參數：
- `url` - 直播地址
- `licenseUrl` / `license` - License URL
- `licenseKey` - License Key
- `debug` - 調試模式開關

**示例：**
```
url: webrtc://domain.liveplay.myqcloud.com/live/stream_id?txSecret=...
licenseUrl: https://license.vod2.myqcloud.com/license/...
licenseKey: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

---

### 2️⃣ 播放器配置區域

顯示播放器的核心配置信息：
- **協議類型** - FLV / HLS / WebRTC / RTMP
- **播放地址** - 實際播放的流地址（截取前 80 字符）
- **video.src** - video 元素的 src 屬性
- **License 配置** - License URL 和 Key 是否已配置

**示例：**
```
協議類型: webrtc
播放地址: webrtc://domain.liveplay.myqcloud.com/live/stream_id?txSecret=abc123...
video.src: blob:http://localhost:8789/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
License URL: ✅ 已配置
License Key: ✅ 已配置
```

---

### 3️⃣ 播放器狀態區域

實時顯示播放器的內部狀態（每秒更新）：

| 狀態項 | 說明 | 正常值 |
|--------|------|--------|
| **readyState** | 媒體就緒狀態 | 4 (HAVE_ENOUGH_DATA) |
| **networkState** | 網絡狀態 | 2 (NETWORK_LOADING) 或 1 (NETWORK_IDLE) |
| **paused** | 是否暫停 | false（播放中） |
| **currentTime** | 當前播放時間 | 遞增（直播流通常較小） |
| **error** | 錯誤信息 | 無錯誤 |

#### readyState 狀態碼說明：
- `0` - HAVE_NOTHING（無數據）
- `1` - HAVE_METADATA（元數據已加載）
- `2` - HAVE_CURRENT_DATA（當前幀已加載）
- `3` - HAVE_FUTURE_DATA（可以播放但可能會卡頓）
- `4` - HAVE_ENOUGH_DATA（足夠數據，流暢播放）✅

#### networkState 狀態碼說明：
- `0` - NETWORK_EMPTY（未初始化）
- `1` - NETWORK_IDLE（閒置，已選擇資源但未使用網絡）
- `2` - NETWORK_LOADING（正在下載數據）✅
- `3` - NETWORK_NO_SOURCE（未找到源）

---

### 4️⃣ 環境檢測區域

檢測瀏覽器和運行環境：

| 檢測項 | 說明 | 要求 |
|--------|------|------|
| **頁面協議** | HTTP / HTTPS | WebRTC 需要 HTTPS（或 localhost） |
| **主機名** | 當前訪問域名 | - |
| **瀏覽器** | 瀏覽器類型和版本 | 現代瀏覽器 |
| **WebRTC 可用** | WebRTC 協議是否可用 | ✅ 是（HTTPS）或 ❌ 否（HTTP） |
| **TCPlayer 版本** | 騰訊雲播放器版本 | v5.1.0 |

**重要提示：**
- ⚠️ 如果使用 **HTTP 協議訪問頁面**，WebRTC 播放會被瀏覽器阻止
- ✅ 解決方案：使用 **HTTPS** 或在 **localhost** 上測試

---

### 5️⃣ 事件日誌區域

顯示最近 10 條播放器事件（按時間倒序）：

**日誌類型：**
- 🟢 **綠色**（info/success）- 正常事件
- 🟡 **黃色**（warning）- 警告事件
- 🔴 **紅色**（error）- 錯誤事件

**常見事件：**
```
[17:30:45.123] ✅ loadedmetadata - 元數據加載完成
[17:30:45.456] ✅ canplay - 可以開始播放
[17:30:45.789] ▶️ playing - 正在播放中
[17:30:50.000] ⏰ [10秒後狀態檢查]
```

**錯誤事件示例：**
```
[17:30:30.123] ❌ Error Code 2: MEDIA_ERR_NETWORK - 網絡錯誤或推流未開始
[17:30:30.456] ❌ Error Code 55: LICENSE_ERROR - License 配置錯誤
```

---

## 🐛 常見問題診斷

### 問題 1：Error Code 55 - License 錯誤

**症狀：**
```
❌ License 錯誤 (Error 55)
```

**診斷步驟：**
1. 打開調試面板 → 查看 **URL 參數區域**
2. 檢查：
   - `licenseUrl` 或 `license` 是否存在？
   - `licenseKey` 是否存在？

**解決方案：**
- ✅ 確認活動管理後台已配置 License URL 和 Key
- ✅ 檢查 URL 參數拼寫（`licenseUrl` 或 `license` 都可以）
- ✅ 檢查 License URL 格式：`https://license.vod2.myqcloud.com/license/v1/...`

---

### 問題 2：Error Code 2 - 網絡錯誤/推流未開始

**症狀：**
```
❌ 網絡錯誤：無法加載直播流
可能原因：
1. 直播尚未開始（最常見）
2. 網絡連接問題
3. 直播地址已過期（txTime）
```

**診斷步驟：**
1. 檢查 **OBS 是否已開始推流**？
   - 打開 OBS → 查看 "開始串流" 按鈕是否為綠色
   - 查看 OBS 底部狀態欄是否顯示碼率（有數據表示正在推流）

2. 檢查 **播放地址是否正確**？
   - 調試面板 → URL 參數 → 查看 `url` 參數
   - 確認域名、StreamID、簽名參數正確

3. 檢查 **txTime 是否過期**？
   - 播放地址中的 `txTime` 參數是 Unix 時間戳（十六進制）
   - 過期後無法播放，需要重新生成地址

**解決方案：**
- ✅ 確保 OBS 已開始推流
- ✅ 重新生成播放地址（如果 txTime 過期）
- ✅ 檢查網絡連接

---

### 問題 3：WebRTC 無法播放（HTTP 環境）

**症狀：**
```
⚠️ WebRTC 播放需要 HTTPS
當前頁面使用 HTTP 協議，WebRTC 無法播放。
```

**診斷步驟：**
1. 調試面板 → **環境檢測區域**
2. 查看：
   - **頁面協議** - 是否為 `http:`？
   - **WebRTC 可用** - 是否顯示 `❌ 否 (需要 HTTPS)`？

**解決方案（3 選 1）：**

**方案 1：使用 HTTPS 訪問**
```
https://your-domain.com/lottery-live-player.html?url=webrtc://...
```

**方案 2：使用 localhost（開發環境）**
```
http://localhost:8789/lottery-live-player.html?url=webrtc://...
http://127.0.0.1:8789/lottery-live-player.html?url=webrtc://...
```

**方案 3：改用 FLV 協議（推薦）**
```
http://your-domain.com/lottery-live-player.html?url=http://xxx.flv
```
- FLV 支持 HTTP 和 HTTPS
- 延遲低（2-5 秒）
- 兼容性好

---

### 問題 4：播放 10 秒後仍未開始

**症狀：**
```
⚠️ 10秒後仍未開始播放，可能原因：推流未開始或網絡問題
```

**診斷步驟：**
1. 調試面板 → **播放器狀態區域**
2. 查看：
   - `readyState` - 是否為 `0` 或 `1`？（正常應為 `4`）
   - `networkState` - 是否為 `3` (NETWORK_NO_SOURCE)？
   - `error` - 是否有錯誤？

**解決方案：**
- ✅ 檢查推流是否開始（OBS）
- ✅ 檢查播放地址是否正確
- ✅ 檢查網絡連接（防火牆、代理）

---

## 📝 日誌記錄詳解

### 完整的播放流程日誌（正常情況）

```
[17:30:00.000] 🚀 頁面加載完成，開始初始化
[17:30:00.100] ========== 開始初始化播放器 ==========
[17:30:00.150] 📋 解析 URL 參數
[17:30:00.200] ✅ 檢測到協議類型: webrtc (WebRTC（超低延遲 <1秒）)
[17:30:00.250] 🎬 準備播放: webrtc://domain.liveplay.myqcloud.com/...
[17:30:00.300] ⚙️ 構建播放器配置...
[17:30:00.350] ✅ License 已配置
[17:30:00.400] 📹 使用 WebRTC 格式
[17:30:00.450] ✅ 播放器容器已找到
[17:30:00.500] 🎬 正在創建播放器實例...
[17:30:00.600] ✅ 播放器實例創建成功
[17:30:00.650] 📊 初始狀態 - readyState: 0, networkState: 0
[17:30:01.000] 📡 loadstart - 開始加載媒體
[17:30:02.500] ✅ loadedmetadata - 元數據加載完成
[17:30:02.550] 📊 視頻信息 - 尺寸: 1920x1080
[17:30:02.600] ✅ canplay - 可以開始播放
[17:30:02.650] ▶️ playing - 正在播放中
[17:30:10.000] ⏰ [10秒後狀態檢查] readyState: 4, networkState: 2, paused: false
```

### 錯誤流程日誌（License 錯誤）

```
[17:30:00.000] 🚀 頁面加載完成，開始初始化
[17:30:00.100] ========== 開始初始化播放器 ==========
[17:30:00.150] 📋 解析 URL 參數
[17:30:00.200] ⚠️ License 未提供，可能會出現 Error 55
[17:30:00.600] ✅ 播放器實例創建成功
[17:30:01.000] ❌ 播放器錯誤事件觸發 (code: 55)
[17:30:01.050] ❌ Error Code 55: LICENSE_ERROR - License 配置錯誤
[17:30:01.100] ❌ 顯示錯誤提示給用戶
```

---

## 🎯 調試技巧

### 技巧 1：對比正常流程日誌

將您的日誌與上面的「正常流程日誌」對比：
- ✅ 如果流程一致，播放應該正常
- ❌ 如果中途斷開，斷開處即為問題所在

### 技巧 2：關注時間戳

查看事件發生的時間間隔：
- **0-1 秒** - 播放器初始化
- **1-3 秒** - 正常連接和加載
- **> 3 秒** - 連接較慢（會顯示警告）
- **> 10 秒** - 可能有問題（推流未開始或網絡問題）

### 技巧 3：使用瀏覽器開發者工具

結合瀏覽器控制台（F12）：
1. **Console 標籤** - 查看詳細的 JavaScript 錯誤
2. **Network 標籤** - 查看網絡請求（License URL、播放地址）
3. **Media 標籤**（Chrome）- 查看媒體播放器詳細狀態

### 技巧 4：截圖/錄屏調試面板

如果遇到問題需要報告：
1. 打開調試面板（按 D 鍵）
2. 截圖或錄屏整個面板
3. 提供給技術支持團隊

---

## 📞 獲取幫助

如果問題無法解決，請提供以下信息：

1. **調試面板截圖**（包含所有 5 個區域）
2. **完整的事件日誌**（最近 20-50 條）
3. **瀏覽器控制台錯誤**（F12 → Console）
4. **訪問的完整 URL**（可隱藏敏感參數）
5. **OBS 推流狀態**（是否正在推流）
6. **網絡環境**（HTTP/HTTPS、域名/IP）

---

## 🔧 開發者備註

### 調試面板技術細節

- **更新頻率** - 每 1 秒自動更新播放器狀態
- **日誌容量** - 保留最近 50 條日誌（顯示最近 10 條）
- **性能影響** - 調試面板關閉時不影響播放性能
- **持久化** - 日誌僅保存在內存中（頁面刷新後清空）

### 日誌級別

- `info` - 一般信息（藍綠色）
- `success` - 成功事件（綠色）
- `warning` - 警告事件（黃色）
- `error` - 錯誤事件（紅色）

### 快捷鍵

- `D` 鍵 - 切換調試面板

### URL 參數

- `debug=1` - 自動打開調試面板
- `debug=true` - 自動打開調試面板

---

## 🎉 總結

調試系統提供了：
- ✅ 實時播放器狀態監控
- ✅ 完整的事件日誌記錄
- ✅ 環境兼容性檢測
- ✅ 詳細的錯誤診斷
- ✅ 便捷的操作方式（按鈕/快捷鍵/URL 參數）

祝您調試順利！🚀
