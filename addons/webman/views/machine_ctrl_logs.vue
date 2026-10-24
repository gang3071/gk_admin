<template>
  <div style="padding:4px 0">
    <!-- 工具栏 -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px">
      <span style="color:#666; font-size:12px">
        {{ timeRange }}
        <a-tag style="margin-left:6px">{{ filteredLogs.length }} / {{ logs.length }} 条</a-tag>
      </span>
      <a-space size="small">
        <a-checkbox v-model:checked="hideOpenNoise">隐藏开机噪音</a-checkbox>
        <a-checkbox v-model:checked="hideHeartbeat">隐藏心跳</a-checkbox>
        <a-button size="small" :loading="loading" @click="fetchData">刷新</a-button>
      </a-space>
    </div>

    <!-- 加载 -->
    <div v-if="loading" style="text-align:center; padding:40px">
      <a-spin /><div style="margin-top:8px; color:#999; font-size:13px">查询 ClickHouse 中...</div>
    </div>

    <!-- 错误 -->
    <a-result v-else-if="error" status="error" title="查询失败" :sub-title="error">
      <template #extra><a-button type="primary" @click="fetchData">重试</a-button></template>
    </a-result>

    <!-- 时间线 -->
    <div v-else style="overflow-y:auto; max-height:520px; padding:0 4px">
      <a-empty v-if="!filteredLogs.length" description="该时间范围内无日志" />

      <div v-for="(item, idx) in filteredLogs" :key="idx" class="tl-item">
        <!-- 左侧时间轴 -->
        <div class="tl-axis">
          <div class="tl-dot" :class="dotClass(item)"></div>
          <div v-if="idx < filteredLogs.length - 1" class="tl-line"></div>
        </div>

        <!-- 右侧内容 -->
        <div class="tl-body" :class="bodyClass(item)">
          <!-- 标题行 -->
          <div class="tl-header">
            <span class="tl-time">{{ formatTime(item.datetime) }}</span>
            <span v-if="item._elapsed !== null" class="tl-elapsed">
              +{{ formatElapsed(item._elapsed) }}
            </span>
            <a-tag :color="actionColor(item)" style="margin:0 4px; font-size:11px">{{ item.action }}</a-tag>
            <a-tag :color="levelColor(item.level)" style="margin:0; font-size:11px">{{ item.level }}</a-tag>
            <a-tag v-if="item.source_type !== 'system'" color="geekblue" style="margin:0 0 0 4px; font-size:11px">{{ item.source_type }}</a-tag>
          </div>

          <!-- 描述 -->
          <div class="tl-desc">{{ item.action_desc }}</div>

          <!-- 心跳机台状态卡片 -->
          <div
            v-if="(item.source_type === 'heartbeat' || item.action === 'heartbeat' || item.action === 'field_update') && stateDisplayFields(parseState(item.machine_state)).length"
            class="tl-state-card"
          >
            <div class="state-grid">
              <div v-for="f in stateDisplayFields(parseState(item.machine_state))" :key="f.key" class="state-field">
                <span class="state-label">{{ f.label }}</span>
                <span class="state-val" :style="{ color: f.color }">{{ f.val }}</span>
              </div>
            </div>
          </div>

          <!-- 指令/响应 hex -->
          <div v-if="item.raw_send && item.raw_send !== '-' && item.raw_send !== ''" class="tl-raw tl-raw-send">
            <span class="raw-arrow">↑</span> {{ item.raw_send }}
          </div>
          <div v-if="item.raw_recv && item.raw_recv !== '-' && item.raw_recv !== ''" class="tl-raw tl-raw-recv">
            <span class="raw-arrow">↓</span> {{ item.raw_recv }}
          </div>

          <!-- extra_data 关键字段 -->
          <div v-if="extraFields(item).length" style="margin-top:4px; display:flex; flex-wrap:wrap; gap:3px">
            <a-tag
              v-for="f in extraFields(item)"
              :key="f.key"
              color="purple"
              style="margin:0; font-size:11px"
            >{{ f.key }}: {{ f.val }}</a-tag>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MachineCtrlLogs',
  props: {
    id: { type: [Number, String], required: true },
  },
  data() {
    return {
      loading: false,
      error: null,
      logs: [],
      timeRange: '',
      hideOpenNoise: true,
      hideHeartbeat: false,
    };
  },
  computed: {
    filteredLogs() {
      const list = this.logs.filter(r => {
        if (this.hideOpenNoise && r.action === 'open' && r.log_direction === 'response') return false;
        if (this.hideHeartbeat && r.source_type === 'heartbeat' && r.action === 'heartbeat') return false;
        return true;
      });
      // 计算与上一条的时间差
      return list.map((r, i) => ({
        ...r,
        _elapsed: i === 0 ? null : this.diffMs(list[i - 1].datetime, r.datetime),
      }));
    },
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    // ── 时间处理 ──────────────────────────────────────────
    parseMs(dt) {
      if (!dt) return 0;
      const s = dt.replace(' ', 'T');
      return new Date(s.includes('+') || s.includes('Z') ? s : s + '+08:00').getTime();
    },
    diffMs(a, b) {
      return this.parseMs(b) - this.parseMs(a);
    },
    formatTime(dt) {
      if (!dt) return '';
      const m = dt.match(/[T ](\d{2}:\d{2}:\d{2})/);
      return m ? m[1] : dt.slice(11, 19);
    },
    formatElapsed(ms) {
      if (ms === null || ms === 0) return '0ms';
      if (ms < 1000) return `${ms}ms`;
      return `${(ms / 1000).toFixed(1)}s`;
    },
    // ── 状态解析 ──────────────────────────────────────────
    parseState(s) {
      if (!s) return null;
      try { return JSON.parse(s); } catch { return null; }
    },
    stateDisplayFields(st) {
      if (!st) return [];
      const defs = [
        { key: 'point',            label: '当前分',  color: '#1890ff' },
        { key: 'player_open_point',label: '开分',    color: '#13c2c2' },
        { key: 'open_point',       label: '开分',    color: '#13c2c2' },
        { key: 'gaming_pressure',  label: '压',      color: '#fa8c16' },
        { key: 'gaming_score',     label: '得',      color: '#52c41a' },
        { key: 'turn_used_point',  label: '转',      color: '#722ed1' },
        { key: 'total_bet',        label: '总押',    color: '#f5222d' },
        { key: 'total_win',        label: '总赢',    color: '#52c41a' },
      ];
      const seen = new Set();
      return defs
        .filter(d => st[d.key] !== undefined && !seen.has(d.label) && seen.add(d.label))
        .map(d => ({ ...d, val: st[d.key] }));
    },
    extraFields(record) {
      if (!record.extra_data) return [];
      try {
        const d = JSON.parse(record.extra_data);
        return Object.entries(d)
          .filter(([, v]) => v !== null && v !== '' && typeof v !== 'object')
          .map(([k, v]) => ({ key: k, val: v }))
          .slice(0, 6);
      } catch { return []; }
    },
    // ── 样式辅助 ──────────────────────────────────────────
    levelColor(level) {
      return { INFO: 'green', WARNING: 'orange', ERROR: 'red' }[level] || 'default';
    },
    actionColor(item) {
      if (item.log_direction === 'command') return 'blue';
      const map = { wash: 'red', leave: 'volcano', open: 'cyan', heartbeat: 'default', field_update: 'geekblue' };
      return map[item.action] || 'default';
    },
    dotClass(item) {
      if (item.level === 'ERROR') return 'dot-error';
      if (item.level === 'WARNING') return 'dot-warning';
      if (['wash', 'leave', 'account_query'].includes(item.action)) return 'dot-key';
      if (item.source_type === 'heartbeat') return 'dot-heartbeat';
      if (item.log_direction === 'command') return 'dot-command';
      return 'dot-default';
    },
    bodyClass(item) {
      if (item.level === 'ERROR') return 'body-error';
      if (item.level === 'WARNING') return 'body-warning';
      if (['wash', 'leave', 'account_query', 'wash_zero'].includes(item.action)) return 'body-key';
      return '';
    },
    // ── API ──────────────────────────────────────────────
    getToken() {
      const appName = window['App-Name'] || '';
      return localStorage.getItem(appName + '_ex-admin-token') || '';
    },
    async fetchData() {
      this.loading = true;
      this.error = null;
      try {
        const res = await fetch(
          '/ex-admin/addons-webman-controller-PlayerGameLogController/fetchCtrlLogs',
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Authorization': this.getToken() },
            body: JSON.stringify({ id: this.id }),
          }
        );
        const json = await res.json();
        if (json.code === 200) {
          this.logs      = json.data.logs;
          this.timeRange = json.data.time_range;
        } else {
          this.error = json.msg || '查询失败';
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
/* ── 时间线容器 ── */
.tl-item {
  display: flex;
  gap: 10px;
  margin-bottom: 2px;
}

/* ── 左侧轴 ── */
.tl-axis {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  width: 12px;
  padding-top: 4px;
}
.tl-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
  border: 2px solid;
}
.tl-line {
  flex: 1;
  width: 2px;
  background: #e8e8e8;
  min-height: 8px;
  margin: 2px 0;
}

/* 圆点颜色 */
.dot-error     { background:#fff1f0; border-color:#f5222d; }
.dot-warning   { background:#fffbe6; border-color:#fa8c16; }
.dot-key       { background:#f6ffed; border-color:#52c41a; }
.dot-heartbeat { background:#f0f5ff; border-color:#adc6ff; }
.dot-command   { background:#e6f7ff; border-color:#1890ff; }
.dot-default   { background:#fafafa; border-color:#d9d9d9; }

/* ── 右侧内容 ── */
.tl-body {
  flex: 1;
  padding: 5px 8px;
  border-radius: 4px;
  margin-bottom: 4px;
  border-left: 3px solid transparent;
  background: #fafafa;
  min-width: 0;
}
.body-error   { background:#fff1f0; border-left-color:#f5222d; }
.body-warning { background:#fffbe6; border-left-color:#fa8c16; }
.body-key     { background:#f6ffed; border-left-color:#52c41a; }

.tl-header {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 3px;
}
.tl-time    { font-family: monospace; font-size: 12px; color: #595959; font-weight: 600; }
.tl-elapsed { font-size: 11px; color: #8c8c8c; background:#f0f0f0; padding:0 5px; border-radius:3px; }
.tl-desc    { font-size: 12px; color: #262626; margin-bottom: 3px; line-height: 1.5; }

/* ── 机台状态卡片 ── */
.tl-state-card {
  background: #fff;
  border: 1px solid #e8e8e8;
  border-radius: 4px;
  padding: 5px 8px;
  margin: 4px 0;
}
.state-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.state-field {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 44px;
}
.state-label { font-size: 10px; color: #8c8c8c; }
.state-val   { font-size: 15px; font-weight: 700; font-family: monospace; }

/* ── 原始指令 ── */
.tl-raw {
  font-family: monospace;
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 3px;
  margin-top: 2px;
  word-break: break-all;
}
.tl-raw-send { background:#e6f7ff; color:#0050b3; }
.tl-raw-recv { background:#f6ffed; color:#135200; }
.raw-arrow   { font-weight: bold; }
</style>
