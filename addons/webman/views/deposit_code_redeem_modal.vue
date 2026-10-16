<template>
  <div style="padding: 16px;">
    <!-- 掃碼輸入區域 -->
    <a-card size="small" style="margin-bottom: 16px;">
      <template #title>
        <qrcode-outlined style="margin-right: 8px;" />
        {{ labels.title }}
      </template>
      <a-row :gutter="16">
        <a-col :span="16">
          <a-input
            v-model:value="qrCode"
            :placeholder="labels.placeholder"
            size="large"
            ref="qrInput"
            :disabled="done"
            @input="onScanInput"
            allow-clear
          >
            <template #prefix>
              <qrcode-outlined style="color: #bfbfbf;" />
            </template>
          </a-input>
        </a-col>
        <a-col :span="8">
          <a-button
            v-if="!done"
            type="primary"
            size="large"
            block
            :loading="loading"
            :disabled="!qrCode"
            @click="submit"
          >
            <template #icon><check-circle-outlined /></template>
            {{ labels.confirm }}
          </a-button>
          <a-button
            v-else
            size="large"
            block
            @click="reset"
          >
            <template #icon><reload-outlined /></template>
            {{ labels.scan_again }}
          </a-button>
        </a-col>
      </a-row>

      <!-- 解析預覽（客戶端解析，不需要請求伺服器） -->
      <div v-if="parsed && !done" style="margin-top: 12px;">
        <a-descriptions :column="2" size="small" bordered>
          <a-descriptions-item :label="labels.uid">
            <a-tag color="blue">{{ parsed.uid }}</a-tag>
          </a-descriptions-item>
          <a-descriptions-item :label="labels.amount">
            <span style="color: #f5222d; font-weight: bold; font-size: 16px;">{{ parsed.amount }}</span>
          </a-descriptions-item>
          <a-descriptions-item :label="labels.expires_in" :span="2">
            <a-tag :color="expiryColor">{{ expiryText }}</a-tag>
          </a-descriptions-item>
        </a-descriptions>
      </div>
    </a-card>

    <!-- 成功結果 -->
    <a-result
      v-if="done && result"
      status="success"
      :title="labels.success_title"
    >
      <template #extra>
        <a-descriptions :column="2" size="small" bordered style="text-align:left;">
          <a-descriptions-item :label="labels.order_id" :span="2">
            <a-tag color="green">{{ result.depositId }}</a-tag>
          </a-descriptions-item>
          <a-descriptions-item :label="labels.uid">{{ result.uid }}</a-descriptions-item>
          <a-descriptions-item :label="labels.amount">
            <span style="color: #f5222d; font-weight: bold;">{{ result.amount }}</span>
          </a-descriptions-item>
          <a-descriptions-item :label="labels.balance_after" :span="2">{{ result.balance }}</a-descriptions-item>
        </a-descriptions>
      </template>
    </a-result>

    <!-- 錯誤提示 -->
    <a-alert
      v-if="error"
      type="error"
      :message="error"
      show-icon
      closable
      @close="error = ''"
      style="margin-top: 12px;"
    />
  </div>
</template>

