<template>
  <div class="command-test-container">
    <div class="content-wrapper">
      <!-- 机台信息卡片 -->
      <a-card :bordered="false" class="machine-info-card">
        <template #title>
          <div class="card-title">
            <span class="title-text">
              <ApiOutlined /> {{ machine_code }}
            </span>
            <div class="title-actions">
              <a-badge :status="currentIsOnline ? 'processing' : 'error'" :text="currentIsOnline ? '在線' : '離線'" />
              <a-button
                :loading="refreshing"
                size="small"
                type="link"
                style="color: white;"
                @click="refreshData"
              >
                <template #icon><ReloadOutlined /></template>
                {{ refreshing ? lang.refreshing : lang.refresh }}
              </a-button>
            </div>
          </div>
        </template>
        <a-row :gutter="12">
          <a-col :span="14">
            <a-descriptions :column="2" size="small" bordered>
              <a-descriptions-item label="機台名稱" :span="2">
                <strong>{{ machine_name }}</strong>
              </a-descriptions-item>
              <a-descriptions-item label="控制類型">
                <a-tag color="orange">{{ control_type_name }}</a-tag>
              </a-descriptions-item>
              <a-descriptions-item label="遊戲類型">
                <a-tag color="blue">{{ game_type_name }}</a-tag>
              </a-descriptions-item>
              <a-descriptions-item label="TCP狀態">
                <a-tag :color="currentIsOnline ? 'success' : 'error'" size="small">
                  {{ currentIsOnline ? '✓ 已連接' : '✗ 未連接' }}
                </a-tag>
              </a-descriptions-item>
              <a-descriptions-item :label="lang.machine_status">
                <a-tag :color="currentIsGaming ? 'processing' : 'default'" size="small">
                  {{ currentIsGaming ? lang.gaming : lang.idle }}
                </a-tag>
              </a-descriptions-item>
            </a-descriptions>
          </a-col>
          <a-col :span="10">
            <!-- 玩家状态和机台数据 -->
            <div class="status-panel">
              <div class="status-section">
                <div class="status-title">{{ lang.player_status }}</div>
                <div v-if="currentPlayerInfo" class="status-content">
                  <a-tag color="green">
                    <UserOutlined /> {{ currentPlayerInfo.nickname || currentPlayerInfo.username }}
                  </a-tag>
                  <span class="player-id">#{{ currentPlayerInfo.id }}</span>
                </div>
                <div v-else class="status-content no-player">
                  {{ lang.no_player }}
                </div>
              </div>
              <div v-if="currentMachineData && Object.keys(currentMachineData).length" class="status-section">
                <div class="status-title">{{ lang.current_data }}</div>
                <div class="status-content machine-data-grid">
                  <div v-for="(value, key) in currentMachineData" :key="key" class="data-item">
                    <span class="data-label">{{ key }}:</span>
                    <span class="data-value">{{ value }}</span>
                  </div>
                </div>
              </div>
            </div>
          </a-col>
        </a-row>
      </a-card>

    <!-- 指令分类 -->
    <a-tabs v-model:activeKey="activeTab" class="command-tabs" type="card">
      <a-tab-pane
        v-for="(commands, category) in commandList"
        :key="category"
        :tab="category"
      >
        <a-space :size="8" direction="vertical" style="width: 100%;">
          <a-card
            v-for="(cmd, index) in commands"
            :key="index"
            :bordered="false"
            :class="{ 'danger-command': cmd.danger }"
            class="command-card"
          >
            <!-- 指令信息和操作按钮 -->
            <div class="command-item">
              <div class="command-info">
                <div class="command-name">
                  <span v-if="cmd.danger" style="color: #ff4d4f;">⚠️</span>
                  {{ cmd.name }}
                </div>
                <div class="command-code">
                  <a-tag color="purple">{{ cmd.cmd }}</a-tag>
                </div>
                <div class="command-desc">{{ cmd.desc }}</div>

                <!-- ✅ 参数输入框（开任意分等需要输入参数的指令） -->
                <div v-if="cmd.has_input" class="command-input">
                  <a-input-number
                    v-model:value="cmdInputValues[cmd.cmd]"
                    :min="1"
                    :max="99999"
                    :placeholder="cmd.input_label || '请输入参数'"
                    size="small"
                    style="width: 180px;"
                  >
                    <template #addonBefore>{{ cmd.input_label || '参数' }}</template>
                  </a-input-number>
                  <span style="margin-left: 8px; color: #8c8c8c; font-size: 11px;">
                    預設: {{ cmd.default_value }}
                  </span>
                </div>
              </div>
              <div class="command-action">
                <a-button
                  :danger="cmd.danger"
                  :loading="currentCommand === cmd.cmd && loading"
                  type="primary"
                  @click="sendCommand(cmd)"
                >
                  <template #icon><ThunderboltOutlined /></template>
                  发送指令
                </a-button>
              </div>
            </div>

            <!-- ✅ 该指令的执行结果（显示在指令下方） -->
            <div v-if="hasResults(cmd.cmd)" class="cmd-results">
              <a-divider style="margin: 16px 0 12px 0;">
                <span style="font-size: 12px; color: #8c8c8c;">
                  执行记录 ({{ getResults(cmd.cmd).length }})
                </span>
              </a-divider>
              <div class="result-list">
                <div
                  v-for="(result, rIndex) in getResults(cmd.cmd)"
                  :key="rIndex"
                  :class="[result.success ? 'success' : 'error', rIndex === 0 ? 'latest' : '']"
                  class="result-item"
                >
                  <div class="result-header">
                    <span class="result-time">{{ result.time }}</span>
                    <a-tag :color="result.success ? 'success' : 'error'" size="small">
                      {{ result.success ? '✅ 成功' : '❌ 失败' }}
                    </a-tag>
                    <a-badge v-if="rIndex === 0" status="processing" text="最新" />
                  </div>
                  <div v-if="result.message" class="result-message">{{ result.message }}</div>
                  <div v-if="result.data" class="result-data">
                    <pre>{{ JSON.stringify(result.data, null, 2) }}</pre>
                  </div>
                </div>
              </div>
            </div>
          </a-card>
        </a-space>
      </a-tab-pane>
    </a-tabs>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CommandTest',
  props: {
    machine_id: {
      type: [String, Number],
      required: true
    },
    machine_code: {
      type: String,
      required: true
    },
    machine_name: {
      type: String,
      required: true
    },
    control_type: {
      type: String,
      required: true
    },
    control_type_name: {
      type: String,
      required: true
    },
    game_type: {
      type: [String, Number],
      required: true
    },
    game_type_name: {
      type: String,
      required: true
    },
    command_list: {
      type: Object,
      required: true
    },
    lang: {
      type: Object,
      required: true
    },
    is_online: {
      type: Boolean,
      default: false
    },
    domain: {
      type: String,
      default: ''
    },
    port: {
      type: [String, Number],
      default: ''
    },
    is_gaming: {
      type: Boolean,
      default: false
    },
    player_info: {
      type: Object,
      default: null
    },
    machine_data: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      activeTab: Object.keys(this.command_list)[0] || '',
      loading: false,
      currentCommand: '',
      refreshing: false,
      cmdResults: {}, // ✅ 改为对象结构：{ 'cmd1': [result1, result2], 'cmd2': [...] }
      cmdInputValues: {}, // ✅ 指令参数输入值：{ 'cmd1': value1, 'cmd2': value2 }
      commandList: this.command_list,
      // 响应式状态数据（从 props 初始化，可以被修改）
      currentIsOnline: this.is_online,
      currentIsGaming: this.is_gaming,
      currentPlayerInfo: this.player_info,
      currentMachineData: this.machine_data
    };
  },
  mounted() {
    // 初始化带输入框的指令的默认值
    Object.keys(this.command_list).forEach(category => {
      this.command_list[category].forEach(cmd => {
        if (cmd.has_input && cmd.default_value) {
          this.cmdInputValues[cmd.cmd] = cmd.default_value;
        }
      });
    });
  },
  methods: {
    // 刷新机台数据
    async refreshData() {
      this.refreshing = true;
      try {
        const response = await fetch('/ex-admin/addons-webman-controller-AdminOfflineMachineController/refreshMachineData', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            machine_id: this.machine_id
          })
        });

        const result = await response.json();

        if (result.code === 1) {
          // 更新数据
          this.currentIsOnline = result.data.is_online;
          this.currentIsGaming = result.data.is_gaming;
          this.currentPlayerInfo = result.data.player_info;
          this.currentMachineData = result.data.machine_data;
          this.$message.success(this.lang.refresh_success);
        } else {
          this.$message.error(result.msg || '刷新失败');
        }
      } catch (error) {
        this.$message.error(this.lang.request_failed.replace('{error}', error.message));
      } finally {
        this.refreshing = false;
      }
    },

    // 判断是否有执行结果
    hasResults(cmdCode) {
      return this.cmdResults[cmdCode] && Array.isArray(this.cmdResults[cmdCode]) && this.cmdResults[cmdCode].length > 0;
    },

    // 获取执行结果列表
    getResults(cmdCode) {
      return this.cmdResults[cmdCode] || [];
    },

    async sendCommand(cmd) {
      this.currentCommand = cmd.cmd;
      this.loading = true;

      // 危险指令二次确认（使用 Ant Design Vue Modal）
      if (cmd.danger) {
        const self = this;

        // 构建警告内容
        const contentText = [
          this.lang.danger_command_content.replace('{name}', cmd.name),
          '',
          this.lang.danger_command_desc.replace('{desc}', cmd.desc),
          '',
          this.lang.danger_command_confirm
        ].join('\n');

        this.$confirm({
          title: '⚠️ ' + this.lang.danger_command_title,
          content: contentText,
          okText: this.lang.confirm,
          cancelText: this.lang.cancel,
          okType: 'danger',
          onOk: async () => {
            await self.executeCommand(cmd);
          },
          onCancel: () => {
            self.loading = false;
            self.currentCommand = '';
          }
        });
        return;
      }

      // 非危险指令直接执行
      await this.executeCommand(cmd);
    },

    async executeCommand(cmd) {
      try {
        // ✅ 获取指令参数（如果有输入框）
        const cmdData = cmd.has_input
          ? (this.cmdInputValues[cmd.cmd] || cmd.default_value || 0)
          : 0;

        const response = await fetch('/ex-admin/addons-webman-controller-AdminOfflineMachineController/sendCommand', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            machine_id: this.machine_id,
            cmd: cmd.cmd,
            cmd_name: cmd.name,
            data: cmdData  // ✅ 传递参数值
          })
        });

        const result = await response.json();

        // ✅ 将结果添加到对应指令的结果列表（最新的在最前面）
        const resultData = {
          time: new Date().toLocaleString('zh-CN'),
          success: result.code === 1,
          message: result.msg,
          data: result.data
        };

        console.log('添加结果:', {
          cmd: cmd.cmd,
          cmdName: cmd.name,
          resultData: resultData,
          beforeCmdResults: JSON.parse(JSON.stringify(this.cmdResults))
        });

        if (!this.cmdResults[cmd.cmd]) {
          // Vue 3 直接赋值即可，响应式会自动处理
          this.cmdResults[cmd.cmd] = [resultData];
          console.log('创建新数组');
        } else {
          // 已存在，直接添加到数组开头
          this.cmdResults[cmd.cmd].unshift(resultData);
          console.log('添加到已存在数组');
        }

        console.log('添加后的 cmdResults:', JSON.parse(JSON.stringify(this.cmdResults)));

        if (result.code === 1) {
          this.$message.success(this.lang.success.replace('{name}', cmd.name));
        } else {
          this.$message.error(this.lang.failed.replace('{msg}', result.msg || '未知错误'));
        }

      } catch (error) {
        // ✅ 将错误结果添加到对应指令的结果列表
        const errorData = {
          time: new Date().toLocaleString('zh-CN'),
          success: false,
          message: error.message,
          data: null
        };

        console.log('添加错误结果:', {
          cmd: cmd.cmd,
          errorData: errorData
        });

        if (!this.cmdResults[cmd.cmd]) {
          // Vue 3 直接赋值
          this.cmdResults[cmd.cmd] = [errorData];
        } else {
          this.cmdResults[cmd.cmd].unshift(errorData);
        }

        console.log('错误后的 cmdResults:', JSON.parse(JSON.stringify(this.cmdResults)));

        this.$message.error(this.lang.request_failed.replace('{error}', error.message));
      } finally {
        this.loading = false;
        this.currentCommand = '';
      }
    }
  }
};
</script>

