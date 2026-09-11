<template>
  <div class="command-test-container">
    <!-- 机台信息卡片 -->
    <a-card :bordered="false" class="machine-info-card">
      <template #title>
        <span style="font-size: 20px; font-weight: 600;">
          <ApiOutlined /> {{ machine_code }} - 指令测试
        </span>
      </template>
      <a-descriptions :column="4" size="small">
        <a-descriptions-item label="机台名称">
          <strong>{{ machine_name }}</strong>
        </a-descriptions-item>
        <a-descriptions-item label="控制类型">
          <a-tag color="orange">{{ control_type_name }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item label="游戏类型">
          <a-tag color="blue">{{ game_type_name }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item label="机台来源">
          <a-tag color="purple">线下版</a-tag>
        </a-descriptions-item>
      </a-descriptions>
    </a-card>

    <!-- 指令分类 -->
    <a-tabs v-model:activeKey="activeTab" class="command-tabs" type="card">
      <a-tab-pane
        v-for="(commands, category) in commandList"
        :key="category"
        :tab="category"
      >
        <a-space :size="12" direction="vertical" style="width: 100%;">
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
    }
  },
  data() {
    return {
      activeTab: Object.keys(this.command_list)[0] || '',
      loading: false,
      currentCommand: '',
      cmdResults: {}, // ✅ 改为对象结构：{ 'cmd1': [result1, result2], 'cmd2': [...] }
      commandList: this.command_list
    };
  },
  methods: {
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

      // 危险指令二次确认
      if (cmd.danger) {
        const confirmed = window.confirm(
          `⚠️ 警告：${cmd.name}\n\n` +
          `${cmd.desc}\n\n` +
          `确定要执行此指令吗？`
        );
        if (!confirmed) {
          this.loading = false;
          this.currentCommand = '';
          return;
        }
      }

      try {
        const response = await fetch('/ex-admin/addons-webman-controller-AdminOfflineMachineController/sendCommand', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            machine_id: this.machine_id,
            cmd: cmd.cmd,
            cmd_name: cmd.name
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
          this.$message.success(`指令执行成功: ${cmd.name}`);
        } else {
          this.$message.error(`指令执行失败: ${result.msg || '未知错误'}`);
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

        this.$message.error(`请求失败: ${error.message}`);
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
  padding: 20px;
  background: #f0f2f5;
  min-height: 100vh;
}

.machine-info-card {
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.machine-info-card :deep(.ant-card-head) {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.machine-info-card :deep(.ant-card-head-title) {
  color: white;
}

.command-tabs {
  background: white;
  padding: 16px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  margin-bottom: 20px;
}

.command-card {
  transition: all 0.3s;
  border: 1px solid #e8e8e8;
}

.command-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  transform: translateY(-2px);
}

.danger-command {
  border-color: #ffccc7;
  background: #fff2f0;
}

.command-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.command-info {
  flex: 1;
}

.command-name {
  font-size: 16px;
  font-weight: 600;
  color: #1a202c;
  margin-bottom: 8px;
}

.command-code {
  margin-bottom: 8px;
}

.command-desc {
  font-size: 14px;
  color: #718096;
}

.command-action {
  margin-left: 20px;
}

/* ✅ 指令内部的结果区域 */
.cmd-results {
  margin-top: 16px;
}

.result-list {
  max-height: 400px;
  overflow-y: auto;
}

.result-item {
  padding: 12px;
  margin-bottom: 8px;
  border-radius: 6px;
  border-left: 3px solid;
  background: #fafafa;
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
  animation: highlight 1.2s ease-in-out;
}

@keyframes highlight {
  0% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(24, 144, 255, 0.4);
  }
  50% {
    transform: scale(1.02);
    box-shadow: 0 0 0 4px rgba(24, 144, 255, 0.2);
  }
  100% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(24, 144, 255, 0);
  }
}

.result-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.result-time {
  color: #8c8c8c;
  font-size: 11px;
}

.result-message {
  font-size: 13px;
  color: #595959;
  margin-bottom: 8px;
  line-height: 1.5;
}

.result-data {
  background: #1a202c;
  color: #e2e8f0;
  padding: 10px;
  border-radius: 4px;
  font-size: 11px;
  overflow-x: auto;
  max-height: 300px;
  overflow-y: auto;
}

.result-data pre {
  margin: 0;
  font-family: 'Courier New', monospace;
  line-height: 1.4;
}
</style>
