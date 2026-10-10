<template>
  <div>
    <!-- 工具栏 -->
    <div class="ex-tools">
      <div class="ex-tools-left">
        <span class="ex-page-title">{{ page_title }}</span>
      </div>
      <div class="ex-tools-right">
        <a-breadcrumb v-if="breadcrumbs && breadcrumbs.length">
          <a-breadcrumb-item v-for="(item, i) in breadcrumbs" :key="i">{{ item }}</a-breadcrumb-item>
        </a-breadcrumb>
        <a-divider type="vertical" style="margin: 0 12px;" />
        <a-tooltip :title="labels.search || '查詢'">
          <search-outlined class="toolbar-icon" @click="fetchData" />
        </a-tooltip>
        <a-tooltip :title="labels.reset || '重置'" style="margin-left: 16px;">
          <reload-outlined class="toolbar-icon" @click="resetFilter" />
        </a-tooltip>
      </div>
    </div>

    <!-- 筛选区 -->
    <div class="ex-filter">
      <!-- 快捷日期（移入搜索区） -->
      <div class="filter-quick-row">
        <span class="filter-quick-label">快捷日期</span>
        <a-radio-group v-model:value="quickDate" button-style="solid" size="small" @change="onQuickDate">
          <a-radio-button value="today">今日</a-radio-button>
          <a-radio-button value="yesterday">昨日</a-radio-button>
          <a-radio-button value="week">本週</a-radio-button>
          <a-radio-button value="month">本月</a-radio-button>
          <a-radio-button value="last_month">上月</a-radio-button>
        </a-radio-group>
      </div>

      <!-- 多列网格表单 -->
      <div class="filter-grid">
        <div class="filter-item">
          <label class="filter-label">{{ labels.start_date || '開始日期' }} ～ {{ labels.end_date || '結束日期' }}</label>
          <a-range-picker
            v-model:value="dateRange"
            value-format="YYYY-MM-DD"
            :allow-clear="false"
            class="filter-control"
            @change="onDateRangeChange"
          />
        </div>

        <div class="filter-item">
          <label class="filter-label">廠商平台</label>
          <a-select
            v-model:value="selectedPlatformIds"
            mode="multiple"
            :placeholder="labels.platform || '全部平台'"
            class="filter-control"
            allow-clear
            :max-tag-count="2"
          >
            <a-select-option v-for="p in platforms" :key="p.id" :value="p.id">
              {{ p.name }}
            </a-select-option>
          </a-select>
        </div>

        <div class="filter-item">
          <label class="filter-label">遊戲分類</label>
          <a-select
            v-model:value="selectedCateIds"
            mode="multiple"
            placeholder="全部分類"
            class="filter-control"
            allow-clear
            :max-tag-count="2"
          >
            <a-select-option v-for="opt in cateOptions" :key="opt.id" :value="opt.id">
              {{ opt.name }}
            </a-select-option>
          </a-select>
        </div>
      </div>

      <!-- 操作按钮（移至表单下方，带图标） -->
      <div class="filter-actions">
        <a-button type="primary" :loading="loading" @click="fetchData">
          <template #icon><search-outlined /></template>
          {{ labels.search || '查詢' }}
        </a-button>
        <a-button @click="resetFilter">
          <template #icon><reload-outlined /></template>
          {{ labels.reset || '重置' }}
        </a-button>
      </div>
    </div>

    <!-- 数据表格 -->
    <a-table
      :columns="columns"
      :data-source="flatRows"
      :loading="loading"
      :pagination="false"
      row-key="key"
      :custom-row="rowAttrs"
      size="middle"
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

function getDateRange(type) {
  const today = new Date();
  const fmt = d => d.toISOString().slice(0, 10);

  if (type === 'today') {
    const s = fmt(today); return [s, s];
  }
  if (type === 'yesterday') {
    const d = new Date(today); d.setDate(d.getDate() - 1); const s = fmt(d); return [s, s];
  }
  if (type === 'week') {
    const d = new Date(today); const day = d.getDay() || 7; d.setDate(d.getDate() - day + 1);
    return [fmt(d), fmt(today)];
  }
  if (type === 'month') {
    return [fmt(today).slice(0, 8) + '01', fmt(today)];
  }
  if (type === 'last_month') {
    const d = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const last = new Date(today.getFullYear(), today.getMonth(), 0);
    return [fmt(d), fmt(last)];
  }
  return [fmt(today).slice(0, 8) + '01', fmt(today)];
}

