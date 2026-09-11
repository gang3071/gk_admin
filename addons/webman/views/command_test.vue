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
          </a-card>
        </a-space>
      </a-tab-pane>
    </a-tabs>

    <!-- 执行结果 -->
    <a-card
      v-if="results.length > 0"
      :bordered="false"
      class="result-card"
      title="📊 执行结果"
    >
      <div class="result-container">
        <div
          v-for="(result, index) in results"
          :key="index"
          :class="result.success ? 'success' : 'error'"
          class="result-item"
        >
          <div class="result-header">
            <span class="result-time">{{ result.time }}</span>
            <span class="result-title">{{ result.cmdName }}</span>
            <a-tag :color="result.success ? 'success' : 'error'">
              {{ result.success ? '✅ 成功' : '❌ 失败' }}
            </a-tag>
          </div>
          <div class="result-cmd">指令: {{ result.cmd }}</div>
          <div v-if="result.message" class="result-message">{{ result.message }}</div>
          <div v-if="result.data" class="result-data">
            <pre>{{ JSON.stringify(result.data, null, 2) }}</pre>
          </div>
        </div>
      </div>
      <div style="text-align: center; margin-top: 16px;">
        <a-button size="small" @click="clearResults">
          <template #icon><ClearOutlined /></template>
          清空记录
        </a-button>
      </div>
    </a-card>
  </div>
</template>

<script>
import {ref} from 'vue'
import {message} from 'ant-design-vue'
import {ApiOutlined, ClearOutlined, ThunderboltOutlined} from '@ant-design/icons-vue'

export default {
  name: 'CommandTest',
  components: {
    ApiOutlined,
    ThunderboltOutlined,
    ClearOutlined
  },
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
  setup(props) {
    const activeTab = ref(Object.keys(props.command_list)[0])
    const loading = ref(false)
    const currentCommand = ref('')
    const results = ref([])

    const sendCommand = async (cmd) => {
      currentCommand.value = cmd.cmd
      loading.value = true

      // 危险指令二次确认
      if (cmd.danger) {
        const confirmed = window.confirm(
          `⚠️ 警告：${cmd.name}\n\n` +
          `${cmd.desc}\n\n` +
          `确定要执行此指令吗？`
        )
        if (!confirmed) {
          loading.value = false
          currentCommand.value = ''
          return
        }
      }

      try {
        const response = await fetch('/admin/ex-admin/addons-webman-controller-AdminOfflineMachineController/sendCommand', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            machine_id: props.machine_id,
            cmd: cmd.cmd,
            cmd_name: cmd.name
          })
        })

        const result = await response.json()

        // 添加结果到列表（最新的在最前面）
        results.value.unshift({
          time: new Date().toLocaleString('zh-CN'),
          cmdName: cmd.name,
          cmd: cmd.cmd,
          success: result.code === 1,
          message: result.msg,
          data: result.data
        })

        if (result.code === 1) {
          message.success(`指令执行成功: ${cmd.name}`)
        } else {
          message.error(`指令执行失败: ${result.msg || '未知错误'}`)
        }

      } catch (error) {
        results.value.unshift({
          time: new Date().toLocaleString('zh-CN'),
          cmdName: cmd.name,
          cmd: cmd.cmd,
          success: false,
          message: error.message,
          data: null
        })
        message.error(`请求失败: ${error.message}`)
      } finally {
        loading.value = false
        currentCommand.value = ''
      }
    }

    const clearResults = () => {
      results.value = []
      message.info('已清空执行记录')
    }

    return {
      activeTab,
      loading,
      currentCommand,
      results,
      commandList: props.command_list,
      sendCommand,
      clearResults
    }
  }
}
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

.result-card {
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.result-container {
  max-height: 500px;
  overflow-y: auto;
}

.result-item {
  padding: 16px;
  margin-bottom: 12px;
  border-radius: 8px;
  border-left: 4px solid;
}

.result-item.success {
  background: #f6ffed;
  border-left-color: #52c41a;
}

.result-item.error {
  background: #fff2f0;
  border-left-color: #ff4d4f;
}

.result-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.result-time {
  color: #8c8c8c;
  font-size: 12px;
}

.result-title {
  font-weight: 600;
  font-size: 14px;
}

.result-cmd {
  font-size: 13px;
  color: #595959;
  font-family: 'Courier New', monospace;
  margin-bottom: 8px;
}

.result-message {
  font-size: 14px;
  margin-bottom: 8px;
}

.result-data {
  background: #1a202c;
  color: #e2e8f0;
  padding: 12px;
  border-radius: 4px;
  font-size: 12px;
  overflow-x: auto;
}

.result-data pre {
  margin: 0;
  font-family: 'Courier New', monospace;
}
</style>
