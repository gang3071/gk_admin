<template>
  <div class="lottery-ticket-container">
    <!-- 顶部操作栏 -->
    <div class="header-actions">
      <a-space>
        <a-button type="primary" @click="showCreateForm" :loading="loading">
          <template #icon>
            <plus-outlined/>
          </template>
          {{ trans.createActivity }}
        </a-button>
        <a-button @click="fetchActivities" :loading="loading">
          <template #icon>
            <reload-outlined/>
          </template>
          {{ trans.refresh }}
        </a-button>
        <a-segmented v-model:value="statusFilter" :options="statusOptions" @change="handleStatusChange"/>
      </a-space>
    </div>

    <!-- 加载状态 -->
    <div v-if="loading && !activities.length" class="loading-container">
      <a-spin size="large"/>
      <div style="margin-top: 10px;">{{ trans.loading }}</div>
    </div>

    <!-- 空状态 -->
    <a-empty v-else-if="!loading && !activities.length" :description="trans.noActivities">
      <a-button type="primary" @click="showCreateForm">{{ trans.createFirst }}</a-button>
    </a-empty>

    <!-- 活动面板列表 -->
    <a-row v-else :gutter="[16, 16]" style="margin-top: 16px;">
      <a-col
          v-for="activity in activities"
          :key="activity.id"
          :xs="24"
          :sm="24"
          :md="12"
          :lg="12"
          :xl="8"
          :xxl="6"
      >
        <a-card
            hoverable
            class="activity-card"
            :class="getCardClass(activity)"
        >
          <!-- 卡片头部 -->
          <template #title>
            <div class="card-title">
              <a-tag :color="getStatusColor(activity.status)">
                {{ getStatusText(activity.status) }}
              </a-tag>
              <span class="activity-name">{{ activity.name }}</span>
            </div>
          </template>

          <!-- 卡片操作按钮 -->
          <template #extra>
            <a-dropdown :trigger="['click']">
              <a-button type="text" size="small">
                <ellipsis-outlined/>
              </a-button>
              <template #overlay>
                <a-menu @click="(e) => handleMenuClick(e, activity)">
                  <a-menu-item key="view">
                    <eye-outlined/>
                    {{ trans.viewDetail }}
                  </a-menu-item>
                  <a-menu-item key="edit" v-if="activity.status === 0">
                    <edit-outlined/>
                    {{ trans.edit }}
                  </a-menu-item>
                  <a-menu-item key="close" danger v-if="activity.status === 1">
                    <stop-outlined/>
                    {{ trans.closeActivity }}
                  </a-menu-item>
                </a-menu>
              </template>
            </a-dropdown>
          </template>

          <!-- 卡片内容 -->
          <div class="activity-content">
            <!-- 活动描述 -->
            <div class="description" v-if="activity.description">
              <a-typography-paragraph
                  :ellipsis="{ rows: 2, expandable: false }"
                  style="margin-bottom: 12px; color: #666;"
              >
                {{ activity.description }}
              </a-typography-paragraph>
            </div>

            <!-- 活动时间 -->
            <div class="time-info">
              <div class="time-item">
                <clock-circle-outlined style="margin-right: 4px; color: #52c41a;"/>
                <span class="label">{{ trans.startTime }}:</span>
                <span class="value">{{ formatTime(activity.start_time) }}</span>
              </div>
              <div class="time-item">
                <clock-circle-outlined style="margin-right: 4px; color: #ff4d4f;"/>
                <span class="label">{{ trans.endTime }}:</span>
                <span class="value">{{ formatTime(activity.end_time) }}</span>
              </div>
            </div>

            <!-- 统计信息 -->
            <a-divider style="margin: 12px 0;"/>
            <a-row :gutter="8">
              <a-col :span="12">
                <a-statistic
                    :title="trans.totalTickets"
                    :value="activity.total_tickets"
                    :value-style="{ fontSize: '20px', color: '#1890ff' }"
                >
                  <template #prefix>
                    <file-text-outlined/>
                  </template>
                </a-statistic>
              </a-col>
              <a-col :span="12">
                <a-statistic
                    :title="trans.usedTickets"
                    :value="activity.used_tickets"
                    :value-style="{ fontSize: '20px', color: '#52c41a' }"
                >
                  <template #prefix>
                    <check-circle-outlined/>
                  </template>
                </a-statistic>
              </a-col>
            </a-row>

            <!-- 使用率进度条 -->
            <div style="margin-top: 12px;">
              <div style="margin-bottom: 4px; font-size: 12px; color: #666;">
                {{ trans.usageRate }}: {{ getUsageRate(activity) }}%
              </div>
              <a-progress
                  :percent="parseFloat(getUsageRate(activity))"
                  :status="getProgressStatus(activity)"
                  :strokeColor="getProgressColor(activity)"
              />
            </div>

            <!-- 奖品配置状态 -->
            <a-alert
                v-if="!activity.has_prize_config"
                :message="trans.noPrizeConfig"
                type="warning"
                show-icon
                style="margin-top: 12px;"
            >
              <template #icon>
                <warning-outlined/>
              </template>
            </a-alert>
          </div>
        </a-card>
      </a-col>
    </a-row>

    <!-- 创建/编辑活动抽屉 -->
    <a-drawer
        v-model:visible="formVisible"
        :title="formMode === 'create' ? trans.createActivity : trans.editActivity"
        width="600"
        :body-style="{ paddingBottom: '80px' }"
        @close="handleFormClose"
    >
      <a-form
          :model="formData"
          :rules="formRules"
          layout="vertical"
          ref="formRef"
      >
        <a-form-item :label="trans.activityName" name="name">
          <a-input v-model:value="formData.name" :placeholder="trans.activityNamePlaceholder"/>
        </a-form-item>

        <a-form-item :label="trans.description" name="description">
          <a-textarea
              v-model:value="formData.description"
              :placeholder="trans.descriptionPlaceholder"
              :rows="4"
              show-count
              :maxlength="500"
          />
        </a-form-item>

        <a-row :gutter="16">
          <a-col :span="12">
            <a-form-item :label="trans.startTime" name="start_time">
              <a-date-picker
                  v-model:value="formData.start_time"
                  show-time
                  format="YYYY-MM-DD HH:mm:ss"
                  style="width: 100%;"
                  :placeholder="trans.selectStartTime"
              />
            </a-form-item>
          </a-col>
          <a-col :span="12">
            <a-form-item :label="trans.endTime" name="end_time">
              <a-date-picker
                  v-model:value="formData.end_time"
                  show-time
                  format="YYYY-MM-DD HH:mm:ss"
                  style="width: 100%;"
                  :placeholder="trans.selectEndTime"
              />
            </a-form-item>
          </a-col>
        </a-row>

        <!-- 奖品等级配置 -->
        <a-divider>{{ trans.prizeLevelConfig }}</a-divider>

        <a-alert
            :message="trans.prizeLevelHint"
            type="info"
            show-icon
            style="margin-bottom: 16px;"
        />

        <a-form-item>
          <a-button type="dashed" block @click="addPrizeLevel">
            <plus-outlined/>
            {{ trans.addPrizeLevel }}
          </a-button>
        </a-form-item>

        <div v-for="(level, index) in formData.prize_levels" :key="index" class="prize-level-item">
          <a-card size="small" :title="`${trans.level} ${index + 1}`" style="margin-bottom: 12px;">
            <template #extra>
              <a-button type="text" danger size="small" @click="removePrizeLevel(index)">
                <delete-outlined/>
              </a-button>
            </template>

            <a-row :gutter="12">
              <a-col :span="12">
                <a-form-item :label="trans.levelRank">
                  <a-select v-model:value="level.level_rank" :placeholder="trans.selectLevelRank">
                    <a-select-option v-for="i in 10" :key="i" :value="i">
                      {{ getLevelName(i) }}
                    </a-select-option>
                  </a-select>
                </a-form-item>
              </a-col>
              <a-col :span="12">
                <a-form-item :label="trans.prizeType">
                  <a-select v-model:value="level.prize_type" :placeholder="trans.selectPrizeType">
                    <a-select-option value="cash">{{ trans.prizeTypeCash }}</a-select-option>
                    <a-select-option value="bonus">{{ trans.prizeTypeBonus }}</a-select-option>
                    <a-select-option value="points">{{ trans.prizeTypePoints }}</a-select-option>
                    <a-select-option value="item">{{ trans.prizeTypeItem }}</a-select-option>
                  </a-select>
                </a-form-item>
              </a-col>
            </a-row>

            <a-row :gutter="12">
              <a-col :span="8">
                <a-form-item :label="trans.prizeAmount" v-if="level.prize_type !== 'item'">
                  <a-input-number
                      v-model:value="level.prize_amount"
                      :min="0"
                      :precision="2"
                      style="width: 100%;"
                  />
                </a-form-item>
                <a-form-item :label="trans.itemName" v-else>
                  <a-input v-model:value="level.prize_item_name"/>
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item :label="trans.prizeCount">
                  <a-input-number
                      v-model:value="level.prize_count"
                      :min="0"
                      :precision="0"
                      style="width: 100%;"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item :label="trans.winProbability">
                  <a-input-number
                      v-model:value="level.win_probability"
                      :min="0"
                      :max="100"
                      :precision="2"
                      addon-after="%"
                      style="width: 100%;"
                  />
                </a-form-item>
              </a-col>
            </a-row>

            <a-form-item :label="trans.description">
              <a-textarea
                  v-model:value="level.description"
                  :rows="2"
                  :maxlength="200"
              />
            </a-form-item>
          </a-card>
        </div>

        <!-- 概率总和提示 -->
        <a-alert
            v-if="getTotalProbability() > 100"
            :message="`${trans.probabilityExceed}: ${getTotalProbability()}%`"
            type="error"
            show-icon
            style="margin-bottom: 16px;"
        />
        <a-alert
            v-else-if="formData.prize_levels.length > 0"
            :message="`${trans.totalProbability}: ${getTotalProbability()}%`"
            :type="getTotalProbability() === 100 ? 'success' : 'info'"
            show-icon
            style="margin-bottom: 16px;"
        />
      </a-form>

      <div class="drawer-footer">
        <a-button @click="handleFormClose" style="margin-right: 8px;">
          {{ trans.cancel }}
        </a-button>
        <a-button type="primary" @click="handleFormSubmit" :loading="submitting">
          {{ trans.submit }}
        </a-button>
      </div>
    </a-drawer>

    <!-- 活动详情抽屉 -->
    <a-drawer
        v-model:visible="detailVisible"
        :title="trans.activityDetail"
        width="600"
    >
      <template v-if="currentActivity">
        <a-descriptions :column="1" bordered>
          <a-descriptions-item :label="trans.activityName">
            {{ currentActivity.name }}
          </a-descriptions-item>
          <a-descriptions-item :label="trans.status">
            <a-tag :color="getStatusColor(currentActivity.status)">
              {{ getStatusText(currentActivity.status) }}
            </a-tag>
          </a-descriptions-item>
          <a-descriptions-item :label="trans.description">
            {{ currentActivity.description || '-' }}
          </a-descriptions-item>
          <a-descriptions-item :label="trans.timeRange">
            {{ formatTime(currentActivity.start_time) }} ~ {{ formatTime(currentActivity.end_time) }}
          </a-descriptions-item>
          <a-descriptions-item :label="trans.totalTickets">
            {{ currentActivity.total_tickets }}
          </a-descriptions-item>
          <a-descriptions-item :label="trans.usedTickets">
            {{ currentActivity.used_tickets }}
          </a-descriptions-item>
          <a-descriptions-item :label="trans.usageRate">
            {{ getUsageRate(currentActivity) }}%
          </a-descriptions-item>
        </a-descriptions>

        <!-- 奖品等级列表 -->
        <a-divider>{{ trans.prizeLevelConfig }}</a-divider>
        <a-table
            :columns="prizeColumns"
            :data-source="currentActivity.prize_levels || []"
            :pagination="false"
            size="small"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'level_name'">
              {{ getLevelName(record.level_rank) }}
            </template>
            <template v-if="column.key === 'prize_type'">
              {{ getPrizeTypeText(record.prize_type) }}
            </template>
            <template v-if="column.key === 'prize_display'">
              <template v-if="record.prize_type === 'item'">
                {{ record.prize_item_name }}
              </template>
              <template v-else>
                {{ record.prize_amount }}
              </template>
            </template>
          </template>
        </a-table>
      </template>
    </a-drawer>
  </div>
