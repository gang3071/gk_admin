<template>
  <div class="bet-statistics-container">
    <a-spin :spinning="loading" tip="加载中...">
      <!-- 统计卡片 -->
      <a-row :gutter="16" style="margin-bottom: 16px; padding: 16px 16px 0 16px">
        <a-col :span="8">
          <a-card>
            <a-statistic
              title="今日打码量"
              :value="stats.today.total"
              :precision="2"
              suffix="元"
              :value-style="{ color: '#3f8600' }"
            >
              <template #prefix>
                <trophy-outlined />
              </template>
            </a-statistic>
            <div style="margin-top: 12px; font-size: 12px; color: rgba(0,0,0,0.45)">
              实体机台：{{ stats.today.machine.toFixed(2) }} 元<br/>
              电子游戏：{{ stats.today.game.toFixed(2) }} 元
            </div>
          </a-card>
        </a-col>

        <a-col :span="8">
          <a-card>
            <a-statistic
              title="本周打码量"
              :value="stats.week.total"
              :precision="2"
              suffix="元"
              :value-style="{ color: '#1890ff' }"
            >
              <template #prefix>
                <calendar-outlined />
              </template>
            </a-statistic>
            <div style="margin-top: 12px; font-size: 12px; color: rgba(0,0,0,0.45)">
              实体机台：{{ stats.week.machine.toFixed(2) }} 元<br/>
              电子游戏：{{ stats.week.game.toFixed(2) }} 元
            </div>
          </a-card>
        </a-col>

        <a-col :span="8">
          <a-card>
            <a-statistic
              title="本月打码量"
              :value="stats.month.total"
              :precision="2"
              suffix="元"
              :value-style="{ color: '#cf1322' }"
            >
              <template #prefix>
                <rise-outlined />
              </template>
            </a-statistic>
            <div style="margin-top: 12px; font-size: 12px; color: rgba(0,0,0,0.45)">
              实体机台：{{ stats.month.machine.toFixed(2) }} 元<br/>
              电子游戏：{{ stats.month.game.toFixed(2) }} 元
            </div>
          </a-card>
        </a-col>
      </a-row>

      <!-- 图表 -->
      <a-row :gutter="16" style="padding: 0 16px 16px 16px">
        <!-- 近30天打码量曲线图 -->
        <a-col :span="16">
          <a-card title="近30天打码量趋势" :bordered="false">
            <div ref="lineChart" style="width: 100%; height: 400px"></div>
          </a-card>
        </a-col>

        <!-- 本月打码量饼图 -->
        <a-col :span="8">
          <a-card title="本月打码量分布" :bordered="false">
            <div ref="pieChart" style="width: 100%; height: 400px"></div>
          </a-card>
        </a-col>
      </a-row>
    </a-spin>
  </div>
</template>

