<template>
  <v-md-editor
      v-model="value"
      :disabled-menus="[]"
      @upload-image="handleUploadImage"
      height="500px"
  />
</template>

<script>
export default {
  props:{
    value:String,
  },
  data() {
    return {
      value: '',
    };
  },
  setup(props, ctx) {
    const value =  Vueuse.useVModel(props, 'value',ctx.emit)
    return {
      value
    }
  },
  methods: {
    handleUploadImage(event, insertImage, files) {
      // 此处只做示例
      const FormData1=new FormData()
      FormData1.append("file",files[0])
      this.$request.post("ex-admin/addons-webman-controller-IndexController/myEditorUpload", FormData1,{
        'Content-Type': 'multipart/form-data'
      }).then(response=>{
        insertImage({
          url:response.data.url
        });
      })
    },
  },
};
</script>