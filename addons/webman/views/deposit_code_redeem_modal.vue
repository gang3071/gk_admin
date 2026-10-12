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
      if (this.$refs.qrInput) this.$refs.qrInput.focus();
    });
  },
  beforeUnmount() {
    this.clearTimers();
  },
  methods: {
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

    tryParse() {
      this.parsed = null;
      this.error = '';
      if (this.expiryTimer) clearInterval(this.expiryTimer);

      const raw = (this.qrCode || '').trim();
      if (!raw) return;

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
      const raw = (this.qrCode || '').trim();
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
