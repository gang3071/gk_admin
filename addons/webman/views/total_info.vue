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
        <!-- 可选：添加刷新按钮 -->
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
  name: "total_info",
  props: {
    ex_admin_filter: [],
    type: String,
    department_id: Number,
    agent_player_id: Number,
    parent_player_id: Number,
    player_id: Number,
    minSpan: {
      type: Number,
      default: 4
    },
    // 最大每行列数
    maxColumns: {
      type: Number,
      default: 6
    },
    // 多语言翻译
    trans: {
      type: Object,
      default: () => ({
        panelHeader: '查看统计数据',
        loading: '数据加载中...',
        refresh: '刷新数据',
        loadError: '数据加载失败',
        retry: '重试',
        clickToView: '点击展开查看统计数据',
        loadFailedMsg: '数据加载失败，请重试'
      })
    }
  },
  computed: {
    // 响应式span计算
    responsiveSpan() {
      const count = this.statsData.length;
      const result = {};

      // 为每个断点计算span值
      Object.keys(this.breakpoints).forEach(breakpoint => {
        const config = this.breakpoints[breakpoint];
        let columns;

        if (count <= config.columns) {
          // 如果数据量小于等于该断点的推荐列数，使用实际数量
          columns = Math.max(1, count);
        } else {
          // 否则使用推荐列数，但不超过最大列数限制
          columns = Math.min(config.columns, this.maxColumns);
        }

        // 计算span值，确保不小于最小值
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
      }
    };
  },
  methods: {
    // 获取数值样式 - 已修改为根据正负数设置颜色
    getValueStyle(item) {
      const baseStyle = {
        fontSize: '15px',
        textAlign: 'center'
      };

      // 获取数值并转换为数字类型
      const value = Number(item.number);

      // 如果是负数，显示红色
      if (value < 0) {
        return {
          ...baseStyle,
          color: '#cf1322' // 红色
        };
      }
      // 如果是正数，显示绿色（可选，根据需求调整）
      else if (value > 0) {
        return {
          ...baseStyle,
          color: '#3f8600' // 绿色
        };
      }
      // 如果是0，使用默认颜色
      else {
        return baseStyle;
      }
    },
    // 处理面板展开/收起
    handlePanelChange(keys) {
      this.activeKey = keys;
      // 当面板展开且尚未加载过数据时，自动获取数据
      if (keys.includes('1') && !this.hasLoaded) {
        this.fetchData();
      }
    },
    getPrecision(number) {
      return Number.isInteger(number) ? 0 : 2;
    },
    // 获取数据方法
    async fetchData() {
      this.loading = true;
      this.error = false;
      this.$request({
        url: 'ex-admin/login/totalInfo',
        method: 'post',
        data: {
          ex_admin_filter: this.ex_admin_filter,
          type: this.type,
          player_id: this.player_id,
          department_id: this.department_id,
          parent_player_id: this.parent_player_id,
          agent_player_id: this.agent_player_id,
        },
      }).then(res => {
        if (res.code === 200) {
          this.statsData = res.data;
          this.loading = false;
          this.hasLoaded = true; // 标记已加载
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

/* 自定义样式 */
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