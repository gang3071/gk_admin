<template>
  <div style="padding: 16px; display: flex; justify-content: center;">
    <div style="width: 100%; max-width: 800px;">
    <!-- 連接配置 -->
    <a-card :title="labels.config_title || '連接配置'" size="small" style="margin-bottom: 16px;" :headStyle="{borderBottom: '2px solid #409EFF'}">
      <a-row :gutter="16">
        <a-col :span="16">
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 6px;">{{ labels.field_port || '串口路徑' }}</div>
            <div style="display: flex; gap: 8px;">
              <a-select
                v-model:value="config.port"
                :placeholder="labels.select_port || '選擇串口'"
                style="flex: 1;"
                :loading="portsLoading"
                @dropdownVisibleChange="handlePortDropdown"
                allowClear
              >
                <a-select-option v-for="port in availablePorts" :key="port.path" :value="port.path">
                  {{ port.path }}
                </a-select-option>
              </a-select>
              <a-button @click="addNewPort" style="flex-shrink: 0;">{{ labels.add_port || '添加串口' }}</a-button>
            </div>
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 6px;">{{ labels.field_baud_rate || '波特率' }}</div>
            <a-select v-model:value="config.baudRate" style="width: 100%;">
              <a-select-option value="9600">9600</a-select-option>
              <a-select-option value="19200">19200</a-select-option>
              <a-select-option value="38400">38400</a-select-option>
              <a-select-option value="57600">57600</a-select-option>
              <a-select-option value="115200">115200</a-select-option>
            </a-select>
          </div>
        </a-col>
        <a-col :span="8">
          <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; gap: 16px;">
            <a-tag :color="isConnected ? 'success' : 'error'" style="padding: 6px 24px; font-size: 16px; margin: 0;">
              {{ isConnected ? '✓ ' + (labels.status_connected || '已連接') : '✗ ' + (labels.status_disconnected || '未連接') }}
            </a-tag>
            <div style="display: flex; gap: 12px;">
              <a-button type="primary" @click="connect" :disabled="isConnected" style="min-width: 90px;">{{ labels.connect || '連接' }}</a-button>
              <a-button danger @click="disconnect" :disabled="!isConnected" style="min-width: 90px;">{{ labels.disconnect || '斷開' }}</a-button>
            </div>
          </div>
        </a-col>
      </a-row>
    </a-card>

    <!-- 設備操作 + QR碼列印 -->
    <a-row :gutter="16" style="margin-bottom: 16px;">
      <a-col :span="12">
        <a-card :title="labels.device_ops_title || '設備操作'" size="small" :headStyle="{borderBottom: '2px solid #E6A23C'}">
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">{{ labels.field_serial_no || '列印序列號' }}</div>
            <a-input-number v-model:value="config.serialNo" :min="0" :max="9999999" placeholder="0-9999999" style="width: 100%;" />
          </div>
          <a-button block @click="setSerialNo" style="margin-bottom: 16px;" :disabled="!isConnected">{{ labels.set_serial_no || '設置序列號' }}</a-button>
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">{{ labels.field_remark || '備註' }}</div>
            <a-input v-model:value="remark" :placeholder="labels.remark_placeholder || '選填，出票時備註'" allow-clear style="width: 100%;" />
          </div>
          <a-row :gutter="8" style="margin-bottom: 8px;">
            <a-col :span="12">
              <a-button block @click="syncDatetime" :disabled="!isConnected">{{ labels.sync_datetime || '同步時間' }}</a-button>
            </a-col>
            <a-col :span="12">
              <a-button block @click="sendHeartbeat" :disabled="!isConnected">{{ labels.heartbeat || '心跳' }}</a-button>
            </a-col>
          </a-row>
          <a-row :gutter="8">
            <a-col :span="12">
              <a-button block @click="restartPrinter" :disabled="!isConnected">{{ labels.restart_printer || '重啟打印機' }}</a-button>
            </a-col>
            <a-col :span="12">
              <a-button danger block @click="resetMachine" :disabled="!isConnected">{{ labels.reset || '復位' }}</a-button>
            </a-col>
          </a-row>
        </a-card>
      </a-col>
      <a-col :span="12">
        <a-card :title="labels.qr_print || 'QR碼列印'" size="small" :headStyle="{borderBottom: '2px solid #67C23A'}">
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">{{ labels.ticket_type || '票據類型' }}</div>
            <a-select v-model:value="ticketType" style="width: 100%;">
              <a-select-option :value="1">{{ labels.type_recharge || '開分' }}</a-select-option>
              <a-select-option :value="2">{{ labels.type_withdraw || '洗分' }}</a-select-option>
              <a-select-option :value="3">{{ labels.type_experience || '體驗卷' }}</a-select-option>
              <a-select-option :value="4">{{ labels.type_welfare || '福利卷' }}</a-select-option>
            </a-select>
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">{{ labels.select_player || '關聯玩家（可選）' }}</div>
            <a-select
              v-model:value="selectedPlayerId"
              show-search
              :placeholder="labels.search_player || '選擇或搜索玩家'"
              :filter-option="filterPlayerOption"
              :options="playerOptions"
              :loading="playerSearching"
              allow-clear
              style="width: 100%;"
              @popupScroll="handlePlayerScroll"
              @search="searchPlayers"
              @change="handlePlayerChange"
            >
            </a-select>
          </div>

          <!-- 玩家打码量信息展示（福利卷/体验卷 + 已选择玩家时显示） -->
          <div v-if="(ticketType === 3 || ticketType === 4) && selectedPlayerId && playerBetInfo" style="margin-bottom: 12px;">
            <div style="padding: 12px; background: #f0f5ff; border-radius: 4px; border: 1px solid #d6e4ff;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-weight: 500; color: #333;">{{ labels.player_bet_info || '玩家打碼量信息' }}</span>
                <a-button type="link" size="small" @click="refreshPlayerBetInfo" :loading="betInfoLoading">
                  <reload-outlined /> {{ labels.refresh || '刷新' }}
                </a-button>
              </div>
              <a-descriptions :column="1" size="small">
                <a-descriptions-item :label="labels.player_name || '玩家名稱'">
                  <a-tag color="blue">
                    <user-outlined /> {{ playerBetInfo.player_name }} ({{ playerBetInfo.player_uuid }})
                  </a-tag>
                </a-descriptions-item>
                <a-descriptions-item :label="labels.today_bet_amount || '今日電子總打碼量'">
                  <a-tag color="green">
                    <dollar-outlined /> NT$ {{ formatNumber(playerBetInfo.today_bet_amount) }}
                  </a-tag>
                </a-descriptions-item>
                <a-descriptions-item :label="labels.yesterday_bet_amount || '昨日電子總打碼量'">
                  <a-tag color="orange">
                    <dollar-outlined /> NT$ {{ formatNumber(playerBetInfo.yesterday_bet_amount) }}
                  </a-tag>
                </a-descriptions-item>
              </a-descriptions>
            </div>
          </div>

          <!-- 分数/金额选择 -->
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">{{ labels.field_score || '分數/金額' }}</div>
            <!-- 福利卷/体验卷：下拉选择 -->
            <a-select
                v-if="ticketType === 3 || ticketType === 4"
                v-model:value="ticketScore"
                placeholder="請選擇分數"
                style="width: 100%;"
                :disabled="!selectedPlayerId || !playerBetInfo"
            >
              <a-select-option
                  v-for="option in voucherScoreOptions"
                  :key="option.value"
                  :value="option.value"
                  :disabled="option.disabled"
              >
                <span>{{ option.label }}</span>
                <span v-if="option.condition" style="margin-left: 8px; color: #999; font-size: 12px;">
                  （{{ option.condition }}）
                </span>
              </a-select-option>
            </a-select>
            <!-- 开分/洗分：数字输入 -->
            <a-input-number v-else v-model:value="ticketScore" :min="0" :placeholder="labels.field_score || '分數/金額'" style="width: 100%;" />
          </div>
          <a-button type="primary" block @click="sendQrCode" :disabled="!isConnected">{{ labels.send_qr || '發送QR碼' }}</a-button>
        </a-card>
      </a-col>
    </a-row>

    <!-- 通信日志 -->
    <a-card size="small" :headStyle="{borderBottom: '2px solid #909399'}">
      <template #title>
        <span>{{ log_title }} <a-tag v-if="logs.length > 0" color="blue" style="margin-left: 8px;">{{ logs.length }}</a-tag></span>
        <div style="float: right; display: flex; gap: 8px;">
          <a-button size="small" @click="clearLog">{{ log_clear }}</a-button>
          <a-button size="small" @click="logExpanded = !logExpanded">
            {{ logExpanded ? log_collapse : log_expand }}
          </a-button>
        </div>
      </template>
      <div v-show="logExpanded" ref="logContainer" style="height: 200px; overflow: auto; background: #1e1e1e; padding: 12px; font-family: Consolas, Monaco, monospace; font-size: 12px; border-radius: 4px; color: #d4d4d4; line-height: 1.6;">
        <div v-for="(log, index) in logs" :key="index" style="padding: 2px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
          <span style="color: #666;">{{ log.time }}</span>
          <span :style="{color: log.color, fontWeight: 'bold'}">[{{ log.icon }} {{ log.label }}]</span>
          <span :style="{color: log.color}">{{ log.message }}</span>
        </div>
      </div>
    </a-card>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    default_baud_rate: String,
    default_store_name: String,
    default_store_uid: String,
    save_ticket_url: String,
    player_bet_info_url: String,
    store_admin_id: Number,
    department_id: Number,
    paper_empty_msg: String,
    paper_jam_msg: String,
    paper_error_msg: String,
    labels: {
      type: Object,
      default: () => ({})
    },
    log_title: { type: String, default: '通信日誌' },
    log_clear: { type: String, default: '清空' },
    log_expand: { type: String, default: '展開' },
    log_collapse: { type: String, default: '收起' },
  },
  data() {
    return {
      isConnected: false,
      port: null,
      reader: null,
      readLoopAbort: null,
      heartbeatTimer: null,
      availablePorts: [],
      portsLoading: false,
      selectedPortId: null,
      config: {
        port: '',
        baudRate: this.default_baud_rate || '115200',
        uid: this.default_store_uid || '',
        machineNo: 0,
        storeName: this.default_store_name || '',
        serialNo: 0,
      },
      lottery: {
        ticketCount: 0,
        giftCount: 0,
        codeTable: 0,
        number: 0,
      },
      ticketType: 1,
      ticketScore: 0,
      selectedPlayerId: null,
      playerOptions: [],
      playerSearching: false,
      playerSearchTimer: null,
      playerPage: 1,
      playerHasMore: true,
      playerKeyword: '',
      playerBetInfo: null,
      betInfoLoading: false,
      hexCommand: '',
      remark: '',
      logExpanded: false,
      logs: [],
      receiveBuffer: [],
      pendingResolve: null,
    };
  },
  computed: {
    // 计算福利卷/体验卷的可选分数选项
    voucherScoreOptions() {
      if (!this.playerBetInfo) return [];

      const todayBet = this.playerBetInfo.today_bet_amount || 0;
      const yesterdayBet = this.playerBetInfo.yesterday_bet_amount || 0;
      const options = [];

      if (this.ticketType === 4) {
        // 福利卷规则
        // 今日打分达20万分以上即可立即领取福利卷1000分
        options.push({
          value: 1000,
          label: '福利卷 1000 分',
          disabled: todayBet < 200000,
          condition: '今日打分≥20萬',
        });

        // 昨日打分达10万分以上即可领取福利卷1000分
        options.push({
          value: 1001,
          label: '福利卷 1000 分',
          disabled: yesterdayBet < 100000,
          condition: '昨日打分≥10萬',
        });

        // 昨日打分达30万分以上即可领取福利卷2000分
        options.push({
          value: 2000,
          label: '福利卷 2000 分',
          disabled: yesterdayBet < 300000,
          condition: '昨日打分≥30萬',
        });

        // 昨日打分达50万分以上即可领取福利卷3000分
        options.push({
          value: 3000,
          label: '福利卷 3000 分',
          disabled: yesterdayBet < 500000,
          condition: '昨日打分≥50萬',
        });
      } else if (this.ticketType === 3) {
        // 体验卷规则（可根据需求扩展）
        options.push({
          value: 500,
          label: '体验卷 500 分',
          disabled: false,
          condition: '',
        });
        options.push({
          value: 1000,
          label: '体验卷 1000 分',
          disabled: false,
          condition: '',
        });
        options.push({
          value: 2000,
          label: '体验卷 2000 分',
          disabled: false,
          condition: '',
        });
      }

      return options;
    }
  },
  watch: {
    // 监听票据类型变化
    ticketType(newType) {
      this.ticketScore = 0;
      if ((newType === 3 || newType === 4) && this.selectedPlayerId) {
        this.loadPlayerBetInfo(this.selectedPlayerId);
      } else {
        this.playerBetInfo = null;
      }
    },
    // 监听打码量信息变化，自动选择第一个可用选项
    playerBetInfo(newInfo) {
      if (newInfo && (this.ticketType === 3 || this.ticketType === 4)) {
        // 延迟一帧等待 computed 更新
        this.$nextTick(() => {
          const firstAvailable = this.voucherScoreOptions.find(opt => !opt.disabled);
          if (firstAvailable) {
            this.ticketScore = firstAvailable.value;
          } else {
            this.ticketScore = 0;
          }
        });
      }
    }
  },
  methods: {
    // 翻译辅助
    t(key, params = {}) {
      let text = this.labels[key] || key;
      Object.keys(params).forEach(k => {
        text = text.replace(`{${k}}`, params[k]);
      });
      return text;
    },

    // 添加日志
    addLog(type, message) {
      const cfg = {
        send:    { icon: '↑', label: 'SEND', color: '#409EFF' },
        receive: { icon: '↓', label: 'RECV', color: '#67C23A' },
        info:    { icon: '●', label: 'INFO', color: '#909399' },
        error:   { icon: '✗', label: 'FAIL', color: '#F56C6C' },
        success: { icon: '✓', label: 'OK',   color: '#67C23A' },
        warn:    { icon: '⚠', label: 'WARN', color: '#E6A23C' },
      }[type] || { icon: '●', label: 'INFO', color: '#909399' };

      const now = new Date();
      this.logs.push({
        time: now.toLocaleTimeString('zh-TW', { hour12: false }) + '.' + String(now.getMilliseconds()).padStart(3, '0'),
        type, icon: cfg.icon, label: cfg.label, color: cfg.color, message,
      });
      this.$nextTick(() => {
        const c = this.$refs.logContainer;
        if (c) c.scrollTop = c.scrollHeight;
      });
    },

    // 构造命令帧
    buildFrame(cmdType, cmd, data = []) {
      const frame = [0xFA, 0xEA, cmdType, cmd, data.length, ...data];
      let xor = cmdType ^ cmd ^ data.length;
      for (const b of data) xor ^= b;
      frame.push(xor);
      let sum = cmdType + cmd + data.length;
      for (const b of data) sum += b;
      frame.push((sum + xor) & 0xFF);
      frame.push(0xFB, 0xEB);
      return new Uint8Array(frame);
    },

    // 解析响应帧
    parseFrame(buffer) {
      // 帧结构: 帧头(2) + cmdType(1) + cmd(1) + dataLen(1) + 数据域(N) + XOR(1) + SUM(1) + 帧尾(2) = 9 + N
      for (let i = 0; i < buffer.length - 1; i++) {
        if (buffer[i] === 0xFA && buffer[i + 1] === 0xEA) {
          if (buffer.length < i + 9) return null; // 最小帧长度: 2+1+1+1+0+1+1+2 = 9
          const dataLen = buffer[i + 4];
          const frameLen = 9 + dataLen;
          if (buffer.length < i + frameLen) return null;
          if (buffer[i + frameLen - 2] === 0xFB && buffer[i + frameLen - 1] === 0xEB) {
            return {
              cmdType: buffer[i + 2],
              cmd: buffer[i + 3],
              data: buffer.slice(i + 5, i + 5 + dataLen),
              consumed: i + frameLen,
            };
          }
        }
      }
      return null;
    },

    // 发送命令并等待响应
    async sendCommand(cmdType, cmd, data = [], waitResponse = true, silent = false) {
      if (!this.port || !this.isConnected) {
        if (!silent) this.addLog('error', this.t('port_not_connected'));
        return null;
      }

      const frame = this.buildFrame(cmdType, cmd, data);
      const hexStr = Array.from(frame).map(b => b.toString(16).padStart(2, '0').toUpperCase()).join(' ');
      if (!silent) this.addLog('send', this.t('send_cmd', {cmd: cmd.toString(16).padStart(2, '0'), len: frame.length, hex: hexStr}));

      try {
        const writer = this.port.writable.getWriter();
        await writer.write(frame);
        writer.releaseLock();
        if (!silent) this.addLog('info', this.t('data_written'));
      } catch (e) {
        if (!silent) this.addLog('error', this.t('send_failed', {error: e.message}));
        return null;
      }

      // 如果不需要等待响应，直接返回
      if (!waitResponse) {
        return true;
      }

      // 等待响应（带超时）
      return new Promise((resolve) => {
        const timeout = setTimeout(() => {
          // 只清除自己的 pendingResolve
          if (this.pendingResolve && this.pendingResolve.cmd === cmd) {
            this.pendingResolve = null;
          }
          if (!silent) this.addLog('warn', this.t('response_timeout', {cmd: cmd.toString(16).padStart(2, '0')}));
          resolve(null);
        }, 2000);

        // 存储期望的命令类型
        this.pendingResolve = {
          cmd: cmd,
          cmdType: cmdType,
          silent: silent,
          callback: (responseData) => {
            clearTimeout(timeout);
            this.pendingResolve = null;
            resolve(responseData);
          }
        };
      });
    },

    // 读取循环（使用原始字节）
    async startReadLoop() {
      let buffer = new Uint8Array(0);

      this.addLog('info', this.t('read_loop_started'));

      try {
        while (this.isConnected && this.port && this.port.readable) {
          try {
            this.reader = this.port.readable.getReader();

            while (this.isConnected) {
              const { value, done } = await this.reader.read();
              if (done) break;

              // 调试：显示收到的原始字节
              if (value && value.length > 0) {
                const rawHex = Array.from(value).map(b => b.toString(16).padStart(2, '0').toUpperCase()).join(' ');
                this.addLog('receive', '收到 ' + value.length + ' 字节: ' + rawHex);

                // 拼接到缓冲区
                const newBuffer = new Uint8Array(buffer.length + value.length);
                newBuffer.set(buffer);
                newBuffer.set(value, buffer.length);
                buffer = newBuffer;

                // 尝试解析帧
                let frame;
                while ((frame = this.parseFrame(buffer)) !== null) {
                  buffer = buffer.slice(frame.consumed);
                  // 心跳响应(cmd=0x01)静默处理，不记录日志
                  const isHeartbeat = frame.cmd === 0x01;
                  const isSilent = isHeartbeat || (this.pendingResolve && this.pendingResolve.silent);

                  if (!isSilent) {
                    const hexStr = Array.from(frame.data).map(b => b.toString(16).padStart(2, '0').toUpperCase()).join(' ');
                    this.addLog('receive', '解析帧: cmd=' + frame.cmd.toString(16).padStart(2, '0') + ' DATA=[' + hexStr + ']');
                  }

                  // 检查是否是期望的响应
                  if (this.pendingResolve && !isHeartbeat) {
                    if (!isSilent) {
                      this.addLog('info', this.t('expected_response', {expected: this.pendingResolve.cmd.toString(16).padStart(2, '0'), received: frame.cmd.toString(16).padStart(2, '0')}));
                    }
                    if (this.pendingResolve.cmd === frame.cmd) {
                      if (!isSilent) this.addLog('success', this.t('response_match'));
                      this.pendingResolve.callback(frame);
                    } else {
                      if (!isSilent) this.addLog('warn', this.t('response_mismatch'));
                    }
                  } else if (this.pendingResolve && isHeartbeat) {
                    // 心跳响应回调
                    this.pendingResolve.callback(frame);
                  }
                }
              }
            }
          } catch (e) {
            // 取消或关闭是正常情况
            if (e.message && (e.message.includes('cancel') || e.message.includes('closed') || e.message.includes('release'))) {
              break;
            }
            // 其他错误
            if (this.isConnected) {
              this.addLog('error', this.t('read_error', {error: e.message}));
            }
            break;
          } finally {
            if (this.reader) {
              try { this.reader.releaseLock(); } catch {}
              this.reader = null;
            }
          }
        }
      } catch (e) {
        // 外层异常处理
      }

      this.addLog('info', this.t('read_loop_exited'));
    },

    // 获取可用串口列表
    async loadPorts() {
      if (!('serial' in navigator)) {
        this.addLog('warn', this.t('browser_not_supported'));
        return;
      }

      this.portsLoading = true;
      try {
        // 获取已授权的串口列表
        const ports = await navigator.serial.getPorts();
        this.availablePorts = ports.map((port, index) => {
          const info = port.getInfo();
          return {
            path: 'USB Serial ' + (index + 1) + ' (VID:' + (info.usbVendorId || '????').toString(16).padStart(4, '0') + ' PID:' + (info.usbProductId || '????').toString(16).padStart(4, '0') + ')',
            port: port,
            description: '',
          };
        });

        // 如果没有已授权的串口，提示用户
        if (this.availablePorts.length === 0) {
          this.addLog('info', this.t('no_authorized_port'));
        }
      } catch (e) {
        this.addLog('error', this.t('get_port_list_failed', {error: e.message}));
      } finally {
        this.portsLoading = false;
      }
    },

    // 下拉框打开时刷新串口列表
    handlePortDropdown(open) {
      if (open) {
        this.loadPorts();
      }
    },

    // 选择串口设备
    handlePortSelect(index) {
      if (index !== null && index !== undefined && this.availablePorts[index]) {
        this.port = this.availablePorts[index].port;
        // 不自动覆盖手动输入的端口名
        if (!this.config.port) {
          this.config.port = this.availablePorts[index].path;
        }
      } else {
        this.port = null;
      }
    },

    // 添加新串口
    async addNewPort() {
      if (!('serial' in navigator)) {
        this.addLog('error', this.t('browser_not_supported'));
        return;
      }

      try {
        const port = await navigator.serial.requestPort();
        await this.loadPorts();
        // 自动选择新添加的串口
        const info = port.getInfo();
        const portName = 'USB Serial (VID:' + (info.usbVendorId || '????').toString(16) + ' PID:' + (info.usbProductId || '????').toString(16) + ')';
        this.config.port = portName;
        this.port = port;
      } catch (e) {
        if (e.name !== 'NotFoundError') {
          this.addLog('error', this.t('add_port_failed', {error: e.message}));
        }
      }
    },

    // 连接串口
    async connect() {
      if (!('serial' in navigator)) {
        this.addLog('error', this.t('browser_not_supported'));
        this.$message.error({ content: this.t('browser_not_supported'), duration: 3 });
        return;
      }

      if (!this.config.port) {
        this.addLog('error', this.t('no_port_selected'));
        this.$message.error({ content: this.t('no_port_selected'), duration: 3 });
        return;
      }

      // 如果已连接，先断开
      if (this.isConnected || this.port) {
        await this.disconnect();
      }

      try {
        // 查找选中的串口对象
        const selectedPort = this.availablePorts.find(p => p.path === this.config.port);
        if (!selectedPort) {
          this.addLog('error', this.t('port_not_found'));
          this.$message.error({ content: this.t('port_not_found'), duration: 3 });
          return;
        }

        this.port = selectedPort.port;
        this.addLog('info', this.t('opening_port', {port: this.config.port}));
        this.addLog('info', this.t('baud_rate', {rate: this.config.baudRate}));

        // 打开串口
        await this.port.open({
          baudRate: parseInt(this.config.baudRate),
          dataBits: 8,
          stopBits: 1,
          parity: 'none',
          flowControl: 'none',
        });

        this.isConnected = true;
        this.addLog('success', this.t('port_opened', {rate: this.config.baudRate}));

        // 设置 DTR/RTS 信号（某些设备需要）
        try {
          const signals = await this.port.getSignals();
          this.addLog('info', this.t('current_signal', {dtr: signals.dataTerminalReady, rts: signals.readyToSend}));

          // 设置 DTR 为 true
          await this.port.setSignals({ dataTerminalReady: true });
          this.addLog('info', this.t('dtr_set'));
        } catch (e) {
          this.addLog('warn', this.t('signal_failed', {error: e.message}));
        }

        // 启动读取循环
        this.startReadLoop();

        // 等待读取循环启动
        await new Promise(r => setTimeout(r, 200));

        // 测试发送心跳
        this.addLog('info', this.t('heartbeat_testing'));
        const testResult = await this.sendCommand(0x01, 0x01);

        if (testResult) {
          this.addLog('success', this.t('device_ok'));

          // 连接成功后自动初始化设备
          this.addLog('info', this.t('auto_init'));

          // 1. 同步时间
          this.addLog('info', this.t('auto_sync_time'));
          const timeResult = await this.syncDatetime();
          this.addLog(timeResult ? 'success' : 'error', timeResult ? this.t('time_sync_success') : this.t('time_sync_failed'));
          await new Promise(r => setTimeout(r, 50)); // 延迟50ms

          // 2. 设置UID（使用店机UUID）
          this.addLog('info', this.t('setting_uid', {uid: this.config.uid}));
          if (this.config.uid) {
            const uid = this.config.uid.padStart(16, '0').substring(0, 16);
            const uidData = Array.from(uid).map(c => c.charCodeAt(0));
            this.addLog('info', this.t('send_uid_data', {uid: uid, len: uidData.length}));
            const uidResult = await this.sendCommand(0x01, 0x03, uidData);
            this.addLog(uidResult ? 'success' : 'error', this.t(uidResult ? 'uid_set_success' : 'uid_set_failed', {uid: uid}));
            await new Promise(r => setTimeout(r, 50)); // 延迟50ms
          } else {
            this.addLog('warn', this.t('uid_empty'));
          }

          // 3. 设置序列号
          this.addLog('info', this.t('setting_serial', {serial: this.config.serialNo}));
          const serialNo = this.config.serialNo || 0;
          const serialData = [(serialNo >> 24) & 0xFF, (serialNo >> 16) & 0xFF, (serialNo >> 8) & 0xFF, serialNo & 0xFF];
          const serialResult = await this.sendCommand(0x01, 0x06, serialData);
          this.addLog(serialResult ? 'success' : 'error', this.t(serialResult ? 'serial_set_success' : 'serial_set_failed', {serial: serialNo}));
          await new Promise(r => setTimeout(r, 50)); // 延迟50ms

          // 4. 设置店名称（使用后端GBK编码）
          await this.setStoreName();

          this.addLog('success', this.t('init_complete'));
        } else {
          this.addLog('warn', this.t('device_no_response'));
          this.addLog('warn', this.t('device_check_1'));
          this.addLog('warn', this.t('device_check_2'));
          this.addLog('warn', this.t('device_check_3'));
        }

        // 启动心跳
        this.addLog('info', this.t('heartbeat_started'));
        this.heartbeatTimer = setInterval(async () => {
          await this.sendCommand(0x01, 0x01, [], true, true);
        }, 10000);

      } catch (e) {
        if (e.name === 'NotFoundError') {
          this.addLog('info', this.t('user_cancelled'));
        } else {
          this.addLog('error', this.t('connect_failed', {error: e.message}));
        }
        if (this.port) {
          try { await this.port.close(); } catch {}
          this.port = null;
        }
        this.isConnected = false;
      }
    },

    // 断开连接
    async disconnect() {
      this.addLog('info', this.t('disconnecting'));
      this.isConnected = false;

      // 1. 停止心跳
      if (this.heartbeatTimer) {
        clearInterval(this.heartbeatTimer);
        this.heartbeatTimer = null;
      }

      // 2. 取消读取器
      if (this.reader) {
        try {
          await this.reader.cancel();
        } catch {}
        this.reader = null;
      }

      // 3. 关闭端口
      if (this.port) {
        try {
          await this.port.close();
        } catch {}
        this.port = null;
      }

      this.config.port = '';
      this.addLog('warn', this.t('disconnected'));
    },

    // 心跳
    async sendHeartbeat() {
      const r = await this.sendCommand(0x01, 0x01);
      this.addLog(r ? 'success' : 'error', r ? this.t('device_ok') : this.t('heartbeat_failed'));
    },

    // 同步时间（北京时间 UTC+8）
    async syncDatetime() {
      const now = new Date();
      // 转换为北京时间
      const utc = now.getTime() + now.getTimezoneOffset() * 60000;
      const beijingTime = new Date(utc + 8 * 3600000);

      const year = beijingTime.getFullYear() % 100; // 只取后两位: 2026 -> 26
      const month = beijingTime.getMonth() + 1;
      const day = beijingTime.getDate();
      const hours = beijingTime.getHours();
      const minutes = beijingTime.getMinutes();
      const seconds = beijingTime.getSeconds();

      const data = [year, month, day, hours, minutes, seconds];

      this.addLog('info', `20${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')} ${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`);

      const r = await this.sendCommand(0x01, 0x02, data);
      this.addLog(r ? 'success' : 'error', r ? this.t('time_sync_success') : this.t('time_sync_failed'));
      return !!r;
    },

    // 初始化设备
    async initMachine() {
      if (!this.config.uid) { this.addLog('error', this.t('uid_empty')); return; }

      this.addLog('info', this.t('auto_init'));

      // 1. 同步时间（北京时间 UTC+8）
      const now = new Date();
      const utc = now.getTime() + now.getTimezoneOffset() * 60000;
      const beijingTime = new Date(utc + 8 * 3600000);

      const year = beijingTime.getFullYear() % 100; // 只取后两位: 2026 -> 26
      const month = beijingTime.getMonth() + 1;
      const day = beijingTime.getDate();
      const hours = beijingTime.getHours();
      const minutes = beijingTime.getMinutes();
      const seconds = beijingTime.getSeconds();

      this.addLog('info', `20${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')} ${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`);

      await this.sendCommand(0x01, 0x02, [year, month, day, hours, minutes, seconds]);
      this.addLog('success', this.t('time_sync_success'));

      // 2. 设置UID
      const uid = this.config.uid.padStart(16, '0').substring(0, 16);
      const uidData = Array.from(uid).map(c => c.charCodeAt(0));
      await this.sendCommand(0x01, 0x03, uidData);
      this.addLog('success', this.t('uid_set_success', {uid}));

      // 3. 设置机台号
      const no = this.config.machineNo || 0;
      await this.sendCommand(0x01, 0x04, [(no >> 8) & 0xFF, no & 0xFF]);
      this.addLog('success', this.t('machine_no_set'));

      // 4. 设置店名称（使用GBK编码）
      await this.setStoreName();

      this.addLog('success', this.t('init_complete'));
    },

    // 设置序列号
    async setSerialNo() {
      const no = this.config.serialNo || 0;
      const data = [(no >> 24) & 0xFF, (no >> 16) & 0xFF, (no >> 8) & 0xFF, no & 0xFF];
      const r = await this.sendCommand(0x01, 0x06, data);
      this.addLog(r ? 'success' : 'error', r ? this.t('serial_set_success', {serial: no}) : this.t('serial_set_failed', {serial: no}));
    },

    // 设置店名称（独立函数，支持GBK编码）
    async setStoreName() {
      if (!this.config.storeName) {
        this.addLog('warn', this.t('store_name_empty'));
        return false;
      }

      this.addLog('info', this.t('setting_store_name', {name: this.config.storeName}));
      let name = this.config.storeName || '';
      let nameBytes = [];

      // 通过后端API进行GBK编码
      try {
        const encodeRes = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelIndexController/ticketMachineApi',
          method: 'get',
          params: { action: 'encode_gbk', text: name },
        });
        if (encodeRes.success && encodeRes.bytes) {
          nameBytes = encodeRes.bytes;
          this.addLog('info', this.t('gbk_encoding', {hex: encodeRes.hex}));
        } else {
          this.addLog('warn', this.t('gbk_failed'));
          const encoder = new TextEncoder();
          nameBytes = Array.from(encoder.encode(name));
        }
      } catch (e) {
        this.addLog('warn', this.t('gbk_failed') + ': ' + e.message);
        const encoder = new TextEncoder();
        nameBytes = Array.from(encoder.encode(name));
      }

      // 确保正好10个字节
      if (nameBytes.length > 10) {
        nameBytes = nameBytes.slice(0, 10);
      } else {
        while (nameBytes.length < 10) nameBytes.push(0x20); // 用空格填充
      }
      const nameHex = nameBytes.map(b => b.toString(16).padStart(2, '0')).join(' ');
      this.addLog('info', this.t('send_store_name', {hex: nameHex, len: nameBytes.length}));
      const nameResult = await this.sendCommand(0x01, 0x05, nameBytes);
      this.addLog(nameResult ? 'success' : 'error', this.t(nameResult ? 'store_name_set_success' : 'store_name_set_failed', {name: name}));
      return nameResult;
    },

    // 重启打印机
    async restartPrinter() {
      this.addLog('info', this.t('restart_printer'));
      const r = await this.sendCommand(0x01, 0x0A, [], false);
      this.addLog(r ? 'success' : 'error', r ? this.t('restart_sent') : this.t('restart_failed'));

      // 重启后等待打印机初始化完成，然后重新设置店名
      if (r) {
        this.addLog('info', this.t('waiting_printer_restart'));
        await new Promise(resolve => setTimeout(resolve, 3000)); // 等待3秒让打印机重启
        await this.setStoreName();
      }
    },

    // 复位
    async resetMachine() {
      const r = await this.sendCommand(0x01, 0x09);
      this.addLog(r ? 'success' : 'error', r ? this.t('reset_sent') : this.t('restart_failed'));
    },

    // 发送彩票数据
    async sendLottery() {
      const tc = this.lottery.ticketCount || 0;
      const gc = this.lottery.giftCount || 0;
      const ct = this.lottery.codeTable || 0;
      const num = this.lottery.number || 0;
      const data = [tc & 0xFF, gc & 0xFF, ct & 0xFF, (num >> 24) & 0xFF, (num >> 16) & 0xFF, (num >> 8) & 0xFF, num & 0xFF];

      this.addLog('info', `tc=${tc} gc=${gc} ct=${ct} num=${num}`);
      const r = await this.sendCommand(0x01, 0x07, data);
      this.addLog(r ? 'success' : 'error', r ? this.t('lottery_sent') : this.t('send_failed', {error: ''}));
    },

    // 发送QR码
    async sendQrCode() {
      if (!this.ticketScore || this.ticketScore <= 0) {
        this.addLog('error', this.t('valid_score_required'));
        this.$message.error({ content: this.t('valid_score_required'), duration: 3 });
        return;
      }

      // 福利卷和体验卷必须选择关联用户
      if ((this.ticketType === 3 || this.ticketType === 4) && !this.selectedPlayerId) {
        this.addLog('error', this.t('player_required_for_voucher'));
        this.$message.error({ content: this.t('player_required_for_voucher'), duration: 3 });
        return;
      }

      // 暂停心跳，避免干扰命令响应
      if (this.heartbeatTimer) {
        clearInterval(this.heartbeatTimer);
        this.heartbeatTimer = null;
        this.addLog('info', this.t('heartbeat_paused'));
      }

      // 先检测纸张状态（在入库之前）
      this.addLog('info', this.t('checking_paper'));
      const paperStatus = await this.sendCommand(0x01, 0x09, [0x00]);
      if (paperStatus && paperStatus.data && paperStatus.data.length > 0) {
        const paperCode = paperStatus.data[0];
        // 0x00=正常, 0x01=缺紙, 0x02=卡紙, 0x03=其他錯誤
        if (paperCode !== 0x00) {
          const errorMap = {
            0x01: this.paper_empty_msg || '打印紙不足，請添加紙張後重試',
            0x02: this.paper_jam_msg || '打印機卡紙，請處理後重試',
            0x03: this.paper_error_msg || '打印機異常，請檢查設備',
          };
          const errorMsg = errorMap[paperCode] || '打印機異常，錯誤碼: 0x' + paperCode.toString(16).padStart(2, '0');
          this.addLog('error', errorMsg);
          this.$message.error({ content: errorMsg, duration: 3 });
          this.heartbeatTimer = setInterval(async () => {
            await this.sendCommand(0x01, 0x01, [], true, true);
          }, 10000);
          return;
        }
        this.addLog('success', this.t('paper_ok'));
      } else {
        this.addLog('warn', this.t('paper_query_no_response'));
      }

      // 纸张正常，保存到数据库获取 order_id
      if (!this.save_ticket_url) {
        this.addLog('error', this.t('save_url_not_configured'));
        this.$message.error({ content: this.t('save_url_not_configured'), duration: 3 });
        return;
      }

      let orderId = '';

      try {
        this.addLog('info', this.t('saving_data', {id: this.selectedPlayerId}));

        const saveRes = await this.$request({
          url: this.save_ticket_url,
          method: 'post',
          data: {
            store_name: this.config.storeName,
            machine_no: this.config.machineNo,
            score: this.ticketScore,
            qr_code: 'auto_generated',
            ticket_type: this.ticketType,
            player_id: this.selectedPlayerId || 0,
            store_admin_id: this.store_admin_id,
            department_id: this.department_id,
            remark: this.remark || '',
          },
        });

        if (saveRes.code === 200) {
          orderId = saveRes.data?.order_id || '';
          this.remark = '';
          this.addLog('success', this.t('ticket_saved', {order_id: orderId}));
        } else {
          const errorMsg = this.t('ticket_save_failed', {error: (saveRes.message || '')});
          this.addLog('error', errorMsg);
          this.$message.error({ content: errorMsg, duration: 3 });
          return;
        }
      } catch (e) {
        const errorMsg = this.t('ticket_save_exception', {error: (e.message || '')});
        this.addLog('error', errorMsg);
        this.$message.error({ content: errorMsg, duration: 3 });
        return;
      }

      // 设置UID为 order_id（16字节，不足补0）
      this.addLog('info', this.t('setting_uid_for_qr', {order_id: orderId}));
      const uid = orderId.padEnd(16, '0').substring(0, 16);
      const uidData = Array.from(uid).map(c => c.charCodeAt(0));
      const uidResult = await this.sendCommand(0x01, 0x03, uidData);
      this.addLog(uidResult ? 'success' : 'error', this.t(uidResult ? 'uid_set_success' : 'uid_set_failed', {uid: uid}));

      // 等待设备处理
      await new Promise(r => setTimeout(r, 100));

      // 设置序列号为 order_id（16字节，不足补0）
      this.addLog('info', this.t('setting_serial_for_qr', {order_id: orderId}));
      const serialStr = orderId.padEnd(16, '0').substring(0, 16);
      const serialData16 = Array.from(serialStr).map(c => c.charCodeAt(0));
      this.addLog('info', this.t('send_serial_data', {serial: serialStr, len: serialData16.length}));
      const serialResult = await this.sendCommand(0x01, 0x06, serialData16);
      this.addLog(serialResult ? 'success' : 'error', this.t(serialResult ? 'serial_set_success' : 'serial_set_failed', {serial: serialStr}));

      // 等待设备处理
      await new Promise(r => setTimeout(r, 100));

      // 先发送彩票数据（票数3字节 + 赠送1字节 + 码表数4字节 = 8字节）
      const score = Math.floor(this.ticketScore);
      const lotteryData = [
        (score >> 16) & 0xFF,  // 票数-高字节
        (score >> 8) & 0xFF,   // 票数-中字节
        score & 0xFF,          // 票数-低字节
        0,                     // 赠送 = 0
        0, 0, 0, 0             // 码表数 = 0 (4字节)
      ];
      this.addLog('info', this.t('send_lottery_data', {score: score, hex: lotteryData.map(b => b.toString(16).padStart(2, '0')).join(' ')}));
      await this.sendCommand(0x01, 0x07, lotteryData);
      this.addLog('success', this.t('lottery_sent'));

      // 使用 order_id 作为QR码发送到出票机
      if (!orderId) {
        this.addLog('error', this.t('order_id_not_found'));
        this.$message.error({ content: this.t('order_id_not_found'), duration: 3 });
        return;
      }

      // 发送到出票机（不等待响应，设备会直接打印）
      // 追加1字节填充(0x20)，避免打印机短数据截断bug
      const data = [...Array.from(orderId).map(c => c.charCodeAt(0)), 0x20];
      await this.sendCommand(0x01, 0x08, data, false);
      this.addLog('success', this.t('qr_sent', {order_id: orderId, len: data.length}));

      // 重启心跳
      this.heartbeatTimer = setInterval(async () => {
        await this.sendCommand(0x01, 0x01, [], true, true);
      }, 10000);
      this.addLog('info', this.t('heartbeat_restarted'));
    },

    // 本地过滤玩家选项
    filterPlayerOption(input, option) {
      return option.label.toLowerCase().indexOf(input.toLowerCase()) >= 0;
    },

    // 玩家选择变化
    handlePlayerChange(value) {
      if (!value) {
        // 清空选择时，重新加载全部玩家
        this.playerKeyword = '';
        this.playerPage = 1;
        this.playerHasMore = true;
        this.playerOptions = [];
        this.playerBetInfo = null;
        this.loadPlayers();
      } else if (this.ticketType === 3 || this.ticketType === 4) {
        // 福利卷/体验卷选择玩家时，获取打码量信息
        this.loadPlayerBetInfo(value);
      }
    },

    // 加载玩家打码量信息
    async loadPlayerBetInfo(playerId) {
      if (!this.player_bet_info_url || !playerId) {
        this.playerBetInfo = null;
        return;
      }

      this.betInfoLoading = true;
      try {
        const res = await this.$request({
          url: this.player_bet_info_url,
          method: 'get',
          params: { player_id: playerId },
        });

        if (res.code === 200 && res.data) {
          this.playerBetInfo = res.data;
        } else {
          this.playerBetInfo = null;
        }
      } catch (e) {
        console.error('获取玩家打码量失败:', e);
        this.playerBetInfo = null;
      } finally {
        this.betInfoLoading = false;
      }
    },

    // 刷新玩家打码量信息
    refreshPlayerBetInfo() {
      if (this.selectedPlayerId) {
        this.loadPlayerBetInfo(this.selectedPlayerId);
      }
    },

    // 格式化数字
    formatNumber(num) {
      if (num === null || num === undefined) return '0.00';
      return parseFloat(num).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    },

    // 搜索玩家
    searchPlayers(keyword) {
      this.playerKeyword = keyword || '';
      this.playerPage = 1;
      this.playerHasMore = true;
      this.playerOptions = [];
      this.loadPlayers();
    },

    // 加载玩家列表
    async loadPlayers() {
      if (this.playerSearching) return;

      this.playerSearching = true;
      try {
        const res = await this.$request({
          url: 'ex-admin/addons-webman-controller-ChannelIndexController/searchPlayers',
          method: 'get',
          params: {
            keyword: this.playerKeyword,
            page: this.playerPage,
            page_size: 50,
          },
        });

        if (res.code === 200 && res.data) {
          const newOptions = res.data.map(p => ({
            value: p.id,
            label: p.name + (p.uuid ? ' (' + p.uuid + ')' : ''),
          }));

          if (this.playerPage === 1) {
            this.playerOptions = newOptions;
          } else {
            this.playerOptions = [...this.playerOptions, ...newOptions];
          }

          this.playerHasMore = newOptions.length >= 50;
        }
      } catch (e) {
        console.error('加载玩家失败:', e);
      } finally {
        this.playerSearching = false;
      }
    },

    // 滚动加载更多
    handlePlayerScroll(e) {
      const { target } = e;
      if (target.scrollTop + target.offsetHeight >= target.scrollHeight - 20) {
        if (this.playerHasMore && !this.playerSearching) {
          this.playerPage++;
          this.loadPlayers();
        }
      }
    },

    // 发送HEX
    async sendHex() {
      if (!this.hexCommand) { this.addLog('error', this.t('invalid_hex')); return; }
      const hex = this.hexCommand.replace(/\s/g, '');
      if (!/^[0-9A-Fa-f]+$/.test(hex) || hex.length % 2 !== 0) {
        this.addLog('error', this.t('invalid_hex'));
        return;
      }
      const data = [];
      for (let i = 0; i < hex.length; i += 2) {
        data.push(parseInt(hex.substr(i, 2), 16));
      }

      const writer = this.port.writable.getWriter();
      await writer.write(new Uint8Array(data));
      writer.releaseLock();

      this.addLog('success', this.t('hex_sent', {hex: this.hexCommand}));
    },

    // 清空日志
    clearLog() {
      this.logs = [];
    },
  },

  mounted() {
    this.addLog('info', '========================================');
    this.addLog('info', this.t('init_complete'));
    this.addLog('info', this.labels.using_web_serial || '使用 Web Serial API 直接访问串口');
    this.addLog('info', this.labels.use_chrome_edge || '请使用 Chrome/Edge 浏览器');
    this.addLog('info', '========================================');

    // 加载串口列表
    this.loadPorts();

    // 加载玩家列表
    this.loadPlayers();
  },

  beforeUnmount() {
    this.disconnect();
  },
};
</script>
