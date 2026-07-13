<template>
  <div class="lottery-ticket-container">
    <!-- 頂部操作欄 -->
    <div class="header-actions">
      <a-space>
        <!-- ⭐ 創建活動下拉選單 -->
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

    <!-- 載入狀態 -->
    <div v-if="loading && !activities.length" class="loading-container">
      <a-spin size="large"/>
      <div style="margin-top: 10px;">{{ trans.loading }}</div>
    </div>

    <!-- 空狀態 -->
    <a-empty v-else-if="!loading && !activities.length" :description="trans.noActivities">
      <a-button type="primary" @click="showCreateForm">{{ trans.createFirst }}</a-button>
    </a-empty>

    <!-- 活動面板列表 -->
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
              <!-- ⭐ 直播狀態標籤 -->
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
                  <!-- 查看詳情（所有狀態） -->
                  <a-menu-item key="view">
                    <eye-outlined/>
                    {{ trans.viewDetail }}
                  </a-menu-item>

                  <!-- 編輯（未開始） -->
                  <a-menu-item key="edit" v-if="activity.status === 0">
                    <edit-outlined/>
                    {{ trans.edit }}
                  </a-menu-item>

                  <!-- ⭐ 開始開獎（待開獎狀態） -->
                  <a-menu-item v-if="activity.status === 5" key="startDrawing">
                    <play-circle-outlined/>
                    {{ trans.startDrawing || '開始開獎' }}
                  </a-menu-item>

                  <!-- ⭐ 錄入中獎（僅開獎中狀態可操作，錄入即發放） -->
                  <a-menu-item v-if="activity.status === 6" key="record">
                    <trophy-outlined/>
                    {{ trans.recordWin }}
                  </a-menu-item>

                  <!-- 發放獎勵已移除：現在錄入中獎時自動發放 -->

                  <!-- ⭐ 停止開獎（開獎中） -->
                  <a-menu-item key="stopDrawing" v-if="activity.status === 6">
                    <check-circle-outlined/>
                    {{ trans.stopDrawing || '停止開獎' }}
                  </a-menu-item>

                  <!-- 新增/編輯直播地址（所有狀態） -->
                  <a-menu-item key="live">
                    <video-camera-outlined/>
                    {{ activity.live_url ? (trans.ui?.edit_live_url || '編輯直播地址') : trans.addLiveUrl }}
                  </a-menu-item>

                  <!-- 預覽直播（僅當有直播地址時） -->
                  <a-menu-item key="previewLive" v-if="activity.live_url">
                    <play-circle-outlined/>
                    {{ trans.previewLive || '預覽直播' }}
                  </a-menu-item>

                  <!-- ⭐ 開始直播（僅當有直播地址且未開播時） -->
                  <a-menu-item v-if="activity.live_url && activity.live_status === 0" key="startLive">
                    <play-circle-outlined style="color: #52c41a;"/>
                    {{ trans.startLive || '開始直播' }}
                  </a-menu-item>

                  <!-- ⭐ 結束直播（僅當直播中時） -->
                  <a-menu-item v-if="activity.live_status === 1" key="endLive">
                    <stop-outlined style="color: #ff4d4f;"/>
                    {{ trans.endLive || '結束直播' }}
                  </a-menu-item>

                  <!-- 關閉活動（進行中） -->
                  <a-menu-item key="close" danger v-if="activity.status === 1">
                    <stop-outlined/>
                    {{ trans.closeActivity }}
                  </a-menu-item>
                </a-menu>
              </template>
            </a-dropdown>
          </template>

          <div class="activity-content">
            <!-- 封面圖片 -->
            <div v-if="activity.cover_image" style="margin-bottom: 12px;">
              <img
                  :src="activity.cover_image"
                  style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px;"
                  :alt="trans.form?.cover_alt || '活動封面'"
              />
            </div>

            <!-- 活動描述 -->
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

            <!-- 統計資訊 -->
            <a-row :gutter="12">
              <a-col :span="8">
                <a-statistic
                    :title="trans.totalTickets || '總發放數量'"
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
                    :title="trans.maxTicketNo || '最大券號'"
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
                    :title="trans.pendingCount || '待發放'"
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

            <!-- 操作按鈕 -->
            <a-space direction="vertical" style="width: 100%; margin-top: 12px;">
              <!-- 未開始：編輯按鈕 -->
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

              <!-- ⭐ 發放獎勵按鈕已隱藏：錄入中獎時自動發放，不需要單獨發放按鈕 -->
              <!-- 如果有歷史待發放記錄，請使用下拉菜單中的"發放獎勵"選項 -->

              <!-- 查看發放列表（所有狀態） -->
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

              <!-- ⭐ 錄入中獎（所有狀態顯示，但未開始/已結束/已關閉時置灰） -->
              <a-button
                  type="primary"
                  block
                  :disabled="activity.status === 0 || activity.status === 2 || activity.status === 3"
                  @click.stop="showRecordModal(activity)"
              >
                <template #icon>
                  <edit-outlined/>
                </template>
                錄入中獎
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

    <!-- ⭐ 選擇歷史活動模態框 -->
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
                <!-- 封面圖 -->
                <div v-if="item.cover_image" style="margin-bottom: 12px;">
                  <img
                      :src="item.cover_image"
                      style="width: 100%; height: 120px; object-fit: cover; border-radius: 4px;"
                      :alt="trans.form?.cover_alt || '活動封面'"
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

    <!-- 創建/編輯活動抽屜 -->
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

        <a-form-item :label="trans.form?.cover_image || '活動封面圖片'">
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
            {{ trans.ui?.cover_hint || '建議尺寸：750x400px，支持jpg、png格式，文件大小不超過2MB' }}
          </div>
          <a-spin :spinning="uploading" style="display: block; margin-top: 8px;">
            <img
                v-if="formData.cover_image"
                :src="formData.cover_image"
                style="max-width: 300px; border: 1px solid #d9d9d9; border-radius: 4px;"
                :alt="trans.ui?.cover_preview_alt || '封面預覽'"
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

        <!-- VIP等級打碼量配置 -->
        <a-divider>VIP等級打碼量配置</a-divider>

        <a-alert
            :message="trans.form?.vip_config_hint || '為每個VIP等級配置達到指定打碼量後發放的摸獎券數量'"
            type="info"
            show-icon
            style="margin-bottom: 16px;"
        />

        <div v-if="formData.vip_configs && formData.vip_configs.length > 0">
          <div v-for="(config, index) in formData.vip_configs" :key="config.vip_level_id || index" class="vip-config-item">
            <a-row :gutter="12" align="middle">
              <a-col :span="8">
                <a-form-item :label="trans.form?.vip_level || 'VIP等級'" style="margin-bottom: 0;">
                  <a-tag color="blue" style="font-size: 14px; padding: 4px 12px;">
                    {{ config.vip_level_name || getVipLevelName(config.vip_level_id) }}
                  </a-tag>
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item :label="trans.form?.bet_amount_required || '所需打碼量'" style="margin-bottom: 0;">
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
                <a-form-item :label="trans.form?.ticket_count || '發放券數'" style="margin-bottom: 0;">
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
        <a-empty v-else :description="trans.ui?.no_vip_data_desc || '暫無VIP等級數據'" style="margin: 20px 0;"/>

        <!-- 獎品等級配置 -->
        <a-divider>{{ trans.prizeLevelConfig }}</a-divider>

        <a-alert
            :message="trans.form?.prize_config_hint || '配置獎品等級和獎勵金額(僅現金獎勵)'"
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
                <a-form-item :label="trans.form?.prize_amount_label || '獎勵金額'">
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
                <a-form-item :label="trans.form?.prize_count || '獎品數量'">
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

    <!-- 活動詳情抽屜 -->
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
              (抽獎時放球的最大號碼)
            </span>
          </a-descriptions-item>
          <a-descriptions-item :label="trans.usageRate">
            {{ getUsageRate(currentActivity) }}%
          </a-descriptions-item>
        </a-descriptions>

        <!-- 獎品等級列表 -->
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

        <!-- VIP等級配置列表 -->
        <a-divider>VIP等級配置</a-divider>
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
        <a-empty v-else description="未配置VIP等級" />
      </template>
    </a-drawer>

    <!-- 錄入中獎抽屜 - 单个录入模式 -->
    <a-drawer
        v-model:visible="recordVisible"
        :title="trans.modalRecordWinTitle || '錄入中獎記錄'"
        width="600px"
        :body-style="{ paddingBottom: '80px' }"
    >
      <!-- ⭐ 活动信息展示 -->
      <a-alert
          v-if="currentActivityInfo"
          type="info"
          show-icon
          style="margin-bottom: 16px;"
      >
        <template #message>
          <div style="font-weight: bold; font-size: 14px;">{{ currentActivityInfo.name }}</div>
        </template>
        <template #description>
          <div style="margin-top: 8px;">
            <div style="margin-bottom: 4px;">
              <span style="color: #666;">活動時間：</span>
              <span>{{ currentActivityInfo.start_time }} ~ {{ currentActivityInfo.end_time }}</span>
            </div>
            <div>
              <span style="color: #666;">當前最大券號：</span>
              <a-tag color="orange" style="font-size: 13px;">{{ currentActivityInfo.max_ticket_no || '尚未派發' }}</a-tag>
            </div>
          </div>
        </template>
      </a-alert>

      <a-form layout="vertical">
        <!-- 选择奖品等级 -->
        <a-form-item label="選擇獎品等級" required>
          <a-select
              v-model:value="singleRecord.prize_level_id"
              placeholder="請選擇獎品等級"
              style="width: 100%;"
              @change="handlePrizeLevelChange"
          >
            <a-select-option
                v-for="level in recordPrizeLevels"
                :key="level.id"
                :value="level.id"
            >
              {{ level.level_name }} - {{ level.prize_amount }}元
            </a-select-option>
          </a-select>
        </a-form-item>

        <!-- 输入券号 -->
        <a-form-item label="中獎券號" required>
          <a-input
              v-model:value="singleRecord.ticket_no"
              placeholder="輸入數字，如: 12 或 000012"
              :loading="singleRecord.loading"
              @blur="querySinglePlayerByTicketNo"
              @keyup.enter="querySinglePlayerByTicketNo"
              @input="clearSinglePlayerInfo"
              style="width: 100%;"
          />
          <div style="margin-top: 4px; color: #999; font-size: 12px;">
            輸入後按Enter或點擊其他地方自動查詢玩家信息
          </div>
        </a-form-item>

        <!-- 玩家信息展示 -->
        <a-form-item v-if="singleRecord.player_info" label="中獎玩家">
          <div style="padding: 12px; background: #f0f5ff; border-radius: 4px; border: 1px solid #d6e4ff;">
            <a-descriptions :column="1" size="small">
              <a-descriptions-item label="玩家UUID">
                <a-tag color="blue">
                  <user-outlined /> {{ singleRecord.player_info.player_uuid }}
                </a-tag>
              </a-descriptions-item>
              <a-descriptions-item label="玩家名稱">
                <a-tag color="green">
                  <smile-outlined /> {{ singleRecord.player_info.player_name }}
                </a-tag>
              </a-descriptions-item>
              <a-descriptions-item v-if="singleRecord.player_info.player_phone" label="手機號">
                <a-tag color="orange">
                  <phone-outlined /> {{ singleRecord.player_info.player_phone }}
                </a-tag>
              </a-descriptions-item>
            </a-descriptions>
          </div>
        </a-form-item>

        <!-- 错误提示 -->
        <a-alert
            v-if="singleRecord.error"
            :message="singleRecord.error"
            type="error"
            closable
            @close="singleRecord.error = null"
            style="margin-bottom: 16px;"
        />

        <!-- 提示信息 -->
        <a-alert
            message="溫馨提示"
            description="錄入後將立即自動發放獎勵到玩家賬戶，請仔細核對券號和玩家信息。"
            type="info"
            show-icon
        />
      </a-form>

      <template #footer>
        <a-space>
          <a-button @click="handleRecordClose">取消</a-button>
          <a-button
              type="primary"
              @click="submitSingleWinRecord"
              :loading="recordSubmitting"
              :disabled="!singleRecord.ticket_no || !singleRecord.prize_level_id || !singleRecord.player_info"
          >
            確認錄入並發放
          </a-button>
        </a-space>
      </template>
    </a-drawer>

    <!-- 發放列表抽屜 -->
    <a-drawer
        v-model:visible="ticketListVisible"
        :title="trans.ticketListTitle || '摸獎券發放列表'"
        width="900px"
        :body-style="{ padding: '16px' }"
    >
      <!-- ⭐ 篩選表單 - 優化樣式 -->
      <a-form layout="inline" style="margin-bottom: 16px;">
        <a-form-item>
          <a-input
              v-model:value="ticketFilter.ticket_no"
              placeholder="券號"
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
              :placeholder="['開始時間', '結束時間']"
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

    <!-- 直播流名稱設定 Modal -->
    <a-modal
        v-model:visible="liveModalVisible"
        :title="trans.ui?.set_live_stream_title || '設置直播流名稱'"
        @ok="submitLiveUrl"
        @cancel="handleLiveModalClose"
    >
      <a-form layout="vertical">
        <a-alert
            :message="trans.ui?.live_stream_hint || '💡 只需填寫流名稱，系統會自動生成騰訊雲直播地址'"
            show-icon
            style="margin-bottom: 16px;"
            type="info"
        />
        <a-form-item :label="trans.ui?.live_stream_label || '直播流名稱'">
          <a-input
              v-model:value="liveUrlInput"
              :placeholder="trans.ui?.live_stream_placeholder || '例如：mojiangjuan'"
              allow-clear
          >
            <template #prefix>
              <video-camera-outlined style="color: #999;"/>
            </template>
          </a-input>
          <div style="margin-top: 8px; color: #999; font-size: 12px;">
            {{ trans.ui?.live_stream_name_hint || '建議使用英文、數字、下劃線，此名稱需與OBS推流配置一致' }}
          </div>
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- ⭐ 直播预览 Modal -->
    <a-modal
        v-model:visible="livePreviewVisible"
        :title="(trans.ui?.live_preview_title || '直播預覽 - {name}').replace('{name}', currentActivity?.name || '摸獎券活動')"
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
                {{ trans.ui?.live_url_label || '直播地址：' }}{{ livePreviewUrl }}
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
                {{ trans.ui?.copy_url || '複製地址' }}
              </a-button>
              <a-button
                  size="small"
                  @click="openInNewTab"
              >
                <template #icon>
                  <link-outlined/>
                </template>
                {{ trans.ui?.open_new_window || '新窗口打開' }}
              </a-button>
            </div>
          </div>
        </div>

        <!-- 協議提示 -->
        <div v-if="livePreviewUrl.startsWith('rtmp://')" style="padding: 12px; background: #fff3cd; border-top: 1px solid #ffc107;">
          <div style="display: flex; align-items: flex-start; color: #856404;">
            <warning-outlined style="font-size: 18px; margin-right: 8px; margin-top: 2px;"/>
            <div>
              <div style="font-weight: bold; margin-bottom: 4px;">{{ trans.ui?.rtmp_protocol_warning || 'RTMP 協議播放提示' }}</div>
              <div style="font-size: 12px; line-height: 1.6;">
                RTMP協議在現代瀏覽器中可能無法播放（需要Flash支持）。<br/>
                建議聯繫騰訊雲客服獲取 <strong>HLS播放地址（.m3u8格式）</strong> 或 <strong>HTTP-FLV格式</strong>，以獲得更好的兼容性。<br/>
                <a href="https://cloud.tencent.com/document/product/267/32733" target="_blank" style="color: #1890ff;">查看騰訊雲直播播放文檔 →</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <a-empty v-else :description="trans.ui?.no_live_url || '未獲取到直播地址'" />
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
      historyModalVisible: false,  // ⭐ 歷史活動選擇Modal
      historyActivities: [],        // ⭐ 歷史活動列表
      historyLoading: false,        // ⭐ 歷史活動載入狀態
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
        showTotal: (total) => `共 ${total} 條`,
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
          {required: true, message: '請輸入活動名稱', trigger: 'blur'},
          {max: 100, message: '活動名稱不能超過100個字符', trigger: 'blur'}
        ],
        start_time: [
          {required: true, message: '請選擇開始時間', trigger: 'change'}
        ],
        end_time: [
          {required: true, message: '請選擇結束時間', trigger: 'change'}
        ]
      },
      recordRules: {
        player_account: [
          {required: true, message: this.trans?.playerAccountPlaceholder || '請輸入玩家帳號', trigger: 'blur'}
        ],
        prize_level_id: [
          {required: true, message: this.trans?.prizeLevelPlaceholder || '請選擇中獎等級', trigger: 'change'}
        ]
      },
      ticketFilter: {
        ticket_no: '',
        player_uuid: '',
        time_range: null,
      },
      ticketColumns: [
        {title: '券號', key: 'ticket_no', dataIndex: 'ticket_no', width: 120, ellipsis: true},
        {title: '玩家', key: 'player_name', dataIndex: 'player_name', width: 100},
        {title: '玩家UUID', key: 'player_uuid', dataIndex: 'player_uuid', width: 150}, // ⭐ 新增
        {title: '來源', key: 'source', dataIndex: 'source', width: 100},
        {title: '狀態', key: 'status', dataIndex: 'status', width: 90},
        {title: '發放時間', key: 'created_at', dataIndex: 'created_at', width: 160},
        {title: '使用時間', key: 'used_at', dataIndex: 'used_at', width: 160},
      ],
      levelNames: [
        '', '特等獎', '一等獎', '二等獎', '三等獎', '四等獎',
        '五等獎', '六等獎', '七等獎', '八等獎', '九等獎'
      ],
      // ⭐ 单个录入数据对象
      singleRecord: {
        prize_level_id: null,      // 选择的奖品等级ID
        ticket_no: '',             // 输入的券号
        player_info: null,         // 查询到的玩家信息
        loading: false,            // 查询loading状态
        error: null                // 错误信息
      },
      // ⭐ 当前活动信息（用于录入表单顶部展示）
      currentActivityInfo: null
    };
  },
  computed: {
    statusOptions() {
      return [
        {label: this.trans.allStatus, value: 'all'},
        {label: this.trans.notStarted, value: 0},
        {label: this.trans.ongoing, value: 1},
        {label: this.trans.pendingDraw, value: 5},  // ⭐ 新增：待開獎
        {label: this.trans.drawing, value: 6},      // ⭐ 新增：開獎中
        {label: this.trans.ended, value: 2},
        {label: this.trans.closed, value: 3},
      ];
    },
    prizeColumns() {
      return [
        {title: this.trans.table?.level || '等級', key: 'level_name', dataIndex: 'level_name'},
        {title: this.trans.table?.prize_amount || '獎勵金額', key: 'prize_amount', dataIndex: 'prize_amount'},
      ];
    },
    vipConfigColumns() {
      return [
        {title: this.trans.table?.vip_level || 'VIP等級', key: 'vip_level_name', dataIndex: 'vip_level_id'},
        {title: this.trans.table?.bet_amount_required || '所需打碼量', key: 'bet_amount_required', dataIndex: 'bet_amount_required'},
        {title: this.trans.table?.ticket_count || '發放券數', key: 'ticket_count', dataIndex: 'ticket_count'},
      ];
    }
  },
  mounted() {
    this.fetchActivities();
  },
  methods: {
    // 獲取活動列表
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
          this.$message.error(res.message || res.msg || '獲取活動列表失敗');
        }
      } catch (error) {
        this.$message.error('獲取活動列表失敗');
        console.error(error);
      } finally {
        this.loading = false;
      }
    },

    // 狀態篩選變化
    handleStatusChange() {
      this.fetchActivities();
    },

    // ⭐ 處理創建選單點擊
    handleCreateMenuClick({key}) {
      if (key === 'new') {
        // 從零創建
        this.showCreateForm();
      } else if (key === 'copy') {
        // 從歷史活動創建
        this.showHistoryActivityModal();
      }
    },

    // ⭐ 顯示歷史活動選擇Modal
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
          this.$message.error(res.message || '獲取歷史活動失敗');
        }
      } catch (error) {
        this.$message.error('獲取歷史活動失敗');
        console.error(error);
      } finally {
        this.historyLoading = false;
      }
    },

    // ⭐ 選擇歷史活動
    async selectHistoryActivity(activity) {
      this.historyModalVisible = false;
      this.formMode = 'create';

      // 獲取活動詳情（包含獎品配置和VIP配置）
      const detail = await this.getActivityDetail(activity.id);

      if (!detail) {
        this.$message.error('獲取活動詳情失敗');
        return;
      }

      // 填充表單資料
      this.formData = {
        name: activity.name + ' (副本)',
        description: activity.description || '',
        cover_image: activity.cover_image || '',
        start_time: null,  // 不複製時間，讓使用者設定
        end_time: null,
        vip_configs: detail.vip_configs || this.vip_levels.map(vipLevel => ({
          vip_level_id: vipLevel.id,
          vip_level_name: vipLevel.name,
          bet_amount_required: 0,
          ticket_count: 1
        })),
        prize_levels: detail.prize_levels || []
      };

      // 顯示創建表單
      this.formVisible = true;
      this.$message.success('已載入歷史活動資料，請設定活動時間並提交');
    },

    // 顯示創建表單
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

    // 上傳前驗證
    handleBeforeUpload(file) {
      const isImage = file.type === 'image/jpeg' || file.type === 'image/png' || file.type === 'image/jpg';
      if (!isImage) {
        this.$message.error(this.trans.validation?.image_format_error || '只能上傳 JPG/PNG 格式的圖片！');
        return false;
      }
      const isLt2M = file.size / 1024 / 1024 < 2;
      if (!isLt2M) {
        this.$message.error('圖片大小不能超過 2MB！');
        return false;
      }
      return true;
    },

    // 處理封面上傳
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
          this.$message.success('圖片上傳成功');
        } else {
          this.$message.error(res.message || '圖片上傳失敗');
        }
      } catch (error) {
        console.error('上傳失敗:', error);
        this.$message.error('圖片上傳失敗');
      } finally {
        this.uploading = false;
      }
    },

    // 選單點擊
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

    // 顯示錄入中獎抽屜 - 改为单个录入模式
    async showRecordModal(activity) {
      try {
        // 獲取活動詳情和獎品等級
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getActivityDetail',
          method: 'post',
          data: {id: activity.id}
        });

        if (res.code === 200 && res.data) {
          const prizeLevels = res.data.prize_levels || [];

          if (prizeLevels.length === 0) {
            this.$message.warning('該活動尚未配置獎品等級');
            return;
          }

          // ⭐ 存储奖品等级列表（供下拉选择使用）
          this.recordPrizeLevels = prizeLevels;

          // ⭐ 存储活动信息（用于表单顶部展示）
          this.currentActivityInfo = {
            name: activity.name,
            start_time: activity.start_time,
            end_time: activity.end_time,
            max_ticket_no: activity.max_ticket_no || res.data.max_ticket_no
          };

          // ⭐ 重置单个录入数据
          this.singleRecord = {
            prize_level_id: null,
            ticket_no: '',
            player_info: null,
            loading: false,
            error: null
          };

          this.recordData = {
            activity_id: activity.id
          };

          this.recordVisible = true;
        } else {
          this.$message.error('獲取活動詳情失敗');
        }
      } catch (error) {
        this.$message.error('獲取活動詳情失敗');
        console.error(error);
      }
    },

    // ⭐ 奖品等级改变时
    handlePrizeLevelChange(value) {
      // 可以在这里添加额外逻辑（如果需要）
      console.log('Selected prize level:', value);
    },

    // ⭐ 查询单个玩家信息（单个录入模式）
    async querySinglePlayerByTicketNo() {
      const ticketNo = this.singleRecord.ticket_no?.trim();

      if (!ticketNo) {
        return;
      }

      // 验证：只能包含数字
      if (!/^\d+$/.test(ticketNo)) {
        this.singleRecord.error = '券號只能包含數字，請重新輸入';
        this.singleRecord.ticket_no = '';
        return;
      }

      // 验证：不能超过6位
      if (ticketNo.length > 6) {
        this.singleRecord.error = '券號不能超過6位數字';
        this.singleRecord.ticket_no = '';
        return;
      }

      // 格式化：补齐6位（前面补0）
      this.singleRecord.ticket_no = ticketNo.padStart(6, '0');

      // 查询玩家信息
      this.singleRecord.loading = true;
      this.singleRecord.error = null;
      this.singleRecord.player_info = null;

      try {
        // ⭐ 使用 this.$request（和 getActivityDetail 一样）
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getPlayerByTicketNo',
          method: 'post',
          data: {
            activity_id: this.recordData.activity_id,
            ticket_no: this.singleRecord.ticket_no
          }
        });

        console.log('📡 Query response:', res);

        // ⭐ 统一响应格式判断：通过 code 区分成功和失败
        if (res.code === 200) {
          // ✅ 成功：数据在 res.data
          this.singleRecord.player_info = res.data;
          this.singleRecord.error = null;
          console.log('✅ Player found:', res.data);
        } else {
          // ❌ 失败：错误消息在 res.message
          this.singleRecord.error = res.message || '查詢失敗';
          this.singleRecord.player_info = null;
          console.log('❌ Error:', res.message, '(code:', res.code + ')');
        }
      } catch (error) {
        // ⭐ this.$request 在 code !== 200 时会 reject
        // 但 reject 的对象就是响应数据本身！
        console.log('❌ Request rejected:', error);

        if (error && error.code && error.message) {
          // 这是正常的业务错误响应
          this.singleRecord.error = error.message || '查詢失敗';
          this.singleRecord.player_info = null;
          console.log('❌ Business error:', error.message, '(code:', error.code + ')');
        } else {
          // 真正的异常错误（网络错误等）
          console.error('System error:', error);
          this.singleRecord.error = '系統錯誤，請聯繫管理員';
          this.singleRecord.player_info = null;
        }
      } finally {
        this.singleRecord.loading = false;
      }
    },

    // ⭐ 清除单个玩家信息
    clearSinglePlayerInfo() {
      this.singleRecord.player_info = null;
      this.singleRecord.error = null;
    },

    // ⭐ 提交单个中奖记录（自动发放）
    async submitSingleWinRecord() {
      if (!this.singleRecord.prize_level_id) {
        this.$message.warning('請選擇獎品等級');
        return;
      }

      if (!this.singleRecord.ticket_no) {
        this.$message.warning('請輸入中獎券號');
        return;
      }

      if (!this.singleRecord.player_info) {
        this.$message.warning('請先查詢玩家信息');
        return;
      }

      this.recordSubmitting = true;

      try {
        // ⭐ 使用单个录入专用API
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/recordSingleWinTicket',
          method: 'post',
          data: {
            activity_id: this.recordData.activity_id,
            prize_level_id: this.singleRecord.prize_level_id,
            ticket_no: this.singleRecord.ticket_no
          }
        });

        // ⭐ 调试日志
        console.log('Submit response:', res);
        console.log('res.code:', res.code);
        console.log('res.code === 200:', res.code === 200);

        // ⭐ 简单清晰的响应处理
        if (res.code === 200) {
          // ✅ 成功
          this.$message.success(res.data?.message || '錄入成功並已自動發放獎勵');

          // 重置单个录入表单，保持抽屉打开
          this.singleRecord = {
            prize_level_id: this.singleRecord.prize_level_id,  // 保留奖品等级选择
            ticket_no: '',
            player_info: null,
            loading: false,
            error: null
          };
        } else {
          // ❌ 失败
          this.$message.error(res.msg || res.message || '錄入失敗');
        }
      } catch (error) {
        this.$message.error('錄入失敗');
        console.error(error);
      } finally {
        this.recordSubmitting = false;
      }
    },

    // 關閉錄入抽屜
    handleRecordClose() {
      this.recordVisible = false;
      this.recordData = {
        activity_id: null
      };
      this.recordPrizeLevels = [];
      // ⭐ 重置单个录入数据
      this.singleRecord = {
        prize_level_id: null,
        ticket_no: '',
        player_info: null,
        loading: false,
        error: null
      };
      // ⭐ 清空活动信息
      this.currentActivityInfo = null;
    },

    // 顯示直播地址彈窗
    showLiveModal(activity) {
      this.currentActivity = activity;
      this.liveUrlInput = activity.live_url || '';
      this.liveModalMode = 'update';
      this.liveModalVisible = true;
    },

    // 關閉直播地址彈窗
    handleLiveModalClose() {
      this.liveModalVisible = false;
      this.liveUrlInput = '';
      this.currentActivity = null;
    },

    // ⭐ 預覽直播
    async previewLive(activity) {
      if (!activity.live_url) {
        this.$message.warning(this.trans.ui?.activity_no_live_url || '該活動尚未設置直播流名稱');
        return;
      }

      // live_url儲存的是流名稱，透過API取得播放器配置
      try {
        const loading = this.$message.loading(this.trans.ui?.generating_live_url || '正在生成直播地址...', 0);

        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/getLivePlayerConfig',
          method: 'post',
          data: {
            stream_name: activity.live_url // 流名稱
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
          this.$message.error(res.message || (this.trans.ui?.generate_live_url_failed || '生成直播地址失敗'));
        }
      } catch (error) {
        console.error('生成直播地址失敗:', error);
        this.$message.error(this.trans.ui?.generate_live_url_failed || '生成直播地址失敗');
      }
    },

    // ⭐ 關閉直播預覽
    closeLivePreview() {
      this.livePreviewVisible = false;
      this.livePreviewUrl = '';
      this.livePlayerConfig = null; // ⭐ 清空播放器配置
      this.currentActivity = null;
    },

    // ⭐ 開始直播
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
          this.$message.success(res.data.message || '直播已開始，已通知所有玩家');
          this.fetchActivities(); // 刷新活动列表
        } else {
          this.$message.error(res.message || res.msg || '開始直播失敗');
        }
      } catch (error) {
        console.error('開始直播失敗:', error);
        this.$message.error('開始直播失敗');
      }
    },

    // ⭐ 結束直播
    async endLiveStream(activity) {
      this.$confirm({
        title: this.trans.ui?.end_live_confirm_title || '結束直播',
        content: this.trans.form?.end_live_confirm_content_full || '確認結束直播嗎？結束後玩家將無法繼續觀看。',
        okText: this.trans.form?.confirm_end || '確認結束',
        cancelText: this.trans.cancel || '取消',
        onOk: async () => {
          try {
            const loading = this.$message.loading('正在結束直播...', 0);
            const res = await this.$request({
              url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/endLive',
              method: 'post',
              data: {
                activity_id: activity.id
              }
            });

            loading();

            if (res.code === 200) {
              this.$message.success(res.data.message || '直播已結束');
              this.fetchActivities(); // 刷新活动列表
            } else {
              this.$message.error(res.message || res.msg || '結束直播失敗');
            }
          } catch (error) {
            console.error('結束直播失敗:', error);
            this.$message.error('結束直播失敗');
          }
        }
      });
    },

    // ⭐ 複製直播地址
    async copyLiveUrl() {
      try {
        await navigator.clipboard.writeText(this.livePreviewUrl);
        this.$message.success(this.trans.ui?.live_url_copied || '直播地址已複製到剪貼板');
      } catch (err) {
        // 兼容旧浏览器
        const input = document.createElement('input');
        input.value = this.livePreviewUrl;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        this.$message.success(this.trans.ui?.live_url_copied || '直播地址已複製到剪貼板');
      }
    },

    // ⭐ 在新視窗開啟直播
    openInNewTab() {
      const url = this.getLivePlayerUrl();
      window.open(url, '_blank', 'width=1280,height=720');
    },

    // ⭐ 取得直播播放器URL（使用TCPlayer v5）
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

    // 提交直播流名稱
    async submitLiveUrl() {
      const streamName = this.liveUrlInput.trim();

      if (!streamName) {
        this.$message.error(this.trans.form?.live_stream_name_required || '請輸入直播流名稱');
        return;
      }

      // 驗證流名稱格式（只允許英文、數字、底線）
      if (!/^[a-zA-Z0-9_]+$/.test(streamName)) {
        this.$message.error(this.trans.ui?.stream_name_format_error || '流名稱只能包含英文、數字和下劃線');
        return;
      }

      if (streamName.length > 50) {
        this.$message.error(this.trans.ui?.stream_name_too_long || '流名稱不能超過50個字符');
        return;
      }

      try {
        let url, successMsg;

        if (this.liveModalMode === 'startDrawing') {
          // ⭐ 开始开奖模式
          url = 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/startDrawing';
          successMsg = '開獎已開始';
        } else {
          // 普通更新直播地址模式
          url = 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/updateLiveUrl';
          successMsg = this.trans.liveUrlUpdated || '直播地址設置成功';
        }

        const res = await this.$request({
          url: url,
          method: 'post',
          data: {
            id: this.currentActivity.id,
            live_url: streamName // 儲存流名稱，不是完整URL
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
          this.$message.error(res.message || res.msg || '操作失敗');
        }
      } catch (error) {
        console.error('操作失敗:', error);
        this.$message.error('操作失敗');
      }
    },

    // 查看詳情
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
        this.$message.error('獲取活動詳情失敗');
      }
    },

    // 點擊卡片查看詳情
    viewActivityDetail(activity) {
      this.showDetail(activity);
    },

    // ⭐ 批量發放該活動所有已錄入未發放的獎勵
    // showDistributeForm 已移除：現在錄入中獎時自動發放，無需單獨操作

    // ⭐ 獲取活動詳情（通用方法）
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
          this.$message.error(res.message || '獲取活動詳情失敗');
          return null;
        }
      } catch (error) {
        console.error('獲取活動詳情失敗:', error);
        return null;
      }
    },

    // 編輯活動
    async editActivity(activity) {
      const data = await this.getActivityDetail(activity.id);
      if (!data) {
        this.$message.error('獲取活動詳情失敗');
        return;
      }

      // 使用 window.dayjs 或 dayjs（全局对象）
      const dayjs = window.dayjs || window.moment;

      this.formMode = 'edit';
      this.formData = {
        id: data.id,
        name: data.name,
        description: data.description,
        cover_image: data.cover_image || '',
        start_time: dayjs ? dayjs(data.start_time) : data.start_time,
        end_time: dayjs ? dayjs(data.end_time) : data.end_time,
        vip_configs: data.vip_configs || [],
        prize_levels: data.prize_levels || []
      };
      this.formVisible = true;
    },

    // 關閉活動
    closeActivity(activity) {
      this.$confirm({
        title: '確認關閉活動？',
        content: '關閉後活動將立即停止，已發放的摸獎券將無法使用',
        okText: '確認',
        cancelText: '取消',
        onOk: async () => {
          try {
            const res = await this.$request({
              url: 'ex-admin/addons-webman-controller-ChannelLotteryTicketActivityController/closeActivity',
              method: 'post',
              data: {id: activity.id}
            });

            if (res.code === 200) {
              this.$message.success('活動已關閉');
              this.fetchActivities();
            } else {
              this.$message.error(res.message || res.msg || '關閉活動失敗');
            }
          } catch (error) {
            this.$message.error('關閉活動失敗');
          }
        }
      });
    },

    // ⭐ 開始開獎（手動觸發）
    startDrawing(activity) {
      // 设置为开奖模式，并弹出直播地址输入框
      this.currentActivity = activity;
      this.liveUrlInput = activity.live_url || '';
      this.liveModalMode = 'startDrawing';
      this.liveModalVisible = true;
    },

    // ⭐ 停止開獎（手動觸發）- 必須二次確認
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

        // 嘗試多種方式獲取回應資料
        if (error.response?.data) {
          res = error.response.data;
        } else if (error.data) {
          res = error.data;
        } else if (typeof error === 'object' && error.code) {
          res = error;
        } else {
          // 真正的網路錯誤
          this.$message.error('網路錯誤：' + (error.message || '未知錯誤'));
          return;
        }
      }

      // ⭐ 詳細偵錯日誌
      console.log('stopDrawing response:', res);
      console.log('res.code:', res.code, 'type:', typeof res.code);
      console.log('res.message:', res.message);
      console.log('res.data:', res.data);
      console.log('res.data?.need_confirm:', res.data?.need_confirm);

      // ⭐ 检查是否需要二次确认（使用 need_confirm 标记而非 40001）
      if (res.code === 200 && res.data?.need_confirm) {
        // ⭐ 后端要求二次确认，显示详细统计信息
        const data = res.data;

        // ⭐ 將 \n 字串替換為真正的換行符
        const confirmMessage = (data.confirm_message || '').replace(/\\n/g, '\n');

        // 构建确认内容（保留换行符）
        const contentLines = confirmMessage.split('\n');

        // 如果有录入券号，显示券号列表（最多显示10个）
        let ticketList = '';
        if (data.ticket_nos && data.ticket_nos.length > 0) {
          const displayTickets = data.ticket_nos.slice(0, 10);
          ticketList = '\n\n🎫 已錄入券號：\n' + displayTickets.join(', ');
          if (data.ticket_nos.length > 10) {
            ticketList += ` ...等${data.ticket_nos.length}個`;
          }
        }

        this.$confirm({
          title: data.win_record_count === 0 ? '⚠️ 警告：未錄入中獎券號' : '確認停止開獎',
          content: contentLines.join('\n') + ticketList,
          okText: '確認停止開獎',
          okType: 'danger',
          cancelText: data.win_record_count === 0 ? (this.trans.ui?.cancel_enter_win || '取消，先錄入中獎') : (this.trans.cancel || '取消'),
          width: 520,
          onOk: () => {
            // 用户确认后，带上 confirmed=true 重新调用
            this.stopDrawing(activity, true);
          }
        });
      } else if (res.code === 200) {
        // ⭐ 真正的成功（已确认并停止开奖）
        this.$message.success('開獎已停止');
        this.fetchActivities();
      } else {
        this.$message.error(res.message || res.msg || '停止開獎失敗');
      }
    },

    // 添加獎品等級
    addPrizeLevel() {
      if (this.formData.prize_levels.length >= 10) {
        this.$message.warning('最多只能添加10個獎品等級');
        return;
      }

      this.formData.prize_levels.push({
        level_rank: null,
        level_name: '',
        prize_amount: 0,
        prize_count: 0
      });
    },

    // 移除獎品等級
    removePrizeLevel(index) {
      this.formData.prize_levels.splice(index, 1);
    },

    // 等級排名變化時更新等級名稱
    handleLevelRankChange(index) {
      const rank = this.formData.prize_levels[index].level_rank;
      this.formData.prize_levels[index].level_name = this.getLevelName(rank);
    },

    // 檢查等級排名是否已被選擇
    isLevelRankSelected(rank, currentIndex) {
      return this.formData.prize_levels.some((level, index) => {
        return index !== currentIndex && level.level_rank === rank;
      });
    },

    // 表單提交
    async handleFormSubmit() {
      try {
        await this.$refs.formRef.validate();

        // 驗證時間
        if (this.formData.end_time.isBefore(this.formData.start_time)) {
          this.$message.error('結束時間必須大於開始時間');
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
          this.$message.success(this.formMode === 'create' ? '創建成功' : '更新成功');
          this.formVisible = false;
          this.fetchActivities();
        } else {
          this.$message.error(res.message || res.msg || '操作失敗');
        }
      } catch (error) {
        console.error(error);
      } finally {
        this.submitting = false;
      }
    },

    // 關閉表單
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
        6: 'purple',    // 開獎中
        2: 'default',   // 已结束
        3: 'red'        // 已關閉
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
      // 使用原生JavaScript格式化時間
      const date = new Date(time);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hour = String(date.getHours()).padStart(2, '0');
      const minute = String(date.getMinutes()).padStart(2, '0');
      return `${year}-${month}-${day} ${hour}:${minute}`;
    },

    getLevelName(rank) {
      return this.levelNames[rank] || `等級${rank}`;
    },

    getVipLevelName(vipLevelId) {
      const vipLevel = this.vip_levels.find(v => v.id === vipLevelId);
      return vipLevel ? vipLevel.name : `VIP${vipLevelId}`;
    },

    // 顯示發放列表
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

    // 獲取發放列表
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

        // ⭐ 時間範圍參數
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
          this.$message.error(res.message || res.msg || '獲取列表失敗');
        }
      } catch (error) {
        this.$message.error('獲取列表失敗');
        console.error(error);
      } finally {
        this.ticketLoading = false;
      }
    },

    // ⭐ 搜尋按鈕點擊
    handleTicketSearch() {
      this.ticketPagination.current = 1;
      this.fetchTicketList(this.currentActivity.id, 1);
    },

    // ⭐ 重置按鈕點擊
    handleTicketReset() {
      this.ticketFilter = {
        ticket_no: '',
        player_uuid: '',
        time_range: null,
      };
      this.ticketPagination.current = 1;
      this.fetchTicketList(this.currentActivity.id, 1);
    },

    // 表格分頁變化
    handleTicketTableChange(pagination) {
      this.fetchTicketList(this.currentActivity.id, pagination.current);
    },

    // 獲取券狀態文本
    getTicketStatusText(status) {
      const statusMap = {
        0: '未使用',
        1: '已使用',
        2: '已過期'
      };
      return statusMap[status] || '未知';
    },

    // 獲取券狀態顏色
    getTicketStatusColor(status) {
      const colorMap = {
        0: 'green',
        1: 'default',
        2: 'red'
      };
      return colorMap[status] || 'default';
    },

    // 獲取來源文本
    getSourceText(source) {
      const sourceMap = {
        'betting': '打碼獲得',
        'recharge': '充值贈送',
        'activity': '活動贈送',
        'manual': '手動發放'
      };
      return sourceMap[source] || '未知來源';
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
