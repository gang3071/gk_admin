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
              <!-- 查詢時段 -->
              <div v-if="tab1TimeRange" class="tab1-time-bar">
                <span class="tab1-time-label">查詢時段：</span>
                <span class="tab1-time-range">{{ tab1TimeRange }}</span>
                <span v-if="form.dateType" class="report-header-shift-badge">交班週期 08:00</span>
              </div>
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
        <div class="tab2-main">
          <!-- 左側：報表內容 -->
          <div class="tab2-result-wrap">
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

              <a-select
                v-model:value="detailDateType"
                allow-clear
                :placeholder="trans.filter.placeholder_date"
                style="width: 130px"
                :options="dateTypeOptions"
                @change="onDetailDateTypeChange"
              />

              <a-range-picker
                v-model:value="detailDateRange"
                :show-time="{ format: 'HH:mm:ss' }"
                format="YYYY-MM-DD HH:mm:ss"
                value-format="YYYY-MM-DD HH:mm:ss"
                style="width: 420px;"
                :placeholder="['開始時間', '結束時間']"
                :disabled="!!detailDateType"
                :disabled-date="detailDisabledDate"
              />

              <a-button type="primary" :loading="detailLoading" @click="handleDetailSearch">查詢</a-button>
            </a-space>
          </a-card>

          <!-- 報表頭部 -->
          <div v-if="reportMeta" class="report-header-bar">
            <span class="report-header-store">{{ reportMeta.storeName }}</span>
            <span class="report-header-sep">｜</span>
            <span class="report-header-time">{{ reportMeta.timeRange }}</span>
            <span v-if="detailDateType" class="report-header-shift-badge">交班週期 08:00</span>
          </div>

          <!-- 加载状态 -->
          <div v-if="detailLoading" style="text-align: center; padding: 60px;">
            <a-spin size="large" />
          </div>

          <!-- 报表内容 -->
          <div v-else-if="reportData">
            <!-- 1. 總覽 -->
            <a-card class="section-card">
              <template #title><span class="section-title-link" @click="scrollToDesc('desc-overview')">總覽</span></template>
              <table class="report-table">
                <thead>
                  <tr>
                    <th class="th-link" @click="scrollToDesc('desc-overview-total-in')">總入</th>
                    <th class="th-link" @click="scrollToDesc('desc-overview-total-out')">總出</th>
                    <th class="th-link" @click="scrollToDesc('desc-overview-profit')">利潤</th>
                    <th class="th-link" @click="scrollToDesc('desc-overview-lottery')">總彩金</th>
                    <th class="th-link th-rtp" @click="scrollToDesc('desc-overview-rtp')">RTP</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="val-income">{{ fmtIncome(reportData.overview.total_in) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.overview.total_out) }}</td>
                    <td :class="profitClass(reportData.overview.profit)">{{ fmtNumSign(reportData.overview.profit) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.overview.total_lottery) }}</td>
                    <td :class="['td-rtp', rtpClass(reportData.overview.rtp)]">{{ fmtPct(reportData.overview.rtp) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>

            <!-- 2. 贈點與福利明細 -->
            <a-card class="section-card">
              <template #title><span class="section-title-link" @click="scrollToDesc('desc-welfare')">贈點與福利明細</span></template>
              <table class="report-table">
                <thead>
                  <tr>
                    <th class="th-link" @click="scrollToDesc('desc-welfare-vip-birthday')">VIP生日禮</th>
                    <th class="th-link" @click="scrollToDesc('desc-welfare-vip-upgrade')">VIP升級禮</th>
                    <th class="th-link" @click="scrollToDesc('desc-welfare-vip-daily')">VIP每日登陸獎</th>
                    <th class="th-link" @click="scrollToDesc('desc-welfare-activity-give')">總外贈點</th>
                    <th class="th-link" @click="scrollToDesc('desc-welfare-experience')">體驗券</th>
                    <th class="th-link" @click="scrollToDesc('desc-welfare-welfare')">福利券</th>
                    <th class="th-link" @click="scrollToDesc('desc-welfare-lottery-ticket')">摸彩獎</th>
                    <th class="th-link" @click="scrollToDesc('desc-welfare-reverse-water')">返水<span class="note-small">含未領取</span></th>
                    <th class="th-link" @click="scrollToDesc('desc-welfare-children-cashback')">推廣紅利<span class="note-small">含未領取</span></th>
                    <th class="subtotal-col th-link" @click="scrollToDesc('desc-welfare-subtotal')">小計</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="val-expense">{{ fmtExpense(reportData.welfare.vip_birthday) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.welfare.vip_upgrade) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.welfare.vip_daily_login) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.welfare.activity_give) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.welfare.experience_coupon) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.welfare.welfare_coupon) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.welfare.lottery_ticket) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.welfare.reverse_water_pool) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.welfare.children_cashback) }}</td>
                    <td class="subtotal-col val-expense">{{ fmtExpense(reportData.welfare.subtotal) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>

            <!-- 3. 電子遊戲 -->
            <a-card class="section-card">
              <template #title>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                  <span class="section-title-link" @click="scrollToDesc('desc-electronic')">電子遊戲</span>
                  <a-tag color="blue" style="font-size:11px; font-weight:400; margin:0;">
                    <template #icon><span style="margin-right:3px;">ℹ️</span></template>
                    僅含近 3 個月數據（{{ electronicDataRange }}），超出範圍的歷史數據不計入
                  </a-tag>
                </div>
              </template>
              <table class="report-table">
                <thead>
                  <tr>
                    <th class="th-link" @click="scrollToDesc('desc-electronic-bet')">電子總押注</th>
                    <th class="th-link" @click="scrollToDesc('desc-electronic-win')">電子總贏分</th>
                    <th class="th-link th-player-diff" @click="scrollToDesc('desc-electronic-diff')">玩家輸贏<span class="note-small">以平台盈虧展示</span></th>
                    <th class="th-link" @click="scrollToDesc('desc-electronic-lottery')">電子彩金</th>
                    <th class="th-link" @click="scrollToDesc('desc-electronic-reverse-water')">返水<span class="note-small">含未領取</span></th>
                    <th class="th-link th-rtp" @click="scrollToDesc('desc-electronic-rtp')">RTP</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="val-income">{{ fmtIncome(reportData.electronic.total_bet) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.electronic.total_win) }}</td>
                    <td :class="['td-player-diff', profitClass(-reportData.electronic.player_diff)]">{{ fmtNumSign(-reportData.electronic.player_diff) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.electronic.lottery) }}</td>
                    <td class="val-expense">{{ fmtExpense(reportData.electronic.reverse_water_pool) }}</td>
                    <td :class="['td-rtp', rtpClass(reportData.electronic.rtp)]">{{ fmtPct(reportData.electronic.rtp) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>

            <!-- 4+5. 線下 / 線上 並排 -->
            <div class="side-by-side-cards">
            <!-- 4. 線下（斯洛 / 鋼珠） -->
            <a-card class="section-card side-card">
              <template #title><span class="section-title-link" @click="scrollToDesc('desc-slot')">線下（斯洛 / 鋼珠）</span></template>
              <table class="report-table vertical-table">
                <thead>
                  <tr>
                    <th style="width:80px">機種</th>
                    <th>項目</th>
                    <th style="width:160px">數值</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- 斯洛線下 -->
                  <tr>
                    <td class="row-label" rowspan="12">斯洛</td>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-up')">斯洛總上點</td>
                    <td class="val-income">{{ fmtIncome(reportData.slot.offline.up_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-down')">斯洛總下點</td>
                    <td class="val-expense">{{ fmtExpense(reportData.slot.offline.down_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-subtotal')">上下點小計</td>
                    <td :class="profitClass(reportData.slot.offline.subtotal)">{{ fmtNumSign(reportData.slot.offline.subtotal) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-lottery')">斯洛彩金</td>
                    <td class="val-expense">{{ fmtExpense(reportData.slot.offline.lottery) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-profit')">總盈虧</td>
                    <td :class="profitClass(reportData.slot.offline.total_profit)">{{ fmtNumSign(reportData.slot.offline.total_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-updown-rtp')">上下點RTP</td>
                    <td :class="['td-rtp', rtpClass(reportData.slot.offline.up_down_rtp)]">{{ fmtPct(reportData.slot.offline.up_down_rtp) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-pressure')">斯洛總押分</td>
                    <td>{{ fmtNum(reportData.slot.offline.pressure) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-score')">斯洛總得分</td>
                    <td>{{ fmtNum(reportData.slot.offline.score) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-press-profit')">押得總盈虧</td>
                    <td :class="profitClass(-reportData.slot.offline.press_score_profit)">{{ fmtNumSign(-reportData.slot.offline.press_score_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-press-rtp')">押得RTP</td>
                    <td :class="['td-rtp', rtpClass(reportData.slot.offline.press_score_rtp)]">{{ fmtPct(reportData.slot.offline.press_score_rtp) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-reverse-water')">返水<span class="note-small">含未領取</span></td>
                    <td class="val-expense">{{ fmtExpense(reportData.slot.offline.reverse_water_pool) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-children-cashback')">推廣紅利<span class="note-small">含未領取</span></td>
                    <td class="val-expense">{{ fmtExpense(reportData.slot.offline.children_cashback) }}</td>
                  </tr>
                  <!-- 鋼珠線下 -->
                  <tr class="section-divider">
                    <td class="row-label" rowspan="8">鋼珠</td>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-up')">鋼珠總上點</td>
                    <td class="val-income">{{ fmtIncome(reportData.steel_ball.offline.up_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-down')">鋼珠總下點</td>
                    <td class="val-expense">{{ fmtExpense(reportData.steel_ball.offline.down_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-subtotal')">上下點小計</td>
                    <td :class="profitClass(reportData.steel_ball.offline.subtotal)">{{ fmtNumSign(reportData.steel_ball.offline.subtotal) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-lottery')">鋼珠彩金</td>
                    <td class="val-expense">{{ fmtExpense(reportData.steel_ball.offline.lottery) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-profit')">鋼珠總盈虧</td>
                    <td :class="profitClass(reportData.steel_ball.offline.total_profit)">{{ fmtNumSign(reportData.steel_ball.offline.total_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-rtp')">RTP</td>
                    <td :class="['td-rtp', rtpClass(reportData.steel_ball.offline.rtp)]">{{ fmtPct(reportData.steel_ball.offline.rtp) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-reverse-water')">返水<span class="note-small">含未領取</span></td>
                    <td class="val-expense">{{ fmtExpense(reportData.steel_ball.offline.reverse_water_pool) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-children-cashback')">推廣紅利<span class="note-small">含未領取</span></td>
                    <td class="val-expense">{{ fmtExpense(reportData.steel_ball.offline.children_cashback) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>

            <!-- 5. 線上（斯洛 / 鋼珠） -->
            <a-card class="section-card side-card">
              <template #title><span class="section-title-link" @click="scrollToDesc('desc-slot')">線上（斯洛 / 鋼珠）</span></template>
              <table class="report-table vertical-table">
                <thead>
                  <tr>
                    <th style="width:80px">機種</th>
                    <th>項目</th>
                    <th style="width:160px">數值</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- 斯洛線上 -->
                  <tr>
                    <td class="row-label" rowspan="12">斯洛</td>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-up')">斯洛總上點</td>
                    <td class="val-income">{{ fmtIncome(reportData.slot.online.up_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-down')">斯洛總下點</td>
                    <td class="val-expense">{{ fmtExpense(reportData.slot.online.down_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-subtotal')">上下點小計</td>
                    <td :class="profitClass(reportData.slot.online.subtotal)">{{ fmtNumSign(reportData.slot.online.subtotal) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-lottery')">斯洛彩金</td>
                    <td class="val-expense">{{ fmtExpense(reportData.slot.online.lottery) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-profit')">總盈虧</td>
                    <td :class="profitClass(reportData.slot.online.total_profit)">{{ fmtNumSign(reportData.slot.online.total_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-updown-rtp')">上下點RTP</td>
                    <td :class="['td-rtp', rtpClass(reportData.slot.online.up_down_rtp)]">{{ fmtPct(reportData.slot.online.up_down_rtp) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-pressure')">斯洛總押分</td>
                    <td>{{ fmtNum(reportData.slot.online.pressure) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-score')">斯洛總得分</td>
                    <td>{{ fmtNum(reportData.slot.online.score) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-press-profit')">押得總盈虧</td>
                    <td :class="profitClass(-reportData.slot.online.press_score_profit)">{{ fmtNumSign(-reportData.slot.online.press_score_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-press-rtp')">押得RTP</td>
                    <td :class="['td-rtp', rtpClass(reportData.slot.online.press_score_rtp)]">{{ fmtPct(reportData.slot.online.press_score_rtp) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-reverse-water')">返水<span class="note-small">含未領取</span></td>
                    <td class="val-expense">{{ fmtExpense(reportData.slot.online.reverse_water_pool) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-slot-children-cashback')">推廣紅利<span class="note-small">含未領取</span></td>
                    <td class="val-expense">{{ fmtExpense(reportData.slot.online.children_cashback) }}</td>
                  </tr>
                  <!-- 鋼珠線上 -->
                  <tr class="section-divider">
                    <td class="row-label" rowspan="8">鋼珠</td>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-up')">鋼珠總上點</td>
                    <td class="val-income">{{ fmtIncome(reportData.steel_ball.online.up_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-down')">鋼珠總下點</td>
                    <td class="val-expense">{{ fmtExpense(reportData.steel_ball.online.down_point) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-subtotal')">上下點小計</td>
                    <td :class="profitClass(reportData.steel_ball.online.subtotal)">{{ fmtNumSign(reportData.steel_ball.online.subtotal) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-lottery')">鋼珠彩金</td>
                    <td class="val-expense">{{ fmtExpense(reportData.steel_ball.online.lottery) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-profit')">鋼珠總盈虧</td>
                    <td :class="profitClass(reportData.steel_ball.online.total_profit)">{{ fmtNumSign(reportData.steel_ball.online.total_profit) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-rtp')">RTP</td>
                    <td :class="['td-rtp', rtpClass(reportData.steel_ball.online.rtp)]">{{ fmtPct(reportData.steel_ball.online.rtp) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-reverse-water')">返水<span class="note-small">含未領取</span></td>
                    <td class="val-expense">{{ fmtExpense(reportData.steel_ball.online.reverse_water_pool) }}</td>
                  </tr>
                  <tr>
                    <td class="item-label th-link" @click="scrollToDesc('desc-steel-ball-children-cashback')">推廣紅利<span class="note-small">含未領取</span></td>
                    <td class="val-expense">{{ fmtExpense(reportData.steel_ball.online.children_cashback) }}</td>
                  </tr>
                </tbody>
              </table>
            </a-card>
            </div><!-- /side-by-side-cards -->
          </div><!-- /v-else-if reportData -->

          <!-- 空状态 -->
          <div v-else-if="detailSearched" style="text-align: center; padding: 60px;">
            <a-empty description="暫無數據" />
          </div>
          </div><!-- /finance-summary -->
          </div><!-- /tab2-result-wrap -->

          <!-- 右側：常駐說明面板 -->
          <div class="tab2-desc-panel">
            <div class="desc-panel-title">報表說明 <span style="font-size:10px;font-weight:400;color:#8c8c8c">（點擊欄位標題定位）</span></div>
            <div class="tab2-desc-content">

              <!-- 一、總覽 -->
              <div class="tab2-desc-block" id="desc-overview">
                <div class="tab2-desc-block-title">一、總覽</div>
                <div class="tab2-desc-text">彙總門店查詢期間錢包資金的流入與流出。</div>
              </div>
              <div class="desc-item" id="desc-overview-total-in" :class="{ 'desc-item-active': activeDescId === 'desc-overview-total-in' }">
                <div class="desc-item-name">總入</div>
                <div class="desc-item-body">= 投鈔(type=23) + 充值/開分(type=6)<br>
                  <b>投鈔</b>：玩家向實體機台投入現金，機台硬件讀取後由 gk_work 寫入錢包，資金進機台錢箱。<br>
                  <b>充值</b>：玩家透過 App 提交開分申請，核准後掃碼完成，錢包扣款並對應機台上點。<br>
                  <span class="desc-source">來源：player_delivery_record type IN (23, 6)</span>
                </div>
              </div>
              <div class="desc-item" id="desc-overview-total-out" :class="{ 'desc-item-active': activeDescId === 'desc-overview-total-out' }">
                <div class="desc-item-name">總出</div>
                <div class="desc-item-body">= 提現/洗分(type=7)<br>
                  玩家在 App 發起洗分申請，錢包扣款並生成洗分券，門店線下現金兌現。<br>
                  <span class="desc-source">來源：player_delivery_record type=7</span>
                </div>
              </div>
              <div class="desc-item" id="desc-overview-profit" :class="{ 'desc-item-active': activeDescId === 'desc-overview-profit' }">
                <div class="desc-item-name">利潤</div>
                <div class="desc-item-body">= 總入 - 總出<br>正值表示門店收入多於支出。</div>
              </div>
              <div class="desc-item" id="desc-overview-lottery" :class="{ 'desc-item-active': activeDescId === 'desc-overview-lottery' }">
                <div class="desc-item-name">總彩金</div>
                <div class="desc-item-body">= 電子彩金 + 鋼珠彩金 + 斯洛彩金<br>所有機台與電子遊戲的 Jackpot 彩金發放總額。<br>
                  <span class="desc-source">來源：player_lottery_record status=COMPLETE</span>
                </div>
              </div>
              <div class="desc-item" id="desc-overview-rtp" :class="{ 'desc-item-active': activeDescId === 'desc-overview-rtp' }">
                <div class="desc-item-name">RTP</div>
                <div class="desc-item-body">= 總出 ÷ 總入 × 100%<br>反映玩家對門店的資金回吐率，越高對玩家越有利。</div>
              </div>

              <a-divider style="margin:8px 0" />

              <!-- 二、贈點與福利明細 -->
              <div class="tab2-desc-block" id="desc-welfare">
                <div class="tab2-desc-block-title">二、贈點與福利明細</div>
                <div class="tab2-desc-text">平台主動向玩家錢包發放的福利點數，屬門店運營成本支出。</div>
              </div>
              <div class="desc-item" id="desc-welfare-vip-birthday" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-vip-birthday' }">
                <div class="desc-item-name">VIP生日禮</div>
                <div class="desc-item-body">gk_work Worker 每日掃描，當月為生日月且本年度未領取者自動發放，每年一次。<br>
                  <span class="desc-source">player_delivery_record type=34</span>
                </div>
              </div>
              <div class="desc-item" id="desc-welfare-vip-upgrade" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-vip-upgrade' }">
                <div class="desc-item-name">VIP升級禮</div>
                <div class="desc-item-body">玩家首次升至指定 VIP 等級時觸發，每等級限一次，降級後重升不重複發放。<br>
                  <span class="desc-source">player_delivery_record type=35</span>
                </div>
              </div>
              <div class="desc-item" id="desc-welfare-vip-daily" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-vip-daily' }">
                <div class="desc-item-name">VIP每日登陸獎</div>
                <div class="desc-item-body">玩家登入 App/H5 時自動觸發，每自然日限一次，依 VIP 等級決定點數。<br>
                  <span class="desc-source">player_delivery_record type=37</span>
                </div>
              </div>
              <div class="desc-item" id="desc-welfare-activity-give" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-activity-give' }">
                <div class="desc-item-name">總外贈點</div>
                <div class="desc-item-body">後台管理員手動批量或單點發放，不走標準活動審批流程，常用於臨時補償或推廣。<br>
                  <span class="desc-source">player_delivery_record type=38</span>
                </div>
              </div>
              <div class="desc-item" id="desc-welfare-experience" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-experience' }">
                <div class="desc-item-name">體驗券</div>
                <div class="desc-item-body">活動體驗券發放至玩家，玩家在機台使用後消耗，此處統計該門店發出的體驗券總分數。<br>
                  <span class="desc-source">ticket_record ticket_type=EXPERIENCE</span>
                </div>
              </div>
              <div class="desc-item" id="desc-welfare-welfare" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-welfare' }">
                <div class="desc-item-name">福利券</div>
                <div class="desc-item-body">活動福利券發放至玩家，玩家在機台使用後消耗，此處統計該門店發出的福利券總分數。<br>
                  <span class="desc-source">ticket_record ticket_type=WELFARE</span>
                </div>
              </div>
              <div class="desc-item" id="desc-welfare-lottery-ticket" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-lottery-ticket' }">
                <div class="desc-item-name">摸彩獎</div>
                <div class="desc-item-body">玩家使用摸獎券參與活動抽獎，命中獎項後即時發放至錢包。<br>
                  <span class="desc-source">player_delivery_record type=33</span>
                </div>
              </div>
              <div class="desc-item" id="desc-welfare-reverse-water" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-reverse-water' }">
                <div class="desc-item-name">返水</div>
                <div class="desc-item-body">玩家累積遊戲量達標後依 VIP 返水比例計算的回饋金額，取自各電子遊戲結算紀錄的 cashback_amount 加總。<br>
                  <span class="desc-source">play_game_record SUM(cashback_amount) settlement_status=SETTLED</span>
                </div>
              </div>
              <div class="desc-item" id="desc-welfare-children-cashback" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-children-cashback' }">
                <div class="desc-item-name">推廣紅利</div>
                <div class="desc-item-body">玩家下注時由系統計算並累積給其上級推廣人的紅利金額，取自各電子遊戲結算紀錄的 parent_cashback_amount 加總。<br>
                  <span class="desc-source">play_game_record SUM(parent_cashback_amount) settlement_status=SETTLED</span>
                </div>
              </div>
              <div class="desc-item" id="desc-welfare-subtotal" :class="{ 'desc-item-active': activeDescId === 'desc-welfare-subtotal' }">
                <div class="desc-item-name">小計</div>
                <div class="desc-item-body">= VIP生日禮 + VIP升級禮 + VIP每日登陸獎 + 總外贈點 + 體驗券 + 福利券 + 摸彩獎 + 返水 + 推廣紅利<br>代表門店當期所有福利成本合計。</div>
              </div>

              <a-divider style="margin:8px 0" />

              <!-- 三、電子遊戲 -->
              <div class="tab2-desc-block" id="desc-electronic">
                <div class="tab2-desc-block-title">三、電子遊戲</div>
                <div class="tab2-desc-text">統計線上電子遊戲平台已結算的投注與贏分。<br>
                  <span class="desc-source">play_game_record settlement_status=SETTLED</span>
                </div>
              </div>
              <div class="desc-item" id="desc-electronic-bet" :class="{ 'desc-item-active': activeDescId === 'desc-electronic-bet' }">
                <div class="desc-item-name">電子總押注</div>
                <div class="desc-item-body">= SUM(bet)<br>玩家在電子遊戲的全部押注金額（已結算局）。</div>
              </div>
              <div class="desc-item" id="desc-electronic-win" :class="{ 'desc-item-active': activeDescId === 'desc-electronic-win' }">
                <div class="desc-item-name">電子總贏分</div>
                <div class="desc-item-body">= SUM(win)<br>玩家在電子遊戲中贏得的總點數。</div>
              </div>
              <div class="desc-item" id="desc-electronic-diff" :class="{ 'desc-item-active': activeDescId === 'desc-electronic-diff' }">
                <div class="desc-item-name">玩家輸贏</div>
                <div class="desc-item-body">= SUM(diff) = 總贏分 - 總押注<br>正值 = 玩家盈利（平台虧損）<br>負值 = 玩家虧損（平台獲利）</div>
              </div>
              <div class="desc-item" id="desc-electronic-lottery" :class="{ 'desc-item-active': activeDescId === 'desc-electronic-lottery' }">
                <div class="desc-item-name">電子彩金</div>
                <div class="desc-item-body">電子遊戲 Jackpot 發放金額。<br>
                  <span class="desc-source">player_lottery_record source IN (GAME, MANUAL) status=COMPLETE</span>
                </div>
              </div>
              <div class="desc-item" id="desc-electronic-rtp" :class="{ 'desc-item-active': activeDescId === 'desc-electronic-rtp' }">
                <div class="desc-item-name">RTP</div>
                <div class="desc-item-body">= 總贏分 ÷ 總押注 × 100%<br>越高代表玩家回吐率越高。</div>
              </div>
              <div class="desc-item" id="desc-electronic-reverse-water" :class="{ 'desc-item-active': activeDescId === 'desc-electronic-reverse-water' }">
                <div class="desc-item-name">返水</div>
                <div class="desc-item-body">依 VIP 返水比例計算的回饋金額，與贈點福利明細中的返水為同一資料來源。<br>
                  <span class="desc-source">play_game_record SUM(cashback_amount) settlement_status=SETTLED</span>
                </div>
              </div>

              <a-divider style="margin:8px 0" />

              <!-- 四、鋼珠 -->
              <div class="tab2-desc-block" id="desc-steel-ball">
                <div class="tab2-desc-block-title">四、鋼珠（線下 / 線上）</div>
                <div class="tab2-desc-text">
                  <b>線下</b>：machine_category.machine_source=OFFLINE，玩家直接投幣操作實體機台，player_game_log.player_id=0，透過 channel_machine.store_admin_id 定位機台。<br>
                  <b>線上</b>：machine_category.machine_source=ONLINE，玩家透過 App 掃碼開分/洗分，player_game_log.player_id 有值。<br>
                  <span class="desc-source">player_game_log JOIN machine(type=STEEL_BALL) JOIN machine_category</span>
                </div>
              </div>
              <div class="desc-item" id="desc-steel-ball-up" :class="{ 'desc-item-active': activeDescId === 'desc-steel-ball-up' }">
                <div class="desc-item-name">鋼珠總上點</div>
                <div class="desc-item-body">= SUM(ABS(game_amount)) WHERE action=ACTION_OPEN<br>玩家充入（開分）機台的總點數，對應錢包扣款。</div>
              </div>
              <div class="desc-item" id="desc-steel-ball-down" :class="{ 'desc-item-active': activeDescId === 'desc-steel-ball-down' }">
                <div class="desc-item-name">鋼珠總下點</div>
                <div class="desc-item-body">= SUM(ABS(game_amount)) WHERE action≠ACTION_OPEN<br>玩家取出（洗分）機台的總點數，對應錢包加款。</div>
              </div>
              <div class="desc-item" id="desc-steel-ball-subtotal" :class="{ 'desc-item-active': activeDescId === 'desc-steel-ball-subtotal' }">
                <div class="desc-item-name">上下點小計</div>
                <div class="desc-item-body">= 總上點 - 總下點<br>正值 = 平台盈利，負值 = 平台虧損。</div>
              </div>
              <div class="desc-item" id="desc-steel-ball-lottery" :class="{ 'desc-item-active': activeDescId === 'desc-steel-ball-lottery' }">
                <div class="desc-item-name">鋼珠彩金</div>
                <div class="desc-item-body">鋼珠機台 Jackpot 發放金額。<br>
                  <span class="desc-source">player_lottery_record game_type=STEEL_BALL status=COMPLETE</span>
                </div>
              </div>
              <div class="desc-item" id="desc-steel-ball-profit" :class="{ 'desc-item-active': activeDescId === 'desc-steel-ball-profit' }">
                <div class="desc-item-name">鋼珠總盈虧</div>
                <div class="desc-item-body">= 上下點小計 - 鋼珠彩金<br>反映扣除彩金後門店的實際盈虧。</div>
              </div>
              <div class="desc-item" id="desc-steel-ball-rtp" :class="{ 'desc-item-active': activeDescId === 'desc-steel-ball-rtp' }">
                <div class="desc-item-name">RTP</div>
                <div class="desc-item-body">= 總下點 ÷ 總上點 × 100%<br>越高代表玩家回吐率越高。</div>
              </div>
              <div class="desc-item" id="desc-steel-ball-reverse-water" :class="{ 'desc-item-active': activeDescId === 'desc-steel-ball-reverse-water' }">
                <div class="desc-item-name">返水</div>
                <div class="desc-item-body">依 VIP 返水比例計算的回饋金額，按線上/線下機台分別統計。<br>
                  <span class="desc-source">player_game_log SUM(cashback_amount)</span>
                </div>
              </div>
              <div class="desc-item" id="desc-steel-ball-children-cashback" :class="{ 'desc-item-active': activeDescId === 'desc-steel-ball-children-cashback' }">
                <div class="desc-item-name">推廣紅利</div>
                <div class="desc-item-body">玩家下注時累積給其上級推廣人的紅利金額，按線上/線下機台分別統計。<br>
                  <span class="desc-source">player_game_log SUM(parent_cashback_amount)</span>
                </div>
              </div>

              <a-divider style="margin:8px 0" />

              <!-- 五、斯洛 -->
              <div class="tab2-desc-block" id="desc-slot">
                <div class="tab2-desc-block-title">五、斯洛（線下 / 線上）</div>
                <div class="tab2-desc-text">線下/線上定義同鋼珠（machine_source 區分）。<br>
                  <span class="desc-source">player_game_log JOIN machine(type=SLOT) JOIN machine_category</span>
                </div>
              </div>
              <div class="desc-item" id="desc-slot-up" :class="{ 'desc-item-active': activeDescId === 'desc-slot-up' }">
                <div class="desc-item-name">斯洛總上點</div>
                <div class="desc-item-body">= SUM(ABS(game_amount)) WHERE action=ACTION_OPEN<br>玩家充入（開分）機台的總點數。</div>
              </div>
              <div class="desc-item" id="desc-slot-down" :class="{ 'desc-item-active': activeDescId === 'desc-slot-down' }">
                <div class="desc-item-name">斯洛總下點</div>
                <div class="desc-item-body">= SUM(ABS(game_amount)) WHERE action≠ACTION_OPEN<br>玩家取出（洗分）機台的總點數。</div>
              </div>
              <div class="desc-item" id="desc-slot-subtotal" :class="{ 'desc-item-active': activeDescId === 'desc-slot-subtotal' }">
                <div class="desc-item-name">上下點小計</div>
                <div class="desc-item-body">= 總上點 - 總下點<br>正值 = 平台盈利，負值 = 平台虧損。</div>
              </div>
              <div class="desc-item" id="desc-slot-lottery" :class="{ 'desc-item-active': activeDescId === 'desc-slot-lottery' }">
                <div class="desc-item-name">斯洛彩金</div>
                <div class="desc-item-body">斯洛機台 Jackpot 發放金額。<br>
                  <span class="desc-source">player_lottery_record game_type=SLOT status=COMPLETE</span>
                </div>
              </div>
              <div class="desc-item" id="desc-slot-profit" :class="{ 'desc-item-active': activeDescId === 'desc-slot-profit' }">
                <div class="desc-item-name">總盈虧（上下點）</div>
                <div class="desc-item-body">= 上下點小計 - 斯洛彩金</div>
              </div>
              <div class="desc-item" id="desc-slot-updown-rtp" :class="{ 'desc-item-active': activeDescId === 'desc-slot-updown-rtp' }">
                <div class="desc-item-name">上下點RTP</div>
                <div class="desc-item-body">= 總下點 ÷ 總上點 × 100%</div>
              </div>
              <div class="desc-item" id="desc-slot-pressure" :class="{ 'desc-item-active': activeDescId === 'desc-slot-pressure' }">
                <div class="desc-item-name">斯洛總押分</div>
                <div class="desc-item-body">= SUM(pressure) from player_game_log<br>機台對玩家押入的分數（如免費遊戲、機台補貼分數等），由機台主動寫入。</div>
              </div>
              <div class="desc-item" id="desc-slot-score" :class="{ 'desc-item-active': activeDescId === 'desc-slot-score' }">
                <div class="desc-item-name">斯洛總得分</div>
                <div class="desc-item-body">= SUM(score) from player_game_log<br>玩家在押分機制下所贏得的分數，由機台回報寫入。</div>
              </div>
              <div class="desc-item" id="desc-slot-press-profit" :class="{ 'desc-item-active': activeDescId === 'desc-slot-press-profit' }">
                <div class="desc-item-name">押得總盈虧</div>
                <div class="desc-item-body">= 總得分 - 總押分<br>正值 = 玩家在押分機制中盈利（機台虧損）。</div>
              </div>
              <div class="desc-item" id="desc-slot-press-rtp" :class="{ 'desc-item-active': activeDescId === 'desc-slot-press-rtp' }">
                <div class="desc-item-name">押得RTP</div>
                <div class="desc-item-body">= 總得分 ÷ 總押分 × 100%</div>
              </div>
              <div class="desc-item" id="desc-slot-reverse-water" :class="{ 'desc-item-active': activeDescId === 'desc-slot-reverse-water' }">
                <div class="desc-item-name">返水</div>
                <div class="desc-item-body">依 VIP 返水比例計算的回饋金額，按線上/線下機台分別統計。<br>
                  <span class="desc-source">player_game_log SUM(cashback_amount)</span>
                </div>
              </div>
              <div class="desc-item" id="desc-slot-children-cashback" :class="{ 'desc-item-active': activeDescId === 'desc-slot-children-cashback' }">
                <div class="desc-item-name">推廣紅利</div>
                <div class="desc-item-body">玩家下注時累積給其上級推廣人的紅利金額，按線上/線下機台分別統計。<br>
                  <span class="desc-source">player_game_log SUM(parent_cashback_amount)</span>
                </div>
              </div>

            </div><!-- /tab2-desc-content -->
          </div><!-- /tab2-desc-panel -->

        </div><!-- /tab2-main -->
        </div><!-- /tab2-wrap -->
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
      tab1TimeRange: null,

      // ── Tab 2 state ──
      detailForm:      { store_id: null },
      detailDateType:  null,
      detailDateRange: [],
      detailLoading:   false,
      detailSearched:  false,
      reportData:      null,
      activeDescId:    null,
      reportMeta:      null,

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
    electronicDataRange() {
      const pad = n => String(n).padStart(2, '0');
      const now = new Date();
      const from = new Date(now.getFullYear(), now.getMonth() - 2, 1);
      return `${from.getFullYear()}-${pad(from.getMonth() + 1)} ~ ${now.getFullYear()}-${pad(now.getMonth() + 1)}`;
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
      this.loading      = true;
      this.searched     = true;
      this.error        = null;
      this.tab1TimeRange = this.calcShiftRange(this.form.dateType, this.form.dateRange
        ? [this.form.dateRange[0]?.format('YYYY-MM-DD HH:mm:ss'), this.form.dateRange[1]?.format('YYYY-MM-DD HH:mm:ss')]
        : []);

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
      this.form          = { agentId: null, storeIds: [], dateType: null, dateRange: null };
      this.searched      = false;
      this.panels        = [];
      this.error         = null;
      this.activeTypeId  = null;
      this.tab1TimeRange = null;
    },

    // ── Tab 2 methods ──

    onDetailDateTypeChange(val) {
      if (val) this.detailDateRange = [];
    },
    scrollToDesc(id) {
      this.activeDescId = id;
      this.$nextTick(() => {
        const el = document.getElementById(id);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
    },
    calcShiftRange(dateType, dateRange) {
      const pad  = n => String(n).padStart(2, '0');
      const fmt  = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
      const s8   = d => `${fmt(d)} 08:00`;
      const add  = (d, n) => { const r = new Date(d); r.setDate(r.getDate() + n); return r; };
      const addM = (d, n) => new Date(d.getFullYear(), d.getMonth() + n, 1);
      const mon  = d => { const dow = d.getDay() === 0 ? 6 : d.getDay()-1; return add(d, -dow); };

      if (dateType) {
        const label = (this.trans.date_types || []).find(t => t.value === Number(dateType));
        const lbl   = label ? label.label : '';
        const now   = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        // 業務日：08:00 前算前一天（與 getDateWhere8am 一致）
        const bd    = now.getHours() >= 8 ? today : add(today, -1);
        let start, end;
        switch (Number(dateType)) {
          case 1: start = bd;            end = add(bd, 1);   break; // 今日
          case 2: start = add(bd, -1);   end = bd;           break; // 昨日
          case 3: start = mon(today);    end = add(mon(today), 7);  break; // 本週（周一 08:00 起）
          case 4: { const tm = mon(today); start = add(tm, -7); end = tm; break; } // 上週
          case 5: start = new Date(today.getFullYear(), today.getMonth(), 1); end = addM(today, 1); break; // 本月
          case 6: start = addM(today, -1); end = new Date(today.getFullYear(), today.getMonth(), 1); break; // 上月
          default: return lbl;
        }
        return `${lbl}　${s8(start)} ~ ${s8(end)}`;
      }
      if (dateRange && dateRange[0]) {
        return dateRange[1] ? `${dateRange[0]} ~ ${dateRange[1]}` : dateRange[0];
      }
      return '全部時間';
    },
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
    fmtIncome(val) {
      const n = Number(val) || 0;
      if (n === 0) return '0.00';
      return '+' + n.toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    fmtExpense(val) {
      const n = Number(val) || 0;
      if (n === 0) return '0.00';
      return '-' + n.toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    fmtNumSign(val) {
      const n = Number(val) || 0;
      if (n === 0) return '0.00';
      const abs = Math.abs(n).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      return (n > 0 ? '+' : '-') + abs;
    },
    profitClass(val) {
      const n = Number(val);
      if (n > 0) return 'profit-positive';
      if (n < 0) return 'profit-negative';
      return '';
    },
    profitClassInv(val) {
      const n = Number(val);
      if (n > 0) return 'profit-negative';
      if (n < 0) return 'profit-positive';
      return '';
    },
    rtpClass(val) {
      const n = Number(val) || 0;
      if (n === 0) return '';
      return n < 100 ? 'profit-positive' : 'profit-negative';
    },
    detailDisabledDate(current) {
      if (!current) return false;
      const cutoff = new Date();
      cutoff.setDate(1);
      cutoff.setMonth(cutoff.getMonth() - 2);
      cutoff.setHours(0, 0, 0, 0);
      return current.valueOf() < cutoff.getTime();
    },
    handleDetailSearch() {
      if (!this.detailForm.store_id) {
        this.$message.warning('請先選擇門店');
        return;
      }
      if (!this.detailDateType && (!this.detailDateRange || !this.detailDateRange[0])) {
        this.$message.warning('請選擇時間類型或查詢時間範圍');
        return;
      }

      this.detailLoading = true;
      this.reportData    = null;
      this.reportMeta    = {
        storeName: this.store_options[this.detailForm.store_id] || `門店 #${this.detailForm.store_id}`,
        timeRange: this.calcShiftRange(this.detailDateType, this.detailDateRange),
      };

      const payload = {
        store_id:   this.detailForm.store_id,
        date_type:  this.detailDateType || '',
        start_date: this.detailDateType ? '' : (this.detailDateRange && this.detailDateRange[0] ? this.detailDateRange[0] : ''),
        end_date:   this.detailDateType ? '' : (this.detailDateRange && this.detailDateRange[1] ? this.detailDateRange[1] : ''),
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

/* ── Tab 2 包裹层 ── */
.tab2-wrap         { height: calc(100vh - 160px); overflow: hidden; }
.tab2-main         { display: flex; gap: 12px; height: 100%; }
.tab2-result-wrap  { flex: 1; min-width: 0; overflow-y: auto; overflow-x: hidden; }

/* ── Tab 2 並排卡片（鋼珠 / 斯洛） ── */
.side-by-side-cards { display: flex; gap: 12px; margin-bottom: 16px; align-items: flex-start; }
.side-card          { flex: 1; min-width: 0; }

/* ── Tab 2 說明面板 ── */
.tab2-desc-panel     { width: 240px; flex-shrink: 0; display: flex; flex-direction: column; background: #fff; border: 1px solid #f0f0f0; border-radius: 8px; overflow: hidden; }
.tab2-desc-content   { flex: 1; overflow-y: auto; padding: 12px; }
.tab2-desc-block     { margin-bottom: 4px; }
.tab2-desc-block-title { font-size: 12px; font-weight: 700; color: #1677ff; margin-bottom: 6px; }
.tab2-desc-text      { font-size: 11px; color: #595959; line-height: 1.65; }

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

/* ── Tab 1 查詢時段列 ── */
.tab1-time-bar   { display: flex; align-items: center; gap: 8px; padding: 7px 12px; margin-bottom: 10px; background: #f0f5ff; border: 1px solid #adc6ff; border-radius: 8px; flex-wrap: wrap; }
.tab1-time-label { font-size: 12px; color: #8c8c8c; }
.tab1-time-range { font-size: 13px; color: #1d39c4; font-weight: 600; }

/* ── Tab 2 報表頭部 ── */
.report-header-bar        { display: flex; align-items: center; gap: 8px; padding: 10px 16px; margin-bottom: 12px; background: #f0f5ff; border: 1px solid #adc6ff; border-radius: 8px; flex-wrap: wrap; }
.report-header-store      { font-size: 15px; font-weight: 700; color: #1d39c4; }
.report-header-sep        { color: #adb5bd; }
.report-header-time       { font-size: 13px; color: #262626; }
.report-header-shift-badge { margin-left: 4px; padding: 1px 8px; background: #fff7e6; border: 1px solid #ffd591; border-radius: 10px; font-size: 11px; color: #d46b08; font-weight: 600; }

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

/* ── Tab 2 income / expense colors ── */
.val-income  { color: #389e0d; font-weight: 600; }
.val-expense { color: #cf1322; font-weight: 600; }
.note-small  { display: block; font-size: 10px; font-weight: 400; color: #8c8c8c; line-height: 1.3; }

/* ── 玩家輸贏：方向與平台視角相反，特殊標記 ── */
.th-player-diff { background: #fff7e6 !important; border-left: 2px solid #ffa940 !important; border-right: 2px solid #ffa940 !important; }
.td-player-diff { background: #fff7e6; border-left: 2px solid #ffa940 !important; border-right: 2px solid #ffa940 !important; font-style: italic; }

/* ── RTP 特殊標記 ── */
.th-rtp { background: #e6fffb !important; border-left: 2px solid #36cfc9 !important; border-right: 2px solid #36cfc9 !important; color: #006d75 !important; }
.td-rtp { background: #e6fffb; border-left: 2px solid #36cfc9 !important; border-right: 2px solid #36cfc9 !important; font-weight: 600; }

/* ── Tab 2 vertical table (钢珠/斯洛) ── */
.vertical-table { width: 100%; }
.vertical-table .item-label { text-align: left; color: #595959; padding-left: 16px; }
.vertical-table .section-divider td { border-top: 2px solid #d9d9d9; }

/* ── Tab 2 clickable headers / labels ── */
.th-link          { cursor: pointer; user-select: none; }
.th-link:hover    { color: #1677ff; background: #e6f4ff !important; }
.section-title-link         { cursor: pointer; }
.section-title-link:hover   { color: #0958d9; }

/* ── Tab 2 desc-item (说明面板细项) ── */
.desc-item             { padding: 5px 6px 5px 8px; margin-bottom: 3px; border-radius: 4px; border-left: 2px solid transparent; transition: background .2s, border-color .2s; scroll-margin-top: 4px; }
.desc-item-name        { font-size: 11px; font-weight: 700; color: #262626; margin-bottom: 2px; }
.desc-item-body        { font-size: 10px; color: #595959; line-height: 1.6; }
.desc-item-active      { background: #e6f4ff; border-left-color: #1677ff; }
.desc-source           { display: inline-block; margin-top: 3px; font-size: 10px; color: #8c8c8c; font-style: italic; }
</style>
