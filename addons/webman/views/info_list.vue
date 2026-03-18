<template>
  <a-card style="width: 100%">
    <a-tabs v-model:activeKey="activeKey" type="card" @change="tabsChange">
      <a-tab-pane key="1" tab="斯洛">
        <div class="tools">
          <a-row style="width: 100%">
            <a-col :span="12">
              <div class="left">
                <a-input-search
                    v-model:value="quick_search_slot"
                    :enter-button=message.search
                    :placeholder=message.enter_keywords
                    allowClear="true"
                    class="quickSearch"
                    size="default"
                    @search="onSearch"
                    @clear="() => onSearch('')"
                >
                  <template #prefix>
                    <search-outlined fill="currentColor"/>
                  </template>
                </a-input-search>
              </div>
            </a-col>
            <a-col :span="12">
              <div class="right">
                <a-button shape="circle" size="small" @click="toggleSearch">
                  <template #icon>
                    <SearchOutlined/>
                  </template>
                </a-button>
                <a-button shape="circle" size="small" @click="refreshTable">
                  <template #icon>
                    <reload-outlined/>
                  </template>
                </a-button>
                <a-dropdown trigger="click">
                  <template #overlay>
                    <a-menu>
                      <a-menu-item v-for="col in columns" :key="col.dataIndex">
                        <a-checkbox
                            v-model="col.visible"
                            :checked="col.visible"
                            @change="handleColumnChange(col)"
                        >
                          {{ col.title }}
                        </a-checkbox>
                      </a-menu-item>
                    </a-menu>
                  </template>
                  <a-button shape="circle" size="small" @click="toggleColumnVisibility">
                    <template #icon>
                      <appstore-filled/>
                    </template>
                  </a-button>
                </a-dropdown>
              </div>
            </a-col>
          </a-row>
          <a-row style="margin-top:10px;width: 100%">
            <a-col :span="24">
              <div v-if="showSearch" class="filter">
                <a-form
                    ref="formRef"
                    :model="formState"
                    class="ant-advanced-search-form"
                    layout="inline"
                    name="advanced_search"
                    @finish="onFinish"
                >
                  <a-form-item name="name">
                    <a-input v-model:value="formState.name" :placeholder="message.machine_name" allowClear="true"/>
                  </a-form-item>
                  <a-form-item name="code">
                    <a-input v-model:value="formState.code" :placeholder="message.machine_code" allowClear="true"/>
                  </a-form-item>
                  <a-form-item name="cate_id">
                    <a-tree-select
                        v-model:value="formState.cate_id"
                        :fieldNames="{children:'children', label:'name', value: 'id'}"
                        :height="233"
                        :max-tag-count="10"
                        :placeholder=message.machine_cate
                        :tree-data="options"
                        style="width: 100%;min-width:200px"
                        tree-checkable
                        tree-default-expand-all
                        tree-node-filter-prop="name"
                        @change="onCateChange"
                    >
                    </a-tree-select>
                  </a-form-item>
                  <a-form-item>
                    <a-button html-type="submit" type="primary">
                      <template #icon>
                        <SearchOutlined/>
                      </template>
                      {{ message.search }}
                    </a-button>
                    <a-button style="margin: 0 8px" @click="onReset"> {{
                        message.reset
                      }}
                    </a-button>
                  </a-form-item>
                </a-form>
              </div>
            </a-col>
          </a-row>
        </div>
        <a-table
            ref="myTable"
            :columns="visibleColumns"
            :data-source="dataSource"
            :loading="loading"
            :pagination="pagination"
            :row-key="record => record.id"
            :scroll="{ x: 1300, y: 1000 }"
            bordered
            @change="handleTableChange"
        >
          <template #bodyCell="{ column, text, record }">
            <template v-if="column.key === 'auto'">
        <span>
          <a-tag
              :color="text === 0 ? 'geekblue' : 'green'"
          >
            {{ text === 0 ? message.close : message.open }}
          </a-tag>
        </span>
            </template>
            <template v-else-if="column.key === 'move_point'">
        <span>
          <a-tag
              :color="text === 0 ? 'geekblue' : 'green'"
          >
            {{ text === 0 ? message.close : message.open }}
          </a-tag>
        </span>
            </template>
            <template v-else-if="column.key === 'player_phone'">
              <div>
                <a-avatar :src="record.player_avatar" size="large">
                </a-avatar>
              </div>
              <div>{{ record.player_phone }}</div>
              <div>{{ record.player_name }}</div>
            </template>
            <template v-else-if="column.key === 'code'">
              {{ text }}
              <a-button shape="circle" size="small" type="dashed" @click="(key) => play(key, record)">
                <template #icon>
                  <PlayCircleOutlined/>
                </template>
              </a-button>
            </template>
            <template v-else-if="column.key === 'reward_status'">
        <span>
          <a-tag
              :color="text === 0 ? 'geekblue' : 'green'"
          >
            {{ text === 0 ? message.not_winning : message.winning }}
            <loading-outlined v-if="text === 1"/>
          </a-tag>
        </span>
            </template>
            <template v-else-if="column.key === 'keep_seconds'">
        <span>
          {{ text }}
          <a-tag
              v-if="record.keeping === 1"
              :color="'red'">
            {{ message.keeping }}
            <loading-outlined/>
          </a-tag>
        </span>
            </template>
            <template v-else-if="column.key === 'action'">
              <a-dropdown>
                <template #overlay>
                  <a-menu @click="(key) => handleMenuClick(key, record)">
                    <a-menu-item key="1">
                      <StopFilled/>
                      {{ message.kick_out }}
                    </a-menu-item>
                    <a-menu-item key="2">
                      <StopOutlined/>
                      {{ message.forced_kick }}
                    </a-menu-item>
                    <a-menu-item key="3">
                      <DashboardOutlined/>
                      {{ message.keep_time_change }}
                    </a-menu-item>
                  </a-menu>
                </template>
                <a-button>
                  {{ message.action }}
                  <DownOutlined/>
                </a-button>
              </a-dropdown>
            </template>
          </template>
        </a-table>
      </a-tab-pane>
      <a-tab-pane key="2" tab="钢珠">
        <div class="tools">
          <a-row style="width: 100%">
            <a-col :span="12">
              <div class="left">
                <a-input-search
                    v-model:value="quick_search_jackpot"
                    :enter-button=message.search
                    :placeholder=message.enter_keywords
                    allowClear="true"
                    class="quickSearch"
                    size="default"
                    @search="onSearch"
                    @clear="() => onSearch('')"
                >
                  <template #prefix>
                    <search-outlined fill="currentColor"/>
                  </template>
                </a-input-search>
              </div>
            </a-col>
            <a-col :span="12">
              <div class="right">
                <a-button shape="circle" size="small" @click="toggleSearch">
                  <template #icon>
                    <SearchOutlined/>
                  </template>
                </a-button>
                <a-button shape="circle" size="small" @click="refreshTable">
                  <template #icon>
                    <reload-outlined/>
                  </template>
                </a-button>
                <a-dropdown trigger="click">
                  <template #overlay>
                    <a-menu>
                      <a-menu-item v-for="col in columns_jackpot" :key="col.dataIndex">
                        <a-checkbox
                            v-model="col.visible"
                            :checked="col.visible"
                            @change="handleColumnChange(col)"
                        >
                          {{ col.title }}
                        </a-checkbox>
                      </a-menu-item>
                    </a-menu>
                  </template>
                  <a-button shape="circle" size="small" @click="toggleColumnVisibility">
                    <template #icon>
                      <appstore-filled/>
                    </template>
                  </a-button>
                </a-dropdown>
              </div>
            </a-col>
          </a-row>
          <a-row style="margin-top:10px;width: 100%">
            <a-col :span="24">
              <div v-if="showSearch" class="filter">
                <a-form
                    ref="formRef"
                    :model="formState"
                    class="ant-advanced-search-form"
                    layout="inline"
                    name="advanced_search"
                    @finish="onFinish"
                >
                  <a-form-item name="name">
                    <a-input v-model:value="formState.name" :placeholder="message.machine_name" allowClear="true"/>
                  </a-form-item>
                  <a-form-item name="code">
                    <a-input v-model:value="formState.code" :placeholder="message.machine_code" allowClear="true"/>
                  </a-form-item>
                  <a-form-item name="cate_id">
                    <a-tree-select
                        v-model:value="formState.cate_id"
                        :fieldNames="{children:'children', label:'name', value: 'id'}"
                        :height="233"
                        :max-tag-count="10"
                        :placeholder=message.machine_cate
                        :tree-data="options"
                        style="width: 100%;min-width:200px"
                        tree-checkable
                        tree-default-expand-all
                        tree-node-filter-prop="name"
                        @change="onCateChange"
                    >
                    </a-tree-select>
                  </a-form-item>
                  <a-form-item>
                    <a-button html-type="submit" type="primary">
                      <template #icon>
                        <SearchOutlined/>
                      </template>
                      {{ message.search }}
                    </a-button>
                    <a-button style="margin: 0 8px" @click="onReset"> {{
                        message.reset
                      }}
                    </a-button>
                  </a-form-item>
                </a-form>
              </div>
            </a-col>
          </a-row>
        </div>
        <a-table
            ref="myJackpotTable"
            :columns="visibleColumns"
            :data-source="dataSource"
            :loading="loading"
            :pagination="pagination"
            :row-key="record => record.id"
            :scroll="{ x: 1300, y: 1000 }"
            bordered
            @change="handleTableChange"
        >
          <template #bodyCell="{ column, text, record }">
            <template v-if="column.key === 'auto'">
        <span>
          <a-tag
              :color="text === 0 ? 'geekblue' : 'green'"
          >
             {{ text === 0 ? message.close : message.open }}
          </a-tag>
        </span>
            </template>
            <template v-else-if="column.key === 'push_auto'">
        <span>
          <a-tag
              :color="text === 0 ? 'geekblue' : 'green'"
          >
             {{ text === 0 ? message.close : message.open }}
          </a-tag>
        </span>
            </template>
            <template v-else-if="column.key === 'player_phone'">
              <div>
                <a-avatar :src="record.player_avatar" size="large">
                </a-avatar>
              </div>
              <div>{{ record.player_phone }}</div>
              <div>{{ record.player_name }}</div>
            </template>
            <template v-else-if="column.key === 'code'">
              {{ text }}
              <a-button shape="circle" size="small" type="dashed" @click="(key) => play(key, record)">
                <template #icon>
                  <PlayCircleOutlined/>
                </template>
              </a-button>
            </template>
            <template v-else-if="column.key === 'reward_status'">
        <span>
          <a-tag
              :color="text === 0 ? 'geekblue' : 'green'"
          >
            {{ text === 0 ? message.not_winning : message.winning }}
            <loading-outlined v-if="text === 1"/>
          </a-tag>
        </span>
            </template>
            <template v-else-if="column.key === 'keep_seconds'">
        <span>
          {{ text }}
          <a-tag
              v-if="record.keeping === 1"
              :color="'red'">
            {{ message.keeping }}
            <loading-outlined/>
          </a-tag>
        </span>
            </template>
            <template v-else-if="column.key === 'action'">
              <a-dropdown>
                <template #overlay>
                  <a-menu @click="(key) => handleMenuClick(key, record)">
                    <a-menu-item key="1">
                      <StopFilled/>
                      {{ message.kick_out }}
                    </a-menu-item>
                    <a-menu-item key="2">
                      <StopOutlined/>
                      {{ message.forced_kick }}
                    </a-menu-item>
                    <a-menu-item key="3">
                      <DashboardOutlined/>
                      {{ message.keep_time_change }}
                    </a-menu-item>
                  </a-menu>
                </template>
                <a-button>
                  {{ message.action }}
                  <DownOutlined/>
                </a-button>
              </a-dropdown>
            </template>
          </template>
        </a-table>
      </a-tab-pane>
    </a-tabs>
    <a-modal
        :title=play_title
        :visible="is_show"
        maskClosable
        width="600px"
        @cancel="closePlay"
    >
      <template #footer>
        <a-button shape="circle" size="small" @click="showActionDrawer">
          <template #icon>
            <interaction-outlined/>
          </template>
        </a-button>
        <a-button shape="circle" size="small" @click="isActive = !isActive">
          <template #icon>
            <sync-outlined/>
          </template>
        </a-button>
        <a-button shape="circle" size="small" @click="showDrawer">
          <template #icon>
            <play-circle-outlined/>
          </template>
        </a-button>
      </template>
      <div :class="{ animate_left:is_move }">
        <a-spin :spinning="spinning" wrapperClassName="iframe_video">
          <iframe
              id="my-iframe"
              :key="refreshKey"
              ref="media" :src="iframe_src" allowfullscreen class="iframe_box"
              frameborder="0" sandbox='allow-scripts allow-same-origin allow-popups'
              v-bind:class="{ active_play: isActive }"
              @load="handleIframeLoad"></iframe>
        </a-spin>
      </div>
      <a-drawer
          :closable="false"
          :get-container="false"
          :maskStyle="{opacity:0}"
          :style="{ position: 'absolute' }"
          :title="play_address"
          :visible="action_visible"
          placement="right"
          width="44%"
          @close="onClose"
      >
        <a-button v-for="(item) in action_list" :key="item.key" shape="round" size="small"
                  style="margin: 6px"
                  type="dashed" @click="handleActionClick(item.key)">
          <template #icon>
            <tool-outlined/>
          </template>
          {{ item.action }}
        </a-button>
      </a-drawer>
      <a-drawer
          :closable="false"
          :get-container="false"
          :maskStyle="{opacity:0}"
          :style="{ position: 'absolute' }"
          :title="play_address"
          :visible="visible"
          placement="right"
          width="44%"
          @close="onClose"
      >
        <a-list :data-source="src_list" item-layout="horizontal">
          <template #renderItem="{ item,index }">
            <a-list-item>
              <a-list-item-meta :description="item.desc">
                <template #title>
                  <a :class="index === typeSelected ?'active_media':''" href="javascript:void(0);"
                     @click="changeMedia(item.src, index)">{{ item.title }}</a>
                </template>
                <template #avatar>
                  <video-camera-add-outlined/>
                </template>
              </a-list-item-meta>
            </a-list-item>
          </template>
        </a-list>
      </a-drawer>
      <div>
        <a-modal v-model:visible="open_visible" :title="message.open_any_point" destroyOnClose="true"
                 maskClosable="true"
                 width="300px" @ok="openAnyPoint">
          <a-input-number v-model:value="open_any_point_value" :max="5000" :min="0" :precision="0" :step="1"
                          addon-after="point" addon-before="+"></a-input-number>
        </a-modal>
      </div>
    </a-modal>
    <a-modal :title="message.keep_time_title" :visible="is_open" maskClosable width="500px"
             @cancel="closeKeepTimeChange" @ok="keepTimeChange">
      <a-descriptions
          :column="{ xxl: 4, xl: 3, lg: 3, md: 3, sm: 2, xs: 1 }"
          bordered
      >
        <a-descriptions-item :label="message.keep_seconds">
          {{ check_keep_seconds }}
        </a-descriptions-item>
        <a-descriptions-item :label="message.keep_status">
          <a-tag
              v-if="check_keep_status === 1"
              :color="'red'">
            {{ message.keeping }}
            <loading-outlined/>
          </a-tag>
          <a-tag
              v-if="check_keep_status === 0"
              :color="'green'">
            {{ message.not_keeping }}
          </a-tag>
        </a-descriptions-item>
      </a-descriptions>
      <a-space direction="vertical" style="width: 100%; margin-top: 10px">
        <a-input v-model:value="modalFormState.duration" :placeholder="message.duration_placeholder" max="10000"
                 min="1" onkeyup="value=value.replace(/^(0+)|[^\d]+/g,'')" step="1" type="number">
          <template #addonBefore>
            <a-select v-model:value="modalFormState.type" style="width: 130px">
              <a-select-option value="1">{{ message.add_keep_seconds }}</a-select-option>
              <a-select-option value="2">{{ message.dec_keep_seconds }}</a-select-option>
            </a-select>
          </template>
          <template #addonAfter>
            <a-select v-model:value="modalFormState.action_type" style="width: 100px">
              <a-select-option value="1">second</a-select-option>
              <a-select-option value="2">minutes</a-select-option>
              <a-select-option value="3">hour</a-select-option>
            </a-select>
          </template>
        </a-input>
      </a-space>
    </a-modal>
  </a-card>
