<template>
  <div class="player-game-list">
    <a-card :bordered="false" :loading="loading" :title="cardTitle">
      <!-- 筛选器 -->
      <template slot="extra">
        <div class="filter-bar">
          <a-select
            v-model="filters.platform_id"
            allowClear
            placeholder="选择平台"
            class="filter-select"
            @change="loadGameList"
          >
            <a-select-option v-for="platform in platforms" :key="platform.id" :value="platform.id">
              <div class="platform-option">
                <img
                  v-if="platform.logo"
                  :alt="platform.name"
                  :src="platform.logo"
                  class="platform-logo-small"
                />
                <span>{{ platform.name }}</span>
              </div>
            </a-select-option>
          </a-select>

          <a-select
            v-model="filters.is_hot"
            allowClear
            class="filter-select-small"
            placeholder="热门筛选"
            @change="loadGameList"
          >
            <a-select-option :value="1">🔥 热门</a-select-option>
            <a-select-option :value="0">普通</a-select-option>
          </a-select>

          <a-select
            v-model="filters.is_new"
            allowClear
            placeholder="新游戏"
            class="filter-select-small"
            @change="loadGameList"
          >
            <a-select-option :value="1">✨ 新游戏</a-select-option>
            <a-select-option :value="0">旧游戏</a-select-option>
          </a-select>

          <a-button
            :loading="saving"
            icon="save"
            size="default"
            type="primary"
            @click="saveSelectedGames"
          >
            保存
          </a-button>
        </div>
      </template>

      <!-- 统计信息 -->
      <div v-if="gameList.length > 0" class="stats-bar">
        <div class="stats-content">
          <span class="stats-item">
            <a-icon style="margin-right: 4px;" type="database" />
            总计: <strong>{{ pagination.total }}</strong> 个游戏
          </span>
          <span class="stats-item">
            <a-icon style="margin-right: 4px; color: #52c41a;" type="check-circle" />
            已选中: <strong style="color: #1890ff;">{{ selectedRowKeys.length }}</strong> 个
          </span>
          <span class="stats-item">
            <a-icon style="margin-right: 4px; color: #ff4d4f;" type="stop" />
            已禁用: <strong style="color: #ff4d4f;">{{ disabledCount }}</strong> 个
          </span>
        </div>
      </div>

      <!-- 游戏表格 -->
      <a-table
        :columns="columns"
        :data-source="gameList"
        :loading="loading"
        :pagination="pagination"
        :row-selection="rowSelection"
        :scroll="{ x: 1300, y: 600 }"
        bordered
        size="middle"
        row-key="id"
        @change="handleTableChange"
      >
        <template slot="id" slot-scope="text">
          <span>{{ text }}</span>
        </template>

        <template slot="platform" slot-scope="text, record">
          <div class="platform-cell">
            <img
              v-if="record.platform_logo"
              :alt="record.platform_name"
              :src="record.platform_logo"
              class="platform-logo"
            />
            <a-tag color="blue">{{ record.platform_name }}</a-tag>
          </div>
        </template>

        <template slot="game_name" slot-scope="text, record">
          <div class="game-name-cell">
            <div class="game-avatar-wrapper">
              <img
                v-if="record.picture"
                :alt="record.name"
                :src="record.picture"
                class="game-avatar"
                @error="handleImageError"
              />
              <div v-else class="game-avatar-placeholder">
                <span>无图</span>
              </div>
            </div>
            <span class="game-name-text">{{ record.name }}</span>
          </div>
        </template>

        <template slot="category" slot-scope="text, record">
          <a-tag color="green">{{ record.category_name }}</a-tag>
        </template>

        <template slot="is_hot" slot-scope="text, record">
          <a-tag v-if="record.is_hot === 1" color="red">🔥 热门</a-tag>
          <span v-else class="empty-tag">-</span>
        </template>

        <template slot="is_new" slot-scope="text, record">
          <a-tag v-if="record.is_new === 1" color="orange">✨ 新</a-tag>
          <span v-else class="empty-tag">-</span>
        </template>

        <template slot="status" slot-scope="text, record">
          <a-tag :color="record.is_selected ? 'red' : 'green'">
            {{ record.is_selected ? '已禁用' : '正常' }}
          </a-tag>
        </template>

        <template slot="action" slot-scope="text, record">
          <a
            v-if="record.is_selected"
            @click="toggleGame(record, false)"
            class="action-link action-enable"
          >
            取消禁用
          </a>
          <a
            v-else
            @click="toggleGame(record, true)"
            class="action-link action-disable"
          >
            禁用游戏
          </a>
        </template>
      </a-table>
    </a-card>
  </div>
</template>

