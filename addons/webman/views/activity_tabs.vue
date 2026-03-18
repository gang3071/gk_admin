<template>
  <a-form layout="vertical" ref="formRef" @finish="onFinish" @finishFailed="onFinishFailed" :model="activity"
          :label-col="labelCol" :wrapper-col="wrapperCol">
    <a-tabs v-model:activeKey="activeKey" type="editable-card" @edit="onEdit">
      <a-tab-pane key="activeKey_content" :tab="activity_content">
        <a-form-item :label="channel" name="department_id">
          <a-select v-model:value="activity.department_id" mode="multiple" :options="departmentOptions"
                    allowClear="true">
          </a-select>
        </a-form-item>
        <a-form-item name="range_time" v-model:label="RangePicker.showTime" v-bind="rangeConfig">
          <a-range-picker v-model:value="activity.range_time" show-time format="YYYY-MM-DD HH:mm:ss"
                          value-format="YYYY-MM-DD HH:mm:ss"/>
        </a-form-item>
        <a-form-item :label="machine_type" :rules="[{ required: true, message: machine_type_required }]"
                     @change="machineTypeChange" name="type">
          <a-radio-group v-model:value="activity.type" :options="gameType" option-type="button"/>
        </a-form-item>
        <a-form-item name="id" hidden>
          <a-input type="hidden" v-model:value="activity.id" name="id"/>
        </a-form-item>
        <a-tabs v-model:activeKey="activeKey_lang_content">
          <a-tab-pane v-for="(lang, lang_index) in langs" :key="lang_index" :tab="lang.value" forceRender="true">
            <a-form-item :label="activity_picture" :name="['activity_content', lang.key, 'picture']" :rules="[{ required: true, message: picture_required }]">
              <a-upload :before-upload="beforeUpload"
                        action="/ex-admin/addons-webman-controller-IndexController/activityUpload" :headers="headers"
                        list-type="picture-card" :max-count="1"
                        v-model:file-list="activity.activity_content[lang.key].picture">
                <div>
                  <PlusOutlined/>
                  <div style="margin-top: 8px">Upload</div>
                </div>
              </a-upload>
            </a-form-item>
            <a-form-item :name="['activity_content', lang.key, 'id']" hidden>
              <a-input type="hidden" v-model:value="activity.activity_content[lang.key].id"/>
            </a-form-item>

            <a-form-item :label="activity_name" :name="['activity_content', lang.key, 'name']" :rules="[{ required: true, message: name_required }]">
              <a-input v-model:value="activity.activity_content[lang.key].name" show-count :maxlength="100"
                       :rules="[{ required: true, message: activity_name_required }]"/>
            </a-form-item>
            <a-form-item :label="activity_get_way" :name="['activity_content', lang.key, 'get_way']">
              <a-input v-model:value="activity.activity_content[lang.key].get_way" show-count :maxlength="100"/>
            </a-form-item>
            <a-form-item :label="activity_description" :name="['activity_content', lang.key, 'description']">
              <a-textarea v-model:value="activity.activity_content[lang.key].description" show-count :maxlength="700"
                          :rows="4"/>
            </a-form-item>
            <a-form-item :label="activity_join_condition" :name="['activity_content', lang.key, 'join_condition']">
              <a-textarea v-model:value="activity.activity_content[lang.key].join_condition" show-count :maxlength="255"
                          :rows="4"/>
            </a-form-item>
          </a-tab-pane>
        </a-tabs>
      </a-tab-pane>
      <a-tab-pane v-for="(activity_phase, activity_phase_index) in activity.activity_phase" :key="activity_phase_index"
                  :tab="activity_phase.cate_name" forceRender="true">
        <a-form-item :name="['activity_phase', activity_phase_index, 'sort']" hidden>
          <a-input type="hidden" v-model:value="activity_phase_index"/>
        </a-form-item>
        <a-form-item :label="machine_cate" :name="['activity_phase', activity_phase_index, 'cate_id']"
                     :rules="[{ required: true, message: machine_cate_required }]">
          <a-select v-model:value="activity_phase.cate_id" :options="options"
                    @change="(val, item) => { cateHandleChange(val, item, activity_phase_index) }" allowClear="true">
          </a-select>
        </a-form-item>
        <a-space v-for="(phase_list, phase_list_index) in activity_phase.phase_list" :key="phase_list_index"
                 class="activity-phase-has-many" style="display: block">
          <a-space>
            <a-form-item :name="['activity_phase', activity_phase_index, 'phase_list', phase_list_index, 'sort']"
                         hidden>
              <a-input type="hidden" v-model:value="phase_list_index"/>
            </a-form-item>
            <a-form-item :name="['activity_phase', activity_phase_index, 'phase_list', phase_list_index, 'id']" hidden>
              <a-input type="hidden" v-model:value="phase_list.id"/>
            </a-form-item>
            <a-form-item :label="activity_condition"
                         :name="['activity_phase', activity_phase_index, 'phase_list', phase_list_index, 'condition']"
                         :rules="[{ required: true, type: 'number', trigger: 'change', validator: validateCondition}]">
              <a-input v-model:value="phase_list.condition" :placeholder="activity_condition"/>
            </a-form-item>
            <a-form-item :label="activity_bonus"
                         :name="['activity_phase', activity_phase_index, 'phase_list', phase_list_index, 'bonus']"
                         :rules="[{ required: true, message: activity_bonus_required, type: 'number', trigger: 'change', validator: validateBonus}]">
              <a-input v-model:value="phase_list.bonus" :placeholder="activity_bonus"/>
            </a-form-item>
          </a-space>
          <a-tabs v-model:activeKey="activeKey_lang_phase">
            <a-tab-pane v-for="(lang, lang_index) in langs" :key="lang_index" :tab="lang.value" forceRender="true">
              <a-form-item
                  :name="['activity_phase', activity_phase_index, 'phase_list', phase_list_index, 'notice', lang.key]">
                <a-textarea v-model:value="phase_list.notice[lang.key]" :placeholder="activity_notice"
                            :auto-size="{ minRows: 2, maxRows: 7 }"/>
              </a-form-item>
            </a-tab-pane>
          </a-tabs>
          <a-button type="dashed" danger @click="removeUser(phase_list, activity_phase_index)">
            <DeleteOutlined/>
            {{ remove_phase }}
          </a-button>
        </a-space>
        <a-form-item>
          <a-button type="dashed" block @click="addActivityCate(activity_phase_index)">
            <PlusOutlined/>
            {{ add_phase }}
          </a-button>
        </a-form-item>
      </a-tab-pane>
    </a-tabs>
    <a-form-item>
      <a-button type="primary" html-type="submit">{{ Submit }}</a-button>
      <a-button style="margin-left: 10px" @click="resetForm">{{ reset }}</a-button>
    </a-form-item>
  </a-form>
