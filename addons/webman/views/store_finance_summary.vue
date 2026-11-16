<template>
  <div class="page-wrap">

    <!-- 筛选栏 -->
    <a-card class="search-card">
      <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap">
        <a-select
          v-model:value="form.agentId"
          allow-clear
          show-search
          option-filter-prop="label"
          placeholder="代理（全部）"
          style="width:200px"
          :options="agents"
          @change="onAgentChange"
        />
        <a-select
          v-model:value="form.storeIds"
          mode="multiple"
          allow-clear
          show-search
          option-filter-prop="label"
          placeholder="选择门店（可多选）"
          style="min-width:280px; max-width:600px"
          :max-tag-count="4"
          :options="filteredStores"
        />
        <a-select
          v-model:value="form.dateType"
          allow-clear
          placeholder="时间类型"
          style="width:130px"
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

      <!-- 未查询 -->
      <div v-if="!searched" class="center-state">
        <a-empty description="选择门店和时间范围后点击查询" />
      </div>

      <!-- 加载中 -->
      <div v-else-if="loading" class="center-state">
        <a-spin size="large" />
      </div>

      <!-- 查询失败 -->
      <a-result v-else-if="error" status="error" title="查询失败" :sub-title="error">
        <template #extra>
          <a-button type="primary" @click="doSearch">重试</a-button>
        </template>
      </a-result>

      <!-- 无数据 -->
      <div v-else-if="!panels.length" class="center-state">
        <a-empty description="该条件下暂无数据" />
      </div>

      <!-- 面板列表 -->
      <div v-else class="panels-scroll">
        <a-row :gutter="[16, 16]">
          <a-col
            v-for="panel in panels"
            :key="panel.store_id"
            :xs="24"
            :md="12"
            :xxl="8"
          >
            <a-card class="store-card" size="small">
              <!-- 卡片标题 -->
              <template #title>
                <div class="card-header">
                  <span class="store-name">{{ panel.store_name }}</span>
                  <a-tag color="blue" style="margin-left:8px">{{ panel.store_username }}</a-tag>
                  <span class="agent-name">{{ panel.agent_name }}</span>
                </div>
              </template>

              <!-- 实体机台 + 电子游戏 高亮区 -->
              <a-row :gutter="8" class="highlight-row">
                <a-col :span="12">
                  <div class="highlight-stat">
                    <div class="stat-label">实体机台输赢</div>
                    <div class="stat-value" :class="amountClass(panel.machine_win_loss)">
                      {{ fmt(panel.machine_win_loss) }}
                    </div>
                  </div>
                </a-col>
                <a-col :span="12">
                  <div class="highlight-stat">
                    <div class="stat-label">电子游戏输赢</div>
                    <div class="stat-value" :class="amountClass(panel.electronic_game_win_loss)">
                      {{ fmt(panel.electronic_game_win_loss) }}
                    </div>
                  </div>
                </a-col>
              </a-row>

              <!-- 账变明细 -->
              <a-divider style="margin:10px 0 8px" orientation="left" orientation-margin="0">
                <span style="font-size:12px; color:#8c8c8c">账变明细</span>
              </a-divider>

              <div class="delivery-split">
                <!-- 左：收入 -->
                <div class="split-col">
                  <div class="split-col-header">收入</div>
                  <div v-for="(label, typeId) in incomeTypeLabels" :key="'i'+typeId" class="delivery-item">
                    <span class="delivery-label">{{ label }}</span>
                    <span class="delivery-amount" :class="panel.delivery_types[typeId] > 0 ? 'amount-pos' : 'amount-zero'">
                      {{ fmt(panel.delivery_types[typeId] || '0') }}
                    </span>
                  </div>
                </div>
                <!-- 右：支出 -->
                <div class="split-col">
                  <div class="split-col-header">支出</div>
                  <div v-for="(label, typeId) in expenseTypeLabels" :key="'e'+typeId" class="delivery-item">
                    <span class="delivery-label">{{ label }}</span>
                    <span class="delivery-amount" :class="panel.delivery_types[typeId] > 0 ? 'amount-neg' : 'amount-zero'">
                      {{ fmt(panel.delivery_types[typeId] || '0') }}
                    </span>
                  </div>
                </div>
              </div>
            </a-card>
          </a-col>
        </a-row>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'StoreFinanceSummary',
  data() {
    return {
      form: { agentId: null, storeIds: [], dateType: null, dateRange: null },
      loading:  false,
      searched: false,
      error:    null,
      agents:   [],
      stores:   [],
      panels:   [],
      dateTypeOptions: [
        { value: 1, label: '今天' },
        { value: 2, label: '昨天' },
        { value: 3, label: '本周' },
        { value: 4, label: '上周' },
        { value: 5, label: '本月' },
        { value: 6, label: '上月' },
      ],
      incomeTypeLabels: {
        1:  '管理员加点',
        4:  '机台上分',
        6:  '充值(开分)',
        12: '推广员分润',
        13: '彩金中奖',
        16: '全民邀请奖励',
        23: '投钞',
        32: '退款',
        33: '摸奖券奖励',
        34: 'VIP生日礼金',
        35: 'VIP升级礼金',
        36: '反水池反水',
        37: 'VIP每日登录',
        38: '活动外增',
        39: '排行榜奖励',
      },
      expenseTypeLabels: {
        5:  '机台下分',
        7:  '提现(洗分)',
        8:  '管理员扣点',
        22: '特殊类型',
        31: '预扣',
      },
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
    amountClass(val) {
      const n = parseFloat(val);
      if (isNaN(n) || n === 0) return 'amount-zero';
      return n > 0 ? 'amount-pos' : 'amount-neg';
    },
    typeLabel(typeId) {
      return this.deliveryTypeLabels[typeId] || ('类型' + typeId);
    },

    buildParams() {
      const p = {
        agent_id:  this.form.agentId  || '',
        store_ids: (this.form.storeIds || []).join(','),
        date_type: this.form.dateType || '',
      };
      if (this.form.dateRange && this.form.dateRange.length === 2) {
        p.start_time = this.form.dateRange[0].format('YYYY-MM-DD HH:mm:ss');
        p.end_time   = this.form.dateRange[1].format('YYYY-MM-DD HH:mm:ss');
      }
      return p;
    },
    async loadOptions() {
      try {
        const res = await this.$request({
          url:    'ex-admin/addons-webman-controller-ChannelStoreFinanceSummaryController/search',
          method: 'get',
          params: {},
        });
        if (res.code === 200) {
          this.agents = res.data.agents;
          this.stores = res.data.stores;
        }
      } catch (_) { /* ignore */ }
    },
    onAgentChange() {
      this.form.storeIds = [];
    },
    onDateTypeChange(val) {
      if (val) this.form.dateRange = null;
    },
    async doSearch() {
      this.loading  = true;
      this.searched = true;
      this.error    = null;

      try {
        const res = await this.$request({
          url:    'ex-admin/addons-webman-controller-ChannelStoreFinanceSummaryController/search',
          method: 'get',
          params: this.buildParams(),
        });
        if (res.code === 200) {
          this.agents = res.data.agents;
          this.stores = res.data.stores;
          this.panels = res.data.panels;
        } else {
          this.error = res.msg || '查询失败';
        }
      } catch (e) {
        this.error = e.message || '请求失败';
      } finally {
        this.loading = false;
      }
    },
    doReset() {
      this.form     = { agentId: null, storeIds: [], dateType: null, dateRange: null };
      this.searched = false;
      this.panels   = [];
      this.error    = null;
    },
  },
};
</script>