</template>

<script>
const lang_map = {
  'zh-CN': {
    search: '搜索',
    enter_keywords: '请输入关键字',
    machine_name: '机器名称',
    machine_code: '机器编号',
    machine_cate: '机台类别',
    reset: '重置',
    close: '关闭',
    open: '开启',
    not_winning: '未中奖',
    winning: '开奖中',
    kick_out: '踢除玩家',
    forced_kick: '强制踢出',
    keep_time_change: '调整保留时间',
    action: '操作',
    keeping: '保留中',
    not_keeping: '未保留',
    open_any_point: '自定义开分',
    player: '游戏中玩家',
    keep_seconds: '可保留时间',
    game_info: '游戏实时信息',
    auto: '自动状态',
    move_point: '移分状态',
    reward_status: '开奖状态',
    point: '当前余分',
    turn_number: '当前转数',
    player_win_number: '玩家使用转数',
    score: '当前得分',
    player_score: '玩家得分',
    player_pressure: '玩家押分',
    wash: '下分可兑换游戏点',
    player_open_point: '玩家总上分',
    player_wash_point: '玩家总下分',
    last_point_at: '最后上分时间',
    last_game_at: '游戏开始时间',
    notice: '提示',
    kick_out_msg: '此操作將會把分數轉回該玩家的遊戲錢包，你確定要踢除玩家嗎？ (该操作离线机台无法操作)',
    forced_kick_msg: '此操作將會踢除玩家，並不返還機台內分數，你確定要踢除玩家嗎？',
    action_success: '操作成功',
    machine_media_not_found: '机台未配置播放线路',
    keep_time_title: '调整保留时间',
    duration_placeholder: '请输入调整时长',
    keep_status: '保留状态',
    add_keep_seconds: '增加保留时间',
    dec_keep_seconds: '减少保留时间',
  },
  'en': {
    search: 'search',
    enter_keywords: 'Please enter keywords',
    machine_name: 'machine name',
    machine_code: 'machine number',
    machine_cate: 'machine category',
    reset: 'reset',
    close: 'close',
    open: 'open',
    not_winning: 'not winning',
    winning: 'winning',
    kick_out: 'Kick player',
    forced_kick: 'Forced kick',
    keep_time_change: 'Adjust retention time',
    action: 'action',
    keeping: 'keeping',
    not_keeping: 'not kept',
    open_any_point: 'custom open point',
    player: 'Player in game',
    keep_seconds: 'retainable time',
    game_info: 'Game real-time information',
    auto: 'automatic state',
    move_point: 'Move point status',
    reward_status: 'Reward status',
    point: 'Current remainder',
    turn_number: 'Current number of turns',
    player_win_number: "Player's number of turns",
    score: 'Current score',
    player_score: 'Player score',
    player_pressure: 'Player bets',
    wash: 'Lower points can be exchanged for game points',
    player_open_point: "Player's total points",
    player_wash_point: "Player's total points",
    last_point_at: 'Last point time',
    last_game_at: 'game start time',
    notice: 'hint',
    kick_out_msg: "This operation will transfer the points back to the player's game wallet. Are you sure you want to kick the player? (This operation cannot be performed on offline machines)",
    forced_kick_msg: 'This operation will kick the player and will not return the points in the machine. Are you sure you want to kick the player? ',
    action_success: 'Operation successful',
    machine_media_not_found: 'The machine is not configured with a playback line',
    keep_time_title: 'Adjust retention time',
    duration_placeholder: 'Please enter the adjustment duration',
    keep_status: 'Keep status',
    add_keep_seconds: 'Increase retention time',
    dec_keep_seconds: 'Reduce retention time',
  },
  'jp': {
    search: '検索',
    enter_keywords: 'キーワードを入力してください',
    machine_name: 'マシン名',
    machine_code: 'マシン番号',
    machine_cate: 'マシン カテゴリ',
    reset: 'リセット',
    close: '閉じる',
    open: '開く',
    not_winning: '勝てません',
    winning: '勝利',
    kill_out: 'プレーヤーをキック',
    Forced_kick: '強制キック',
    keep_time_change: '保持期間を調整',
    action: 'アクション',
    keeping: '維持',
    not_keeping: '保持されていない',
    open_any_point: 'カスタムオープンポイント',
    player: 'ゲーム内のプレイヤー',
    keep_seconds: '保持可能時間',
    game_info: 'ゲームのリアルタイム情報',
    auto: '自動状態',
    move_point: '移動ポイントのステータス',
    reward_status: '報酬ステータス',
    point: '現在の残り',
    turn_number: '現在のターン数',
    player_win_number: 'プレイヤーのターン数',
    score: '現在のスコア',
    player_score: 'プレイヤースコア',
    player_pressure: 'プレイヤーのベット',
    wash: '低いポイントはゲームポイントと交換できます',
    player_open_point: 'プレイヤーの合計ポイント',
    player_wash_point: 'プレイヤーの合計ポイント',
    last_point_at: '最終ポイント時刻',
    last_game_at: 'ゲーム開始時間',
    notice: 'ヒント',
    kick_out_msg: 'この操作により、ポイントがプレーヤーのゲーム ウォレットに戻されます。プレーヤーをキックしてもよろしいですか? (この操作はオフラインのマシンでは実行できません)',
    Forced_kick_msg: 'この操作はプレーヤーをキックしますが、マシン内のポイントは返されません。プレーヤーをキックしてもよろしいですか? ',
    action_success: '操作は成功しました',
    machine_media_not_found: 'マシンには再生ラインが設定されていません',
    keep_time_title: '保存期間を調整',
    duration_placeholder: '調整期間を入力してください',
    keep_status: 'ステータスを保持',
    add_keep_seconds: '保持期間を長くします',
    dec_keep_seconds: '保持時間を短縮',
  },
  'zh-TW': {
    search: '搜尋',
    enter_keywords: '請輸入關鍵字',
    machine_name: '機器名稱',
    machine_code: '機器編號',
    machine_cate: '機器類別',
    reset: '重置',
    close: '關閉',
    open: '開啟',
    not_winning: '未中獎',
    winning: '開獎中',
    kick_out: '踢除玩家',
    forced_kick: '強制踢出',
    keep_time_change: '調整保留時間',
    action: '操作',
    keeping: '保留中',
    not_keeping: '未保留',
    open_any_point: '自訂開分',
    player: '遊戲中玩家',
    keep_seconds: '可保留時間',
    game_info: '遊戲即時資訊',
    auto: '自動狀態',
    move_point: '移分狀態',
    reward_status: '開獎狀態',
    point: '目前餘分',
    turn_number: '目前轉數',
    player_win_number: '玩家使用轉數',
    score: '當前得分',
    player_score: '玩家得分',
    player_pressure: '玩家押分',
    wash: '下分可兌換遊戲點數',
    player_open_point: '玩家總上分',
    player_wash_point: '玩家總下分',
    last_point_at: '最後上分時間',
    last_game_at: '遊戲開始時間',
    notice: '提示',
    kick_out_msg: '此操作將會把分數轉回該玩家的遊戲錢包，你確定要踢除玩家嗎？ (該操作離線機台無法操作)',
    forced_kick_msg: '此操作將會踢除玩家，並不返還機台內分數，你確定要踢除玩家嗎？ ',
    action_success: '操作成功',
    machine_media_not_found: '機台未設定播放線路',
    keep_time_title: '調整保留時間',
    duration_placeholder: '請輸入調整時長',
    keep_status: '保留狀態',
    add_keep_seconds: '增加保留時間',
    dec_keep_seconds: '減少保留時間',
  }
}
export default {
  //可传参数
  props: {
    cate_options: [],
    department_id: '',
    ws: '',
    slot_action_list: [],
    jackpot_action_list: [],
    play_title: '',
    lang: '',
  },
  data() {
    return {
      message: {
        search: lang_map[this.lang].search,
        enter_keywords: lang_map[this.lang].enter_keywords,
        machine_name: lang_map[this.lang].machine_name,
        machine_code: lang_map[this.lang].machine_code,
        machine_cate: lang_map[this.lang].machine_cate,
        reset: lang_map[this.lang].reset,
        close: lang_map[this.lang].close,
        open: lang_map[this.lang].open,
        not_winning: lang_map[this.lang].not_winning,
        winning: lang_map[this.lang].winning,
        kick_out: lang_map[this.lang].kick_out,
        forced_kick: lang_map[this.lang].forced_kick,
        keep_time_change: lang_map[this.lang].keep_time_change,
        action: lang_map[this.lang].action,
        keeping: lang_map[this.lang].keeping,
        not_keeping: lang_map[this.lang].not_keeping,
        open_any_point: lang_map[this.lang].keeping,
        turn_number: lang_map[this.lang].turn_number,
        keep_time_title: lang_map[this.lang].keep_time_title,
        duration_placeholder: lang_map[this.lang].duration_placeholder,
        keep_seconds: lang_map[this.lang].keep_seconds,
        keep_status: lang_map[this.lang].keep_status,
        add_keep_seconds: lang_map[this.lang].add_keep_seconds,
        dec_keep_seconds: lang_map[this.lang].dec_keep_seconds,
      },
      columns: [
        {
          title: 'ID',
          dataIndex: 'id',
          align: 'center',
          visible: true,
          fixed: 'left',
          width: 100,
        },
        {
          title: lang_map[this.lang].machine_name,
          dataIndex: 'name',
          align: 'center',
          sorter: {
            compare: (a, b) => a.name - b.name,
            multiple: 2,
          },
          visible: true,
          fixed: 'left',
          width: 100,
        },
        {
          title: lang_map[this.lang].machine_code,
          dataIndex: 'code',
          key: 'code',
          align: 'center',
          sorter: {
            compare: (a, b) => a.code - b.code,
            multiple: 1,
          },
          visible: true,
          width: 150,
        },
        {
          title: lang_map[this.lang].machine_cate,
          dataIndex: 'cate_name',
          align: 'center',
          visible: true,
          width: 100,
        },
        {
          title: lang_map[this.lang].player,
          dataIndex: 'player_phone',
          align: 'center',
          key: 'player_phone',
          visible: true,
          width: 150,
        },
        {
          title: lang_map[this.lang].keep_seconds,
          dataIndex: 'keep_seconds',
          key: 'keep_seconds',
          align: 'center',
          visible: true,
          width: 100,
        },
        {
          title: lang_map[this.lang].game_info,
          visible: true,
          children: [
            {
              title: lang_map[this.lang].auto,
              dataIndex: 'auto',
              key: 'auto',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].move_point,
              dataIndex: 'move_point',
              key: 'move_point',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].reward_status,
              dataIndex: 'reward_status',
              key: 'reward_status',
              align: 'center',
              width: 100,
            },
            {
              title: lang_map[this.lang].point,
              dataIndex: 'point',
              key: 'point',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].player_score,
              dataIndex: 'player_score',
              key: 'player_score',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].player_pressure,
              dataIndex: 'player_pressure',
              key: 'player_pressure',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].wash,
              dataIndex: 'wash',
              key: 'wash',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].player_open_point,
              dataIndex: 'player_open_point',
              key: 'player_open_point',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].player_wash_point,
              dataIndex: 'player_wash_point',
              key: 'player_wash_point',
              align: 'center',
              width: 80,
            },
          ],
        },
        {
          title: lang_map[this.lang].last_point_at,
          dataIndex: 'last_point_at',
          align: 'center',
          visible: true,
          width: 150,
        },
        {
          title: lang_map[this.lang].last_game_at,
          dataIndex: 'last_game_at',
          align: 'center',
          visible: true,
          fixed: 'right',
          width: 150,
        },
        {
          title: '',
          key: 'action',
          dataIndex: 'action',
          align: 'center',
          fixed: 'right',
          width: 100,
          visible: true,
        },
      ],
      columns_jackpot: [
        {
          title: 'ID',
          dataIndex: 'id',
          align: 'center',
          visible: true,
          fixed: 'left',
          width: 100,
        },
        {
          title: lang_map[this.lang].machine_name,
          dataIndex: 'name',
          align: 'center',
          sorter: {
            compare: (a, b) => a.name - b.name,
            multiple: 2,
          },
          visible: true,
          fixed: 'left',
          width: 100,
        },
        {
          title: lang_map[this.lang].machine_code,
          dataIndex: 'code',
          key: 'code',
          align: 'center',
          sorter: {
            compare: (a, b) => a.code - b.code,
            multiple: 1,
          },
          visible: true,
          width: 150,
        },
        {
          title: lang_map[this.lang].machine_cate,
          dataIndex: 'cate_name',
          align: 'center',
          visible: true,
          width: 100,
        },
        {
          title: lang_map[this.lang].player,
          dataIndex: 'player_phone',
          align: 'center',
          visible: true,
          width: 150,
        },
        {
          title: lang_map[this.lang].keep_seconds,
          dataIndex: 'keep_seconds',
          key: 'keep_seconds',
          align: 'center',
          visible: true,
          width: 100,
        },
        {
          title: lang_map[this.lang].game_info,
          visible: true,
          children: [
            {
              title: lang_map[this.lang].auto,
              dataIndex: 'auto',
              key: 'auto',
              align: 'center',
              width: 80,
            },
            {
              title: 'PUSH OUT',
              dataIndex: 'push_auto',
              key: 'push_auto',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].reward_status,
              dataIndex: 'reward_status',
              key: 'reward_status',
              align: 'center',
              width: 100,
            },
            {
              title: lang_map[this.lang].turn_number,
              dataIndex: 'turn',
              key: 'turn',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].player_win_number,
              dataIndex: 'player_win_number',
              key: 'player_win_number',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].point,
              dataIndex: 'point',
              key: 'point',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].score,
              dataIndex: 'score',
              key: 'score',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].wash,
              dataIndex: 'wash',
              key: 'wash',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].player_open_point,
              dataIndex: 'player_open_point',
              key: 'player_open_point',
              align: 'center',
              width: 80,
            },
            {
              title: lang_map[this.lang].player_wash_point,
              dataIndex: 'player_wash_point',
              key: 'player_wash_point',
              align: 'center',
              width: 80,
            },
          ],
        },
        {
          title: lang_map[this.lang].last_point_at,
          dataIndex: 'last_point_at',
          align: 'center',
          visible: true,
          width: 150,
        },
        {
          title: lang_map[this.lang].last_game_at,
          dataIndex: 'last_game_at',
          align: 'center',
          visible: true,
          fixed: 'right',
          width: 150,
        },
        {
          title: '',
          key: 'action',
          dataIndex: 'action',
          align: 'center',
          fixed: 'right',
          width: 100,
          visible: true,
        },
      ],
      loading: true,
      current: 1,
      pageSize: 10,
      dataSource: [],
      total: 0,
      showSearch: false,
      options: this.cate_options,
      department_id: this.department_id,
      sort: [],
      activeKey: '1',
      spinning: true,
      isActive: true,
      refreshKey: 0,
      visible: false,
      typeSelected: 0,
      open_visible: false,
      open_any_point_value: null,
      open_any_point_cmd: '4A',
      is_move: false,
      action_visible: false,
      is_show: false,
      is_open: false,
      iframe_src: '',
      btn_text: '',
      src_list: '',
      play_address: '',
      machine_id: '',
      quick_search_slot: '',
      quick_search_jackpot: '',
      keeping_status: 0,
      keep_seconds: 0,
      check_machine_id: 0,
      check_keep_status: null,
      check_keep_seconds: 0,
      action_list: [],
      formState: {
        name: '',
        code: '',
        cate_id: []
      },
      modalFormState: {
        type: '1',
        action_type: '1',
        duration: '',
      },
      rangeConfig: {
        rules: [{
          type: 'array',
        }],
      },
      content: null,
      channelName: null
    };
  },
  computed: {
    visibleColumns() {
      if (this.activeKey === '1') {
        return this.columns.filter(col => col.visible);
      }
      if (this.activeKey === '2') {
        return this.columns_jackpot.filter(col => col.visible);
      }
    },
    pagination() {
      return {
        total: this.total,
        current: this.current,
        pageSize: this.pageSize,
        showSizeChanger: true,
        showQuickJumper: true,
        showTotal: (total) => `共 ${total} 条`,
        pageSizeOptions: ['20', '50', '100'],
        size: 'small',
        style: "float: left; margin-top: 16px;"
      };
    },
  },
  created() {
    this.queryData();
    if (this.activeKey === '1') {
      this.action_list = this.slot_action_list;
    }
    if (this.activeKey === '2') {
      this.action_list = this.jackpot_action_list;
    }
    // 初始化 WebSocket
    this.initWebSocket();
  },
  beforeUnmount() {
    // 取消订阅
    const pushManager = this.getPushManager();
    if (this.channelName && pushManager) {
      pushManager.unsubscribe(this.channelName, this.handleMessage);
      this.channelName = null;
    }
  },
  methods: {
    getPushManager() {
      // 优先使用 Vue 注入的实例，否则使用全局实例
      return this.$pushManager || window.$pushManager;
    },

    async initWebSocket() {
      try {
        const pushManager = this.getPushManager();

        // 检查 pushManager 是否可用
        if (!pushManager) {
          console.error('[InfoList] PushManager not available');
          return;
        }

        // 初始化连接
        await pushManager.init(this.ws);

        // 订阅频道
        this.channelName = `machine-real-time-information-${this.department_id}`;
        pushManager.subscribe(this.channelName, this.handleMessage, this);

        console.log('[InfoList] WebSocket initialized successfully');
      } catch (error) {
        console.error('[InfoList] Init WebSocket failed:', error);
      }
    },

    handleMessage(data) {
      try {
        const content = JSON.parse(data.content);
        switch (content.msg_type) {
          case 'game_start':
            this.current = 1;
            // WebSocket 推送时保持快速查询值
            const quickSearch = this.activeKey === '1' ? this.quick_search_slot : this.quick_search_jackpot;
            this.queryData({
              quickSearch: quickSearch,
              name: this.formState.name,
              code: this.formState.code,
              cate_id: this.formState.cate_id,
              sort: this.sort,
            });
            break;
          case 'game_info_change':
            const updatedRecord = content.machine_info;
            const index = this.dataSource.findIndex(record => record.id === updatedRecord.id);
            if (index !== -1) {
              this.dataSource[index] = {...this.dataSource[index], ...updatedRecord};
              let rowElement = this.$refs.myTable.$el.querySelector(`tr[data-row-key="${updatedRecord.id}"]`);
              if (rowElement) {
                const tdElements = rowElement.querySelectorAll('td'); // 获取所有 <td> 元素
                // 遍历 <td> 元素并执行操作
                tdElements.forEach(td => {
                  td.classList.add('ant-table-cell-row-change');
                });
                setTimeout(() => {
                  tdElements.forEach(td => {
                    td.classList.remove('ant-table-cell-row-change');
                  });
                }, 1500);
              }
              if (this.$refs.myJackpotTable) {
                let rowJackpotElement = this.$refs.myJackpotTable.$el.querySelector(`tr[data-row-key="${updatedRecord.id}"]`);
                if (rowJackpotElement) {
                  const tdJackpotElements = rowJackpotElement.querySelectorAll('td'); // 获取所有 <td> 元素
                  // 遍历 <td> 元素并执行操作
                  tdJackpotElements.forEach(td => {
                    td.classList.add('ant-table-cell-row-change');
                  });
                  setTimeout(() => {
                    tdJackpotElements.forEach(td => {
                      td.classList.remove('ant-table-cell-row-change');
                    });
                  }, 1500);
                }
              }
            }
            if (this.check_machine_id === updatedRecord.id) {
              this.check_keep_seconds = updatedRecord.keep_seconds;
              this.check_keep_status = updatedRecord.keeping;
            }
            break;
        }
      } catch (e) {
        console.warn('[InfoList] Parse message failed:', e);
      }
    },
    queryData(params = {}) {
      this.loading = true;
      this.$request({
        url: 'ex-admin/addons-webman-controller-ChannelMachineController/getMachineList',
        method: 'post',
        data: {
          type: this.activeKey,
          page: this.current,
          pageSize: this.pageSize,
          ...params
        },
      }).then(res => {
        this.dataSource = res.data.data;
        this.total = res.data.total;
      }).catch(error => {
        this.$message.error(error.message);
      }).finally(() => {
        this.loading = false;
      });
    },
    toggleColumnVisibility() {
    },
    handleColumnChange(col) {
      col.visible = !col.visible;
    },
    handleTableChange(pag, filters, sorter) {
      this.current = pag.current;
      this.pageSize = pag.pageSize;
      if (Array.isArray(sorter)) {
        sorter.forEach((item, key) => {
          this.sort[key] = [item.field, item.order];
        });
      } else {
        if (sorter.field !== '' && sorter.field !== undefined && sorter.order !== '' && sorter.order !== undefined) {
          this.sort[0] = [sorter.field, sorter.order];
        }
      }
      // 分页/排序时保持快速查询值
      const quickSearch = this.activeKey === '1' ? this.quick_search_slot : this.quick_search_jackpot;
      const params = {
        quickSearch: quickSearch,
        sort: this.sort,
        filters,
        name: this.formState.name,
        code: this.formState.code,
        cate_id: this.formState.cate_id,
      };

      this.queryData(params);
    },
    onSearch(value) {
      this.current = 1;
      // 根据当前标签页保存快速查询值
      if (this.activeKey === '1') {
        this.quick_search_slot = value;
      } else if (this.activeKey === '2') {
        this.quick_search_jackpot = value;
      }
      const params = {
        quickSearch: value,
        name: this.formState.name,
        code: this.formState.code,
        cate_id: this.formState.cate_id,
        sort: this.sort,
      };
      this.queryData(params);
    },
    refreshTable() {
      // 刷新时保持快速查询值
      const quickSearch = this.activeKey === '1' ? this.quick_search_slot : this.quick_search_jackpot;
      this.queryData({
        quickSearch: quickSearch,
        name: this.formState.name,
        code: this.formState.code,
        cate_id: this.formState.cate_id,
        sort: this.sort,
      });
    },
    toggleSearch() {
      this.showSearch = !this.showSearch;
    },
    onFinish() {
      this.current = 1;
      // 表单筛选时保持快速查询值
      const quickSearch = this.activeKey === '1' ? this.quick_search_slot : this.quick_search_jackpot;
      this.queryData({
        quickSearch: quickSearch,
        name: this.formState.name,
        code: this.formState.code,
        cate_id: this.formState.cate_id,
        sort: this.sort,
      });
    },
    onReset() {
      // 重置表单字段
      this.$refs.formRef.resetFields();
      // 重置后自动搜索
      this.current = 1;
      const quickSearch = this.activeKey === '1' ? this.quick_search_slot : this.quick_search_jackpot;
      this.queryData({
        quickSearch: quickSearch,
        name: '',
        code: '',
        cate_id: [],
        sort: this.sort,
      });
    },
    onCateChange(value) {
      this.formState.cate_id = value;
    },
    handleMenuClick(key, record) {
      let action = key.key;
      let id = record.id;
      let that = this;
      if (action === '1') {
        this.$confirm({
          title: () => lang_map[this.lang].notice,
          content: () => lang_map[this.lang].kick_out_msg,
          onOk() {
            that.$request({
              url: 'ex-admin/addons-webman-controller-ChannelMachineController/action',
              method: 'post',
              data: {
                action: 'kick_player',
                id: id,
              },
            }).then(res => {
              that.loading = true;
              // 操作完成后保持快速查询值
              const quickSearch = that.activeKey === '1' ? that.quick_search_slot : that.quick_search_jackpot;
              that.queryData({
                quickSearch: quickSearch,
                name: that.formState.name,
                code: that.formState.code,
                cate_id: that.formState.cate_id,
                sort: that.sort,
              });
            }).catch(error => {
              this.$message.error(error.message);
            });
          }
        });
      } else if (action === '2') {
        this.$confirm({
          title: () => lang_map[this.lang].notice,
          content: () => lang_map[this.lang].forced_kick_msg,
          onOk() {
            that.$request({
              url: 'ex-admin/addons-webman-controller-ChannelMachineController/action',
              method: 'post',
              data: {
                action: 'kick_force',
                id: id,
              },
            }).then(res => {
              that.loading = true;
              // 操作完成后保持快速查询值
              const quickSearch = that.activeKey === '1' ? that.quick_search_slot : that.quick_search_jackpot;
              that.queryData({
                quickSearch: quickSearch,
                name: that.formState.name,
                code: that.formState.code,
                cate_id: that.formState.cate_id,
                sort: that.sort,
              });
            }).catch(error => {
              this.$message.error(error.message);
            });
          }
        });
      } else if (action === '3') {
        this.is_open = true;
        this.check_machine_id = id;
        let index = this.dataSource.findIndex(record => record.id === id);
        if (index !== -1) {
          this.check_keep_status = this.dataSource[index].keeping;
          this.check_keep_seconds = this.dataSource[index].keep_seconds;
        }
      }
    },
    handleIframeLoad() {
      this.spinning = false;
    },
    showDrawer() {
      this.visible = true;
      this.is_move = true;
    },
    showActionDrawer() {
      this.action_visible = true;
      this.is_move = true;
    },
    onClose() {
      this.visible = false;
      this.action_visible = false;
      this.is_move = false;
    },
    handleActionClick(cmd) {
      if (cmd === this.open_any_point_cmd) {
        this.open_visible = true;
      } else {
        this.sendCmd(cmd);
      }
    },
    sendCmd(cmd, data = null) {
      this.$request({
        url: 'ex-admin/system/doMachineCmd',
        method: 'post',
        data: {
          'cmd': cmd,
          'data': data,
          'machine_id': this.machine_id,
        },
      }).then(res => {
        if (res.code === 200) {
          this.$message.success(lang_map[this.lang].action_success);
        } else {
          this.$message.error(res.message ? res.message : res.msg);
        }
      }).catch(error => {
        this.$message.error(error.message);
      })
    },
    openAnyPoint() {
      this.sendCmd(this.open_any_point_cmd, this.open_any_point_value)
      this.open_visible = false;
    },
    changeMedia(src, index) {
      this.$props.iframe_src = src;
      this.isActive = false;
      this.refreshKey++
      this.visible = false;
      this.open_visible = false;
      this.typeSelected = index;
    },
    play(key, record) {
      if (record.iframe_src.length === 0) {
        return this.$message.error(lang_map[this.lang].machine_media_not_found);
      }
      this.is_show = true;
      this.iframe_src = record.iframe_src;
      this.machine_id = record.id;
      this.src_list = record.src_list;
    },
    closePlay() {
      this.is_show = false;
      this.iframe_src = '';
      this.machine_id = 0;
      this.src_list = [];
    },
    tabsChange(key) {
      this.current = 1;
      if (key === '1') {
        this.action_list = this.slot_action_list;
      }
      if (key === '2') {
        this.action_list = this.jackpot_action_list;
      }
      // 切换标签页时保持对应的快速查询值
      const quickSearch = key === '1' ? this.quick_search_slot : this.quick_search_jackpot;
      this.queryData({
        quickSearch: quickSearch,
        name: this.formState.name,
        code: this.formState.code,
        cate_id: this.formState.cate_id,
        sort: this.sort,
      });
    },
    closeKeepTimeChange() {
      this.is_open = false;
      this.check_machine_id = 0;
      this.check_keep_status = null;
      this.check_keep_seconds = 0;
    },
    keepTimeChange() {
      this.$request({
        url: 'ex-admin/addons-webman-controller-ChannelMachineController/keepTimeChange',
        method: 'post',
        data: {
          'type': this.modalFormState.type,
          'duration': this.modalFormState.duration,
          'action_type': this.modalFormState.action_type,
          'id': this.check_machine_id,
        },
      }).then(res => {
        if (res.code !== 200) {
          this.$message.error(res.message ? res.message : res.msg);
        }
      }).catch(error => {
        this.$message.error(error.message);
      })
    },
  }
};
</script>

