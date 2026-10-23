<template>
  <div style="padding: 4px 0">
    <!-- 时间范围 & 刷新 -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
      <span style="color:#666; font-size:13px">
        {{ timeRange ? `查询时间范围：${timeRange}` : '' }}
        <a-tag v-if="!loading" style="margin-left:8px">{{ logs.length }} 条</a-tag>
      </span>
      <a-button size="small" :loading="loading" @click="fetchData">刷新</a-button>
    </div>

    <!-- 加载中 -->
    <div v-if="loading" style="text-align:center; padding:40px">
      <a-spin />
      <div style="margin-top:8px; color:#999; font-size:13px">查询 ClickHouse 中...</div>
    </div>

    <!-- 错误 -->
    <a-result v-else-if="error" status="error" title="查询失败" :sub-title="error">
      <template #extra>
        <a-button type="primary" @click="fetchData">重试</a-button>
      </template>
    </a-result>

    <!-- 数据表格 -->
    <a-table
      v-else
      :columns="columns"
      :data-source="logs"
      :pagination="false"
      :scroll="{ x: 1100, y: 420 }"
      size="small"
      :row-key="(r, i) => i"
    >
      <!-- 级别 -->
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'level'">
          <a-tag :color="levelColor(record.level)">{{ record.level }}</a-tag>
        </template>
        <template v-else-if="column.key === 'source_type'">
          <a-tag color="blue">{{ record.source_type }}</a-tag>
        </template>
        <template v-else-if="column.key === 'log_direction'">
          <a-tag :color="record.log_direction === 'command' ? 'orange' : record.log_direction === 'response' ? 'green' : 'default'">
            {{ record.log_direction }}
          </a-tag>
        </template>
        <template v-else-if="column.key === 'raw_send'">
          <span style="font-family:monospace; font-size:11px; color:#595959">{{ record.raw_send || '-' }}</span>
        </template>
        <template v-else-if="column.key === 'raw_recv'">
          <span style="font-family:monospace; font-size:11px; color:#595959">{{ record.raw_recv || '-' }}</span>
        </template>
        <template v-else-if="column.key === 'extra_data'">
          <a-tooltip v-if="record.extra_data" :title="record.extra_data" placement="topLeft">
            <span style="font-size:11px; cursor:pointer; color:#1890ff">查看</span>
          </a-tooltip>
          <span v-else style="color:#ccc">-</span>
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
      columns: [
        { title: '时间',     dataIndex: 'datetime',      key: 'datetime',      width: 175, fixed: 'left' },
        { title: '级别',     dataIndex: 'level',         key: 'level',         width: 80,  align: 'center' },
        { title: '来源',     dataIndex: 'source_type',   key: 'source_type',   width: 90,  align: 'center' },
        { title: '方向',     dataIndex: 'log_direction', key: 'log_direction', width: 90,  align: 'center' },
        { title: '操作',     dataIndex: 'action',        key: 'action',        width: 100 },
        { title: '描述',     dataIndex: 'action_desc',   key: 'action_desc',   ellipsis: true },
        { title: '发送指令', dataIndex: 'raw_send',      key: 'raw_send',      width: 160 },
        { title: '接收响应', dataIndex: 'raw_recv',      key: 'raw_recv',      width: 160 },
        { title: '扩展数据', dataIndex: 'extra_data',    key: 'extra_data',    width: 80,  align: 'center' },
      ],
    };
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    levelColor(level) {
      return { INFO: 'green', WARNING: 'orange', ERROR: 'red' }[level] || 'default';
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
