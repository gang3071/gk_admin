<template>
  <div>
    <!-- 工具栏 -->
    <div class="ex-tools">
      <div class="ex-tools-left">
        <span class="ex-page-title">{{ titleMain }}</span>
        <a-divider type="vertical" class="title-divider" />
        <span class="ex-page-sub">{{ titleSub }}</span>
      </div>

      <div class="ex-tools-right">
        <a-breadcrumb v-if="breadcrumbs && breadcrumbs.length" class="ex-breadcrumb">
          <a-breadcrumb-item v-for="(item, i) in breadcrumbs" :key="i">{{ item }}</a-breadcrumb-item>
        </a-breadcrumb>
        <a-divider type="vertical" style="margin: 0 14px;" />
        <a-space :size="8">
          <a-tooltip title="搜索">
            <a-button class="icon-btn" shape="circle" size="small" :loading="loading" @click="fetchData">
              <template #icon><search-outlined /></template>
            </a-button>
          </a-tooltip>
          <a-tooltip title="重置">
            <a-button class="icon-btn" shape="circle" size="small" @click="resetFilter">
              <template #icon><reload-outlined /></template>
            </a-button>
          </a-tooltip>
        </a-space>
      </div>
    </div>

    <!-- 筛选区 -->
    <div class="ex-filter">
      <!-- 三列网格：[快捷日期（上）+ 日期范围（下）] / [平台] / [分类] -->
      <div class="filter-grid">

        <!-- 日期组：快捷按钮在上，日期范围在下，严格左对齐 -->
        <div class="filter-item filter-date-group">
          <a-radio-group
            v-model:value="quickDate"
            button-style="solid"
            @change="onQuickDate"
            class="quick-date-btns"
          >
            <a-radio-button value="today">今日</a-radio-button>
            <a-radio-button value="yesterday">昨日</a-radio-button>
            <a-radio-button value="week">本週</a-radio-button>
            <a-radio-button value="month">本月</a-radio-button>
            <a-radio-button value="last_month">上月</a-radio-button>
          </a-radio-group>
          <a-range-picker
            v-model:value="dateRange"
            value-format="YYYY-MM-DD"
            :allow-clear="false"
            class="date-picker"
            @change="onDateRangeChange"
          />
        </div>

        <!-- 廠商平台 -->
        <div class="filter-item">
          <a-select
            v-model:value="selectedPlatformIds"
            mode="multiple"
            :placeholder="labels.platform || '全部平台'"
            class="filter-control"
            allow-clear
            show-arrow
            :max-tag-count="2"
          >
            <a-select-option v-for="p in platforms" :key="p.id" :value="p.id">
              {{ p.name }}
            </a-select-option>
          </a-select>
        </div>

        <!-- 遊戲分類 -->
        <div class="filter-item">
          <a-select
            v-model:value="selectedCateIds"
            mode="multiple"
            placeholder="全部分類"
            class="filter-control"
            allow-clear
            show-arrow
            :max-tag-count="2"
          >
            <a-select-option v-for="opt in cateOptions" :key="opt.id" :value="opt.id">
              {{ opt.name }}
            </a-select-option>
          </a-select>
        </div>
      </div>

      <!-- 操作按钮 -->
      <div class="filter-actions">
        <a-button type="primary" :loading="loading" @click="fetchData">
          <template #icon><search-outlined /></template>
          搜索
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
  1: '實體機台', 2: '電子', 3: '真人視訊', 4: '捕魚',
  5: '牌桌', 6: '棋牌', 7: '老虎機', 8: '街機', 9: '體育', 10: '彩票',
};
const CATE_ORDER = [2, 3, 4, 5, 6, 7, 8, 9, 10, 1];