</template>

<script>
export default {
  name: 'LotteryTicketActivities',
  props: {
    department_id: Number,
    trans: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      loading: false,
      activities: [],
      statusFilter: 'all',
      formVisible: false,
      detailVisible: false,
      formMode: 'create',
      currentActivity: null,
      submitting: false,
      formData: {
        name: '',
        description: '',
        start_time: null,
        end_time: null,
        prize_levels: []
      },
      formRules: {
        name: [
          {required: true, message: '请输入活动名称', trigger: 'blur'},
          {max: 100, message: '活动名称不能超过100个字符', trigger: 'blur'}
        ],
        start_time: [
          {required: true, message: '请选择开始时间', trigger: 'change'}
        ],
        end_time: [
          {required: true, message: '请选择结束时间', trigger: 'change'}
        ]
      },
      prizeColumns: [
        {title: '等级', key: 'level_name', dataIndex: 'level_rank'},
        {title: '奖品类型', key: 'prize_type', dataIndex: 'prize_type'},
        {title: '奖品', key: 'prize_display'},
        {title: '数量', dataIndex: 'prize_count'},
        {title: '概率(%)', dataIndex: 'win_probability'},
      ],
      levelNames: [
        '', '特等奖', '一等奖', '二等奖', '三等奖', '四等奖',
        '五等奖', '六等奖', '七等奖', '八等奖', '九等奖'
      ]
    };
  },
  computed: {
    statusOptions() {
      return [
        {label: this.trans.allStatus, value: 'all'},
        {label: this.trans.notStarted, value: 0},
        {label: this.trans.ongoing, value: 1},
        {label: this.trans.ended, value: 2},
        {label: this.trans.closed, value: 3},
      ];
    }
  },
  mounted() {
    this.fetchActivities();
  },
  methods: {
    // 获取活动列表
    async fetchActivities() {
      this.loading = true;
      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getActivities',
          method: 'post',
          data: {
            status: this.statusFilter !== 'all' ? this.statusFilter : null
          }
        });

        if (res.code === 200) {
          this.activities = res.data;
        } else {
          this.$message.error(res.message || '获取活动列表失败');
        }
      } catch (error) {
        this.$message.error('获取活动列表失败');
        console.error(error);
      } finally {
        this.loading = false;
      }
    },

    // 状态筛选变化
    handleStatusChange() {
      this.fetchActivities();
    },

    // 显示创建表单
    showCreateForm() {
      this.formMode = 'create';
      this.formData = {
        name: '',
        description: '',
        start_time: null,
        end_time: null,
        prize_levels: []
      };
      this.formVisible = true;
    },

    // 菜单点击
    handleMenuClick({key}, activity) {
      this.currentActivity = activity;

      switch (key) {
        case 'view':
          this.showDetail(activity);
          break;
        case 'edit':
          this.editActivity(activity);
          break;
        case 'close':
          this.closeActivity(activity);
          break;
      }
    },

    // 查看详情
    async showDetail(activity) {
      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getActivityDetail',
          method: 'post',
          data: {id: activity.id}
        });

        if (res.code === 200) {
          this.currentActivity = res.data;
          this.detailVisible = true;
        }
      } catch (error) {
        this.$message.error('获取活动详情失败');
      }
    },

    // 编辑活动
    async editActivity(activity) {
      // 先获取完整数据
      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getActivityDetail',
          method: 'post',
          data: {id: activity.id}
        });

        if (res.code === 200) {
          const data = res.data;
          this.formMode = 'edit';
          this.formData = {
            id: data.id,
            name: data.name,
            description: data.description,
            start_time: this.$dayjs(data.start_time),
            end_time: this.$dayjs(data.end_time),
            prize_levels: data.prize_levels || []
          };
          this.formVisible = true;
        }
      } catch (error) {
        this.$message.error('获取活动详情失败');
      }
    },

    // 关闭活动
    closeActivity(activity) {
      this.$confirm({
        title: '确认关闭活动？',
        content: '关闭后活动将立即停止，已发放的摸奖券将无法使用',
        okText: '确认',
        cancelText: '取消',
        onOk: async () => {
          try {
            const res = await this.$request({
              url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/closeActivity',
              method: 'post',
              data: {id: activity.id}
            });

            if (res.code === 200) {
              this.$message.success('活动已关闭');
              this.fetchActivities();
            } else {
              this.$message.error(res.message || '关闭活动失败');
            }
          } catch (error) {
            this.$message.error('关闭活动失败');
          }
        }
      });
    },

    // 添加奖品等级
    addPrizeLevel() {
      if (this.formData.prize_levels.length >= 10) {
        this.$message.warning('最多只能添加10个奖品等级');
        return;
      }

      this.formData.prize_levels.push({
        level_rank: this.formData.prize_levels.length + 1,
        prize_type: 'cash',
        prize_amount: 0,
        prize_item_name: '',
        prize_count: 0,
        win_probability: 0,
        description: ''
      });
    },

    // 移除奖品等级
    removePrizeLevel(index) {
      this.formData.prize_levels.splice(index, 1);
    },

    // 获取概率总和
    getTotalProbability() {
      return this.formData.prize_levels.reduce((sum, level) => {
        return sum + (parseFloat(level.win_probability) || 0);
      }, 0).toFixed(2);
    },

    // 表单提交
    async handleFormSubmit() {
      try {
        await this.$refs.formRef.validate();

        // 验证时间
        if (this.formData.end_time.isBefore(this.formData.start_time)) {
          this.$message.error('结束时间必须大于开始时间');
          return;
        }

        // 验证概率
        if (this.getTotalProbability() > 100) {
          this.$message.error('中奖概率总和不能超过100%');
          return;
        }

        this.submitting = true;

        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/saveActivity',
          method: 'post',
          data: {
            ...this.formData,
            start_time: this.formData.start_time.format('YYYY-MM-DD HH:mm:ss'),
            end_time: this.formData.end_time.format('YYYY-MM-DD HH:mm:ss'),
          }
        });

        if (res.code === 200) {
          this.$message.success(this.formMode === 'create' ? '创建成功' : '更新成功');
          this.formVisible = false;
          this.fetchActivities();
        } else {
          this.$message.error(res.message || '操作失败');
        }
      } catch (error) {
        console.error(error);
      } finally {
        this.submitting = false;
      }
    },

    // 关闭表单
    handleFormClose() {
      this.formVisible = false;
      this.$refs.formRef?.resetFields();
    },

    // 工具方法
    getCardClass(activity) {
      const classes = [];
      if (activity.status === 1) classes.push('card-ongoing');
      if (activity.status === 0) classes.push('card-not-started');
      if (activity.status === 2 || activity.status === 3) classes.push('card-ended');
      return classes.join(' ');
    },

    getStatusColor(status) {
      const colors = {0: 'blue', 1: 'green', 2: 'default', 3: 'red'};
      return colors[status] || 'default';
    },

    getStatusText(status) {
      const texts = {
        0: this.trans.notStarted,
        1: this.trans.ongoing,
        2: this.trans.ended,
        3: this.trans.closed
      };
      return texts[status] || '未知';
    },

    getUsageRate(activity) {
      if (activity.total_tickets === 0) return '0.00';
      return ((activity.used_tickets / activity.total_tickets) * 100).toFixed(2);
    },

    getProgressStatus(activity) {
      const rate = parseFloat(this.getUsageRate(activity));
      if (rate === 100) return 'success';
      if (rate >= 80) return 'normal';
      return 'active';
    },

    getProgressColor(activity) {
      const rate = parseFloat(this.getUsageRate(activity));
      if (rate >= 80) return '#52c41a';
      if (rate >= 50) return '#1890ff';
      return '#faad14';
    },

    formatTime(time) {
      if (!time) return '';
      // 使用 dayjs 格式化时间
      return this.$dayjs(time).format('YYYY-MM-DD HH:mm');
    },

    getLevelName(rank) {
      return this.levelNames[rank] || `等级${rank}`;
    },

    getPrizeTypeText(type) {
      const types = {
        cash: this.trans.prizeTypeCash,
        bonus: this.trans.prizeTypeBonus,
        points: this.trans.prizeTypePoints,
        item: this.trans.prizeTypeItem
      };
      return types[type] || type;
    }
  }
};
</script>

