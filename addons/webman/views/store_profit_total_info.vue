<template>
  <a-collapse v-model:activeKey="activeKey" @change="handlePanelChange">
    <a-collapse-panel key="1" :header="trans.panelHeader">
      <!-- 加载状态 -->
      <div v-if="loading" style="text-align: center; padding: 20px;">
        <a-spin size="large"/>
        <div style="margin-top: 10px;">{{ trans.loading }}</div>
      </div>
      <!-- 数据展示 -->
      <div v-else-if="statsData">
        <a-row :gutter="[10, 10]" style="background: #fff">
          <a-col
              v-for="(item, index) in statsData"
              :key="index"
              :xs="responsiveSpan.xs"
              :sm="responsiveSpan.sm"
              :md="responsiveSpan.md"
              :lg="responsiveSpan.lg"
              :xl="responsiveSpan.xl"
              :xxl="responsiveSpan.xxl"
          >
            <a-card
                hoverable
                :body-style="cardBodyStyle"
                :head-style="{ height: '0px', borderBottom: '0px', minHeight: '0px' }"
                class="ant-card-body-d"
            >
              <div style="text-align: center; width: 100%">
                <a-statistic
                    :title="item.title"
                    :value="item.number"
                    :prefix="item.prefix"
                    :suffix="item.suffix"
                    :value-style="getValueStyle(item)"
                    :precision="getPrecision(item.number)"
                />
              </div>
            </a-card>
          </a-col>
        </a-row>
        <!-- 刷新按钮 -->
        <div style="margin-top: 16px; text-align: right;">
          <a-button type="primary" @click="fetchData" :loading="loading">
            <reload-outlined/>
            {{ trans.refresh }}
          </a-button>
        </div>
      </div>

      <!-- 初始状态或错误状态 -->
      <div v-else style="text-align: center; color: #999; padding: 20px;">
        <div v-if="error">
          <exclamation-circle-outlined style="font-size: 24px; color: #ff4d4f;"/>
          <p>{{ trans.loadError }}</p>
          <a-button @click="fetchData">{{ trans.retry }}</a-button>
        </div>
        <div v-else>
          <info-circle-outlined style="font-size: 24px;"/>
          <p>{{ trans.clickToView }}</p>
        </div>
      </div>
    </a-collapse-panel>
  </a-collapse>
</template>

<script>
export default {
  name: "store_profit_total_info",
  props: {
    ex_admin_filter: [],
    api_url: {
      type: String,
      default: 'ex-admin/login/totalInfo'
    },
    type: String,
    department_id: Number,
    admin_user_id: Number,
    store_ids: Array,
    player_ids: Array,
    minSpan: {
      type: Number,
      default: 4
    },
    maxColumns: {
      type: Number,
      default: 6
    },
    trans: {
      type: Object,
      default: () => ({
        panelHeader: '查看統計數據',
        loading: '數據載入中...',
        refresh: '刷新數據',
        loadError: '數據載入失敗',
        retry: '重試',
        clickToView: '點擊展開查看統計數據',
        loadFailedMsg: '數據載入失敗，請重試'
      })
    }
  },
  computed: {
    responsiveSpan() {
      const count = this.statsData ? this.statsData.length : 0;
      const result = {};

      Object.keys(this.breakpoints).forEach(breakpoint => {
        const config = this.breakpoints[breakpoint];
        let columns;

        if (count <= config.columns) {
          columns = Math.max(1, count);
        } else {
          columns = Math.min(config.columns, this.maxColumns);
        }

        const span = Math.floor(24 / columns);
        result[breakpoint] = Math.max(this.minSpan, span);
      });

      return result;
    },
    cardBodyStyle() {
      return {
        display: 'flex',
        alignItems: 'center',
        height: '72px',
        padding: '12px'
      };
    },
  },
  data() {
    return {
      activeKey: [],
      loading: false,
      statsData: null,
      error: false,
      hasLoaded: false,
      breakpoints: {
        xs: {max: 576, columns: 1},
        sm: {min: 576, max: 768, columns: 2},
        md: {min: 768, max: 992, columns: 3},
        lg: {min: 992, max: 1200, columns: 4},
        xl: {min: 1200, max: 1600, columns: 5},
        xxl: {min: 1600, columns: 6}
      },
      currentFilter: null
    };
  },
  watch: {
    ex_admin_filter: {
      handler(newVal) {
        this.currentFilter = newVal;
        if (this.activeKey.includes('1') && this.hasLoaded) {
          this.fetchData();
        }
      },
      deep: true,
      immediate: true
    }
  },
  methods: {
    getValueStyle(item) {
      const baseStyle = {
        fontSize: '15px',
        textAlign: 'center'
      };

      const value = Number(item.number);

      if (value < 0) {
        return {
          ...baseStyle,
          color: '#cf1322'
        };
      } else if (value > 0) {
        return {
          ...baseStyle,
          color: '#3f8600'
        };
      } else {
        return baseStyle;
      }
    },
    handlePanelChange(keys) {
      this.activeKey = keys;
      if (keys.includes('1') && !this.hasLoaded) {
        this.fetchData();
      }
    },
    getPrecision(number) {
      return Number.isInteger(number) ? 0 : 2;
    },
    async fetchData() {
      this.loading = true;
      this.error = false;

      const filterToUse = this.currentFilter || this.ex_admin_filter;

      this.$request({
        url: this.api_url,
        method: 'post',
        data: {
          ex_admin_filter: filterToUse,
          type: this.type,
          department_id: this.department_id,
          admin_user_id: this.admin_user_id,
          store_ids: this.store_ids,
          player_ids: this.player_ids,
        },
      }).then(res => {
        if (res.code === 200) {
          this.statsData = res.data;
          this.loading = false;
          this.hasLoaded = true;
        } else {
          this.error = true;
          this.loading = false;
          this.$message.error(this.trans.loadFailedMsg);
        }
      }).catch(error => {
        this.error = true;
        this.loading = false;
        this.$message.error(this.trans.loadFailedMsg);
      })
    }
  }
}
</script>

<style scoped>
.ant-space {
  width: 100%;
}

.ant-descriptions {
  margin-bottom: 16px;
}

.ant-spin {
  display: block;
}

.custom-statistic {
  font-size: 15px;
}

.ant-card-body-d {
  display: flex;
  align-items: center;
  justify-content: center;
}

.ant-divider-vertical {
  margin: 0 8px;
}

@media (max-width: 1200px) {
  .ant-col-8 {
    flex: 0 0 100%;
    max-width: 100%;
    margin-bottom: 10px;
  }
}

@media (max-width: 768px) {
  .ant-row-flex {
    flex-direction: column;
  }

  .ant-col-8 {
    flex: 0 0 100%;
    max-width: 100%;
  }

  .ant-divider-vertical {
    display: none;
  }
}
</style>