<style scoped>
.tools {
  background: #fff;
  position: relative;
  border-radius: 5px;
  padding-left: 10px;
  padding-bottom: 10px;
  padding-top: 10px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
}

.tools .left {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
}

.tools .right {
  display: flex;
  justify-content: end;
  margin: 0 15px;
}

.quickSearch {
  margin-right: 8px;
  width: 250px;
}

.filter {
  border-top: 1px solid #ededed;
  background: #fff;
  padding: 20px 20px 0;
}

.filter .ant-form-inline .ant-form-item {
  margin-bottom: 20px;
}

.ant-form-inline .ant-form-item {
  flex: none;
  flex-wrap: nowrap;
  margin-bottom: 0;
  margin-right: 16px;
}

.active_play {
  transform: rotate(270deg)
}

.active_media {
  color: rgb(24, 144, 255) !important;
}

.iframe_video {
  width: 527px !important;
  height: 536px !important;
  overflow: hidden !important;
  display: flex !important;
  margin: 0 auto !important;
}

.animate_left {
  animation: left-move 1s ease-in-out;
  animation-fill-mode: forwards
}

@keyframes left-move {
  to {
    transform: translateX(-131px);
  }
}

.iframe_box {
  width: 527px;
  height: 357px;
  margin-top: 93px;
  display: flex;
}

.ant-table-cell-row-change {
  background: #ddd;
}
</style>