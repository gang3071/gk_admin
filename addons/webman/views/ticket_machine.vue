<template>
  <div style="padding: 16px; display: flex; justify-content: center;">
    <div style="width: 100%; max-width: 800px;">
    <!-- 连接配置 -->
    <a-card title="连接配置" size="small" style="margin-bottom: 16px;" :headStyle="{borderBottom: '2px solid #409EFF'}">
      <a-row :gutter="16">
        <a-col :span="6">
          <div style="margin-bottom: 8px; font-weight: 500;">串口路径</div>
          <a-input v-model:value="config.port" placeholder="点击连接时选择串口" disabled />
        </a-col>
        <a-col :span="4">
          <div style="margin-bottom: 8px; font-weight: 500;">波特率</div>
          <a-select v-model:value="config.baudRate" style="width: 100%;">
            <a-select-option value="9600">9600</a-select-option>
            <a-select-option value="19200">19200</a-select-option>
            <a-select-option value="38400">38400</a-select-option>
            <a-select-option value="57600">57600</a-select-option>
            <a-select-option value="115200">115200</a-select-option>
          </a-select>
        </a-col>
        <a-col :span="4">
          <div style="margin-bottom: 8px; font-weight: 500;">状态</div>
          <a-tag :color="isConnected ? 'success' : 'error'" style="padding: 4px 12px; font-size: 14px;">
            {{ isConnected ? '✓ 已连接' : '✗ 未连接' }}
          </a-tag>
        </a-col>
        <a-col :span="6">
          <div style="margin-bottom: 8px; font-weight: 500;">&nbsp;</div>
          <a-space>
            <a-button type="primary" @click="connect" :disabled="isConnected">连接</a-button>
            <a-button danger @click="disconnect" :disabled="!isConnected">断开</a-button>
          </a-space>
        </a-col>
      </a-row>
    </a-card>

    <!-- 设备配置 + 操作按钮 -->
    <a-row :gutter="16" style="margin-bottom: 16px;">
      <a-col :span="12">
        <a-card title="设备配置" size="small" :headStyle="{borderBottom: '2px solid #67C23A'}">
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">唯一ID (UID)</div>
            <div style="color: #909399; font-size: 12px; margin-bottom: 4px;">16个字符的唯一标识</div>
            <a-input v-model:value="config.uid" maxlength="16" placeholder="16字符" />
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">机台号</div>
            <div style="color: #909399; font-size: 12px; margin-bottom: 4px;">范围: 0-65535</div>
            <a-input-number v-model:value="config.machineNo" :min="0" :max="65535" placeholder="0-65535" style="width: 100%;" />
          </div>
          <div style="margin-bottom: 16px;">
            <div style="font-weight: 500; margin-bottom: 4px;">店名称</div>
            <div style="color: #909399; font-size: 12px; margin-bottom: 4px;">最多10个字符</div>
            <a-input v-model:value="config.storeName" maxlength="10" placeholder="店名称" />
          </div>
          <a-button type="primary" block @click="initMachine" :disabled="!isConnected">初始化设备</a-button>
        </a-card>
      </a-col>
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
    </a-row>

    <!-- 出票操作 -->
    <a-row :gutter="16" style="margin-bottom: 16px;">
      <a-col :span="12">
        <a-card title="彩票数据" size="small" :headStyle="{borderBottom: '2px solid #409EFF'}">
          <a-row :gutter="8" style="margin-bottom: 12px;">
            <a-col :span="6">
              <div style="font-weight: 500; margin-bottom: 4px;">票数</div>
              <a-input-number v-model:value="lottery.ticketCount" :min="0" placeholder="票数" style="width: 100%;" />
            </a-col>
            <a-col :span="6">
              <div style="font-weight: 500; margin-bottom: 4px;">赠送</div>
              <a-input-number v-model:value="lottery.giftCount" :min="0" placeholder="赠送" style="width: 100%;" />
            </a-col>
            <a-col :span="6">
              <div style="font-weight: 500; margin-bottom: 4px;">码表</div>
              <a-input-number v-model:value="lottery.codeTable" :min="0" placeholder="码表" style="width: 100%;" />
            </a-col>
            <a-col :span="6">
              <div style="font-weight: 500; margin-bottom: 4px;">数</div>
              <a-input-number v-model:value="lottery.number" :min="0" :max="999999" placeholder="0-999999" style="width: 100%;" />
            </a-col>
          </a-row>
          <a-button type="primary" block @click="sendLottery" :disabled="!isConnected">发送彩票数据</a-button>
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
            <div style="font-weight: 500; margin-bottom: 4px;">分数/金额</div>
            <a-input-number v-model:value="ticketScore" :min="0" placeholder="分数/金额" style="width: 100%;" />
          </div>
          <div style="margin-bottom: 12px;">
            <div style="font-weight: 500; margin-bottom: 4px;">QR码内容</div>
            <a-input v-model:value="qrCode" placeholder="QR码内容" />
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
      config: {
        port: '',
        baudRate: this.default_baud_rate || '115200',
        uid: '',
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
      qrCode: '',
      ticketType: 1,
      ticketScore: 0,
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
    async sendCommand(cmdType, cmd, data = []) {
      if (!this.port || !this.isConnected) {
        this.addLog('error', '串口未连接');
        return null;
      }

      const frame = this.buildFrame(cmdType, cmd, data);
      const hexStr = Array.from(frame).map(b => b.toString(16).padStart(2, '0').toUpperCase()).join(' ');
      this.addLog('send', '发送 [' + frame.length + '字节]: ' + hexStr);

      try {
        const writer = this.port.writable.getWriter();
        await writer.write(frame);
        writer.releaseLock();
        this.addLog('info', '数据已写入串口');
      } catch (e) {
        this.addLog('error', '发送失败: ' + e.message);
        return null;
      }

      // 等待响应
      return new Promise((resolve) => {
        const timeout = setTimeout(() => {
          this.pendingResolve = null;
          this.addLog('warn', '响应超时 (2秒)');
          resolve(null);
        }, 2000);

        this.pendingResolve = (responseData) => {
          clearTimeout(timeout);
          this.pendingResolve = null;
          resolve(responseData);
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
                  const hexStr = Array.from(frame.data).map(b => b.toString(16).padStart(2, '0').toUpperCase()).join(' ');
                  this.addLog('success', '解析帧: ' + frame.cmdType.toString(16).padStart(2, '0') + ' ' + frame.cmd.toString(16).padStart(2, '0') + ' DATA=[' + hexStr + ']');

                  if (this.pendingResolve) {
                    this.pendingResolve(frame);
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

    // 连接串口
    async connect() {
      if (!('serial' in navigator)) {
        this.addLog('error', '浏览器不支持 Web Serial API，请使用 Chrome/Edge 浏览器');
        return;
      }

      // 如果已连接，先断开
      if (this.isConnected || this.port) {
        await this.disconnect();
      }

      try {
        this.addLog('info', '请选择串口设备...');

        // 弹出串口选择框
        this.port = await navigator.serial.requestPort();

        // 获取端口信息
        const info = this.port.getInfo();
        const portName = 'USB Serial (VID:' + (info.usbVendorId || '????').toString(16) + ' PID:' + (info.usbProductId || '????').toString(16) + ')';
        this.config.port = portName;

        this.addLog('info', '已选择: ' + portName);
        this.addLog('info', '正在打开串口，波特率: ' + this.config.baudRate);

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
        } else {
          this.addLog('warn', '设备未响应，请检查：');
          this.addLog('warn', '1. 出票机是否已开机');
          this.addLog('warn', '2. TX/RX 是否交叉连接');
          this.addLog('warn', '3. 波特率是否正确');
        }

        // 启动心跳
        this.addLog('info', '心跳已启动 (每10秒)');
        this.heartbeatTimer = setInterval(async () => {
          await this.sendCommand(0x01, 0x01);
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

    // 同步时间
    async syncDatetime() {
      const now = new Date();
      const data = [
        (now.getFullYear() >> 8) & 0xFF, now.getFullYear() & 0xFF,
        now.getMonth() + 1, now.getDate(), now.getHours(), now.getMinutes(), now.getSeconds()
      ];
      const r = await this.sendCommand(0x01, 0x02, data);
      this.addLog(r ? 'success' : 'error', r ? '日期时间已同步' : '同步失败');
    },

    // 初始化设备
    async initMachine() {
      if (!this.config.uid) { this.addLog('error', '请输入UID'); return; }

      this.addLog('info', '===== 开始初始化设备 =====');

      // 1. 同步时间
      const now = new Date();
      await this.sendCommand(0x01, 0x02, [
        (now.getFullYear() >> 8) & 0xFF, now.getFullYear() & 0xFF,
        now.getMonth() + 1, now.getDate(), now.getHours(), now.getMinutes(), now.getSeconds()
      ]);
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
      if (!this.qrCode) { this.addLog('error', '请输入QR码内容'); return; }

      // TODO: 测试模式 - 跳过实际出票，只保存数据
      const TEST_MODE = true;

      if (TEST_MODE) {
        this.addLog('warn', '[测试模式] 跳过实际出票');
      } else {
        // 发送到出票机
        const data = Array.from(this.qrCode).map(c => c.charCodeAt(0));
        const r = await this.sendCommand(0x01, 0x08, data);

        if (!r) {
          this.addLog('error', 'QR码发送失败');
          return;
        }
        this.addLog('success', 'QR码已发送并打印');
      }

      // 保存到数据库
      if (this.save_ticket_url) {
        try {
          const saveRes = await this.$request({
            url: this.save_ticket_url,
            method: 'post',
            data: {
              store_name: this.config.storeName,
              machine_no: this.config.machineNo,
              score: this.ticketScore,
              qr_code: this.qrCode,
              ticket_type: this.ticketType,
              store_admin_id: this.store_admin_id,
              department_id: this.department_id,
            },
          });

          if (saveRes.code === 200) {
            this.addLog('success', '票据记录已保存: ' + (saveRes.data?.order_id || ''));
          } else {
            this.addLog('warn', '票据记录保存失败: ' + (saveRes.message || ''));
          }
        } catch (e) {
          this.addLog('warn', '票据记录保存异常: ' + (e.message || ''));
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
  },

  beforeUnmount() {
    this.disconnect();
  },
};
</script>
