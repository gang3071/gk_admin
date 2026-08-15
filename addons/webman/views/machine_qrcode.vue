<template>
  <div class="qrcode-modal-wrapper">
    <div class="qrcode-modal-header">
      <h3 class="qrcode-modal-title">{{ title }}</h3>
    </div>

    <div class="qrcode-modal-info">
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

    <div class="qrcode-modal-content">
      <canvas ref="qrcodeCanvas" width="300" height="300"></canvas>
    </div>

    <div class="qrcode-modal-footer">
      <a-alert
        message="扫码说明"
        description="玩家使用手机APP扫描此二维码，即可快速查看该机台信息"
        type="info"
        show-icon
      />

      <div class="qrcode-button-group">
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
  data() {
    return {
      scriptLoaded: false
    };
  },
  mounted() {
    this.$nextTick(() => {
      this.loadAndGenerateQrCode();
    });
  },
  beforeUnmount() {
    // 清空画布
    if (this.$refs.qrcodeCanvas) {
      const ctx = this.$refs.qrcodeCanvas.getContext('2d');
      if (ctx) {
        ctx.clearRect(0, 0, 300, 300);
      }
    }
  },
  methods: {
    async loadAndGenerateQrCode() {
      try {
        // 如果库已加载，直接生成
        if (window.QRCode) {
          this.generateQrCode();
          return;
        }

        // 检查是否正在加载
        if (window.__qrcodeLoading) {
          // 等待加载完成
          const checkInterval = setInterval(() => {
            if (window.QRCode) {
              clearInterval(checkInterval);
              this.generateQrCode();
            }
          }, 100);
          return;
        }

        // 标记正在加载
        window.__qrcodeLoading = true;

        // 动态加载库
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcodejs2@0.0.2/qrcode.min.js';

        script.onload = () => {
          window.__qrcodeLoading = false;
          this.scriptLoaded = true;
          this.generateQrCode();
        };

        script.onerror = () => {
          window.__qrcodeLoading = false;
          console.error('Failed to load QRCode library');
          if (this.$message) {
            this.$message.error('二维码库加载失败');
          }
        };

        document.head.appendChild(script);
      } catch (error) {
        console.error('Error loading QRCode library:', error);
      }
    },

    generateQrCode() {
      if (!window.QRCode || !this.$refs.qrcodeCanvas) {
        return;
      }

      try {
        // 创建临时 div 用于生成二维码
        const tempDiv = document.createElement('div');
        tempDiv.style.position = 'absolute';
        tempDiv.style.left = '-9999px';
        tempDiv.style.top = '-9999px';
        document.body.appendChild(tempDiv);

        // 生成二维码
        const qr = new window.QRCode(tempDiv, {
          text: String(this.machineId),
          width: 300,
          height: 300,
          colorDark: '#000000',
          colorLight: '#ffffff',
          correctLevel: window.QRCode.CorrectLevel.H
        });

        // 等待生成完成后复制到 canvas
        setTimeout(() => {
          const qrCanvas = tempDiv.querySelector('canvas');
          if (qrCanvas && this.$refs.qrcodeCanvas) {
            const ctx = this.$refs.qrcodeCanvas.getContext('2d');
            ctx.clearRect(0, 0, 300, 300);
            ctx.drawImage(qrCanvas, 0, 0);
          }
          // 移除临时元素
          document.body.removeChild(tempDiv);
        }, 100);

      } catch (error) {
        console.error('Failed to generate QR code:', error);
        if (this.$message) {
          this.$message.error('二维码生成失败');
        }
      }
    },

    downloadQrCode() {
      if (!this.$refs.qrcodeCanvas) return;

      try {
        const canvas = this.$refs.qrcodeCanvas;
        const url = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.download = `machine_${this.machineCode}_qrcode.png`;
        link.href = url;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      } catch (error) {
        console.error('Failed to download QR code:', error);
        if (this.$message) {
          this.$message.error('下载失败');
        }
      }
    },

    printQrCode() {
      if (!this.$refs.qrcodeCanvas) return;

      try {
        const canvas = this.$refs.qrcodeCanvas;
        const dataUrl = canvas.toDataURL('image/png');

        const printWindow = window.open('', '_blank');
        if (!printWindow) {
          if (this.$message) {
            this.$message.warning('请允许弹出窗口');
          }
          return;
        }

        const machineCode = this.machineCode;
        const machineName = this.machineName;
        const machineId = this.machineId;

        printWindow.document.write(`
          <!DOCTYPE html>
          <html>
            <head>
              <meta charset="UTF-8">
              <title>机台二维码 - ${machineCode}</title>
              <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                  display: flex;
                  flex-direction: column;
                  align-items: center;
                  justify-content: center;
                  padding: 20px;
                  font-family: Arial, sans-serif;
                }
                h2 { margin-bottom: 20px; }
                img { max-width: 400px; margin: 20px 0; }
                .info { text-align: center; margin: 10px 0; }
                .label { font-weight: bold; color: #333; }
                @media print {
                  body { padding: 0; }
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
              <img src="${dataUrl}" alt="机台二维码" />
              <div class="info">
                <small>扫描此二维码查看机台信息</small>
              </div>
              <script>
                window.onload = function() {
                  setTimeout(function() {
                    window.print();
                  }, 500);
                };
              </script>
            </body>
          </html>
        `);
        printWindow.document.close();
      } catch (error) {
        console.error('Failed to print QR code:', error);
        if (this.$message) {
          this.$message.error('打印失败');
        }
      }
    }
  }
};
</script>

<style scoped>
.qrcode-modal-wrapper {
  padding: 24px;
  max-width: 600px;
  margin: 0 auto;
}

.qrcode-modal-header {
  text-align: center;
  margin-bottom: 24px;
}

.qrcode-modal-title {
  font-size: 20px;
  font-weight: 600;
  color: #1890ff;
  margin: 0;
}

.qrcode-modal-info {
  margin-bottom: 24px;
}

.qrcode-modal-content {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 32px;
  background: #f5f5f5;
  border-radius: 8px;
  margin-bottom: 24px;
}

.qrcode-modal-content canvas {
  display: block;
  border: 1px solid #e0e0e0;
  background: white;
}

.qrcode-modal-footer {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.qrcode-button-group {
  display: flex;
  justify-content: center;
  gap: 12px;
}

.qrcode-button-group .ant-btn {
  min-width: 140px;
}
</style>