</template>
<script>
const messages = {
  //简体中文
  'zh-CN': {
    rangTime: '请选择开放时间',
    showTime: '开放时间',
    submit: '提交',
    reset: '重置',
    activity_content: '活动内容',
    machine_type: '机台类型',
    machine_type_required: '请选择机台类型',
    machine_cate: '机台类别',
    machine_cate_required: '请选择机台类别',
    activity_picture: '活动图片',
    activity_picture_required: '请上传活动图片',
    activity_name: '活动名称',
    activity_name_required: '请填写活动名称',
    activity_get_way: '领取方式',
    activity_description: '活动说明',
    activity_join_condition: '参与条件',
    activity_condition: '达成条件',
    activity_condition_required: '请填写达成条件',
    activity_bonus: '奖励点数',
    activity_bonus_required: '请填奖励点数',
    activity_notice: '奖励达成提示语',
    activity_phase: '活动阶段',
    remove_phase: '移除',
    add_phase: '添加',
    upload_type: '支持png, jpeg, png图片格式',
    picture_required: '请上传活动图片',
    name_required: '请填写活动名称',
    bonus_required: '请输入奖励点数',
    bonus_number: '请输入正整数',
    bonus_min: '奖励点数最少不能为0',
    bonus_max: '奖励点数最大不能超过1000000',
    condition_required: '请输入达成条件',
    condition_number: '请输入正整数',
    condition_min: '达成条件最少不能为0',
    condition_max: '达成条件最大不能超过100000000',
    channel: '渠道子站'
  },
  //英文
  en: {
    rangTime: 'Please select opening time',
    showTime: 'Opening time',
    submit: 'submit',
    reset: 'reset',
    activity_content: 'activity content',
    machine_type: 'machine type',
    machine_type_required: 'Please select the machine type',
    machine_cate: 'machine category',
    machine_cate_required: 'Please select the machine category',
    activity_picture: 'activity picture',
    activity_picture_required: 'Please upload activity pictures',
    activity_name: 'activity name',
    activity_name_required: 'Please fill in the activity name',
    activity_get_way: 'How to receive',
    activity_description: 'activity description',
    activity_join_condition: 'Participation condition',
    activity_condition: 'Condition met',
    activity_condition_required: 'Please fill in the conditions to achieve',
    activity_bonus: 'Reward points',
    activity_bonus_required: 'Please fill in the reward points',
    activity_notice: 'Reward achievement prompt',
    activity_phase: 'activity phase',
    remove_phase: 'remove',
    add_phase: 'Add',
    upload_type: 'Support png, jpeg, png image formats',
    upload_size: 'The maximum image size must not exceed 5M',
    picture_required: 'Please upload event pictures',
    name_required: 'Please fill in the event name',
    bonus_required: 'Please enter the bonus points',
    bonus_number: 'Please enter a positive integer',
    bonus_min: 'The minimum number of bonus points cannot be 0',
    bonus_max: 'The maximum number of bonus points cannot exceed 1000000',
    condition_required: 'Please enter the conditions to achieve',
    condition_number: 'Please enter a positive integer',
    condition_min: 'The condition to be met cannot be at least 0',
    condition_max: 'The maximum condition to be met cannot exceed 100000000',
    channel: 'Channel'
  },
  jp: {
    rangTime: '開始時間を選択してください',
    showTime: '開場時間',
    submit: '送信',
    reset: 'リセット',
    activity_content: 'アクティビティコンテンツ',
    machine_type: 'マシンタイプ',
    machine_type_required: 'マシンタイプを選択してください',
    machine_cate: 'マシン カテゴリ',
    machine_cate_required: 'マシン カテゴリを選択してください',
    activity_picture: 'アクティビティ画像',
    activity_picture_required: 'アクティビティの写真をアップロードしてください',
    activity_name: 'アクティビティ名',
    activity_name_required: 'アクティビティ名を入力してください',
    activity_get_way: '受け取り方法',
    activity_description: 'アクティビティの説明',
    activity_join_condition: '参加条件',
    activity_condition: '条件が満たされました',
    activity_condition_required: '達成する条件を入力してください',
    activity_bonus: '報酬ポイント',
    activity_bonus_required: '特典ポイントを入力してください',
    activity_notice: '報酬達成プロンプト',
    activity_phase: 'アクティビティフェーズ',
    remove_phase: '削除',
    add_phase: '追加',
    Upload_type: 'png、jpeg、png 画像形式をサポート',
    Upload_size: '画像の最大サイズは 5M を超えてはなりません',
    picture_required: 'イベントの写真をアップロードしてください',
    name_required: 'イベント名を入力してください',
    bonus_required: 'ボーナスポイントを入力してください',
    bonus_number: '正の整数を入力してください',
    bonus_min: 'ボーナス ポイントの最小数を 0 にすることはできません',
    bonus_max: 'ボーナス ポイントの最大数は 1000000 を超えることはできません',
    condition_required: '達成する条件を入力してください',
    condition_number: '正の整数を入力してください',
    condition_min: '満たすべき条件は少なくとも 0 にすることはできません',
    condition_max: '満たすべき最大条件は 100000000 を超えることはできません',
    channel: 'ルート本'
  },
  // 繁体中文
  'zh-TW': {
    rangTime: '請選擇開放時間',
    showTime: '開放時間',
    submit: '提交',
    reset: '重置',
    activity_content: '活動內容',
    machine_type: '機器型別',
    machine_type_required: '請選擇機器型別',
    machine_cate: '機器類別',
    machine_cate_required: '請選擇機器類別',
    activity_picture: '活動圖片',
    activity_picture_required: '請上傳活動圖片',
    activity_name: '活動名稱',
    activity_name_required: '請填入活動名稱',
    activity_get_way: '領取方式',
    activity_description: '活動說明',
    activity_join_condition: '參與條件',
    activity_condition: '達成條件',
    activity_condition_required: '請填寫達成條件',
    activity_bonus: '獎勵點數',
    activity_bonus_required: '請填寫獎勵點數',
    activity_notice: '獎勵達成提示語',
    activity_phase: '活動階段',
    remove_phase: '移除',
    add_phase: '新增',
    upload_type: '支援png, jpeg, png圖片格式',
    upload_size: '圖片最大不得超過5M',
    picture_required: '請上傳活動圖片',
    name_required: '請填入活動名稱',
    bonus_required: '請輸入獎勵點數',
    bonus_number: '請輸入正整數',
    bonus_min: '獎勵點數最少不能為0',
    bonus_max: '獎勵點數最大不能超過1000000',
    condition_required: '請輸入達成條件',
    condition_number: '請輸入正整數',
    condition_min: '達成條件最少不能為0',
    condition_max: '達成條件最大不能超過100000000',
    channel: '渠道子站'
  }
}
export default {
  name: "socket.vue",
  //可传参数
  props: {
    activityModel: {},
    categoryOptions: [],
    departmentOptions: [],
    gameType: {},
    langs: {},
    showTime: String,
    langLocale: String,
  },
  data() {
    return {
      activity: this.activityModel,
      options: this.categoryOptions,
      departmentOptions: this.departmentOptions,
      activeKey: 'activeKey_content',
      newTabIndex: '',
      activeKey_lang_content: 0,
      activeKey_lang_phase: 0,
      rangeConfig: {
        rules: [{
          type: 'array',
          required: true,
          message: messages[this.langLocale]['rangTime'],
        }],
      },
      RangePicker: Vue.reactive({
        showTime: messages[this.langLocale]['showTime']
      }),
      labelCol: {
        style: {
          width: '150px',
        },
      },
      wrapperCol: {
        span: 24,
      },
      headers: {
        authorization: localStorage.getItem("/admin_ex-admin-token"),
      },
      Submit: messages[this.langLocale]['submit'],
      reset: messages[this.langLocale]['reset'],
      activity_content: messages[this.langLocale]['activity_content'],
      machine_type: messages[this.langLocale]['machine_type'],
      machine_type_required: messages[this.langLocale]['machine_type_required'],
      machine_cate: messages[this.langLocale]['machine_cate'],
      machine_cate_required: messages[this.langLocale]['machine_cate_required'],
      activity_picture: messages[this.langLocale]['activity_picture'],
      activity_name: messages[this.langLocale]['activity_name'],
      activity_name_required: messages[this.langLocale]['activity_name_required'],
      activity_get_way: messages[this.langLocale]['activity_get_way'],
      activity_description: messages[this.langLocale]['activity_description'],
      activity_join_condition: messages[this.langLocale]['activity_join_condition'],
      activity_condition: messages[this.langLocale]['activity_condition'],
      activity_condition_required: messages[this.langLocale]['activity_condition_required'],
      activity_bonus: messages[this.langLocale]['activity_bonus'],
      activity_bonus_required: messages[this.langLocale]['activity_bonus_required'],
      activity_notice: messages[this.langLocale]['activity_notice'],
      activity_phase: messages[this.langLocale]['activity_phase'],
      remove_phase: messages[this.langLocale]['remove_phase'],
      add_phase: messages[this.langLocale]['add_phase'],
      upload_type: messages[this.langLocale]['upload_type'],
      upload_size: messages[this.langLocale]['upload_size'],
      picture_required: messages[this.langLocale]['picture_required'],
      name_required: messages[this.langLocale]['name_required'],
      bonus_required: messages[this.langLocale]['bonus_required'],
      bonus_number: messages[this.langLocale]['bonus_number'],
      bonus_min: messages[this.langLocale]['bonus_min'],
      bonus_max: messages[this.langLocale]['bonus_max'],
      condition_required: messages[this.langLocale]['condition_required'],
      condition_number: messages[this.langLocale]['condition_number'],
      condition_min: messages[this.langLocale]['condition_min'],
      condition_max: messages[this.langLocale]['condition_max'],
      channel: messages[this.langLocale]['channel'],
      selectedItems: Vue.ref([]),
      select_machine_type: [],
      validateBonus: (rule, value) => {
        if (!value) {
          return Promise.reject(this.bonus_required)
        }
        let r = /^\+?[1-9][0-9]*$/;//正整数
        if (!r.test(value) ) {
          return Promise.reject(this.bonus_number)
        }
        if (value <= 0) {
          return Promise.reject(this.bonus_min)
        }
        if (value > 1000000) {
          return Promise.reject(this.bonus_max)
        }
        return Promise.resolve()
      },
      validateCondition: (rule, value) => {
        if (!value) {
          return Promise.reject(this.condition_required)
        }
        let r = /^\+?[1-9][0-9]*$/;//正整数
        if (!r.test(value) ) {
          return Promise.reject(this.condition_number)
        }
        if (value <= 0) {
          return Promise.reject(this.condition_min)
        }
        if (value > 100000000) {
          return Promise.reject(this.condition_max)
        }
        return Promise.resolve()
      }
    };
  },
  //生命周期渲染完执行
  created() {
    this.newTabIndex = Vue.ref(0);
    if (this.activity.length === 0) {
      this.activity = Vue.reactive({
        id: '',
        range_time: {
          start_time: '',
          end_time: '',
        },
        dateRange: ['', ''],
        activity_content: {
          'zh-CN': {
            name: '',
            lang: 'zh-CN',
            description: '',
            join_condition: '',
            get_way: '',
            picture: [],
            id: '',
          },
          'zh-TW': {
            name: '',
            lang: 'zh-TW',
            description: '',
            join_condition: '',
            get_way: '',
            picture: [],
            id: '',
          },
          en: {
            name: '',
            lang: 'en',
            description: '',
            join_condition: '',
            get_way: '',
            picture: [],
            id: '',
          },
          jp: {
            name: '',
            lang: 'jp',
            description: '',
            join_condition: '',
            get_way: '',
            picture: [],
            id: '',
          }
        },
        activity_phase: [
          {
            key: 0,
            cate_id: '',
            cate_name: this.activity_phase,
            phase_list: [
              {
                id: '',
                condition: '',
                bonus: '',
                notice: {
                  'zh-CN': '',
                  'zh-TW': '',
                  en: '',
                  jp: '',
                },
              }
            ]
          }
        ]
      })
    } else {
      this.activity.activity_phase.forEach((activity_phase, i) => {
        this.select_machine_type.push(i)
      });
    }
  },
  //定义函数方法
  methods: {
    beforeUpload(file) {
      const isJpgOrPng = file.type === 'image/jpeg' || file.type === 'image/png' || file.type === 'image/jpg';
      if (!isJpgOrPng) {
        this.$message.error(this.upload_type);
      }
      const isLt2M = file.size / 1024 / 1024 < 5;
      if (!isLt2M) {
        this.$message.error(this.upload_size);
      }
      return isJpgOrPng && isLt2M;
    },
    cateHandleChange(value, op, index) {
      if (value !== undefined && value !== '') {
        this.activity.activity_phase[index].cate_name = op.label;
        this.activity.type = op.type;
        this.select_machine_type.push(index)
        this.categoryOptions = this.categoryOptions.forEach((options) => {
          options.options.forEach((option) => {
            option.disabled = option.type !== op.type;
          });
        });
      } else {
        this.activity.activity_phase[index].cate_name = this.activity_phase;
        let i = this.select_machine_type.indexOf(index);
        if (i !== -1) {
          this.select_machine_type.splice(i, 1)
        }
        if (this.select_machine_type.length === 0) {
          this.activity.type = '';
          this.categoryOptions = this.categoryOptions.forEach((options) => {
            options.options.forEach((option) => {
              option.disabled = false;
            });
          });
        }
      }
    },
    machineTypeChange(value) {
      this.categoryOptions = this.categoryOptions.forEach((options) => {
        options.options.forEach((option) => {
          option.disabled = option.type !== value.target._value;
        });
      });
    },
    onEdit(targetKey, action) {
      if (action === 'add') {
        this.add();
      } else {
        this.remove(targetKey);
      }
    },
    removeUser(item, i) {
      let index = this.activity.activity_phase[i].phase_list.indexOf(item);
      if (index !== -1) {
        this.activity.activity_phase[i].phase_list.splice(index, 1);
      }
    },
    remove(targetKey) {
      let i = this.select_machine_type.indexOf(targetKey);
      if (i !== -1) {
        this.select_machine_type.splice(i, 1)
      }
      if (this.select_machine_type.length === 0) {
        this.activity.type = '';
        this.categoryOptions = this.categoryOptions.forEach((options) => {
          options.options.forEach((option) => {
            option.disabled = false;
          });
        });
      }
      let lastIndex = 0;
      this.activity.activity_phase.forEach((activity_phase, i) => {
        if (i === targetKey) {
          lastIndex = i - 1;
        }
      });
      this.activity.activity_phase = this.activity.activity_phase.filter(function (activity_phase) {
        if (activity_phase.key !== targetKey) {
          return activity_phase;
        }
      });
      this.activity.activity_phase.forEach((activity_phase, i) => {
        if (activity_phase.key === this.activeKey) {
          this.activeKey = i;
        }
        let changeI = this.select_machine_type.indexOf(activity_phase.key);
        if (changeI !== -1) {
          this.select_machine_type.splice(changeI, 1)
          this.select_machine_type.push(i)
        }
        activity_phase.key = i;
      });
      if (this.activity.activity_phase.length && this.activeKey === targetKey) {
        if (lastIndex >= 0) {
          this.activeKey = this.activity.activity_phase[lastIndex].key;
        } else {
          this.activeKey = this.activity.activity_phase[0].key;
        }
      }
      if (this.activity.activity_phase.length === 0) {
        this.activeKey = 'activeKey_content';
      }
    },
    add() {
      this.activity.activity_phase.push({
        key: -1,
        cate_id: '',
        cate_name: this.activity_phase,
        phase_list: [
          {
            id: '',
            condition: '',
            bonus: '',
            notice: {
              jp: '',
              en: '',
              'zh-CN': '',
              'zh-TW': '',
            },
          }
        ]
      });
      this.activity.activity_phase.forEach((activity_phase, i) => {
        if (activity_phase.key === -1) {
          this.activeKey = i;
        }
        activity_phase.key = i;
      });
    },
    resetForm() {
      this.$refs.formRef.resetFields();
    },
    addActivityCate(index) {
      this.activity.activity_phase[index].phase_list.push({
        condition: '',
        bonus: '',
        notice: [
          {
            en: 'en',
          },
          {
            jp: 'jp',
          },
          {
            'zh-CN': 'zh-CN',
          },
          {
            'zh-TW': 'zh-TW',
          },
        ],
        id: '',
      });
    },
    onFinish(values) {
      this.$request({
        url: '/ex-admin/addons-webman-controller-ActivityController/activityOperate',
        method: 'post',
        data: values,
        header: this.headers
      }).then(res => {
        if (res.code === 200) {
          location.reload();
        }
      })
    },
    onFinishFailed(errorInfo) {
      let name = errorInfo.errorFields[0]['name'];
      if (name.length > 0) {
        switch (name[0]) {
          case 'range_time':
            this.activeKey = 'activeKey_content';
            break;
          case 'activity_content':
            this.activeKey = 'activeKey_content';
            if (name[1] !== 'undefined' && name[1] != null && name[1] !== '') {
              let ac = 0;
              for (let key in this.activity.activity_content) {
                if (key === name[1]) {
                  this.activeKey_lang_content = ac;
                }
                ac++;
              }
            }
            break;
          case 'activity_phase':
            if (name[1] !== 'undefined' && name[1] != null && name[1] !== '') {
              this.activeKey = name[1]++;
            }
        }
      }
    }
  }
}
</script>