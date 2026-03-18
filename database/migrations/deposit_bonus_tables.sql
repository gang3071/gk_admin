-- ============================================
-- 充值满赠活动系统 - 数据库迁移脚本
-- 创建时间：2026-03-08
-- 说明：创建充值满赠活动系统所需的所有数据表
-- ============================================

-- 1. 活动配置表
CREATE TABLE IF NOT EXISTS `deposit_bonus_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '活动ID',
  `store_id` int(11) NOT NULL COMMENT '店家ID',
  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理ID',
  `activity_name` varchar(100) NOT NULL COMMENT '活动名称',
  `activity_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '活动类型：1=充值满赠',
  `start_time` int(11) NOT NULL COMMENT '开始时间',
  `end_time` int(11) NOT NULL COMMENT '结束时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0=停用,1=启用',

  -- 过关条件配置
  `unlock_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '解锁类型：1=押码量,2=无机台使用',
  `bet_multiple` decimal(10,2) DEFAULT 0.00 COMMENT '押码倍数（针对押码量条件）',
  `valid_days` int(11) DEFAULT 7 COMMENT '有效天数',

  -- 使用限制
  `allow_physical_machine` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否允许实体机台：0=否,1=是',
  `require_no_machine` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否要求无使用中机台：0=否,1=是',

  -- 其他配置
  `limit_per_player` int(11) DEFAULT 0 COMMENT '每人限制次数：0=不限制',
  `limit_period` varchar(20) DEFAULT 'day' COMMENT '限制周期：day=每天,week=每周,month=每月',
  `description` text COMMENT '活动说明',

  -- 审计字段
  `created_by` int(11) DEFAULT NULL COMMENT '创建人ID',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) DEFAULT NULL COMMENT '更新时间',
  `deleted_at` int(11) DEFAULT NULL COMMENT '删除时间',

  PRIMARY KEY (`id`),
  KEY `idx_store_status` (`store_id`, `status`),
  KEY `idx_agent_status` (`agent_id`, `status`),
  KEY `idx_time` (`start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值满赠活动配置表';

