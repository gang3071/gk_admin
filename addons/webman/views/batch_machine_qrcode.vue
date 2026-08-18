<template>
  <div class="batch-qrcode-container">
    <!-- 操作按钮 -->
    <div class="action-bar" style="margin-bottom: 20px; text-align: center;">
      <a-button type="primary" @click="downloadImage" :loading="generating" style="margin-right: 10px;">
        {{ $t('下載圖片') }}
      </a-button>
      <a-button @click="printQRCodes" :loading="generating">
        {{ $t('列印') }}
      </a-button>
      <a-tag color="blue" style="margin-left: 10px;">{{ $t('共') }} {{ machines.length }} {{ $t('個機台') }}</a-tag>
    </div>

    <!-- 加载提示 -->
    <div v-if="generating" style="text-align: center; padding: 40px 0;">
      <a-spin size="large" />
      <div style="margin-top: 15px; color: #666;">{{ $t('正在生成二維碼...') }}</div>
    </div>

    <!-- A4 画布预览 -->
    <div v-show="!generating" class="canvas-wrapper" ref="canvasWrapper">
      <canvas
        ref="batchCanvas"
        class="batch-canvas"
        :style="canvasStyle"
      ></canvas>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BatchMachineQrcode',
  props: {
    machines: {
      type: Array,
      required: true,
      default: () => []
    },
    title: {
      type: String,
      default: '批量二維碼'
    }
  },
  data() {
    return {
      generating: false,
      qrcodeLoaded: false,
      canvasScale: 1,   // Canvas 缩放比例

      // A4 纸张尺寸 (96 DPI)
      pageWidth: 794,   // 210mm
      pageHeight: 1123, // 297mm

      // 布局配置
      cols: 3,          // 每行3个二维码
      qrSize: 200,      // 二维码尺寸
      textHeight: 60,   // 文字区域高度
      padding: 20,      // 页边距
      gapX: 15,         // 水平间距
      gapY: 15,         // 垂直间距
    };
  },
  computed: {
    // 计算每个二维码单元的尺寸
    cellWidth() {
      return this.qrSize + this.gapX;
    },
    cellHeight() {
      return this.qrSize + this.textHeight + this.gapY;
    },
    // Canvas 缩放样式
    canvasStyle() {
      return {
        transform: `scale(${this.canvasScale})`,
        transformOrigin: 'center center',
        transition: 'transform 0.3s ease'
      };
    }
  },
  created() {
    // 调试：打印接收到的机台数据
    console.log('Batch QR Code - Machines data:', this.machines);
  },
  mounted() {
    this.loadQRCodeLibrary();
    // 监听窗口大小变化
    window.addEventListener('resize', this.calculateCanvasScale);
  },
  beforeDestroy() {
    // 移除监听器
    window.removeEventListener('resize', this.calculateCanvasScale);
  },
  methods: {
    /**
     * 加载 QR Code 库
     */
    async loadQRCodeLibrary() {
      if (window.qrcode) {
        this.qrcodeLoaded = true;
        this.generateAllQRCodes();
        return;
      }

      this.generating = true;

      return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
        script.onload = () => {
          this.qrcodeLoaded = true;
          this.generateAllQRCodes();
          resolve();
        };
        script.onerror = (error) => {
          this.$message.error(this.$t('二維碼庫加載失敗，請檢查網絡連接'));
          this.generating = false;
          reject(error);
        };
        document.head.appendChild(script);
      });
    },

    /**
     * 生成所有二维码到 A4 画布
     */
    async generateAllQRCodes() {
      if (!this.qrcodeLoaded || !this.machines.length) {
        this.generating = false;
        return;
      }

      this.generating = true;

      // 延迟执行，确保 DOM 已更新
      await this.$nextTick();

      try {
        const canvas = this.$refs.batchCanvas;
        if (!canvas) {
          throw new Error('Canvas not found');
        }

        // 设置 canvas 尺寸为 A4
        canvas.width = this.pageWidth;
        canvas.height = this.pageHeight;

        const ctx = canvas.getContext('2d');

        // 填充白色背景
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, this.pageWidth, this.pageHeight);

        // 遍历机台，逐个绘制二维码
        for (let i = 0; i < this.machines.length; i++) {
          const machine = this.machines[i];

          // 计算位置（每行3个）
          const row = Math.floor(i / this.cols);
          const col = i % this.cols;

          const x = this.padding + col * this.cellWidth;
          const y = this.padding + row * this.cellHeight;

          // 绘制单个二维码
          await this.drawSingleQRCode(ctx, machine, x, y);
        }

        this.generating = false;

        // 生成完成后计算缩放比例
        await this.$nextTick();
        this.calculateCanvasScale();
      } catch (error) {
        console.error('Generate QR codes failed:', error);
        this.$message.error(this.$t('生成二維碼失敗'));
        this.generating = false;
      }
    },

    /**
     * 计算 Canvas 缩放比例，使其适应可视区域
     */
    calculateCanvasScale() {
      this.$nextTick(() => {
        const wrapper = this.$refs.canvasWrapper;
        const canvas = this.$refs.batchCanvas;

        if (!wrapper || !canvas) {
          return;
        }

        // 获取容器尺寸
        const wrapperRect = wrapper.getBoundingClientRect();
        const wrapperWidth = wrapperRect.width - 40; // 减去 padding
        const wrapperHeight = wrapperRect.height - 40;

        // 计算缩放比例（保持宽高比）
        const scaleX = wrapperWidth / this.pageWidth;
        const scaleY = wrapperHeight / this.pageHeight;
        const scale = Math.min(scaleX, scaleY, 1); // 不放大，只缩小

        this.canvasScale = scale;
      });
    },

    /**
     * 绘制单个二维码
     */
    async drawSingleQRCode(ctx, machine, x, y) {
      try {
        // 防御性检查：确保必要字段存在
        if (!machine || !machine.id) {
          throw new Error('Invalid machine data');
        }

        // 确保 code 和 name 是字符串
        const machineCode = String(machine.code || machine.id);
        const machineName = String(machine.name || '-');

        // 生成二维码数据：机台ID|机台编号|时间戳
        const qrData = `${machine.id}|${machineCode}|${Date.now()}`;

        // 使用 qrcode-generator 库生成二维码
        const qr = window.qrcode(0, 'M'); // type=0(auto), errorCorrectionLevel='M'(15%)
        qr.addData(qrData);
        qr.make();

        const moduleCount = qr.getModuleCount();
        const cellSize = this.qrSize / moduleCount;

        // 绘制二维码模块
        for (let row = 0; row < moduleCount; row++) {
          for (let col = 0; col < moduleCount; col++) {
            const isDark = qr.isDark(row, col);
            ctx.fillStyle = isDark ? '#000000' : '#ffffff';
            ctx.fillRect(
              x + col * cellSize,
              y + row * cellSize,
              cellSize,
              cellSize
            );
          }
        }

        // 绘制边框
        ctx.strokeStyle = '#cccccc';
        ctx.lineWidth = 1;
        ctx.strokeRect(x, y, this.qrSize, this.qrSize);

        // 绘制文字标签
        const textY = y + this.qrSize + 10;
        ctx.fillStyle = '#000000';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        // 机台编号（粗体、较大）
        ctx.font = 'bold 16px Arial, sans-serif';
        ctx.fillText(machineCode, x + this.qrSize / 2, textY);

        // 机台名称（普通、较小）
        ctx.font = '14px Arial, sans-serif';
        const maxNameWidth = this.qrSize - 10;
        const truncatedName = this.truncateText(ctx, machineName, maxNameWidth);
        ctx.fillText(truncatedName, x + this.qrSize / 2, textY + 22);

      } catch (error) {
        console.error('Draw single QR code failed:', error);
        // 绘制错误占位符
        ctx.fillStyle = '#f5f5f5';
        ctx.fillRect(x, y, this.qrSize, this.qrSize);
        ctx.strokeStyle = '#ff4d4f';
        ctx.lineWidth = 2;
        ctx.strokeRect(x, y, this.qrSize, this.qrSize);
        ctx.fillStyle = '#ff4d4f';
        ctx.font = '14px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('生成失敗', x + this.qrSize / 2, y + this.qrSize / 2);
      }
    },

    /**
     * 截断文字以适应宽度
     */
    truncateText(ctx, text, maxWidth) {
      // 确保 text 是字符串
      const safeText = String(text || '');

      if (!safeText) {
        return '';
      }

      const metrics = ctx.measureText(safeText);
      if (metrics.width <= maxWidth) {
        return safeText;
      }

      let truncated = safeText;
      while (ctx.measureText(truncated + '...').width > maxWidth && truncated.length > 0) {
        truncated = truncated.slice(0, -1);
      }
      return truncated + '...';
    },

    /**
     * 下载为图片
     */
    async downloadImage() {
      if (this.generating) return;

      try {
        const canvas = this.$refs.batchCanvas;
        if (!canvas) {
          this.$message.error(this.$t('畫布未就緒'));
          return;
        }

        // 转换为 Blob
        canvas.toBlob((blob) => {
          if (!blob) {
            this.$message.error(this.$t('生成圖片失敗'));
            return;
          }

          // 创建下载链接
          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = `機台二維碼_${new Date().getTime()}.png`;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);

          this.$message.success(this.$t('圖片已下載'));
        }, 'image/png', 1.0);

      } catch (error) {
        console.error('Download failed:', error);
        this.$message.error(this.$t('下載失敗'));
      }
    },

    /**
     * 打印二维码
     */
    async printQRCodes() {
      if (this.generating) return;

      try {
        const canvas = this.$refs.batchCanvas;
        if (!canvas) {
          this.$message.error(this.$t('畫布未就緒'));
          return;
        }

        // 创建打印窗口
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
          this.$message.error(this.$t('請允許彈出窗口以進行列印'));
          return;
        }

        // 转换为图片 URL
        const imageUrl = canvas.toDataURL('image/png');

        // 写入打印页面 HTML
        printWindow.document.write(`
          <!DOCTYPE html>
          <html>
          <head>
            <title>機台二維碼列印</title>
            <style>
              @page {
                size: A4;
                margin: 0;
              }
              body {
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: flex-start;
              }
              img {
                max-width: 100%;
                height: auto;
                display: block;
              }
            </style>
          </head>
          <body>
            <img src="${imageUrl}" onload="window.print(); window.close();" />
          </body>
          </html>
        `);
        printWindow.document.close();

      } catch (error) {
        console.error('Print failed:', error);
        this.$message.error(this.$t('列印失敗'));
      }
    }
  }
};
</script>

<style scoped>
.batch-qrcode-container {
  padding: 20px;
  min-height: 600px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.action-bar {
  flex-shrink: 0;
  background: #fff;
  z-index: 10;
  padding: 15px;
  border-bottom: 1px solid #e8e8e8;
}

.canvas-wrapper {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  background: #f0f0f0;
  padding: 20px;
  overflow: hidden;
}

.batch-canvas {
  background: #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  display: block;
  /* Canvas 实际尺寸保持不变，通过 transform: scale() 缩放 */
}

@media print {
  .batch-qrcode-container {
    height: auto;
    overflow: visible;
  }

  .action-bar {
    display: none !important;
  }

  .canvas-wrapper {
    background: transparent !important;
    padding: 0 !important;
    overflow: visible !important;
  }

  .batch-canvas {
    box-shadow: none !important;
    transform: none !important; /* 打印时取消缩放 */
  }
}
</style>