export default {
  props: {
    api_url:     { type: String, required: true },
    labels:      { type: Object, default: () => ({}) },
    platforms:   { type: Array,  default: () => [] },
    page_title:  { type: String, default: '' },
    breadcrumbs: { type: Array,  default: () => [] },
  },

  data() {
    return {
      dateRange:           getDateRange('month'),
      quickDate:           'month',
      selectedPlatformIds: [],
      selectedCateIds:     [],
      loading:             false,
      rawPlatforms:        [],
    };
  },

  computed: {
    cateOptions() {
      return CATE_ORDER.map(id => ({ id, name: CATE_NAMES[id] }));
    },

    columns() {
      return [
        { title: this.labels.vendor_name     || '廠商名稱', dataIndex: 'name',            key: 'name',            width: 200 },
        { title: this.labels.valid_bet       || '有效投注', dataIndex: 'valid_bet',       key: 'valid_bet',       align: 'right', width: 140 },
        { title: this.labels.total_bet       || '投注金額', dataIndex: 'total_bet',       key: 'total_bet',       align: 'right', width: 140 },
        { title: this.labels.player_win_loss || '玩家輸贏', dataIndex: 'player_win_loss', key: 'player_win_loss', align: 'right', width: 140 },
        { title: this.labels.gift_amount     || '打賞總額', dataIndex: 'gift_amount',     key: 'gift_amount',     align: 'right', width: 130 },
        { title: this.labels.gift_count      || '打賞筆數', dataIndex: 'gift_count',      key: 'gift_count',      align: 'right', width: 100 },
      ];
    },

    filteredPlatforms() {
      let result = this.rawPlatforms;

      if (this.selectedPlatformIds.length) {
        const ids = new Set(this.selectedPlatformIds);
        result = result.filter(p => ids.has(p.platform_id));
      }

      if (this.selectedCateIds.length) {
        const cateIds = new Set(this.selectedCateIds);
        result = result.filter(p => cateIds.has(p.cate_id));
      }

      return result;
    },

    flatRows() {
      const groups = {};
      this.filteredPlatforms.forEach(p => {
        const id = p.cate_id;
        if (!groups[id]) {
          groups[id] = {
            cate_id: id,
            valid_bet: 0, total_bet: 0, player_win_loss: 0,
            gift_amount: 0, gift_count: 0,
            platforms: [],
          };
        }
        const g = groups[id];
        g.valid_bet       += p.valid_bet       || 0;
        g.total_bet       += p.total_bet       || 0;
        g.player_win_loss += p.player_win_loss || 0;
        g.gift_amount     += p.gift_amount     || 0;
        g.gift_count      += p.gift_count      || 0;
        g.platforms.push(p);
      });

      const toRows = (g) => [
        {
          key:             `cate_${g.cate_id}`,
          is_category:     true,
          name:            CATE_NAMES[g.cate_id] || '其他',
          valid_bet:       g.valid_bet,
          total_bet:       g.total_bet,
          player_win_loss: g.player_win_loss,
          gift_amount:     g.gift_amount,
          gift_count:      g.gift_count,
        },
        ...g.platforms.map(p => ({
          key:             `platform_${p.platform_id}`,
          is_category:     false,
          name:            p.name,
          valid_bet:       p.valid_bet,
          total_bet:       p.total_bet,
          player_win_loss: p.player_win_loss,
          gift_amount:     p.gift_amount,
          gift_count:      p.gift_count,
        })),
      ];

      const rows = [];
      const handled = new Set();
      CATE_ORDER.forEach(id => {
        if (!groups[id]) return;
        handled.add(id);
        rows.push(...toRows(groups[id]));
      });
      Object.values(groups).forEach(g => {
        if (!handled.has(g.cate_id)) rows.push(...toRows(g));
      });
      return rows;
    },
  },

  mounted() {
    this.fetchData();
  },

  methods: {
    onQuickDate(e) {
      this.dateRange = getDateRange(e.target.value);
      this.fetchData();
    },

    onDateRangeChange() {
      this.quickDate = null;
    },

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
          this.rawPlatforms = res.data || [];
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
      this.quickDate           = 'month';
      this.dateRange           = getDateRange('month');
      this.selectedPlatformIds = [];
      this.selectedCateIds     = [];
      this.fetchData();
    },

    rowAttrs(record) {
      if (record.is_category) {
        return { style: { background: '#fafafa', fontWeight: 'bold' } };
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

<style scoped>
/* ===== 工具栏 ===== */
.ex-tools {
  background: #fff;
  padding: 10px 16px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  min-height: 46px;
}

.ex-tools-left {
  flex: 1;
  display: flex;
  align-items: center;
}

.ex-tools-right {
  display: flex;
  align-items: center;
  margin: 0 4px;
}

.ex-page-title {
  font-size: 14px;
  font-weight: 600;
  color: rgba(0, 0, 0, 0.85);
}

.toolbar-icon {
  font-size: 16px;
  color: rgba(0, 0, 0, 0.45);
  cursor: pointer;
  transition: color 0.2s;
}

.toolbar-icon:hover {
  color: var(--ant-primary-color, #1890ff);
}

/* ===== 筛选区 ===== */
.ex-filter {
  border-top: 1px solid #ededed;
  background: #fff;
  padding: 24px 24px 8px;
}

/* 快捷日期行 */
.filter-quick-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.filter-quick-label {
  font-size: 13px;
  color: rgba(0, 0, 0, 0.65);
  white-space: nowrap;
  min-width: 56px;
}

/* 多列网格 */
.filter-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px 24px;
  margin-bottom: 20px;
}

/* 单个筛选项 */
.filter-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-label {
  font-size: 13px;
  color: rgba(0, 0, 0, 0.65);
  font-weight: 500;
}

.filter-control {
  width: 100%;
}

/* 按钮区 */
.filter-actions {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
}
</style>
