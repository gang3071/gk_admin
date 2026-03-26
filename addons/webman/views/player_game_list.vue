<template>
  <div class="player-game-list">
    <a-card :loading="loading" :title="title">
      <!-- 筛选器 -->
      <template #extra>
        <a-space>
          <a-select
            v-model:value="filters.platform_id"
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
            v-model:value="filters.is_hot"
            allowClear
            placeholder="热门游戏"
            style="width: 120px"
            @change="loadGameList"
          >
            <a-select-option :value="1">热门游戏</a-select-option>
            <a-select-option :value="0">普通游戏</a-select-option>
          </a-select>

          <a-select
            v-model:value="filters.is_new"
            allowClear
            placeholder="新游戏"
            style="width: 120px"
            @change="loadGameList"
          >
            <a-select-option :value="1">新游戏</a-select-option>
            <a-select-option :value="0">旧游戏</a-select-option>
          </a-select>

          <a-button :icon="h(SaveOutlined)" :loading="saving" type="primary" @click="saveSelectedGames">
            保存选中游戏
          </a-button>
        </a-space>
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
        <template #bodyCell="{ column, record }">
          <!-- 平台列 -->
          <template v-if="column.key === 'platform'">
            <a-tag color="blue">{{ record.platform_name }}</a-tag>
          </template>

          <!-- 游戏名称列 -->
          <template v-else-if="column.key === 'game_name'">
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

          <!-- 游戏分类列 -->
          <template v-else-if="column.key === 'category'">
            <a-tag color="green">{{ record.category_name }}</a-tag>
          </template>

          <!-- 热门标签 -->
          <template v-else-if="column.key === 'is_hot'">
            <a-tag v-if="record.is_hot === 1" color="red">热门</a-tag>
          </template>

          <!-- 新游戏标签 -->
          <template v-else-if="column.key === 'is_new'">
            <a-tag v-if="record.is_new === 1" color="orange">新游戏</a-tag>
          </template>

          <!-- 状态列 -->
          <template v-else-if="column.key === 'status'">
            <a-tag :color="record.is_selected ? 'red' : 'green'">
              {{ record.is_selected ? '已禁用' : '正常' }}
            </a-tag>
          </template>

          <!-- 操作列 -->
          <template v-else-if="column.key === 'action'">
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
              danger
              size="small"
              type="primary"
              @click="toggleGame(record, true)"
            >
              禁用游戏
            </a-button>
          </template>
        </template>
      </a-table>
    </a-card>
  </div>
</template>

