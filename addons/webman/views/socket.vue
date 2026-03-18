<template>
  <a-badge :count="count" showZeros="true">
    <a-button shape="circle" type="ghost" style="color:white" @click="showModal">
      <template #icon>
        <MessageOutlined/>
      </template>
    </a-button>
  </a-badge>
  <a-drawer
      v-model:visible="visible"
      :title="title"
      placement="right"
      :destroyOnClose="true"
      @close="closeDrawer()"
      width="500px"
  >
    <div>
      <div v-if="!timelineData.length && is_empty">
        <a-empty/>
      </div>
      <a-skeleton :loading="loading" active>
        <div v-if="timelineData.length" class="timeline-container" @scroll="handleScroll"
             style="height: 800px;overflow: scroll;overflow-x: hidden; padding: 10px">
          <a-timeline mode="alternate" pending="Recording...">
            <a-timeline-item v-for="item in timelineData" :key="item.id" :color="getColor(item.type)">
              <template #dot v-if="item.type === 6 || item.type === 5">
                <ClockCircleOutlined style="font-size: 16px"/>
              </template>
              <a-card hoverable size="small" bodyStyle="text-align:left" @click="pageJump(item.url, item.source_id)">
                <div style="display: inline-flex">
                  <span style="font-size: 12px;width: 128px;line-height: 23px">{{ item.created_at }}</span>
                  <a-tag v-if="item.type == 7" :color="item.status == 1 ? 'green' : 'red'"
                         style="height: 23px;border-radius: 4px">
                    {{ item.status == 1 ? online : untreated }}
                  </a-tag>
                  <a-tag v-else-if="item.type == 9 || item.type == 8 || item.type == 10"
                         :color="item.machine_status == 0 ? 'red' : 'green'" style="height: 23px;border-radius: 4px">
                    {{ item.machine_status == 0 ? untreated : processed }}
                  </a-tag>
                  <a-tag v-else-if="item.type == 20"
                         :color="item.machine_status == 1 ? 'red' : 'green'" style="height: 23px;border-radius: 4px">
                    {{ item.machine_status == 1 ? lock : open }}
                  </a-tag>
                  <a-tag v-else :color="item.type == 7 ? 'red' : 'green'" style="height: 23px;border-radius: 4px">
                    {{ item.type == 7 ? offline : processed }}
                  </a-tag>
                </div>
                <p style="font-weight: 700;margin-top: 5px">{{ item.title }}</p>
                <p style="font-size: 11px">{{ item.content }}</p>
              </a-card>
            </a-timeline-item>
          </a-timeline>
        </div>
      </a-skeleton>
    </div>
  </a-drawer>
  <audio controls="controls" hidden muted src="/audio/activity_examine.mp3" ref="activity_examine_audio"></audio>
  <audio controls="controls" hidden muted src="/audio/lottery_examine.mp3" ref="lottery_examine_audio"></audio>
  <audio controls="controls" hidden muted src="/audio/recharge_examine.mp3" ref="recharge_examine_audio"></audio>
  <audio controls="controls" hidden muted src="/audio/withdraw_examine.mp3" ref="withdraw_examine_audio"></audio>
