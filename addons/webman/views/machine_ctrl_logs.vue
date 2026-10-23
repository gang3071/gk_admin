<template>
  <div style="padding:4px 0">
    <!-- 工具栏 -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px">
      <span style="color:#666; font-size:12px">
        {{ timeRange }}
        <a-tag style="margin-left:6px">{{ filteredLogs.length }} / {{ logs.length }} 条</a-tag>
      </span>
      <a-space>
        <a-checkbox v-model:checked="hideOpenNoise">隐藏重复开机响应</a-checkbox>
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

    <!-- 表格 -->
    <a-table
      v-else
      :columns="columns"
      :data-source="filteredLogs"
      :pagination="filteredLogs.length > 50 ? { pageSize: 50, showSizeChanger: false } : false"
      :scroll="{ x: 900, y: 460 }"
      size="small"
      :row-key="(r, i) => i"
      :row-class-name="rowClass"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'time'">
          <span style="font-family:monospace; font-size:12px; color:#595959">{{ formatTime(record.datetime) }}</span>
        </template>
        <template v-else-if="column.key === 'level'">
          <a-tag :color="levelColor(record.level)" style="margin:0; font-size:11px">{{ record.level }}</a-tag>
        </template>
        <template v-else-if="column.key === 'action'">
          <a-tag :color="actionColor(record.action, record.log_direction)" style="margin:0; font-size:11px">
            {{ record.action }}
          </a-tag>
        </template>
        <template v-else-if="column.key === 'action_desc'">
          <span :style="{ fontSize: '12px', fontWeight: isKeyEvent(record) ? '600' : 'normal' }">
            {{ record.action_desc }}
          </span>
        </template>
        <template v-else-if="column.key === 'raw'">
          <span v-if="record.raw_send && record.raw_send !== '-'" style="font-family:monospace; font-size:11px; color:#1890ff">↑{{ record.raw_send }}</span>
          <span v-if="record.raw_recv && record.raw_recv !== '-'" style="font-family:monospace; font-size:11px; color:#52c41a; display:block">↓{{ record.raw_recv }}</span>
          <span v-if="!record.raw_send && !record.raw_recv" style="color:#d9d9d9">-</span>
        </template>
        <template v-else-if="column.key === 'extra_data'">
          <a-tooltip v-if="record.extra_data && record.extra_data !== '-' && record.extra_data !== ''" :title="record.extra_data" placement="topLeft">
            <a-tag color="purple" style="cursor:pointer; font-size:11px">详情</a-tag>
          </a-tooltip>
          <span v-else style="color:#d9d9d9">-</span>
        </template>
      </template>
    </a-table>
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
      columns: [
        { title: '时间',   key: 'time',        width: 90,  fixed: 'left' },
        { title: '级别',   key: 'level',        width: 72,  align: 'center' },
        { title: '操作',   key: 'action',       width: 120, align: 'center' },
        { title: '描述',   dataIndex: 'action_desc', key: 'action_desc', ellipsis: true },
        { title: '指令/响应', key: 'raw',       width: 160 },
        { title: '扩展',   key: 'extra_data',   width: 60,  align: 'center' },
      ],
    };
  },
  computed: {
    filteredLogs() {
      return this.logs.filter(r => {
        if (this.hideOpenNoise && r.action === 'open' && r.log_direction === 'response') return false;
        if (this.hideHeartbeat && r.source_type === 'heartbeat') return false;
        return true;
      });
    },
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    formatTime(dt) {
      // "2026-10-23T17:38:00+08:00" 或 "2026-10-23 17:38:00.000" → "17:38:00"
      if (!dt) return '';
      const m = dt.match(/[T ](\d{2}:\d{2}:\d{2})/);
      return m ? m[1] : dt.slice(11, 19);
    },
    levelColor(level) {
      return { INFO: 'green', WARNING: 'orange', ERROR: 'red' }[level] || 'default';
    },
    actionColor(action, direction) {
      if (direction === 'command') return 'blue';
      const map = { wash: 'red', leave: 'red', open: 'cyan', heartbeat: 'default', ca_send: 'orange' };
      return map[action] || 'default';
    },
    isKeyEvent(record) {
      return ['wash', 'leave', 'account_query', 'wash_zero', 'all_down', 'check'].includes(record.action);
    },
    rowClass(record) {
      if (record.level === 'ERROR') return 'row-error';
      if (record.level === 'WARNING') return 'row-warning';
      if (this.isKeyEvent(record)) return 'row-key';
      return '';
    },
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
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'Authorization': this.getToken(),
            },
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
:deep(.row-error td) { background: #fff2f0 !important; }
:deep(.row-warning td) { background: #fffbe6 !important; }
:deep(.row-key td) { background: #f6ffed !important; }
</style>
