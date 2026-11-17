<template>
  <div class="page-wrap">

    <!-- 搜索表單 -->
    <a-card class="search-card">
      <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
        <a-input
          v-model:value="form.machineCode"
          placeholder="機台編號（如：A001）"
          style="width:180px"
          allow-clear
          @press-enter="canSearch && doSearch()"
        />
        <a-range-picker
          v-model:value="form.dateRange"
          show-time
          format="YYYY-MM-DD HH:mm:ss"
          :placeholder="['開始時間', '結束時間']"
          style="width:420px"
        />
        <a-button type="primary" :loading="loading" :disabled="!canSearch" @click="doSearch">查詢</a-button>
        <a-divider type="vertical" />
        <span style="font-size:12px; color:#595959; flex-shrink:0">等級：</span>
        <a-checkbox-group v-model:value="showLevels" :options="levelOptions" />
        <a-divider type="vertical" />
        <a-checkbox v-model:checked="hideNoise">隱藏開機噪音</a-checkbox>
        <a-checkbox v-model:checked="hideHeartbeat">隱藏心跳</a-checkbox>
      </div>
    </a-card>

    <!-- 結果區域（佔滿剩餘高度） -->
    <div class="result-wrap">

      <!-- 未查詢 -->
      <div v-if="!searched" class="center-state">
        <a-empty description="請輸入機台編號及時間範圍後點擊查詢" />
      </div>

      <!-- 加載中 -->
      <div v-else-if="loading" class="center-state">
        <a-spin size="large" />
        <div style="margin-top:16px; color:#999; font-size:14px">查詢 ClickHouse 中，請稍候…</div>
      </div>

      <!-- 查詢失敗 -->
      <a-result v-else-if="error" status="error" title="查詢失敗" :sub-title="error">
        <template #extra>
          <a-button type="primary" @click="doSearch">重試</a-button>
        </template>
      </a-result>

      <!-- 查詢結果：card 本身拉伸，body 內部滾動 -->
      <a-card
        v-else
        class="result-card"
        :body-style="{ display:'flex', flexDirection:'column', flex:1, overflow:'hidden', padding:'12px', minHeight:0 }"
      >
        <!-- 頂部信息欄（固定，不滾動） -->
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px">
          <span style="color:#595959; font-size:12px">
            <strong>{{ machineInfo }}</strong>　{{ timeRange }}
            <a-tag v-if="truncated" color="orange" style="margin-left:6px">結果超過 10000 筆，已截斷顯示</a-tag>
            <a-tag style="margin-left:6px">{{ visibleLogs.length }} / {{ logs.length }} 條</a-tag>
          </span>
          <a-button size="small" :loading="loading" @click="doSearch">刷新</a-button>
        </div>

        <!-- 錨點快速跳轉 -->
        <div v-if="anchors.length" class="anchor-nav">
          <span class="anchor-label">跳轉：</span>
          <div class="anchor-list">
            <button
              v-for="a in anchors"
              :key="a.idx"
              class="anchor-btn"
              :class="'anchor-btn-' + a.item.marker"
              @click="scrollToAnchor(a.idx)"
            >{{ a.item.time.slice(11, 19) }} {{ markerLabel(a.item.marker) }}</button>
          </div>
        </div>

        <!-- 時間軸（內部滾動） -->
        <div ref="timeline" class="timeline-scroll">
          <a-empty v-if="!visibleLogs.length" description="該時間範圍內無日誌（或全部被過濾）" />

          <div v-for="(item, idx) in visibleLogs" :key="idx" :id="'tl-item-' + idx" class="tl-row">
            <div class="tl-axis">
              <div class="tl-dot" :class="dotClass(item)"></div>
              <div v-if="idx < visibleLogs.length - 1" class="tl-line"></div>
            </div>

            <div class="tl-body" :class="bodyClass(item)">
              <div class="tl-head">
                <code class="tl-time">{{ item.time }}</code>
                <span v-if="item.elapsed_ms !== null" class="tl-elapsed">+{{ fmtElapsed(item.elapsed_ms) }}</span>
                <span v-if="item.marker" class="tl-marker" :class="'marker-' + item.marker">
                  {{ markerLabel(item.marker) }}
                </span>
                <a-tag :color="actionTagColor(item)" style="margin:0; font-size:11px; line-height:18px">{{ item.action }}</a-tag>
                <a-tag :color="levelColor(item.level)" style="margin:0; font-size:11px; line-height:18px">{{ item.level }}</a-tag>
                <a-tag v-if="item.source_type !== 'system'" style="margin:0; font-size:11px; line-height:18px; background:#f0f5ff; border-color:#adc6ff; color:#2f54eb">{{ item.source_type }}</a-tag>
              </div>

              <div class="tl-desc">
                <span v-if="item.display_type === 'command'" class="dir-arrow dir-send">↑</span>
                <span v-else-if="item.display_type === 'response'" class="dir-arrow dir-recv">↓</span>
                {{ item.action_desc }}
              </div>

              <div v-if="item.display_type === 'heartbeat' && item.state.length" class="state-card">
                <div class="state-grid">
                  <div v-for="f in item.state" :key="f.label" class="state-cell">
                    <div class="state-label">{{ f.label }}</div>
                    <div class="state-val" :style="{ color: f.color }">{{ f.val }}</div>
                  </div>
                </div>
              </div>

              <div v-if="item.raw_send" class="hex-row hex-send"><span class="hex-arrow">↑</span>{{ item.raw_send }}</div>
              <div v-if="item.raw_recv" class="hex-row hex-recv"><span class="hex-arrow">↓</span>{{ item.raw_recv }}</div>

              <div v-if="item.extra.length" style="margin-top:4px; display:flex; flex-wrap:wrap; gap:3px">
                <a-tag v-for="f in item.extra" :key="f.key" color="purple" style="margin:0; font-size:11px; line-height:18px">
                  {{ f.key }}: {{ f.val }}
                </a-tag>
              </div>
            </div>
          </div>
        </div>
      </a-card>
    </div>

  </div>
