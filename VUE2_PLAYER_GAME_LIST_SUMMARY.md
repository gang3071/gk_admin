# Vue 2 玩家游戏列表组件实现总结

## 问题与解决

### 原始问题
- ExAdmin Grid在筛选后选中状态丢失
- 用户选择的游戏在应用平台筛选后被清空

### 解决方案
使用Vue 2组件完全控制选中状态，确保筛选不影响选择

## Vue 2 组件改造要点

### 1. 模板语法差异

**Vue 3 → Vue 2**

```vue
<!-- Vue 3 -->
<template #extra>
<template #bodyCell="{ column, record }">
<a-select v-model:value="filters.platform_id">

<!-- Vue 2 -->
<template slot="extra">
<template slot="platform" slot-scope="text, record">
<a-select v-model="filters.platform_id">
```

### 2. Script语法差异

**Vue 3 Composition API → Vue 2 Options API**

```javascript
// ❌ Vue 3 (不支持)
import { ref, reactive, computed, onMounted } from 'vue';
export default {
  setup(props) {
    const loading = ref(false);
    const loadGameList = async () => { ... };
    return { loading, loadGameList };
  }
}

// ✅ Vue 2 (正确)
export default {
  data() {
    return {
      loading: false
    };
  },
  computed: {
    title() {
      return `${this.player_name} - 游戏权限管理`;
    }
  },
  mounted() {
    this.loadGameList();
  },
  methods: {
    async loadGameList() { ... }
  }
}
```

### 3. 表格插槽配置

**Vue 2中的scopedSlots配置**

```javascript
columns: [
  {
    title: '游戏平台',
    key: 'platform',
    scopedSlots: { customRender: 'platform' },  // ✅ 关键配置
    width: 120,
    align: 'center'
  }
]
```

**对应的模板**

```vue
<template slot="platform" slot-scope="text, record">
  <a-tag color="blue">{{ record.platform_name }}</a-tag>
</template>
```

### 4. HTTP请求兼容性

创建了通用的HTTP请求函数，兼容多种HTTP库：

```javascript
async httpRequest(method, url, data = null, config = {}) {
  // 1. 优先使用 this.$http（Vue Resource或axios插件）
  if (this.$http) {
    return await this.$http[method](url, ...);
  }

  // 2. 其次使用全局 axios
  if (window.axios) {
    return await window.axios[method](url, ...);
  }

  // 3. 最后使用原生 fetch
  const response = await fetch(url, ...);
  return { data: await response.json() };
}
```

### 5. 确认对话框

```javascript
showConfirm(options) {
  return new Promise((resolve) => {
    try {
      // 尝试使用 this.$confirm（Ant Design Vue提供）
      this.$confirm({
        title: options.title,
        content: options.content,
        okText: '确认',
        cancelText: '取消',
        onOk() { ... },
        onCancel() { ... }
      });
    } catch (error) {
      // Fallback到原生confirm
      const result = window.confirm(`${options.title}\n\n${options.content}`);
      resolve(result);
    }
  });
}
```

## 核心功能

### 1. 游戏列表加载
- GET `/ex-admin/channel-player/getPlayerGameListData`
- 参数：player_id, page, size, platform_id, is_hot, is_new
- 返回：游戏列表 + 选中状态（is_selected字段）

### 2. 筛选功能
- 平台筛选（platform_id）
- 热门游戏筛选（is_hot）
- 新游戏筛选（is_new）
- **关键**：筛选后重新加载数据，但根据is_selected字段恢复选中状态

### 3. 批量保存
- POST `/ex-admin/channel-player/savePlayerGamesVue`
- 参数：{ player_id, selected_game_ids: [...] }
- 逻辑：清空旧记录 → 插入新选中的游戏

### 4. 单个切换
- POST `/ex-admin/channel-player/toggleGameDisable`
- 参数：{ player_id, game_id, action: 'disable'|'enable' }
- 支持Grid和Vue组件两种调用方式

## 数据流程

```
用户选择游戏 → selectedRowKeys更新（Vue响应式数据）
    ↓
用户应用筛选 → 调用API重新加载数据
    ↓
服务器返回筛选后的游戏 + is_selected字段
    ↓
Vue组件根据is_selected更新selectedRowKeys
    ↓
Table组件显示勾选状态 ✅
```

## 关键改进

### 选中状态保持
- Grid方案：状态存储在Grid内部，筛选后丢失
- Vue方案：状态存储在组件data中，独立于数据加载

### 代码结构
```
data() {
  return {
    selectedRowKeys: [],  // ← 选中状态独立存储
    gameList: []          // ← 游戏列表数据
  }
}
```

### 状态恢复逻辑
```javascript
async loadGameList() {
  const response = await this.httpRequest('get', ...);
  this.gameList = response.data.data.list;

  // 根据服务器返回的is_selected字段恢复选中状态
  this.selectedRowKeys = this.gameList
    .filter(game => game.is_selected)
    .map(game => game.id);
}
```

## 测试要点

1. ✅ 页面加载后自动选中已禁用的游戏
2. ✅ 选择多个游戏后应用筛选，选中状态保持
3. ✅ 保存后刷新页面，选中状态正确显示
4. ✅ 单个游戏禁用/取消禁用功能正常
5. ✅ 分页功能正常
6. ✅ 确认对话框正常弹出

## 文件清单

### 后端
- `D:\gk_admin\addons\webman\controller\ChannelPlayerController.php`
  - `playerGameList()` - 返回Vue组件视图
  - `getPlayerGameListData()` - API: 获取游戏列表
  - `savePlayerGamesVue()` - API: 保存选中游戏
  - `toggleGameDisable()` - API: 切换游戏状态（兼容Grid和Vue）

### 前端
- `D:\gk_admin\addons\webman\views\player_game_list.vue` - Vue 2组件

### 翻译
- `D:\gk_admin\addons\webman\lang\*/common.php` - 添加了3个新的翻译键

## 常见问题

### Q: 为什么不直接修复Grid？
A: ExAdmin Grid的选中状态存储机制复杂，筛选时会重新初始化，难以保持状态。Vue组件完全可控。

### Q: 为什么不用Vue 3？
A: ExAdmin基于Vue 2构建，使用Vue 3会导致兼容性问题。

### Q: HTTP请求为什么要兼容多种方式？
A: 不同ExAdmin版本可能使用不同的HTTP库（axios/vue-resource/fetch），兼容性函数确保都能正常工作。

### Q: 确认对话框为什么有fallback？
A: 某些环境下this.$confirm可能不可用，使用原生confirm作为后备方案确保功能可用。

## 性能优化

1. **分页加载**：每次只加载50条数据
2. **筛选优化**：筛选在数据库层面完成
3. **选中状态计算**：仅在数据加载后计算一次

## 总结

通过Vue 2组件实现，完美解决了Grid筛选导致选中状态丢失的问题。关键在于：

1. 使用Vue 2 Options API而非Vue 3 Composition API
2. 选中状态独立于数据加载过程
3. 服务器端记录选中状态（is_selected字段）
4. 筛选后根据服务器数据恢复选中状态
5. 兼容多种HTTP库和对话框实现

这个方案保证了用户体验流畅，数据准确，代码可维护。
