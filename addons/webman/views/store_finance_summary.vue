<template>
  <div class="finance-summary">
    <!-- 筛选区域 -->
    <a-card :body-style="{ padding: '16px' }" style="margin-bottom: 16px;">
      <a-space wrap>
        <a-select
            v-model:value="form.store_id"
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
            v-model:value="dateRange"
            :show-time="{ format: 'HH:mm:ss' }"
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 420px;"
            placeholder="['開始時間', '結束時間']"
        />

        <a-button type="primary" :loading="loading" @click="handleSearch">查詢</a-button>
      </a-space>
    </a-card>

    <!-- 加载状态 -->
    <div v-if="loading" style="text-align: center; padding: 60px;">
      <a-spin size="large" />
    </div>

    <!-- 报表内容 -->
    <template v-else-if="reportData">
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
              <td>{{ fmt(reportData.overview.total_in) }}</td>
              <td>{{ fmt(reportData.overview.total_out) }}</td>
              <td :class="profitClass(reportData.overview.profit)">{{ fmt(reportData.overview.profit) }}</td>
              <td>{{ fmt(reportData.overview.total_lottery) }}</td>
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
              <td>{{ fmt(reportData.welfare.vip_birthday) }}</td>
              <td>{{ fmt(reportData.welfare.vip_upgrade) }}</td>
              <td>{{ fmt(reportData.welfare.vip_daily_login) }}</td>
              <td>{{ fmt(reportData.welfare.activity_give) }}</td>
              <td>{{ fmt(reportData.welfare.experience_coupon) }}</td>
              <td>{{ fmt(reportData.welfare.welfare_coupon) }}</td>
              <td>{{ fmt(reportData.welfare.lottery_ticket) }}</td>
              <td class="subtotal-col">{{ fmt(reportData.welfare.subtotal) }}</td>
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
              <td>{{ fmt(reportData.electronic.total_bet) }}</td>
              <td>{{ fmt(reportData.electronic.total_win) }}</td>
              <td :class="profitClass(reportData.electronic.player_diff)">{{ fmt(reportData.electronic.player_diff) }}</td>
              <td>{{ fmt(reportData.electronic.lottery) }}</td>
              <td>{{ fmtPct(reportData.electronic.rtp) }}</td>
            </tr>
          </tbody>
        </table>
      </a-card>

      <!-- 4. 鋼珠 -->
      <a-card title="鋼珠" class="section-card">
        <table class="report-table">
          <thead>
            <tr>
              <th></th>
              <th>上點</th>
              <th>下點</th>
              <th>小計</th>
              <th>彩金</th>
              <th>總盈虧</th>
              <th>RTP</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="row-label">線下</td>
              <td>{{ fmt(reportData.steel_ball.offline.up_point) }}</td>
              <td>{{ fmt(reportData.steel_ball.offline.down_point) }}</td>
              <td>{{ fmt(reportData.steel_ball.offline.subtotal) }}</td>
              <td>{{ fmt(reportData.steel_ball.offline.lottery) }}</td>
              <td :class="profitClass(reportData.steel_ball.offline.total_profit)">{{ fmt(reportData.steel_ball.offline.total_profit) }}</td>
              <td>{{ fmtPct(reportData.steel_ball.offline.rtp) }}</td>
            </tr>
            <tr>
              <td class="row-label">線上</td>
              <td>{{ fmt(reportData.steel_ball.online.up_point) }}</td>
              <td>{{ fmt(reportData.steel_ball.online.down_point) }}</td>
              <td>{{ fmt(reportData.steel_ball.online.subtotal) }}</td>
              <td>{{ fmt(reportData.steel_ball.online.lottery) }}</td>
              <td :class="profitClass(reportData.steel_ball.online.total_profit)">{{ fmt(reportData.steel_ball.online.total_profit) }}</td>
              <td>{{ fmtPct(reportData.steel_ball.online.rtp) }}</td>
            </tr>
          </tbody>
        </table>
      </a-card>

      <!-- 5. 斯洛 -->
      <a-card title="斯洛" class="section-card">
        <table class="report-table">
          <thead>
            <tr>
              <th></th>
              <th>上點</th>
              <th>下點</th>
              <th>小計</th>
              <th>彩金</th>
              <th>總盈虧</th>
              <th>上下點RTP</th>
              <th>押分</th>
              <th>得分</th>
              <th>押得總盈虧</th>
              <th>押得RTP</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="row-label">線下</td>
              <td>{{ fmt(reportData.slot.offline.up_point) }}</td>
              <td>{{ fmt(reportData.slot.offline.down_point) }}</td>
              <td>{{ fmt(reportData.slot.offline.subtotal) }}</td>
              <td>{{ fmt(reportData.slot.offline.lottery) }}</td>
              <td :class="profitClass(reportData.slot.offline.total_profit)">{{ fmt(reportData.slot.offline.total_profit) }}</td>
              <td>{{ fmtPct(reportData.slot.offline.up_down_rtp) }}</td>
              <td>{{ fmt(reportData.slot.offline.pressure) }}</td>
              <td>{{ fmt(reportData.slot.offline.score) }}</td>
              <td :class="profitClass(reportData.slot.offline.press_score_profit)">{{ fmt(reportData.slot.offline.press_score_profit) }}</td>
              <td>{{ fmtPct(reportData.slot.offline.press_score_rtp) }}</td>
            </tr>
            <tr>
              <td class="row-label">線上</td>
              <td>{{ fmt(reportData.slot.online.up_point) }}</td>
              <td>{{ fmt(reportData.slot.online.down_point) }}</td>
              <td>{{ fmt(reportData.slot.online.subtotal) }}</td>
              <td>{{ fmt(reportData.slot.online.lottery) }}</td>
              <td :class="profitClass(reportData.slot.online.total_profit)">{{ fmt(reportData.slot.online.total_profit) }}</td>
              <td>{{ fmtPct(reportData.slot.online.up_down_rtp) }}</td>
              <td>{{ fmt(reportData.slot.online.pressure) }}</td>
              <td>{{ fmt(reportData.slot.online.score) }}</td>
              <td :class="profitClass(reportData.slot.online.press_score_profit)">{{ fmt(reportData.slot.online.press_score_profit) }}</td>
              <td>{{ fmtPct(reportData.slot.online.press_score_rtp) }}</td>
            </tr>
          </tbody>
        </table>
      </a-card>
    </template>

    <!-- 空状态 -->
    <a-empty v-else-if="searched" description="暫無數據" />
  </div>