<script>
export default {
  name: 'PlayerBetStatistics',
  props: {
    playerId: {
      type: Number,
      required: true,
    },
  },
  data() {
    return {
      loading: true,
      echartsLoaded: false,
      stats: {
        today: { machine: 0, game: 0, total: 0 },
        week: { machine: 0, game: 0, total: 0 },
        month: { machine: 0, game: 0, total: 0 },
      },
      dailyTrend: {
        dates: [],
        machine: [],
        game: [],
      },
      lineChartInstance: null,
      pieChartInstance: null,
      pollingTimer: null, // 轮询定时器
      resizeHandler: null, // 窗口 resize 监听器
    };
  },
  mounted() {
    this.loadEcharts();
    // 启动轮询，每5秒刷新一次数据
    this.startPolling();
  },
  beforeUnmount() {
    // 停止轮询
    this.stopPolling();
    // 移除 resize 监听器
    if (this.resizeHandler) {
      window.removeEventListener('resize', this.resizeHandler);
      this.resizeHandler = null;
    }
    // 销毁图表实例
    if (this.lineChartInstance) {
      this.lineChartInstance.dispose();
      this.lineChartInstance = null;
    }
    if (this.pieChartInstance) {
      this.pieChartInstance.dispose();
      this.pieChartInstance = null;
    }
  },
  methods: {
    // 启动轮询
    startPolling() {
      this.pollingTimer = setInterval(() => {
        this.loadData(false); // false 表示不显示 loading
      }, 5000); // 每5秒刷新一次
    },

    // 停止轮询
    stopPolling() {
      if (this.pollingTimer) {
        clearInterval(this.pollingTimer);
        this.pollingTimer = null;
      }
    },

    loadEcharts() {
      // 检查 echarts 是否已加载
      if (window.echarts) {
        this.echartsLoaded = true;
        this.loadData(true);
        return;
      }

      // 动态加载 echarts
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js';
      script.onload = () => {
        this.echartsLoaded = true;
        this.loadData(true);
      };
      script.onerror = () => {
        this.$message.error('图表库加载失败，请刷新页面重试');
        this.loading = false;
      };
      document.head.appendChild(script);
    },

    async loadData(showLoading = true) {
      if (showLoading) {
        this.loading = true;
      }

      try {
        const response = await this.$request({
          url: 'ex-admin/addons-webman-controller-PlayerController/getBetStatisticsData',
          method: 'post',
          data: {
            playerId: this.playerId,
          }
        });

        if (response.code === 200) {
          if (response.data.today) {
            this.stats.today = response.data.today;
            this.stats.week = response.data.week;
            this.stats.month = response.data.month;
          }

          this.dailyTrend = response.data.dailyTrend;

          // 渲染图表
          this.$nextTick(() => {
            if (this.echartsLoaded) {
              this.renderLineChart();
              this.renderPieChart();
            }
            if (showLoading) {
              this.loading = false;
            }
          });
        } else {
          this.$message.error('加载数据失败: ' + response.msg);
          if (showLoading) {
            this.loading = false;
          }
        }
      } catch (error) {
        this.$message.error('加载数据失败: ' + (error.message || '未知错误'));
        if (showLoading) {
          this.loading = false;
        }
      }
    },

    renderLineChart() {
      if (!this.$refs.lineChart || !window.echarts) return;

      // ✅ 首次创建实例，后续只更新数据
      if (!this.lineChartInstance) {
        this.lineChartInstance = window.echarts.init(this.$refs.lineChart);
      }

      const option = {
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'cross',
          },
        },
        legend: {
          data: ['实体机台', '电子游戏'],
          top: 0,
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '3%',
          containLabel: true,
        },
        xAxis: {
          type: 'category',
          boundaryGap: false,
          data: this.dailyTrend.dates,
          axisLabel: {
            rotate: 45,
          },
        },
        yAxis: {
          type: 'value',
          name: '打码量（元）',
          axisLabel: {
            formatter: '{value}',
          },
        },
        series: [
          {
            name: '实体机台',
            type: 'line',
            smooth: true,
            data: this.dailyTrend.machine,
            itemStyle: {
              color: '#5470c6',
            },
            areaStyle: {
              color: {
                type: 'linear',
                x: 0,
                y: 0,
                x2: 0,
                y2: 1,
                colorStops: [
                  { offset: 0, color: 'rgba(84, 112, 198, 0.3)' },
                  { offset: 1, color: 'rgba(84, 112, 198, 0.05)' },
                ],
              },
            },
          },
          {
            name: '电子游戏',
            type: 'line',
            smooth: true,
            data: this.dailyTrend.game,
            itemStyle: {
              color: '#91cc75',
            },
            areaStyle: {
              color: {
                type: 'linear',
                x: 0,
                y: 0,
                x2: 0,
                y2: 1,
                colorStops: [
                  { offset: 0, color: 'rgba(145, 204, 117, 0.3)' },
                  { offset: 1, color: 'rgba(145, 204, 117, 0.05)' },
                ],
              },
            },
          },
        ],
      };

      // ✅ 使用 notMerge: false 平滑更新数据，避免闪烁
      this.lineChartInstance.setOption(option, { notMerge: false });

      // ✅ 仅首次添加 resize 监听器
      if (!this.resizeHandler) {
        this.resizeHandler = () => {
          if (this.lineChartInstance) {
            this.lineChartInstance.resize();
          }
          if (this.pieChartInstance) {
            this.pieChartInstance.resize();
          }
        };
        window.addEventListener('resize', this.resizeHandler);
      }
    },

    renderPieChart() {
      if (!this.$refs.pieChart || !window.echarts) return;

      // ✅ 首次创建实例，后续只更新数据
      if (!this.pieChartInstance) {
        this.pieChartInstance = window.echarts.init(this.$refs.pieChart);
      }

      const option = {
        tooltip: {
          trigger: 'item',
          formatter: '{a} <br/>{b}: {c} 元 ({d}%)',
        },
        legend: {
          orient: 'vertical',
          left: 'left',
          top: 'middle',
        },
        series: [
          {
            name: '打码量',
            type: 'pie',
            radius: ['40%', '70%'],
            avoidLabelOverlap: false,
            itemStyle: {
              borderRadius: 10,
              borderColor: '#fff',
              borderWidth: 2,
            },
            label: {
              show: true,
              formatter: '{b}\n{d}%',
            },
            emphasis: {
              label: {
                show: true,
                fontSize: '18',
                fontWeight: 'bold',
              },
            },
            data: [
              {
                value: this.stats.month.machine,
                name: '实体机台',
                itemStyle: { color: '#5470c6' },
              },
              {
                value: this.stats.month.game,
                name: '电子游戏',
                itemStyle: { color: '#91cc75' },
              },
            ],
          },
        ],
      };

      // ✅ 使用 notMerge: false 平滑更新数据，避免闪烁
      this.pieChartInstance.setOption(option, { notMerge: false });
      // resize 监听器已在 renderLineChart 中统一处理
    },
  },
};
</script>

<style scoped>
.bet-statistics-container {
  width: 100%;
  min-height: 600px;
  padding: 0;
  background: #f5f5f5;
  display: block;
  box-sizing: border-box;
}

.bet-statistics-container :deep(.ant-spin-container) {
  width: 100%;
  display: block;
}

.bet-statistics-container :deep(.ant-row) {
  width: 100%;
  margin: 0 !important;
  display: flex;
  flex-wrap: wrap;
}

.bet-statistics-container :deep(.ant-card) {
  border-radius: 8px;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03), 0 1px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px 0 rgba(0, 0, 0, 0.02);
  height: 100%;
}

.bet-statistics-container :deep(.ant-card-body) {
  padding: 20px;
}

.bet-statistics-container :deep(.ant-statistic-title) {
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 8px;
}

.bet-statistics-container :deep(.ant-statistic-content) {
  font-size: 24px;
  font-weight: 600;
}
</style>