<style scoped>
.page-wrap    { display:flex; flex-direction:column; height:calc(100vh - 112px); padding:16px; box-sizing:border-box; gap:12px; overflow:hidden; }
.search-card  { flex-shrink:0; }
.result-wrap  { flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden; }
.center-state { display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; }
.panels-scroll { flex:1; overflow-y:auto; padding-right:4px; }

/* 卡片标题 */
.card-header  { display:flex; align-items:center; flex-wrap:wrap; gap:4px; }
.store-name   { font-weight:700; font-size:14px; }
.agent-name   { font-size:12px; color:#8c8c8c; margin-left:4px; }

/* 高亮统计区 */
.highlight-row  { margin-bottom:4px; }
.highlight-stat { background:#f8f9ff; border-radius:6px; padding:10px 12px; text-align:center; }
.stat-label     { font-size:12px; color:#8c8c8c; margin-bottom:4px; }
.stat-value     { font-size:20px; font-weight:700; }

/* 账变明细：左收入 右支出 */
.delivery-split   { display:grid; grid-template-columns:1fr 1fr; gap:0 10px; }
.split-col        { display:flex; flex-direction:column; gap:3px; }
.split-col-header { font-size:11px; color:#8c8c8c; padding:2px 4px; margin-bottom:2px; border-bottom:1px solid #f0f0f0; }
.delivery-item    { display:flex; justify-content:space-between; align-items:center; padding:3px 6px; border-radius:4px; background:#fafafa; font-size:12px; }
.delivery-label   { color:#595959; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:72px; }
.delivery-amount  { font-weight:600; white-space:nowrap; }

/* 金额颜色 */
.amount-pos  { color:#3f8600; }
.amount-neg  { color:#cf1322; }
.amount-zero { color:#8c8c8c; }
</style>