<script>
export default {
  name: 'PlayerGameList',
  props: {
    player_id: {
      type: [String, Number],
      required: true
    },
    player_name: {
      type: String,
      default: ''
    },
    title: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      loading: false,
      saving: false,
      gameList: [],
      platforms: [],
      selectedRowKeys: [],
      filters: {
        platform_id: undefined,
        is_hot: undefined,
        is_new: undefined
      },
      pagination: {
        current: 1,
        pageSize: 50,
        total: 0,
        showSizeChanger: true,
        showQuickJumper: true,
        pageSizeOptions: ['20', '50', '100', '200'],
        showTotal: (total) => `共 ${total} 个游戏`
      },
      columns: [
        {
          title: 'ID',
          dataIndex: 'id',
          key: 'id',
          scopedSlots: { customRender: 'id' },
          width: 90,
          align: 'center'
        },
        {
          title: '游戏名称',
          dataIndex: 'name',
          key: 'game_name',
          scopedSlots: { customRender: 'game_name' },
          width: 300
        },
        {
          title: '游戏平台',
          dataIndex: 'platform_name',
          key: 'platform',
          scopedSlots: { customRender: 'platform' },
          width: 180,
          align: 'center'
        },
        {
          title: '游戏分类',
          dataIndex: 'category_name',
          key: 'category',
          scopedSlots: { customRender: 'category' },
          width: 110,
          align: 'center'
        },
        {
          title: '热门',
          dataIndex: 'is_hot',
          key: 'is_hot',
          scopedSlots: { customRender: 'is_hot' },
          width: 90,
          align: 'center'
        },
        {
          title: '新游戏',
          dataIndex: 'is_new',
          key: 'is_new',
          scopedSlots: { customRender: 'is_new' },
          width: 90,
          align: 'center'
        },
        {
          title: '状态',
          dataIndex: 'is_selected',
          key: 'status',
          scopedSlots: { customRender: 'status' },
          width: 100,
          align: 'center'
        },
        {
          title: '操作',
          key: 'action',
          scopedSlots: { customRender: 'action' },
          width: 100,
          align: 'center'
        }
      ]
    };
  },
  computed: {
    cardTitle() {
      // 优先使用后端传入的翻译标题，否则使用默认格式
      return this.title || `${this.player_name} - 游戏权限管理`;
    },
    rowSelection() {
      return {
        selectedRowKeys: this.selectedRowKeys,
        onChange: (keys) => {
          this.selectedRowKeys = keys;
        }
      };
    },
    disabledCount() {
      return this.gameList.filter(game => game.is_selected).length;
    }
  },
  mounted() {
    this.loadGameList();
  },
  methods: {
    // 确认对话框辅助函数（兼容ExAdmin环境）
    showConfirm(options) {
      const self = this;
      return new Promise((resolve) => {
        try {
          this.$confirm({
            title: options.title,
            content: options.content,
            okText: '确认',
            cancelText: '取消',
            onOk() {
              resolve(true);
              if (options.onOk) {
                return options.onOk();
              }
            },
            onCancel() {
              resolve(false);
              if (options.onCancel) {
                options.onCancel();
              }
            }
          });
        } catch (error) {
          // Fallback到原生confirm
          console.warn('Modal.confirm不可用，使用原生confirm', error);
          const result = window.confirm(`${options.title}\n\n${options.content}`);
          if (result && options.onOk) {
            options.onOk();
          }
          resolve(result);
        }
      });
    },

    // 加载游戏列表
    loadGameList() {
      this.loading = true;

      const promise = this.$request({
        url: 'ex-admin/addons-webman-controller-ChannelPlayerController/getPlayerGameListData',
        params: {
          player_id: this.player_id,
          page: this.pagination.current,
          size: this.pagination.pageSize,
          ...this.filters
        }
      });

      // ExAdmin的$request可能会reject成功的响应，所以我们在两个回调中都处理
      const handleResponse = (res) => {
        if (res && res.status === 1 && res.data) {
          const data = res.data;
          this.gameList = data.list || [];
          this.pagination.total = data.total || 0;
          this.platforms = data.platforms || [];

          // 更新选中的行（已禁用的游戏）
          this.selectedRowKeys = this.gameList
            .filter(game => game && game.is_selected)
            .map(game => game.id);
        } else {
          console.error('响应格式不正确:', res);
        }
      };

      promise.then(handleResponse, handleResponse)
        .finally(() => {
          this.loading = false;
        });
    },

    // 表格变化处理
    handleTableChange(pag) {
      this.pagination.current = pag.current;
      this.pagination.pageSize = pag.pageSize;
      this.loadGameList();
    },

    // 单个游戏切换
    toggleGame(record, disable) {
      const action = disable ? 'disable' : 'enable';
      const actionText = disable ? '禁用' : '取消禁用';

      this.showConfirm({
        title: `确认${actionText}游戏`,
        content: `确定要${actionText}游戏"${record.name}"吗？`,
        onOk: () => {
          const promise = this.$request({
            url: 'ex-admin/addons-webman-controller-ChannelPlayerController/toggleGameDisable',
            method: 'post',
            data: {
              player_id: this.player_id,
              game_id: record.id,
              action: action
            }
          });

          const handleResponse = (res) => {
            if (res && res.status === 1) {
              if (this.$message && this.$message.success) {
                this.$message.success(res.message || `${actionText}成功`);
              }
              this.loadGameList();
            }
          };

          promise.then(handleResponse, handleResponse);
        }
      });
    },

    // 图片加载错误处理
    handleImageError(e) {
      e.target.style.display = 'none';
      if (e.target.nextElementSibling) {
        e.target.nextElementSibling.style.display = 'flex';
      }
    },

    // 批量保存选中的游戏
    saveSelectedGames() {
      if (this.selectedRowKeys.length === 0) {
        if (this.$message && this.$message.warning) {
          this.$message.warning('请至少选择一个游戏');
        } else {
          alert('请至少选择一个游戏');
        }
        return;
      }

      this.showConfirm({
        title: '确认保存',
        content: `确定要将选中的 ${this.selectedRowKeys.length} 个游戏设为禁用状态吗？`,
        onOk: () => {
          this.saving = true;
          const promise = this.$request({
            url: 'ex-admin/addons-webman-controller-ChannelPlayerController/savePlayerGamesVue',
            method: 'post',
            data: {
              player_id: this.player_id,
              selected_game_ids: this.selectedRowKeys
            }
          });

          const handleResponse = (res) => {
            if (res && res.status === 1) {
              if (this.$message && this.$message.success) {
                this.$message.success(res.message || '保存成功');
              }
              this.loadGameList();
            }
          };

          promise.then(handleResponse, handleResponse)
            .finally(() => {
              this.saving = false;
            });
        }
      });
    }
  }
};
</script>