<script>
export default {
  props: {
    redeem_url: String,
    labels: Object,
  },
  data() {
    return {
      qrCode: '',
      scanBuf: '',          // 扫码枪原始缓冲（绕过输入法，唯一真相）
      parsed: null,
      loading: false,
      done: false,
      result: null,
      error: '',
      inputTimer: null,
      expiryTimer: null,
      expirySeconds: 0,
    };
  },
  computed: {
    expiryColor() {
      if (this.expirySeconds <= 0) return 'red';
      if (this.expirySeconds < 15) return 'orange';
      return 'green';
    },
    expiryText() {
      if (this.expirySeconds <= 0) return this.labels.expired;
      return this.labels.expires_in_seconds.replace('{n}', this.expirySeconds);
    },
  },
  mounted() {
    this.$nextTick(() => {
      this.bindScanKeyHandler();
      if (this.$refs.qrInput) this.$refs.qrInput.focus();
    });
  },
  beforeUnmount() {
    this.unbindScanKeyHandler();
    this.clearTimers();
  },
  methods: {
    // 归一化扫码输入：中文/全角输入法会把半角 . 打成 。．・･ 等，全角模式还会把字母数字打成全角
    normalizeScanCode(raw) {
      if (!raw) return '';
      return raw
        .replace(/[。．・･｡]/g, '.') // 中文/日文句点、中点 → .
        .replace(/[！-～]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0)) // 全角英数 → 半角
        .replace(/　/g, ' ') // 全角空格 → 半角
        .trim();
    },

    // ===== 绕过输入法的按键捕获 =====
    // 键盘扫码枪是按键模拟输入，会经过当前输入法。中文输入法会把 . 转成 、
    // 组合态下数字当候选词被吞掉（GV1 → GV、签名丢位）。归一化救不回被吞的字符，
    // 必须让 IME 根本看不到这些按键，并用独立的 scanBuf 作为唯一真相。
    getScanInputEl() {
      const comp = this.$refs.qrInput;
      if (!comp) return null;
      if (comp instanceof HTMLElement) return comp;
      const el = comp.$el || null;
      if (!el) return null;
      if (el.tagName === 'INPUT') return el;
      return (el.querySelector && el.querySelector('input')) || null;
    },

    bindScanKeyHandler() {
      const el = this.getScanInputEl();
      if (!el) return;
      if (el._scanKeyBound) return;
      el.addEventListener('keydown', this.onScanKeyDown, true);
      el.addEventListener('beforeinput', this.onScanBeforeInput, true);
      el.addEventListener('compositionstart', this.onScanCompositionStart, true);
      el.addEventListener('paste', this.onScanPaste);
      el.addEventListener('focus', this.bindScanKeyHandler);
      el._scanKeyBound = true;
    },

    unbindScanKeyHandler() {
      const el = this.getScanInputEl();
      if (!el || !el._scanKeyBound) return;
      el.removeEventListener('keydown', this.onScanKeyDown, true);
      el.removeEventListener('beforeinput', this.onScanBeforeInput, true);
      el.removeEventListener('compositionstart', this.onScanCompositionStart, true);
      el.removeEventListener('paste', this.onScanPaste);
      el.removeEventListener('focus', this.bindScanKeyHandler);
      el._scanKeyBound = false;
    },

    scanCodeToChar(code) {
      if (!code) return null;
      let m;
      if ((m = /^Digit(\d)$/.exec(code)))  return m[1];
      if ((m = /^Numpad(\d)$/.exec(code))) return m[1];
      if ((m = /^Key([A-Z])$/.exec(code))) return m[1];
      if (code === 'Period' || code === 'NumpadDecimal') return '.';
      return null;
    },

    // 输入法一开始就掐掉：不允许在扫码框里组中文
    onScanCompositionStart(e) {
      e.preventDefault();
      e.stopImmediatePropagation();
    },

    // 组合文本（输入法候选上屏）一律拒绝写入
    onScanBeforeInput(e) {
      if (e.inputType && e.inputType.indexOf('insertComposition') === 0) {
        e.preventDefault();
      }
    },

    // 粘贴是唯一不走 keydown 的入口，走归一化兜底
    onScanPaste(e) {
      const text = (e.clipboardData && e.clipboardData.getData('text')) || '';
      if (!text) return;
      e.preventDefault();
      this.scanBuf = this.normalizeScanCode(this.scanBuf + this.normalizeScanCode(text));
      this.qrCode = this.scanBuf;
      this.onScanInput();
    },

    onScanKeyDown(e) {
      // Ctrl/Cmd+V 粘贴等组合键放行
      if (e.ctrlKey || e.metaKey || e.altKey) return;

      if (e.key === 'Enter') {
        e.preventDefault();
        this.flushScanInput();
        return;
      }
      if (e.key === 'Backspace') {
        e.preventDefault();
        this.scanBuf = this.scanBuf.slice(0, -1);
        this.qrCode = this.scanBuf;
        return;
      }

      // 被输入法劫持的按键一律用物理键码还原
      let ch = null;
      if (e.isComposing || e.keyCode === 229 || e.key === 'Process' || e.key === 'Unidentified') {
        ch = this.scanCodeToChar(e.code);
      } else if (e.key && e.key.length === 1 && /^[\x20-\x7E]$/.test(e.key)) {
        ch = e.key;
      } else {
        ch = this.scanCodeToChar(e.code);
      }
      if (ch === null) return;

      e.preventDefault();
      e.stopImmediatePropagation();
      this.scanBuf += ch;
      this.qrCode = this.scanBuf;
      this.onScanInput();
    },

    clearTimers() {
      if (this.inputTimer) clearTimeout(this.inputTimer);
      if (this.expiryTimer) clearInterval(this.expiryTimer);
    },

    onScanInput() {
      if (this.inputTimer) clearTimeout(this.inputTimer);
      this.inputTimer = setTimeout(() => {
        this.tryParse();
      }, 400);
    },

    // Enter 提前触发，不等防抖
    flushScanInput() {
      if (this.inputTimer) {
        clearTimeout(this.inputTimer);
        this.inputTimer = null;
      }
      this.tryParse();
    },

    tryParse() {
      this.parsed = null;
      this.error = '';
      if (this.expiryTimer) clearInterval(this.expiryTimer);

      // scanBuf 是按键捕获的原始内容；为空时回退到输入框（兼容自动填充等）
      const source = this.scanBuf || this.qrCode || '';
      const raw = this.normalizeScanCode(source);
      if (!raw) return;
      this.scanBuf = raw;
      this.qrCode  = raw;

      const parts = raw.split('.');
      if (parts.length !== 6 || parts[0] !== 'GV1') {
        return; // 格式不符合就靜默，不顯示錯誤（掃到一半時不報錯）
      }

      const uid    = parts[1];
      const amount = parseInt(parts[2], 10);
      const exp    = parseInt(parts[3], 10);
      const nonce  = parts[4];
      const sig    = parts[5];

      if (!uid || isNaN(amount) || amount < 1000 || isNaN(exp)
          || !/^[0-9A-F]{8}$/.test(nonce) || !/^[0-9A-F]{20}$/.test(sig)) {
        return;
      }

      this.parsed = { uid, amount, exp };
      this.startExpiryCountdown(exp);
    },

    startExpiryCountdown(exp) {
      const update = () => {
        this.expirySeconds = exp - Math.floor(Date.now() / 1000);
        if (this.expirySeconds <= 0) {
          clearInterval(this.expiryTimer);
        }
      };
      update();
      this.expiryTimer = setInterval(update, 1000);
    },

    async submit() {
      const raw = this.normalizeScanCode(this.scanBuf || this.qrCode || '');
      if (!raw) return;

      this.error = '';
      this.loading = true;

      try {
        const res = await this.$request({
          url: this.redeem_url,
          method: 'post',
          data: { code: raw },
        });

        if (res.code === 0) {
          this.done = true;
          this.result = res.data;
          this.clearTimers();
          this.$message.success(this.labels.success_title);
          // 延迟刷新列表
          setTimeout(() => this.$emit('success'), 1000);
        } else {
          this.error = res.msg || this.labels.failed;
        }
      } catch (e) {
        this.error = e.message || this.labels.failed;
      } finally {
        this.loading = false;
      }
    },

    reset() {
      this.qrCode  = '';
      this.parsed  = null;
      this.done    = false;
      this.result  = null;
      this.error   = '';
      this.clearTimers();
      this.$nextTick(() => {
        if (this.$refs.qrInput) this.$refs.qrInput.focus();
      });
    },
  },
};
</script>
