<template>
  <div class="page-wrap">
    <!-- 頂部工具列 -->
    <div class="top-bar">
      <span class="page-title">{{ ex_admin_title }} — 配置說明</span>
      <a :href="grid_url" class="ant-btn ant-btn-default back-btn">← 返回編輯</a>
    </div>

    <div class="main-area">
      <!-- 左側：Tabs + 配置列表 -->
      <div class="list-wrap">
        <a-tabs v-model:activeKey="activeTab" type="card" @change="onTabChange">
          <a-tab-pane key="0" tab="總配置" />
          <a-tab-pane
            v-for="ch in channels"
            :key="String(ch.department_id)"
            :tab="ch.name"
          />
        </a-tabs>

        <div class="table-wrap">
          <a-spin :spinning="loading">
            <a-table
              :columns="columns"
              :custom-row="customRow"
              :data-source="rows"
              :pagination="false"
              :row-class-name="rowClass"
              row-key="feature"
              size="small"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'feature'">
                  <span class="feature-link" @click="selectFeature(record.feature)">
                    {{ featureLabel(record.feature) }}
                  </span>
                </template>
                <template v-else-if="column.key === 'value'">
                  <span class="value-cell">{{ displayValue(record) }}</span>
                </template>
                <template v-else-if="column.key === 'status'">
                  <a-tag :color="record.status ? 'success' : 'default'">
                    {{ record.status ? '啟用' : '停用' }}
                  </a-tag>
                </template>
              </template>
            </a-table>
          </a-spin>
        </div>
      </div>

      <!-- 右側：注解面板 -->
      <div class="ann-panel">
        <div class="ann-panel-title">📋 配置說明</div>

        <div v-if="activeFeature" class="ann-content">
          <div class="ann-feature-name">{{ featureLabel(activeFeature) }}</div>
          <div class="ann-desc" v-html="annotations[activeFeature] || '暫無說明'"></div>
        </div>

        <div v-else class="ann-empty">
          <div class="ann-empty-icon">📋</div>
          <div class="ann-empty-hint">點擊左側的功能名稱<br>查看詳細說明</div>
          <a-divider style="margin:12px 0 8px" />
          <div class="ann-index">
            <div
              v-for="feature in orderedFeatures"
              :key="feature"
              class="ann-index-item"
              @click="selectFeature(feature)"
            >{{ featureLabel(feature) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    ex_admin_title: { type: String, default: '系統配置' },
    channels:       { type: Array,  default: () => [] },
    data_url:       { type: String, default: '' },
    grid_url:       { type: String, default: '' },
  },

  data() {
    return {
      activeTab:     '0',
      loading:       false,
      settingsMap:   {},   // feature => row
      activeFeature: null,

      columns: [
        { title: '功能',   key: 'feature', dataIndex: 'feature', width: '38%' },
        { title: '配置值', key: 'value',   dataIndex: 'value',   width: '42%', ellipsis: true },
        { title: '狀態',   key: 'status',  dataIndex: 'status',  width: '20%', align: 'center' },
      ],

      // ── 功能顯示名稱（繁體中文） ──
      labels: {
        register_present:                        '注册成功贈送點數',
        marquee:                                 '用戶端跑馬燈',
        machine_marquee:                         '機台跑馬燈',
        machine_maintain:                        '機台週維護時間',
        client_maintain:                         '用戶端週維護時間',
        recharge_order_expiration:               '開分訂單過期時間',
        pending_minutes:                         '機台閒置自動保留',
        keeping_off:                             '保留停止時段',
        gift_keeping_minutes:                    '上分贈送保留時長',
        activity_open:                           '活動功能開關',
        settlement_date:                         '每月分潤結算日',
        max_keeping_minutes:                     '最大保留時間',
        line_customer:                           'LINE 客服地址',
        line_discussion_group:                   'LINE 討論群',
        spectator_num:                           '開獎觀看人數',
        client_version:                          '用戶端版本號',
        line_redirect_uri:                       'LINE 跳轉地址',
        line_key:                                'LINE Key',
        line_secret:                             'LINE Secret',
        commission:                              '開分手續費比例',
        app_password:                            '密碼管理',
        jackpot_screen_domain:                   '大屏彩金 Domain',
        turn_relay_ip:                           'TURN 中繼 IP',
        high_score_broadcast_threshold:          '高分廣播閾值',
        steel_ball_broadcast_threshold:          '鋼珠下珠廣播閾值',
        device_collect:                          '店機跨店登入',
        electronic_game_rebate:                  '電子遊戲反水',
        machine_rebate:                          '實體機台反水',
        ticket_machine_version:                  '儲值機版本號',
        ticket_machine_download_url:             '儲值機下載鏈接',
        vip_welcome_voice:                       'VIP 歡迎語音',
        leaderboard_golden_weekly:               '金樽遊戲量週排行',
        leaderboard_store_weekly:                '各店週排行',
        leaderboard_store_monthly:               '各店月排行',
        leaderboard_golden_monthly:              '金樽遊戲量月排行',
        leaderboard_steel_ball_global_weekly:    '金樽鋼珠週排行',
        leaderboard_steel_ball_store_weekly:     '各店鋼珠週排行',
        leaderboard_steel_ball_global_monthly:   '金樽鋼珠月排行',
        leaderboard_switch:                      '排行榜總開關',
      },

      // ── 各功能的詳細說明 ──
      annotations: {
        register_present:
          '<b>用途：</b>新玩家注册成功後自動發放的遊戲點數。<br>' +
          '<b>填寫：</b>填入正整數（點數），設為 0 表示不贈送。<br>' +
          '<b>注意：</b>此點數直接進入玩家錢包，不計入開分訂單。',

        marquee:
          '<b>用途：</b>顯示在玩家用戶端（APP）頂部的跑馬燈文字，可用於公告、活動說明。<br>' +
          '<b>限制：</b>最多 100 個字元。<br>' +
          '<b>注意：</b>留空則不顯示跑馬燈。',

        machine_marquee:
          '<b>用途：</b>顯示在實體機台螢幕上的跑馬燈文字。<br>' +
          '<b>限制：</b>最多 100 個字元。',

        machine_maintain:
          '<b>用途：</b>設定每週固定的機台維護時間段，維護期間機台自動停機並鎖定，玩家無法使用。<br>' +
          '<b>填寫：</b>選擇星期幾 + 開始/結束時間。<br>' +
          '<b>注意：</b>維護期間所有機台均受影響，請提前公告玩家。',

        client_maintain:
          '<b>用途：</b>設定每週固定的用戶端（APP）維護時間段，維護期間玩家無法登入。<br>' +
          '<b>填寫：</b>選擇星期幾 + 開始/結束時間。',

        recharge_order_expiration:
          '<b>用途：</b>玩家提交開分訂單後，若超過設定分鐘數未完成付款，訂單自動取消。<br>' +
          '<b>填寫：</b>填入分鐘數（正整數）。<br>' +
          '<b>注意：</b>設定過長會佔用庫存，設定過短會讓玩家來不及付款。',

        pending_minutes:
          '<b>用途：</b>玩家離開座位後，機台閒置超過此分鐘數，系統自動進入「保留」狀態，等待玩家回來。<br>' +
          '<b>填寫：</b>填入分鐘數（正整數）。<br>' +
          '<b>注意：</b>保留時間不能超過「最大保留時間」設定。',

        keeping_off:
          '<b>用途：</b>在此時間段內，保留功能暫停，讓下班時段不再保留機台（例如：凌晨 00:00–08:00 停止保留）。<br>' +
          '<b>填寫：</b>選擇開始/結束時間。<br>' +
          '<b>注意：</b>在此時段結束的保留會被自動取消。',

        gift_keeping_minutes:
          '<b>用途：</b>開分完成後，贈送玩家此分鐘數的保留時長，作為坐台激勵。<br>' +
          '<b>填寫：</b>填入分鐘數，0 表示不贈送。',

        activity_open:
          '<b>用途：</b>控制整個活動功能模組是否對玩家開放。<br>' +
          '<b>關閉後：</b>所有活動對玩家不可見，但後台資料不受影響。',

        settlement_date:
          '<b>用途：</b>每月分潤報表以此日期為結算截止日，影響分潤計算周期。<br>' +
          '<b>填寫：</b>選擇 1–28 日（避免月份天數差異）。',

        max_keeping_minutes:
          '<b>用途：</b>玩家保留時間的上限，超過此分鐘數系統自動取消保留，釋放機台供其他玩家使用。<br>' +
          '<b>填寫：</b>填入分鐘數（正整數）。',

        line_customer:
          '<b>用途：</b>玩家在用戶端點擊「客服」時開啟的 LINE 連結網址。<br>' +
          '<b>填寫：</b>填入完整 URL（https://line.me/...）。<br>' +
          '<b>限制：</b>最多 200 個字元。',

        line_discussion_group:
          '<b>用途：</b>玩家在用戶端可加入的 LINE 討論群連結。<br>' +
          '<b>填寫：</b>填入完整 URL（https://line.me/...）。<br>' +
          '<b>限制：</b>最多 200 個字元。',

        spectator_num:
          '<b>用途：</b>開獎（Jackpot）時，大屏或廣播中顯示的「觀看人數」數字。<br>' +
          '<b>說明：</b>此為固定顯示值，可設定一個合理的氛圍數字，不代表真實在線人數。',

        client_version:
          '<b>用途：</b>用戶端 APP 的最新版本號，玩家版本低於此值時會被強制提示更新。<br>' +
          '<b>填寫：</b>填入版本號，格式如 1.0.0。<br>' +
          '<b>注意：</b>修改後所有舊版用戶端會立即收到更新提示。',

        line_redirect_uri:
          '<b>用途：</b>LINE 登入 OAuth 流程的回調 URL。<br>' +
          '<b>注意：</b>此 URL 必須與 LINE Developer Console 中設定的 Callback URL 完全一致，否則 LINE 登入會失敗。',

        line_key:
          '<b>用途：</b>LINE 登入的 Channel ID（來自 LINE Developer Console）。<br>' +
          '<b>取得方式：</b>登入 LINE Developers → 選擇 Channel → Basic settings → Channel ID。',

        line_secret:
          '<b>用途：</b>LINE 登入的 Channel Secret（來自 LINE Developer Console）。<br>' +
          '<b>安全：</b>請勿將此值外洩，若洩漏請立即到 LINE Developer Console 重新生成。',

        commission:
          '<b>用途：</b>玩家開分時向門店收取的手續費比例。<br>' +
          '<b>填寫：</b>填入百分比數字（例如：2 代表收取 2%），0 表示免手續費。',

        app_password:
          '<b>用途：</b>管理後台帳號密碼相關設定，包含密碼強度要求等。',

        jackpot_screen_domain:
          '<b>用途：</b>大屏彩金顯示頁面的 Domain，系統會在此 Domain 下渲染彩金大屏頁面。<br>' +
          '<b>填寫：</b>填入 Domain（含 https://），不含結尾斜線。',

        turn_relay_ip:
          '<b>用途：</b>WebRTC TURN 中繼伺服器的 IP 位址，用於機台直播串流。<br>' +
          '<b>說明：</b>若機台與伺服器網路路由不通（NAT 後面），需設定 TURN 伺服器 IP 作為中繼節點。<br>' +
          '<b>格式：</b>填入 IP 地址（例如：123.45.67.89）。',

        high_score_broadcast_threshold:
          '<b>用途：</b>玩家在機台得分超過此數值時，系統自動廣播「高分報喜」語音和通知。<br>' +
          '<b>填寫：</b>填入分數閾值（正整數），0 表示停用廣播。',

        steel_ball_broadcast_threshold:
          '<b>用途：</b>鋼珠機台的「下珠數」超過此數值時，系統自動廣播鋼珠報喜。<br>' +
          '<b>填寫：</b>填入珠數閾值（正整數）。',

        device_collect:
          '<b>用途：</b>控制是否允許同一店機帳號在不同門店的裝置上登入（跨店共用）。<br>' +
          '<b>啟用：</b>同一帳號可在多間門店的不同裝置上登入。<br>' +
          '<b>停用：</b>同一帳號同時只能在一間門店使用。',

        electronic_game_rebate:
          '<b>用途：</b>控制電子遊戲（線上遊戲平台）的反水功能是否啟用。<br>' +
          '<b>說明：</b>反水是指按玩家遊戲流水的一定比例回饋點數，激勵玩家持續遊戲。',

        machine_rebate:
          '<b>用途：</b>控制實體機台的反水功能是否啟用。<br>' +
          '<b>說明：</b>反水邏輯與電子遊戲相同，依機台遊戲流水計算回饋。',

        ticket_machine_version:
          '<b>用途：</b>儲值機韌體/軟體的最新版本號，用於儲值機自動更新判斷。<br>' +
          '<b>填寫：</b>填入版本號，格式如 1.0.0。',

        ticket_machine_download_url:
          '<b>用途：</b>儲值機客戶端軟體的下載連結，提供給技術人員更新儲值機。<br>' +
          '<b>填寫：</b>填入完整下載 URL。',

        vip_welcome_voice:
          '<b>用途：</b>VIP 玩家登入時播放的專屬歡迎語音。<br>' +
          '<b>設定：</b>可針對不同 VIP 等級設定不同的歡迎語音文字，系統自動合成語音。<br>' +
          '<b>注意：</b>儲存後語音自動合成，可能需要數秒。',

        leaderboard_golden_weekly:
          '<b>用途：</b>金樽（全門店）遊戲量週排行榜的詳細配置。<br>' +
          '<b>設定項目：</b>榜單名稱、參賽門檻分數、各名次獎金、是否自動派發。',

        leaderboard_store_weekly:
          '<b>用途：</b>各門店遊戲量週排行榜配置（門店內部自己的排行）。',

        leaderboard_store_monthly:
          '<b>用途：</b>各門店遊戲量月排行榜配置。',

        leaderboard_golden_monthly:
          '<b>用途：</b>金樽（全門店）遊戲量月排行榜配置。',

        leaderboard_steel_ball_global_weekly:
          '<b>用途：</b>金樽鋼珠週排行榜配置（全門店鋼珠機台遊戲量）。',

        leaderboard_steel_ball_store_weekly:
          '<b>用途：</b>各門店鋼珠週排行榜配置。',

        leaderboard_steel_ball_global_monthly:
          '<b>用途：</b>金樽鋼珠月排行榜配置（全門店鋼珠機台遊戲量）。',

        leaderboard_switch:
          '<b>用途：</b>排行榜功能整體開關。<br>' +
          '<b>關閉後：</b>所有排行榜對玩家不可見，但排行榜資料和獎勵設定不受影響。<br>' +
          '<b>注意：</b>開啟此開關後，各排行榜還需分別確認狀態為「啟用」才會顯示。',
      },
    };
  },

  computed: {
    rows() {
      return Object.values(this.settingsMap);
    },
    orderedFeatures() {
      return Object.keys(this.labels).filter(f => this.settingsMap[f]);
    },
  },

  mounted() {
    this.fetchData('0');
  },

  methods: {
    featureLabel(feature) {
      return this.labels[feature] || feature;
    },

    displayValue(record) {
      const f = record.feature;
      if (['machine_maintain', 'client_maintain', 'keeping_off'].includes(f)) {
        try {
          const d = JSON.parse(record.content || '{}');
          return d.week_day ? `星期 ${d.week_day}  ${d.start_time || ''}–${d.end_time || ''}` : '—';
        } catch { return record.content || '—'; }
      }
      if (f.startsWith('leaderboard_') && f !== 'leaderboard_switch') {
        try {
          const d = JSON.parse(record.content || '{}');
          return `門檻 ${d.threshold || 0} 分`;
        } catch { return '—'; }
      }
      if (record.num !== null && record.num !== undefined && record.num !== '') {
        return String(record.num);
      }
      const c = record.content;
      if (!c) return '—';
      if (c.length > 40) return c.slice(0, 38) + '…';
      return c;
    },

    rowClass(record) {
      return record.feature === this.activeFeature ? 'row-active' : '';
    },

    customRow(record) {
      return { onClick: () => this.selectFeature(record.feature) };
    },

    selectFeature(feature) {
      this.activeFeature = feature;
    },

    onTabChange(key) {
      this.activeFeature = null;
      this.fetchData(key);
    },

    fetchData(departmentId) {
      this.loading = true;
      this.$request({
        url:    this.data_url,
        method: 'get',
        params: { department_id: departmentId },
      }).then(res => {
        this.loading = false;
        if (res.code === 200) {
          this.settingsMap = res.data || {};
        }
      }).catch(() => {
        this.loading = false;
      });
    },
  },
};
</script>

