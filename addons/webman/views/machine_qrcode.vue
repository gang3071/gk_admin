<template>
  <div class="qr-modal-container" ref="modalContainer">
    <div class="qr-header">
      <h3>{{ modalTitle }}</h3>
    </div>

    <div class="qr-info">
      <a-descriptions :column="1" bordered size="small">
        <a-descriptions-item :label="t.machine_code">
          <a-tag color="blue">{{ machineCode }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item :label="t.machine_name">
          {{ machineName }}
        </a-descriptions-item>
        <a-descriptions-item :label="t.machine_id">
          <a-tag color="green">{{ machineId }}</a-tag>
        </a-descriptions-item>
      </a-descriptions>
    </div>

    <div class="qr-canvas-wrapper">
      <canvas
        ref="qrcodeCanvas"
        :width="canvasSize"
        :height="canvasSize"
        class="qr-canvas"
      ></canvas>
    </div>

    <div class="qr-footer">
      <a-alert
        :message="t.scan_title"
        :description="t.scan_desc"
        type="info"
        show-icon
      />

      <div class="qr-buttons">
        <a-button type="primary" @click="downloadQrCode" size="large">
          {{ t.download }}
        </a-button>

        <a-button @click="printQrCode" size="large">
          {{ t.print }}
        </a-button>
      </div>
    </div>
  </div>
</template>

<script>
const messages = {
  'zh-TW': {
    default_title: '機台二維碼',
    machine_code: '機台編號',
    machine_name: '機台名稱',
    machine_id: '機台 ID',
    machine_qr_code: '機台二維碼',
    scan_title: '掃碼說明',
    scan_desc: '玩家使用手機APP掃描此二維碼，即可快速查看該機台資訊',
    download: '下載二維碼',
    print: '列印二維碼',
    qr_error: '二維碼生成失敗',
    download_failed: '下載失敗',
    print_failed: '列印失敗'
  },
  'zh-CN': {
    default_title: '机台二维码',
    machine_code: '机台编号',
    machine_name: '机台名称',
    machine_id: '机台 ID',
    machine_qr_code: '机台二维码',
    scan_title: '扫码说明',
    scan_desc: '玩家使用手机APP扫描此二维码，即可快速查看该机台信息',
    download: '下载二维码',
    print: '打印二维码',
    qr_error: '二维码生成失败',
    download_failed: '下载失败',
    print_failed: '打印失败'
  },
  en: {
    default_title: 'Machine QR Code',
    machine_code: 'Machine Code',
    machine_name: 'Machine Name',
    machine_id: 'Machine ID',
    machine_qr_code: 'Machine QR Code',
    scan_title: 'Scan Instructions',
    scan_desc: 'Players scan this QR code with the mobile app to view machine information quickly.',
    download: 'Download QR Code',
    print: 'Print QR Code',
    qr_error: 'QR code generation failed',
    download_failed: 'Download failed',
    print_failed: 'Print failed'
  },
  jp: {
    default_title: '筐体QRコード',
    machine_code: '筐体番号',
    machine_name: '筐体名',
    machine_id: '筐体ID',
    machine_qr_code: '筐体QRコード',
    scan_title: 'スキャン説明',
    scan_desc: 'プレイヤーがスマホアプリでこのQRコードをスキャンすると、該当筐体の情報を素早く確認できます',
    download: 'QRコードダウンロード',
    print: 'QRコード印刷',
    qr_error: 'QRコード生成失敗',
    download_failed: 'ダウンロード失敗',
    print_failed: '印刷失敗'
  }
}
// 引入第三方二维码库（通过 CDN）
// 注意：这里使用内联实现，避免外部依赖

// 使用更简单的 QR Code 生成方式
function generateQRCode(text) {
  const QRCode = {
    typeNumber: 4,
    errorCorrectLevel: 'H',

    make: function(text) {
      const typeNumber = this.getTypeNumber(text);
      const errorCorrectLevel = 2;

      const moduleCount = typeNumber * 4 + 17;
      const modules = new Array(moduleCount);

      for (let row = 0; row < moduleCount; row++) {
        modules[row] = new Array(moduleCount);
        for (let col = 0; col < moduleCount; col++) {
          modules[row][col] = null;
        }
      }

      this.setupPositionProbePattern(modules, 0, 0);
      this.setupPositionProbePattern(modules, moduleCount - 7, 0);
      this.setupPositionProbePattern(modules, 0, moduleCount - 7);
      this.setupTimingPattern(modules, moduleCount);

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
      default: ''
    },
    langLocale: {
      type: String,
      default: 'zh-TW'
    }
  },
  computed: {
    t() {
      return messages[this.langLocale] || messages['zh-TW'];
    },
    modalTitle() {
      return this.title || this.t.default_title;
    }
  },
  data() {
    return {
      qrcodeSize: 260,      // 二维码区域大小（从 300 缩小到 260）
      canvasSize: 320,      // 总画布大小（从 380 缩小到 320）
      textHeight: 60        // 文字区域高度（从 80 缩小到 60）
    };
  },
  mounted() {
    // 加载完整的 QR Code 库
    this.loadQRCodeLibrary().then(() => {
      this.$nextTick(() => {
        this.drawQRCodeWithLibrary();
      });
    }).catch(() => {
      // 如果加载失败，回退到简化版本
      this.$nextTick(() => {
        this.drawQRCode();
      });
    });
  },
  beforeUnmount() {
    this.clearCanvas();
  },
  methods: {
    loadQRCodeLibrary() {
      // 检查是否已加载
      if (window.qrcode) {
        return Promise.resolve();
      }

      return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
        script.onload = () => {
          console.log('[QRCode] Library loaded successfully');
          resolve();
        };
        script.onerror = () => {
          console.error('[QRCode] Failed to load library');
          reject();
        };
        document.head.appendChild(script);
      });
    },

    drawQRCodeWithLibrary() {
      const canvas = this.$refs.qrcodeCanvas;
      if (!canvas) return;

      const ctx = canvas.getContext('2d');
      const qrData = `${this.machineId}|${this.machineCode}|${Date.now()}`;

      try {
        // 使用完整的 QR Code 库
        const qr = window.qrcode(0, 'M'); // type=0(自动), 纠错级别=M
        qr.addData(qrData);
        qr.make();

        const moduleCount = qr.getModuleCount();
        const cellSize = Math.floor(this.qrcodeSize / moduleCount);
        const actualSize = cellSize * moduleCount;
        const offsetX = Math.floor((this.canvasSize - actualSize) / 2);
        const offsetY = 10;

        // 清空画布
        ctx.clearRect(0, 0, this.canvasSize, this.canvasSize);

        // 白色背景
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, this.canvasSize, this.canvasSize);

        // 绘制二维码
        ctx.fillStyle = '#000000';
        for (let row = 0; row < moduleCount; row++) {
          for (let col = 0; col < moduleCount; col++) {
            if (qr.isDark(row, col)) {
              ctx.fillRect(
                offsetX + col * cellSize,
                offsetY + row * cellSize,
                cellSize,
                cellSize
              );
            }
          }
        }

        // 绘制文字标签
        const textStartY = offsetY + actualSize + 20;

        ctx.fillStyle = '#000000';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        ctx.font = 'bold 16px Arial, sans-serif';
        ctx.fillText(`${this.t.machine_code}: ${this.machineCode}`, this.canvasSize / 2, textStartY);

        ctx.font = '14px Arial, sans-serif';
        const nameText = this.machineName.length > 15
          ? this.machineName.substring(0, 15) + '...'
          : this.machineName;
        ctx.fillText(`${this.t.machine_name}: ${nameText}`, this.canvasSize / 2, textStartY + 25);

        // 边框
        ctx.strokeStyle = '#d9d9d9';
        ctx.lineWidth = 1;
        ctx.strokeRect(0, 0, this.canvasSize, this.canvasSize);

        console.log('[QRCode] Generated successfully with library');

      } catch (error) {
        console.error('QR code generation error:', error);
        // 绘制错误提示
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, this.canvasSize, this.canvasSize);
        ctx.fillStyle = '#FF0000';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(this.t.qr_error, this.canvasSize / 2, this.canvasSize / 2);
      }
    },

    drawQRCode() {
      const canvas = this.$refs.qrcodeCanvas;
      if (!canvas) return;

      const ctx = canvas.getContext('2d');

      // 简化内容：只包含机台ID、编号和时间戳
      const qrData = `${this.machineId}|${this.machineCode}|${Date.now()}`;

      try {
        const qrMatrix = generateQRCode(qrData);
        const moduleCount = qrMatrix.length;
        const cellSize = Math.floor(this.qrcodeSize / moduleCount);
        const actualSize = cellSize * moduleCount;
        const offsetX = Math.floor((this.canvasSize - actualSize) / 2);
        const offsetY = 10;

        ctx.clearRect(0, 0, this.canvasSize, this.canvasSize);

        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, this.canvasSize, this.canvasSize);

        ctx.fillStyle = '#000000';
        for (let row = 0; row < moduleCount; row++) {
          for (let col = 0; col < moduleCount; col++) {
            if (qrMatrix[row][col]) {
              ctx.fillRect(
                offsetX + col * cellSize,
                offsetY + row * cellSize,
                cellSize,
                cellSize
              );
            }
          }
        }

        const textStartY = offsetY + actualSize + 20;

        ctx.fillStyle = '#000000';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        ctx.font = 'bold 16px Arial, sans-serif';
        ctx.fillText(`${this.t.machine_code}: ${this.machineCode}`, this.canvasSize / 2, textStartY);

        ctx.font = '14px Arial, sans-serif';
        const nameText = this.machineName.length > 15
          ? this.machineName.substring(0, 15) + '...'
          : this.machineName;
        ctx.fillText(`${this.t.machine_name}: ${nameText}`, this.canvasSize / 2, textStartY + 25);

        ctx.strokeStyle = '#d9d9d9';
        ctx.lineWidth = 1;
        ctx.strokeRect(0, 0, this.canvasSize, this.canvasSize);

      } catch (error) {
        console.error('QR code generation error:', error);
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, this.canvasSize, this.canvasSize);
        ctx.fillStyle = '#FF0000';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(this.t.qr_error, this.canvasSize / 2, this.canvasSize / 2);
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
          this.$message.error(this.t.download_failed);
        }
      }
    },

    printQrCode() {
      const canvas = this.$refs.qrcodeCanvas;
      if (!canvas) return;

      try {
        // 1. 转换 canvas 为图片
        const dataUrl = canvas.toDataURL('image/png');

        // 2. 创建临时打印容器（纯 DOM 元素，不使用 HTML 字符串）
        const printContainer = document.createElement('div');
        printContainer.className = 'qrcode-print-container';

        // 3. 创建机台信息区域（只显示编号和名称）
        const infoDiv = document.createElement('div');
        infoDiv.className = 'print-info';

        const codeInfo = document.createElement('div');
        codeInfo.innerHTML = `<strong>${this.t.machine_code}:</strong> ${this.machineCode}`;
        infoDiv.appendChild(codeInfo);

        const nameInfo = document.createElement('div');
        nameInfo.innerHTML = `<strong>${this.t.machine_name}:</strong> ${this.machineName}`;
        infoDiv.appendChild(nameInfo);

        printContainer.appendChild(infoDiv);

        // 4. 创建二维码图片
        const img = document.createElement('img');
        img.src = dataUrl;
        img.alt = this.t.machine_qr_code;
        img.className = 'print-qrcode-image';
        printContainer.appendChild(img);

        // 5. 添加到 body
        document.body.appendChild(printContainer);

        // 6. 执行打印
        setTimeout(() => {
          window.print();

          // 7. 监听打印完成后清理（延迟确保打印完成）
          setTimeout(() => {
            document.body.removeChild(printContainer);
          }, 1000);
        }, 100);

      } catch (error) {
        console.error('Print failed:', error);
        if (this.$message) {
          this.$message.error(this.t.print_failed);
        }
      }
    }
  }
};
</script>