<style scoped>
.command-test-container {
  padding: 16px;
  background: #f0f2f5;
  min-height: 100vh;
  display: flex;
  justify-content: center;
}

.content-wrapper {
  width: 100%;
  max-width: 1000px;
}

.machine-info-card {
  margin-bottom: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  border-radius: 8px;
}

.machine-info-card :deep(.ant-card-head) {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 12px 20px;
  min-height: auto;
}

.machine-info-card :deep(.ant-card-head-title) {
  color: white;
  padding: 0;
}

.machine-info-card :deep(.ant-card-body) {
  padding: 16px 20px;
}

.card-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
}

.title-text {
  font-size: 16px;
  font-weight: 600;
}

.title-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.title-actions :deep(.ant-btn-link) {
  padding: 0 8px;
  height: 24px;
  font-size: 12px;
}

.title-actions :deep(.ant-btn-link:hover) {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

.card-title :deep(.ant-badge-status-text) {
  color: white;
  font-size: 13px;
  margin-left: 4px;
}

.status-panel {
  background: #fafafa;
  border: 1px solid #e8e8e8;
  border-radius: 6px;
  padding: 12px;
  height: 100%;
}

.status-section {
  margin-bottom: 12px;
}

.status-section:last-child {
  margin-bottom: 0;
}

.status-title {
  font-size: 12px;
  font-weight: 600;
  color: #595959;
  margin-bottom: 8px;
  padding-bottom: 4px;
  border-bottom: 1px solid #e8e8e8;
}

.status-content {
  font-size: 12px;
  color: #262626;
}

.status-content.no-player {
  color: #8c8c8c;
  font-style: italic;
}

.player-id {
  margin-left: 6px;
  color: #8c8c8c;
  font-size: 11px;
}

.machine-data-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 6px;
}

