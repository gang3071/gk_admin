<template>
  <div style="padding: 16px;">
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

    <!-- 加载状态 -->
    <div v-if="loading" style="text-align: center; padding: 40px 0;">
      <a-spin size="large" />
    </div>

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
    redeem_url: String,
    record_data: {
      type: Object,
      default: null,
    },
    labels: Object,
  },
  data() {
    return {
      record: null,
      loading: false,
      redeeming: false,
      message: '',
      messageDesc: '',
      messageType: 'info',
    };
  },
  computed: {
    canRedeem() {
      if (!this.record) return false;
      return this.record.ticket_type === 1 && this.record.status === 1;
    }
  },
  mounted() {
    if (this.record_data) {
      this.record = this.record_data;
    }
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

    // 关闭弹窗并刷新页面
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
