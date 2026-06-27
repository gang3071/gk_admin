<template>
  <div style="padding: 16px; display: flex; justify-content: center;">
    <div style="width: 100%; max-width: 800px;">
    <!-- 连接配置 -->
    <a-card title="连接配置" size="small" style="margin-bottom: 16px;" :headStyle="{borderBottom: '2px solid #409EFF'}">
      <a-row :gutter="16">
        <a-col :span="16">
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 6px;">串口路径</div>
            <div style="display: flex; gap: 8px;">
              <a-select
                v-model:value="config.port"
                placeholder="选择串口"
                style="flex: 1;"
                :loading="portsLoading"
                @dropdownVisibleChange="handlePortDropdown"
                allowClear
              >
                <a-select-option v-for="port in availablePorts" :key="port.path" :value="port.path">
                  {{ port.path }}
                </a-select-option>
              </a-select>
              <a-button @click="addNewPort" style="flex-shrink: 0;">添加串口</a-button>
            </div>
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 6px;">波特率</div>
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
              {{ isConnected ? '✓ 已连接' : '✗ 未连接' }}
            </a-tag>
            <div style="display: flex; gap: 12px;">
              <a-button type="primary" @click="connect" :disabled="isConnected" style="min-width: 90px;">连接</a-button>
              <a-button danger @click="disconnect" :disabled="!isConnected" style="min-width: 90px;">断开</a-button>
            </div>
          </div>
        </a-col>
      </a-row>
    </a-card>

    <!-- 设备操作 + QR码打印 -->
    <a-row :gutter="16" style="margin-bottom: 16px;">
      <a-col :span="12">
        <a-card title="设备操作" size="small" :headStyle="{borderBottom: '2px solid #E6A23C'}">
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">打印序列号</div>
            <a-input-number v-model:value="config.serialNo" :min="0" :max="9999999" placeholder="0-9999999" style="width: 100%;" />
          </div>
          <a-button block @click="setSerialNo" style="margin-bottom: 16px;" :disabled="!isConnected">设置序列号</a-button>
          <a-row :gutter="8" style="margin-bottom: 8px;">
            <a-col :span="12">
              <a-button block @click="syncDatetime" :disabled="!isConnected">同步时间</a-button>
            </a-col>
            <a-col :span="12">
              <a-button block @click="sendHeartbeat" :disabled="!isConnected">心跳</a-button>
            </a-col>
          </a-row>
          <a-row :gutter="8">
            <a-col :span="12">
              <a-button block @click="queryStatus" :disabled="!isConnected">查询状态</a-button>
            </a-col>
            <a-col :span="12">
              <a-button danger block @click="resetMachine" :disabled="!isConnected">复位</a-button>
            </a-col>
          </a-row>
        </a-card>
      </a-col>
      <a-col :span="12">
        <a-card title="QR码打印" size="small" :headStyle="{borderBottom: '2px solid #67C23A'}">
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">票据类型</div>
            <a-select v-model:value="ticketType" style="width: 100%;">
              <a-select-option :value="1">开分</a-select-option>
              <a-select-option :value="2">洗分</a-select-option>
            </a-select>
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">关联玩家（可选）</div>
            <a-select
              v-model:value="selectedPlayerId"
              show-search
              placeholder="选择或搜索玩家"
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
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">分数/金额</div>
            <a-input-number v-model:value="ticketScore" :min="0" placeholder="分数/金额" style="width: 100%;" />
          </div>
          <a-button type="primary" block @click="sendQrCode" :disabled="!isConnected">发送QR码</a-button>
        </a-card>
      </a-col>
    </a-row>

    <!-- 通信日志 -->
    <a-card size="small" :headStyle="{borderBottom: '2px solid #909399'}">
      <template #title>
        <span>通信日志</span>
        <a-button size="small" @click="clearLog" style="float: right;">清空日志</a-button>
      </template>
      <div ref="logContainer" style="height: 200px; overflow: auto; background: #1e1e1e; padding: 12px; font-family: Consolas, Monaco, monospace; font-size: 12px; border-radius: 4px; color: #d4d4d4; line-height: 1.6;">
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
    store_admin_id: Number,
    department_id: Number,
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
      hexCommand: '',
      logs: [],
      receiveBuffer: [],
      pendingResolve: null,
    };
  },
  methods: {
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
        if (!silent) this.addLog('error', '串口未连接');
        return null;
      }

      const frame = this.buildFrame(cmdType, cmd, data);
      const hexStr = Array.from(frame).map(b => b.toString(16).padStart(2, '0').toUpperCase()).join(' ');
      if (!silent) this.addLog('send', '发送 CMD=' + cmd.toString(16).padStart(2, '0') + ' [' + frame.length + '字节]: ' + hexStr);

      try {
        const writer = this.port.writable.getWriter();
        await writer.write(frame);
        writer.releaseLock();
        if (!silent) this.addLog('info', '数据已写入串口');
      } catch (e) {
        if (!silent) this.addLog('error', '发送失败: ' + e.message);
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
          if (!silent) this.addLog('warn', '响应超时 CMD=' + cmd.toString(16).padStart(2, '0') + ' (2秒)');
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

      this.addLog('info', '读取循环已启动');

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
                      this.addLog('info', '期望响应: cmd=' + this.pendingResolve.cmd.toString(16).padStart(2, '0') + ', 收到: cmd=' + frame.cmd.toString(16).padStart(2, '0'));
                    }
                    if (this.pendingResolve.cmd === frame.cmd) {
                      if (!isSilent) this.addLog('success', '命令响应匹配成功');
                      this.pendingResolve.callback(frame);
                    } else {
                      if (!isSilent) this.addLog('warn', '命令响应不匹配，忽略');
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
              this.addLog('error', '读取错误: ' + e.message);
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

      this.addLog('info', '读取循环已退出');
    },

    // 获取可用串口列表
    async loadPorts() {
      if (!('serial' in navigator)) {
        this.addLog('warn', '浏览器不支持 Web Serial API');
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
          this.addLog('info', '未发现已授权串口，请先点击"添加串口"授权设备');
        }
      } catch (e) {
        this.addLog('error', '获取串口列表失败: ' + e.message);
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
        this.addLog('error', '浏览器不支持 Web Serial API');
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
          this.addLog('error', '添加串口失败: ' + e.message);
        }
      }
    },

    // 连接串口
    async connect() {
      if (!('serial' in navigator)) {
        this.addLog('error', '浏览器不支持 Web Serial API，请使用 Chrome/Edge 浏览器');
        return;
      }

      if (!this.config.port) {
        this.addLog('error', '请先选择串口');
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
          this.addLog('error', '未找到选中的串口，请重新选择');
          return;
        }

        this.port = selectedPort.port;
        this.addLog('info', '正在打开串口: ' + this.config.port);
        this.addLog('info', '波特率: ' + this.config.baudRate);

        // 打开串口
        await this.port.open({
          baudRate: parseInt(this.config.baudRate),
          dataBits: 8,
          stopBits: 1,
          parity: 'none',
          flowControl: 'none',
        });

        this.isConnected = true;
        this.addLog('success', '串口已打开！波特率: ' + this.config.baudRate);

        // 设置 DTR/RTS 信号（某些设备需要）
        try {
          const signals = await this.port.getSignals();
          this.addLog('info', '当前信号: DTR=' + signals.dataTerminalReady + ' RTS=' + signals.readyToSend);

          // 设置 DTR 为 true
          await this.port.setSignals({ dataTerminalReady: true });
          this.addLog('info', '已设置 DTR=true');
        } catch (e) {
          this.addLog('warn', '设置信号失败: ' + e.message);
        }

        // 启动读取循环
        this.startReadLoop();

        // 等待读取循环启动
        await new Promise(r => setTimeout(r, 200));

        // 测试发送心跳
        this.addLog('info', '测试发送心跳...');
        const testResult = await this.sendCommand(0x01, 0x01);

        if (testResult) {
          this.addLog('success', '设备响应正常！');

          // 连接成功后自动初始化设备
          this.addLog('info', '===== 自动初始化设备 =====');

          // 1. 同步时间
          this.addLog('info', '自动同步时间...');
          const timeResult = await this.syncDatetime();
          this.addLog(timeResult ? 'success' : 'error', '同步时间: ' + (timeResult ? '成功' : '失败'));
          await new Promise(r => setTimeout(r, 50)); // 延迟50ms

          // 2. 设置UID（使用店机UUID）
          this.addLog('info', '准备设置UID, config.uid=' + this.config.uid);
          if (this.config.uid) {
            const uid = this.config.uid.padStart(16, '0').substring(0, 16);
            const uidData = Array.from(uid).map(c => c.charCodeAt(0));
            this.addLog('info', '发送UID数据: ' + uid + ' (长度:' + uidData.length + ')');
            const uidResult = await this.sendCommand(0x01, 0x03, uidData);
            this.addLog(uidResult ? 'success' : 'error', 'UID设置: ' + (uidResult ? '成功' : '失败') + ' -> ' + uid);
            await new Promise(r => setTimeout(r, 50)); // 延迟50ms
          } else {
            this.addLog('warn', 'UID为空，跳过设置');
          }

          // 3. 设置店名称（使用后端GBK编码）
          this.addLog('info', '准备设置店名称, config.storeName=' + this.config.storeName);
          if (this.config.storeName) {
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
                this.addLog('info', 'GBK编码: ' + encodeRes.hex);
              } else {
                this.addLog('warn', 'GBK编码失败，使用UTF-8');
                const encoder = new TextEncoder();
                nameBytes = Array.from(encoder.encode(name));
              }
            } catch (e) {
              this.addLog('warn', 'GBK编码请求失败，使用UTF-8: ' + e.message);
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
            this.addLog('info', '发送店名称数据: [' + nameHex + '] (长度:' + nameBytes.length + ')');
            const nameResult = await this.sendCommand(0x01, 0x05, nameBytes);
            this.addLog(nameResult ? 'success' : 'error', '店名称设置: ' + (nameResult ? '成功' : '失败') + ' -> "' + name + '"');
          } else {
            this.addLog('warn', '店名称为空，跳过设置');
          }

          this.addLog('success', '===== 设备初始化完成 =====');
        } else {
          this.addLog('warn', '设备未响应，请检查：');
          this.addLog('warn', '1. 出票机是否已开机');
          this.addLog('warn', '2. TX/RX 是否交叉连接');
          this.addLog('warn', '3. 波特率是否正确');
        }

        // 启动心跳
        this.addLog('info', '心跳已启动 (每10秒)');
        this.heartbeatTimer = setInterval(async () => {
          await this.sendCommand(0x01, 0x01, [], true, true);
        }, 10000);

      } catch (e) {
        if (e.name === 'NotFoundError') {
          this.addLog('info', '用户取消了串口选择');
        } else {
          this.addLog('error', '连接失败: ' + e.message);
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
      this.addLog('info', '正在断开连接...');
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
      this.addLog('warn', '已断开连接');
    },

    // 心跳
    async sendHeartbeat() {
      const r = await this.sendCommand(0x01, 0x01);
      this.addLog(r ? 'success' : 'error', r ? '心跳正常 - 设备在线' : '心跳失败');
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

      this.addLog('info', `北京时间: 20${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')} ${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`);

      const r = await this.sendCommand(0x01, 0x02, data);
      this.addLog(r ? 'success' : 'error', r ? '日期时间已同步' : '同步失败');
      return !!r;
    },

    // 初始化设备
    async initMachine() {
      if (!this.config.uid) { this.addLog('error', '请输入UID'); return; }

      this.addLog('info', '===== 开始初始化设备 =====');

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

      this.addLog('info', `北京时间: 20${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')} ${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`);

      await this.sendCommand(0x01, 0x02, [year, month, day, hours, minutes, seconds]);
      this.addLog('success', '日期时间已同步');

      // 2. 设置UID
      const uid = this.config.uid.padStart(16, '0').substring(0, 16);
      const uidData = Array.from(uid).map(c => c.charCodeAt(0));
      await this.sendCommand(0x01, 0x03, uidData);
      this.addLog('success', 'UID已设置: ' + uid);

      // 3. 设置机台号
      const no = this.config.machineNo || 0;
      await this.sendCommand(0x01, 0x04, [(no >> 8) & 0xFF, no & 0xFF]);
      this.addLog('success', '机台号已设置: ' + no);

      // 4. 设置店名称
      let name = (this.config.storeName || '').padEnd(10, ' ').substring(0, 10);
      const nameData = Array.from(name).map(c => c.charCodeAt(0));
      await this.sendCommand(0x01, 0x05, nameData);
      this.addLog('success', '店名称已设置: ' + name.trim());

      this.addLog('success', '===== 设备初始化完成 =====');
    },

    // 设置序列号
    async setSerialNo() {
      const no = this.config.serialNo || 0;
      const data = [(no >> 24) & 0xFF, (no >> 16) & 0xFF, (no >> 8) & 0xFF, no & 0xFF];
      const r = await this.sendCommand(0x01, 0x06, data);
      this.addLog(r ? 'success' : 'error', r ? '序列号已设置: ' + no : '设置失败');
    },

    // 查询状态
    async queryStatus() {
      const r = await this.sendCommand(0x01, 0x02);
      this.addLog(r ? 'info' : 'error', r ? '状态查询已发送' : '查询失败');
    },

    // 复位
    async resetMachine() {
      const r = await this.sendCommand(0x01, 0x09);
      this.addLog(r ? 'success' : 'error', r ? '复位指令已发送' : '复位失败');
    },

    // 发送彩票数据
    async sendLottery() {
      const tc = this.lottery.ticketCount || 0;
      const gc = this.lottery.giftCount || 0;
      const ct = this.lottery.codeTable || 0;
      const num = this.lottery.number || 0;
      const data = [tc & 0xFF, gc & 0xFF, ct & 0xFF, (num >> 24) & 0xFF, (num >> 16) & 0xFF, (num >> 8) & 0xFF, num & 0xFF];

      this.addLog('info', '票数:' + tc + ' 赠送:' + gc + ' 码表:' + ct + ' 数:' + num);
      const r = await this.sendCommand(0x01, 0x07, data);
      this.addLog(r ? 'success' : 'error', r ? '彩票数据已发送' : '发送失败');
    },

    // 发送QR码
    async sendQrCode() {
      if (!this.ticketScore || this.ticketScore <= 0) {
        this.addLog('error', '请输入有效的分数/金额');
        return;
      }

      // 暂停心跳，避免干扰命令响应
      if (this.heartbeatTimer) {
        clearInterval(this.heartbeatTimer);
        this.heartbeatTimer = null;
        this.addLog('info', '心跳已暂停');
      }

      // 先保存到数据库，获取加密后的内容
      if (!this.save_ticket_url) {
        this.addLog('error', '保存地址未配置');
        return;
      }

      let encryptedContent = '';
      let orderId = '';

      try {
        this.addLog('info', '保存数据: player_id=' + this.selectedPlayerId);

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
          },
        });

        if (saveRes.code === 200) {
          orderId = saveRes.data?.order_id || '';
          encryptedContent = saveRes.data?.encrypted_content || '';
          this.addLog('success', '票据记录已保存: ' + orderId);
        } else {
          this.addLog('error', '票据记录保存失败: ' + (saveRes.message || ''));
          return;
        }
      } catch (e) {
        this.addLog('error', '票据记录保存异常: ' + (e.message || ''));
        return;
      }

      // 设置序列号为 order_id 的数字部分（取后7位）
      this.addLog('info', '准备设置序列号, order_id=' + orderId);
      const numericPart = orderId.replace(/[^0-9]/g, '');
      this.addLog('info', '提取数字部分: ' + numericPart + ' (长度:' + numericPart.length + ')');
      const serialNo = parseInt(numericPart.slice(-7)) || 0;
      this.config.serialNo = serialNo;
      const serialData = [(serialNo >> 24) & 0xFF, (serialNo >> 16) & 0xFF, (serialNo >> 8) & 0xFF, serialNo & 0xFF];
      const serialHex = serialData.map(b => b.toString(16).padStart(2, '0')).join(' ');
      this.addLog('info', '发送序列号数据: [' + serialHex + '] = ' + serialNo + ' (4字节)');

      // 手动构造帧并发送，用于调试
      const frame = this.buildFrame(0x01, 0x06, serialData);
      const frameHex = Array.from(frame).map(b => b.toString(16).padStart(2, '0').toUpperCase()).join(' ');
      this.addLog('send', '序列号帧: ' + frameHex);

      const serialResult = await this.sendCommand(0x01, 0x06, serialData);
      this.addLog(serialResult ? 'success' : 'error', '序列号设置: ' + (serialResult ? '成功' : '失败') + ' -> ' + serialNo);

      // 等待更长时间确保设备处理完成
      await new Promise(r => setTimeout(r, 100));

      // 先发送彩票数据（分数设置到前两位，其他传0）
      const score = Math.floor(this.ticketScore);
      const lotteryData = [
        (score >> 8) & 0xFF,  // 票数 = 分数高字节
        score & 0xFF,         // 赠送 = 分数低字节
        0,                    // 码表 = 0
        0, 0, 0, 0           // 数 = 0 (4字节)
      ];
      this.addLog('info', '发送彩票数据: 分数=' + score);
      await this.sendCommand(0x01, 0x07, lotteryData);
      this.addLog('success', '彩票数据已发送');

      // 使用加密内容作为QR码发送到出票机
      if (!encryptedContent) {
        this.addLog('error', '未获取到加密内容');
        return;
      }

      // 发送到出票机（不等待响应，设备会直接打印）
      const data = Array.from(encryptedContent).map(c => c.charCodeAt(0));
      await this.sendCommand(0x01, 0x08, data, false);
      this.addLog('success', 'QR码已发送: ' + orderId);

      // 重启心跳
      this.heartbeatTimer = setInterval(async () => {
        await this.sendCommand(0x01, 0x01, [], true, true);
      }, 10000);
      this.addLog('info', '心跳已重启');
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
        this.loadPlayers();
      }
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
      if (!this.hexCommand) { this.addLog('error', '请输入HEX指令'); return; }
      const hex = this.hexCommand.replace(/\s/g, '');
      if (!/^[0-9A-Fa-f]+$/.test(hex) || hex.length % 2 !== 0) {
        this.addLog('error', '无效的HEX格式');
        return;
      }
      const data = [];
      for (let i = 0; i < hex.length; i += 2) {
        data.push(parseInt(hex.substr(i, 2), 16));
      }

      const writer = this.port.writable.getWriter();
      await writer.write(new Uint8Array(data));
      writer.releaseLock();

      this.addLog('success', 'HEX已发送: ' + this.hexCommand);
    },

    // 清空日志
    clearLog() {
      this.logs = [];
      this.addLog('info', '日志已清空');
    },
  },

  mounted() {
    this.addLog('info', '========================================');
    this.addLog('info', '出票机控制面板已加载');
    this.addLog('info', '使用 Web Serial API 直接访问串口');
    this.addLog('info', '请使用 Chrome/Edge 浏览器');
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