<script>
import {computed, createVNode, h, onMounted, reactive, ref} from 'vue';
import {message, Modal} from 'ant-design-vue';
import {ExclamationCircleOutlined, SaveOutlined} from '@ant-design/icons-vue';
import axios from 'axios';

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
  setup(props) {
    const loading = ref(false);
    const saving = ref(false);
    const gameList = ref([]);
    const platforms = ref([]);
    const selectedRowKeys = ref([]);
    const title = computed(() => `${props.player_name} - 游戏权限管理`);

    // 确认对话框辅助函数（兼容ExAdmin环境）
    const showConfirm = (options) => {
      return new Promise((resolve, reject) => {
        try {
          Modal.confirm({
            icon: createVNode(ExclamationCircleOutlined),
            okText: '确认',
            cancelText: '取消',
            ...options,
            onOk: () => {
              resolve(true);
              if (options.onOk) {
                return options.onOk();
              }
            },
            onCancel: () => {
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
    };

    const filters = reactive({
      platform_id: undefined,
      is_hot: undefined,
      is_new: undefined
    });

    const pagination = reactive({
      current: 1,
      pageSize: 50,
      total: 0,
      showSizeChanger: true,
      showQuickJumper: true,
      showTotal: (total) => `共 ${total} 条`
    });

    const columns = [
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
        width: 120,
        align: 'center'
      },
      {
        title: '游戏名称',
        key: 'game_name',
        width: 200
      },
      {
        title: '游戏分类',
        key: 'category',
        width: 100,
        align: 'center'
      },
      {
        title: '热门',
        key: 'is_hot',
        width: 80,
        align: 'center'
      },
      {
        title: '新游戏',
        key: 'is_new',
        width: 80,
        align: 'center'
      },
      {
        title: '状态',
        key: 'status',
        width: 100,
        align: 'center'
      },
      {
        title: '操作',
        key: 'action',
        width: 120,
        align: 'center'
      }
    ];

    const rowSelection = computed(() => ({
      selectedRowKeys: selectedRowKeys.value,
      onChange: (keys) => {
        selectedRowKeys.value = keys;
      },
      getCheckboxProps: (record) => ({
        // 可以添加一些禁用逻辑
      })
    }));

    // 加载游戏列表
    const loadGameList = async () => {
      loading.value = true;
      try {
        const response = await axios.get('/ex-admin/channel-player/getPlayerGameListData', {
          params: {
            player_id: props.player_id,
            page: pagination.current,
            size: pagination.pageSize,
            ...filters
          },
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        });

        if (response.data.status === 1) {
          const data = response.data.data;
          gameList.value = data.list || [];
          pagination.total = data.total || 0;
          platforms.value = data.platforms || [];

          // 更新选中的行（已禁用的游戏）
          selectedRowKeys.value = gameList.value
            .filter(game => game.is_selected)
            .map(game => game.id);
        } else {
          message.error(response.data.message || '加载失败');
        }
      } catch (error) {
        console.error('加载游戏列表失败:', error);
        message.error('加载游戏列表失败');
      } finally {
        loading.value = false;
      }
    };

    // 表格变化处理
    const handleTableChange = (pag) => {
      pagination.current = pag.current;
      pagination.pageSize = pag.pageSize;
      loadGameList();
    };

    // 单个游戏切换
    const toggleGame = (record, disable) => {
      const action = disable ? 'disable' : 'enable';
      const actionText = disable ? '禁用' : '取消禁用';

      showConfirm({
        title: `确认${actionText}游戏`,
        content: `确定要${actionText}游戏"${record.name}"吗？`,
        onOk: async () => {
          try {
            const response = await axios.post('/ex-admin/channel-player/toggleGameDisable', {
              player_id: props.player_id,
              game_id: record.id,
              action: action
            }, {
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
              }
            });

            if (response.data.status === 1) {
              message.success(response.data.message || `${actionText}成功`);
              await loadGameList();
            } else {
              message.error(response.data.message || `${actionText}失败`);
            }
          } catch (error) {
            console.error(`${actionText}游戏失败:`, error);
            message.error(`${actionText}游戏失败`);
          }
        }
      });
    };

    // 批量保存选中的游戏
    const saveSelectedGames = () => {
      if (selectedRowKeys.value.length === 0) {
        message.warning('请至少选择一个游戏');
        return;
      }

      showConfirm({
        title: '确认保存',
        content: `确定要将选中的 ${selectedRowKeys.value.length} 个游戏设为禁用状态吗？`,
        onOk: async () => {
          saving.value = true;
          try {
            const response = await axios.post('/ex-admin/channel-player/savePlayerGamesVue', {
              player_id: props.player_id,
              selected_game_ids: selectedRowKeys.value
            }, {
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
              }
            });

            if (response.data.status === 1) {
              message.success(response.data.message || '保存成功');
              await loadGameList();
            } else {
              message.error(response.data.message || '保存失败');
            }
          } catch (error) {
            console.error('保存失败:', error);
            message.error('保存失败');
          } finally {
            saving.value = false;
          }
        }
      });
    };

    onMounted(() => {
      loadGameList();
    });

    return {
      h,
      SaveOutlined,
      loading,
      saving,
      gameList,
      platforms,
      selectedRowKeys,
      filters,
      pagination,
      columns,
      rowSelection,
      title,
      loadGameList,
      handleTableChange,
      toggleGame,
      saveSelectedGames
    };
  }
};
</script>

<style scoped>
.player-game-list {
  padding: 16px;
}
</style>
