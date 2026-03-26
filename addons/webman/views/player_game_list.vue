<template>
  <div class="player-game-list">
    <a-card :loading="loading" :title="title">
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
              {{ platform.name }}
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
        :scroll="{ x: 1200 }"
        bordered
        row-key="id"
        @change="handleTableChange"
      >
        <template slot="platform" slot-scope="text, record">
          <a-tag color="blue">{{ record.platform_name }}</a-tag>
        </template>

        <template slot="game_name" slot-scope="text, record">
          <div style="display: flex; align-items: center">
            <a-avatar
              v-if="record.picture"
              :size="50"
              :src="record.picture"
              shape="square"
              style="margin-right: 8px"
            />
            <span>{{ record.name }}</span>
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
          <a-button
            v-if="record.is_selected"
            size="small"
            type="default"
            @click="toggleGame(record, false)"
          >
            取消禁用
          </a-button>
          <a-button
            v-else
            size="small"
            type="danger"
            @click="toggleGame(record, true)"
          >
            禁用游戏
          </a-button>
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
          title: '游戏平台',
          key: 'platform',
          scopedSlots: { customRender: 'platform' },
          width: 120,
          align: 'center'
        },
        {
          title: '游戏名称',
          key: 'game_name',
          scopedSlots: { customRender: 'game_name' },
          width: 200
        },
        {
          title: '游戏分类',
          key: 'category',
          scopedSlots: { customRender: 'category' },
          width: 100,
          align: 'center'
        },
        {
          title: '热门',
          key: 'is_hot',
          scopedSlots: { customRender: 'is_hot' },
          width: 80,
          align: 'center'
        },
        {
          title: '新游戏',
          key: 'is_new',
          scopedSlots: { customRender: 'is_new' },
          width: 80,
          align: 'center'
        },
        {
          title: '状态',
          key: 'status',
          scopedSlots: { customRender: 'status' },
          width: 100,
          align: 'center'
        },
        {
          title: '操作',
          key: 'action',
          scopedSlots: { customRender: 'action' },
          width: 120,
          align: 'center'
        }
      ]
    };
  },
  computed: {
    title() {
      return `${this.player_name} - 游戏权限管理`;
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
      this.$request({
        url: 'ex-admin/addons-webman-controller-ChannelPlayerController/getPlayerGameListData',
        params: {
          player_id: this.player_id,
          page: this.pagination.current,
          size: this.pagination.pageSize,
          ...this.filters
        }
      }).then(res => {
        try {
          console.log('API响应:', res);

          if (res.status === 1) {
            const data = res.data;
            console.log('游戏数据:', data);

            this.gameList = data.list || [];
            this.pagination.total = data.total || 0;
            this.platforms = data.platforms || [];

            console.log('游戏列表:', this.gameList.length, '条');

            // 更新选中的行（已禁用的游戏）
            this.selectedRowKeys = this.gameList
              .filter(game => game && game.is_selected)
              .map(game => game.id);
            console.log('选中的游戏ID:', this.selectedRowKeys);
            console.log('加载完成！');
          } else {
            console.error('API返回失败:', res.message);
          }
        } catch (e) {
          console.error('处理响应数据时出错:', e);
          console.error('错误堆栈:', e.stack);
          throw e; // 重新抛出以便外层catch捕获
        }
      }).catch(error => {
        console.error('请求失败或处理出错:', error);
        console.error('错误类型:', typeof error);
        console.error('错误对象:', error);
      }).finally(() => {
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
          this.$request({
            url: 'ex-admin/addons-webman-controller-ChannelPlayerController/toggleGameDisable',
            method: 'post',
            data: {
              player_id: this.player_id,
              game_id: record.id,
              action: action
            }
          }).then(res => {
            if (res.status === 1) {
              if (this.$message && this.$message.success) {
                this.$message.success(res.message || `${actionText}成功`);
              }
              this.loadGameList();
            } else {
              if (this.$message && this.$message.error) {
                this.$message.error(res.message || `${actionText}失败`);
              }
            }
          }).catch(error => {
            console.error(`${actionText}游戏失败:`, error);
            if (this.$message && this.$message.error) {
              this.$message.error(`${actionText}游戏失败`);
            }
          });
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
          this.$request({
            url: 'ex-admin/addons-webman-controller-ChannelPlayerController/savePlayerGamesVue',
            method: 'post',
            data: {
              player_id: this.player_id,
              selected_game_ids: this.selectedRowKeys
            }
          }).then(res => {
            if (res.status === 1) {
              if (this.$message && this.$message.success) {
                this.$message.success(res.message || '保存成功');
              }
              this.loadGameList();
            } else {
              if (this.$message && this.$message.error) {
                this.$message.error(res.message || '保存失败');
              }
            }
          }).catch(error => {
            console.error('保存失败:', error);
            if (this.$message && this.$message.error) {
              this.$message.error('保存失败');
            }
          }).finally(() => {
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
