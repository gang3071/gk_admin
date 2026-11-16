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
          :placeholder="trans.filter.placeholder_agent"
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
          :placeholder="trans.filter.placeholder_stores"
          style="min-width:280px; max-width:600px"
          :max-tag-count="4"
          :options="filteredStores"
        />
        <a-select
          v-model:value="form.dateType"
          allow-clear
          :placeholder="trans.filter.placeholder_date"
          style="width:130px"
          :options="dateTypeOptions"
          @change="onDateTypeChange"
        />
        <a-range-picker
          v-model:value="form.dateRange"
          show-time
          format="YYYY-MM-DD HH:mm:ss"
          :placeholder="[trans.filter.start_time, trans.filter.end_time]"
          :disabled="!!form.dateType"
          style="width:380px"
        />
        <a-button type="primary" :loading="loading" @click="doSearch">{{ trans.filter.query }}</a-button>
        <a-button @click="doReset">{{ trans.filter.reset }}</a-button>
      </div>
    </a-card>

    <!-- 主体区域：左侧面板 + 右侧常驻说明 -->
    <div class="main-area">

      <!-- 左侧：结果区 -->
      <div class="result-wrap">

        <div v-if="!searched" class="center-state">
          <a-empty :description="trans.states.hint" />
        </div>

        <div v-else-if="loading" class="center-state">
          <a-spin size="large" />
        </div>

        <a-result v-else-if="error" status="error" :title="trans.states.search_failed" :sub-title="error">
          <template #extra>
            <a-button type="primary" @click="doSearch">{{ trans.states.retry }}</a-button>
          </template>
        </a-result>

        <div v-else-if="!panels.length" class="center-state">
          <a-empty :description="trans.states.no_data" />
        </div>

        <div v-else class="panels-scroll">
          <a-row :gutter="[16, 16]">
            <a-col
              v-for="panel in panels"
              :key="panel.store_id"
              :xs="24"
              :sm="12"
            >
              <a-card class="store-card" size="small">
                <template #title>
                  <div class="card-header">
                    <span class="store-name">{{ panel.store_name }}</span>
                    <a-tag color="blue" style="margin-left:8px">{{ panel.store_username }}</a-tag>
                    <span class="agent-name">{{ panel.agent_name }}</span>
                  </div>
                </template>

                <!-- 顶部三栏统计 -->
                <a-row :gutter="6" class="highlight-row">
                  <a-col :span="8">
                    <div class="highlight-stat">
                      <div class="stat-label">{{ trans.panel.machine_win_loss }}</div>
                      <div class="stat-value" :class="amountClass(panel.machine_win_loss)">
                        {{ fmt(panel.machine_win_loss) }}
                      </div>
                      <div class="profit-tag" :class="profitTagClass(panel.machine_win_loss)">
                        {{ profitLabel(panel.machine_win_loss) }}
                      </div>
                    </div>
                  </a-col>
                  <a-col :span="8">
                    <div class="highlight-stat">
                      <div class="stat-label">{{ trans.panel.electronic_win_loss }}</div>
                      <div class="stat-value" :class="amountClass(panel.electronic_game_win_loss)">
                        {{ fmt(panel.electronic_game_win_loss) }}
                      </div>
                      <div class="profit-tag" :class="profitTagClass(panel.electronic_game_win_loss)">
                        {{ profitLabel(panel.electronic_game_win_loss) }}
                      </div>
                    </div>
                  </a-col>
                  <a-col :span="8">
                    <div class="highlight-stat highlight-net">
                      <div class="stat-label">{{ trans.panel.net_revenue }}</div>
                      <div class="stat-value" :class="amountClass(calcNetRevenue(panel))">
                        {{ fmt(calcNetRevenue(panel)) }}
                      </div>
                      <div class="profit-tag" :class="profitTagClass(calcNetRevenue(panel))">
                        {{ profitLabel(calcNetRevenue(panel)) }}
                      </div>
                    </div>
                  </a-col>
                </a-row>

                <!-- 运营成本明细 -->
                <a-divider style="margin:10px 0 8px" orientation="left" orientation-margin="0">
                  <span style="font-size:12px; color:#8c8c8c">{{ trans.panel.cost_detail }}</span>
                </a-divider>

                <div class="cost-list">
                  <div
                    v-for="(label, typeId) in trans.cost_types"
                    :key="typeId"
                    class="cost-item"
                    :class="{ 'item-active': activeTypeId == typeId }"
                    @click="selectType(typeId)"
                  >
                    <span class="cost-label">{{ label }}</span>
                    <span class="cost-amount" :class="parseFloat(panel.delivery_types[typeId] || 0) > 0 ? 'amount-neg' : 'amount-zero'">
                      {{ fmt(panel.delivery_types[typeId] || '0') }}
                    </span>
                  </div>
                  <div class="cost-total">
                    <span>{{ trans.panel.total }}</span>
                    <span :class="amountClass('-' + calcTotalCost(panel))">{{ fmt(calcTotalCost(panel)) }}</span>
                  </div>
                </div>

              </a-card>
            </a-col>
          </a-row>
        </div>

      </div>

      <!-- 右侧：常驻说明面板 -->
      <div class="global-desc-panel">
        <div class="desc-panel-title">{{ trans.desc_panel.title }}</div>

        <div v-if="activeTypeId" class="desc-content">
          <div class="desc-type-name">{{ (trans.cost_types || {})[activeTypeId] }}</div>
          <div class="desc-text">{{ typeDescriptions[activeTypeId] }}</div>
        </div>

        <div v-else class="desc-empty">
          <div class="desc-empty-icon">📋</div>
          <div class="desc-empty-hint" style="white-space:pre-line">{{ trans.desc_panel.click_hint }}</div>
          <a-divider style="margin:12px 0 8px" />
          <div class="desc-index">
            <div
              v-for="(label, typeId) in (trans.cost_types || {})"
              :key="typeId"
              class="desc-index-item"
              @click="selectType(typeId)"
            >{{ label }}</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'StoreFinanceSummary',
  props: {
    trans: {
      type: Object,
      default: () => ({
        filter: { placeholder_agent: '', placeholder_stores: '', placeholder_date: '', start_time: '', end_time: '', query: '查询', reset: '重置' },
        date_types: [],
        panel: { machine_win_loss: '', electronic_win_loss: '', net_revenue: '', operating_cost: '', cost_detail: '', total: '', profit: '盈利', loss: '亏损', even: '持平' },
        states: { hint: '', search_failed: '', retry: '', no_data: '' },
        cost_types: {},
        desc_panel: { title: '', click_hint: '' },
      }),
    },
  },
  data() {
    return {
      form: { agentId: null, storeIds: [], dateType: null, dateRange: null },
      loading:    false,
      searched:   false,
      error:      null,
      agents:     [],
      stores:     [],
      panels:     [],
      activeTypeId: null,
      typeDescriptions: {
        12: '【推广员分润】\n\n来源：Worker 进程按结算周期自动触发（gk_work 后台任务）。\n\n触发流程：结算周期到达 → Worker 统计推广员名下所有玩家的游戏流水 → 按约定分润比例计算奖励金额 → 批量写入账变记录（type=12）→ 推广员账户余额增加。\n\n计算逻辑：分润金额 = 下级玩家游戏净流水 × 推广员分润比例（比例由渠道配置）。\n\n适用对象：仅 is_promoter=1 的推广员账户产生此类型记录，普通玩家不参与分润。',

        13: '【彩金中奖】\n\n来源：玩家在实体机台（钢珠机/Slot机）游戏过程中触发 Jackpot 彩金。\n\n触发流程：机台游戏检测到特定中奖组合 → gk_work 机台代理服务接收彩金事件 → 计算中奖金额 → 写入账变记录（type=13）→ 金额从彩金奖励池扣除并发放至玩家账户。\n\n金额来源：独立的 Jackpot 彩金奖励池（由渠道配置和积累），与普通游戏余额分开管理。\n\n财务影响：此类账变金额通常较大，是门店实体机台的重要运营成本，大额彩金中奖会显著影响当期净营收。',

        33: '【摸奖券奖励】\n\n来源：摸奖券活动系统（LotteryTicket 模块）。\n\n触发流程：玩家使用持有的摸奖券参与抽奖 → 活动系统执行抽奖逻辑 → 命中奖项 → 写入账变记录（type=33）→ 奖励积分即时发放至玩家账户。\n\n摸奖券来源：通常通过游戏流水积累兑换、活动签到赠送、管理员发放等方式获得，本身不产生账变记录。',

        34: '【VIP生日礼金】\n\n来源：VIP 福利系统（gk_work Worker 定时任务每日检查）。\n\n触发流程：Worker 每日扫描 → 筛选「当月为生日月且符合 VIP 等级且本年度未领取」的玩家 → 写入账变记录（type=34）→ 礼金自动发放至玩家账户。\n\n发放规则：\n• 每自然年生日月仅发放一次\n• 礼金金额根据玩家当前 VIP 等级阶梯配置\n• 玩家无需手动领取，系统自动发放',

        35: '【VIP升级礼金】\n\n来源：VIP 升级事件实时触发（游戏流水结算后检测）。\n\n触发流程：玩家游戏流水结算 → 系统检测累计流水是否达到下一 VIP 等级门槛 → 达到则自动升级 → 写入账变记录（type=35）→ 升级礼金一次性发放至玩家账户。\n\n发放规则：\n• 同一 VIP 等级的升级礼金仅发放一次（首次升至该等级时）\n• 若玩家降级后重新升回同一等级，不再重复发放\n• 礼金金额按各 VIP 等级的配置表决定',

        36: '【反水池返水】\n\n来源：反水池系统（游戏流水积累与领取）。\n\n触发流程：玩家在统计周期内累计游戏流水 → 流水达到反水池领取阈值 → 玩家手动申请或系统自动发放 → 计算返水金额（流水 × 返水比例）→ 写入账变记录（type=36）→ 资金从反水池划拨至玩家账户。\n\n资金来源：由渠道统一维护的反水资金池，渠道定期充值补充。\n\n反水比例：根据玩家 VIP 等级或所在渠道的配置而定，VIP 等级越高反水比例通常越高。',

        37: '【VIP每日登录】\n\n来源：VIP 每日签到系统（玩家登录时实时触发）。\n\n触发流程：VIP 玩家登录 App/H5 → 系统检查当前 VIP 等级及今日是否已领取 → 未领取则写入账变记录（type=37）→ 积分即时发放至玩家账户。\n\n发放规则：\n• 每自然日（00:00-23:59）仅发放一次\n• 金额根据玩家当前 VIP 等级阶梯配置\n• 仅达到设定 VIP 等级门槛的玩家才能触发',

        38: '【活动外增】\n\n来源：运营人员通过管理后台工具手动批量或单点发放，不经标准活动审批流程。\n\n触发流程：运营在管理后台执行发放操作（可单个或批量选择玩家）→ 填写金额和原因 → 确认后系统写入账变记录（type=38）→ 金额计入玩家账户。\n\n常见场景：\n• 线下推广活动的线上奖励兑现\n• KOL/网红合作的返利发放\n• 特殊节假日礼包（临时决定，未走标准活动流程）\n• 渠道间合作互推奖励',

        39: '【排行榜奖励】\n\n来源：排行榜活动结算系统（gk_work Worker 周期性结算任务）。\n\n触发流程：排行榜活动周期结束（日榜/周榜/月榜/赛季榜）→ Worker 触发结算脚本 → 锁定最终排名数据 → 按名次对应配置的奖励金额计算 → 批量写入账变记录（type=39）→ 自动发放至上榜玩家账户。\n\n排名指标：通常按特定指标（如游戏流水、开分次数、在线时长等）排名，具体取决于活动配置。\n\n奖励规模：头部名次奖励金额通常较大，是阶段性的重要支出项。建议在排行榜结算日前关注资金池余额是否充足。',
      },
    };
  },
  computed: {
    filteredStores() {
      if (!this.form.agentId) return this.stores;
      return this.stores.filter(s => s.agentId === this.form.agentId);
    },
    dateTypeOptions() {
      return (this.trans.date_types || []).map(item => ({ value: item.value, label: item.label }));
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
      if (isNaN(n) || n === 0) return this.trans.panel.even;
      return n > 0 ? this.trans.panel.profit : this.trans.panel.loss;
    },
    profitTagClass(val) {
      const n = parseFloat(val);
      if (isNaN(n) || n === 0) return 'tag-even';
      return n > 0 ? 'tag-profit' : 'tag-loss';
    },
    calcTotalCost(panel) {
      return Object.keys(this.trans.cost_types || {})
        .reduce((sum, typeId) => sum + parseFloat(panel.delivery_types[typeId] || 0), 0)
        .toFixed(2);
    },
    calcNetRevenue(panel) {
      const machine    = parseFloat(panel.machine_win_loss || 0);
      const electronic = parseFloat(panel.electronic_game_win_loss || 0);
      const cost       = parseFloat(this.calcTotalCost(panel));
      return (machine + electronic - cost).toFixed(2);
    },
    selectType(typeId) {
      this.activeTypeId = this.activeTypeId == typeId ? null : typeId;
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
          this.error = res.msg || this.trans.states.search_failed;
        }
      } catch (e) {
        this.error = e.message || this.trans.states.search_failed;
      } finally {
        this.loading = false;
      }
    },
    doReset() {
      this.form         = { agentId: null, storeIds: [], dateType: null, dateRange: null };
      this.searched     = false;
      this.panels       = [];
      this.error        = null;
      this.activeTypeId = null;
    },
  },
};
</script>

