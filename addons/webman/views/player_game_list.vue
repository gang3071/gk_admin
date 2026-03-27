<template>
  <div class="player-game-list">
    <a-card :bordered="false" :loading="loading" :title="cardTitle">
      <div slot="extra" class="filter-bar">
        <a-input-search
          v-model="filters.game_name"
          allowClear
          class="filter-search"
          enter-button
          placeholder="搜索游戏名称"
          @search="loadGameList"
        />

        <a-select
          v-model="filters.platform_id"
          allowClear
          class="filter-select"
          placeholder="选择平台"
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
          v-model="filters.cate_id"
          allowClear
          class="filter-select-small"
          placeholder="游戏分类"
          @change="loadGameList"
        >
          <a-select-option v-for="category in categories" :key="category.value" :value="category.value">
            {{ category.label }}
          </a-select-option>
        </a-select>

        <a-select
          v-model="filters.is_hot"
          allowClear
          class="filter-select-small"
          placeholder="热门筛选"
          @change="loadGameList"
        >
          <a-select-option :value="1">
            <span style="color: #ff4d4f; font-weight: 600;">
              <a-icon type="fire" /> 热门
            </span>
          </a-select-option>
          <a-select-option :value="0">普通</a-select-option>
        </a-select>

        <a-select
          v-model="filters.is_new"
          allowClear
          class="filter-select-small"
          placeholder="新游戏"
          @change="loadGameList"
        >
          <a-select-option :value="1">
            <span style="color: #ff7a45; font-weight: 600;">
              <a-icon type="thunderbolt" /> 新游戏
            </span>
          </a-select-option>
          <a-select-option :value="0">旧游戏</a-select-option>
        </a-select>

        <a-button
          icon="reload"
          size="default"
          @click="resetFilters"
        >
          重置
        </a-button>

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
        :scroll="{ x: 1250, y: 600 }"
        bordered
        size="middle"
        row-key="id"
        @change="handleTableChange"
      >
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
    const vm = this;
    return {
      loading: false,
      saving: false,
      gameList: [],
      platforms: [],
      categories: [],
      selectedRowKeys: [],
      filters: {
        game_name: undefined,
        platform_id: undefined,
        cate_id: undefined,
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
      columns: []
    };
  },
  created() {
    // 在 created 钩子中初始化 columns，此时 this 可用
    this.columns = [
      {
        title: '游戏名称',
        dataIndex: 'name',
        key: 'game_name',
        width: 300,
        customRender: (text, record) => {
          const h = this.$createElement;
          return h('div', { class: 'game-name-cell' }, [
            h('div', { class: 'game-avatar-wrapper' }, [
              record.picture
                ? h('img', {
                    attrs: { src: record.picture, alt: record.name },
                    class: 'game-avatar'
                  })
                : h('div', { class: 'game-avatar-placeholder' }, [h('span', '无图')])
            ]),
            h('span', { class: 'game-name-text' }, text)
          ]);
        }
      },
      {
        title: '游戏平台',
        dataIndex: 'platform_name',
        key: 'platform',
        width: 180,
        align: 'center',
        customRender: (text, record) => {
          const h = this.$createElement;
          const elements = [];
          if (record.platform_logo) {
            elements.push(h('img', {
              attrs: { src: record.platform_logo, alt: record.platform_name },
              class: 'platform-logo'
            }));
          }
          elements.push(h('a-tag', { props: { color: 'blue' } }, text));
          return h('div', { class: 'platform-cell' }, elements);
        }
      },
      {
        title: '游戏分类',
        dataIndex: 'category_name',
        key: 'category',
        width: 110,
        align: 'center',
        customRender: (text) => {
          const h = this.$createElement;
          return h('a-tag', { props: { color: 'green' } }, text);
        }
      },
      {
        title: '热门',
        dataIndex: 'is_hot',
        key: 'is_hot',
        width: 100,
        align: 'center',
        customRender: (text, record) => {
          const h = this.$createElement;
          if (Number(record.is_hot) === 1) {
            return h('div', { class: 'tag-hot' }, [
              h('a-icon', { props: { type: 'fire' } }),
              h('span', '热门')
            ]);
          }
          return h('span', { class: 'tag-empty' }, '—');
        }
      },
      {
        title: '新游戏',
        dataIndex: 'is_new',
        key: 'is_new',
        width: 100,
        align: 'center',
        customRender: (text, record) => {
          const h = this.$createElement;
          if (Number(record.is_new) === 1) {
            return h('div', { class: 'tag-new' }, [
              h('a-icon', { props: { type: 'thunderbolt' } }),
              h('span', '新')
            ]);
          }
          return h('span', { class: 'tag-empty' }, '—');
        }
      },
      {
        title: '状态',
        dataIndex: 'is_selected',
        key: 'status',
        width: 110,
        align: 'center',
        customRender: (text, record) => {
          const h = this.$createElement;
          return h('a-badge', {
            props: {
              status: record.is_selected ? 'error' : 'success',
              text: record.is_selected ? '已禁用' : '正常'
            }
          });
        }
      },
      {
        title: '操作',
        key: 'action',
        width: 100,
        align: 'center',
        fixed: 'right',
        customRender: (text, record) => {
          const h = this.$createElement;
          if (record.is_selected) {
            return h('a', {
              class: 'action-link action-enable',
              on: { click: () => this.toggleGame(record, false) }
            }, '取消禁用');
          }
          return h('a', {
            class: 'action-link action-disable',
            on: { click: () => this.toggleGame(record, true) }
          }, '禁用游戏');
        }
      }
    ];
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
    },
    hasActiveFilters() {
      return !!(
        this.filters.game_name ||
        this.filters.platform_id ||
        this.filters.cate_id ||
        this.filters.is_hot !== undefined ||
        this.filters.is_new !== undefined
      );
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

      // 过滤掉 undefined 和 null 的参数
      const params = {
        player_id: this.player_id,
        page: this.pagination.current,
        size: this.pagination.pageSize
      };

      // 只添加有值的筛选条件
      if (this.filters.game_name) params.game_name = this.filters.game_name;
      if (this.filters.platform_id !== undefined && this.filters.platform_id !== null) {
        params.platform_id = this.filters.platform_id;
      }
      if (this.filters.cate_id !== undefined && this.filters.cate_id !== null) {
        params.cate_id = this.filters.cate_id;
      }
      if (this.filters.is_hot !== undefined && this.filters.is_hot !== null) {
        params.is_hot = this.filters.is_hot;
      }
      if (this.filters.is_new !== undefined && this.filters.is_new !== null) {
        params.is_new = this.filters.is_new;
      }

      console.log('请求参数:', params);

      const promise = this.$request({
        url: 'ex-admin/addons-webman-controller-ChannelPlayerController/getPlayerGameListData',
        params: params
      });

      // ExAdmin的$request可能会reject成功的响应，所以我们在两个回调中都处理
      const handleResponse = (res) => {
        console.log('API响应:', res);
        if (res && res.status === 1 && res.data) {
          const data = res.data;
          this.gameList = data.list || [];
          this.pagination.total = data.total || 0;
          this.platforms = data.platforms || [];
          this.categories = data.categories || [];

          // 调试输出
          if (this.gameList.length > 0) {
            console.log('第一条数据:', this.gameList[0]);
            console.log('is_hot类型:', typeof this.gameList[0].is_hot, '值:', this.gameList[0].is_hot);
            console.log('is_new类型:', typeof this.gameList[0].is_new, '值:', this.gameList[0].is_new);
          }
          console.log('分类列表:', this.categories);

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

    // 重置筛选条件
    resetFilters() {
      this.filters = {
        game_name: undefined,
        platform_id: undefined,
        cate_id: undefined,
        is_hot: undefined,
        is_new: undefined
      };
      this.pagination.current = 1;
      this.loadGameList();
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

.filter-search {
  width: 220px;
  min-width: 180px;
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

/* 热门标签样式 */
.tag-hot {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: linear-gradient(135deg, #ff6b6b 0%, #ff4d4f 100%);
  color: #fff;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  box-shadow: 0 2px 6px rgba(255, 77, 79, 0.3);
  animation: pulse-hot 2s ease-in-out infinite;
}

.tag-hot .anticon {
  font-size: 14px;
  animation: flame 1.5s ease-in-out infinite;
}

@keyframes pulse-hot {
  0%, 100% {
    box-shadow: 0 2px 6px rgba(255, 77, 79, 0.3);
  }
  50% {
    box-shadow: 0 2px 12px rgba(255, 77, 79, 0.5);
  }
}

@keyframes flame {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}

/* 新游戏标签样式 */
.tag-new {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: linear-gradient(135deg, #ffa940 0%, #ff7a45 100%);
  color: #fff;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  box-shadow: 0 2px 6px rgba(255, 169, 64, 0.3);
  animation: pulse-new 2.5s ease-in-out infinite;
}

.tag-new .anticon {
  font-size: 14px;
  animation: sparkle 1.2s ease-in-out infinite;
}

@keyframes pulse-new {
  0%, 100% {
    box-shadow: 0 2px 6px rgba(255, 169, 64, 0.3);
  }
  50% {
    box-shadow: 0 2px 12px rgba(255, 169, 64, 0.5);
  }
}

@keyframes sparkle {
  0%, 100% {
    opacity: 1;
    transform: rotate(0deg);
  }
  50% {
    opacity: 0.7;
    transform: rotate(-15deg);
  }
}

/* 空标签样式 */
.tag-empty {
  color: #d9d9d9;
  font-size: 16px;
  font-weight: 300;
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
</style>

<!-- Ant Design 组件样式覆盖（不加scoped） -->
<style>
/* 表格样式优化 */
.player-game-list .ant-table {
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
}

.player-game-list .ant-table-thead > tr > th {
  background: #fafafa;
  font-weight: 600;
  color: #333;
  border-bottom: 2px solid #f0f0f0;
}

.player-game-list .ant-table-tbody > tr:hover > td {
  background: #f5f9ff;
}

.player-game-list .ant-table-tbody > tr > td {
  border-bottom: 1px solid #f5f5f5;
}

/* Badge 状态样式优化 */
.player-game-list .ant-badge-status-text {
  font-size: 13px;
  font-weight: 500;
  margin-left: 8px;
}

.player-game-list .ant-badge-status-dot {
  width: 8px;
  height: 8px;
}

.player-game-list .ant-badge-status-success {
  background-color: #52c41a;
  box-shadow: 0 0 0 3px rgba(82, 196, 26, 0.2);
}

.player-game-list .ant-badge-status-error {
  background-color: #ff4d4f;
  box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.2);
}

/* Tag 样式优化 */
.player-game-list .ant-tag {
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 12px;
  font-weight: 500;
  border: none;
}

/* 分页样式 */
.player-game-list .ant-pagination {
  margin-top: 16px;
}

/* 卡片样式 */
.player-game-list .ant-card {
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.player-game-list .ant-card-head {
  border-bottom: 1px solid #f0f0f0;
  background: #fff;
}

.player-game-list .ant-card-head-title {
  font-size: 16px;
  font-weight: 600;
  color: #333;
}

/* 输入框样式 */
.player-game-list .ant-input {
  border-radius: 4px;
}

.player-game-list .ant-input:focus {
  border-color: #40a9ff;
  box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.2);
}

.player-game-list .ant-input-search-icon {
  color: #1890ff;
}
</style>
