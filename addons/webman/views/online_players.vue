<template>
  <div class="online-players-container">
    <a-tabs v-model:activeKey="activeTab" @change="onTabChange">
      <!-- 实体机台在线玩家 -->
      <a-tab-pane key="machine" tab="实体机台在线玩家">
        <a-card :title="`实体机台在线玩家 (${machinePlayers.length}人在线)`" :bordered="false">
          <template #extra>
            <a-space>
              <a-tag color="green">实时更新</a-tag>
              <a-tag color="blue">最后更新: {{ lastMachineUpdateTime }}</a-tag>
              <a-button type="primary" size="small" @click="refreshMachinePlayers">
                刷新
              </a-button>
            </a-space>
          </template>

          <a-empty v-if="!machineLoading && machinePlayers.length === 0" description="暂无在线玩家（最近10秒内无押注记录）" />

          <a-table
            v-else
            :columns="machineColumns"
            :data-source="machinePlayers"
            :loading="machineLoading"
            :pagination="{ pageSize: 20 }"
            :scroll="{ x: 1200 }"
            row-key="id"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.key === 'player_info'">
                <a-space>
                  <a-avatar :src="record.avatar" v-if="record.avatar" />
                  <a-avatar v-else />
                  <div>
                    <div>{{ record.name }}</div>
                    <div style="color: #999; font-size: 12px;">{{ record.phone }}</div>
                  </div>
                </a-space>
              </template>

              <template v-if="column.key === 'machine_info'">
                <div v-if="record.machine_name">
                  <div>{{ record.machine_name }}</div>
                  <div style="color: #999; font-size: 12px;">编号: {{ record.machine_code }}</div>
                </div>
                <span v-else>-</span>
              </template>

              <template v-if="column.key === 'last_bet_time'">
                <div>
                  <div>{{ record.last_bet_time }}</div>
                  <a-tag color="green" style="margin-top: 4px;">{{ record.bet_seconds_ago }}秒前</a-tag>
                </div>
              </template>

              <template v-if="column.key === 'status'">
                <a-tag color="green">游戏中</a-tag>
              </template>

              <template v-if="column.key === 'action'">
                <a-button type="primary" size="small" @click="showGrantModal(record)">
                  发放彩金
                </a-button>
              </template>
            </template>
          </a-table>
        </a-card>
      </a-tab-pane>

      <!-- 电子游戏在线玩家 -->
      <a-tab-pane key="game" tab="电子游戏在线玩家">
        <a-card :title="`电子游戏在线玩家 (${gamePlayers.length}人在线)`" :bordered="false">
          <template #extra>
            <a-space>
              <a-tag color="green">实时更新</a-tag>
              <a-tag color="blue">最后更新: {{ lastGameUpdateTime }}</a-tag>
              <a-button type="primary" size="small" @click="refreshGamePlayers">
                刷新
              </a-button>
            </a-space>
          </template>

          <a-empty v-if="!gameLoading && gamePlayers.length === 0" description="暂无在线玩家（最近10秒内无押注记录）" />

          <a-table
            v-else
            :columns="gameColumns"
            :data-source="gamePlayers"
            :loading="gameLoading"
            :pagination="{ pageSize: 20 }"
            :scroll="{ x: 1200 }"
            row-key="id"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.key === 'player_info'">
                <a-space>
                  <a-avatar :src="record.avatar" v-if="record.avatar" />
                  <a-avatar v-else />
                  <div>
                    <div>{{ record.name }}</div>
                    <div style="color: #999; font-size: 12px;">{{ record.phone }}</div>
                  </div>
                </a-space>
              </template>

              <template v-if="column.key === 'platform_info'">
                <div v-if="record.platform_name">{{ record.platform_name }}</div>
                <span v-else>-</span>
              </template>

              <template v-if="column.key === 'last_bet_time'">
                <div>
                  <div>{{ record.last_bet_time }}</div>
                  <a-tag color="green" style="margin-top: 4px;">{{ record.bet_seconds_ago }}秒前</a-tag>
                </div>
              </template>

              <template v-if="column.key === 'status'">
                <a-tag color="green">游戏中</a-tag>
              </template>

              <template v-if="column.key === 'action'">
                <a-button type="primary" size="small" @click="showGrantModal(record)">
                  发放彩金
                </a-button>
              </template>
            </template>
          </a-table>
        </a-card>
      </a-tab-pane>
    </a-tabs>

    <!-- 发放彩金弹窗 -->
    <a-modal
      v-model:visible="grantModalVisible"
      title="发放彩金"
      @ok="handleGrantLottery"
      @cancel="handleCancelGrant"
      :confirm-loading="grantLoading"
    >
      <a-form :model="grantForm" :label-col="{ span: 6 }" :wrapper-col="{ span: 18 }">
        <a-form-item label="玩家信息">
          <div>
            <div><strong>{{ selectedPlayer?.name }}</strong></div>
            <div style="color: #999; font-size: 12px;">UUID: {{ selectedPlayer?.uuid }}</div>
            <div style="color: #999; font-size: 12px;">手机: {{ selectedPlayer?.phone }}</div>
          </div>
        </a-form-item>

        <a-form-item label="选择彩金" required>
          <a-select
            v-model:value="grantForm.lottery_id"
            placeholder="请选择彩金类型"
            :options="lotteryOptions"
          />
        </a-form-item>

        <a-form-item label="发放金额" required>
          <a-input-number
            v-model:value="grantForm.amount"
            :min="1"
            :max="1000000"
            :precision="2"
            placeholder="请输入发放金额"
            style="width: 100%;"
          />
        </a-form-item>

        <a-form-item label="备注">
          <a-textarea
            v-model:value="grantForm.remark"
            :rows="3"
            placeholder="请输入发放原因或备注信息"
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script>
export default {
  name: 'OnlinePlayers',
  props: {
    lotteryOptions: {
      type: Array,
      default: () => []
    },
    wsUrl: {
      type: String,
      default: 'ws://127.0.0.1:3131'
    },
    appKey: {
      type: String,
      default: '20f94408fc4c52845f162e92a253c7a3'
    }
  },
  data() {
    return {
      activeTab: 'machine',
      machinePlayers: [],
      gamePlayers: [],
      machineLoading: false,
      gameLoading: false,
      lastMachineUpdateTime: '未更新',
      lastGameUpdateTime: '未更新',
      grantModalVisible: false,
      grantLoading: false,
      selectedPlayer: null,
      grantForm: {
        player_id: null,
        lottery_id: null,
        amount: null,
        remark: ''
      },
      machineChannelName: null,
      gameChannelName: null,
      reconnectTimer: null,
      cleanupTimer: null, // 自动清理定时器
      updateTimer: null, // 更新时间显示定时器
      machineColumns: [
        { title: 'ID', dataIndex: 'id', key: 'id', width: 80, align: 'center' },
        { title: '玩家信息', key: 'player_info', width: 200, align: 'center' },
        { title: 'UUID', dataIndex: 'uuid', key: 'uuid', width: 150, align: 'center' },
        { title: '当前机台', key: 'machine_info', width: 180, align: 'center' },
        { title: '最后押注时间', key: 'last_bet_time', width: 180, align: 'center' },
        { title: '累计押注', dataIndex: 'total_pressure', key: 'total_pressure', width: 120, align: 'center' },
        { title: '状态', key: 'status', width: 100, align: 'center' },
        { title: '操作', key: 'action', width: 150, align: 'center', fixed: 'right' },
      ],
      gameColumns: [
        { title: 'ID', dataIndex: 'id', key: 'id', width: 80, align: 'center' },
        { title: '玩家信息', key: 'player_info', width: 200, align: 'center' },
        { title: 'UUID', dataIndex: 'uuid', key: 'uuid', width: 150, align: 'center' },
        { title: '当前平台', key: 'platform_info', width: 150, align: 'center' },
        { title: '最后押注时间', key: 'last_bet_time', width: 180, align: 'center' },
        { title: '累计押注', dataIndex: 'total_bet', key: 'total_bet', width: 120, align: 'center' },
        { title: '状态', key: 'status', width: 100, align: 'center' },
        { title: '操作', key: 'action', width: 150, align: 'center', fixed: 'right' },
      ],
    };
  },
  created() {
    console.log('[在线玩家] created钩子执行', {
      wsUrl: this.wsUrl,
      appKey: this.appKey,
      hasScript: typeof this.$script
    });
    // 在 created 钩子中也尝试加载数据
    this.connectWebSocket();
  },
  mounted() {
    // 初始加载时同时获取两个Tab的数据
    try {
      this.loadMachinePlayers();
      this.loadGamePlayers();
    } catch (e) {
      console.error('[在线玩家] 调用加载方法失败:', e);
    }

    // 启动自动清理定时器，每2秒检查一次
    this.startCleanupTimer();

    // 启动更新时间显示定时器，每1秒更新一次
    this.startUpdateTimer();
  },
  beforeUnmount() {
    this.disconnectWebSocket();
    this.stopCleanupTimer();
    this.stopUpdateTimer();
  },
  methods: {
    // 加载实体机台玩家
    async loadMachinePlayers() {
      console.log('[在线玩家] 开始加载实体机台玩家');
      console.log('[在线玩家] this.$request 是否存在:', typeof this.$request);

      if (!this.$request) {
        console.error('[在线玩家] $request 方法不存在！');
        return;
      }

      this.machineLoading = true;
      try {
        const res = await this.$request({
          url: '/ex-admin/addons-webman-controller-OnlinePlayerLotteryController/getMachinePlayers',
          method: 'get'
        });
        console.log('[在线玩家] API响应:', res);
        if (res.code === 200) {
          this.machinePlayers = res.data;
          this.lastMachineUpdateTime = new Date().toLocaleTimeString();
          console.log('[在线玩家] 实体机台玩家加载成功，数量:', this.machinePlayers.length);
          console.log('[在线玩家] 玩家数据:', this.machinePlayers);
        } else {
          console.error('[在线玩家] API返回错误:', res);
        }
      } catch (error) {
        console.error('[在线玩家] 加载实体机台玩家失败:', error);
      } finally {
        this.machineLoading = false;
      }
    },

    // 加载电子游戏玩家
    async loadGamePlayers() {
      console.log('[在线玩家] 开始加载电子游戏玩家');
      this.gameLoading = true;
      try {
        const res = await this.$request({
          url: '/ex-admin/addons-webman-controller-OnlinePlayerLotteryController/getGamePlayers',
          method: 'get'
        });
        console.log('[在线玩家] API响应:', res);
        if (res.code === 200) {
          this.gamePlayers = res.data;
          this.lastGameUpdateTime = new Date().toLocaleTimeString();
          console.log('[在线玩家] 电子游戏玩家加载成功，数量:', this.gamePlayers.length);
          console.log('[在线玩家] 玩家数据:', this.gamePlayers);
        } else {
          console.error('[在线玩家] API返回错误:', res);
        }
      } catch (error) {
        console.error('[在线玩家] 加载电子游戏玩家失败:', error);
      } finally {
        this.gameLoading = false;
      }
    },

    // 刷新实体机台玩家
    refreshMachinePlayers() {
      this.loadMachinePlayers();
    },

    // 刷新电子游戏玩家
    refreshGamePlayers() {
      this.loadGamePlayers();
    },

    // Tab切换
    onTabChange(key) {
      if (key === 'game' && this.gamePlayers.length === 0) {
        this.loadGamePlayers();
      }
    },

    // 显示发放彩金弹窗
    showGrantModal(player) {
      this.selectedPlayer = player;
      this.grantForm = {
        player_id: player.id,
        lottery_id: null,
        amount: null,
        remark: ''
      };
      this.grantModalVisible = true;
    },

    // 取消发放
    handleCancelGrant() {
      this.grantModalVisible = false;
      this.selectedPlayer = null;
      this.grantForm = {
        player_id: null,
        lottery_id: null,
        amount: null,
        remark: ''
      };
    },

    // 发放彩金
    async handleGrantLottery() {
      if (!this.grantForm.lottery_id) {
        this.$message.error('请选择彩金类型');
        return;
      }
      if (!this.grantForm.amount || this.grantForm.amount <= 0) {
        this.$message.error('请输入有效的发放金额');
        return;
      }

      this.grantLoading = true;
      try {
        const res = await this.$request({
          url: '/ex-admin/addons-webman-controller-OnlinePlayerLotteryController/grantLottery',
          method: 'post',
          data: this.grantForm
        });

        if (res.code === 200) {
          this.$message.success('彩金发放成功');
          this.grantModalVisible = false;
          this.handleCancelGrant();
          // 刷新列表
          if (this.activeTab === 'machine') {
            this.refreshMachinePlayers();
          } else {
            this.refreshGamePlayers();
          }
        } else {
          this.$message.error(res.msg || '彩金发放失败');
        }
      } catch (error) {
        console.error('发放彩金失败:', error);
        this.$message.error('彩金发放失败');
      } finally {
        this.grantLoading = false;
      }
    },

    getPushManager() {
      // 优先使用 Vue 注入的实例，否则使用全局实例
      return this.$pushManager || window.$pushManager;
    },

    // 连接WebSocket
    async connectWebSocket() {
      try {
        if (!this.wsUrl) {
          console.error('[在线玩家] WebSocket URL未配置');
          return;
        }

        const pushManager = this.getPushManager();

        // 检查 pushManager 是否可用
        if (!pushManager) {
          console.error('[在线玩家] PushManager not available');
          return;
        }

        console.log('[在线玩家] 开始连接WebSocket', {
          wsUrl: this.wsUrl,
          appKey: this.appKey
        });

        // 初始化连接
        await pushManager.init(this.wsUrl);

        // 订阅实体机台频道
        this.machineChannelName = 'group-online-players-machine';
        pushManager.subscribe(this.machineChannelName, this.handleMachineMessage, this);
        console.log('[在线玩家] 订阅实体机台频道成功');

        // 订阅电子游戏频道
        this.gameChannelName = 'group-online-players-game';
        pushManager.subscribe(this.gameChannelName, this.handleGameMessage, this);
        console.log('[在线玩家] 订阅电子游戏频道成功');

        console.log('[在线玩家] WebSocket连接初始化成功');
      } catch (error) {
        console.error('[在线玩家] Init WebSocket failed:', error);
      }
    },

    // 处理实体机台消息
    handleMachineMessage(data) {
      try {
        console.log('[在线玩家] 收到实体机台消息:', data);
        const content = JSON.parse(data.content);
        console.log('[在线玩家] 解析后的消息内容:', content);

        if (content.msg_type === 'online_players_update' && content.type === 'machine') {
          console.log('[在线玩家] 更新实体机台玩家列表，数量:', content.players.length);
          // 使用 $nextTick 确保 Vue 响应式更新
          this.$nextTick(() => {
            this.machinePlayers = [...content.players];
            this.lastMachineUpdateTime = new Date().toLocaleTimeString();
            console.log('[在线玩家] 实体机台玩家列表已更新:', this.machinePlayers.length);
          });
        } else if (content.msg_type === 'player_betting' && content.type === 'machine') {
          console.log('[在线玩家] 玩家押注事件:', content.player);
          this.handlePlayerBetting(content, 'machine');
        } else if (content.msg_type === 'players_offline' && content.type === 'machine') {
          console.log('[在线玩家] 玩家离线事件:', content.player_ids);
          this.handlePlayersOffline(content.player_ids, 'machine');
        }
      } catch (e) {
        console.error('[在线玩家] 解析实体机台消息失败:', e, data);
      }
    },

    // 处理电子游戏消息
    handleGameMessage(data) {
      try {
        console.log('[在线玩家] 收到电子游戏消息:', data);
        const content = JSON.parse(data.content);
        console.log('[在线玩家] 解析后的消息内容:', content);

        if (content.msg_type === 'online_players_update' && content.type === 'game') {
          console.log('[在线玩家] 更新电子游戏玩家列表，数量:', content.players.length);
          // 使用 $nextTick 确保 Vue 响应式更新
          this.$nextTick(() => {
            this.gamePlayers = [...content.players];
            this.lastGameUpdateTime = new Date().toLocaleTimeString();
            console.log('[在线玩家] 电子游戏玩家列表已更新:', this.gamePlayers.length);
          });
        } else if (content.msg_type === 'player_betting' && content.type === 'game') {
          console.log('[在线玩家] 玩家押注事件:', content.player);
          this.handlePlayerBetting(content, 'game');
        } else if (content.msg_type === 'players_offline' && content.type === 'game') {
          console.log('[在线玩家] 玩家离线事件:', content.player_ids);
          this.handlePlayersOffline(content.player_ids, 'game');
        }
      } catch (e) {
        console.error('[在线玩家] 解析电子游戏消息失败:', e, data);
      }
    },

    // 断开WebSocket
    disconnectWebSocket() {
      const pushManager = this.getPushManager();
      if (this.machineChannelName && pushManager) {
        pushManager.unsubscribe(this.machineChannelName, this.handleMachineMessage);
        this.machineChannelName = null;
      }
      if (this.gameChannelName && pushManager) {
        pushManager.unsubscribe(this.gameChannelName, this.handleGameMessage);
        this.gameChannelName = null;
      }
    },

    // 处理玩家押注事件 - 如果不在列表中就添加
    handlePlayerBetting(content, type) {
      console.log('[在线玩家] 玩家押注事件', { player: content.player, type });

      if (!content.player) {
        console.error('[在线玩家] 消息中缺少玩家数据');
        return;
      }

      const playerList = type === 'machine' ? this.machinePlayers : this.gamePlayers;
      const existingIndex = playerList.findIndex(p => p.id === content.player.id);

      if (existingIndex >= 0) {
        // 玩家已存在，更新数据
        console.log('[在线玩家] 更新现有玩家:', content.player.id);
        playerList[existingIndex] = content.player;
      } else {
        // 玩家不存在，添加到列表
        console.log('[在线玩家] 添加新玩家:', content.player.id);
        playerList.unshift(content.player); // 添加到列表开头
      }

      // 触发响应式更新
      this.$nextTick(() => {
        if (type === 'machine') {
          this.machinePlayers = [...playerList];
          this.lastMachineUpdateTime = new Date().toLocaleTimeString();
        } else {
          this.gamePlayers = [...playerList];
          this.lastGameUpdateTime = new Date().toLocaleTimeString();
        }
      });
    },

    // 处理玩家离线事件 - 从列表中移除
    handlePlayersOffline(playerIds, type) {
      console.log('[在线玩家] 玩家离线事件', { player_ids: playerIds, type });

      this.$nextTick(() => {
        if (type === 'machine') {
          this.machinePlayers = this.machinePlayers.filter(p => !playerIds.includes(p.id));
          this.lastMachineUpdateTime = new Date().toLocaleTimeString();
          console.log('[在线玩家] 实体机台玩家离线已处理，剩余:', this.machinePlayers.length);
        } else {
          this.gamePlayers = this.gamePlayers.filter(p => !playerIds.includes(p.id));
          this.lastGameUpdateTime = new Date().toLocaleTimeString();
          console.log('[在线玩家] 电子游戏玩家离线已处理，剩余:', this.gamePlayers.length);
        }
      });
    },

    // 启动自动清理定时器
    startCleanupTimer() {
      this.cleanupTimer = setInterval(() => {
        this.cleanupOfflinePlayers();
      }, 2000); // 每2秒检查一次
    },

    // 停止自动清理定时器
    stopCleanupTimer() {
      if (this.cleanupTimer) {
        clearInterval(this.cleanupTimer);
        this.cleanupTimer = null;
      }
    },

    // 启动更新时间显示定时器
    startUpdateTimer() {
      this.updateTimer = setInterval(() => {
        this.updateBetSecondsAgo();
      }, 1000); // 每1秒更新一次
    },

    // 停止更新时间显示定时器
    stopUpdateTimer() {
      if (this.updateTimer) {
        clearInterval(this.updateTimer);
        this.updateTimer = null;
      }
    },

    // 清理超过10秒未押注的玩家
    cleanupOfflinePlayers() {
      const now = Math.floor(Date.now() / 1000);

      // 清理实体机台玩家
      const beforeMachineCount = this.machinePlayers.length;
      this.machinePlayers = this.machinePlayers.filter(player => {
        const lastBetTimestamp = new Date(player.last_bet_time).getTime() / 1000;
        const secondsAgo = now - lastBetTimestamp;
        return secondsAgo <= 10;
      });

      if (beforeMachineCount !== this.machinePlayers.length) {
        console.log('[在线玩家] 自动清理实体机台离线玩家', {
          before: beforeMachineCount,
          after: this.machinePlayers.length,
          removed: beforeMachineCount - this.machinePlayers.length
        });
      }

      // 清理电子游戏玩家
      const beforeGameCount = this.gamePlayers.length;
      this.gamePlayers = this.gamePlayers.filter(player => {
        const lastBetTimestamp = new Date(player.last_bet_time).getTime() / 1000;
        const secondsAgo = now - lastBetTimestamp;
        return secondsAgo <= 10;
      });

      if (beforeGameCount !== this.gamePlayers.length) {
        console.log('[在线玩家] 自动清理电子游戏离线玩家', {
          before: beforeGameCount,
          after: this.gamePlayers.length,
          removed: beforeGameCount - this.gamePlayers.length
        });
      }
    },

    // 更新玩家的 bet_seconds_ago 显示
    updateBetSecondsAgo() {
      const now = Math.floor(Date.now() / 1000);

      // 更新实体机台玩家
      this.machinePlayers.forEach(player => {
        const lastBetTimestamp = new Date(player.last_bet_time).getTime() / 1000;
        player.bet_seconds_ago = now - lastBetTimestamp;
      });

      // 更新电子游戏玩家
      this.gamePlayers.forEach(player => {
        const lastBetTimestamp = new Date(player.last_bet_time).getTime() / 1000;
        player.bet_seconds_ago = now - lastBetTimestamp;
      });
    }
  }
};
</script>

<style scoped>
.online-players-container {
  padding: 20px;
  background: #f0f2f5;
}
</style>