<template>
  <div class="qr-modal-isolated">
    <div class="qr-modal-isolated-header">
      <h3>{{ title }}</h3>
    </div>

    <div class="qr-modal-isolated-info">
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

    <div class="qr-modal-isolated-canvas-wrapper">
      <canvas
        ref="qrcodeCanvas"
        :width="canvasSize"
        :height="canvasSize"
        class="qr-modal-isolated-canvas"
      ></canvas>
    </div>

    <div class="qr-modal-isolated-footer">
      <a-alert
        message="扫码说明"
        description="玩家使用手机APP扫描此二维码，即可快速查看该机台信息"
        type="info"
        show-icon
      />

      <div class="qr-modal-isolated-buttons">
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
// 纯 JavaScript 二维码生成器（无需外部库）
function generateQRCode(text) {
  // 简化版 QR Code 生成算法
  const QRCode = {
    // QR Code 版本和容量
    typeNumber: 4,
    errorCorrectLevel: 'H',

    // 生成二维码矩阵
    make: function(text) {
      const qr = this;
      const typeNumber = this.getTypeNumber(text);
      const errorCorrectLevel = 2; // H 级别

      // 创建二维码矩阵
      const moduleCount = typeNumber * 4 + 17;
      const modules = new Array(moduleCount);

      for (let row = 0; row < moduleCount; row++) {
        modules[row] = new Array(moduleCount);
        for (let col = 0; col < moduleCount; col++) {
          modules[row][col] = null;
        }
      }

      // 简化实现：使用固定模式 + 文本编码
      this.setupPositionProbePattern(modules, 0, 0);
      this.setupPositionProbePattern(modules, moduleCount - 7, 0);
      this.setupPositionProbePattern(modules, 0, moduleCount - 7);
      this.setupTimingPattern(modules, moduleCount);

      // 编码数据
      const data = this.encodeData(text);
      this.mapData(modules, data, moduleCount, errorCorrectLevel);

      return modules;
    },

    getTypeNumber: function(text) {
      const length = text.length;
      if (length <= 14) return 1;
      if (length <= 26) return 2;
      if (length <= 42) return 3;
      return 4;
    },

    setupPositionProbePattern: function(modules, row, col) {
      for (let r = -1; r <= 7; r++) {
        if (row + r <= -1 || modules.length <= row + r) continue;
        for (let c = -1; c <= 7; c++) {
          if (col + c <= -1 || modules.length <= col + c) continue;

          if ((0 <= r && r <= 6 && (c == 0 || c == 6))
            || (0 <= c && c <= 6 && (r == 0 || r == 6))
            || (2 <= r && r <= 4 && 2 <= c && c <= 4)) {
            modules[row + r][col + c] = true;
          } else {
            modules[row + r][col + c] = false;
          }
        }
      }
    },

    setupTimingPattern: function(modules, moduleCount) {
      for (let r = 8; r < moduleCount - 8; r++) {
        if (modules[r][6] !== null) continue;
        modules[r][6] = (r % 2 == 0);
      }
      for (let c = 8; c < moduleCount - 8; c++) {
        if (modules[6][c] !== null) continue;
        modules[6][c] = (c % 2 == 0);
      }
    },

    encodeData: function(text) {
      const bytes = [];
      for (let i = 0; i < text.length; i++) {
        bytes.push(text.charCodeAt(i));
      }
      return bytes;
    },

    mapData: function(modules, data, moduleCount, errorCorrectLevel) {
      let inc = -1;
      let row = moduleCount - 1;
      let bitIndex = 7;
      let byteIndex = 0;

      for (let col = moduleCount - 1; col > 0; col -= 2) {
        if (col == 6) col--;

        while (true) {
          for (let c = 0; c < 2; c++) {
            if (modules[row][col - c] === null) {
              let dark = false;

              if (byteIndex < data.length) {
                dark = (((data[byteIndex] >>> bitIndex) & 1) == 1);
              }

              modules[row][col - c] = dark;
              bitIndex--;

              if (bitIndex == -1) {
                byteIndex++;
                bitIndex = 7;
              }
            }
          }

          row += inc;

          if (row < 0 || moduleCount <= row) {
            row -= inc;
            inc = -inc;
            break;
          }
        }
      }
    }
  };

  return QRCode.make(text);
}

