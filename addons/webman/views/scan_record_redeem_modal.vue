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
            placeholder="请输入或扫描二维码编号"
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

    <!-- 订单信息 -->
    <a-card v-if="record" size="small" title="订单信息" style="margin-bottom: 16px;" :headStyle="{borderBottom: '2px solid #1890ff'}">
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
    labels: Object,
  },
  data() {
    return {
      qrCodeNo: '',
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
    canRedeem() {
      if (!this.record) return false;
      // 只有开分票且状态为正常时可以核销
      return this.record.ticket_type === 1 && this.record.status === 1;
    }
  },
  mounted() {
    this.$nextTick(() => {
      if (this.$refs.qrInput) {
        this.$refs.qrInput.focus();
      }
    });
  },
  methods: {
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
      if (this.record.ticket_type !== 1) return '只有开分票可以核销';
      if (this.record.status === 0) return '该记录已禁用';
      if (this.record.status === 2 || this.record.status === 3) return '该记录已核销';
      return '无法核销';
    },

    onScanInput() {
      if (this.inputTimer) {
        clearTimeout(this.inputTimer);
      }
      this.inputTimer = setTimeout(() => {
        this.inputTimer = null;
        const raw = this.qrCodeNo.trim();
        if (!raw) return;
        const value = this.extractFirstOccurrence(raw);
        this.qrCodeNo = value;
        if (value === this.lastScannedValue) {
          this.qrCodeNo = '';
          return;
        }
        this.queryRecord();
      }, 500);
    },

    extractFirstOccurrence(str) {
      if (!str || str.length < 50) return str;
      for (let len = 50; len <= Math.floor(str.length / 2); len++) {
        const prefix = str.substring(0, len);
        let isRepetition = true;
        for (let i = len; i < str.length; i++) {
          if (str[i] !== prefix[i % len]) {
            isRepetition = false;
            break;
          }
        }
        if (isRepetition) return prefix;
      }
      return str;
    },

    resetScan() {
      this.qrCodeNo = '';
      this.record = null;
      this.inputLocked = false;
      this.lastScannedValue = '';
      this.message = '';
      this.messageDesc = '';
      this.$nextTick(() => {
        if (this.$refs.qrInput) {
          this.$refs.qrInput.focus();
        }
      });
    },

    closeModalAndRefresh() {
      this.$emit('success');
      const el = this.$el;
      const modalWrap = el.closest ? el.closest('.ant-modal-wrap') : null;
      const closeBtn = modalWrap ? modalWrap.querySelector('.ant-modal-close') : null;
      if (closeBtn) {
        closeBtn.click();
      }
      // 延迟刷新页面
      setTimeout(() => {
        window.location.reload();
      }, 300);
    },

    async queryRecord() {
      if (!this.qrCodeNo) {
        this.message = '请输入二维码编号';
        this.messageType = 'warning';
        this.messageDesc = '';
        return;
      }

      this.qrCodeNo = this.qrCodeNo.trim();
      this.loading = true;
      this.record = null;
      this.message = '';
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

    async redeemRecord() {
      if (!this.record || !this.canRedeem) return;

      this.redeeming = true;
      this.message = '';
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
  },
};
</script>
