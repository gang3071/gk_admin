vue
<template>
  <div class="video-container">
    <video controls class="rotated-video">
      <source :src="play_address" type="video/mp4">
    </video>
  </div>
</template>

<script>
export default {
  props: {
    play_address: String,
  }
};
</script>

<style>
.video-container {
  display: flex; /* 使用 Flexbox 布局 */
  justify-content: center; /* 水平居中 */
  align-items: center; /* 垂直居中 */
  height: 600px; /* 设置容器高度 */
  overflow: hidden; /* 隐藏溢出内容 */
}

.rotated-video {
  width: auto;
  height: 100%;
  max-height: 59%;
  transform: rotate(270deg)
}
</style>