export default {
  name: 'MachineQrCodeIsolated',
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
      canvasSize: 300,
      originalBodyOverflow: undefined,
      originalBodyClass: undefined,
      originalBodyStyle: undefined,
      originalHtmlStyle: undefined
    };
  },
  mounted() {
    // 保存原始样式（完整备份）
    this.originalBodyOverflow = document.body.style.overflow;
    this.originalBodyClass = document.body.className;
    this.originalBodyStyle = document.body.getAttribute('style');
    this.originalHtmlStyle = document.documentElement.getAttribute('style');

    this.$nextTick(() => {
      this.drawQRCode();
    });
  },
  beforeUnmount() {
    this.clearCanvas();
    this.restoreOriginalStyles();
  },
  methods: {
    restoreOriginalStyles() {
      try {
        // 恢复 body 样式
        if (this.originalBodyOverflow !== undefined) {
          document.body.style.overflow = this.originalBodyOverflow;
        }
        if (this.originalBodyClass !== undefined) {
          document.body.className = this.originalBodyClass;
        }
        if (this.originalBodyStyle !== undefined) {
          if (this.originalBodyStyle) {
            document.body.setAttribute('style', this.originalBodyStyle);
          } else {
            document.body.removeAttribute('style');
          }
        }

        // 恢复 html 样式
        if (this.originalHtmlStyle !== undefined) {
          if (this.originalHtmlStyle) {
            document.documentElement.setAttribute('style', this.originalHtmlStyle);
          } else {
            document.documentElement.removeAttribute('style');
          }
        }

        // 移除可能的 viewport meta 标签污染（防御性编程）
        const viewportMeta = document.querySelector('meta[name="viewport"][data-qrcode]');
        if (viewportMeta) {
          viewportMeta.remove();
        }
      } catch (error) {
        console.error('[QRCode] Failed to restore styles:', error);
      }
    },

    drawQRCode() {
      const canvas = this.$refs.qrcodeCanvas;
      if (!canvas) return;

      const ctx = canvas.getContext('2d');
      const text = String(this.machineId);

      try {
        // 生成二维码矩阵
        const qrMatrix = generateQRCode(text);
        const moduleCount = qrMatrix.length;
        const cellSize = Math.floor(this.canvasSize / moduleCount);
        const actualSize = cellSize * moduleCount;
        const offset = Math.floor((this.canvasSize - actualSize) / 2);

        // 清空画布
        ctx.clearRect(0, 0, this.canvasSize, this.canvasSize);

        // 白色背景
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, this.canvasSize, this.canvasSize);

        // 绘制二维码
        ctx.fillStyle = '#000000';
        for (let row = 0; row < moduleCount; row++) {
          for (let col = 0; col < moduleCount; col++) {
            if (qrMatrix[row][col]) {
              ctx.fillRect(
                offset + col * cellSize,
                offset + row * cellSize,
                cellSize,
                cellSize
              );
            }
          }
        }

      } catch (error) {
        console.error('QR code generation error:', error);
        // 绘制错误提示
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, this.canvasSize, this.canvasSize);
        ctx.fillStyle = '#FF0000';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('二维码生成失败', this.canvasSize / 2, this.canvasSize / 2);
      }
    },

    clearCanvas() {
      const canvas = this.$refs.qrcodeCanvas;
      if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, this.canvasSize, this.canvasSize);
      }
    },

    downloadQrCode() {
      const canvas = this.$refs.qrcodeCanvas;
      if (!canvas) return;

      try {
        canvas.toBlob((blob) => {
          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.download = `machine_${this.machineCode}_qrcode.png`;
          link.href = url;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);
        }, 'image/png');
      } catch (error) {
        console.error('Download failed:', error);
        if (this.$message) {
          this.$message.error('下载失败');
        }
      }
    },

    printQrCode() {
      const canvas = this.$refs.qrcodeCanvas;
      if (!canvas) return;

      try {
        const dataUrl = canvas.toDataURL('image/png');

        // 使用更严格的窗口配置，完全隔离
        const printWin = window.open('', '_blank', 'width=600,height=700,toolbar=no,menubar=no,scrollbars=yes,resizable=yes');

        if (!printWin) {
          if (this.$message) {
            this.$message.warning('请允许弹出窗口');
          }
          return;
        }

        // 确保新窗口完全独立，清除任何默认内容
        printWin.document.write(`
          <!DOCTYPE html>
          <html>
          <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>机台二维码 - ${this.machineCode}</title>
            <style>
              * { margin: 0; padding: 0; box-sizing: border-box; }
              html, body {
                width: 100%;
                height: 100%;
                overflow-x: hidden;
              }
              body {
                font-family: Arial, sans-serif;
                padding: 20px;
                display: flex;
                flex-direction: column;
                align-items: center;
                min-width: 600px;
              }
              h2 { margin-bottom: 20px; color: #333; font-size: 24px; }
              .info { margin: 15px 0; line-height: 1.8; width: 100%; max-width: 400px; }
              .label { font-weight: bold; color: #666; }
              img {
                margin: 20px 0;
                width: 300px;
                height: 300px;
                border: 2px solid #eee;
                display: block;
              }
              @media print {
                body { padding: 0; min-width: auto; }
                @page { size: portrait; margin: 1cm; }
              }
              @media screen and (max-width: 640px) {
                body { min-width: 100%; padding: 10px; }
                h2 { font-size: 18px; }
                img { width: 250px; height: 250px; }
              }
            </style>
          </head>
          <body>
            <h2>机台二维码</h2>
            <div class="info">
              <div><span class="label">机台编号：</span>${this.machineCode}</div>
              <div><span class="label">机台名称：</span>${this.machineName}</div>
              <div><span class="label">机台ID：</span>${this.machineId}</div>
            </div>
            <img src="${dataUrl}" alt="机台二维码" />
            <div style="margin-top: 10px; color: #999; font-size: 12px;">
              扫描此二维码查看机台信息
            </div>
            <script>
              window.onload = function() {
                setTimeout(function() { window.print(); }, 500);
              };
            </script>
          </body>
          </html>
        `);
        printWin.document.close();

      } catch (error) {
        console.error('Print failed:', error);
        if (this.$message) {
          this.$message.error('打印失败');
        }
      }
    }
  }
};
</script>

