<template>
  <div class="machine-qrcode-container">
    <div class="qrcode-header">
      <h3>{{ title }}</h3>
    </div>

    <div class="qrcode-info">
      <a-descriptions :column="1" bordered size="small">
        <a-descriptions-item label="机台编号">
          <a-tag color="blue">{{ machineCode }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item label="机台名称">
          {{ machineName }}
        </a-descriptions-item>
        <a-descriptions-item label="机台ID">
          <a-tag color="green">{{ machineId }}</a-tag>
        </a-descriptions-item>
      </a-descriptions>
    </div>

    <div class="qrcode-wrapper">
      <div id="qrcode" ref="qrcode"></div>
    </div>

    <div class="qrcode-footer">
      <a-alert
        message="扫码说明"
        description="玩家使用手机APP扫描此二维码，即可快速查看该机台信息"
        type="info"
        show-icon
      />

      <div class="button-group">
        <a-button type="primary" @click="downloadQrCode" size="large">
          <template #icon>
            <download-outlined />
          </template>
          下载二维码
        </a-button>

        <a-button @click="printQrCode" size="large">
          <template #icon>
            <printer-outlined />
          </template>
          打印二维码
        </a-button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MachineQrCode',
  props: {
    machineId: {
      type: [String, Number],
      required: true
    },
    machineCode: {
      type: String,
      required: true
    },
    machineName: {
      type: String,
      required: true
    },
    title: {
      type: String,
      default: '机台二维码'
    }
  },
  mounted() {
    this.loadQrCodeLibrary();
  },
  methods: {
    loadQrCodeLibrary() {
      // 检查是否已加载 QRCode 库
      if (window.QRCode) {
        this.generateQrCode();
        return;
      }

      // 动态加载 qrcodejs2 库
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/qrcodejs2@0.0.2/qrcode.min.js';
      script.onload = () => {
        this.generateQrCode();
      };
      script.onerror = () => {
        console.error('Failed to load QRCode library');
        this.$message.error('二维码库加载失败');
      };
      document.head.appendChild(script);
    },

    generateQrCode() {
      // 清空之前的二维码
      if (this.$refs.qrcode) {
        this.$refs.qrcode.innerHTML = '';
      }

      // 确保 QRCode 库已加载
      if (!window.QRCode) {
        console.error('QRCode library not loaded');
        return;
      }

      // 生成二维码内容：使用机台ID
      const qrContent = String(this.machineId);

      // 创建二维码
      new window.QRCode(this.$refs.qrcode, {
        text: qrContent,
        width: 300,
        height: 300,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: window.QRCode.CorrectLevel.H
      });
    },

    downloadQrCode() {
      const canvas = this.$refs.qrcode.querySelector('canvas');
      if (canvas) {
        const url = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.download = `machine_${this.machineCode}_qrcode.png`;
        link.href = url;
        link.click();
      }
    },

    printQrCode() {
      const canvas = this.$refs.qrcode.querySelector('canvas');
      if (canvas) {
        const printWindow = window.open('', '_blank');
        const img = new Image();
        img.src = canvas.toDataURL('image/png');
        const machineCode = this.machineCode;
        const machineName = this.machineName;
        const machineId = this.machineId;

        img.onload = function() {
          printWindow.document.write(`
            <html>
              <head>
                <title>打印机台二维码 - ${machineCode}</title>
                <style>
                  body {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    font-family: Arial, sans-serif;
                  }
                  img {
                    max-width: 400px;
                    margin: 20px 0;
                  }
                  .info {
                    text-align: center;
                    margin: 10px 0;
                  }
                  .label {
                    font-weight: bold;
                    color: #333;
                  }
                  @media print {
                    body {
                      padding: 0;
                    }
                  }
                </style>
              </head>
              <body>
                <h2>机台二维码</h2>
                <div class="info">
                  <div><span class="label">机台编号：</span>${machineCode}</div>
                  <div><span class="label">机台名称：</span>${machineName}</div>
                  <div><span class="label">机台ID：</span>${machineId}</div>
                </div>
                <img src="${img.src}" />
                <div class="info">
                  <small>扫描此二维码查看机台信息</small>
                </div>
              </body>
            </html>
          `);
          printWindow.document.close();
          printWindow.focus();
          setTimeout(() => {
            printWindow.print();
          }, 250);
        };
      }
    }
  }
};
</script>

<style scoped>
.machine-qrcode-container {
  padding: 24px;
  max-width: 600px;
  margin: 0 auto;
}

.qrcode-header {
  text-align: center;
  margin-bottom: 24px;
}

.qrcode-header h3 {
  font-size: 20px;
  font-weight: 600;
  color: #1890ff;
  margin: 0;
}

.qrcode-info {
  margin-bottom: 24px;
}

.qrcode-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 32px;
  background: #f5f5f5;
  border-radius: 8px;
  margin-bottom: 24px;
}

#qrcode {
  display: inline-block;
}

.qrcode-footer {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.button-group {
  display: flex;
  justify-content: center;
  gap: 12px;
}

.button-group .ant-btn {
  min-width: 140px;
}
</style>
