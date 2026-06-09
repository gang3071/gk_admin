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
          <template #title>
            <div class="card-title">
              <a-tag :color="getStatusColor(activity.status)">
                {{ getStatusText(activity.status) }}
              </a-tag>
              <span class="activity-name">{{ activity.name }}</span>
            </div>
          </template>

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

          <div class="activity-content">
            <div class="description" v-if="activity.description">
              <a-typography-paragraph
                  :ellipsis="{ rows: 2, expandable: false }"
                  style="margin-bottom: 12px; color: #666;"
              >
                {{ activity.description }}
              </a-typography-paragraph>
            </div>

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
        width="720"
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

        <a-form-item label="活动封面图片">
          <input
              type="file"
              accept="image/jpg,image/jpeg,image/png"
              @change="handleCoverUpload"
              ref="coverInput"
              style="display: block; margin-bottom: 8px;"
          />
          <div style="margin-top: 4px; margin-bottom: 8px; color: #999; font-size: 12px;">
            建议尺寸：750x400px，支持jpg、png格式，文件大小不超过2MB
          </div>
          <div v-if="uploading" style="margin-top: 8px;">
            <a-spin size="small"/> 上传中...
          </div>
          <img
              v-if="formData.cover_image"
              :src="formData.cover_image"
              style="max-width: 300px; margin-top: 8px; border: 1px solid #d9d9d9; border-radius: 4px;"
              alt="封面预览"
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

        <!-- VIP等级打码量配置 -->
        <a-divider>VIP等级打码量配置</a-divider>

        <a-alert
            message="为每个VIP等级配置达到指定打码量后发放的摸奖券数量"
            type="info"
            show-icon
            style="margin-bottom: 16px;"
        />

        <div v-if="formData.vip_configs && formData.vip_configs.length > 0">
          <div v-for="(config, index) in formData.vip_configs" :key="config.vip_level_id || index" class="vip-config-item">
            <a-row :gutter="12" align="middle">
              <a-col :span="8">
                <a-form-item label="VIP等级" style="margin-bottom: 0;">
                  <a-tag color="blue" style="font-size: 14px; padding: 4px 12px;">
                    {{ config.vip_level_name || getVipLevelName(config.vip_level_id) }}
                  </a-tag>
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item label="所需打码量" style="margin-bottom: 0;">
                  <a-input-number
                      v-model:value="config.bet_amount_required"
                      :min="0"
                      :precision="2"
                      style="width: 100%;"
                      placeholder="0.00"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item label="发放券数" style="margin-bottom: 0;">
                  <a-input-number
                      v-model:value="config.ticket_count"
                      :min="1"
                      :precision="0"
                      style="width: 100%;"
                      placeholder="1"
                  />
                </a-form-item>
              </a-col>
            </a-row>
          </div>
        </div>
        <a-empty v-else description="暂无VIP等级数据" style="margin: 20px 0;"/>

        <!-- 奖品等级配置 -->
        <a-divider>{{ trans.prizeLevelConfig }}</a-divider>

        <a-alert
            message="配置奖品等级和奖励金额(仅现金奖励)"
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
                  <a-select v-model:value="level.level_rank" :placeholder="trans.selectLevelRank" @change="handleLevelRankChange(index)">
                    <a-select-option v-for="i in 10" :key="i" :value="i" :disabled="isLevelRankSelected(i, index)">
                      {{ getLevelName(i) }}
                    </a-select-option>
                  </a-select>
                </a-form-item>
              </a-col>
              <a-col :span="12">
                <a-form-item label="奖励金额">
                  <a-input-number
                      v-model:value="level.prize_amount"
                      :min="0"
                      :precision="2"
                      style="width: 100%;"
                      placeholder="0.00"
                  />
                </a-form-item>
              </a-col>
            </a-row>
          </a-card>
        </div>
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
              {{ record.level_name }}
            </template>
            <template v-if="column.key === 'prize_amount'">
              {{ record.prize_amount }}
            </template>
          </template>
        </a-table>

        <!-- VIP等级配置列表 -->
        <a-divider>VIP等级配置</a-divider>
        <a-table
            v-if="currentActivity.vip_configs && currentActivity.vip_configs.length > 0"
            :columns="vipConfigColumns"
            :data-source="currentActivity.vip_configs"
            :pagination="false"
            size="small"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'vip_level_name'">
              {{ getVipLevelName(record.vip_level_id) }}
            </template>
            <template v-if="column.key === 'bet_amount_required'">
              {{ record.bet_amount_required }}
            </template>
            <template v-if="column.key === 'ticket_count'">
              {{ record.ticket_count }}
            </template>
          </template>
        </a-table>
        <a-empty v-else description="未配置VIP等级" />
      </template>
    </a-drawer>
  </div>