.data-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 6px;
  background: white;
  border-radius: 3px;
  font-size: 11px;
}

.data-label {
  color: #8c8c8c;
  font-weight: 500;
}

.data-value {
  color: #1890ff;
  font-weight: 600;
  font-family: 'Consolas', monospace;
}

.command-tabs {
  background: white;
  padding: 12px;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}

.command-tabs :deep(.ant-tabs-nav) {
  margin-bottom: 12px;
}

.command-tabs :deep(.ant-tabs-tab) {
  padding: 8px 16px;
  font-size: 13px;
}

.command-card {
  transition: all 0.2s;
  border: 1px solid #e8e8e8;
  border-radius: 6px;
}

.command-card :deep(.ant-card-body) {
  padding: 14px 16px;
}

.command-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  transform: translateY(-1px);
  border-color: #d9d9d9;
}

.danger-command {
  border-color: #ffccc7;
  background: #fff2f0;
}

.danger-command:hover {
  border-color: #ffa39e;
}

.command-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}

.command-info {
  flex: 1;
  min-width: 0;
}

.command-name {
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.command-code {
  margin-bottom: 6px;
}

.command-code :deep(.ant-tag) {
  font-size: 12px;
  padding: 2px 8px;
}

.command-desc {
  font-size: 12px;
  color: #718096;
  line-height: 1.5;
}

.command-action {
  flex-shrink: 0;
}

.command-action :deep(.ant-btn) {
  font-size: 13px;
  height: 32px;
  padding: 4px 15px;
}

.command-input {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #f0f0f0;
}

.command-input :deep(.ant-input-number) {
  font-size: 12px;
}

.command-input :deep(.ant-input-number-group-addon) {
  font-size: 12px;
  padding: 0 8px;
}

/* ✅ 指令内部的结果区域 */
.cmd-results {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px dashed #e8e8e8;
}

.cmd-results :deep(.ant-divider) {
  margin: 8px 0;
}

.result-list {
  max-height: 300px;
  overflow-y: auto;
  padding-right: 4px;
}

.result-list::-webkit-scrollbar {
  width: 6px;
}

.result-list::-webkit-scrollbar-thumb {
  background: #d9d9d9;
  border-radius: 3px;
}

.result-list::-webkit-scrollbar-thumb:hover {
  background: #bfbfbf;
}

.result-item {
  padding: 10px 12px;
  margin-bottom: 6px;
  border-radius: 4px;
  border-left: 3px solid;
  background: #fafafa;
  font-size: 12px;
}

.result-item.success {
  background: #f6ffed !important;
  border-left-color: #52c41a;
}

.result-item.error {
  background: #fff2f0 !important;
  border-left-color: #ff4d4f;
}

.result-item.latest {
  animation: highlight 1s ease-in-out;
}

@keyframes highlight {
  0% {
    box-shadow: 0 0 0 0 rgba(24, 144, 255, 0.4);
  }
  50% {
    box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.2);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(24, 144, 255, 0);
  }
}

.result-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}

.result-header :deep(.ant-tag) {
  font-size: 11px;
  padding: 0 6px;
  line-height: 18px;
}

.result-header :deep(.ant-badge) {
  font-size: 11px;
}

.result-time {
  color: #8c8c8c;
  font-size: 11px;
}

.result-message {
  font-size: 12px;
  color: #595959;
  margin-bottom: 6px;
  line-height: 1.5;
}

.result-data {
  background: #1a202c;
  color: #e2e8f0;
  padding: 8px;
  border-radius: 4px;
  font-size: 11px;
  overflow-x: auto;
  max-height: 200px;
  overflow-y: auto;
}

.result-data::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

.result-data::-webkit-scrollbar-thumb {
  background: #4a5568;
  border-radius: 3px;
}

.result-data pre {
  margin: 0;
  font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
  line-height: 1.4;
}
</style>
