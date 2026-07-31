<template>
  <div class="bet-statistics-container">
    <!-- 统计卡片 -->
    <a-row :gutter="16" style="margin-bottom: 24px">
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
    <a-row :gutter="16">
      <!-- 每日打码量曲线图 -->
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
  </div>
</template>

<script>
import { defineComponent, ref, onMounted, nextTick } from 'vue';
import { Card, Row, Col, Statistic, message } from 'ant-design-vue';
import {
  TrophyOutlined,
  CalendarOutlined,
  RiseOutlined
} from '@ant-design/icons-vue';
import * as echarts from 'echarts';
import axios from 'axios';

export default defineComponent({
  name: 'PlayerBetStatistics',
  components: {
    ACard: Card,
    ARow: Row,
    ACol: Col,
    AStatistic: Statistic,
    TrophyOutlined,
    CalendarOutlined,
    RiseOutlined,
  },
  props: {
    playerId: {
      type: Number,
      required: true,
    },
  },
  setup(props) {
    const lineChart = ref(null);
    const pieChart = ref(null);
    const stats = ref({
      today: { machine: 0, game: 0, total: 0 },
      week: { machine: 0, game: 0, total: 0 },
      month: { machine: 0, game: 0, total: 0 },
    });
    const dailyTrend = ref({
      dates: [],
      machine: [],
      game: [],
    });

    // 加载数据
    const loadData = async () => {
      try {
        const response = await axios.post('/admin/player/getBetStatisticsData', {
          playerId: props.playerId,
        });

        if (response.data.code === 0) {
          stats.value = response.data.data.today ? {
            today: response.data.data.today,
            week: response.data.data.week,
            month: response.data.data.month,
          } : stats.value;

          dailyTrend.value = response.data.data.dailyTrend;

          // 渲染图表
          await nextTick();
          renderLineChart();
          renderPieChart();
        } else {
          message.error('加载数据失败: ' + response.data.msg);
        }
      } catch (error) {
        message.error('加载数据失败: ' + error.message);
      }
    };

    // 渲染曲线图
    const renderLineChart = () => {
      if (!lineChart.value) return;

      const chart = echarts.init(lineChart.value);
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
          data: dailyTrend.value.dates,
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
            data: dailyTrend.value.machine,
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
            data: dailyTrend.value.game,
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

      chart.setOption(option);

      // 响应式
      window.addEventListener('resize', () => {
        chart.resize();
      });
    };

    // 渲染饼图
    const renderPieChart = () => {
      if (!pieChart.value) return;

      const chart = echarts.init(pieChart.value);
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
                value: stats.value.month.machine,
                name: '实体机台',
                itemStyle: { color: '#5470c6' },
              },
              {
                value: stats.value.month.game,
                name: '电子游戏',
                itemStyle: { color: '#91cc75' },
              },
            ],
          },
        ],
      };

      chart.setOption(option);

      // 响应式
      window.addEventListener('resize', () => {
        chart.resize();
      });
    };

    onMounted(() => {
      loadData();
    });

    return {
      lineChart,
      pieChart,
      stats,
    };
  },
});
</script>

<style scoped>
.bet-statistics-container {
  padding: 24px;
  background: #f0f2f5;
}

:deep(.ant-card) {
  border-radius: 8px;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03), 0 1px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px 0 rgba(0, 0, 0, 0.02);
}

:deep(.ant-statistic-title) {
  font-size: 14px;
  font-weight: 500;
}

:deep(.ant-statistic-content) {
  font-size: 24px;
  font-weight: 600;
}
</style>
