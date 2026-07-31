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

    <!-- 数据表格 -->
    <a-row :gutter="16">
      <!-- 近30天打码量列表 -->
      <a-col :span="16">
        <a-card title="近30天打码量详情" :bordered="false">
          <a-table
            :columns="columns"
            :data-source="tableData"
            :pagination="{ pageSize: 10 }"
            size="small"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.key === 'machine'">
                <a-tag color="blue">{{ record.machine }} 元</a-tag>
              </template>
              <template v-if="column.key === 'game'">
                <a-tag color="green">{{ record.game }} 元</a-tag>
              </template>
              <template v-if="column.key === 'total'">
                <a-tag color="orange">{{ record.total }} 元</a-tag>
              </template>
            </template>
          </a-table>
        </a-card>
      </a-col>

      <!-- 本月打码量占比 -->
      <a-col :span="8">
        <a-card title="本月打码量占比" :bordered="false">
          <div style="padding: 20px 0">
            <a-progress
              :percent="machinePercent"
              :format="() => '实体机台 ' + machinePercent + '%'"
              stroke-color="#5470c6"
              style="margin-bottom: 30px"
            />
            <a-statistic
              title="实体机台"
              :value="stats.month.machine"
              :precision="2"
              suffix="元"
              style="margin-bottom: 30px"
            />

            <a-progress
              :percent="gamePercent"
              :format="() => '电子游戏 ' + gamePercent + '%'"
              stroke-color="#91cc75"
              style="margin-bottom: 30px"
            />
            <a-statistic
              title="电子游戏"
              :value="stats.month.game"
              :precision="2"
              suffix="元"
            />
          </div>
        </a-card>
      </a-col>
    </a-row>
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
      columns: [
        {
          title: '日期',
          dataIndex: 'date',
          key: 'date',
          align: 'center',
        },
        {
          title: '实体机台',
          dataIndex: 'machine',
          key: 'machine',
          align: 'center',
        },
        {
          title: '电子游戏',
          dataIndex: 'game',
          key: 'game',
          align: 'center',
        },
        {
          title: '合计',
          dataIndex: 'total',
          key: 'total',
          align: 'center',
        },
      ],
    };
  },
  computed: {
    tableData() {
      const data = [];
      for (let i = 0; i < this.dailyTrend.dates.length; i++) {
        const machine = this.dailyTrend.machine[i] || 0;
        const game = this.dailyTrend.game[i] || 0;
        data.push({
          key: i,
          date: this.dailyTrend.dates[i],
          machine: machine.toFixed(2),
          game: game.toFixed(2),
          total: (machine + game).toFixed(2),
        });
      }
      return data.reverse(); // 最新日期在前
    },
    machinePercent() {
      const total = this.stats.month.machine + this.stats.month.game;
      if (total === 0) return 0;
      return Math.round((this.stats.month.machine / total) * 100);
    },
    gamePercent() {
      const total = this.stats.month.machine + this.stats.month.game;
      if (total === 0) return 0;
      return Math.round((this.stats.month.game / total) * 100);
    },
  },
  mounted() {
    this.loadData();
  },
  methods: {
    async loadData() {
      try {
        const response = await this.$request({
          url: 'ex-admin/addons-webman-controller-PlayerController/getBetStatisticsData',
          method: 'post',
          data: {
            playerId: this.playerId,
          }
        });

        if (response.code === 0) {
          if (response.data.today) {
            this.stats.today = response.data.today;
            this.stats.week = response.data.week;
            this.stats.month = response.data.month;
          }

          this.dailyTrend = response.data.dailyTrend;
        } else {
          this.$message.error('加载数据失败: ' + response.msg);
        }
      } catch (error) {
        this.$message.error('加载数据失败: ' + (error.message || '未知错误'));
      }
    },
  },
};
</script>

<style scoped>
.bet-statistics-container {
  padding: 24px;
  background: #f0f2f5;
}

.bet-statistics-container :deep(.ant-card) {
  border-radius: 8px;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03), 0 1px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px 0 rgba(0, 0, 0, 0.02);
}

.bet-statistics-container :deep(.ant-statistic-title) {
  font-size: 14px;
  font-weight: 500;
}

.bet-statistics-container :deep(.ant-statistic-content) {
  font-size: 24px;
  font-weight: 600;
}
</style>
