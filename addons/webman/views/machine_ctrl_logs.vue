<template>
  <div style="padding:4px 0">
    <!-- 工具栏 -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px">
      <span style="color:#666; font-size:12px">
        {{ timeRange }}
        <a-tag style="margin-left:6px">{{ visibleLogs.length }} / {{ logs.length }} 条</a-tag>
      </span>
      <a-space size="small">
        <a-checkbox v-model:checked="hideNoise">隐藏开机噪音</a-checkbox>
        <a-checkbox v-model:checked="hideHeartbeat">隐藏心跳</a-checkbox>
        <a-button size="small" :loading="loading" @click="fetchData">刷新</a-button>
      </a-space>
    </div>

    <!-- 加载 -->
    <div v-if="loading" style="text-align:center; padding:40px">
      <a-spin /><div style="margin-top:8px; color:#999; font-size:13px">查询中...</div>
    </div>

    <!-- 错误 -->
    <a-result v-else-if="error" status="error" title="查询失败" :sub-title="error">
      <template #extra><a-button type="primary" @click="fetchData">重试</a-button></template>
    </a-result>

    <!-- 时间线 -->
    <div v-else style="overflow-y:auto; max-height:540px; padding:0 2px">
      <a-empty v-if="!visibleLogs.length" description="该时间范围内无日志" />

      <div v-for="(item, idx) in visibleLogs" :key="idx" class="tl-row">
        <!-- 左轴 -->
        <div class="tl-axis">
          <div class="tl-dot" :class="dotClass(item)"></div>
          <div v-if="idx < visibleLogs.length - 1" class="tl-line"></div>
        </div>

        <!-- 内容 -->
        <div class="tl-body" :class="bodyClass(item)">
          <!-- 标题行 -->
          <div class="tl-head">
            <code class="tl-time">{{ item.time }}</code>
            <span v-if="item.elapsed_ms !== null" class="tl-elapsed">+{{ fmtElapsed(item.elapsed_ms) }}</span>
            <!-- 開分/洗分 醒目標記 -->
            <span v-if="item.marker" class="tl-marker" :class="'marker-' + item.marker">
              {{ markerLabel(item.marker) }}
            </span>
            <a-tag :color="actionTagColor(item)" style="margin:0; font-size:11px; line-height:18px">{{ item.action }}</a-tag>
            <a-tag :color="levelColor(item.level)" style="margin:0; font-size:11px; line-height:18px">{{ item.level }}</a-tag>
            <a-tag v-if="item.source_type !== 'system'" style="margin:0; font-size:11px; line-height:18px; background:#f0f5ff; border-color:#adc6ff; color:#2f54eb">{{ item.source_type }}</a-tag>
          </div>

          <!-- 描述 -->
          <div class="tl-desc">{{ item.action_desc }}</div>

          <!-- 心跳 / field_update → 机台状态卡片 -->
          <div v-if="item.display_type === 'heartbeat' && item.state.length" class="state-card">
            <div class="state-grid">
              <div v-for="f in item.state" :key="f.label" class="state-cell">
                <div class="state-label">{{ f.label }}</div>
                <div class="state-val" :style="{ color: f.color }">{{ f.val }}</div>
              </div>
            </div>
          </div>

          <!-- 发送指令 hex -->
          <div v-if="item.raw_send" class="hex-row hex-send"><span class="hex-arrow">↑</span>{{ item.raw_send }}</div>
          <!-- 接收响应 hex -->
          <div v-if="item.raw_recv" class="hex-row hex-recv"><span class="hex-arrow">↓</span>{{ item.raw_recv }}</div>

          <!-- extra 字段 -->
          <div v-if="item.extra.length" style="margin-top:4px; display:flex; flex-wrap:wrap; gap:3px">
            <a-tag v-for="f in item.extra" :key="f.key" color="purple" style="margin:0; font-size:11px; line-height:18px">
              {{ f.key }}: {{ f.val }}
            </a-tag>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MachineCtrlLogs',
  props: { id: { type: [Number, String], required: true } },
  data() {
    return { loading: false, error: null, logs: [], timeRange: '', hideNoise: true, hideHeartbeat: false };
  },
  computed: {
    visibleLogs() {
      return this.logs.filter(r => {
        if (this.hideNoise && r.display_type === 'noise') return false;
        if (this.hideHeartbeat && r.display_type === 'heartbeat') return false;
        return true;
      });
    },
  },
  mounted() { this.fetchData(); },
  methods: {
    fmtElapsed(ms) {
      if (ms === 0) return '0ms';
      if (ms < 1000) return `${ms}ms`;
      return `${(ms / 1000).toFixed(1)}s`;
    },
    levelColor(l) { return { INFO: 'green', WARNING: 'orange', ERROR: 'red' }[l] || 'default'; },
    actionTagColor(item) {
      if (item.display_type === 'command')   return 'blue';
      if (item.display_type === 'heartbeat') return 'geekblue';
      if (item.level === 'WARNING')          return 'orange';
      const m = { wash: 'red', leave: 'volcano', open: 'cyan' };
      return m[item.action] || 'default';
    },
    markerLabel(m) {
      return { open_start: '▶ 開分開始', open_end: '✓ 開分結束', wash_start: '▶ 洗分開始', wash_end: '✓ 洗分結束' }[m] || m;
    },
    dotClass(item) {
      if (item.marker === 'open_start')  return 'dot-open-start';
      if (item.marker === 'open_end')    return 'dot-open-end';
      if (item.marker === 'wash_start')  return 'dot-wash-start';
      if (item.marker === 'wash_end')    return 'dot-wash-end';
      const base = 'dot-' + item.display_type;
      if (item.level === 'ERROR')   return base + ' dot-error';
      if (item.level === 'WARNING') return base + ' dot-warn';
      return base;
    },
    bodyClass(item) {
      if (item.marker === 'open_start')  return 'body-open-start';
      if (item.marker === 'open_end')    return 'body-open-end';
      if (item.marker === 'wash_start')  return 'body-wash-start';
      if (item.marker === 'wash_end')    return 'body-wash-end';
      if (item.level === 'ERROR')   return 'body-error';
      if (item.level === 'WARNING') return 'body-warn';
      if (['wash', 'leave', 'account_query', 'wash_zero', 'all_down'].includes(item.action)) return 'body-key';
      return 'body-default';
    },
    getToken() {
      return localStorage.getItem((window['App-Name'] || '') + '_ex-admin-token') || '';
    },
    async fetchData() {
      this.loading = true; this.error = null;
      try {
        const res = await fetch(
          '/ex-admin/addons-webman-controller-PlayerGameLogController/fetchCtrlLogs',
          { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Authorization': this.getToken() }, body: JSON.stringify({ id: this.id }) }
        );
        const j = await res.json();
        if (j.code === 200) { this.logs = j.data.logs; this.timeRange = j.data.time_range; }
        else this.error = j.msg || '查询失败';
      } catch (e) { this.error = e.message; }
      finally { this.loading = false; }
    },
  },
};
</script>