<style scoped>
.page-wrap  { padding: 16px; height: calc(100vh - 72px); display: flex; flex-direction: column; overflow: hidden; }
.top-bar    { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-shrink: 0; }
.page-title { font-size: 15px; font-weight: 700; color: #262626; }
.back-btn   { font-size: 13px; }

/* ── 主體：左右分欄 ── */
.main-area  { flex: 1; min-height: 0; display: flex; gap: 12px; overflow: hidden; }
.list-wrap  { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
.table-wrap { flex: 1; overflow-y: auto; }

/* ── 表格 row 高亮 ── */
:deep(.row-active td) { background: #e6f4ff !important; }
:deep(.ant-table-tbody > tr:hover > td) { cursor: pointer; }

.feature-link {
  color: #1677ff;
  cursor: pointer;
  font-weight: 500;
}
.feature-link:hover { text-decoration: underline; }
.value-cell { font-size: 12px; color: #595959; }

/* ── 右側注解面板 ── */
.ann-panel       { width: 260px; flex-shrink: 0; display: flex; flex-direction: column; background: #fff; border: 1px solid #f0f0f0; border-radius: 8px; overflow: hidden; }
.ann-panel-title { font-size: 13px; font-weight: 700; color: #1677ff; padding: 10px 12px; border-bottom: 1px solid #f0f0f0; background: #f0f5ff; flex-shrink: 0; }

/* ── 注解：有選中時 ── */
.ann-content      { flex: 1; overflow-y: auto; padding: 14px 12px; }
.ann-feature-name { font-size: 13px; font-weight: 700; color: #262626; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0; }
.ann-desc         { font-size: 12px; color: #595959; line-height: 1.8; }
:deep(.ann-desc b)  { color: #262626; }

/* ── 注解：無選中時（索引列表） ── */
.ann-empty      { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 16px 12px; overflow-y: auto; }
.ann-empty-icon { font-size: 28px; margin-bottom: 8px; }
.ann-empty-hint { font-size: 12px; color: #8c8c8c; text-align: center; line-height: 1.6; }
.ann-index      { width: 100%; margin-top: 4px; }
.ann-index-item {
  font-size: 12px; color: #1677ff; padding: 4px 6px; margin: 2px 0;
  border-radius: 4px; cursor: pointer; transition: background .15s;
}
.ann-index-item:hover { background: #f0f5ff; }
</style>
