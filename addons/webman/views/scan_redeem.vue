<template>
  <div style="padding: 16px;">
    <!-- 扫码输入区域 -->
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
            @pressEnter="queryRecord"
          >
            <template #prefix>
              <qrcode-outlined style="color: #bfbfbf;" />
            </template>
          </a-input>
        </a-col>
        <a-col :span="8">
          <a-button
            type="primary"
            size="large"
            block
            :loading="loading"
            @click="queryRecord"
          >
            <template #icon><search-outlined /></template>
            查找
          </a-button>
        </a-col>
      </a-row>
    </a-card>

    <!-- 用户信息 -->
    <a-card v-if="record" size="small" title="用户信息" style="margin-bottom: 16px;" :headStyle="{borderBottom: '2px solid #722ed1'}">
      <a-descriptions :column="2" size="small" bordered>
        <a-descriptions-item :label="labels.player_name" :span="2">
          <div style="display: flex; align-items: center;">
            <template v-if="record.player_id">
              <a-avatar v-if="record.player_avatar" :src="record.player_avatar" size="small" style="margin-right: 8px;" />
              <a-avatar v-else size="small" style="margin-right: 8px; background-color: #722ed1;">
                {{ (record.player_name || 'U').charAt(0) }}
              </a-avatar>
              <span style="font-weight: 500;">{{ record.player_name || '未命名' }}</span>
            </template>
            <template v-else>
              <a-avatar size="small" style="margin-right: 8px; background-color: #d9d9d9;">?</a-avatar>
              <span style="color: #999;">未绑定玩家</span>
            </template>
          </div>
        </a-descriptions-item>
        <a-descriptions-item :label="labels.player_id">
          <span v-if="record.player_uuid">{{ record.player_uuid }}</span>
          <span v-else style="color: #999;">-</span>
        </a-descriptions-item>
      </a-descriptions>
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
          <a-tag :color="getStatusColor(record.status)">{{ record.status_name || '-' }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item :label="labels.ticket_type">
          <a-tag :color="record.ticket_type === 1 ? 'blue' : 'green'">{{ record.ticket_type_name || '-' }}</a-tag>
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
          {{ canRedeem ? '确认核销' : getRedeemDisabledReason() }}
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
    record_id: {
      type: [Number, String],
      default: 0,
    },
    labels: Object,
  },
  data() {
    return {
      qrCodeNo: '',
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
      // 只有洗分票且状态为正常或已打印时可以核销
      return this.record.ticket_type === 2 &&
             [1, 2].includes(this.record.status);
    }
  },
  mounted() {
    // 如果有 record_id，自动加载记录
    if (this.record_id && this.record_id > 0) {
      this.loadRecordById(this.record_id);
    } else {
      this.$nextTick(() => {
        if (this.$refs.qrInput) {
          this.$refs.qrInput.focus();
        }
      });
    }
  },
  methods: {
    getStatusColor(status) {
      const colors = {
        0: 'default',  // 禁用
        1: 'blue',     // 正常
        2: 'green',    // 已打印
        3: 'orange',   // 已使用
      };
      return colors[status] || 'default';
    },

    closeModal() {
      // 向上查找最近的弹窗容器，点击其关闭按钮
      const el = this.$el;
      const modalWrap = el.closest ? el.closest('.ant-modal-wrap') : null;
      if (modalWrap) {
        const closeBtn = modalWrap.querySelector('.ant-modal-close');
        if (closeBtn) {
          closeBtn.click();
          return;
        }
      }
      // 备用方案：查找当前页面中所有可见弹窗
      const modals = document.querySelectorAll('.ant-modal-wrap');
      for (const modal of modals) {
        if (modal.style.display !== 'none') {
          const closeBtn = modal.querySelector('.ant-modal-close');
          if (closeBtn) {
            closeBtn.click();
            return;
          }
        }
      }
    },

    getRedeemDisabledReason() {
      if (!this.record) return '无法核销';
      if (this.record.ticket_type !== 2) return '只有洗分票可以核销';
      if (this.record.status === 0) return '该记录已禁用';
      if (this.record.status === 3) return '该记录已核销';
      return '无法核销';
    },

    async loadRecordById(id) {
      this.loading = true;
      this.record = null;
      this.message = '';
      this.messageDesc = '';

      try {
        const res = await this.$request({
          url: this.query_url,
          method: 'get',
          params: { id: id },
        });

        if (res.code === 0) {
          this.record = res.data;
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

    async queryRecord() {
      if (!this.qrCodeNo) {
        this.message = '请输入二维码编号';
        this.messageType = 'warning';
        this.messageDesc = '';
        return;
      }

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
      if (!this.record || !this.canRedeem) {
        return;
      }

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
          // 关闭弹窗
          this.$nextTick(() => {
            this.closeModal();
          });
        } else {
          this.message = res.msg || '核销失败';
          this.messageType = 'error';
          this.messageDesc = '';
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
