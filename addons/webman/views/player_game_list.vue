<template>
  <div class="player-game-list">
    <a-card :loading="loading" :title="cardTitle">
      <!-- 筛选器 -->
      <template slot="extra">
        <div style="display: flex; gap: 8px; align-items: center;">
          <a-select
            v-model="filters.platform_id"
            allowClear
            placeholder="选择平台"
            style="width: 200px"
            @change="loadGameList"
          >
            <a-select-option v-for="platform in platforms" :key="platform.id" :value="platform.id">
              <div style="display: flex; align-items: center; gap: 6px;">
                <img
                  v-if="platform.logo"
                  :alt="platform.name"
                  :src="platform.logo"
                  style="width: 20px; height: 20px; object-fit: contain;"
                />
                <span>{{ platform.name }}</span>
              </div>
            </a-select-option>
          </a-select>

          <a-select
            v-model="filters.is_hot"
            allowClear
            placeholder="热门游戏"
            style="width: 120px"
            @change="loadGameList"
          >
            <a-select-option :value="1">热门游戏</a-select-option>
            <a-select-option :value="0">普通游戏</a-select-option>
          </a-select>

          <a-select
            v-model="filters.is_new"
            allowClear
            placeholder="新游戏"
            style="width: 120px"
            @change="loadGameList"
          >
            <a-select-option :value="1">新游戏</a-select-option>
            <a-select-option :value="0">旧游戏</a-select-option>
          </a-select>

          <a-button :loading="saving" icon="save" type="primary" @click="saveSelectedGames">
            保存选中游戏
          </a-button>
        </div>
      </template>

      <!-- 游戏表格 -->
      <a-table
        :columns="columns"
        :data-source="gameList"
        :loading="loading"
        :pagination="pagination"
        :row-selection="rowSelection"
        :scroll="{ x: 'max-content', y: 550 }"
        bordered
        size="middle"
        row-key="id"
        @change="handleTableChange"
      >
        <template slot="platform" slot-scope="text, record">
          <div style="display: flex; align-items: center; gap: 6px; justify-content: center;">
            <img
              v-if="record.platform_logo"
              :alt="record.platform_name"
              :src="record.platform_logo"
              style="width: 24px; height: 24px; object-fit: contain;"
            />
            <a-tag color="blue">{{ record.platform_name }}</a-tag>
          </div>
        </template>

        <template slot="game_name" slot-scope="text, record">
          <div style="display: flex; align-items: center; gap: 8px;">
            <img
              v-if="record.picture"
              :src="record.picture"
              :alt="record.name"
              style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
              @error="handleImageError"
            />
            <div v-else style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <span style="color: #999; font-size: 11px;">无图</span>
            </div>
            <span style="flex: 1; word-break: break-word;">{{ record.name }}</span>
          </div>
        </template>

        <template slot="category" slot-scope="text, record">
          <a-tag color="green">{{ record.category_name }}</a-tag>
        </template>

        <template slot="is_hot" slot-scope="text, record">
          <a-tag v-if="record.is_hot === 1" color="red">热门</a-tag>
        </template>

        <template slot="is_new" slot-scope="text, record">
          <a-tag v-if="record.is_new === 1" color="orange">新游戏</a-tag>
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
            style="color: #52c41a; cursor: pointer;"
          >
            取消
          </a>
          <a
            v-else
            @click="toggleGame(record, true)"
            style="color: #ff4d4f; cursor: pointer;"
          >
            禁用
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
        showTotal: (total) => `共 ${total} 条`
      },
      columns: [
        {
          title: 'ID',
          dataIndex: 'id',
          key: 'id',
          width: 80,
          align: 'center'
        },
        {
          title: '游戏名称',
          dataIndex: 'name',
          key: 'game_name',
          scopedSlots: { customRender: 'game_name' },
          width: 280,
          fixed: 'left'
        },
        {
          title: '游戏平台',
          dataIndex: 'platform_name',
          key: 'platform',
          scopedSlots: { customRender: 'platform' },
          width: 160,
          align: 'center'
        },
        {
          title: '游戏分类',
          dataIndex: 'category_name',
          key: 'category',
          scopedSlots: { customRender: 'category' },
          width: 100,
          align: 'center'
        },
        {
          title: '热门',
          dataIndex: 'is_hot',
          key: 'is_hot',
          scopedSlots: { customRender: 'is_hot' },
          width: 80,
          align: 'center'
        },
        {
          title: '新游戏',
          dataIndex: 'is_new',
          key: 'is_new',
          scopedSlots: { customRender: 'is_new' },
          width: 80,
          align: 'center'
        },
        {
          title: '状态',
          dataIndex: 'is_selected',
          key: 'status',
          scopedSlots: { customRender: 'status' },
          width: 90,
          align: 'center'
        },
        {
          title: '操作',
          key: 'action',
          scopedSlots: { customRender: 'action' },
          width: 120,
          align: 'center',
          fixed: 'right'
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
}
</style>