-- 2. 活动档位表
CREATE TABLE IF NOT EXISTS `deposit_bonus_tier` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '档位ID',
  `activity_id` int(11) NOT NULL COMMENT '活动ID',
  `deposit_amount` decimal(10,2) NOT NULL COMMENT '充值金额',
  `bonus_amount` decimal(10,2) NOT NULL COMMENT '赠送金额',
  `bonus_ratio` decimal(10,2) DEFAULT NULL COMMENT '赠送比例（%）',
  `sort_order` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0=停用,1=启用',
  `created_at` int(11) NOT NULL COMMENT '创建时间',

  PRIMARY KEY (`id`),
  KEY `idx_activity` (`activity_id`),
  KEY `idx_amount` (`deposit_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值满赠档位表';

-- 3. 赠送订单表
CREATE TABLE IF NOT EXISTS `deposit_bonus_order` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `order_no` varchar(32) NOT NULL COMMENT '订单编号',
  `activity_id` int(11) NOT NULL COMMENT '活动ID',
  `tier_id` int(11) NOT NULL COMMENT '档位ID',
  `store_id` int(11) NOT NULL COMMENT '店家ID',
  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理ID',
  `player_id` int(11) NOT NULL COMMENT '玩家ID',

  -- 金额信息
  `deposit_amount` decimal(10,2) NOT NULL COMMENT '充值金额',
  `bonus_amount` decimal(10,2) NOT NULL COMMENT '赠送金额',

  -- 押码量要求
  `required_bet_amount` decimal(10,2) DEFAULT 0.00 COMMENT '需要完成的押码量',
  `current_bet_amount` decimal(10,2) DEFAULT 0.00 COMMENT '当前已完成押码量',
  `bet_progress` decimal(5,2) DEFAULT 0.00 COMMENT '押码进度（%）',

  -- 二维码信息
  `qrcode_token` varchar(64) DEFAULT NULL COMMENT '二维码令牌',
  `qrcode_url` varchar(255) DEFAULT NULL COMMENT '二维码图片URL',
  `qrcode_expires_at` int(11) DEFAULT NULL COMMENT '二维码过期时间',

  -- 状态信息
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '订单状态：0=待核销,1=已核销,2=已完成,3=已过期,4=已取消',
  `verified_at` int(11) DEFAULT NULL COMMENT '核销时间',
  `verified_by` int(11) DEFAULT NULL COMMENT '核销人ID',
  `completed_at` int(11) DEFAULT NULL COMMENT '完成时间（押码量达标）',
  `expires_at` int(11) DEFAULT NULL COMMENT '有效期截止时间',

  -- 审计字段
  `created_by` int(11) DEFAULT NULL COMMENT '创建人ID',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) DEFAULT NULL COMMENT '更新时间',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_activity_player` (`activity_id`, `player_id`),
  KEY `idx_player_status` (`player_id`, `status`),
  KEY `idx_agent_status` (`agent_id`, `status`),
  KEY `idx_qrcode` (`qrcode_token`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值赠送订单表';

-- 4. 押码量明细表
CREATE TABLE IF NOT EXISTS `deposit_bonus_bet_detail` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '明细ID',
  `order_id` bigint(20) NOT NULL COMMENT '赠送订单ID',
  `player_id` int(11) NOT NULL COMMENT '玩家ID',
  `store_id` int(11) NOT NULL COMMENT '店家ID',
  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理ID',

  -- 游戏信息
  `game_type` varchar(50) NOT NULL COMMENT '游戏类型：slot,electron,baccarat',
  `game_platform` varchar(50) DEFAULT NULL COMMENT '游戏平台',
  `game_id` varchar(100) DEFAULT NULL COMMENT '游戏ID',
  `game_name` varchar(100) DEFAULT NULL COMMENT '游戏名称',

  -- 押注信息
  `bet_amount` decimal(10,2) NOT NULL COMMENT '押注金额',
  `win_amount` decimal(10,2) DEFAULT 0.00 COMMENT '赢取金额',
  `valid_bet_amount` decimal(10,2) NOT NULL COMMENT '有效押注金额',

  -- 余额信息
  `balance_before` decimal(10,2) NOT NULL COMMENT '押注前余额',
  `balance_after` decimal(10,2) NOT NULL COMMENT '押注后余额',

  -- 押码量累计
  `accumulated_bet` decimal(10,2) NOT NULL COMMENT '累计押码量（本次之前）',
  `new_accumulated_bet` decimal(10,2) NOT NULL COMMENT '累计押码量（本次之后）',

  -- 审计字段
  `bet_time` int(11) NOT NULL COMMENT '押注时间',
  `settle_time` int(11) DEFAULT NULL COMMENT '结算时间',
  `created_at` int(11) NOT NULL COMMENT '创建时间',

  PRIMARY KEY (`id`),
  KEY `idx_order_player` (`order_id`, `player_id`),
  KEY `idx_player_time` (`player_id`, `bet_time`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='押码量明细表';

-- 5. 玩家押码量任务表
CREATE TABLE IF NOT EXISTS `player_bonus_task` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `player_id` int(11) NOT NULL COMMENT '玩家ID',
  `store_id` int(11) NOT NULL COMMENT '店家ID',
  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理ID',
  `order_id` bigint(20) NOT NULL COMMENT '关联订单ID',

  -- 押码量要求
  `required_bet_amount` decimal(10,2) NOT NULL COMMENT '需要完成的押码量',
  `current_bet_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '当前已完成押码量',
  `bet_progress` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '完成进度（%）',

  -- 状态
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0=进行中,1=已完成,2=已过期',
  `expires_at` int(11) NOT NULL COMMENT '有效期截止时间',
  `completed_at` int(11) DEFAULT NULL COMMENT '完成时间',

  -- 审计字段
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order` (`order_id`),
  KEY `idx_player_status` (`player_id`, `status`, `expires_at`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='玩家押码量任务表';

-- 6. 账变记录扩展表
CREATE TABLE IF NOT EXISTS `player_money_edit_log_bonus` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `player_id` int(11) NOT NULL COMMENT '玩家ID',
  `store_id` int(11) NOT NULL COMMENT '店家ID',
  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理ID',
  `order_id` bigint(20) DEFAULT NULL COMMENT '关联订单ID',

  -- 变动信息
  `change_type` varchar(50) NOT NULL COMMENT '变动类型：bonus_grant=赠送发放,bonus_cancel=赠送取消',

  -- 金额信息
  `amount` decimal(10,2) NOT NULL COMMENT '变动金额',
  `balance_before` decimal(10,2) NOT NULL COMMENT '变动前余额',
  `balance_after` decimal(10,2) NOT NULL COMMENT '变动后余额',

  -- 关联信息
  `related_id` bigint(20) DEFAULT NULL COMMENT '关联业务ID',
  `related_type` varchar(50) DEFAULT NULL COMMENT '关联业务类型',

  -- 审计字段
  `operator_id` int(11) DEFAULT NULL COMMENT '操作人ID',
  `operator_type` varchar(20) DEFAULT NULL COMMENT '操作人类型：admin,player,system',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` int(11) NOT NULL COMMENT '创建时间',

  PRIMARY KEY (`id`),
  KEY `idx_player_time` (`player_id`, `created_at`),
  KEY `idx_order` (`order_id`),
  KEY `idx_type` (`change_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值满赠账变记录表';

-- 7. 活动统计表
CREATE TABLE IF NOT EXISTS `deposit_bonus_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `activity_id` int(11) NOT NULL COMMENT '活动ID',
  `store_id` int(11) NOT NULL COMMENT '店家ID',
  `agent_id` int(11) NOT NULL DEFAULT 0 COMMENT '代理ID',
  `stat_date` date NOT NULL COMMENT '统计日期',

  -- 参与数据
  `total_participants` int(11) DEFAULT 0 COMMENT '参与人数',
  `new_participants` int(11) DEFAULT 0 COMMENT '新增参与人数',
  `total_orders` int(11) DEFAULT 0 COMMENT '订单总数',

  -- 金额数据
  `total_deposit_amount` decimal(10,2) DEFAULT 0.00 COMMENT '充值总金额',
  `total_bonus_amount` decimal(10,2) DEFAULT 0.00 COMMENT '赠送总金额',
  `total_bet_amount` decimal(10,2) DEFAULT 0.00 COMMENT '总押码量',
  `total_withdraw_amount` decimal(10,2) DEFAULT 0.00 COMMENT '已提现金额',

  -- 完成数据
  `completed_orders` int(11) DEFAULT 0 COMMENT '完成押码量订单数',
  `expired_orders` int(11) DEFAULT 0 COMMENT '过期订单数',
  `cancelled_orders` int(11) DEFAULT 0 COMMENT '取消订单数',

  -- 审计字段
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  `created_at` int(11) NOT NULL COMMENT '创建时间',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_agent_date` (`activity_id`, `agent_id`, `stat_date`),
  KEY `idx_store_date` (`store_id`, `stat_date`),
  KEY `idx_agent_date` (`agent_id`, `stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='活动统计表';

-- ============================================
-- 索引优化
-- ============================================

-- 高频查询索引
ALTER TABLE `deposit_bonus_order` ADD INDEX `idx_player_active` (`player_id`, `status`, `expires_at`);
ALTER TABLE `deposit_bonus_bet_detail` ADD INDEX `idx_order_time` (`order_id`, `bet_time`);
ALTER TABLE `deposit_bonus_order` ADD INDEX `idx_qrcode_status` (`qrcode_token`, `status`);

-- 统计查询索引
ALTER TABLE `deposit_bonus_order` ADD INDEX `idx_activity_status_time` (`activity_id`, `status`, `created_at`);
ALTER TABLE `deposit_bonus_order` ADD INDEX `idx_store_time` (`store_id`, `created_at`);
ALTER TABLE `deposit_bonus_order` ADD INDEX `idx_agent_time` (`agent_id`, `created_at`);
