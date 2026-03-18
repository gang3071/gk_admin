<template>
  <div>
    <a-tag color="processing" v-model="machine_status" v-if="isOnline">
      <template #icon>
        <sync-outlined :spin="true"/>
      </template>
      在线
    </a-tag>
    <a-tag color="default" v-model="machine_status" v-else>
      <template #icon>
        <minus-circle-outlined/>
      </template>
      离线
    </a-tag>
  </div>
</template>
<script>
export default {
  name: "machine_status.vue",
  props: {
    id: String,
    type: String,
    department_id: String,
    ws: String,
    machine_status: String,
  },
  data() {
    return {
      isOnline: false,
      channelName: null,
    };
  },
  created() {
    if (this.machine_status === 'online') {
      this.isOnline = true;
    }
    if (this.ws) {
      this.initWebSocket();
    }
  },
  beforeUnmount() {
    // 只取消订阅频道，不断开连接
    const pushManager = this.getPushManager();
    if (this.channelName && pushManager) {
      pushManager.unsubscribe(this.channelName, this.handleMessage);
      this.channelName = null;
    }
  },
  methods: {
    getPushManager() {
      // 优先使用 Vue 注入的实例，否则使用全局实例
      return this.$pushManager || window.$pushManager;
    },

    async initWebSocket() {
      try {
        const pushManager = this.getPushManager();

        // 检查 pushManager 是否可用
        if (!pushManager) {
          console.error('[MachineStatus] PushManager not available');
          return;
        }

        // 初始化连接（单例）
        await pushManager.init(this.ws);

        // 订阅频道
        this.channelName = `private-admin_group-${this.type}-${this.department_id}-${this.id}`;
        pushManager.subscribe(this.channelName, this.handleMessage, this);

        console.log('[MachineStatus] WebSocket initialized successfully');
      } catch (error) {
        console.error('[MachineStatus] Init WebSocket failed:', error);
      }
    },

    handleMessage(data) {
      try {
        const content = JSON.parse(data.content);
        if (content.msg_type === 'machine_now_status') {
          this.isOnline = content.machine_status === 'online';
        }
      } catch (e) {
        console.warn('[MachineStatus] Parse message failed:', e);
      }
    }
  }
}
</script>