</template>

<script>
export default {
  name: "store_finance_summary",
  props: {
    store_options: {
      type: Object,
      default: () => ({})
    },
    search_url: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      form: {
        store_id: null,
      },
      dateRange: [],
      loading: false,
      reportData: null,
      searched: false,
    };
  },
  methods: {
    filterOption(input, option) {
      return option.children[0].children.toLowerCase().includes(input.toLowerCase());
    },

    fmt(val) {
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

    handleSearch() {
      if (!this.form.store_id) {
        this.$message.warning('請先選擇門店');
        return;
      }

      this.loading = true;
      this.reportData = null;

      const payload = {
        store_id: this.form.store_id,
        start_date: this.dateRange && this.dateRange[0] ? this.dateRange[0] : '',
        end_date: this.dateRange && this.dateRange[1] ? this.dateRange[1] : '',
      };

      this.$request({
        url: this.search_url,
        method: 'post',
        data: payload,
      }).then(res => {
        this.loading = false;
        this.searched = true;
        if (res.code === 200) {
          this.reportData = res.data;
        } else {
          this.$message.error(res.msg || '查詢失敗');
        }
      }).catch(() => {
        this.loading = false;
        this.searched = true;
        this.$message.error('查詢失敗，請稍後重試');
      });
    },
  },
};
</script>

<style scoped>
.finance-summary {
  padding: 16px;
}

.section-card {
  margin-bottom: 16px;
}

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

.profit-positive {
  color: #389e0d;
  font-weight: 600;
}

.profit-negative {
  color: #cf1322;
  font-weight: 600;
}
</style>
