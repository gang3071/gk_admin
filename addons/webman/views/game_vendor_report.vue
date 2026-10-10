<template>
  <div style="padding: 16px;">
    <!-- 查询条件 -->
    <a-card size="small" style="margin-bottom: 16px;">
      <a-space>
        <a-range-picker
          v-model:value="dateRange"
          value-format="YYYY-MM-DD"
          :allow-clear="false"
          style="width: 230px;"
        />
        <a-button type="primary" :loading="loading" @click="fetchData">
          {{ labels.search || '查詢' }}
        </a-button>
        <a-button @click="resetFilter">{{ labels.reset || '重置' }}</a-button>
      </a-space>
    </a-card>

    <!-- 数据表格 -->
    <a-table
      :columns="columns"
      :data-source="flatRows"
      :loading="loading"
      :pagination="false"
      row-key="key"
      :custom-row="rowAttrs"
      size="middle"
      bordered
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'name'">
          <strong v-if="record.is_category">{{ record.name }}</strong>
          <span v-else style="padding-left: 20px; color: #555;">{{ record.name }}</span>
        </template>
        <template v-else-if="column.dataIndex === 'player_win_loss'">
          <span :style="winLossStyle(record.player_win_loss)">
            {{ formatNum(record.player_win_loss) }}
          </span>
        </template>
        <template v-else>
          {{ formatNum(record[column.dataIndex]) }}
        </template>
      </template>
    </a-table>
  </div>
</template>

<script>
const CATE_NAMES = {
  1:  '實體機台',
  2:  '電子',
  3:  '真人視訊',
  4:  '捕魚',
  5:  '牌桌',
  6:  '棋牌',
  7:  '老虎機',
  8:  '街機',
  9:  '體育',
  10: '彩票',
};

const CATE_ORDER = [2, 3, 4, 5, 6, 7, 8, 9, 10, 1];

export default {
  props: {
    api_url: { type: String, required: true },
    labels: { type: Object, default: () => ({}) },
  },

  data() {
    const today = new Date().toISOString().slice(0, 10);
    return {
      dateRange: [today, today],
      loading: false,
      rawCategories: [],
    };
  },

  computed: {
    columns() {
      return [
        { title: this.labels.vendor_name || '廠商名稱',    dataIndex: 'name',            key: 'name',            width: 200 },
        { title: this.labels.valid_bet    || '有效投注',    dataIndex: 'valid_bet',       key: 'valid_bet',       align: 'right', width: 140 },
        { title: this.labels.total_bet    || '投注金額',    dataIndex: 'total_bet',       key: 'total_bet',       align: 'right', width: 140 },
        { title: this.labels.player_win_loss || '玩家輸贏', dataIndex: 'player_win_loss', key: 'player_win_loss', align: 'right', width: 140 },
        { title: this.labels.gift_amount  || '打賞總額',    dataIndex: 'gift_amount',     key: 'gift_amount',     align: 'right', width: 130 },
        { title: this.labels.gift_count   || '打賞筆數',    dataIndex: 'gift_count',      key: 'gift_count',      align: 'right', width: 100 },
      ];
    },

    flatRows() {
      const cateMap = {};
      this.rawCategories.forEach(c => { cateMap[c.cate_id] = c; });

      const rows = [];
      CATE_ORDER.forEach(cateId => {
        const cate = cateMap[cateId];
        if (!cate) return;
        rows.push({
          key:         `cate_${cateId}`,
          is_category: true,
          name:        CATE_NAMES[cateId] || cate.name,
          valid_bet:       cate.valid_bet,
          total_bet:       cate.total_bet,
          player_win_loss: cate.player_win_loss,
          gift_amount:     cate.gift_amount,
          gift_count:      cate.gift_count,
        });
        (cate.platforms || []).forEach(p => {
          rows.push({
            key:         `platform_${p.platform_id}`,
            is_category: false,
            name:        p.name,
            valid_bet:       p.valid_bet,
            total_bet:       p.total_bet,
            player_win_loss: p.player_win_loss,
            gift_amount:     p.gift_amount,
            gift_count:      p.gift_count,
          });
        });
      });
      return rows;
    },
  },

  mounted() {
    this.fetchData();
  },

  methods: {
    async fetchData() {
      this.loading = true;
      try {
        const [startDate, endDate] = this.dateRange || [];
        const res = await this.$request({
          url:    this.api_url,
          method: 'get',
          params: { start_date: startDate || '', end_date: endDate || '' },
        });
        if (res.code === 200) {
          this.rawCategories = res.data || [];
        } else {
          this.$message.error(res.message || '查詢失敗');
        }
      } catch {
        this.$message.error('請求失敗');
      } finally {
        this.loading = false;
      }
    },

    resetFilter() {
      const today = new Date().toISOString().slice(0, 10);
      this.dateRange = [today, today];
      this.fetchData();
    },

    rowAttrs(record) {
      if (record.is_category) {
        return { style: { background: '#f0f5ff', fontWeight: 'bold' } };
      }
      return {};
    },

    formatNum(val) {
      const num = parseFloat(val) || 0;
      return num.toLocaleString('zh-TW', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    },

    winLossStyle(val) {
      const num = parseFloat(val);
      if (num > 0) return { color: '#52c41a', fontWeight: 'bold' };
      if (num < 0) return { color: '#ff4d4f', fontWeight: 'bold' };
      return {};
    },
  },
};
</script>
