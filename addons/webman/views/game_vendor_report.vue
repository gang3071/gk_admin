<template>
  <div style="padding: 16px;">
    <!-- 查询条件 -->
    <a-card size="small" style="margin-bottom: 16px;">
      <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span style="white-space: nowrap;">{{ labels.start_date || '開始日期' }}</span>
          <a-date-picker
            v-model:value="startDate"
            value-format="YYYY-MM-DD"
            :placeholder="labels.start_date || '開始日期'"
            style="width: 140px;"
          />
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <span style="white-space: nowrap;">{{ labels.end_date || '結束日期' }}</span>
          <a-date-picker
            v-model:value="endDate"
            value-format="YYYY-MM-DD"
            :placeholder="labels.end_date || '結束日期'"
            style="width: 140px;"
          />
        </div>
        <a-button type="primary" :loading="loading" @click="fetchData">
          {{ labels.search || '查詢' }}
        </a-button>
        <a-button @click="resetFilter">{{ labels.reset || '重置' }}</a-button>
      </div>
    </a-card>

    <!-- 数据表格 -->
    <a-table
      :columns="columns"
      :data-source="categories"
      :loading="loading"
      :pagination="false"
      row-key="cate_id"
      :expandedRowKeys="expandedKeys"
      @expand="handleExpand"
      size="middle"
      bordered
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'player_win_loss'">
          <span :style="winLossStyle(record.player_win_loss)">
            {{ formatNumber(record.player_win_loss) }}
          </span>
        </template>
        <template v-else-if="column.dataIndex === 'name'">
          <strong v-if="record.is_category">{{ record.name }}</strong>
          <span v-else style="padding-left: 16px;">{{ record.name }}</span>
        </template>
        <template v-else-if="column.dataIndex === 'valid_bet'">
          {{ formatNumber(record.valid_bet) }}
        </template>
        <template v-else-if="column.dataIndex === 'total_bet'">
          {{ formatNumber(record.total_bet) }}
        </template>
        <template v-else-if="column.dataIndex === 'gift_amount'">
          {{ formatNumber(record.gift_amount) }}
        </template>
      </template>

      <template #expandedRowRender="{ record }">
        <a-table
          :columns="childColumns"
          :data-source="record.platforms"
          :pagination="false"
          size="small"
          :show-header="false"
          row-key="platform_id"
        >
          <template #bodyCell="{ column, record: childRecord }">
            <template v-if="column.dataIndex === 'player_win_loss'">
              <span :style="winLossStyle(childRecord.player_win_loss)">
                {{ formatNumber(childRecord.player_win_loss) }}
              </span>
            </template>
            <template v-else-if="column.dataIndex === 'valid_bet'">
              {{ formatNumber(childRecord.valid_bet) }}
            </template>
            <template v-else-if="column.dataIndex === 'total_bet'">
              {{ formatNumber(childRecord.total_bet) }}
            </template>
            <template v-else-if="column.dataIndex === 'gift_amount'">
              {{ formatNumber(childRecord.gift_amount) }}
            </template>
          </template>
        </a-table>
      </template>
    </a-table>
  </div>
</template>

<script>
export default {
  props: {
    api_url: { type: String, required: true },
    labels: { type: Object, default: () => ({}) },
  },

  data() {
    const today = new Date().toISOString().slice(0, 10);
    return {
      startDate: today,
      endDate: today,
      loading: false,
      categories: [],
      expandedKeys: [],
    };
  },

  computed: {
    columns() {
      return [
        { title: this.labels.vendor_name || '廠商名稱', dataIndex: 'name', key: 'name', width: 180 },
        { title: this.labels.valid_bet || '有效投注', dataIndex: 'valid_bet', key: 'valid_bet', align: 'right', width: 140 },
        { title: this.labels.total_bet || '投注金額', dataIndex: 'total_bet', key: 'total_bet', align: 'right', width: 140 },
        { title: this.labels.player_win_loss || '玩家輸贏', dataIndex: 'player_win_loss', key: 'player_win_loss', align: 'right', width: 140 },
        { title: this.labels.gift_amount || '打賞總額', dataIndex: 'gift_amount', key: 'gift_amount', align: 'right', width: 130 },
        { title: this.labels.gift_count || '打賞筆數', dataIndex: 'gift_count', key: 'gift_count', align: 'right', width: 100 },
      ];
    },
    childColumns() {
      return this.columns.map(c => ({ ...c, title: '' }));
    },
  },

  mounted() {
    this.fetchData();
  },

  methods: {
    async fetchData() {
      this.loading = true;
      try {
        const res = await this.$request({
          url: this.api_url,
          method: 'get',
          params: {
            start_date: this.startDate || '',
            end_date: this.endDate || '',
          },
        });
        if (res.code === 200) {
          this.categories = res.data || [];
          this.expandedKeys = this.categories.map(c => c.cate_id);
        } else {
          this.$message.error(res.message || '查詢失敗');
        }
      } catch (e) {
        this.$message.error('請求失敗');
      } finally {
        this.loading = false;
      }
    },

    resetFilter() {
      const today = new Date().toISOString().slice(0, 10);
      this.startDate = today;
      this.endDate = today;
      this.fetchData();
    },

    handleExpand(expanded, record) {
      if (expanded) {
        this.expandedKeys = [...this.expandedKeys, record.cate_id];
      } else {
        this.expandedKeys = this.expandedKeys.filter(k => k !== record.cate_id);
      }
    },

    formatNumber(val) {
      if (val === null || val === undefined) return '0';
      const num = parseFloat(val);
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
