<template>
  <div class="media_btn">
    <a-button shape="circle" @click="showActionDrawer" size="small">
      <template #icon>
        <interaction-outlined/>
      </template>
    </a-button>
    <a-button shape="circle" @click="isActive = !isActive" size="small">
      <template #icon>
        <sync-outlined/>
      </template>
    </a-button>
    <a-button shape="circle" @click="showDrawer" size="small">
      <template #icon>
        <play-circle-outlined/>
      </template>
    </a-button>
  </div>
  <div :class="{ animate_left:is_move }">
    <a-spin :spinning="spinning" wrapperClassName="iframe_video">
      <iframe
          class="iframe_box"
          :src="iframe_src"
          @load="handleIframeLoad" sandbox='allow-scripts allow-same-origin allow-popups' ref="media" frameborder="0"
          allowfullscreen v-bind:class="{ active_play: isActive }" id="my-iframe"
          :key="refreshKey"></iframe>
    </a-spin>
  </div>
  <a-drawer
      :title="play_address"
      placement="right"
      :closable="false"
      :visible="action_visible"
      :get-container="false"
      :style="{ position: 'absolute' }"
      @close="onClose"
      width="44%"
      :maskStyle="{opacity:0}"
  >
    <a-button type="dashed" @click="handleMenuClick(item.key)" shape="round" size="small" v-for="(item) in action_list"
              :key="item.key" style="margin: 6px">
      <template #icon>
        <tool-outlined/>
      </template>
      {{ item.action }}
    </a-button>
  </a-drawer>
  <a-drawer
      :title="play_address"
      placement="right"
      :closable="false"
      :visible="visible"
      :get-container="false"
      :style="{ position: 'absolute' }"
      @close="onClose"
      width="44%"
      :maskStyle="{opacity:0}"
  >
    <a-list item-layout="horizontal" :data-source="iframe_list">
      <template #renderItem="{ item,index }">
        <a-list-item>
          <a-list-item-meta :description="item.desc">
            <template #title>
              <a href="javascript:void(0);" @click="changeMedia(item.src, index)"
                 :class="index === typeSelected ?'active_media':''">{{ item.title }}</a>
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
    <a-modal v-model:visible="open_visible" title="自定义开分" @ok="openAnyPoint" destroyOnClose="true"
             maskClosable="true" width="300px">
      <a-input-number v-model:value="open_any_point_value" addon-before="+" addon-after="point" :max="5000" :min="0"
                      :step="1" :precision="0"></a-input-number>
    </a-modal>
  </div>
</template>
<script>
export default {
  props: {
    iframe_src: String,
    btn_text: String,
    iframe_list: String,
    play_address: String,
    type: String,
    machine_id: String,
    action_list: [],
    open_any_point_cmd: String,
  },
  data() {
    return {
      spinning: true,
      isActive: true,
      refreshKey: 0,
      visible: false,
      typeSelected: 0,
      open_visible: false,
      open_any_point_value: null,
      is_move: false,
      action_visible: false,
    };
  },
  methods: {
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
    handleMenuClick(cmd) {
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
          this.$message.success('操作成功');
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
  },
};
</script>
<style>
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

.media_btn {
  margin-top: -63px;
  position: fixed;
  margin-left: 382px;
}
</style>