<style scoped>
.player-game-list {
  padding: 16px;
  background: #f0f2f5;
}

/* 统计栏样式 */
.stats-bar {
  background: #f5f7fa;
  padding: 12px 16px;
  margin-bottom: 16px;
  border-radius: 6px;
  border: 1px solid #e8ebf0;
}

.stats-content {
  display: flex;
  gap: 24px;
  align-items: center;
  flex-wrap: wrap;
}

.stats-item {
  color: #666;
  font-size: 14px;
}

.stats-item strong {
  font-size: 16px;
  font-weight: 600;
}

/* 筛选栏样式 */
.filter-bar {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.filter-select {
  width: 200px;
  min-width: 180px;
}

.filter-select-small {
  width: 120px;
  min-width: 100px;
}

.platform-option {
  display: flex;
  align-items: center;
  gap: 6px;
}

.platform-logo-small {
  width: 20px;
  height: 20px;
  object-fit: contain;
  flex-shrink: 0;
}

/* 平台列样式 */
.platform-cell {
  display: flex;
  align-items: center;
  gap: 6px;
  justify-content: center;
}

.platform-logo {
  width: 24px;
  height: 24px;
  object-fit: contain;
  flex-shrink: 0;
}

/* 游戏名称列样式 */
.game-name-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.game-avatar-wrapper {
  flex-shrink: 0;
}

.game-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #f0f0f0;
  transition: all 0.3s;
}

.game-avatar:hover {
  transform: scale(1.1);
  border-color: #1890ff;
  box-shadow: 0 2px 8px rgba(24, 144, 255, 0.3);
}

.game-avatar-placeholder {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  color: #999;
  border: 2px solid #f0f0f0;
}

.game-name-text {
  flex: 1;
  word-break: break-word;
  line-height: 1.5;
  color: #333;
  font-weight: 500;
}

/* 空标签样式 */
.empty-tag {
  color: #d9d9d9;
  font-size: 14px;
}

/* 操作链接样式 */
.action-link {
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
  text-decoration: none;
  padding: 4px 8px;
  border-radius: 4px;
  display: inline-block;
}

.action-enable {
  color: #52c41a;
}

.action-enable:hover {
  color: #73d13d;
  background: #f6ffed;
}

.action-disable {
  color: #ff4d4f;
}

.action-disable:hover {
  color: #ff7875;
  background: #fff1f0;
}

/* 表格样式优化 */
:deep(.ant-table) {
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
}

:deep(.ant-table-thead > tr > th) {
  background: #fafafa;
  font-weight: 600;
  color: #333;
  border-bottom: 2px solid #f0f0f0;
}

:deep(.ant-table-tbody > tr:hover > td) {
  background: #f5f9ff;
}

:deep(.ant-table-tbody > tr > td) {
  border-bottom: 1px solid #f5f5f5;
}

/* Tag 样式优化 */
:deep(.ant-tag) {
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 12px;
  font-weight: 500;
  border: none;
}

/* 分页样式 */
:deep(.ant-pagination) {
  margin-top: 16px;
}

/* 卡片样式 */
:deep(.ant-card) {
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

:deep(.ant-card-head) {
  border-bottom: 1px solid #f0f0f0;
  background: #fff;
}

:deep(.ant-card-head-title) {
  font-size: 16px;
  font-weight: 600;
  color: #333;
}
</style>
