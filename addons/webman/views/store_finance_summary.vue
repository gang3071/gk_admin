<template>
  <div class="page-wrap">

    <!-- 筛选表单 -->
    <a-card class="search-card">
      <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
        <a-select
          v-model:value="form.agentId"
          allow-clear
          show-search
          option-filter-prop="label"
          placeholder="选择代理（全部）"
          style="width:220px"
          :options="agents"
          @change="onAgentChange"
        />
        <a-select
          v-model:value="form.storeId"
          allow-clear
          show-search
          option-filter-prop="label"
          placeholder="选择店家（全部）"
          style="width:240px"
          :options="filteredStores"
        />
        <a-select
          v-model:value="form.dateType"
          allow-clear
          placeholder="时间类型"
          style="width:140px"
          :options="dateTypeOptions"
          @change="onDateTypeChange"
        />
        <a-range-picker
          v-model:value="form.dateRange"
          show-time
          format="YYYY-MM-DD HH:mm:ss"
          :placeholder="['开始时间', '结束时间']"
          :disabled="!!form.dateType"
          style="width:380px"
        />
        <a-button type="primary" :loading="loading" @click="doSearch">查询</a-button>
        <a-button @click="doReset">重置</a-button>
      </div>
    </a-card>

    <!-- 结果区域 -->
    <div class="result-wrap">
      <div v-if="!searched" class="center-state">
        <a-empty description="请选择查询条件后点击查询" />
      </div>
      <div v-else-if="loading" class="center-state">
        <a-spin size="large" />
      </div>
      <a-result v-else-if="error" status="error" title="查询失败" :sub-title="error">
        <template #extra>
          <a-button type="primary" @click="doSearch">重试</a-button>
        </template>
      </a-result>
      <a-card v-else class="result-card" :body-style="{ padding:'12px', overflow:'auto', flex:1, minHeight:0 }">
        <a-table
          :columns="columns"
          :data-source="rows"
          :pagination="{ pageSize: 50, showTotal: total => '共 ' + total + ' 家店' }"
          :scroll="{ x: 1800 }"
          size="small"
          bordered
          row-key="id"
          :summary="summaryRow"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="amountFields.includes(column.dataIndex)">
              <span :style="{ color: parseFloat(record[column.dataIndex]) >= 0 ? '#3f8600' : '#cf1322', fontWeight: 500 }">
                {{ fmt(record[column.dataIndex]) }}
              </span>
            </template>
          </template>
        </a-table>
      </a-card>
    </div>

  </div>
</template>

<script>
import { h } from 'vue';

