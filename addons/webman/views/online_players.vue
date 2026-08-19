<template>
  <div class="online-players-container">
    <a-tabs v-model:activeKey="activeTab" @change="onTabChange">
      <!-- 电子游戏在线玩家 -->
      <a-tab-pane key="game" :tab="t('tab.game_online_players')">
        <a-card :bordered="false" :title="t('card.game_title', { count: gamePlayers.length })">
          <template #extra>
            <a-space>
              <a-tag color="green">{{ t('tag.realtime_update') }}</a-tag>
              <a-tag color="blue">{{ t('tag.last_update', { time: lastGameUpdateTime }) }}</a-tag>
              <a-button size="small" type="primary" @click="refreshGamePlayers">
                {{ t('button.refresh') }}
              </a-button>
            </a-space>
          </template>

          <a-empty v-if="!gameLoading && gamePlayers.length === 0" :description="t('empty.no_online_players')" />

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
                  <a-tag color="green" style="margin-top: 4px;">{{ t('tag.seconds_ago', { seconds: record.bet_seconds_ago }) }}</a-tag>
                </div>
              </template>

              <template v-if="column.key === 'status'">
                <a-tag color="green">{{ t('tag.playing') }}</a-tag>
              </template>

              <template v-if="column.key === 'action'">
                <a-button type="primary" size="small" @click="showGrantModal(record)">
                  {{ t('button.grant_lottery') }}
                </a-button>
              </template>
            </template>
          </a-table>
        </a-card>
      </a-tab-pane>

      <!-- 实体机台在线玩家 -->
      <a-tab-pane key="machine" :tab="t('tab.machine_online_players')">
        <a-card :bordered="false" :title="t('card.machine_title', { count: machinePlayers.length })">
          <template #extra>
            <a-space>
              <a-tag color="green">{{ t('tag.realtime_update') }}</a-tag>
              <a-tag color="blue">{{ t('tag.last_update', { time: lastMachineUpdateTime }) }}</a-tag>
              <a-button size="small" type="primary" @click="refreshMachinePlayers">
                {{ t('button.refresh') }}
              </a-button>
            </a-space>
          </template>

          <a-empty v-if="!machineLoading && machinePlayers.length === 0" :description="t('empty.no_online_players')" />

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
                  <div style="color: #999; font-size: 12px;">{{ t('display.code_prefix', { code: record.machine_code }) }}</div>
                </div>
                <span v-else>-</span>
              </template>

              <template v-if="column.key === 'last_bet_time'">
                <div>
                  <div>{{ record.last_bet_time }}</div>
                  <a-tag color="green" style="margin-top: 4px;">{{ t('tag.seconds_ago', { seconds: record.bet_seconds_ago }) }}</a-tag>
                </div>
              </template>

              <template v-if="column.key === 'status'">
                <a-tag color="green">{{ t('tag.playing') }}</a-tag>
              </template>

              <template v-if="column.key === 'action'">
                <a-button type="primary" size="small" @click="showGrantModal(record)">
                  {{ t('button.grant_lottery') }}
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
      :title="t('modal.grant_lottery')"
      @ok="handleGrantLottery"
      @cancel="handleCancelGrant"
      :confirm-loading="grantLoading"
    >
      <a-form :model="grantForm" :label-col="{ span: 6 }" :wrapper-col="{ span: 18 }">
        <a-form-item :label="t('modal.player_info')">
          <div>
            <div><strong>{{ selectedPlayer?.name }}</strong></div>
            <div style="color: #999; font-size: 12px;">UUID: {{ selectedPlayer?.uuid }}</div>
            <div style="color: #999; font-size: 12px;">{{ selectedPlayer?.phone }}</div>
          </div>
        </a-form-item>

        <a-form-item :label="t('modal.select_lottery')" required>
          <a-select
            v-model:value="grantForm.lottery_id"
            :placeholder="t('placeholder.select_lottery')"
            :options="lotteryOptions"
          />
        </a-form-item>

        <a-form-item :label="t('modal.grant_amount')" required>
          <a-input-number
            v-model:value="grantForm.amount"
            :min="1"
            :max="1000000"
            :precision="2"
            :placeholder="t('placeholder.input_amount')"
            style="width: 100%;"
          />
        </a-form-item>

        <a-form-item :label="t('modal.remark')">
          <a-textarea
            v-model:value="grantForm.remark"
            :rows="3"
            :placeholder="t('placeholder.input_remark')"
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script>
const messages = {
  'zh-CN': {
    tab: {
      game_online_players: '电子游戏在线玩家',
      machine_online_players: '实体机台在线玩家',
    },
    card: {
      game_title: '电子游戏在线玩家 ({count}人在线)',
      machine_title: '实体机台在线玩家 ({count}人在线)',
    },
    tag: {
      realtime_update: '实时更新',
      last_update: '最后更新: {time}',
      playing: '游戏中',
      seconds_ago: '{seconds}秒前',
    },
    button: {
      refresh: '刷新',
      grant_lottery: '发放彩金',
    },
    empty: {
      no_online_players: '暂无在线玩家（最近1分钟内无押注记录）',
    },
    columns: {
      id: 'ID',
      player_info: '玩家信息',
      uuid: 'UUID',
      current_machine: '当前机台',
      current_platform: '当前平台',
      last_bet_time: '最后押注时间',
      total_pressure: '累计押注',
      total_bet: '累计押注',
      status: '状态',
      action: '操作',
    },
    display: {
      code_prefix: '编号: {code}',
    },
    modal: {
      grant_lottery: '发放彩金',
      player_info: '玩家信息',
      select_lottery: '选择彩金',
      grant_amount: '发放金额',
      remark: '备注',
    },
    placeholder: {
      select_lottery: '请选择彩金类型',
      input_amount: '请输入发放金额',
      input_remark: '请输入发放原因或备注信息',
    },
    validation_msg: {
      select_lottery: '请选择彩金类型',
      input_valid_amount: '请输入有效的发放金额',
      grant_success: '彩金发放成功',
      grant_failed: '彩金发放失败',
    },
    default: {
      not_updated: '未更新',
    },
  },
  en: {
    tab: {
      game_online_players: 'Online Game Players',
      machine_online_players: 'Machine Online Players',
    },
    card: {
      game_title: 'Online Game Players ({count} online)',
      machine_title: 'Machine Online Players ({count} online)',
    },
    tag: {
      realtime_update: 'Real-time',
      last_update: 'Last update: {time}',
      playing: 'Playing',
      seconds_ago: '{seconds}s ago',
    },
    button: {
      refresh: 'Refresh',
      grant_lottery: 'Grant Lottery',
    },
    empty: {
      no_online_players: 'No online players (no bet records in the last 1 minute)',
    },
    columns: {
      id: 'ID',
      player_info: 'Player Info',
      uuid: 'UUID',
      current_machine: 'Current Machine',
      current_platform: 'Current Platform',
      last_bet_time: 'Last Bet Time',
      total_pressure: 'Total Bets',
      total_bet: 'Total Bets',
      status: 'Status',
      action: 'Action',
    },
    display: {
      code_prefix: 'Code: {code}',
    },
    modal: {
      grant_lottery: 'Grant Lottery',
      player_info: 'Player Info',
      select_lottery: 'Select Lottery',
      grant_amount: 'Grant Amount',
      remark: 'Remark',
    },
    placeholder: {
      select_lottery: 'Please select lottery type',
      input_amount: 'Please enter grant amount',
      input_remark: 'Please enter reason or remark',
    },
    validation_msg: {
      select_lottery: 'Please select lottery type',
      input_valid_amount: 'Please enter a valid amount',
      grant_success: 'Lottery granted successfully',
      grant_failed: 'Lottery grant failed',
    },
    default: {
      not_updated: 'Not updated',
    },
  },
  jp: {
    tab: {
      game_online_players: 'オンラインゲームプレイヤー',
      machine_online_players: 'マシンオンラインプレイヤー',
    },
    card: {
      game_title: 'オンラインゲームプレイヤー ({count}人オンライン)',
      machine_title: 'マシンオンラインプレイヤー ({count}人オンライン)',
    },
    tag: {
      realtime_update: 'リアルタイム更新',
      last_update: '最終更新: {time}',
      playing: 'プレイ中',
      seconds_ago: '{seconds}秒前',
    },
    button: {
      refresh: '更新',
      grant_lottery: '宝くじ付与',
    },
    empty: {
      no_online_players: 'オンラインプレイヤーがいません（直近1分以内にベット記録なし）',
    },
    columns: {
      id: 'ID',
      player_info: 'プレイヤー情報',
      uuid: 'UUID',
      current_machine: '現在のマシン',
      current_platform: '現在のプラットフォーム',
      last_bet_time: '最終ベット時間',
      total_pressure: '累計ベット',
      total_bet: '累計ベット',
      status: 'ステータス',
      action: 'アクション',
    },
    display: {
      code_prefix: 'コード: {code}',
    },
    modal: {
      grant_lottery: '宝くじ付与',
      player_info: 'プレイヤー情報',
      select_lottery: '宝くじ選択',
      grant_amount: '付与金額',
      remark: '備考',
    },
    placeholder: {
      select_lottery: '宝くじタイプを選択してください',
      input_amount: '付与金額を入力してください',
      input_remark: '理由または備考を入力してください',
    },
    validation_msg: {
      select_lottery: '宝くじタイプを選択してください',
      input_valid_amount: '有効な金額を入力してください',
      grant_success: '宝くじ付与成功',
      grant_failed: '宝くじ付与失敗',
    },
    default: {
      not_updated: '未更新',
    },
  },
  'zh-TW': {
    tab: {
      game_online_players: '電子遊戲在線玩家',
      machine_online_players: '實體機台在線玩家',
    },
    card: {
      game_title: '電子遊戲在線玩家 ({count}人在線)',
      machine_title: '實體機台在線玩家 ({count}人在線)',
    },
    tag: {
      realtime_update: '實時更新',
      last_update: '最後更新: {time}',
      playing: '遊戲中',
      seconds_ago: '{seconds}秒前',
    },
    button: {
      refresh: '刷新',
      grant_lottery: '發放彩金',
    },
    empty: {
      no_online_players: '暫無在線玩家（最近1分鐘內無押注記錄）',
    },
    columns: {
      id: 'ID',
      player_info: '玩家信息',
      uuid: 'UUID',
      current_machine: '當前機台',
      current_platform: '當前平台',
      last_bet_time: '最後押注時間',
      total_pressure: '累計押注',
      total_bet: '累計押注',
      status: '狀態',
      action: '操作',
    },
    display: {
      code_prefix: '編號: {code}',
    },
    modal: {
      grant_lottery: '發放彩金',
      player_info: '玩家信息',
      select_lottery: '選擇彩金',
      grant_amount: '發放金額',
      remark: '備註',
    },
    placeholder: {
      select_lottery: '請選擇彩金類型',
      input_amount: '請輸入發放金額',
      input_remark: '請輸入發放原因或備註信息',
    },
    validation_msg: {
      select_lottery: '請選擇彩金類型',
      input_valid_amount: '請輸入有效的發放金額',
      grant_success: '彩金發放成功',
      grant_failed: '彩金發放失敗',
    },
    default: {
      not_updated: '未更新',
    },
  },
};

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
    },
    langLocale: {
      type: String,
      default: 'zh-TW'
    }
  },
  data() {
    return {
      activeTab: 'machine',
      machinePlayers: [],
      gamePlayers: [],
      machineLoading: false,
      gameLoading: false,
      lastMachineUpdateTime: messages[this.langLocale]?.default?.not_updated || '未更新',
      lastGameUpdateTime: messages[this.langLocale]?.default?.not_updated || '未更新',
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
      cleanupTimer: null,
      updateTimer: null,
      machineColumns: [
        { title: messages[this.langLocale]?.columns?.id || 'ID', dataIndex: 'id', key: 'id', width: 80, align: 'center' },
        { title: messages[this.langLocale]?.columns?.player_info || '玩家信息', key: 'player_info', width: 200, align: 'center' },
        { title: messages[this.langLocale]?.columns?.uuid || 'UUID', dataIndex: 'uuid', key: 'uuid', width: 150, align: 'center' },
        { title: messages[this.langLocale]?.columns?.current_machine || '當前機台', key: 'machine_info', width: 180, align: 'center' },
        { title: messages[this.langLocale]?.columns?.last_bet_time || '最後押注時間', key: 'last_bet_time', width: 180, align: 'center' },
        { title: messages[this.langLocale]?.columns?.total_pressure || '累計押注', dataIndex: 'total_pressure', key: 'total_pressure', width: 120, align: 'center' },
        { title: messages[this.langLocale]?.columns?.status || '狀態', key: 'status', width: 100, align: 'center' },
        { title: messages[this.langLocale]?.columns?.action || '操作', key: 'action', width: 150, align: 'center', fixed: 'right' },
      ],
      gameColumns: [
        { title: messages[this.langLocale]?.columns?.id || 'ID', dataIndex: 'id', key: 'id', width: 80, align: 'center' },
        { title: messages[this.langLocale]?.columns?.player_info || '玩家信息', key: 'player_info', width: 200, align: 'center' },
        { title: messages[this.langLocale]?.columns?.uuid || 'UUID', dataIndex: 'uuid', key: 'uuid', width: 150, align: 'center' },
        { title: messages[this.langLocale]?.columns?.current_platform || '當前平台', key: 'platform_info', width: 150, align: 'center' },
        { title: messages[this.langLocale]?.columns?.last_bet_time || '最後押注時間', key: 'last_bet_time', width: 180, align: 'center' },
        { title: messages[this.langLocale]?.columns?.total_bet || '累計押注', dataIndex: 'total_bet', key: 'total_bet', width: 120, align: 'center' },
        { title: messages[this.langLocale]?.columns?.status || '狀態', key: 'status', width: 100, align: 'center' },
        { title: messages[this.langLocale]?.columns?.action || '操作', key: 'action', width: 150, align: 'center', fixed: 'right' },
      ],
    };
  },
  created() {
    console.log('[在线玩家] 组件创建', {
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
    // 翻译方法
    t(key, params = {}) {
      const keys = key.split('.');
      let value = messages[this.langLocale];
      for (const k of keys) {
        if (value && typeof value === 'object') {
          value = value[k];
        } else {
          return key;
        }
      }
      if (typeof value !== 'string') {
        return key;
      }
      // 替换参数
      return value.replace(/\{(\w+)\}/g, (match, paramKey) => {
        return params[paramKey] !== undefined ? params[paramKey] : match;
      });
    },

    // 加载实体机台玩家
    async loadMachinePlayers() {

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
        if (res.code === 200) {
          this.machinePlayers = res.data;
          this.lastMachineUpdateTime = new Date().toLocaleTimeString();
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
      this.gameLoading = true;
      try {
        const res = await this.$request({
          url: '/ex-admin/addons-webman-controller-OnlinePlayerLotteryController/getGamePlayers',
          method: 'get'
        });
        if (res.code === 200) {
          this.gamePlayers = res.data;
          this.lastGameUpdateTime = new Date().toLocaleTimeString();
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
        this.$message.error(this.t('validation_msg.select_lottery'));
        return;
      }
      if (!this.grantForm.amount || this.grantForm.amount <= 0) {
        this.$message.error(this.t('validation_msg.input_valid_amount'));
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
          this.$message.success(this.t('validation_msg.grant_success'));
          this.grantModalVisible = false;
          this.handleCancelGrant();
          // 刷新列表
          if (this.activeTab === 'machine') {
            this.refreshMachinePlayers();
          } else {
            this.refreshGamePlayers();
          }
        } else {
          this.$message.error(res.msg || this.t('validation_msg.grant_failed'));
        }
      } catch (error) {
        console.error(this.t('validation_msg.grant_failed'), error);
        this.$message.error(this.t('validation_msg.grant_failed'));
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

        console.log('[在线玩家] 连接WebSocket', {
          wsUrl: this.wsUrl,
          appKey: this.appKey
        });

        // 初始化连接
        await pushManager.init(this.wsUrl);

        // 订阅实体机台频道
        this.machineChannelName = 'group-online-players-machine';
        pushManager.subscribe(this.machineChannelName, this.handleMachineMessage, this);

        // 订阅电子游戏频道
        this.gameChannelName = 'group-online-players-game';
        pushManager.subscribe(this.gameChannelName, this.handleGameMessage, this);

      } catch (error) {
        console.error('[在线玩家] Init WebSocket failed:', error);
      }
    },

    // 处理实体机台消息
    handleMachineMessage(data) {
      try {
        const content = JSON.parse(data.content);

        if (content.msg_type === 'online_players_update' && content.type === 'machine') {
          // 智能更新：只更新变化的玩家，避免列表跳动
          this.$nextTick(() => {
            this.updateMachinePlayersList(content.players);
            this.lastMachineUpdateTime = new Date().toLocaleTimeString();
          });
        } else if (content.msg_type === 'player_betting' && content.type === 'machine') {
          this.handlePlayerBetting(content, 'machine');
        } else if (content.msg_type === 'players_offline' && content.type === 'machine') {
          this.handlePlayersOffline(content.player_ids, 'machine');
        }
      } catch (e) {
        console.error('[在线玩家] 解析实体机台消息失败:', e, data);
      }
    },

    // 处理电子游戏消息
    handleGameMessage(data) {
      try {
        const content = JSON.parse(data.content);

        if (content.msg_type === 'online_players_update' && content.type === 'game') {
          // 智能更新：只更新变化的玩家，避免列表跳动
          this.$nextTick(() => {
            this.updateGamePlayersList(content.players);
            this.lastGameUpdateTime = new Date().toLocaleTimeString();
          });
        } else if (content.msg_type === 'player_betting' && content.type === 'game') {
          this.handlePlayerBetting(content, 'game');
        } else if (content.msg_type === 'players_offline' && content.type === 'game') {
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

      if (!content.player) {
        console.error('[在线玩家] 消息中缺少玩家数据');
        return;
      }

      const playerList = type === 'machine' ? this.machinePlayers : this.gamePlayers;
      const existingIndex = playerList.findIndex(p => p.id === content.player.id);

      if (existingIndex >= 0) {
        // 玩家已存在，更新数据
        playerList[existingIndex] = content.player;
      } else {
        // 玩家不存在，添加到列表
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

      this.$nextTick(() => {
        if (type === 'machine') {
          this.machinePlayers = this.machinePlayers.filter(p => !playerIds.includes(p.id));
          this.lastMachineUpdateTime = new Date().toLocaleTimeString();
        } else {
          this.gamePlayers = this.gamePlayers.filter(p => !playerIds.includes(p.id));
          this.lastGameUpdateTime = new Date().toLocaleTimeString();
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

    // 清理超过60秒未押注的玩家（改为60秒，与后端一致）
    cleanupOfflinePlayers() {
      const now = Math.floor(Date.now() / 1000);
      const timeout = 60; // 60秒超时

      // 清理实体机台玩家（原地删除，避免重建数组）
      const beforeMachineCount = this.machinePlayers.length;
      for (let i = this.machinePlayers.length - 1; i >= 0; i--) {
        const player = this.machinePlayers[i];
        if (player.last_bet_time) {
          const lastBetTimestamp = new Date(player.last_bet_time).getTime() / 1000;
          const secondsAgo = now - lastBetTimestamp;
          if (secondsAgo > timeout) {
            this.machinePlayers.splice(i, 1);
          }
        }
      }

      if (beforeMachineCount !== this.machinePlayers.length) {
        console.log('[在线玩家] 清理实体机台离线玩家', {
          before: beforeMachineCount,
          after: this.machinePlayers.length,
          removed: beforeMachineCount - this.machinePlayers.length
        });
      }

      // 清理电子游戏玩家（原地删除，避免重建数组）
      const beforeGameCount = this.gamePlayers.length;
      for (let i = this.gamePlayers.length - 1; i >= 0; i--) {
        const player = this.gamePlayers[i];
        if (player.last_bet_time) {
          const lastBetTimestamp = new Date(player.last_bet_time).getTime() / 1000;
          const secondsAgo = now - lastBetTimestamp;
          if (secondsAgo > timeout) {
            this.gamePlayers.splice(i, 1);
          }
        }
      }

      if (beforeGameCount !== this.gamePlayers.length) {
        console.log('[在线玩家] 清理电子游戏离线玩家', {
          before: beforeGameCount,
          after: this.gamePlayers.length,
          removed: beforeGameCount - this.gamePlayers.length
        });
      }
    },

    // 智能更新实体机台玩家列表（避免跳动）
    updateMachinePlayersList(newPlayers) {
      // 如果当前列表为空，直接赋值
      if (this.machinePlayers.length === 0) {
        this.machinePlayers = newPlayers;
        return;
      }

      // 构建当前玩家Map（以ID为key）
      const currentMap = new Map();
      this.machinePlayers.forEach(player => {
        currentMap.set(player.id, player);
      });

      // 构建新玩家Map
      const newMap = new Map();
      newPlayers.forEach(player => {
        newMap.set(player.id, player);
      });

      // 1. 移除已离线的玩家
      this.machinePlayers = this.machinePlayers.filter(player => newMap.has(player.id));

      // 2. 更新现有玩家的信息（原地更新，避免对象引用变化）
      this.machinePlayers.forEach((player, index) => {
        const newPlayer = newMap.get(player.id);
        if (newPlayer) {
          // 只更新变化的字段
          Object.keys(newPlayer).forEach(key => {
            if (player[key] !== newPlayer[key]) {
              player[key] = newPlayer[key];
            }
          });
        }
      });

      // 3. 添加新玩家
      newPlayers.forEach(newPlayer => {
        if (!currentMap.has(newPlayer.id)) {
          this.machinePlayers.push(newPlayer);
        }
      });

      // 4. 按后端顺序重新排序（保持与后端一致）
      const orderMap = new Map();
      newPlayers.forEach((player, index) => {
        orderMap.set(player.id, index);
      });
      this.machinePlayers.sort((a, b) => {
        return (orderMap.get(a.id) || 999) - (orderMap.get(b.id) || 999);
      });
    },

    // 智能更新电子游戏玩家列表（避免跳动）
    updateGamePlayersList(newPlayers) {
      // 如果当前列表为空，直接赋值
      if (this.gamePlayers.length === 0) {
        this.gamePlayers = newPlayers;
        return;
      }

      // 构建当前玩家Map（以ID为key）
      const currentMap = new Map();
      this.gamePlayers.forEach(player => {
        currentMap.set(player.id, player);
      });

      // 构建新玩家Map
      const newMap = new Map();
      newPlayers.forEach(player => {
        newMap.set(player.id, player);
      });

      // 1. 移除已离线的玩家
      this.gamePlayers = this.gamePlayers.filter(player => newMap.has(player.id));

      // 2. 更新现有玩家的信息（原地更新，避免对象引用变化）
      this.gamePlayers.forEach((player, index) => {
        const newPlayer = newMap.get(player.id);
        if (newPlayer) {
          // 只更新变化的字段
          Object.keys(newPlayer).forEach(key => {
            if (player[key] !== newPlayer[key]) {
              player[key] = newPlayer[key];
            }
          });
        }
      });

      // 3. 添加新玩家
      newPlayers.forEach(newPlayer => {
        if (!currentMap.has(newPlayer.id)) {
          this.gamePlayers.push(newPlayer);
        }
      });

      // 4. 按后端顺序重新排序（保持与后端一致）
      const orderMap = new Map();
      newPlayers.forEach((player, index) => {
        orderMap.set(player.id, index);
      });
      this.gamePlayers.sort((a, b) => {
        return (orderMap.get(a.id) || 999) - (orderMap.get(b.id) || 999);
      });
    },

    // 更新玩家的 bet_seconds_ago 显示
    updateBetSecondsAgo() {
      const now = Math.floor(Date.now() / 1000);

      // 更新实体机台玩家（使用 Vue.set 确保响应式）
      this.machinePlayers.forEach(player => {
        if (player.last_bet_time) {
          const lastBetTimestamp = new Date(player.last_bet_time).getTime() / 1000;
          const newSecondsAgo = now - lastBetTimestamp;
          // 只在秒数变化时更新（减少不必要的响应式触发）
          if (player.bet_seconds_ago !== newSecondsAgo) {
            player.bet_seconds_ago = newSecondsAgo;
          }
        }
      });

      // 更新电子游戏玩家
      this.gamePlayers.forEach(player => {
        if (player.last_bet_time) {
          const lastBetTimestamp = new Date(player.last_bet_time).getTime() / 1000;
          const newSecondsAgo = now - lastBetTimestamp;
          // 只在秒数变化时更新
          if (player.bet_seconds_ago !== newSecondsAgo) {
            player.bet_seconds_ago = newSecondsAgo;
          }
        }
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