</template>
<style>
.action_content {
  height: 8px
}
</style>
<script>
const messages = {
  //简体中文
  'zh-CN': {
    message: {
      player_examine_recharge_order: '有新的充值订单需要审核！',
      player_create_withdraw_order: '有新的提现订单需要审核！',
      player_examine_activity_bonus: '当前存在待审核的活动奖励请尽快审核！',
      player_examine_lottery: '当前存在待审核的彩金奖励请尽快审核！',
      machine_online: '机台设备离线, 请尽快检查！',
      machine_lock: '机台设备上下分异常(锁定), 请尽快检查！',
      online: '在线',
      offline: '离线',
      processed: '已处理',
      untreated: '未处理',
      online_machine_info: '机台信息',
      lock: '锁定',
      open: '开启',
    }
  },
  //英文
  en: {
    message: {
      player_examine_recharge_order: 'There are new recharge orders that need to be reviewed！',
      player_create_withdraw_order: 'There are new withdrawal orders that need to be approved！',
      player_examine_activity_bonus: 'There are currently pending activity rewards for review. Please review them as soon as possible！',
      player_examine_lottery: 'There are currently lottery awards to be reviewed, please review as soon as possible！',
      machine_online: 'Machine equipment offline, please check as soon as possible!',
      machine_lock: 'The upper and lower parts of the machine equipment are abnormal (locked), please check as soon as possible!',
      online: 'on line',
      offline: 'off line',
      processed: 'processed',
      untreated: 'untreated',
      online_machine_info: 'Machine information',
      lock: 'Lock',
      open: 'Open',
    }
  },
  jp: {
    message: {
      player_examine_recharge_order: '新規チャージ注文がある場合はレビューが必要です',
      player_create_withdraw_order: '新規引出注文がある場合はレビューが必要です！',
      player_examine_activity_bonus: '現在レビュー対象のアクティビティインセンティブがあります。できるだけ早くレビューしてください！',
      player_examine_lottery: '現在レビュー対象のカラー報酬が存在します。できるだけ早くレビューしてください',
      machine_online: '机台設備がオフラインになっているので、できるだけ早くチェックしてください！',
      machine_lock: '机台設備の上下に異常（ロック）があるので、できるだけ早くチェックしてください！',
      online: 'オンライン',
      offline: 'オフライン',
      processed: '処理済み',
      untreated: '未処理',
      online_machine_info: 'きょくだいじょうほう',
      lock: 'Lock',
      open: 'Open',
    }
  },
  // 繁体中文
  'zh-TW': {
    message: {
      player_examine_recharge_order: '有新的充值訂單需要審核！',
      player_create_withdraw_order: '有新的提現訂單需要審核！',
      player_examine_activity_bonus: '當前存在待審核的活動獎勵請盡快審核！',
      player_examine_lottery: '當前存在待審核的彩金獎勵請盡快審核！',
      machine_online: '機台設備離線, 請盡快檢查!',
      machine_lock: '機台設備上下分异常（鎖定），請儘快檢查！',
      online: '在線',
      offline: '離線',
      processed: '已處理',
      untreated: '未處理',
      online_machine_info: '機台信息',
      lock: '鎖定',
      open: '開啟',
    }
  }
}
export default {
  name: "socket.vue",
  //可传参数
  props: {
    id: String,
    type: String,
    department_id: String,
    count: String,
    lang: String,
    topShow: String,
    ws: String,
    examine_withdraw: String,
    examine_recharge: String,
    examine_activity: String,
    examine_lottery: String,
    machine: String,
    title: String,
  },
  data() {
    return {
      visible: false,
      timelineData: [],
      page: 1,
      size: 20,
      is_empty: false,
      loading: true,
      online: '',
      offline: '',
      open: '',
      lock: '',
      processed: '',
      untreated: '',
      adminChannelName: null,
      groupChannelName: null
    };
  },
  //生命周期渲染完执行
  created() {
    this.online = messages[this.lang].message.online;
    this.offline = messages[this.lang].message.offline;
    this.processed = messages[this.lang].message.processed;
    this.untreated = messages[this.lang].message.untreated;
    this.lock = messages[this.lang].message.lock;
    this.open = messages[this.lang].message.open;

    // 初始化 WebSocket
    if (this.ws && (this.examine_withdraw === true || this.examine_recharge === true || this.examine_activity === true || this.examine_lottery === true || this.machine === true)) {
      this.initWebSocket();
    }
  },
  beforeUnmount() {
    // 取消订阅
    const pushManager = this.getPushManager();
    if (this.adminChannelName && pushManager) {
      pushManager.unsubscribe(this.adminChannelName, this.handleAdminMessage);
    }
    if (this.groupChannelName && pushManager) {
      pushManager.unsubscribe(this.groupChannelName, this.handleGroupMessage);
    }
  },
  //定义函数方法
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
          console.error('[Socket] PushManager not available');
          return;
        }

        // 初始化连接
        await pushManager.init(this.ws);

        // 订阅私人频道
        this.adminChannelName = `private-${this.type}-${this.department_id}-${this.id}`;
        pushManager.subscribe(this.adminChannelName, this.handleAdminMessage, this);

        // 订阅群组频道
        this.groupChannelName = `private-admin_group-${this.type}-${this.department_id}`;
        pushManager.subscribe(this.groupChannelName, this.handleGroupMessage, this);

        console.log('[Socket] WebSocket initialized successfully');
      } catch (error) {
        console.error('[Socket] Init WebSocket failed:', error);
      }
    },

    handleAdminMessage(data) {
      try {
        const content = JSON.parse(data.content);
        const lang = this.lang;
        let that = this;
        switch (content.msg_type) {
          case 'machine_action_result':
            that.$notification.info({
              message: messages[lang].message.online_machine_info,
              description: content.description.split('\n').map((paragraph) => {
                return Vue.createVNode('p', {class: 'action_content'}, paragraph);
              }),
            });
            break;
          case 'player_offline_profit_settlement_success':
            that.$notification.info({
              message: content.title,
              router: '/ex-admin/addons-webman-controller-ChannelAgentPromoterController/settlementList',
              description: content.description.split('\n').map((paragraph) => {
                return Vue.createVNode('p', {class: 'action_content'}, paragraph);
              }),
            });
            break;
          case 'player_offline_profit_settlement_erro':
            that.$notification.error({
              message: content.title,
              description: content.description.split('\n').map((paragraph) => {
                return Vue.createVNode('p', {class: 'action_content'}, paragraph);
              }),
            });
            break;
          default:
            // Ignore other message types
            break;
        }
      } catch (e) {
        console.warn('[Socket] Parse admin message failed:', e);
      }
    },

    handleGroupMessage(data) {
      try {
        const content = JSON.parse(data.content);
        const lang = this.lang;
        const examine_withdraw = this.examine_withdraw;
        const examine_recharge = this.examine_recharge;
        const examine_activity = this.examine_activity;
        const examine_lottery = this.examine_lottery;
        const machine = this.machine;
        const type = this.type;
        let that = this;
        let title = '';
        let router = '';
        let params = '';
        let description = '';
        switch (content.msg_type) {
          case 'player_create_withdraw_order':
            if (examine_withdraw === true) {
              title = messages[lang].message.player_create_withdraw_order;
              router = '/ex-admin/addons-webman-controller-ChannelWithdrawRecordController/examineList';
              params = content.tradeno;
              // 语言播报
              that.startPlay('withdraw_examine');
              that.openNotification(title, router, description, params);
            }
            break;
          case 'player_examine_recharge_order':
            if (examine_recharge === true) {
              title = messages[lang].message.player_examine_recharge_order;
              router = '/ex-admin/addons-webman-controller-ChannelRechargeRecordController/examineList';
              params = content.tradeno;
              // 语言播报
              that.startPlay('recharge_examine');
              that.openNotification(title, router, description, params);
            }
            break;
          case 'player_examine_activity_bonus':
            if (examine_activity === true) {
              title = messages[lang].message.player_examine_activity_bonus;
              router = '/ex-admin/addons-webman-controller-PlayerActivityRecordController/examine';
              if (type === 'channel') {
                router = '/ex-admin/addons-webman-controller-ChannelPlayerActivityRecordController/examine';
              }
              params = content.id;
              // 语言播报
              that.startPlay('activity_examine');
              that.openNotification(title, router, description, params);
            }
            break;
          case 'player_examine_lottery':
            if (examine_lottery === true) {
              title = messages[lang].message.player_examine_lottery;
              router = '/ex-admin/addons-webman-controller-PlayerLotteryRecordController/auditList';
              if (type === 'channel') {
                router = '/ex-admin/addons-webman-controller-ChannelPlayerLotteryRecordController/auditList';
              }
              params = content.id;
              // 语言播报
              that.startPlayLottery();
              that.openNotification(title, router, description, params);
            }
            break;
          case 'machine_online':
            if (machine === true) {
              title = messages[lang].message.machine_online;
              router = '/ex-admin/addons-webman-controller-MachineController/index';
              that.openNotification(title, router, description, params);
            }
            break;
          case 'machine_lock':
            if (machine === true) {
              title = messages[lang].message.machine_lock;
              router = '/ex-admin/addons-webman-controller-MachineController/infoList';
              that.openNotification(title, router, description, params);
            }
            break;
        }
      } catch (e) {
        console.warn('[Socket] Parse group message failed:', e);
      }
    },
    openNotification(title, router, description = '', params) {
      this.$notification.info({
        message: title,
        description: description,
        onClick: () => {
          this.$router.push({path: router, query: {tradeno: params}})
        },
      });
    },
    openNotificationErro(title, router, description = '', params) {
      this.$notification.error({
        message: title,
        description: description,
        onClick: () => {
          this.$router.push({path: router, query: {tradeno: params}})
        },
      });
    },
    async startPlay(v) {
      this.$nextTick(() => {
        this.$refs[`${v}_audio`].muted = false;
        this.$refs[`${v}_audio`].currentTime = 0;
        this.$refs[`${v}_audio`].play();
      })
    },
    async startPlayLottery() {
      this.$nextTick(() => {
        this.$refs.lottery_examine_audio.muted = false;
        this.$refs.lottery_examine_audio.currentTime = 0;
        this.$refs.lottery_examine_audio.play();
      })
    },
    showModal() {
      this.visible = true;
      this.loadMore();
    },
    closeDrawer() {
      this.timelineData = [];
      this.page = 1;
      this.is_empty = false;
      this.loading = true;
    },
    handleScroll() {
      const container = document.querySelector('.timeline-container');
      if (container.scrollTop + container.clientHeight >= container.scrollHeight) {
        this.page = this.page + 1;
        this.loadMore();
      }
    },
    loadMore() {
      this.$request({
        url: 'ex-admin/system/noticeList',
        method: 'post',
        data: {
          'page': this.page,
          'size': this.size,
        },
      }).then(response => {
        this.loading = false;
        if (response.data.length > 0) {
          this.timelineData = this.timelineData.concat(response.data);
        } else {
          this.is_empty = true;
        }
      }).catch(error => {
        console.error(error);
      });
    },
    getColor(type) {
      switch (type) {
        case 3:
          return 'green'
        case 4:
          return 'gray'
        case 5:
          return 'green'
        case 6:
          return 'orange'
        case 7:
          return 'red'
      }
    },
    pageJump(url, source_id) {
      this.closeDrawer()
      this.visible = false;
      this.$router.push({
        path: '/' + url,
        params: {source_id: source_id}
      })
    },
  }
}
</script>