<style scoped>
.tl-row   { display:flex; gap:10px; margin-bottom:2px; }

.tl-axis  { display:flex; flex-direction:column; align-items:center; flex-shrink:0; width:12px; padding-top:5px; }
.tl-dot   { width:10px; height:10px; border-radius:50%; border:2px solid; flex-shrink:0; }
.tl-line  { flex:1; width:2px; background:#e8e8e8; min-height:6px; margin:2px 0; }

/* 圆点颜色（display_type 为主，level 覆盖） */
.dot-noise     { background:#fafafa;  border-color:#d9d9d9; }
.dot-heartbeat { background:#f0f5ff;  border-color:#adc6ff; }
.dot-command   { background:#e6f7ff;  border-color:#1890ff; }
.dot-response  { background:#f6ffed;  border-color:#95de64; }
.dot-event     { background:#fff7e6;  border-color:#ffd591; }
.dot-error     { background:#fff1f0 !important; border-color:#f5222d !important; }
.dot-warn      { background:#fffbe6 !important; border-color:#fa8c16 !important; }

/* 開分/洗分 標記圓點（放大、實心） */
.dot-open-start { width:12px; height:12px; background:#13c2c2; border-color:#006d75; }
.dot-open-end   { width:12px; height:12px; background:#52c41a; border-color:#237804; }
.dot-wash-start { width:12px; height:12px; background:#fa8c16; border-color:#ad4e00; }
.dot-wash-end   { width:12px; height:12px; background:#722ed1; border-color:#391085; }

.tl-body     { flex:1; padding:5px 8px; border-radius:4px; margin-bottom:4px; border-left:3px solid transparent; min-width:0; }
.body-default{ background:#fafafa; border-left-color:#e8e8e8; }
.body-error  { background:#fff1f0; border-left-color:#f5222d; }
.body-warn   { background:#fffbe6; border-left-color:#fa8c16; }
.body-key    { background:#f6ffed; border-left-color:#52c41a; }

/* 開分/洗分 標記行樣式（加粗左邊線、特殊背景） */
.body-open-start { background:#e6fffb; border-left:4px solid #13c2c2; }
.body-open-end   { background:#f6ffed; border-left:4px solid #52c41a; }
.body-wash-start { background:#fff7e6; border-left:4px solid #fa8c16; }
.body-wash-end   { background:#f9f0ff; border-left:4px solid #722ed1; }

.tl-head   { display:flex; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:3px; }
.tl-time   { font-size:12px; color:#595959; font-weight:700; background:none; padding:0; }
.tl-elapsed{ font-size:11px; color:#8c8c8c; background:#f5f5f5; padding:0 5px; border-radius:3px; }
.tl-desc   { font-size:12px; color:#262626; margin-bottom:2px; line-height:1.5; word-break:break-word; }

/* 開分/洗分 醒目標籤 */
.tl-marker         { font-size:12px; font-weight:700; padding:1px 8px; border-radius:3px; border:1px solid; }
.marker-open_start { background:#e6fffb; border-color:#13c2c2; color:#006d75; }
.marker-open_end   { background:#f6ffed; border-color:#52c41a; color:#237804; }
.marker-wash_start { background:#fff7e6; border-color:#fa8c16; color:#ad4e00; }
.marker-wash_end   { background:#f9f0ff; border-color:#722ed1; color:#391085; }

/* 机台状态卡片 */
.state-card  { background:#fff; border:1px solid #e8e8e8; border-radius:4px; padding:6px 10px; margin:4px 0; }
.state-grid  { display:flex; flex-wrap:wrap; gap:16px; }
.state-cell  { display:flex; flex-direction:column; align-items:center; min-width:44px; }
.state-label { font-size:11px; color:#8c8c8c; }
.state-val   { font-size:16px; font-weight:700; font-family:monospace; }

/* 原始 hex */
.hex-row   { font-family:monospace; font-size:11px; padding:2px 6px; border-radius:3px; margin-top:2px; word-break:break-all; }
.hex-send  { background:#e6f7ff; color:#0050b3; }
.hex-recv  { background:#f6ffed; color:#135200; }
.hex-arrow { font-weight:bold; margin-right:4px; }
</style>
