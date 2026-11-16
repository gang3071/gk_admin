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

                <!-- 高亮统计 -->
                <a-row :gutter="8" class="highlight-row">
                  <a-col :span="12">
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
                  <a-col :span="12">
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
                </a-row>

                <!-- 账变明细 -->
                <a-divider style="margin:10px 0 8px" orientation="left" orientation-margin="0">
                  <span style="font-size:12px; color:#8c8c8c">{{ trans.panel.delivery_detail }}</span>
                </a-divider>

                <div class="delivery-split">
                  <div class="split-col">
                    <div class="split-col-header">{{ trans.panel.income }}</div>
                    <div
                      v-for="(label, typeId) in trans.income_types"
                      :key="'i'+typeId"
                      class="delivery-item"
                      :class="{ 'item-active': activeTypeId == typeId }"
                      @click="selectType(typeId)"
                    >
                      <span class="delivery-label">{{ label }}</span>
                      <span class="delivery-amount" :class="panel.delivery_types[typeId] > 0 ? 'amount-pos' : 'amount-zero'">
                        {{ fmt(panel.delivery_types[typeId] || '0') }}
                      </span>
                    </div>
                  </div>
                  <div class="split-col">
                    <div class="split-col-header">{{ trans.panel.expense }}</div>
                    <div
                      v-for="(label, typeId) in trans.expense_types"
                      :key="'e'+typeId"
                      class="delivery-item"
                      :class="{ 'item-active': activeTypeId == typeId }"
                      @click="selectType(typeId)"
                    >
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

      <!-- 右侧：常驻说明面板 -->
      <div class="global-desc-panel">
        <div class="desc-panel-title">{{ trans.desc_panel.title }}</div>

        <div v-if="activeTypeId" class="desc-content">
          <div class="desc-type-name">{{ allTypeLabels[activeTypeId] }}</div>
          <div class="desc-text">{{ typeDescriptions[activeTypeId] }}</div>
        </div>

        <div v-else class="desc-empty">
          <div class="desc-empty-icon">📋</div>
          <div class="desc-empty-hint" style="white-space:pre-line">{{ trans.desc_panel.click_hint }}</div>
          <a-divider style="margin:12px 0 8px" />
          <div class="desc-index">
            <div
              v-for="(label, typeId) in allTypeLabels"
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
        panel: { machine_win_loss: '', electronic_win_loss: '', delivery_detail: '', income: '收入', expense: '支出', profit: '盈利', loss: '亏损', even: '持平' },
        states: { hint: '', search_failed: '', retry: '', no_data: '' },
        income_types: {},
        expense_types: {},
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
        1:  '【管理员加点】\n\n来源：渠道后台管理员（代理或店家账号）在「玩家管理」页面手动操作。\n\n触发流程：管理员选择目标玩家 → 填写加分金额及备注 → 确认后系统立即写入账变记录，玩家余额实时增加。\n\n常见场景：\n• 活动积分人工赠送\n• 充值未到账的人工补偿\n• 客服处理投诉赔付\n• 节日/生日特别赠送\n\n⚠️ 注意：此操作不产生外部资金流水，属于后台单方面授信，建议填写备注留存审计记录。',

        4:  '【机台上分】\n\n来源：玩家在门店实体机台（钢珠机或 Slot 机）发起「开分」操作。\n\n触发流程：玩家在机台界面选择开分金额 → gk_work 机台代理服务接收请求 → 从玩家账户扣除余额并写入 PlayerGameLog（上分记录）→ 同步写入账变记录（type=4）→ 机台积分对应增加。\n\n实际效果：玩家账户余额减少，机台游戏积分增加，玩家可开始游戏。\n\n财务意义：属于平台收入（玩家将余额交付机台），与「机台下分(type5)」构成实体机台盈亏的两端。\n\n⚠️ 注意：积分与余额的兑换比例由渠道后台配置，不同渠道可能不同。',

        5:  '【机台下分】\n\n来源：玩家在门店实体机台（钢珠机或 Slot 机）发起「洗分」操作。\n\n触发流程：玩家在机台界面选择洗分金额 → gk_work 机台代理服务接收请求 → 将机台剩余积分换算为账户余额并写入 PlayerGameLog（下分记录）→ 同步写入账变记录（type=5）→ 玩家余额增加。\n\n实际效果：机台积分减少，玩家账户余额增加，玩家完成本次游戏结算。\n\n财务意义：属于平台支出（机台将积分归还给玩家），门店实体机台输赢公式：机台上分(type4) - 机台下分(type5) = 平台净收益（正数盈利）。',

        6:  '【充值(开分)】\n\n来源：玩家通过渠道 App/H5 在线发起充值（开分）申请，由门店以「开分券」方式完成。\n\n触发流程：\n① 玩家在 App 提交开分申请（填写金额）\n② 系统生成「开分券」（内含二维码）\n③ 门店核验申请并打印/展示开分券\n④ 玩家在实体机台扫描开分券\n⑤ 机台确认后写入账变记录（type=6），玩家余额扣除\n⑥ 机台积分对应增加，玩家开始游戏\n\n资金流向：玩家账户余额 → 机台积分（通过券绑定）\n\n区别说明：\n• type6（开分）= 通过开分券绑定机台积分，属于线上余额换机台积分\n• type4（机台上分）= 账户内部余额直接划入机台，实时到账\n\n⚠️ 注意：开分券有使用时效，过期未扫描则自动作废，余额不扣除。',

        7:  '【提现(洗分)】\n\n来源：玩家在实体机台完成游戏后，通过 App/H5 发起提现（洗分）申请，由门店以「洗分券」方式线下兑现。\n\n触发流程：\n① 玩家在 App 提交洗分申请（填写洗分金额）\n② 系统写入账变记录（type=7），玩家余额扣除，同时生成「洗分券」\n③ 门店核验洗分券（扫码或输入券号）\n④ 门店线下向玩家支付现金（与洗分金额对应）\n⑤ 门店确认后洗分券核销完成\n\n资金流向：玩家账户余额扣除 → 门店线下现金支付给玩家\n\n区别说明：\n• type7（洗分/提现）= 通过洗分券换取门店现金，属于线下兑现\n• type5（机台下分）= 机台积分直接返还账户余额，实时到账\n\n⚠️ 注意：洗分券由门店核销后方才生效，玩家实际拿到现金取决于门店操作；若门店未及时核销，请联系渠道客服处理。',

        8:  '【管理员扣点】\n\n来源：渠道后台管理员（代理或店家账号）在「玩家管理」页面手动操作。\n\n触发流程：管理员选择目标玩家 → 填写扣分金额及原因 → 确认后系统立即写入账变记录，玩家余额实时减少。\n\n常见场景：\n• 充值异常已打款但需部分追回\n• 账户违规处理\n• 账务对账误差调整\n\n⚠️ 注意：与「管理员加点(type1)」构成一对操作，扣点操作不可撤销，务必填写操作原因备注，建议双人审核。',

        12: '【推广员分润】\n\n来源：Worker 进程按结算周期自动触发（gk_work 后台任务）。\n\n触发流程：结算周期到达 → Worker 统计推广员名下所有玩家的游戏流水 → 按约定分润比例计算奖励金额 → 批量写入账变记录（type=12）→ 推广员账户余额增加。\n\n计算逻辑：分润金额 = 下级玩家游戏净流水 × 推广员分润比例（比例由渠道配置）。\n\n适用对象：仅 is_promoter=1 的推广员账户产生此类型记录，普通玩家不参与分润。\n\n区别说明：与「全民邀请奖励(type16)」不同，type12 是按流水持续结算的分润，type16 是邀请新人成功的一次性奖励。',

        13: '【彩金中奖】\n\n来源：玩家在实体机台（钢珠机/Slot机）游戏过程中触发 Jackpot 彩金。\n\n触发流程：机台游戏检测到特定中奖组合 → gk_work 机台代理服务接收彩金事件 → 计算中奖金额 → 写入账变记录（type=13）→ 金额从彩金奖励池扣除并发放至玩家账户。\n\n金额来源：独立的 Jackpot 彩金奖励池（由渠道配置和积累），与普通游戏余额分开管理。\n\n财务影响：此类账变金额通常较大，是门店实体机台的重要支出项，大额彩金中奖会显著影响当期盈亏数据。',

        16: '【全民邀请奖励】\n\n来源：全民邀请活动系统（配置于活动管理后台）。\n\n触发流程：玩家分享专属邀请链接 → 新用户通过链接注册 → 新用户完成活动设定的达标条件（如首充金额 ≥ X 元、游戏流水 ≥ Y 元）→ 系统检测条件满足 → 写入账变记录（type=16）→ 奖励发放至邀请人账户。\n\n奖励规则：金额和条件由活动配置决定，不同推广期可能不同。\n\n区别说明：\n• type16 = 一次性邀请奖励，新用户完成条件触发一次\n• type12 = 持续性流水分润，只要下级有流水就持续结算',

        22: '【特殊类型】\n\n来源：系统内部非常规流程或历史数据迁移。\n\n触发条件：不遵循标准业务流程，通常由开发人员直接操作或系统特殊脚本写入。\n\n常见场景：\n• 系统迁移时的历史数据对账补录\n• 技术团队修复数据错误时的调整记录\n• 特殊业务场景的临时处理\n\n⚠️ 注意：正常运营状态下此类型账变应极少出现，如统计期内大量出现 type22 记录，需立即联系技术团队排查系统异常。',

        23: '【投钞】\n\n来源：玩家向门店实体机台硬件投入现金（硬币或纸币）。\n\n触发流程：机台硬件（硬币识别器/纸币接收器）读取现金 → gk_work 机台代理服务接收投钞事件 → 按面值为玩家账户计入对应积分 → 写入账变记录（type=23）。\n\n资金流向：\n• 物理现金：存入机台钱箱（属于门店现金收入）\n• 账户积分：玩家账户余额增加（机台欠玩家的积分）\n\n区别说明：\n• type23（投钞）= 外部现金转为账户积分，是实物资金的入口\n• type4（机台上分）= 账户余额转为机台积分，是账户内部流转\n\n财务意义：投钞是门店实体机台的重要现金流来源，需与收银台现金对账核查。',

        31: '【预扣】\n\n来源：玩家提交提现/洗分申请时系统自动触发。\n\n触发流程：玩家申请提现 → 系统立即检查余额是否充足 → 充足则写入账变记录（type=31）将对应金额冻结（余额逻辑扣除，显示为预扣状态）→ 提现进入审核队列。\n\n后续处理：\n• 审核通过 → 写入 type7（提现）正式扣款，type31 对应冲销\n• 审核拒绝 → 写入 type32（退款）解冻金额，余额恢复\n\n作用：防止玩家在提现审核期间将同一笔余额重复使用（防双花）。\n\n⚠️ 注意：在分析账变数据时，type31（预扣）和 type7（提现）需配对分析，避免将同一笔资金重复计入支出。',

        32: '【退款】\n\n来源：充值异常处理、提现驳回解冻、或客服纠纷处理。\n\n常见触发场景：\n• 充值审核驳回 → 退还玩家已打款金额\n• 提现审核拒绝 → 解冻预扣（type31）金额\n• 重复充值误操作 → 退还多余金额\n• 客服认定赔付 → 退还相关金额\n\n系统流程：人工审核确认退款原因 → 写入账变记录（type=32）→ 玩家余额增加 → 同步处理外部支付退款（如需）。\n\n⚠️ 注意：退款记录较为敏感，对账时务必与对应的充值记录（type6）或预扣记录（type31）配对核查，确保资金流水完整闭环。',

        33: '【摸奖券奖励】\n\n来源：摸奖券活动系统（LotteryTicket 模块）。\n\n触发流程：玩家使用持有的摸奖券参与抽奖 → 活动系统执行抽奖逻辑 → 命中奖项 → 写入账变记录（type=33）→ 奖励积分即时发放至玩家账户。\n\n摸奖券来源：通常通过游戏流水积累兑换、活动签到赠送、管理员发放等方式获得，本身不产生账变记录。\n\n奖项类型：可能是积分奖励，也可能是实物奖品（实物奖品不产生此类账变）。',

        34: '【VIP生日礼金】\n\n来源：VIP 福利系统（gk_work Worker 定时任务每日检查）。\n\n触发流程：Worker 每日扫描 → 筛选「当月为生日月且符合 VIP 等级且本年度未领取」的玩家 → 写入账变记录（type=34）→ 礼金自动发放至玩家账户。\n\n发放规则：\n• 每自然年生日月仅发放一次\n• 礼金金额根据玩家当前 VIP 等级阶梯配置\n• 玩家无需手动领取，系统自动发放\n\n⚠️ 注意：玩家生日信息需在注册时或个人资料中正确填写，否则无法触发。',

        35: '【VIP升级礼金】\n\n来源：VIP 升级事件实时触发（游戏流水结算后检测）。\n\n触发流程：玩家游戏流水结算 → 系统检测累计流水是否达到下一 VIP 等级门槛 → 达到则自动升级 → 写入账变记录（type=35）→ 升级礼金一次性发放至玩家账户。\n\n发放规则：\n• 同一 VIP 等级的升级礼金仅发放一次（首次升至该等级时）\n• 若玩家降级后重新升回同一等级，不再重复发放\n• 礼金金额按各 VIP 等级的配置表决定',

        36: '【反水池反水】\n\n来源：反水池系统（游戏流水积累与领取）。\n\n触发流程：玩家在统计周期内累计游戏流水 → 流水达到反水池领取阈值 → 玩家手动申请或系统自动发放 → 计算反水金额（流水 × 反水比例）→ 写入账变记录（type=36）→ 资金从反水池划拨至玩家账户。\n\n资金来源：由渠道统一维护的反水资金池，渠道定期充值补充。\n\n反水比例：根据玩家 VIP 等级或所在渠道的配置而定，VIP 等级越高反水比例通常越高。',

        37: '【VIP每日登录】\n\n来源：VIP 每日签到系统（玩家登录时实时触发）。\n\n触发流程：VIP 玩家登录 App/H5 → 系统检查当前 VIP 等级及今日是否已领取 → 未领取则写入账变记录（type=37）→ 积分即时发放至玩家账户。\n\n发放规则：\n• 每自然日（00:00-23:59）仅发放一次\n• 金额根据玩家当前 VIP 等级阶梯配置\n• 仅达到设定 VIP 等级门槛的玩家才能触发',

        38: '【活动外增】\n\n来源：运营人员通过管理后台工具手动批量或单点发放，不经标准活动审批流程。\n\n触发流程：运营在管理后台执行发放操作（可单个或批量选择玩家）→ 填写金额和原因 → 确认后系统写入账变记录（type=38）→ 金额计入玩家账户。\n\n常见场景：\n• 线下推广活动的线上奖励兑现\n• KOL/网红合作的返利发放\n• 特殊节假日礼包（临时决定，未走标准活动流程）\n• 渠道间合作互推奖励\n\n区别说明：与「管理员加点(type1)」相比，type38 通常有明确的活动背景，type1 更多用于个案补偿。',

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
    allTypeLabels() {
      return Object.assign({}, this.trans.income_types || {}, this.trans.expense_types || {});
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

/* 高亮统计区 */
.highlight-row  { margin-bottom:4px; }
.highlight-stat { background:#f8f9ff; border-radius:6px; padding:10px 12px; text-align:center; }
.stat-label     { font-size:12px; color:#8c8c8c; margin-bottom:4px; }
.stat-value     { font-size:20px; font-weight:700; }

/* 账变明细：收入 / 支出 */
.delivery-split   { display:grid; grid-template-columns:1fr 1fr; gap:0 10px; }
.split-col        { display:flex; flex-direction:column; gap:3px; }
.split-col-header { font-size:11px; color:#8c8c8c; padding:2px 4px; margin-bottom:2px; border-bottom:1px solid #f0f0f0; }
.delivery-item    { display:flex; justify-content:space-between; align-items:center; padding:3px 6px; border-radius:4px; background:#fafafa; font-size:12px; cursor:pointer; transition:background .15s; }
.delivery-item:hover { background:#e6f4ff; }
.item-active      { background:#bae0ff !important; }
.delivery-label   { color:#595959; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:72px; }
.delivery-amount  { font-weight:600; white-space:nowrap; }

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
.desc-index-item:hover { background:#e6f4ff; }

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
