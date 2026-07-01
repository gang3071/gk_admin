<template>
  <div class="lottery-ticket-container">
    <!-- 顶部操作栏 -->
    <div class="header-actions">
      <a-space>
        <!-- ⭐ 创建活动下拉菜单 -->
        <a-dropdown :trigger="['click']">
          <a-button type="primary" :loading="loading">
            <template #icon>
              <plus-outlined/>
            </template>
            {{ trans.createActivity }}
            <down-outlined style="margin-left: 4px; font-size: 10px;"/>
          </a-button>
          <template #overlay>
            <a-menu @click="handleCreateMenuClick">
              <a-menu-item key="new">
                <plus-outlined/>
                {{ trans.createFromScratch || '從零創建' }}
              </a-menu-item>
              <a-menu-item key="copy">
                <copy-outlined/>
                {{ trans.createFromHistory || '從歷史活動創建' }}
              </a-menu-item>
            </a-menu>
          </template>
        </a-dropdown>
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
            style="cursor: pointer;"
            @click="viewActivityDetail(activity)"
        >
          <template #title>
            <div class="card-title">
              <a-tag :color="getStatusColor(activity.status)">
                {{ getStatusText(activity.status) }}
              </a-tag>
              <!-- ⭐ 直播状态标签 -->
              <a-tag v-if="activity.live_url && activity.live_status === 1" color="red" style="margin-left: 8px;">
                <play-circle-outlined style="margin-right: 4px;"/>
                {{ trans.liveStreaming || '直播中' }}
              </a-tag>
              <a-tag v-else-if="activity.live_url && activity.live_status === 0" color="blue" style="margin-left: 8px;">
                <video-camera-outlined style="margin-right: 4px;"/>
                {{ trans.notLive || '未開播' }}
              </a-tag>
              <span class="activity-name">{{ activity.name }}</span>
            </div>
          </template>

          <template #extra>
            <a-dropdown :trigger="['click']" @click.stop>
              <a-button type="text" size="small">
                <ellipsis-outlined/>
              </a-button>
              <template #overlay>
                <a-menu @click="(e) => handleMenuClick(e, activity)">
                  <!-- 查看详情（所有状态） -->
                  <a-menu-item key="view">
                    <eye-outlined/>
                    {{ trans.viewDetail }}
                  </a-menu-item>

                  <!-- 编辑（未开始） -->
                  <a-menu-item key="edit" v-if="activity.status === 0">
                    <edit-outlined/>
                    {{ trans.edit }}
                  </a-menu-item>

                  <!-- ⭐ 开始开奖（待开奖状态） -->
                  <a-menu-item v-if="activity.status === 5" key="startDrawing">
                    <play-circle-outlined/>
                    {{ trans.startDrawing || '开始开奖' }}
                  </a-menu-item>

                  <!-- ⭐ 录入中奖（进行中/待开奖/开奖中） -->
                  <a-menu-item v-if="activity.status === 1 || activity.status === 5 || activity.status === 6" key="record">
                    <trophy-outlined/>
                    {{ trans.recordWin }}
                  </a-menu-item>

                  <!-- ⭐ 发放奖励（进行中/待开奖/开奖中/已结束，且有待发放） -->
                  <a-menu-item v-if="(activity.status === 1 || activity.status === 5 || activity.status === 6 || activity.status === 2) && activity.pending_count > 0" key="distribute">
                    <gift-outlined/>
                    {{ trans.distributeAllPending || '发放奖励' }} ({{ activity.pending_count }})
                  </a-menu-item>

                  <!-- ⭐ 停止开奖（开奖中） -->
                  <a-menu-item key="stopDrawing" v-if="activity.status === 6">
                    <check-circle-outlined/>
                    {{ trans.stopDrawing || '停止开奖' }}
                  </a-menu-item>

                  <!-- 添加/编辑直播地址（所有状态） -->
                  <a-menu-item key="live">
                    <video-camera-outlined/>
                    {{ activity.live_url ? '编辑直播地址' : trans.addLiveUrl }}
                  </a-menu-item>

                  <!-- 预览直播（仅当有直播地址时） -->
                  <a-menu-item key="previewLive" v-if="activity.live_url">
                    <play-circle-outlined/>
                    {{ trans.previewLive || '預覽直播' }}
                  </a-menu-item>

                  <!-- ⭐ 开始直播（仅当有直播地址且未开播时） -->
                  <a-menu-item v-if="activity.live_url && activity.live_status === 0" key="startLive">
                    <play-circle-outlined style="color: #52c41a;"/>
                    {{ trans.startLive || '開始直播' }}
                  </a-menu-item>

                  <!-- ⭐ 结束直播（仅当直播中时） -->
                  <a-menu-item v-if="activity.live_status === 1" key="endLive">
                    <stop-outlined style="color: #ff4d4f;"/>
                    {{ trans.endLive || '結束直播' }}
                  </a-menu-item>

                  <!-- 关闭活动（进行中） -->
                  <a-menu-item key="close" danger v-if="activity.status === 1">
                    <stop-outlined/>
                    {{ trans.closeActivity }}
                  </a-menu-item>
                </a-menu>
              </template>
            </a-dropdown>
          </template>

          <div class="activity-content">
            <!-- 封面图片 -->
            <div v-if="activity.cover_image" style="margin-bottom: 12px;">
              <img
                  :src="activity.cover_image"
                  style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px;"
                  alt="活动封面"
              />
            </div>

            <!-- 活动描述 -->
            <div class="description" v-if="activity.description">
              <div style="margin-bottom: 12px; color: #666; font-size: 13px; line-height: 1.6;">
                <a-typography-paragraph
                    :ellipsis="{ rows: 3, expandable: true, symbol: trans.expand }"
                    :content="activity.description"
                    style="margin-bottom: 0;"
                />
              </div>
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

            <!-- 统计信息 -->
            <a-row :gutter="12">
              <a-col :span="8">
                <a-statistic
                    :title="trans.totalTickets || '总发放数量'"
                    :value="activity.total_tickets"
                    :value-style="{ fontSize: '18px', color: '#1890ff' }"
                    style="text-align: center;"
                >
                  <template #prefix>
                    <file-text-outlined/>
                  </template>
                </a-statistic>
              </a-col>
              <a-col :span="8">
                <a-statistic
                    :title="trans.maxTicketNo || '最大券号'"
                    :value="activity.max_ticket_no || '000000'"
                    :value-style="{ fontSize: '18px', color: '#52c41a', fontFamily: 'monospace' }"
                    style="text-align: center;"
                >
                  <template #prefix>
                    <number-outlined/>
                  </template>
                </a-statistic>
              </a-col>
              <a-col :span="8">
                <a-statistic
                    :title="trans.pendingCount || '待发放'"
                    :value="activity.pending_count || 0"
                    :value-style="{ fontSize: '18px', color: activity.pending_count > 0 ? '#ff9800' : '#999' }"
                    style="text-align: center;"
                >
                  <template #prefix>
                    <gift-outlined/>
                  </template>
                </a-statistic>
              </a-col>
            </a-row>

            <!-- 操作按钮 -->
            <a-space direction="vertical" style="width: 100%; margin-top: 12px;">
              <!-- 未开始：编辑按钮 -->
              <a-button
                  v-if="activity.status === 0"
                  type="primary"
                  block
                  @click.stop="editActivity(activity)"
              >
                <template #icon>
                  <edit-outlined/>
                </template>
                {{ trans.edit }}
              </a-button>

              <!-- 进行中：发放奖励按钮 -->
              <a-button
                  v-if="activity.status === 1 && activity.pending_count > 0"
                  block
                  type="primary"
                  @click.stop="showDistributeForm(activity)"
              >
                <template #icon>
                  <gift-outlined/>
                </template>
                {{ trans.distributeAllPending || '发放奖励' }}
                <a-badge
                    :count="activity.pending_count"
                    :number-style="{ backgroundColor: '#52c41a', marginLeft: '8px' }"
                />
              </a-button>

              <!-- ⭐ 开奖中：发放奖励按钮 -->
              <a-button
                  v-if="activity.status === 6 && activity.pending_count > 0"
                  block
                  type="primary"
                  @click.stop="showDistributeForm(activity)"
              >
                <template #icon>
                  <gift-outlined/>
                </template>
                {{ trans.distributeAllPending || '发放奖励' }}
                <a-badge
                    :count="activity.pending_count"
                    :number-style="{ backgroundColor: '#52c41a', marginLeft: '8px' }"
                />
              </a-button>

              <!-- ⭐ 已结束：发放奖励按钮 -->
              <a-button
                  v-if="activity.status === 2 && activity.pending_count > 0"
                  block
                  type="primary"
                  @click.stop="showDistributeForm(activity)"
              >
                <template #icon>
                  <gift-outlined/>
                </template>
                {{ trans.distributeAllPending || '发放奖励' }}
                <a-badge
                    :count="activity.pending_count"
                    :number-style="{ backgroundColor: '#52c41a', marginLeft: '8px' }"
                />
              </a-button>

              <!-- 查看发放列表（所有状态） -->
              <a-button
                  type="default"
                  block
                  @click.stop="showTicketList(activity)"
              >
                <template #icon>
                  <unordered-list-outlined/>
                </template>
                {{ trans.viewTicketList || '查看發放列表' }}
              </a-button>
            </a-space>

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

    <!-- ⭐ 选择历史活动模态框 -->
    <a-modal
        v-model:visible="historyModalVisible"
        :title="trans.selectHistoryActivity || '選擇歷史活動'"
        width="800px"
        :footer="null"
    >
      <a-spin :spinning="historyLoading">
        <a-list
            :data-source="historyActivities"
            :grid="{ gutter: 16, column: 2 }"
        >
          <template #renderItem="{ item }">
            <a-list-item>
              <a-card
                  hoverable
                  @click="selectHistoryActivity(item)"
                  style="cursor: pointer;"
              >
                <!-- 封面图 -->
                <div v-if="item.cover_image" style="margin-bottom: 12px;">
                  <img
                      :src="item.cover_image"
                      style="width: 100%; height: 120px; object-fit: cover; border-radius: 4px;"
                      alt="活动封面"
                  />
                </div>
                <a-card-meta>
                  <template #title>
                    <a-tooltip :title="item.name">
                      <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        {{ item.name }}
                      </div>
                    </a-tooltip>
                  </template>
                  <template #description>
                    <div style="font-size: 12px; color: #999;">
                      {{ item.created_at }}
                    </div>
                  </template>
                </a-card-meta>
              </a-card>
            </a-list-item>
          </template>
        </a-list>
        <a-empty v-if="!historyLoading && historyActivities.length === 0" :description="trans.noHistoryActivities || '暫無歷史活動'"/>
      </a-spin>
    </a-modal>

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
          <a-upload
              :before-upload="handleBeforeUpload"
              :custom-request="handleCoverUpload"
              :show-upload-list="false"
              accept="image/jpeg,image/png,image/jpg"
          >
            <a-button type="primary">
              <upload-outlined/>
              {{ trans.selectImage || '選擇圖片' }}
            </a-button>
          </a-upload>
          <div style="margin-top: 4px; margin-bottom: 8px; color: #999; font-size: 12px;">
            建议尺寸：750x400px，支持jpg、png格式，文件大小不超过2MB
          </div>
          <a-spin :spinning="uploading" style="display: block; margin-top: 8px;">
            <img
                v-if="formData.cover_image"
                :src="formData.cover_image"
                style="max-width: 300px; border: 1px solid #d9d9d9; border-radius: 4px;"
                alt="封面预览"
            />
          </a-spin>
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
              <a-col :span="8">
                <a-form-item :label="trans.levelRank">
                  <a-select v-model:value="level.level_rank" :placeholder="trans.selectLevelRank" @change="handleLevelRankChange(index)">
                    <a-select-option v-for="i in 10" :key="i" :value="i" :disabled="isLevelRankSelected(i, index)">
                      {{ getLevelName(i) }}
                    </a-select-option>
                  </a-select>
                </a-form-item>
              </a-col>
              <a-col :span="8">
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
              <a-col :span="8">
                <a-form-item label="奖品数量">
                  <a-input-number
                      v-model:value="level.prize_count"
                      :min="0"
                      :precision="0"
                      style="width: 100%;"
                      placeholder="0"
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
          <a-descriptions-item :label="trans.maxTicketNo">
            <a-tag color="green" style="font-family: monospace; font-size: 16px;">
              {{ currentActivity.max_ticket_no || '000000' }}
            </a-tag>
            <span style="margin-left: 8px; color: #999; font-size: 12px;">
              (抽奖时放球的最大号码)
            </span>
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

    <!-- 录入中奖抽屉 -->
    <a-drawer
        v-model:visible="recordVisible"
        :title="trans.modalRecordWinTitle"
        width="680px"
        :body-style="{ paddingBottom: '80px' }"
    >
      <div v-for="(prizeLevel, index) in recordPrizeLevels" :key="prizeLevel.id">
        <a-card size="small" :title="`${prizeLevel.level_name} - ${prizeLevel.prize_amount}元`" style="margin-bottom: 16px;">
          <div v-for="(ticket, ticketIndex) in prizeLevel.tickets" :key="ticketIndex" style="margin-bottom: 8px;">
            <a-space style="width: 100%; align-items: center;">
              <span style="min-width: 80px; color: #666;">输入券号:</span>
              <a-input
                  v-model:value="ticket.ticket_no"
                  style="width: 200px;"
                  placeholder="输入数字，如: 12 或 000012"
                  @blur="formatTicketNo(ticket)"
                  @keyup.enter="formatTicketNo(ticket)"
              />
              <a-button
                  type="text"
                  danger
                  size="small"
                  @click="removeTicketInput(index, ticketIndex)"
                  v-if="prizeLevel.tickets.length > 1"
              >
                <delete-outlined/>
              </a-button>
            </a-space>
          </div>
          <a-button type="dashed" block @click="addTicketInput(index)" style="margin-top: 8px;">
            <plus-outlined/>
            添加券号
          </a-button>
        </a-card>
      </div>

      <template #footer>
        <a-space>
          <a-button @click="handleRecordClose">取消</a-button>
          <a-button type="primary" @click="submitWinRecord" :loading="recordSubmitting">提交</a-button>
        </a-space>
      </template>
    </a-drawer>

    <!-- 发放列表抽屉 -->
    <a-drawer
        v-model:visible="ticketListVisible"
        :title="trans.ticketListTitle || '摸獎券發放列表'"
        width="900px"
        :body-style="{ padding: '16px' }"
    >
      <!-- ⭐ 筛选表单 - 优化样式 -->
      <a-form layout="inline" style="margin-bottom: 16px;">
        <a-form-item>
          <a-input
              v-model:value="ticketFilter.ticket_no"
              placeholder="券号"
              allow-clear
              style="width: 150px;"
          />
        </a-form-item>
        <a-form-item>
          <a-input
              v-model:value="ticketFilter.player_uuid"
              placeholder="玩家UUID"
              allow-clear
              style="width: 180px;"
          />
        </a-form-item>
        <a-form-item>
          <a-range-picker
              v-model:value="ticketFilter.time_range"
              show-time
              format="YYYY-MM-DD HH:mm:ss"
              :placeholder="['开始时间', '结束时间']"
              style="width: 360px;"
          />
        </a-form-item>
        <a-form-item style="margin: 4px;">
          <a-space :size="8">
            <a-button type="primary" @click="handleTicketSearch">
              <template #icon>
                <search-outlined/>
              </template>
              {{ trans.search || '搜索' }}
            </a-button>
            <a-button @click="handleTicketReset">
              <template #icon>
                <reload-outlined/>
              </template>
              {{ trans.reset || '重置' }}
            </a-button>
          </a-space>
        </a-form-item>
      </a-form>

      <!-- 表格 -->
      <a-table
          :columns="ticketColumns"
          :data-source="ticketList"
          :loading="ticketLoading"
          :pagination="ticketPagination"
          @change="handleTicketTableChange"
          size="small"
          :scroll="{ x: 900 }"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'player_uuid'">
            <a-typography-text copyable>{{ record.player_uuid }}</a-typography-text>
          </template>
          <template v-if="column.key === 'status'">
            <a-tag :color="record.status_color">
              {{ record.status }}
            </a-tag>
          </template>
          <!-- ✅ 后端已返回转换好的文本，直接显示，不需要再转换 -->
        </template>
      </a-table>
    </a-drawer>

    <!-- 直播流名称设置 Modal -->
    <a-modal
        v-model:visible="liveModalVisible"
        title="设置直播流名称"
        @ok="submitLiveUrl"
        @cancel="handleLiveModalClose"
    >
      <a-form layout="vertical">
        <a-alert
            message="💡 只需填写流名称，系统会自动生成腾讯云直播地址"
            show-icon
            style="margin-bottom: 16px;"
            type="info"
        />
        <a-form-item label="直播流名称">
          <a-input
              v-model:value="liveUrlInput"
              placeholder="例如：mojiangjuan"
              allow-clear
          >
            <template #prefix>
              <video-camera-outlined style="color: #999;"/>
            </template>
          </a-input>
          <div style="margin-top: 8px; color: #999; font-size: 12px;">
            建议使用英文、数字、下划线，此名称需与OBS推流配置一致
          </div>
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- ⭐ 直播预览 Modal -->
    <a-modal
        v-model:visible="livePreviewVisible"
        :title="'直播预览 - ' + (currentActivity?.name || '摸奖券活动')"
        width="90%"
        :footer="null"
        @cancel="closeLivePreview"
        :bodyStyle="{ padding: '0', background: '#000' }"
        :destroyOnClose="true"
    >
      <div v-if="livePreviewUrl" style="position: relative; background: #000;">
        <!-- 直播播放器iframe（使用TCPlayer v5）-->
        <iframe
            :src="getLivePlayerUrl()"
            style="width: 100%; height: 70vh; border: none; display: block;"
            frameborder="0"
            allowfullscreen
        ></iframe>

        <!-- 直播信息栏 -->
        <div style="padding: 16px; background: #1f1f1f; color: #fff;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <div style="font-size: 16px; font-weight: bold; margin-bottom: 8px;">
                {{ currentActivity?.name }}
              </div>
              <div style="font-size: 12px; color: #999;">
                直播地址：{{ livePreviewUrl }}
              </div>
            </div>
            <div>
              <a-button
                  type="primary"
                  size="small"
                  @click="copyLiveUrl"
                  style="margin-right: 8px;"
              >
                <template #icon>
                  <copy-outlined/>
                </template>
                复制地址
              </a-button>
              <a-button
                  size="small"
                  @click="openInNewTab"
              >
                <template #icon>
                  <link-outlined/>
                </template>
                新窗口打开
              </a-button>
            </div>
          </div>
        </div>

        <!-- 协议提示 -->
        <div v-if="livePreviewUrl.startsWith('rtmp://')" style="padding: 12px; background: #fff3cd; border-top: 1px solid #ffc107;">
          <div style="display: flex; align-items: flex-start; color: #856404;">
            <warning-outlined style="font-size: 18px; margin-right: 8px; margin-top: 2px;"/>
            <div>
              <div style="font-weight: bold; margin-bottom: 4px;">RTMP 协议播放提示</div>
              <div style="font-size: 12px; line-height: 1.6;">
                RTMP协议在现代浏览器中可能无法播放（需要Flash支持）。<br/>
                建议联系腾讯云客服获取 <strong>HLS播放地址（.m3u8格式）</strong> 或 <strong>HTTP-FLV格式</strong>，以获得更好的兼容性。<br/>
                <a href="https://cloud.tencent.com/document/product/267/32733" target="_blank" style="color: #1890ff;">查看腾讯云直播播放文档 →</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <a-empty v-else description="未获取到直播地址" />
    </a-modal>

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
      recordVisible: false,
      ticketListVisible: false,
      liveModalVisible: false,
      liveUrlInput: '',
      liveModalMode: 'update', // 'update' 或 'startDrawing'
      livePreviewVisible: false, // ⭐ 直播预览Modal
      livePreviewUrl: '', // ⭐ 当前预览的直播地址
      livePlayerConfig: null, // ⭐ 播放器配置（包含 License 信息）
      formMode: 'create',
      historyModalVisible: false,  // ⭐ 历史活动选择Modal
      historyActivities: [],        // ⭐ 历史活动列表
      historyLoading: false,        // ⭐ 历史活动加载状态
      currentActivity: null,
      submitting: false,
      recordSubmitting: false,
      uploading: false,
      recordPrizeLevels: [],
      ticketList: [],
      ticketLoading: false,
      ticketPagination: {
        current: 1,
        pageSize: 20,
        total: 0,
        showSizeChanger: true,
        showTotal: (total) => `共 ${total} 条`,
      },
      formData: {
        name: '',
        description: '',
        cover_image: '',
        start_time: null,
        end_time: null,
        vip_configs: [],
        prize_levels: []
      },
      recordData: {
        activity_id: null
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
      recordRules: {
        player_account: [
          {required: true, message: this.trans?.playerAccountPlaceholder || '请输入玩家账号', trigger: 'blur'}
        ],
        prize_level_id: [
          {required: true, message: this.trans?.prizeLevelPlaceholder || '请选择中奖等级', trigger: 'change'}
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
      ticketFilter: {
        ticket_no: '',
        player_uuid: '',
        time_range: null,
      },
      ticketColumns: [
        {title: '券号', key: 'ticket_no', dataIndex: 'ticket_no', width: 120, ellipsis: true},
        {title: '玩家', key: 'player_name', dataIndex: 'player_name', width: 100},
        {title: '玩家UUID', key: 'player_uuid', dataIndex: 'player_uuid', width: 150}, // ⭐ 新增
        {title: '来源', key: 'source', dataIndex: 'source', width: 100},
        {title: '状态', key: 'status', dataIndex: 'status', width: 90},
        {title: '发放时间', key: 'created_at', dataIndex: 'created_at', width: 160},
        {title: '使用时间', key: 'used_at', dataIndex: 'used_at', width: 160},
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
        {label: this.trans.pendingDraw, value: 5},  // ⭐ 新增：待开奖
        {label: this.trans.drawing, value: 6},      // ⭐ 新增：开奖中
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

    // ⭐ 处理创建菜单点击
    handleCreateMenuClick({key}) {
      if (key === 'new') {
        // 从零创建
        this.showCreateForm();
      } else if (key === 'copy') {
        // 从历史活动创建
        this.showHistoryActivityModal();
      }
    },

    // ⭐ 显示历史活动选择Modal
    async showHistoryActivityModal() {
      this.historyModalVisible = true;
      this.historyLoading = true;
      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getHistoryActivities',
          method: 'post'
        });
        if (res.code === 200) {
          this.historyActivities = res.data.activities || [];
        } else {
          this.$message.error(res.message || '获取历史活动失败');
        }
      } catch (error) {
        this.$message.error('获取历史活动失败');
        console.error(error);
      } finally {
        this.historyLoading = false;
      }
    },

    // ⭐ 选择历史活动
    async selectHistoryActivity(activity) {
      this.historyModalVisible = false;
      this.formMode = 'create';

      // 获取活动详情（包含奖品配置和VIP配置）
      const detail = await this.getActivityDetail(activity.id);

      if (!detail) {
        this.$message.error('获取活动详情失败');
        return;
      }

      // 填充表单数据
      this.formData = {
        name: activity.name + ' (副本)',
        description: activity.description || '',
        cover_image: activity.cover_image || '',
        start_time: null,  // 不复制时间，让用户设置
        end_time: null,
        vip_configs: detail.vip_configs || this.vip_levels.map(vipLevel => ({
          vip_level_id: vipLevel.id,
          vip_level_name: vipLevel.name,
          bet_amount_required: 0,
          ticket_count: 1
        })),
        prize_levels: detail.prize_levels || []
      };

      // 显示创建表单
      this.formVisible = true;
      this.$message.success('已加载历史活动数据，请设置活动时间并提交');
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

    // 上传前验证
    handleBeforeUpload(file) {
      const isImage = file.type === 'image/jpeg' || file.type === 'image/png' || file.type === 'image/jpg';
      if (!isImage) {
        this.$message.error('只能上传 JPG/PNG 格式的图片！');
        return false;
      }
      const isLt2M = file.size / 1024 / 1024 < 2;
      if (!isLt2M) {
        this.$message.error('图片大小不能超过 2MB！');
        return false;
      }
      return true;
    },

    // 处理封面图片上传
    async handleCoverUpload({file}) {
      this.uploading = true;
      const formData = new FormData();
      formData.append('file', file);

      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/uploadCover',
          method: 'post',
          data: formData,
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });

        if (res.code === 200 && res.data && res.data.url) {
          this.formData.cover_image = res.data.url;
          this.$message.success('图片上传成功');
        } else {
          this.$message.error(res.message || '图片上传失败');
        }
      } catch (error) {
        console.error('上传失败:', error);
        this.$message.error('图片上传失败');
      } finally {
        this.uploading = false;
      }
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
        case 'startDrawing':
          this.startDrawing(activity);
          break;
        case 'record':
          this.showRecordModal(activity);
          break;
        case 'distribute':
          this.showDistributeForm(activity);
          break;
        case 'stopDrawing':
          this.stopDrawing(activity);
          break;
        case 'live':
          this.showLiveModal(activity);
          break;
        case 'previewLive':
          this.previewLive(activity);
          break;
        case 'startLive':
          this.startLiveStream(activity);
          break;
        case 'endLive':
          this.endLiveStream(activity);
          break;
        case 'close':
          this.closeActivity(activity);
          break;
      }
    },

    // 显示录入中奖抽屉
    async showRecordModal(activity) {
      try {
        // 获取活动详情和奖品等级
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getActivityDetail',
          method: 'post',
          data: {id: activity.id}
        });

        if (res.code === 200 && res.data) {
          const prizeLevels = res.data.prize_levels || [];

          if (prizeLevels.length === 0) {
            this.$message.warning('该活动尚未配置奖品等级');
            return;
          }

          // 为每个奖品等级初始化券号输入框
          this.recordPrizeLevels = prizeLevels.map(level => {
            // 根据奖品数量生成输入框，如果数量为0则默认1个
            const ticketCount = level.prize_count > 0 ? level.prize_count : 1;
            const tickets = [];
            for (let i = 0; i < ticketCount; i++) {
              tickets.push({ ticket_no: null });
            }
            return {
              ...level,
              tickets: tickets
            };
          });

          this.recordData = {
            activity_id: activity.id
          };

          this.recordVisible = true;
        } else {
          this.$message.error('获取活动详情失败');
        }
      } catch (error) {
        this.$message.error('获取活动详情失败');
        console.error(error);
      }
    },

    // 添加券号输入框
    addTicketInput(prizeLevelIndex) {
      this.recordPrizeLevels[prizeLevelIndex].tickets.push({ ticket_no: null });
    },

    // 移除券号输入框
    removeTicketInput(prizeLevelIndex, ticketIndex) {
      this.recordPrizeLevels[prizeLevelIndex].tickets.splice(ticketIndex, 1);
    },

    // 格式化券号：前端验证并自动补0
    formatTicketNo(ticket) {
      if (!ticket.ticket_no) {
        return;
      }

      // 去除首尾空格
      let value = String(ticket.ticket_no).trim();

      // 验证：只能包含数字
      if (!/^\d+$/.test(value)) {
        this.$message.error('券号只能包含数字，请重新输入');
        ticket.ticket_no = '';
        return;
      }

      // 验证：不能超过6位
      if (value.length > 6) {
        this.$message.error('券号不能超过6位数字');
        ticket.ticket_no = '';
        return;
      }

      // 自动补0到6位
      ticket.ticket_no = value.padStart(6, '0');
    },

    // 提交中奖记录
    async submitWinRecord() {
      // 收集所有券号并验证
      const records = [];
      for (const prizeLevel of this.recordPrizeLevels) {
        for (const ticket of prizeLevel.tickets) {
          if (ticket.ticket_no) {
            // 二次验证：确保券号格式正确
            const ticketNo = String(ticket.ticket_no).trim();
            if (!/^\d{1,6}$/.test(ticketNo)) {
              this.$message.error(`券号 "${ticket.ticket_no}" 格式错误，请检查`);
              return;
            }

            records.push({
              prize_level_id: prizeLevel.id,
              ticket_no: ticketNo.padStart(6, '0')  // 确保补0到6位
            });
          }
        }
      }

      if (records.length === 0) {
        this.$message.warning('请至少输入一个券号');
        return;
      }

      this.recordSubmitting = true;
      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/recordWinByTickets',
          method: 'post',
          data: {
            activity_id: this.recordData.activity_id,
            records: records
          }
        });

        if (res.code === 200) {
          this.$message.success(`成功录入 ${res.data.success_count} 条中奖记录`);
          this.recordVisible = false;
          this.fetchActivities();
        } else {
          this.$message.error(res.message || res.msg || '录入失败');
        }
      } catch (error) {
        this.$message.error('录入失败');
        console.error(error);
      } finally {
        this.recordSubmitting = false;
      }
    },

    // 关闭录入抽屉
    handleRecordClose() {
      this.recordVisible = false;  // ✅ 关闭抽屉
      this.recordData = {
        activity_id: null
      };
      this.recordPrizeLevels = [];
    },

    // 显示直播地址弹窗
    showLiveModal(activity) {
      this.currentActivity = activity;
      this.liveUrlInput = activity.live_url || '';
      this.liveModalMode = 'update';
      this.liveModalVisible = true;
    },

    // 关闭直播地址弹窗
    handleLiveModalClose() {
      this.liveModalVisible = false;
      this.liveUrlInput = '';
      this.currentActivity = null;
    },

    // ⭐ 预览直播
    async previewLive(activity) {
      if (!activity.live_url) {
        this.$message.warning('该活动尚未设置直播流名称');
        return;
      }

      // live_url存储的是流名称，通过API获取播放器配置
      try {
        const loading = this.$message.loading('正在生成直播地址...', 0);

        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getLivePlayerConfig',
          method: 'post',
          data: {
            stream_name: activity.live_url // 流名称
          }
        });

        loading();

        if (res.code === 0 || res.code === 200) {
          // 使用返回的播放地址
          this.currentActivity = activity;
          this.livePreviewUrl = res.data.play_url; // FLV地址
          this.livePlayerConfig = res.data.player_config; // ⭐ 保存播放器配置（包含 License）
          this.livePreviewVisible = true;

        } else {
          this.$message.error(res.message || '生成直播地址失败');
        }
      } catch (error) {
        console.error('生成直播地址失败:', error);
        this.$message.error('生成直播地址失败');
      }
    },

    // ⭐ 关闭直播预览
    closeLivePreview() {
      this.livePreviewVisible = false;
      this.livePreviewUrl = '';
      this.livePlayerConfig = null; // ⭐ 清空播放器配置
      this.currentActivity = null;
    },

    // ⭐ 开始直播
    async startLiveStream(activity) {
      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/startLive',
          method: 'post',
          data: {
            activity_id: activity.id
          }
        });

        if (res.code === 200) {
          this.$message.success(res.data.message || '直播已开始，已通知所有玩家');
          this.fetchActivities(); // 刷新活动列表
        } else {
          this.$message.error(res.message || res.msg || '开始直播失败');
        }
      } catch (error) {
        console.error('开始直播失败:', error);
        this.$message.error('开始直播失败');
      }
    },

    // ⭐ 结束直播
    async endLiveStream(activity) {
      this.$confirm({
        title: '结束直播',
        content: '确认结束直播吗？结束后玩家将无法继续观看。',
        okText: '确认结束',
        cancelText: '取消',
        onOk: async () => {
          try {
            const loading = this.$message.loading('正在结束直播...', 0);
            const res = await this.$request({
              url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/endLive',
              method: 'post',
              data: {
                activity_id: activity.id
              }
            });

            loading();

            if (res.code === 200) {
              this.$message.success(res.data.message || '直播已结束');
              this.fetchActivities(); // 刷新活动列表
            } else {
              this.$message.error(res.message || res.msg || '结束直播失败');
            }
          } catch (error) {
            console.error('结束直播失败:', error);
            this.$message.error('结束直播失败');
          }
        }
      });
    },

    // ⭐ 复制直播地址
    async copyLiveUrl() {
      try {
        await navigator.clipboard.writeText(this.livePreviewUrl);
        this.$message.success('直播地址已复制到剪贴板');
      } catch (err) {
        // 兼容旧浏览器
        const input = document.createElement('input');
        input.value = this.livePreviewUrl;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        this.$message.success('直播地址已复制到剪贴板');
      }
    },

    // ⭐ 在新窗口打开直播
    openInNewTab() {
      const url = this.getLivePlayerUrl();
      window.open(url, '_blank', 'width=1280,height=720');
    },

    // ⭐ 获取直播播放器URL（使用TCPlayer v5）
    getLivePlayerUrl() {
      let url = `/lottery-live-player.html?url=${encodeURIComponent(this.livePreviewUrl)}`;

      // ⭐ 添加 License 参数（如果有）
      if (this.livePlayerConfig) {
        const config = this.livePlayerConfig;

        // 支持多种字段名（licenceUrl, licenseUrl, license）
        const licenseUrl = config.licenceUrl || config.licenseUrl || config.license;
        const licenseKey = config.licenceKey || config.licenseKey;

        if (licenseUrl) {
          url += `&licenseUrl=${encodeURIComponent(licenseUrl)}`;
        }
        if (licenseKey) {
          url += `&licenseKey=${encodeURIComponent(licenseKey)}`;
        }
      }

      return url;
    },

    // 提交直播流名称
    async submitLiveUrl() {
      const streamName = this.liveUrlInput.trim();

      if (!streamName) {
        this.$message.error('请输入直播流名称');
        return;
      }

      // 验证流名称格式（只允许英文、数字、下划线）
      if (!/^[a-zA-Z0-9_]+$/.test(streamName)) {
        this.$message.error('流名称只能包含英文、数字和下划线');
        return;
      }

      if (streamName.length > 50) {
        this.$message.error('流名称不能超过50个字符');
        return;
      }

      try {
        let url, successMsg;

        if (this.liveModalMode === 'startDrawing') {
          // ⭐ 开始开奖模式
          url = 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/startDrawing';
          successMsg = '开奖已开始';
        } else {
          // 普通更新直播地址模式
          url = 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/updateLiveUrl';
          successMsg = this.trans.liveUrlUpdated || '直播地址设置成功';
        }

        const res = await this.$request({
          url: url,
          method: 'post',
          data: {
            id: this.currentActivity.id,
            live_url: streamName // 存储流名称，不是完整URL
          }
        });

        if (res.code === 200) {
          this.$message.success(successMsg);
          this.liveModalVisible = false;
          this.liveUrlInput = '';
          this.liveModalMode = 'update';
          this.currentActivity = null;
          this.fetchActivities();
        } else {
          this.$message.error(res.message || res.msg || '操作失败');
        }
      } catch (error) {
        console.error('操作失败:', error);
        this.$message.error('操作失败');
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

    // 点击卡片查看详情
    viewActivityDetail(activity) {
      this.showDetail(activity);
    },

    // ⭐ 批量发放该活动所有已录入未发放的奖励
    showDistributeForm(activity) {
      this.$confirm({
        title: this.trans.distributeAllPending || '发放奖励',
        content: this.trans.confirm?.distributeAllPending || '确认发放该活动所有已录入但未发放的奖励？\n此操作将批量发放所有待发放记录,请谨慎操作。',
        okText: this.trans.confirmDistribute || '确认发放',
        cancelText: this.trans.cancel || '取消',
        onOk: async () => {
          try {
            const loading = this.$message.loading('正在批量发放奖励...', 0);
            const res = await this.$request({
              url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/batchDistributeActivity',
              method: 'post',
              data: {
                activity_id: activity.id
              }
            });

            loading();

            if (res.code === 200) {
              // 显示详细结果
              if (res.data && res.data.fail_count > 0) {
                this.$warning({
                  title: '批量发放完成',
                  content: res.data.message || res.message,
                  okText: '知道了'
                });
              } else {
                this.$message.success(res.message || '批量发放成功');
              }
              this.fetchActivities();
            } else {
              this.$message.error(res.message || res.msg || '批量发放失败');
            }
          } catch (error) {
            this.$message.error('批量发放失败');
            console.error(error);
          }
        }
      });
    },

    // ⭐ 获取活动详情（通用方法）
    async getActivityDetail(activityId) {
      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getActivityDetail',
          method: 'post',
          data: {id: activityId}
        });

        if (res.code === 200) {
          return res.data;
        } else {
          this.$message.error(res.message || '获取活动详情失败');
          return null;
        }
      } catch (error) {
        console.error('获取活动详情失败:', error);
        return null;
      }
    },

    // 编辑活动
    async editActivity(activity) {
      const data = await this.getActivityDetail(activity.id);
      if (!data) {
        this.$message.error('获取活动详情失败');
        return;
      }

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

    // ⭐ 开始开奖（手动触发）
    startDrawing(activity) {
      // 设置为开奖模式，并弹出直播地址输入框
      this.currentActivity = activity;
      this.liveUrlInput = activity.live_url || '';
      this.liveModalMode = 'startDrawing';
      this.liveModalVisible = true;
    },

    // ⭐ 停止开奖（手动触发）- 必须二次确认
    async stopDrawing(activity, confirmed = false) {
      let res;

      try {
        res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/stopDrawing',
          method: 'post',
          data: {
            id: activity.id,
            confirmed: confirmed
          }
        });
      } catch (error) {
        // ⭐ ExAdmin 的 $request 会将非200响应作为错误抛出
        // 我们需要从错误中提取响应数据
        console.log('Caught error:', error);
        console.log('error.response:', error.response);
        console.log('error.data:', error.data);

        // 尝试多种方式获取响应数据
        if (error.response?.data) {
          res = error.response.data;
        } else if (error.data) {
          res = error.data;
        } else if (typeof error === 'object' && error.code) {
          res = error;
        } else {
          // 真正的网络错误
          this.$message.error('网络错误：' + (error.message || '未知错误'));
          return;
        }
      }

      // ⭐ 详细调试日志
      console.log('stopDrawing response:', res);
      console.log('res.code:', res.code, 'type:', typeof res.code);
      console.log('res.message:', res.message);
      console.log('res.data:', res.data);
      console.log('res.data?.need_confirm:', res.data?.need_confirm);

      // ⭐ 检查是否需要二次确认（使用 need_confirm 标记而非 40001）
      if (res.code === 200 && res.data?.need_confirm) {
        // ⭐ 后端要求二次确认，显示详细统计信息
        const data = res.data;

        // ⭐ 将 \n 字符串替换为真正的换行符
        const confirmMessage = (data.confirm_message || '').replace(/\\n/g, '\n');

        // 构建确认内容（保留换行符）
        const contentLines = confirmMessage.split('\n');

        // 如果有录入券号，显示券号列表（最多显示10个）
        let ticketList = '';
        if (data.ticket_nos && data.ticket_nos.length > 0) {
          const displayTickets = data.ticket_nos.slice(0, 10);
          ticketList = '\n\n🎫 已录入券号：\n' + displayTickets.join(', ');
          if (data.ticket_nos.length > 10) {
            ticketList += ` ...等${data.ticket_nos.length}个`;
          }
        }

        this.$confirm({
          title: data.win_record_count === 0 ? '⚠️ 警告：未录入中奖券号' : '确认停止开奖',
          content: contentLines.join('\n') + ticketList,
          okText: '确认停止开奖',
          okType: 'danger',
          cancelText: data.win_record_count === 0 ? '取消，先录入中奖' : '取消',
          width: 520,
          onOk: () => {
            // 用户确认后，带上 confirmed=true 重新调用
            this.stopDrawing(activity, true);
          }
        });
      } else if (res.code === 200) {
        // ⭐ 真正的成功（已确认并停止开奖）
        this.$message.success('开奖已停止');
        this.fetchActivities();
      } else {
        this.$message.error(res.message || res.msg || '停止开奖失败');
      }
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
        prize_amount: 0,
        prize_count: 0
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
      if (activity.status === 0) classes.push('card-not-started');
      if (activity.status === 1) classes.push('card-ongoing');
      if (activity.status === 5) classes.push('card-pending-draw');  // ⭐ 新增：待开奖样式
      if (activity.status === 6) classes.push('card-drawing');       // ⭐ 新增：开奖中样式
      if (activity.status === 2 || activity.status === 3) classes.push('card-ended');
      return classes.join(' ');
    },

    getStatusColor(status) {
      const colors = {
        0: 'blue',      // 未开始
        1: 'green',     // 进行中
        5: 'orange',    // 待开奖 ⭐ 新增
        6: 'purple',    // 开奖中
        2: 'default',   // 已结束
        3: 'red'        // 已关闭
      };
      return colors[status] || 'default';
    },

    getStatusText(status) {
      const texts = {
        0: this.trans.notStarted,
        1: this.trans.ongoing,
        5: this.trans.pendingDraw,  // ⭐ 新增：待开奖状态
        6: this.trans.drawing,      // ⭐ 修复：开奖中状态
        2: this.trans.ended,
        3: this.trans.closed
      };
      return texts[status] || this.trans.statusUnknown || '未知狀態';
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
    },

    // 显示发放列表
    async showTicketList(activity) {
      this.currentActivity = activity;
      this.ticketListVisible = true;
      this.ticketPagination.current = 1;

      // ⭐ 重置筛选条件
      this.ticketFilter = {
        ticket_no: '',
        player_uuid: '',
        time_range: null,
      };

      await this.fetchTicketList(activity.id);
    },

    // 获取发放列表
    async fetchTicketList(activityId, page = 1) {
      this.ticketLoading = true;
      try {
        // ⭐ 准备筛选参数
        const requestData = {
          activity_id: activityId,
          page: page,
          size: this.ticketPagination.pageSize,
          ticket_no: this.ticketFilter.ticket_no || undefined,
          player_uuid: this.ticketFilter.player_uuid || undefined,
        };

        // ⭐ 时间范围参数
        if (this.ticketFilter.time_range && this.ticketFilter.time_range.length === 2) {
          requestData.start_time = this.ticketFilter.time_range[0].format('YYYY-MM-DD HH:mm:ss');
          requestData.end_time = this.ticketFilter.time_range[1].format('YYYY-MM-DD HH:mm:ss');
        }

        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getTicketList',
          method: 'post',
          data: requestData
        });

        if (res.code === 200) {
          this.ticketList = res.data.list || [];
          this.ticketPagination.total = res.data.total || 0;
          this.ticketPagination.current = page;
        } else {
          this.$message.error(res.message || res.msg || '获取列表失败');
        }
      } catch (error) {
        this.$message.error('获取列表失败');
        console.error(error);
      } finally {
        this.ticketLoading = false;
      }
    },

    // ⭐ 搜索按钮点击
    handleTicketSearch() {
      this.ticketPagination.current = 1;
      this.fetchTicketList(this.currentActivity.id, 1);
    },

    // ⭐ 重置按钮点击
    handleTicketReset() {
      this.ticketFilter = {
        ticket_no: '',
        player_uuid: '',
        time_range: null,
      };
      this.ticketPagination.current = 1;
      this.fetchTicketList(this.currentActivity.id, 1);
    },

    // 表格分页变化
    handleTicketTableChange(pagination) {
      this.fetchTicketList(this.currentActivity.id, pagination.current);
    },

    // 获取券状态文本
    getTicketStatusText(status) {
      const statusMap = {
        0: '未使用',
        1: '已使用',
        2: '已过期'
      };
      return statusMap[status] || '未知';
    },

    // 获取券状态颜色
    getTicketStatusColor(status) {
      const colorMap = {
        0: 'green',
        1: 'default',
        2: 'red'
      };
      return colorMap[status] || 'default';
    },

    // 获取来源文本
    getSourceText(source) {
      const sourceMap = {
        'betting': '打码获得',
        'recharge': '充值赠送',
        'activity': '活动赠送',
        'manual': '手动发放'
      };
      return sourceMap[source] || '未知来源';
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

.card-not-started {
  border-left: 4px solid #1890ff;
}

.card-ongoing {
  border-left: 4px solid #52c41a;
}

.card-pending-draw {
  border-left: 4px solid #fa8c16; /* ⭐ 新增：待开奖 - 橙色 */
  background: linear-gradient(to right, #fff7e6 0%, #ffffff 10%);
}

.card-drawing {
  border-left: 4px solid #722ed1; /* ⭐ 新增：开奖中 - 紫色 */
  background: linear-gradient(to right, #f9f0ff 0%, #ffffff 10%);
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