</template>

<script>
export default {
  name: 'LotteryTicketActivities',
  props: {
    department_id: Number,
    vip_levels: {
      type: Array,
      default: () => []
    },
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
      uploading: false,
      formData: {
        name: '',
        description: '',
        cover_image: '',
        start_time: null,
        end_time: null,
        vip_configs: [],
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
        {title: '等级', key: 'level_name', dataIndex: 'level_name'},
        {title: '奖励金额', key: 'prize_amount', dataIndex: 'prize_amount'},
      ],
      vipConfigColumns: [
        {title: 'VIP等级', key: 'vip_level_name', dataIndex: 'vip_level_id'},
        {title: '所需打码量', key: 'bet_amount_required', dataIndex: 'bet_amount_required'},
        {title: '发放券数', key: 'ticket_count', dataIndex: 'ticket_count'},
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
    console.log('Component mounted, department_id:', this.department_id);
    console.log('VIP levels:', this.vip_levels);
    this.fetchActivities();
  },
  methods: {
    // 上传封面图片
    async handleCoverUpload(event) {
      const file = event.target.files[0];
      if (!file) return;

      // 验证文件类型
      const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
      if (!allowedTypes.includes(file.type)) {
        this.$message.error('只支持 jpg、png 格式图片');
        return;
      }

      // 验证文件大小
      if (file.size > 2 * 1024 * 1024) {
        this.$message.error('文件大小不能超过2MB');
        return;
      }

      const formData = new FormData();
      formData.append('file', file);

      this.uploading = true;
      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/uploadCover',
          method: 'post',
          data: formData,
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });

        if (res.code === 0) {
          this.formData.cover_image = res.data.url;
          this.$message.success('图片上传成功');
        } else {
          this.$message.error(res.msg || '上传失败');
        }
      } catch (error) {
        this.$message.error('上传失败');
        console.error(error);
      } finally {
        this.uploading = false;
      }
    },

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

        console.log('API Response:', res);
        if (res.code === 200) {
          this.activities = res.data;
          console.log('Activities loaded:', this.activities.length, this.activities);
        } else {
          this.$message.error(res.message || res.msg || '获取活动列表失败');
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

      // 初始化VIP配置
      const vipConfigs = this.vip_levels.map(vipLevel => ({
        vip_level_id: vipLevel.id,
        vip_level_name: vipLevel.name,
        bet_amount_required: 0,
        ticket_count: 1
      }));

      this.formData = {
        name: '',
        description: '',
        cover_image: '',
        start_time: null,
        end_time: null,
        vip_configs: vipConfigs,
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
            cover_image: data.cover_image || '',
            start_time: this.$dayjs(data.start_time),
            end_time: this.$dayjs(data.end_time),
            vip_configs: data.vip_configs || [],
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
        content: '关闭后活动将立即停止,已发放的摸奖券将无法使用',
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
              this.$message.error(res.message || res.msg || '关闭活动失败');
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
        level_rank: null,
        level_name: '',
        prize_amount: 0
      });
    },

    // 移除奖品等级
    removePrizeLevel(index) {
      this.formData.prize_levels.splice(index, 1);
    },

    // 等级排名变化时更新等级名称
    handleLevelRankChange(index) {
      const rank = this.formData.prize_levels[index].level_rank;
      this.formData.prize_levels[index].level_name = this.getLevelName(rank);
    },

    // 检查等级排名是否已被选择
    isLevelRankSelected(rank, currentIndex) {
      return this.formData.prize_levels.some((level, index) => {
        return index !== currentIndex && level.level_rank === rank;
      });
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
          this.$message.error(res.message || res.msg || '操作失败');
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
      // 使用原生JavaScript格式化时间
      const date = new Date(time);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hour = String(date.getHours()).padStart(2, '0');
      const minute = String(date.getMinutes()).padStart(2, '0');
      return `${year}-${month}-${day} ${hour}:${minute}`;
    },

    getLevelName(rank) {
      return this.levelNames[rank] || `等级${rank}`;
    },

    getVipLevelName(vipLevelId) {
      const vipLevel = this.vip_levels.find(v => v.id === vipLevelId);
      return vipLevel ? vipLevel.name : `VIP${vipLevelId}`;
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

.vip-config-item {
  margin-bottom: 16px;
  padding: 16px;
  background: #fafafa;
  border: 1px solid #e8e8e8;
  border-radius: 6px;
  transition: all 0.3s;
}

.vip-config-item:hover {
  background: #f0f0f0;
  border-color: #d9d9d9;
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