function getDateRange(type) {
  const today = new Date();
  const fmt = d => d.toISOString().slice(0, 10);
  if (type === 'today')      { const s = fmt(today); return [s, s]; }
  if (type === 'yesterday')  { const d = new Date(today); d.setDate(d.getDate() - 1); const s = fmt(d); return [s, s]; }
  if (type === 'week')       { const d = new Date(today); const day = d.getDay() || 7; d.setDate(d.getDate() - day + 1); return [fmt(d), fmt(today)]; }
  if (type === 'month')      { return [fmt(today).slice(0, 8) + '01', fmt(today)]; }
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
    titleMain() {
      const parts = (this.page_title || '').split(' ');
      return parts.length > 1 ? parts.slice(0, -1).join(' ') : this.page_title;
    },
    titleSub() {
      const parts = (this.page_title || '').split(' ');
      return parts.length > 1 ? parts[parts.length - 1] : '';
    },

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
          groups[id] = { cate_id: id, valid_bet: 0, total_bet: 0, player_win_loss: 0, gift_amount: 0, gift_count: 0, platforms: [] };
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
        { key: `cate_${g.cate_id}`, is_category: true, name: CATE_NAMES[g.cate_id] || '其他',
          valid_bet: g.valid_bet, total_bet: g.total_bet, player_win_loss: g.player_win_loss,
          gift_amount: g.gift_amount, gift_count: g.gift_count },
        ...g.platforms.map(p => ({
          key: `platform_${p.platform_id}`, is_category: false, name: p.name,
          valid_bet: p.valid_bet, total_bet: p.total_bet, player_win_loss: p.player_win_loss,
          gift_amount: p.gift_amount, gift_count: p.gift_count,
        })),
      ];

      const rows = [];
      const handled = new Set();
      CATE_ORDER.forEach(id => { if (!groups[id]) return; handled.add(id); rows.push(...toRows(groups[id])); });
      Object.values(groups).forEach(g => { if (!handled.has(g.cate_id)) rows.push(...toRows(g)); });
      return rows;
    },
  },

  mounted() {
    this.fetchData();
  },

  methods: {
    onQuickDate(e) { this.dateRange = getDateRange(e.target.value); this.fetchData(); },
    onDateRangeChange() { this.quickDate = null; },

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
      return record.is_category ? { style: { background: '#fafafa', fontWeight: 'bold' } } : {};
    },

    formatNum(val) {
      return (parseFloat(val) || 0).toLocaleString('zh-TW', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
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
  justify-content: space-between;
  align-items: center;
  flex-wrap: nowrap;
  min-height: 46px;
}

.ex-tools-left {
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.ex-tools-right {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  overflow: hidden;
  max-width: 60%;
}

/* 面包屑强制单行，超出省略 */
.ex-breadcrumb {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ex-page-title {
  font-size: 14px;
  font-weight: 600;
  color: rgba(0, 0, 0, 0.85);
  white-space: nowrap;
}

.title-divider {
  height: 1em;
  margin: 0 8px;
  border-color: rgba(0, 0, 0, 0.2);
}

.ex-page-sub {
  font-size: 13px;
  color: rgba(0, 0, 0, 0.45);
  white-space: nowrap;
}

/* 圆形图标按钮：浅灰底色，无明显边框 */
.icon-btn {
  background-color: #f5f5f5 !important;
  border-color: #f5f5f5 !important;
  color: rgba(0, 0, 0, 0.55) !important;
  box-shadow: none !important;
}

.icon-btn:hover {
  background-color: #e8e8e8 !important;
  border-color: #e8e8e8 !important;
  color: rgba(0, 0, 0, 0.85) !important;
}

/* ===== 筛选区 ===== */
.ex-filter {
  border-top: 1px solid #ededed;
  background: #fff;
  padding: 16px 20px 4px;
}

/* 三列网格：日期组 2fr，平台 1fr，分类 1fr */
.filter-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 10px 16px;    /* 列间距 16px，行间距 10px */
  align-items: start; /* 顶部对齐，使日期组不拉伸相邻列 */
  margin-bottom: 12px;
}

/* 通用筛选项 */
.filter-item {
  display: flex;
  align-items: center;
}

/* 日期组：快捷按钮（上）+ 日期范围（下），垂直堆叠，严格左对齐 */
.filter-date-group {
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
}

/* 快捷日期按钮组 */
.quick-date-btns :deep(.ant-radio-button-wrapper) {
  height: 32px;
  line-height: 30px;
  padding: 0 11px;
  font-size: 12px;
}

/* 日期范围：撑满列宽，与上方按钮组左边对齐 */
.date-picker {
  width: 100%;
}

/* 平台/分类：撑满列宽 */
.filter-control {
  width: 100%;
}

/* 统一 select 高度 32px */
.filter-item :deep(.ant-select-selector) {
  height: 32px !important;
  min-height: 32px !important;
}

.filter-item :deep(.ant-select-selection-placeholder),
.filter-item :deep(.ant-select-selection-item) {
  line-height: 30px !important;
}

/* 按钮区 */
.filter-actions {
  display: flex;
  gap: 8px;
  padding-bottom: 16px;
}
</style>