</template>

<script>
export default {
  name: 'MachineCtrlLogCenter',
  data() {
    return {
      form: { machineCode: '', dateRange: null },
      loading:       false,
      searched:      false,
      error:         null,
      logs:          [],
      machineInfo:   '',
      timeRange:     '',
      truncated:     false,
      hideNoise:     true,
      hideHeartbeat: false,
      showLevels:    ['INFO', 'WARNING', 'ERROR'],
      levelOptions: [
        { label: 'INFO',    value: 'INFO' },
        { label: 'WARNING', value: 'WARNING' },
        { label: 'ERROR',   value: 'ERROR' },
      ],
    };
  },
  computed: {
    canSearch() {
      return this.form.machineCode.trim() !== ''
        && Array.isArray(this.form.dateRange)
        && this.form.dateRange.length === 2;
    },
    visibleLogs() {
      return this.logs.filter(r => {
        if (this.hideNoise     && r.display_type === 'noise')     return false;
        if (this.hideHeartbeat && r.display_type === 'heartbeat') return false;
        if (!this.showLevels.includes(r.level))                   return false;
        return true;
      });
    },
    anchors() {
      return this.visibleLogs
        .map((item, idx) => ({ item, idx }))
        .filter(({ item }) => !!item.marker);
    },
  },
  methods: {
    fmtElapsed(ms) {
      if (ms === 0)  return '0ms';
      if (ms < 1000) return `${ms}ms`;
      return `${(ms / 1000).toFixed(1)}s`;
    },
    levelColor(l)  { return { INFO: 'green', WARNING: 'orange', ERROR: 'red' }[l] || 'default'; },
    actionTagColor(item) {
      if (item.display_type === 'command')   return 'blue';
      if (item.display_type === 'heartbeat') return 'geekblue';
      if (item.level === 'WARNING')          return 'orange';
      return { wash: 'red', leave: 'volcano', open: 'cyan' }[item.action] || 'default';
    },
    markerLabel(m) {
      return {
        open_start: '▶ 開分開始', open_end: '✓ 開分結束',
        wash_start: '▶ 洗分開始', wash_end: '✓ 洗分結束',
        bet_drop:   '⚠ BET下降',
      }[m] || m;
    },
    dotClass(item) {
      if (item.marker === 'open_start') return 'dot-open-start';
      if (item.marker === 'open_end')   return 'dot-open-end';
      if (item.marker === 'wash_start') return 'dot-wash-start';
      if (item.marker === 'wash_end')   return 'dot-wash-end';
      if (item.marker === 'bet_drop')   return 'dot-bet-drop';
      const base = 'dot-' + item.display_type;
      if (item.level === 'ERROR')   return base + ' dot-error';
      if (item.level === 'WARNING') return base + ' dot-warn';
      return base;
    },
    bodyClass(item) {
      if (item.marker === 'open_start') return 'body-open-start';
      if (item.marker === 'open_end')   return 'body-open-end';
      if (item.marker === 'wash_start') return 'body-wash-start';
      if (item.marker === 'wash_end')   return 'body-wash-end';
      if (item.marker === 'bet_drop')   return 'body-bet-drop';
      if (item.level === 'ERROR')   return 'body-error';
      if (item.level === 'WARNING') return 'body-warn';
      if (['wash', 'leave', 'account_query', 'wash_zero', 'all_down'].includes(item.action)) return 'body-key';
      return 'body-default';
    },
    scrollToAnchor(idx) {
      const el = this.$refs.timeline?.querySelector(`#tl-item-${idx}`);
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },
    getToken() {
      return localStorage.getItem((window['App-Name'] || '') + '_ex-admin-token') || '';
    },
    async doSearch() {
      if (!this.canSearch) return;

      const [startDayjs, endDayjs] = this.form.dateRange;
      const startTime = startDayjs.format('YYYY-MM-DD HH:mm:ss');
      const endTime   = endDayjs.format('YYYY-MM-DD HH:mm:ss');

      this.loading  = true;
      this.searched = true;
      this.error    = null;
      this.logs     = [];

      try {
        const res = await fetch(
          '/ex-admin/addons-webman-controller-MachineCtrlLogController/search',
          {
            method: 'POST',
            headers: {
              'Content-Type':    'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'Authorization':   this.getToken(),
            },
            body: JSON.stringify({
              machine_code: this.form.machineCode.trim(),
              start_time:   startTime,
              end_time:     endTime,
            }),
          }
        );
        const j = await res.json();
        if (j.code === 200) {
          this.logs        = j.data.logs;
          this.machineInfo = j.data.machine;
          this.timeRange   = j.data.time_range;
          this.truncated   = j.data.truncated;
        } else {
          this.error = j.msg || '查詢失敗';
        }
      } catch (e) {
        this.error = e.message;
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
/* ── 整頁佈局 ───────────────────────────────────────────────── */
.page-wrap {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 112px);
  padding: 16px;
  box-sizing: border-box;
  gap: 12px;
  overflow: hidden;
}
.search-card  { flex-shrink: 0; }
.result-wrap  { flex: 1; min-height: 0; display: flex; flex-direction: column; overflow: hidden; }
.center-state { display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; }
/* result-card 本身需要 flex 拉伸，讓 body 可以 overflow:hidden */
.result-card  { flex: 1; min-height: 0; display: flex; flex-direction: column; overflow: hidden; }

/* 時間軸滾動容器 */
.timeline-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 0 2px;
}

/* ── 時間軸 ─────────────────────────────────────────────────── */
.tl-row  { display:flex; gap:10px; margin-bottom:2px; }

.tl-axis { display:flex; flex-direction:column; align-items:center; flex-shrink:0; width:12px; padding-top:5px; }
.tl-dot  { width:10px; height:10px; border-radius:50%; border:2px solid; flex-shrink:0; }
.tl-line { flex:1; width:2px; background:#e8e8e8; min-height:6px; margin:2px 0; }

.dot-noise     { background:#fafafa;  border-color:#d9d9d9; }
.dot-heartbeat { background:#f0f5ff;  border-color:#adc6ff; }
.dot-command   { background:#e6f7ff;  border-color:#1890ff; }
.dot-response  { background:#f6ffed;  border-color:#95de64; }
.dot-event     { background:#fff7e6;  border-color:#ffd591; }
.dot-error     { background:#fff1f0 !important; border-color:#f5222d !important; }
.dot-warn      { background:#fffbe6 !important; border-color:#fa8c16 !important; }

.dot-open-start { width:12px; height:12px; background:#13c2c2; border-color:#006d75; }
.dot-open-end   { width:12px; height:12px; background:#52c41a; border-color:#237804; }
.dot-wash-start { width:12px; height:12px; background:#fa8c16; border-color:#ad4e00; }
.dot-wash-end   { width:12px; height:12px; background:#722ed1; border-color:#391085; }
.dot-bet-drop   { width:12px; height:12px; background:#fff1f0; border-color:#f5222d; }

.tl-body      { flex:1; padding:5px 8px; border-radius:4px; margin-bottom:4px; border-left:3px solid transparent; min-width:0; }
.body-default { background:#fafafa; border-left-color:#e8e8e8; }
.body-error   { background:#fff1f0; border-left-color:#f5222d; }
.body-warn    { background:#fffbe6; border-left-color:#fa8c16; }
.body-key     { background:#f6ffed; border-left-color:#52c41a; }

.body-open-start { background:#e6fffb; border-left:4px solid #13c2c2; }
.body-open-end   { background:#f6ffed; border-left:4px solid #52c41a; }
.body-wash-start { background:#fff7e6; border-left:4px solid #fa8c16; }
.body-wash-end   { background:#f9f0ff; border-left:4px solid #722ed1; }
.body-bet-drop   { background:#fff1f0; border-left:4px solid #f5222d; }

.tl-head    { display:flex; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:3px; }
.tl-time    { font-size:12px; color:#595959; font-weight:700; background:none; padding:0; }
.tl-elapsed { font-size:11px; color:#8c8c8c; background:#f5f5f5; padding:0 5px; border-radius:3px; }
.tl-desc    { font-size:12px; color:#262626; margin-bottom:2px; line-height:1.5; word-break:break-word; }

.tl-marker         { font-size:12px; font-weight:700; padding:1px 8px; border-radius:3px; border:1px solid; }
.marker-open_start { background:#e6fffb; border-color:#13c2c2; color:#006d75; }
.marker-open_end   { background:#f6ffed; border-color:#52c41a; color:#237804; }
.marker-wash_start { background:#fff7e6; border-color:#fa8c16; color:#ad4e00; }
.marker-wash_end   { background:#f9f0ff; border-color:#722ed1; color:#391085; }
.marker-bet_drop   { background:#fff1f0; border-color:#f5222d; color:#a8071a; }

.state-card  { background:#fff; border:1px solid #e8e8e8; border-radius:4px; padding:6px 10px; margin:4px 0; }
.state-grid  { display:flex; flex-wrap:wrap; gap:16px; }
.state-cell  { display:flex; flex-direction:column; align-items:center; min-width:44px; }
.state-label { font-size:11px; color:#8c8c8c; }
.state-val   { font-size:16px; font-weight:700; font-family:monospace; }

.dir-arrow { font-weight:700; margin-right:3px; font-size:13px; }
.dir-send  { color:#0050b3; }
.dir-recv  { color:#135200; }

.hex-row   { font-family:monospace; font-size:11px; padding:2px 6px; border-radius:3px; margin-top:2px; word-break:break-all; }
.hex-send  { background:#e6f7ff; color:#0050b3; }
.hex-recv  { background:#f6ffed; color:#135200; }
.hex-arrow { font-weight:bold; margin-right:4px; }

/* ── 錨點導航 ───────────────────────────────────────────────── */
.anchor-nav   { flex-shrink:0; display:flex; align-items:center; gap:6px; margin-bottom:8px; }
.anchor-label { font-size:11px; color:#8c8c8c; white-space:nowrap; flex-shrink:0; }
.anchor-list  { display:flex; gap:6px; overflow-x:auto; padding-bottom:2px; }
.anchor-list::-webkit-scrollbar { height:4px; }
.anchor-list::-webkit-scrollbar-thumb { background:#d9d9d9; border-radius:2px; }

.anchor-btn {
  flex-shrink: 0;
  font-size: 11px;
  padding: 2px 10px;
  border-radius: 12px;
  border: 1px solid;
  cursor: pointer;
  white-space: nowrap;
  line-height: 20px;
  background: #fff;
  transition: opacity .15s;
}
.anchor-btn:hover { opacity: .75; }

.anchor-btn-open_start { background:#e6fffb; border-color:#13c2c2; color:#006d75; }
.anchor-btn-open_end   { background:#f6ffed; border-color:#52c41a; color:#237804; }
.anchor-btn-wash_start { background:#fff7e6; border-color:#fa8c16; color:#ad4e00; }
.anchor-btn-wash_end   { background:#f9f0ff; border-color:#722ed1; color:#391085; }
.anchor-btn-bet_drop   { background:#fff1f0; border-color:#f5222d; color:#a8071a; }
</style>