<style scoped>
.qr-modal-container {
  padding: 12px;
  max-width: 500px;
  margin: 0 auto;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  font-size: 14px;
  line-height: 1.5715;
  color: rgba(0, 0, 0, 0.85);
}

.qr-header {
  text-align: center;
  margin-bottom: 8px;
}

.qr-header h3 {
  font-size: 16px;
  font-weight: 600;
  color: #1890ff;
  margin: 0;
  padding: 0;
  line-height: 1.35;
}

.qr-info {
  margin-bottom: 8px;
}

.qr-canvas-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 12px;
  background-color: #f5f5f5;
  border-radius: 8px;
  margin-bottom: 8px;
}

.qr-canvas {
  display: block;
  border: 1px solid #d9d9d9;
  background-color: #ffffff;
}

.qr-footer {
  display: flex;
  flex-direction: column;
}

.qr-footer > * {
  margin-bottom: 8px;
}

.qr-footer > *:last-child {
  margin-bottom: 0;
}

.qr-buttons {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
}

.qr-buttons > * {
  margin: 0 6px 6px 6px;
}

.qr-buttons .ant-btn {
  min-width: 100px;
}
</style>

<style>
/* 全局打印样式（不能 scoped，因为打印元素在 body 下） */

/* 打印容器默认隐藏 */
.qrcode-print-container {
  display: none;
}

/* 打印时的样式 */
@media print {
  /* 隐藏页面所有内容 */
  body > *:not(.qrcode-print-container) {
    display: none !important;
  }

  /* 显示打印容器 */
  .qrcode-print-container {
    display: block !important;
    padding: 10px;
    text-align: center;
    width: 100%;
    max-width: 100%;
  }

  /* 隐藏标题 */
  .qrcode-print-container h2 {
    display: none;
  }

  /* 隐藏机台信息（二维码图片内已包含） */
  .qrcode-print-container .print-info {
    display: none;
  }

  /* 二维码图片 - 固定大小（现在包含文字标签） */
  .qrcode-print-container .print-qrcode-image {
    width: 10cm;
    height: auto;
    max-height: 12cm;
    margin: 0 auto;
    display: block;
    border: none;
  }

  /* 隐藏底部提示 */
  .qrcode-print-container .print-hint {
    display: none;
  }

  /* 设置打印页面 - 紧凑尺寸 */
  @page {
    size: 10cm 13cm;
    margin: 0.3cm;
  }

  /* 重置 body 样式 */
  html, body {
    width: 100%;
    height: auto;
    margin: 0;
    padding: 0;
  }
}
</style>
