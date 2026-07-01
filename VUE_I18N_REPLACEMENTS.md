# Vue 组件多语言替换清单

## lottery_ticket_activities.vue 需要替换的硬编码文本

### 模板部分替换

```vue
<!-- 原文 --> 录入券号发放
<!-- 替换为 --> {{ trans.distributeByTicket }}

<!-- 原文 --> 查看发放列表  
<!-- 替换为 --> {{ trans.viewTicketList }}

<!-- 原文 --> 活动封面图片
<!-- 替换为 --> {{ trans.coverImage }}

<!-- 原文 --> 选择图片
<!-- 替换为 --> {{ trans.selectImage }}

<!-- 原文 --> 建议尺寸：750x400px，支持jpg、png格式，文件大小不超过2MB
<!-- 替换为 --> {{ trans.coverImageHelp }}

<!-- 原文 --> alt="活动封面"
<!-- 替换为 --> :alt="trans.coverAlt"

<!-- 原文 --> alt="封面预览"
<!-- 替换为 --> :alt="trans.coverPreview"

<!-- 原文 --> VIP等级打码量配置
<!-- 替换为 --> {{ trans.vipConfigSection }}

<!-- 原文 --> 为每个VIP等级配置达到指定打码量后发放的摸奖券数量
<!-- 替换为 --> {{ trans.vipConfigHint }}

<!-- 原文 --> VIP等级
<!-- 替换为 --> {{ trans.vipLevel }}

<!-- 原文 --> 所需打码量
<!-- 替换为 --> {{ trans.betAmountRequired }}

<!-- 原文 --> 发放券数
<!-- 替换为 --> {{ trans.ticketCount }}

<!-- 原文 --> 暂无VIP等级数据
<!-- 替换为 --> {{ trans.noVipData }}

<!-- 原文 --> 奖品等级配置
<!-- 替换为 --> {{ trans.prizeConfigSection }}

<!-- 原文 --> 配置奖品等级和奖励金额(仅现金奖励)
<!-- 替换为 --> {{ trans.prizeConfigHint }}

<!-- 原文 --> 奖励金额
<!-- 替换为 --> {{ trans.prizeAmount }}

<!-- 原文 --> 奖品数量
<!-- 替换为 --> {{ trans.prizeCount }}

<!-- 原文 --> VIP等级配置
<!-- 替换为 --> {{ trans.prizeLevelConfig }}

<!-- 原文 --> 未配置VIP等级
<!-- 替换为 --> {{ trans.noVipConfig }}

<!-- 原文 --> 输入券号:
<!-- 替换为 --> {{ trans.inputTicketNo }}

<!-- 原文 --> 添加券号
<!-- 替换为 --> {{ trans.addTicket }}

<!-- 原文 --> 取消
<!-- 替换为 --> {{ trans.cancel }}

<!-- 原文 --> 提交
<!-- 替换为 --> {{ trans.submit }}

<!-- 原文 --> 摸奖券发放列表
<!-- 替换为 --> {{ trans.modalTicketListTitle }}

<!-- 原文 --> 录入券号发放奖励
<!-- 替换为 --> {{ trans.modalDistributeTitle }}

<!-- 原文 --> 请输入中奖券号，系统将根据券号自动识别奖品等级并发放奖励
<!-- 替换为 --> {{ trans.distributeHint }}

<!-- 原文 --> 活动名称
<!-- 替换为 --> {{ trans.activityName }}

<!-- 原文 --> 中奖券号
<!-- 替换为 --> {{ trans.ticketNoInput }}

<!-- 原文 --> 请输入券号
<!-- 替换为 --> {{ trans.ticketNoRequired }}

<!-- 原文 --> 请输入6位券号
<!-- 替换为 --> :placeholder="trans.ticketNoPlaceholder"

<!-- 原文 --> 发放备注
<!-- 替换为 --> {{ trans.distributionRemark }}

<!-- 原文 --> 选填，可备注发放说明
<!-- 替换为 --> :placeholder="trans.distributeRemarkPlaceholder"

<!-- 原文 --> 确认发放
<!-- 替换为 --> {{ trans.confirmDistribute }}

<!-- 原文 --> 元
<!-- 替换为 --> {{ trans.yuan }}
```

### JavaScript 部分替换

