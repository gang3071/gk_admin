<template>
  <div class="batch-qrcode-container">
    <!-- 操作區 -->
    <div class="batch-action-bar">
      <div class="batch-controls">
        <span class="batch-control-item">
          <label>二維碼尺寸</label>
          <a-input-number v-model:value="qrSize" :min="80" :max="300" :step="10" size="small" />
          <span class="batch-unit">px</span>
        </span>
        <span class="batch-control-item">
          <label>每行數量</label>
          <a-input-number v-model:value="cols" :min="1" :max="6" size="small" />
        </span>
      </div>

      <div class="batch-buttons">
        <a-button type="primary" @click="downloadAll" :loading="generating" class="batch-btn-download">
          下載全部
        </a-button>
        <a-button @click="printAll" :loading="generating" class="batch-btn-print">
          列印全部
        </a-button>
        <a-button @click="renderAll" :loading="generating" class="batch-btn-regenerate">
          重新生成
        </a-button>
      </div>

      <div class="batch-summary">
        <a-tag color="blue">共 {{ machines.length }} 個機台</a-tag>
        <a-tag color="cyan">目前尺寸 {{ qrSize }}px</a-tag>
        <a-tag color="green">每頁 {{ perPage }} 個</a-tag>
        <a-tag color="orange">共 {{ pages.length }} 頁</a-tag>
        <a-tag v-if="clamped" color="red">尺寸過大，每行已自動調整為 {{ usedCols }}</a-tag>
      </div>
    </div>

    <!-- 加載提示 -->
    <div v-if="generating" class="batch-loading">
      <a-spin size="large" />
      <div class="batch-loading-text">正在生成二維碼...</div>
    </div>

    <!-- 多頁 A4 預覽 -->
    <div v-show="!generating" class="batch-pages">
      <div v-for="(page, pageIndex) in pages" :key="pageIndex" class="batch-page">
        <div class="batch-page-label">第 {{ pageIndex + 1 }} / {{ pages.length }} 頁</div>
        <canvas :ref="(el) => setPageCanvas(pageIndex, el)" class="batch-canvas"></canvas>
      </div>
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

      // 可調整參數
      qrSize: 180,   // 二維碼尺寸（正方形）
      cols: 3,       // 每行數量（會依紙張寬度自動夾限）

      // A4 紙張尺寸 (96 DPI)
      pageWidth: 794,   // 210mm
      pageHeight: 1123, // 297mm

      // 固定版面參數
      textHeight: 60,   // 文字區域高度
      padding: 20,      // 頁邊距
      gapX: 15,         // 水平間距
      gapY: 15          // 垂直間距
    };
  },
  computed: {
    usableWidth() {
      return this.pageWidth - this.padding * 2;
    },
    usableHeight() {
      return this.pageHeight - this.padding * 2;
    },
    cellWidth() {
      return this.qrSize + this.gapX;
    },
    cellHeight() {
      return this.qrSize + this.textHeight + this.gapY;
    },
    // 依紙張寬度算出最多能放幾欄
    maxCols() {
      return Math.max(1, Math.floor((this.usableWidth + this.gapX) / this.cellWidth));
    },
    // 依紙張高度算出最多能放幾列
    maxRows() {
      return Math.max(1, Math.floor((this.usableHeight + this.gapY) / this.cellHeight));
    },
    // 實際使用的欄數（不超過能放的數量，避免被切到）
    usedCols() {
      return Math.max(1, Math.min(this.cols, this.maxCols));
    },
    usedRows() {
      return this.maxRows;
    },
    // 每頁可容納數量
    perPage() {
      return this.usedCols * this.usedRows;
    },
    // 使用者設定的欄數超過可放數量時提示
    clamped() {
      return this.cols > this.maxCols;
    },
    // 依每頁容量切頁
    pages() {
      const list = this.machines || [];
      const result = [];
      for (let i = 0; i < list.length; i += this.perPage) {
        result.push(list.slice(i, i + this.perPage));
      }
      return result;
    }
  },
  watch: {
    qrSize() {
      this.renderAll();
    },
    cols() {
      this.renderAll();
    },
    machines() {
      this.renderAll();
    }
  },
  created() {
    // 各頁 canvas 元素（非響應式，避免 function ref 觸發重複渲染）
    this.pageCanvasEls = [];
  },
  mounted() {
    this.loadQRCodeLibrary();
  },
  methods: {
    /**
     * 記錄每一頁的 canvas 元素（function ref）
     */
    setPageCanvas(index, el) {
      if (!this.pageCanvasEls) {
        this.pageCanvasEls = [];
      }
      this.pageCanvasEls[index] = el || null;
    },

    /**
     * 取得所有頁面的 canvas 元素（依頁碼順序）
     */
    getPageCanvases() {
      const els = this.pageCanvasEls || [];
      return this.pages.map((page, index) => els[index]).filter(Boolean);
    },

    /**
     * 載入 QR Code 庫
     */
    async loadQRCodeLibrary() {
      if (window.qrcode) {
        this.qrcodeLoaded = true;
        this.renderAll();
        return;
      }

      this.generating = true;

      return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
        script.onload = () => {
          this.qrcodeLoaded = true;
          this.renderAll();
          resolve();
        };
        script.onerror = (error) => {
          this.$message.error('二維碼庫加載失敗，請檢查網絡連接');
          this.generating = false;
          reject(error);
        };
        document.head.appendChild(script);
      });
    },

    /**
     * 生成所有頁面的二維碼
     */
    async renderAll() {
      if (!this.qrcodeLoaded || !this.machines.length) {
        this.generating = false;
        return;
      }

      this.generating = true;

      // 等 DOM 更新（頁數可能因尺寸改變而變動）
      await this.$nextTick();

      try {
        const canvases = this.getPageCanvases();
        const pages = this.pages;

        for (let p = 0; p < pages.length; p++) {
          const canvas = canvases[p];
          if (!canvas) {
            continue;
          }

          // 設定 canvas 為 A4 尺寸
          canvas.width = this.pageWidth;
          canvas.height = this.pageHeight;

          const ctx = canvas.getContext('2d');

          // 白色背景
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, this.pageWidth, this.pageHeight);

          // 逐個繪製本頁的二維碼
          const items = pages[p];
          for (let i = 0; i < items.length; i++) {
            const row = Math.floor(i / this.usedCols);
            const col = i % this.usedCols;

            const x = this.padding + col * this.cellWidth;
            const y = this.padding + row * this.cellHeight;

            this.drawSingleQRCode(ctx, items[i], x, y);
          }
        }

        this.generating = false;
      } catch (error) {
        console.error('Generate QR codes failed:', error);
        this.$message.error('生成二維碼失敗');
        this.generating = false;
      }
    },

    /**
     * 繪製單個二維碼
     */
    drawSingleQRCode(ctx, machine, x, y) {
      try {
        if (!machine || !machine.id) {
          throw new Error('Invalid machine data');
        }

        const machineCode = String(machine.code || machine.id);
        const machineName = String(machine.name || '-');

        // 生成二維碼資料：機台ID|機台編號|時間戳
        const qrData = `${machine.id}|${machineCode}|${Date.now()}`;

        const qr = window.qrcode(0, 'M'); // type=0(auto), errorCorrectionLevel='M'
        qr.addData(qrData);
        qr.make();

        const moduleCount = qr.getModuleCount();
        const cellSize = this.qrSize / moduleCount;

        // 繪製二維碼模組
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

        // 邊框
        ctx.strokeStyle = '#cccccc';
        ctx.lineWidth = 1;
        ctx.strokeRect(x, y, this.qrSize, this.qrSize);

        // 文字標籤
        const textY = y + this.qrSize + 10;
        ctx.fillStyle = '#000000';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        // 機台編號（粗體、較大）
        ctx.font = 'bold 16px Arial, sans-serif';
        ctx.fillText(machineCode, x + this.qrSize / 2, textY);

        // 機台名稱（普通、較小）
        ctx.font = '14px Arial, sans-serif';
        const maxNameWidth = this.qrSize - 10;
        const truncatedName = this.truncateText(ctx, machineName, maxNameWidth);
        ctx.fillText(truncatedName, x + this.qrSize / 2, textY + 22);

      } catch (error) {
        console.error('Draw single QR code failed:', error);
        // 錯誤佔位符
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
     * 截斷文字以適應寬度
     */
    truncateText(ctx, text, maxWidth) {
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
     * 下載全部頁面（合併成一張 PNG）
     */
    async downloadAll() {
      if (this.generating) {
        return;
      }

      const canvases = this.getPageCanvases();
      if (!canvases.length) {
        this.$message.error('畫布未就緒');
        return;
      }

      try {
        const combined = document.createElement('canvas');
        combined.width = this.pageWidth;
        combined.height = this.pageHeight * canvases.length;

        const ctx = combined.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, combined.width, combined.height);

        canvases.forEach((canvas, index) => {
          ctx.drawImage(canvas, 0, index * this.pageHeight);
        });

        combined.toBlob((blob) => {
          if (!blob) {
            this.$message.error('生成圖片失敗');
            return;
          }

          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = `機台二維碼_${this.machines.length}台_${new Date().getTime()}.png`;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);

          this.$message.success('已下載全部頁面');
        }, 'image/png', 1.0);

      } catch (error) {
        console.error('Download failed:', error);
        this.$message.error('下載失敗');
      }
    },

    /**
     * 列印全部頁面（每頁一張 A4）
     */
    async printAll() {
      if (this.generating) {
        return;
      }

      const canvases = this.getPageCanvases();
      if (!canvases.length) {
        this.$message.error('畫布未就緒');
        return;
      }

      try {
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
          this.$message.error('請允許彈出窗口以進行列印');
          return;
        }

        const doc = printWindow.document;

        const styleEl = doc.createElement('style');
        styleEl.textContent = '@page { size: A4; margin: 0; } html, body { margin: 0; padding: 0; } img { width: 100%; display: block; page-break-after: always; break-after: page; } img:last-child { page-break-after: auto; break-after: auto; }';
        doc.head.appendChild(styleEl);

        let printed = false;
        const doPrint = () => {
          if (printed) {
            return;
          }
          printed = true;
          printWindow.focus();
          printWindow.print();
          printWindow.close();
        };

        const images = canvases.map((canvas) => {
          const img = doc.createElement('img');
          img.src = canvas.toDataURL('image/png');
          doc.body.appendChild(img);
          return img;
        });

        const total = images.length;
        let loaded = 0;
        const onOneLoaded = () => {
          loaded++;
          if (loaded >= total) {
            doPrint();
          }
        };

        images.forEach((img) => {
          if (img.complete) {
            onOneLoaded();
          } else {
            img.onload = onOneLoaded;
            img.onerror = onOneLoaded;
          }
        });

        // 保險：最多等 1.5 秒就列印
        setTimeout(doPrint, 1500);

      } catch (error) {
        console.error('Print failed:', error);
        this.$message.error('列印失敗');
      }
    }
  }
};
</script>