<style scoped>
.lottery-ticket-container {
  padding: 16px;
  background: #f0f2f5;
  min-height: calc(100vh - 64px);
}

.header-actions {
  background: #fff;
  padding: 16px;
  border-radius: 4px;
  margin-bottom: 16px;
}

.loading-container {
  text-align: center;
  padding: 60px 0;
  background: #fff;
  border-radius: 4px;
}

.activity-card {
  height: 100%;
  transition: all 0.3s;
  border-radius: 8px;
}

.activity-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.card-ongoing {
  border-left: 4px solid #52c41a;
}

.card-not-started {
  border-left: 4px solid #1890ff;
}

.card-ended {
  border-left: 4px solid #d9d9d9;
}

.card-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.activity-name {
  font-weight: 500;
  font-size: 16px;
}

.activity-content {
  min-height: 280px;
}

.time-info {
  font-size: 13px;
  color: #666;
}

.time-item {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}

.time-item .label {
  margin-right: 4px;
  color: #999;
}

.time-item .value {
  color: #333;
}

.prize-level-item {
  margin-bottom: 12px;
}

.drawer-footer {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 100%;
  border-top: 1px solid #e8e8e8;
  padding: 10px 16px;
  background: #fff;
  text-align: right;
}

:deep(.ant-card-head) {
  border-bottom: 1px solid #f0f0f0;
}

:deep(.ant-statistic-title) {
  font-size: 12px;
  color: #999;
}

:deep(.ant-progress-text) {
  font-size: 12px;
}
</style>