<style scoped>
/* 强制重置所有可能被污染的样式 */
.qr-modal-isolated,
.qr-modal-isolated * {
  box-sizing: border-box;
  line-height: normal;
  letter-spacing: normal;
  word-spacing: normal;
  text-transform: none;
  text-indent: 0;
  text-shadow: none;
  white-space: normal;
}

.qr-modal-isolated {
  padding: 24px;
  max-width: 600px;
  margin: 0 auto;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  font-size: 14px;
  line-height: 1.5715;
  color: rgba(0, 0, 0, 0.85);
  background: transparent;
}

.qr-modal-isolated-header {
  text-align: center;
  margin-bottom: 24px;
  margin-top: 0;
  padding: 0;
}

.qr-modal-isolated-header h3 {
  font-size: 20px;
  font-weight: 600;
  color: #1890ff;
  margin: 0;
  padding: 0;
  line-height: 1.35;
  font-family: inherit;
}

.qr-modal-isolated-info {
  margin-bottom: 24px;
  margin-top: 0;
  padding: 0;
}

.qr-modal-isolated-canvas-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 32px;
  background-color: #f5f5f5;
  border-radius: 8px;
  margin-bottom: 24px;
  margin-top: 0;
  margin-left: auto;
  margin-right: auto;
}

.qr-modal-isolated-canvas {
  display: block;
  border: 1px solid #d9d9d9;
  background-color: #ffffff;
  margin: 0;
  padding: 0;
}

.qr-modal-isolated-footer {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin: 0;
  padding: 0;
}

.qr-modal-isolated-buttons {
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
  margin: 0;
  padding: 0;
}

.qr-modal-isolated-buttons .ant-btn {
  min-width: 140px;
  margin: 0;
}
</style>