<style scoped>
.page-wrap   { display:flex; flex-direction:column; height:calc(100vh - 112px); padding:16px; box-sizing:border-box; gap:12px; overflow:hidden; }
.search-card { flex-shrink:0; }

/* 主体：左侧面板 + 右侧常驻说明 */
.main-area   { flex:1; min-height:0; display:flex; gap:12px; overflow:hidden; }
.result-wrap { flex:1; min-width:0; display:flex; flex-direction:column; overflow:hidden; }
.center-state { display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; }
.panels-scroll { flex:1; overflow-y:auto; padding-right:4px; }

/* 卡片标题 */
.card-header { display:flex; align-items:center; flex-wrap:wrap; gap:4px; }
.store-name  { font-weight:700; font-size:14px; }
.agent-name  { font-size:12px; color:#8c8c8c; margin-left:4px; }

/* 顶部三栏统计 */
.highlight-row  { margin-bottom:4px; }
.highlight-stat { background:#f8f9ff; border-radius:6px; padding:8px 10px; text-align:center; }
.highlight-net  { background:#fffbe6; border:1px solid #ffe58f; }
.stat-label     { font-size:11px; color:#8c8c8c; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.stat-value     { font-size:18px; font-weight:700; }

/* 运营成本明细：单列 */
.cost-list  { display:flex; flex-direction:column; gap:3px; }
.cost-item  { display:flex; justify-content:space-between; align-items:center; padding:3px 6px; border-radius:4px; background:#fafafa; font-size:12px; cursor:pointer; transition:background .15s; }
.cost-item:hover { background:#fff7e6; }
.item-active     { background:#ffe7ba !important; }
.cost-label      { color:#595959; }
.cost-amount     { font-weight:600; white-space:nowrap; }
.cost-total      { display:flex; justify-content:space-between; align-items:center; padding:4px 6px; margin-top:2px; border-top:1px solid #f0f0f0; font-size:12px; font-weight:700; color:#262626; }

/* 右侧常驻说明面板 */
.global-desc-panel   { width:220px; flex-shrink:0; display:flex; flex-direction:column; background:#fff; border:1px solid #f0f0f0; border-radius:8px; overflow:hidden; }
.desc-panel-title    { font-size:13px; font-weight:700; color:#1677ff; padding:10px 12px; border-bottom:1px solid #f0f0f0; background:#f0f5ff; flex-shrink:0; }
.desc-content        { flex:1; overflow-y:auto; padding:12px; }
.desc-type-name      { font-size:13px; font-weight:700; color:#262626; margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid #f0f0f0; }
.desc-text           { font-size:12px; color:#595959; line-height:1.7; white-space:pre-line; }
.desc-empty          { flex:1; display:flex; flex-direction:column; align-items:center; padding:16px 12px; overflow-y:auto; }
.desc-empty-icon     { font-size:28px; margin-bottom:8px; }
.desc-empty-hint     { font-size:12px; color:#8c8c8c; text-align:center; line-height:1.6; }
.desc-index          { width:100%; display:flex; flex-direction:column; gap:4px; }
.desc-index-item     { font-size:11px; color:#1677ff; cursor:pointer; padding:3px 6px; border-radius:4px; }
.desc-index-item:hover { background:#fff7e6; }

/* 金额颜色 */
.amount-pos  { color:#3f8600; }
.amount-neg  { color:#cf1322; }
.amount-zero { color:#8c8c8c; }

/* 盈亏标签 */
.profit-tag  { display:inline-block; margin-top:4px; padding:1px 8px; border-radius:10px; font-size:11px; font-weight:600; }
.tag-profit  { background:#f6ffed; border:1px solid #b7eb8f; color:#3f8600; }
.tag-loss    { background:#fff1f0; border:1px solid #ffa39e; color:#cf1322; }
.tag-even    { background:#fafafa; border:1px solid #d9d9d9; color:#8c8c8c; }
</style>