export default {
  name: 'StoreFinanceSummary',
  data() {
    return {
      form: { agentId: null, storeId: null, dateType: null, dateRange: null },
      loading:  false,
      searched: false,
      error:    null,
      agents:   [],
      stores:   [],
      rows:     [],
      summary:  {},
      amountFields: [
        'electronic_game_win_loss', 'machine_win_loss', 'lottery_ticket_sent',
        'electronic_jackpot', 'machine_jackpot', 'wallet_increase', 'wallet_decrease',
        'machine_rebate', 'electronic_rebate', 'coupon_sent',
      ],
      dateTypeOptions: [
        { value: 1, label: '今天' },
        { value: 2, label: '昨天' },
        { value: 3, label: '本周' },
        { value: 4, label: '上周' },
        { value: 5, label: '本月' },
        { value: 6, label: '上月' },
      ],
      columns: [
        { title: '店家名称', dataIndex: 'store_name',               width: 140, fixed: 'left' },
        { title: '登录账号', dataIndex: 'store_username',           width: 120, fixed: 'left' },
        { title: '所属代理', dataIndex: 'agent_name',               width: 120 },
        { title: '电子游戏输赢', dataIndex: 'electronic_game_win_loss', width: 130, align: 'right' },
        { title: '实体机输赢',   dataIndex: 'machine_win_loss',         width: 120, align: 'right' },
        { title: '摸彩劵送出',   dataIndex: 'lottery_ticket_sent',      width: 120, align: 'right' },
        { title: '电子游戏彩金', dataIndex: 'electronic_jackpot',        width: 130, align: 'right' },
        { title: '实体机台彩金', dataIndex: 'machine_jackpot',           width: 130, align: 'right' },
        { title: '钱包增加',     dataIndex: 'wallet_increase',           width: 120, align: 'right' },
        { title: '钱包扣除',     dataIndex: 'wallet_decrease',           width: 120, align: 'right' },
        { title: '实体机台返水', dataIndex: 'machine_rebate',            width: 130, align: 'right' },
        { title: '电子游戏返水', dataIndex: 'electronic_rebate',         width: 130, align: 'right' },
        { title: '体验/福利劵',  dataIndex: 'coupon_sent',               width: 120, align: 'right' },
      ],
    };
  },
  computed: {
    filteredStores() {
      if (!this.form.agentId) return this.stores;
      return this.stores.filter(s => s.agentId === this.form.agentId);
    },
  },
  mounted() {
    this.loadOptions();
  },
  methods: {
    fmt(val) {
      const n = parseFloat(val);
      return isNaN(n) ? '0.00' : n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    getToken() {
      return localStorage.getItem((window['App-Name'] || '') + '_ex-admin-token') || '';
    },
    async request(params) {
      const qs = new URLSearchParams();
      Object.entries(params).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') qs.append(k, v); });
      const res = await fetch(
        '/ex-admin/addons-webman-controller-ChannelStoreFinanceSummaryController/search?' + qs.toString(),
        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Authorization': this.getToken() } }
      );
      return res.json();
    },
    async loadOptions() {
      try {
        const j = await this.request({});
        if (j.code === 200) {
          this.agents = j.data.agents;
          this.stores = j.data.stores;
        }
      } catch (e) { /* ignore */ }
    },
    onAgentChange() {
      this.form.storeId = null;
    },
    onDateTypeChange(val) {
      if (val) this.form.dateRange = null;
    },
    async doSearch() {
      this.loading  = true;
      this.searched = true;
      this.error    = null;

      const params = { agent_id: this.form.agentId, store_id: this.form.storeId, date_type: this.form.dateType };
      if (this.form.dateRange && this.form.dateRange.length === 2) {
        params.start_time = this.form.dateRange[0].format('YYYY-MM-DD HH:mm:ss');
        params.end_time   = this.form.dateRange[1].format('YYYY-MM-DD HH:mm:ss');
      }

      try {
        const j = await this.request(params);
        if (j.code === 200) {
          this.agents  = j.data.agents;
          this.stores  = j.data.stores;
          this.rows    = j.data.rows;
          this.summary = j.data.summary;
        } else {
          this.error = j.msg || '查询失败';
        }
      } catch (e) {
        this.error = e.message;
      } finally {
        this.loading = false;
      }
    },
    doReset() {
      this.form     = { agentId: null, storeId: null, dateType: null, dateRange: null };
      this.searched = false;
      this.rows     = [];
      this.summary  = {};
      this.error    = null;
    },
    summaryRow() {
      if (!this.rows.length || !Object.keys(this.summary).length) return null;
      const s = this.summary;
      const amountFields = this.amountFields;
      const fmt = this.fmt;

      return h('tr', { style: { background: '#fafafa', fontWeight: 700 } }, [
        h('td', { colspan: 3, style: { textAlign: 'center', padding: '8px' } }, '汇总'),
        ...amountFields.map(field =>
          h('td', { style: { textAlign: 'right', padding: '8px 12px', color: parseFloat(s[field]) >= 0 ? '#3f8600' : '#cf1322' } },
            fmt(s[field])
          )
        ),
      ]);
    },
  },
};
</script>

<style scoped>
.page-wrap    { display:flex; flex-direction:column; height:calc(100vh - 112px); padding:16px; box-sizing:border-box; gap:12px; overflow:hidden; }
.search-card  { flex-shrink:0; }
.result-wrap  { flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden; }
.center-state { display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; }
.result-card  { flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden; }
</style>