<style scoped>
.batch-qrcode-container {
  padding: 20px;
}

.batch-action-bar {
  background: #fff;
  padding: 15px;
  margin-bottom: 20px;
  border-bottom: 1px solid #e8e8e8;
  text-align: center;
}

.batch-controls {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.batch-control-item {
  display: inline-flex;
  align-items: center;
  margin: 0 12px 8px 12px;
}

.batch-control-item label {
  margin-right: 8px;
  color: #666;
}

.batch-unit {
  margin-left: 6px;
  color: #999;
}

.batch-buttons {
  margin-bottom: 12px;
}

.batch-btn-download {
  margin-right: 10px;
}

.batch-summary .ant-tag {
  margin: 4px;
}

.batch-loading {
  text-align: center;
  padding: 40px 0;
}

.batch-loading-text {
  margin-top: 15px;
  color: #666;
}

.batch-pages {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: #f0f0f0;
  padding: 20px;
}

.batch-page {
  margin-bottom: 24px;
  text-align: center;
}

.batch-page:last-child {
  margin-bottom: 0;
}

.batch-page-label {
  margin-bottom: 8px;
  color: #666;
  font-size: 13px;
}

.batch-canvas {
  width: 100%;
  max-width: 794px;
  height: auto;
  background: #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  display: block;
}
</style>