```javascript
// 原文：showTotal: (total) => `共 ${total} 条`,
// 替换为：showTotal: (total) => this.trans.totalRecords?.replace('{total}', total) || `共 ${total} 条`,

// 原文：{required: true, message: '请输入活动名称', trigger: 'blur'}
// 替换为：{required: true, message: this.trans.nameRequired, trigger: 'blur'}

// 原文：{max: 100, message: '活动名称不能超过100个字符', trigger: 'blur'}
// 替换为：{max: 100, message: this.trans.nameMaxLength, trigger: 'blur'}

// 原文：{required: true, message: '请选择开始时间', trigger: 'change'}
// 替换为：{required: true, message: this.trans.startTimeRequired, trigger: 'change'}

// 原文：{required: true, message: '请选择结束时间', trigger: 'change'}
// 替换为：{required: true, message: this.trans.endTimeRequired, trigger: 'change'}

// 原文：{title: '等级', key: 'level_name', dataIndex: 'level_name'}
// 替换为：{title: this.trans.level, key: 'level_name', dataIndex: 'level_name'}

// 原文：{title: '奖励金额', key: 'prize_amount', dataIndex: 'prize_amount'}
// 替换为：{title: this.trans.prizeAmount, key: 'prize_amount', dataIndex: 'prize_amount'}

// 原文：{title: 'VIP等级', key: 'vip_level_name', dataIndex: 'vip_level_id'}
// 替换为：{title: this.trans.vipLevel, key: 'vip_level_name', dataIndex: 'vip_level_id'}

// 原文：{title: '所需打码量', key: 'bet_amount_required', dataIndex: 'bet_amount_required'}
// 替换为：{title: this.trans.betAmountRequired, key: 'bet_amount_required', dataIndex: 'bet_amount_required'}

// 原文：{title: '发放券数', key: 'ticket_count', dataIndex: 'ticket_count'}
// 替换为：{title: this.trans.ticketCount, key: 'ticket_count', dataIndex: 'ticket_count'}

// 原文：{title: '券号', key: 'ticket_no', dataIndex: 'ticket_no', width: 160, ellipsis: true}
// 替换为：{title: this.trans.ticketNo, key: 'ticket_no', dataIndex: 'ticket_no', width: 160, ellipsis: true}

// 原文：{title: '玩家', key: 'player_name', dataIndex: 'player_name', width: 100}
// 替换为：{title: this.trans.playerName, key: 'player_name', dataIndex: 'player_name', width: 100}

// 原文：{title: '来源', key: 'source', dataIndex: 'source', width: 90}
// 替换为：{title: this.trans.source, key: 'source', dataIndex: 'source', width: 90}

// 原文：{title: '状态', key: 'status', dataIndex: 'status', width: 90}
// 替换为：{title: this.trans.status, key: 'status', dataIndex: 'status', width: 90}

// 原文：{title: '发放时间', key: 'created_at', dataIndex: 'created_at', width: 150}
// 替换为：{title: this.trans.createdAt, key: 'created_at', dataIndex: 'created_at', width: 150}

// 原文：{title: '使用时间', key: 'used_at', dataIndex: 'used_at', width: 150}
// 替换为：{title: this.trans.usedAt, key: 'used_at', dataIndex: 'used_at', width: 150}

// 错误消息替换
// this.$message.error('获取活动列表失败') 
// 替换为: this.$message.error(res.message || res.msg || this.trans.fetchFailed)

// this.$message.error('只能上传 JPG/PNG 格式的图片！')
// 替换为: this.$message.error(this.trans.imageFormatError)

// this.$message.error('图片大小不能超过 2MB！')
// 替换为: this.$message.error(this.trans.imageSizeError)

// this.$message.success('图片上传成功')
// 替换为: this.$message.success(this.trans.imageUploadSuccess)

// this.$message.error('图片上传失败')
// 替换为: this.$message.error(res.message || this.trans.imageUploadFailed)

// this.$message.warning('该活动尚未配置奖品等级')
// 替换为: this.$message.warning(this.trans.noPrizeLevel)

// this.$message.error('获取活动详情失败')
// 替换为: this.$message.error(this.trans.fetchDetailFailed)

// this.$message.warning('请至少输入一个券号')
// 替换为: this.$message.warning(this.trans.minOneTicket)

// this.$message.success(`成功录入 ${res.data.success_count} 条中奖记录`)
// 替换为: this.$message.success(this.trans.recordSuccessCount.replace('{count}', res.data.success_count))

// this.$message.error('请输入券号')
// 替换为: this.$message.error(this.trans.pleaseInputTicket)

// this.$message.error('券号必须是6位数字')
// 替换为: this.$message.error(this.trans.ticketMust6Digits)

// this.$message.error('关闭活动失败')
// 替换为: this.$message.error(this.trans.closeActivityFailed)

```

## 总替换数量统计

- 模板硬编码文本：约 40 处
- JavaScript 字符串：约 50 处
- 总计：约 90 处需要替换

## 注意事项

1. 所有 `{ required: true, message: '...' }` 形式的验证消息都需要替换
2. 所有 `this.$message.error('...')` 的错误提示都需要替换
3. 所有 table columns 的 title 都需要替换
4. 所有 placeholder 都需要使用 trans 传递的值
5. 动态文本（如`共 ${total} 条`）需要使用字符串替换方法

