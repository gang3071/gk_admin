<template>
  <div class="page-wrap">
    <a-tabs v-model:activeKey="activeTab" type="card">

      <!-- ══════════════════════════════════════════════════════════
           Tab 1：多店概覽（原有功能）
      ══════════════════════════════════════════════════════════ -->
      <a-tab-pane key="overview" tab="多店概覽">
        <div class="tab1-wrap">
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

            <div v-else-if="error" class="center-state">
              <a-result status="error" :title="trans.states.search_failed" :sub-title="error">
                <template #extra>
                  <a-button type="primary" @click="doSearch">{{ trans.states.retry }}</a-button>
                </template>
              </a-result>
            </div>

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
                          <div :class="['stat-value', amountClass(panel.machine_win_loss)]">
                            {{ fmt(panel.machine_win_loss) }}
                          </div>
                          <div :class="['profit-tag', profitTagClass(panel.machine_win_loss)]">
                            {{ profitLabel(panel.machine_win_loss) }}
                          </div>
                        </div>
                      </a-col>
                      <a-col :span="8">
                        <div class="highlight-stat">
                          <div class="stat-label">{{ trans.panel.electronic_win_loss }}</div>
                          <div :class="['stat-value', amountClass(panel.electronic_game_win_loss)]">
                            {{ fmt(panel.electronic_game_win_loss) }}
                          </div>
                          <div :class="['profit-tag', profitTagClass(panel.electronic_game_win_loss)]">
                            {{ profitLabel(panel.electronic_game_win_loss) }}
                          </div>
                        </div>
                      </a-col>
                      <a-col :span="8">
                        <div class="highlight-stat highlight-net">
                          <div class="stat-label">{{ trans.panel.net_revenue }}</div>
                          <div :class="['stat-value', amountClass(calcNetRevenue(panel))]">
                            {{ fmt(calcNetRevenue(panel)) }}
                          </div>
                          <div :class="['profit-tag', profitTagClass(calcNetRevenue(panel))]">
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
                        :class="['cost-item', { 'item-active': activeTypeId == typeId }]"
                        @click="selectType(typeId)"
                      >
                        <div class="cost-item-top">
                          <span class="cost-label">{{ label }}</span>
                          <span :class="['cost-amount', parseFloat(panel.delivery_types[typeId] || 0) > 0 ? 'amount-neg' : 'amount-zero']">
                            {{ fmt(panel.delivery_types[typeId] || '0') }}
                          </span>
                        </div>
                        <div class="cost-item-bar">
                          <div class="cost-bar-wrap">
                            <div class="cost-bar" :style="{ width: costPercent(panel, typeId) + '%' }"></div>
                          </div>
                          <span class="cost-pct">{{ costPercent(panel, typeId) }}%</span>
                        </div>
                      </div>
                      <div class="cost-total">
                        <span>{{ trans.panel.total }}</span>
                        <span :class="amountClass('-' + calcTotalCost(panel))">{{ fmt(calcTotalCost(panel)) }}</span>
                      </div>
                    </div>

                    <!-- 钱包流水参考（不计入营收） -->
                    <a-divider style="margin:10px 0 8px" orientation="left" orientation-margin="0">
                      <span style="font-size:12px; color:#8c8c8c">{{ trans.panel.wallet_detail }}</span>
                    </a-divider>

                    <div class="cost-list wallet-list">
                      <div
                        v-for="(label, typeId) in trans.wallet_types"
                        :key="typeId"
                        :class="['cost-item', 'wallet-item', { 'item-active': activeTypeId == typeId }]"
                        @click="selectType(typeId)"
                      >
                        <span class="cost-label">{{ label }}</span>
                        <span class="cost-amount amount-muted">
                          {{ fmt(panel.delivery_types[typeId] || '0') }}
                        </span>
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
                <div class="desc-index-section">{{ trans.panel.operating_cost }}</div>
                <div
                  v-for="(label, typeId) in (trans.cost_types || {})"
                  :key="'c'+typeId"
                  class="desc-index-item"
                  @click="selectType(typeId)"
                >{{ label }}</div>
                <div class="desc-index-section" style="margin-top:8px">{{ trans.panel.wallet_detail }}</div>
                <div
                  v-for="(label, typeId) in (trans.wallet_types || {})"
                  :key="'w'+typeId"
                  class="desc-index-item desc-index-item--muted"
                  @click="selectType(typeId)"
                >{{ label }}</div>
              </div>
            </div>
          </div><!-- /global-desc-panel -->
        </div><!-- /main-area -->
        </div><!-- /tab1-wrap -->
      </a-tab-pane>

      <!-- ══════════════════════════════════════════════════════════
           Tab 2：單店詳細報表（新功能）
      ══════════════════════════════════════════════════════════ -->
      <a-tab-pane key="detail" tab="單店詳細報表">
        <div class="tab2-wrap">
        <div class="finance-summary">
          <!-- 筛选区域 -->
          <a-card :body-style="{ padding: '16px' }" style="margin-bottom: 16px;">
            <a-space wrap>
              <a-select
                v-model:value="detailForm.store_id"
                placeholder="請選擇門店"
                style="width: 260px;"
                show-search
                :filter-option="filterOption"
                allow-clear
              >
                <a-select-option v-for="(label, id) in store_options" :key="id" :value="Number(id)">
                  {{ label }}
                </a-select-option>
              </a-select>

              <a-range-picker
                v-model:value="detailDateRange"
                :show-time="{ format: 'HH:mm:ss' }"
                format="YYYY-MM-DD HH:mm:ss"
                value-format="YYYY-MM-DD HH:mm:ss"
                style="width: 420px;"
                :placeholder="['開始時間', '結束時間']"
              />

              <a-button type="primary" :loading="detailLoading" @click="handleDetailSearch">查詢</a-button>
            </a-space>
          </a-card>

          <!-- 加载状态 -->
          <div v-if="detailLoading" style="text-align: center; padding: 60px;">
            <a-spin size="large" />
          </div>

          <!-- 报表内容 -->
          <div v-else-if="reportData">
            <!-- 1. 總覽 -->
            <a-card title="總覽" class="section-card">
              <table class="report-table">
                <thead>
                  <tr>
                    <th>總入</th>
                    <th>總出</th>
                    <th>利潤</th>
                    <th>總彩金</th>
                    <th>RTP</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>{{ fmtNum(reportData.overview.total_in) }}</td>
                    <td>{{ fmtNum(reportData.overview.total_out) }}</td>
                    <td :class="profitClass(reportData.overview.profit)">{{ fmtNum(reportData.overview.profit) }}</td>
                    <td>{{ fmtNum(reportData.overview.total_lottery) }}</td>
                    <td>{{ fmtPct(reportData.overview.rtp) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>

            <!-- 2. 贈點與福利明細 -->
            <a-card title="贈點與福利明細" class="section-card">
              <table class="report-table">
                <thead>
                  <tr>
                    <th>VIP生日禮</th>
                    <th>VIP升級禮</th>
                    <th>VIP每日登陸獎</th>
                    <th>總外贈點</th>
                    <th>體驗券</th>
                    <th>福利券</th>
                    <th>摸彩獎</th>
                    <th class="subtotal-col">小計</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>{{ fmtNum(reportData.welfare.vip_birthday) }}</td>
                    <td>{{ fmtNum(reportData.welfare.vip_upgrade) }}</td>
                    <td>{{ fmtNum(reportData.welfare.vip_daily_login) }}</td>
                    <td>{{ fmtNum(reportData.welfare.activity_give) }}</td>
                    <td>{{ fmtNum(reportData.welfare.experience_coupon) }}</td>
                    <td>{{ fmtNum(reportData.welfare.welfare_coupon) }}</td>
                    <td>{{ fmtNum(reportData.welfare.lottery_ticket) }}</td>
                    <td class="subtotal-col">{{ fmtNum(reportData.welfare.subtotal) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>

            <!-- 3. 電子遊戲 -->
            <a-card title="電子遊戲" class="section-card">
              <table class="report-table">
                <thead>
                  <tr>
                    <th>電子總押注</th>
                    <th>電子總贏分</th>
                    <th>玩家輸贏</th>
                    <th>電子彩金</th>
                    <th>RTP</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>{{ fmtNum(reportData.electronic.total_bet) }}</td>
                    <td>{{ fmtNum(reportData.electronic.total_win) }}</td>
                    <td :class="profitClass(reportData.electronic.player_diff)">{{ fmtNum(reportData.electronic.player_diff) }}</td>
                    <td>{{ fmtNum(reportData.electronic.lottery) }}</td>
                    <td>{{ fmtPct(reportData.electronic.rtp) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>

            <!-- 4. 鋼珠（線下 / 線上） -->
            <a-card title="鋼珠（線下 / 線上）" class="section-card">
              <table class="report-table vertical-table">
                <thead>
                  <tr>
                    <th style="width:80px">類別</th>
                    <th>項目</th>
                    <th style="width:160px">數值</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- 線下 -->
                  <tr>
                    <td class="row-label" rowspan="6">線下</td>
                    <td class="item-label">鋼珠總上點</td>
                    <td>{{ fmtNum(reportData.steel_ball.offline.up_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">鋼珠總下點</td>
                    <td>{{ fmtNum(reportData.steel_ball.offline.down_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">上下點小計</td>
                    <td>{{ fmtNum(reportData.steel_ball.offline.subtotal) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">鋼珠彩金</td>
                    <td>{{ fmtNum(reportData.steel_ball.offline.lottery) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">鋼珠總盈虧</td>
                    <td :class="profitClass(reportData.steel_ball.offline.total_profit)">{{ fmtNum(reportData.steel_ball.offline.total_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">RTP</td>
                    <td>{{ fmtPct(reportData.steel_ball.offline.rtp) }}</td>
                  </tr>
                  <!-- 線上 -->
                  <tr class="section-divider">
                    <td class="row-label" rowspan="6">線上</td>
                    <td class="item-label">鋼珠總上點</td>
                    <td>{{ fmtNum(reportData.steel_ball.online.up_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">鋼珠總下點</td>
                    <td>{{ fmtNum(reportData.steel_ball.online.down_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">上下點小計</td>
                    <td>{{ fmtNum(reportData.steel_ball.online.subtotal) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">鋼珠彩金</td>
                    <td>{{ fmtNum(reportData.steel_ball.online.lottery) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">鋼珠總盈虧</td>
                    <td :class="profitClass(reportData.steel_ball.online.total_profit)">{{ fmtNum(reportData.steel_ball.online.total_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">RTP</td>
                    <td>{{ fmtPct(reportData.steel_ball.online.rtp) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>

            <!-- 5. 斯洛（線下 / 線上） -->
            <a-card title="斯洛（線下 / 線上）" class="section-card">
              <table class="report-table vertical-table">
                <thead>
                  <tr>
                    <th style="width:80px">類別</th>
                    <th>項目</th>
                    <th style="width:160px">數值</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- 線下 -->
                  <tr>
                    <td class="row-label" rowspan="10">線下</td>
                    <td class="item-label">斯洛總上點</td>
                    <td>{{ fmtNum(reportData.slot.offline.up_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">斯洛總下點</td>
                    <td>{{ fmtNum(reportData.slot.offline.down_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">上下點小計</td>
                    <td>{{ fmtNum(reportData.slot.offline.subtotal) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">斯洛彩金</td>
                    <td>{{ fmtNum(reportData.slot.offline.lottery) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">總盈虧</td>
                    <td :class="profitClass(reportData.slot.offline.total_profit)">{{ fmtNum(reportData.slot.offline.total_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">上下點RTP</td>
                    <td>{{ fmtPct(reportData.slot.offline.up_down_rtp) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">斯洛總押分</td>
                    <td>{{ fmtNum(reportData.slot.offline.pressure) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">斯洛總得分</td>
                    <td>{{ fmtNum(reportData.slot.offline.score) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">押得總盈虧</td>
                    <td :class="profitClass(reportData.slot.offline.press_score_profit)">{{ fmtNum(reportData.slot.offline.press_score_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">押得RTP</td>
                    <td>{{ fmtPct(reportData.slot.offline.press_score_rtp) }}</td>
                  </tr>
                  <!-- 線上 -->
                  <tr class="section-divider">
                    <td class="row-label" rowspan="10">線上</td>
                    <td class="item-label">斯洛總上點</td>
                    <td>{{ fmtNum(reportData.slot.online.up_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">斯洛總下點</td>
                    <td>{{ fmtNum(reportData.slot.online.down_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">上下點小計</td>
                    <td>{{ fmtNum(reportData.slot.online.subtotal) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">斯洛彩金</td>
                    <td>{{ fmtNum(reportData.slot.online.lottery) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">總盈虧</td>
                    <td :class="profitClass(reportData.slot.online.total_profit)">{{ fmtNum(reportData.slot.online.total_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">上下點RTP</td>
                    <td>{{ fmtPct(reportData.slot.online.up_down_rtp) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">斯洛總押分</td>
                    <td>{{ fmtNum(reportData.slot.online.pressure) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">斯洛總得分</td>
                    <td>{{ fmtNum(reportData.slot.online.score) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">押得總盈虧</td>
                    <td :class="profitClass(reportData.slot.online.press_score_profit)">{{ fmtNum(reportData.slot.online.press_score_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label">押得RTP</td>
                    <td>{{ fmtPct(reportData.slot.online.press_score_rtp) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>
          </div>

          <!-- 空状态 -->
          <a-empty v-else-if="detailSearched" description="暫無數據" />
        </div>
        </div>
      </a-tab-pane>

    </a-tabs>
  </div>
</template>

<script>
export default {
  name: 'StoreFinanceSummary',
  props: {
    // Tab 1：多店概覽
    trans: {
      type: Object,
      default: () => ({
        filter: { placeholder_agent: '', placeholder_stores: '', placeholder_date: '', start_time: '', end_time: '', query: '查询', reset: '重置' },
        date_types: [],
        panel: { machine_win_loss: '', electronic_win_loss: '', net_revenue: '', operating_cost: '', cost_detail: '', wallet_detail: '', total: '', profit: '盈利', loss: '亏损', even: '持平' },
        states: { hint: '', search_failed: '', retry: '', no_data: '' },
        cost_types: {},
        wallet_types: {},
        desc_panel: { title: '', click_hint: '' },
      }),
    },
    // Tab 2：單店詳細報表
    store_options: {
      type: Object,
      default: () => ({})
    },
    detail_search_url: {
      type: String,
      default: ''
    },
  },
  data() {
    return {
      activeTab: 'overview',

      // ── Tab 1 state ──
      form: { agentId: null, storeIds: [], dateType: null, dateRange: null },
      loading:      false,
      searched:     false,
      error:        null,
      agents:       [],
      stores:       [],
      panels:       [],
      activeTypeId: null,

      // ── Tab 2 state ──
      detailForm:     { store_id: null },
      detailDateRange: [],
      detailLoading:  false,
      detailSearched: false,
      reportData:     null,

      // ── Tab 1 账变类型说明文案 ──
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
        // ── 钱包流水参考类型说明 ──
        1:  '【管理员加点】\n\n来源：渠道后台管理员（代理或店家账号）在「玩家管理」页面手动操作。\n\n触发流程：管理员选择目标玩家 → 填写加分金额及备注 → 确认后系统立即写入账变记录，玩家余额实时增加。\n\n常见场景：\n• 活动积分人工赠送\n• 充值未到账的人工补偿\n• 客服处理投诉赔付\n\n⚠️ 注意：此操作不产生外部资金流水，属于后台单方面授信，建议填写备注留存审计记录。不计入营收，仅作钱包流水参考。',
        4:  '【机台上分】\n\n来源：玩家在门店实体机台（钢珠机或 Slot 机）发起「开分」操作。\n\n触发流程：玩家在机台界面选择开分金额 → gk_work 机台代理服务接收请求 → 从玩家账户扣除余额并写入 PlayerGameLog（上分记录）→ 同步写入账变记录（type=4）→ 机台积分对应增加。\n\n财务意义：玩家账户余额减少，机台游戏积分增加。此数据已计入「实体机台输赢」顶部汇总（机台上分 - 机台下分），此处仅供参考。',
        5:  '【机台下分】\n\n来源：玩家在门店实体机台（钢珠机或 Slot 机）发起「洗分」操作。\n\n触发流程：玩家在机台界面选择洗分金额 → gk_work 机台代理服务接收请求 → 将机台剩余积分换算为账户余额 → 同步写入账变记录（type=5）→ 玩家余额增加。\n\n财务意义：机台积分减少，玩家账户余额增加。此数据已计入「实体机台输赢」顶部汇总（机台上分 - 机台下分），此处仅供参考。',
        6:  '【充值(开分)】\n\n来源：玩家通过渠道 App/H5 在线发起充值（开分）申请，由门店以「开分券」方式完成。\n\n触发流程：\n① 玩家在 App 提交开分申请（填写金额）\n② 系统生成「开分券」（内含二维码）\n③ 门店核验申请并打印/展示开分券\n④ 玩家在实体机台扫描开分券\n⑤ 机台确认后写入账变记录（type=6），玩家余额扣除\n\n⚠️ 注意：此为钱包充值记录，玩家余额尚未在游戏中消耗，不计入营收计算，仅作流水参考。',
        7:  '【提现(洗分)】\n\n来源：玩家在实体机台完成游戏后，通过 App/H5 发起提现（洗分）申请，由门店以「洗分券」方式线下兑现。\n\n触发流程：\n① 玩家在 App 提交洗分申请（填写洗分金额）\n② 系统写入账变记录（type=7），玩家余额扣除，同时生成「洗分券」\n③ 门店核验洗分券（扫码或输入券号）\n④ 门店线下向玩家支付现金\n\n⚠️ 注意：此为钱包提现记录，不计入营收计算，仅作流水参考。',
        8:  '【管理员扣点】\n\n来源：渠道后台管理员（代理或店家账号）在「玩家管理」页面手动操作。\n\n触发流程：管理员选择目标玩家 → 填写扣分金额及原因 → 确认后系统立即写入账变记录，玩家余额实时减少。\n\n常见场景：\n• 充值异常已打款但需部分追回\n• 账户违规处理\n• 账务对账误差调整\n\n⚠️ 注意：此操作不可撤销，务必填写操作原因备注。不计入营收，仅作钱包流水参考。',
        22: '【特殊类型】\n\n来源：系统内部非常规流程或历史数据迁移。\n\n触发条件：不遵循标准业务流程，通常由开发人员直接操作或系统特殊脚本写入。\n\n常见场景：\n• 系统迁移时的历史数据对账补录\n• 技术团队修复数据错误时的调整记录\n\n⚠️ 注意：正常运营状态下此类型账变应极少出现，如大量出现 type22 记录，需立即联系技术团队排查。不计入营收，仅作参考。',
        23: '【投钞】\n\n来源：玩家向门店实体机台硬件投入现金（硬币或纸币）。\n\n触发流程：机台硬件（硬币识别器/纸币接收器）读取现金 → gk_work 机台代理服务接收投钞事件 → 按面值为玩家账户计入对应积分 → 写入账变记录（type=23）。\n\n资金流向：\n• 物理现金：存入机台钱箱（属于门店现金收入）\n• 账户积分：玩家账户余额增加（机台欠玩家的积分）\n\n⚠️ 注意：投钞是门店实体机台的重要现金流来源，需与收银台现金对账核查。此处仅作流水参考，实际现金需线下单独统计。',
        31: '【预扣】\n\n来源：玩家提交提现/洗分申请时系统自动触发。\n\n触发流程：玩家申请提现 → 系统立即检查余额是否充足 → 充足则写入账变记录（type=31）将对应金额冻结（余额逻辑扣除，显示为预扣状态）→ 提现进入审核队列。\n\n后续处理：\n• 审核通过 → 写入 type7（提现）正式扣款，type31 对应冲销\n• 审核拒绝 → 写入 type32（退款）解冻金额，余额恢复\n\n⚠️ 注意：分析账变数据时，type31（预扣）和 type7（提现）需配对分析，不计入营收。',
        32: '【退款】\n\n来源：充值异常处理、提现驳回解冻、或客服纠纷处理。\n\n常见触发场景：\n• 充值审核驳回 → 退还玩家已打款金额\n• 提现审核拒绝 → 解冻预扣（type31）金额\n• 重复充值误操作 → 退还多余金额\n\n⚠️ 注意：退款记录较为敏感，对账时务必与对应的充值记录（type6）或预扣记录（type31）配对核查。此处仅作流水参考，不计入营收。',
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
      return Object.assign({}, this.trans.cost_types || {}, this.trans.wallet_types || {});
    },
  },
  mounted() {
    this.loadOptions();
  },
  methods: {
    // ── Tab 1 methods ──

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
    costPercent(panel, typeId) {
      const total = parseFloat(this.calcTotalCost(panel));
      if (total <= 0) return 0;
      const amount = parseFloat(panel.delivery_types[typeId] || 0);
      return Math.round(amount / total * 100);
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

    // ── Tab 2 methods ──

    filterOption(input, option) {
      return option.children[0].children.toLowerCase().includes(input.toLowerCase());
    },
    fmtNum(val) {
      const n = Number(val) || 0;
      return n.toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    fmtPct(val) {
      const n = Number(val) || 0;
      return n.toFixed(2) + '%';
    },
    profitClass(val) {
      const n = Number(val);
      if (n > 0) return 'profit-positive';
      if (n < 0) return 'profit-negative';
      return '';
    },
    handleDetailSearch() {
      if (!this.detailForm.store_id) {
        this.$message.warning('請先選擇門店');
        return;
      }

      this.detailLoading = true;
      this.reportData    = null;

      const payload = {
        store_id:   this.detailForm.store_id,
        start_date: this.detailDateRange && this.detailDateRange[0] ? this.detailDateRange[0] : '',
        end_date:   this.detailDateRange && this.detailDateRange[1] ? this.detailDateRange[1] : '',
      };

      this.$request({
        url:    this.detail_search_url,
        method: 'post',
        data:   payload,
      }).then(res => {
        this.detailLoading  = false;
        this.detailSearched = true;
        if (res.code === 200) {
          this.reportData = res.data;
        } else {
          this.$message.error(res.msg || '查詢失敗');
        }
      }).catch(() => {
        this.detailLoading  = false;
        this.detailSearched = true;
        this.$message.error('查詢失敗，請稍後重試');
      });
    },
  },
};
</script>

<style scoped>
/* ── 整体布局：只提供外边距，不限高度，让 a-tabs 自然渲染 ── */
.page-wrap   { padding: 16px; }
.search-card { margin-bottom: 12px; }

/* ── Tab 1 包裹层：显式高度 + overflow:hidden，内部自己滚 ── */
/* 100vh - ExAdmin顶栏(72px) - 页面padding(32px) - tabs标签头(42px) - gap(12px) ≈ 160px */
.tab1-wrap {
  height: calc(100vh - 160px);
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow: hidden;
}

/* ── Tab 2 包裹层：与 Tab1 等高，整体可滚动 ── */
.tab2-wrap {
  height: calc(100vh - 160px);
  overflow-y: auto;
  overflow-x: hidden;
}

/* ── Tab 1 主体：左侧面板 + 右侧常驻说明 ── */
.main-area   { flex:1; min-height:0; display:flex; gap:12px; overflow:hidden; }
.result-wrap { flex:1; min-width:0; display:flex; flex-direction:column; overflow:hidden; }
.center-state { display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; }
.panels-scroll { flex:1; overflow-y:auto; padding-right:4px; }

/* ── 卡片标题 ── */
.card-header { display:flex; align-items:center; flex-wrap:wrap; gap:4px; }
.store-name  { font-weight:700; font-size:14px; }
.agent-name  { font-size:12px; color:#8c8c8c; margin-left:4px; }

/* ── 顶部三栏统计 ── */
.highlight-row  { margin-bottom:4px; }
.highlight-stat { background:#f8f9ff; border-radius:6px; padding:8px 10px; text-align:center; }
.highlight-net  { background:#fffbe6; border:1px solid #ffe58f; }
.stat-label     { font-size:11px; color:#8c8c8c; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.stat-value     { font-size:18px; font-weight:700; }

/* ── 运营成本明细 ── */
.cost-list  { display:flex; flex-direction:column; gap:3px; }
.cost-item  { display:flex; flex-direction:column; padding:4px 6px; border-radius:4px; background:#fafafa; font-size:12px; cursor:pointer; transition:background .15s; }
.cost-item:hover { background:#fff7e6; }
.item-active     { background:#ffe7ba !important; }
.cost-item-top   { display:flex; justify-content:space-between; align-items:center; }
.cost-item-bar   { display:flex; align-items:center; gap:6px; margin-top:3px; }
.cost-label      { color:#595959; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cost-bar-wrap   { flex:1; height:4px; background:#f0f0f0; border-radius:2px; overflow:hidden; }
.cost-bar        { height:100%; background:#ff9c6e; border-radius:2px; transition:width .4s ease; }
.cost-pct        { flex-shrink:0; width:34px; text-align:right; font-size:11px; color:#8c8c8c; }
.cost-amount     { flex-shrink:0; font-weight:600; white-space:nowrap; }
.cost-total      { display:flex; justify-content:space-between; align-items:center; padding:4px 6px; margin-top:2px; border-top:1px solid #f0f0f0; font-size:12px; font-weight:700; color:#262626; }

/* ── 钱包流水参考列表 ── */
.wallet-list .cost-item { background:#fafafa; opacity:.85; }
.wallet-list .cost-item:hover { background:#f0f5ff; opacity:1; }
.wallet-list .item-active { background:#d6e4ff !important; opacity:1; }

/* ── 右侧常驻说明面板 ── */
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
.desc-index-section  { font-size:10px; color:#8c8c8c; padding:2px 6px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; }
.desc-index-item--muted { color:#8c8c8c; }
.desc-index-item--muted:hover { background:#f5f5f5; }

/* ── Tab 1 金额颜色 ── */
.amount-pos   { color:#3f8600; }
.amount-neg   { color:#cf1322; }
.amount-zero  { color:#8c8c8c; }
.amount-muted { color:#adb5bd; }

/* ── Tab 1 盈亏标签 ── */
.profit-tag  { display:inline-block; margin-top:4px; padding:1px 8px; border-radius:10px; font-size:11px; font-weight:600; }
.tag-profit  { background:#f6ffed; border:1px solid #b7eb8f; color:#3f8600; }
.tag-loss    { background:#fff1f0; border:1px solid #ffa39e; color:#cf1322; }
.tag-even    { background:#fafafa; border:1px solid #d9d9d9; color:#8c8c8c; }

/* ── Tab 2 layout ── */
.finance-summary { padding: 16px; }
.section-card    { margin-bottom: 16px; }

/* ── Tab 2 report table ── */
.report-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.report-table th,
.report-table td {
  border: 1px solid #d9d9d9;
  padding: 8px 12px;
  text-align: center;
  white-space: nowrap;
}
.report-table thead tr {
  background-color: #fafafa;
  font-weight: 600;
  color: #595959;
}
.report-table tbody tr:hover {
  background-color: #f5f5f5;
}
.row-label {
  font-weight: 600;
  background-color: #f0f5ff;
  color: #1d39c4;
}
.subtotal-col {
  font-weight: 600;
  background-color: #f6ffed;
}

/* ── Tab 2 profit colors ── */
.profit-positive { color: #389e0d; font-weight: 600; }
.profit-negative { color: #cf1322; font-weight: 600; }

/* ── Tab 2 vertical table (钢珠/斯洛) ── */
.vertical-table { max-width: 600px; }
.vertical-table .item-label { text-align: left; color: #595959; padding-left: 16px; }
.vertical-table .section-divider td { border-top: 2px solid #d9d9d9; }
</style>
