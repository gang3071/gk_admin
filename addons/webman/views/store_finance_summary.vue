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
            :sm="12"
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
                    <div class="profit-tag" :class="profitTagClass(panel.machine_win_loss)">
                      {{ profitLabel(panel.machine_win_loss) }}
                    </div>
                  </div>
                </a-col>
                <a-col :span="12">
                  <div class="highlight-stat">
                    <div class="stat-label">电子游戏输赢</div>
                    <div class="stat-value" :class="amountClass(panel.electronic_game_win_loss)">
                      {{ fmt(panel.electronic_game_win_loss) }}
                    </div>
                    <div class="profit-tag" :class="profitTagClass(panel.electronic_game_win_loss)">
                      {{ profitLabel(panel.electronic_game_win_loss) }}
                    </div>
                  </div>
                </a-col>
              </a-row>

              <!-- 账变明细 -->
              <a-divider style="margin:10px 0 8px" orientation="left" orientation-margin="0">
                <span style="font-size:12px; color:#8c8c8c">账变明细（点击类型查看说明）</span>
              </a-divider>

              <div class="delivery-wrap">
                <!-- 左右：收入 / 支出 -->
                <div class="delivery-split">
                  <!-- 收入 -->
                  <div class="split-col">
                    <div class="split-col-header">收入</div>
                    <div
                      v-for="(label, typeId) in incomeTypeLabels"
                      :key="'i'+typeId"
                      class="delivery-item"
                      :class="{ 'item-active': activeType[panel.store_id] == typeId }"
                      @click="selectType(panel.store_id, typeId)"
                    >
                      <span class="delivery-label">{{ label }}</span>
                      <span class="delivery-amount" :class="panel.delivery_types[typeId] > 0 ? 'amount-pos' : 'amount-zero'">
                        {{ fmt(panel.delivery_types[typeId] || '0') }}
                      </span>
                    </div>
                  </div>
                  <!-- 支出 -->
                  <div class="split-col">
                    <div class="split-col-header">支出</div>
                    <div
                      v-for="(label, typeId) in expenseTypeLabels"
                      :key="'e'+typeId"
                      class="delivery-item"
                      :class="{ 'item-active': activeType[panel.store_id] == typeId }"
                      @click="selectType(panel.store_id, typeId)"
                    >
                      <span class="delivery-label">{{ label }}</span>
                      <span class="delivery-amount" :class="panel.delivery_types[typeId] > 0 ? 'amount-neg' : 'amount-zero'">
                        {{ fmt(panel.delivery_types[typeId] || '0') }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- 右侧说明面板 -->
                <div class="desc-panel" :class="{ 'desc-visible': !!activeType[panel.store_id] }">
                  <template v-if="activeType[panel.store_id]">
                    <div class="desc-title">
                      {{ allTypeLabels[activeType[panel.store_id]] }}
                      <span class="desc-close" @click="selectType(panel.store_id, activeType[panel.store_id])">×</span>
                    </div>
                    <div class="desc-body">{{ typeDescriptions[activeType[panel.store_id]] }}</div>
                  </template>
                  <div v-else class="desc-hint">← 点击左侧类型查看说明</div>
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
      activeType: {},   // { store_id: typeId }
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
      typeDescriptions: {
        1:  '来源：后台管理员手动操作。\n用途：用于赠送活动积分、错误补偿、人工发放奖励等，直接增加玩家账户余额。',
        4:  '来源：玩家在门店操作实体机台（钢珠机/Slot）。\n用途：玩家将账户余额转入机台以获得游戏积分（开分），此时玩家余额被扣除。',
        5:  '来源：玩家在门店操作实体机台（钢珠机/Slot）。\n用途：玩家将机台剩余积分兑换回账户余额（洗分），此时玩家余额增加。',
        6:  '来源：玩家通过渠道充值系统发起充值。\n用途：玩家以银行转账、第三方支付等方式向账户充值，完成后余额增加（即线上开分）。',
        7:  '来源：玩家在渠道系统发起提现申请。\n用途：玩家将账户余额提现，审核通过后扣除余额并打款至玩家绑定账户（即线上洗分）。',
        8:  '来源：后台管理员手动操作。\n用途：用于纠错、违规处罚、系统对账等场景，直接从玩家账户扣除余额。',
        12: '来源：系统自动结算。\n用途：推广员（上级玩家）因名下玩家产生游戏流水，系统按约定比例自动发放分润至推广员账户。',
        13: '来源：游戏系统触发。\n用途：玩家在游戏中命中 Jackpot 彩金，系统自动将奖金发放至玩家账户。',
        16: '来源：全民邀请活动系统。\n用途：玩家通过邀请链接推荐新用户注册并完成指定条件（如首充、流水），获得对应奖励。',
        22: '来源：系统内部特殊流程。\n用途：用于特殊场景下的余额调整，如系统对账、非常规操作纠错等，不归入其他标准类型。',
        23: '来源：玩家向实体机台投入现金（硬币或纸币）。\n用途：机台读取现金后按面值为玩家账户计入积分，物理现金存入机台。',
        31: '来源：提现审核流程。\n用途：玩家提交提现申请时，系统将对应金额临时冻结（预扣）；审核通过后转为正式扣款，审核拒绝则解冻返还。',
        32: '来源：充值异常或交易纠纷处理。\n用途：对已完成的充值或支付发起退款，原金额退还至玩家账户。',
        33: '来源：摸奖券活动系统。\n用途：玩家使用摸奖券参与抽奖活动并中奖后，系统自动将奖励积分发放至玩家账户。',
        34: '来源：VIP 生日福利系统。\n用途：玩家达到对应 VIP 等级后，每年在生日当月由系统自动发放一次礼金。',
        35: '来源：VIP 升级事件触发。\n用途：玩家 VIP 等级提升时，系统自动发放一次性升级礼金作为奖励。',
        36: '来源：反水池积累与提取系统。\n用途：玩家游戏流水达到设定阈值后，从对应反水池中领取的返水奖励，直接发放至账户。',
        37: '来源：VIP 每日签到系统。\n用途：达到对应 VIP 等级的玩家，每日登录后由系统自动发放签到积分奖励。',
        38: '来源：运营人员手动在系统外部操作。\n用途：发放不经标准活动流程的一次性奖励，常用于线下活动、特殊补贴等场景。',
        39: '来源：排行榜活动结算系统。\n用途：排行榜活动周期结束后，系统按最终名次自动将奖励积分发放至对应玩家账户。',
      },
    };
  },
  computed: {
    filteredStores() {
      if (!this.form.agentId) return this.stores;
      return this.stores.filter(s => s.agentId === this.form.agentId);
    },
    allTypeLabels() {
      return Object.assign({}, this.incomeTypeLabels, this.expenseTypeLabels);
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
    profitLabel(val) {
      const n = parseFloat(val);
      if (isNaN(n) || n === 0) return '持平';
      return n > 0 ? '盈利' : '亏损';
    },
    profitTagClass(val) {
      const n = parseFloat(val);
      if (isNaN(n) || n === 0) return 'tag-even';
      return n > 0 ? 'tag-profit' : 'tag-loss';
    },
    selectType(storeId, typeId) {
      const cur = this.activeType[storeId];
      this.activeType = Object.assign({}, this.activeType, {
        [storeId]: cur == typeId ? null : typeId,
      });
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
      this.form       = { agentId: null, storeIds: [], dateType: null, dateRange: null };
      this.searched   = false;
      this.panels     = [];
      this.error      = null;
      this.activeType = {};
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

/* 账变明细外层 */
.delivery-wrap  { display:flex; gap:8px; align-items:flex-start; }

/* 收入/支出列 */
.delivery-split   { flex:1; min-width:0; display:grid; grid-template-columns:1fr 1fr; gap:0 10px; }
.split-col        { display:flex; flex-direction:column; gap:3px; }
.split-col-header { font-size:11px; color:#8c8c8c; padding:2px 4px; margin-bottom:2px; border-bottom:1px solid #f0f0f0; }
.delivery-item    { display:flex; justify-content:space-between; align-items:center; padding:3px 6px; border-radius:4px; background:#fafafa; font-size:12px; cursor:pointer; transition:background .15s; }
.delivery-item:hover { background:#e6f4ff; }
.item-active      { background:#bae0ff !important; }
.delivery-label   { color:#595959; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:72px; }
.delivery-amount  { font-weight:600; white-space:nowrap; }

/* 右侧说明面板 */
.desc-panel   { width:0; overflow:hidden; transition:width .2s ease; flex-shrink:0; }
.desc-visible { width:168px; }
.desc-title   { font-size:12px; font-weight:700; color:#1677ff; padding:4px 6px 4px; border-bottom:1px solid #e8e8e8; display:flex; justify-content:space-between; align-items:center; }
.desc-close   { cursor:pointer; color:#8c8c8c; font-size:14px; line-height:1; padding:0 2px; }
.desc-close:hover { color:#ff4d4f; }
.desc-body    { font-size:11px; color:#595959; padding:6px; line-height:1.6; white-space:pre-line; background:#f8f9ff; border-radius:0 0 4px 4px; }
.desc-hint    { font-size:11px; color:#bfbfbf; padding:8px 6px; text-align:center; writing-mode:vertical-rl; white-space:nowrap; }

/* 金额颜色 */
.amount-pos  { color:#3f8600; }
.amount-neg  { color:#cf1322; }
.amount-zero { color:#8c8c8c; }

/* 盈亏标签 */
.profit-tag   { display:inline-block; margin-top:4px; padding:1px 8px; border-radius:10px; font-size:11px; font-weight:600; }
.tag-profit   { background:#f6ffed; border:1px solid #b7eb8f; color:#3f8600; }
.tag-loss     { background:#fff1f0; border:1px solid #ffa39e; color:#cf1322; }
.tag-even     { background:#fafafa; border:1px solid #d9d9d9; color:#8c8c8c; }
</style>
