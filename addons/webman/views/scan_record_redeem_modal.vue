<template>
  <div style="padding: 16px;">
    <!-- 掃碼輸入區域 -->
    <a-card size="small" style="margin-bottom: 16px;">
      <template #title>
        <span>扫码核销</span>
      </template>
      <a-row :gutter="16">
        <a-col :span="16">
          <a-input
            v-model:value="qrCodeNo"
            :placeholder="isGv1Locked ? labels.scan_qr_code_placeholder : (labels.scan_qr_code_placeholder || '请输入或扫描二维码编号')"
            :type="inputType"
            size="large"
            ref="qrInput"
            :disabled="inputLocked"
            @input="onScanInput"
          >
            <template #prefix>
              <qrcode-outlined style="color: #bfbfbf;" />
            </template>
          </a-input>
        </a-col>
        <a-col :span="8">
          <a-button
            v-if="!inputLocked"
            type="primary"
            size="large"
            block
            :loading="loading"
            @click="queryRecord"
          >
            <template #icon><search-outlined /></template>
            查找
          </a-button>
          <a-button
            v-else
            size="large"
            block
            @click="resetScan"
          >
            <template #icon><reload-outlined /></template>
            重新扫码
          </a-button>
        </a-col>
      </a-row>
    </a-card>

    <!-- GV1 寄存碼資訊（掃到 GV1 時顯示，代替普通訂單資訊） -->
    <a-card
      v-if="gv1Info"
      size="small"
      style="margin-bottom: 16px;"
      :headStyle="{borderBottom: '2px solid #13c2c2'}"
    >
      <template #title>
        <save-outlined style="margin-right:6px;color:#13c2c2;" />
        點數寄存碼
        <a-spin v-if="notifying" size="small" style="margin-left:8px;" />
      </template>

      <!-- 玩家資訊（通知接口返回後顯示） -->
      <a-descriptions v-if="playerInfo" :column="2" size="small" bordered style="margin-bottom:12px;">
        <!-- 帳號名稱 -->
        <a-descriptions-item label="玩家帳號">
          <span
            style="cursor:pointer;color:#1890ff;"
            :title="'點擊複製：' + playerInfo.name"
            @click="copyText(playerInfo.name)"
          >{{ playerInfo.name || '-' }}</span>
          <copy-outlined style="margin-left:4px;color:#bfbfbf;cursor:pointer;" @click="copyText(playerInfo.name)" />
        </a-descriptions-item>
        <!-- UUID -->
        <a-descriptions-item label="UUID">
          <span
            style="cursor:pointer;color:#1890ff;font-family:monospace;"
            :title="'點擊複製：' + playerInfo.uuid"
            @click="copyText(playerInfo.uuid)"
          >{{ playerInfo.uuid || '-' }}</span>
          <copy-outlined style="margin-left:4px;color:#bfbfbf;cursor:pointer;" @click="copyText(playerInfo.uuid)" />
        </a-descriptions-item>
        <!-- 餘額 -->
        <a-descriptions-item label="當前餘額">
          <span style="color:#f5222d;font-weight:bold;">{{ playerInfo.balance }}</span>
        </a-descriptions-item>
        <!-- 寄存金額 -->
        <a-descriptions-item :label="labels.deposit_amount || '寄存金額'">
          <span style="color:#fa8c16;font-weight:bold;font-size:15px;">{{ gv1Info.amount }}</span>
        </a-descriptions-item>
        <!-- 錢包狀態 -->
        <a-descriptions-item label="錢包狀態" :span="2">
          <a-tag :color="playerInfo.wallet_locked ? 'red' : 'green'">
            {{ playerInfo.wallet_locked ? '🔒 已鎖定' : '✅ 正常' }}
          </a-tag>
          <a-tag v-if="playerInfo.is_crashed" color="volcano" style="margin-left:4px;">
            💥 已暴機
          </a-tag>
        </a-descriptions-item>
        <!-- 有效期 -->
        <a-descriptions-item label="有效期" :span="2">
          <a-tag :color="expiryColor">{{ expiryText }}</a-tag>
        </a-descriptions-item>
      </a-descriptions>

      <!-- 尚未取回玩家資訊時僅顯示基本資訊 -->
      <a-descriptions v-else :column="2" size="small" bordered style="margin-bottom:12px;">
        <a-descriptions-item :label="labels.deposit_amount || '寄存金額'">
          <span style="color:#fa8c16;font-weight:bold;font-size:15px;">{{ gv1Info.amount }}</span>
        </a-descriptions-item>
        <a-descriptions-item label="有效期">
          <a-tag :color="expiryColor">{{ expiryText }}</a-tag>
        </a-descriptions-item>
      </a-descriptions>

      <div>
        <a-button
          type="primary"
          size="large"
          block
          :disabled="gv1ExpirySeconds <= 0"
          :loading="redeeming"
          @click="redeemDepositCode"
        >
          <template #icon><check-circle-outlined /></template>
          {{ labels.deposit_confirm || '確認寄存' }}
        </a-button>
      </div>
    </a-card>

    <!-- 普通訂單資訊（非 GV1 時顯示） -->
    <a-card v-if="record && !gv1Info" size="small" title="订单信息" style="margin-bottom: 16px;" :headStyle="{borderBottom: '2px solid #1890ff'}">
      <a-descriptions :column="2" size="small" bordered>
        <a-descriptions-item :label="labels.order_id" :span="2">
          <a-tag color="blue">{{ record.order_id || '-' }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item :label="labels.store_name">{{ record.store_name || '-' }}</a-descriptions-item>
        <a-descriptions-item :label="labels.machine_no">{{ record.machine_no || '-' }}</a-descriptions-item>
        <a-descriptions-item :label="labels.score">
          <span style="color: #f5222d; font-weight: bold; font-size: 16px;">{{ record.score || '0' }}</span>
        </a-descriptions-item>
        <a-descriptions-item :label="labels.status">
          <a-tag :color="getStatusColor(record.status)">{{ getStatusName(record.status) }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item :label="labels.ticket_type">
          <a-tag color="blue">{{ getTicketTypeName(record.ticket_type) }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item :label="labels.qr_code_no">
          <a-tag>{{ record.qr_code_no || '-' }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item :label="labels.created_at" :span="2">{{ record.created_at || '-' }}</a-descriptions-item>
      </a-descriptions>

      <div style="margin-top: 16px;">
        <a-button
          type="primary"
          size="large"
          block
          :disabled="!canRedeem"
          :loading="redeeming"
          @click="redeemRecord"
        >
          <template #icon><check-circle-outlined /></template>
          {{ canRedeem ? labels.redeem_confirm : getRedeemDisabledReason() }}
        </a-button>
      </div>
    </a-card>

    <!-- 提示信息 -->
    <a-alert
      v-if="message"
      :type="messageType"
      :message="message"
      :description="messageDesc"
      show-icon
      closable
      @close="message = ''"
      style="margin-top: 16px;"
    />
  </div>
</template>

<script>
export default {
  props: {
    query_url: String,
    redeem_url: String,
    deposit_redeem_url: String,
    deposit_notify_url: String,
    labels: Object,
  },
  data() {
    return {
      qrCodeNo: '',
      rawGv1Code: '',       // GV1 原文（不顯示在輸入框）
      gv1Info: null,        // { uid, amount, exp }
      gv1ExpirySeconds: 0,
      serverTimeOffset: 0,   // server_time - local_time（秒），用于修正客户端时钟偏差
      expiryTimer: null,
      isGv1Locked: false,
      playerInfo: null,     // 從後端取回的玩家資訊
      notifying: false,     // 正在請求玩家資訊

      record: null,
      loading: false,
      redeeming: false,
      inputLocked: false,
      inputTimer: null,
      lastScannedValue: '',
      message: '',
      messageDesc: '',
      messageType: 'info',
    };
  },
  computed: {
    inputType() {
      return this.qrCodeNo.startsWith('GV') ? 'password' : 'text';
    },
    canRedeem() {
      if (!this.record) return false;
      // 全票種可核銷，僅保留狀態校驗（需為「正常」）
      return this.record.status === 1;
    },
    expiryColor() {
      if (this.gv1ExpirySeconds <= 0) return 'red';
      if (this.gv1ExpirySeconds < 15) return 'orange';
      return 'green';
    },
    expiryText() {
      if (this.gv1ExpirySeconds <= 0) return this.labels.deposit_expired || '已過期';
      const tpl = this.labels.deposit_expires_in_seconds || '剩餘 {n} 秒';
      return tpl.replace('{n}', this.gv1ExpirySeconds);
    },
  },
  mounted() {
    this.$nextTick(() => {
      if (this.$refs.qrInput) this.$refs.qrInput.focus();
    });
  },
  beforeUnmount() {
    if (this.expiryTimer) clearInterval(this.expiryTimer);
    if (this.inputTimer)  clearTimeout(this.inputTimer);
  },
  methods: {
    // ===== GV1 工具 =====
    // 归一化扫码输入：中文/全角输入法会把半角 . 打成 。．・･ 等，全角模式还会把字母数字打成全角
    normalizeScanCode(raw) {
      if (!raw) return '';
      return raw
        .replace(/[。．・･｡]/g, '.') // 中文/日文句点、中点 → .
        .replace(/[！-～]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0)) // 全角英数 → 半角
        .replace(/　/g, ' ') // 全角空格 → 半角
        .trim();
    },

    tryParseGv1(raw) {
      const parts = raw.split('.');
      if (parts.length !== 6 || parts[0] !== 'GV1') return null;
      const [, uid, amountStr, expStr, nonce, sig] = parts;
      const amount = parseInt(amountStr, 10);
      const exp    = parseInt(expStr, 10);
      if (!uid || isNaN(amount) || amount < 1000 || isNaN(exp)
          || !/^[0-9A-F]{8}$/.test(nonce) || !/^[0-9A-F]{20}$/.test(sig)) return null;
      return { uid, amount, exp };
    },

    startExpiryCountdown(exp) {
      if (this.expiryTimer) clearInterval(this.expiryTimer);
      const tick = () => {
        const serverNow = Math.floor(Date.now() / 1000) + this.serverTimeOffset;
        this.gv1ExpirySeconds = exp - serverNow;
        if (this.gv1ExpirySeconds <= 0) clearInterval(this.expiryTimer);
      };
      tick();
      this.expiryTimer = setInterval(tick, 1000);
    },

    // ===== 掃碼輸入 =====
    onScanInput() {
      if (this.inputTimer) clearTimeout(this.inputTimer);
      this.inputTimer = setTimeout(() => {
        this.inputTimer = null;
        const raw = this.normalizeScanCode(this.qrCodeNo);
        if (!raw) return;

        // 嘗試去除掃碼器重複輸入
        const value = this.extractFirstOccurrence(raw);

        // 偵測 GV1 格式
        const gv1 = this.tryParseGv1(value);
        if (gv1) {
          this.rawGv1Code = value;
          this.qrCodeNo   = '';       // 清空輸入框，不顯示原文
          this.gv1Info    = gv1;
          this.record     = null;
          this.inputLocked = true;
          this.isGv1Locked = true;
          this.message    = '';
          this.startExpiryCountdown(gv1.exp);
          // 通知伺服器並取回玩家資訊
          if (this.deposit_notify_url) {
            this.notifying = true;
            this.$request({ url: this.deposit_notify_url, method: 'post', data: { code: value } })
              .then(res => {
                if (res && res.code === 0 && res.data) {
                  this.playerInfo = res.data;
                  // 用服务端时间校正本地时钟偏差，修正倒计时
                  if (res.data.server_time) {
                    this.serverTimeOffset = res.data.server_time - Math.floor(Date.now() / 1000);
                  }
                }
              })
              .catch(() => {})
              .finally(() => { this.notifying = false; });
          }
          return;
        }

        // 普通票碼
        if (value === this.lastScannedValue) {
          this.qrCodeNo = '';
          return;
        }
        this.qrCodeNo = value;
        this.queryRecord();
      }, 500);
    },

    extractFirstOccurrence(str) {
      if (!str || str.length < 50) return str;
      for (let len = 50; len <= Math.floor(str.length / 2); len++) {
        const prefix = str.substring(0, len);
        let isRepetition = true;
        for (let i = len; i < str.length; i++) {
          if (str[i] !== prefix[i % len]) { isRepetition = false; break; }
        }
        if (isRepetition) return prefix;
      }
      return str;
    },

    resetScan() {
      this.qrCodeNo        = '';
      this.rawGv1Code      = '';
      this.gv1Info         = null;
      this.playerInfo      = null;
      this.notifying       = false;
      this.record          = null;
      this.inputLocked     = false;
      this.isGv1Locked     = false;
      this.lastScannedValue = '';
      this.message         = '';
      this.messageDesc     = '';
      this.serverTimeOffset = 0;
      if (this.expiryTimer) { clearInterval(this.expiryTimer); this.expiryTimer = null; }
      this.$nextTick(() => {
        if (this.$refs.qrInput) this.$refs.qrInput.focus();
      });
    },

    copyText(text) {
      if (!text) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
          this.$message.success('已複製：' + text);
        }).catch(() => {
          this.fallbackCopy(text);
        });
      } else {
        this.fallbackCopy(text);
      }
    },

    fallbackCopy(text) {
      const el = document.createElement('textarea');
      el.value = text;
      el.style.position = 'fixed';
      el.style.opacity = '0';
      document.body.appendChild(el);
      el.select();
      try {
        document.execCommand('copy');
        this.$message.success('已複製：' + text);
      } catch (e) {
        this.$message.error('複製失敗，請手動複製');
      }
      document.body.removeChild(el);
    },

    closeModalAndRefresh() {
      this.$emit('success');
      this.$emit('gridRefresh');

      try {
        // 方案A：ExAdmin 可能直接在 instance 上注入 grid
        if (this.grid && this.grid.exposed && typeof this.grid.exposed.refresh === 'function') {
          this.grid.exposed.refresh();
        } else {
          // 方案B：向上遍歷 $parent，僅找有 selectCheckboxColumn（grid 特徵）的組件
          let p = this.$parent;
          while (p) {
            const exp = p.$ && p.$.exposed;
            if (exp && typeof exp.refresh === 'function' && typeof exp.selectIds !== 'undefined') {
              exp.refresh();
              break;
            }
            p = p.$parent;
          }
        }
      } catch (e) {
        // grid 刷新失敗不影響彈窗關閉
      }

      const el = this.$el;
      const modalWrap = el.closest ? el.closest('.ant-modal-wrap') : null;
      const closeBtn  = modalWrap ? modalWrap.querySelector('.ant-modal-close') : null;
      if (closeBtn) closeBtn.click();
    },

    // ===== 普通票查詢 =====
    async queryRecord() {
      this.qrCodeNo = this.normalizeScanCode(this.qrCodeNo);
      if (!this.qrCodeNo) {
        this.message = '请输入二维码编号';
        this.messageType = 'warning';
        this.messageDesc = '';
        return;
      }
      this.loading  = true;
      this.record   = null;
      this.gv1Info  = null;
      this.message  = '';
      this.messageDesc = '';

      try {
        const res = await this.$request({
          url: this.query_url,
          method: 'get',
          params: { qr_code_no: this.qrCodeNo },
        });

        if (res.code === 0) {
          this.record = res.data;
          this.inputLocked = true;
          this.lastScannedValue = this.qrCodeNo;
          this.message = '查询成功';
          this.messageType = 'success';
          this.messageDesc = this.canRedeem ? '请确认订单信息后点击核销' : this.getRedeemDisabledReason();
        } else {
          this.message = res.msg || '查询失败';
          this.messageType = 'error';
          this.messageDesc = '';
        }
      } catch (e) {
        this.message = '查询失败';
        this.messageType = 'error';
        this.messageDesc = e.message || '';
      } finally {
        this.loading = false;
      }
    },

    // ===== 普通票核銷 =====
    async redeemRecord() {
      if (!this.record || !this.canRedeem) return;
      this.redeeming = true;
      this.message   = '';
      this.messageDesc = '';

      try {
        const res = await this.$request({
          url: this.redeem_url,
          method: 'post',
          data: { id: this.record.id },
        });

        if (res.code === 0) {
          this.$message.success('核销成功');
          this.closeModalAndRefresh();
        } else {
          this.message = res.msg || '核销失败';
          this.messageType = 'error';
        }
      } catch (e) {
        this.message = '核销失败';
        this.messageType = 'error';
        this.messageDesc = e.message || '';
      } finally {
        this.redeeming = false;
      }
    },

    // ===== GV1 寄存核銷 =====
    async redeemDepositCode() {
      if (!this.rawGv1Code || this.gv1ExpirySeconds <= 0) return;
      this.redeeming = true;
      this.message   = '';
      this.messageDesc = '';

      try {
        const res = await this.$request({
          url: this.deposit_redeem_url,
          method: 'post',
          data: { code: this.rawGv1Code },
        });

        if (res.code === 0) {
          const label = this.labels.deposit_success || '寄存成功';
          this.$message.success(label);
          this.closeModalAndRefresh();
        } else {
          this.message = res.msg || this.labels.deposit_failed || '核销失败';
          this.messageType = 'error';
        }
      } catch (e) {
        this.message = this.labels.deposit_failed || '核销失败';
        this.messageType = 'error';
        this.messageDesc = e.message || '';
      } finally {
        this.redeeming = false;
      }
    },

    // ===== 普通票工具 =====
    getStatusColor(status) {
      const colors = { 0: 'default', 1: 'blue', 2: 'orange', 3: 'purple', 5: 'red', 6: 'cyan', 7: 'geekblue' };
      return colors[status] || 'default';
    },
    getStatusName(status) {
      const names = { 0: '禁用', 1: '正常', 2: '后台使用', 3: '机台使用', 5: '打印失败', 6: '已拆分', 7: '已合并' };
      return names[status] || '未知';
    },
    getTicketTypeName(type) {
      const names = { 1: '开分', 2: '洗分', 3: '体验卷', 4: '福利卷' };
      return names[type] || '未知';
    },
    getRedeemDisabledReason() {
      if (!this.record) return '无法核销';
      if (this.record.status === 0) return '该记录已禁用';
      if (this.record.status === 2 || this.record.status === 3) return '该记录已核销';
      return '无法核销';
    },
  },
};
</script>
