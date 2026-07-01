-- 修复菜单name字段中的中文和错误格式
-- 执行前请备份数据库！

-- 1. 修复带emoji和说明文本的菜单（最严重的问题）
UPDATE admin_menus SET name = 'store_lottery_ticket_management'
WHERE id = 278 AND name LIKE 'store_lottery_ticket_management%';

-- 2. 修复使用中文的菜单name（按id顺序）
UPDATE admin_menus SET name = 'channel_machine_list' WHERE id = 117 AND name = '机台列表';
UPDATE admin_menus SET name = 'promotion_management' WHERE id = 151 AND name = '推广管理';
UPDATE admin_menus SET name = 'settlement_record' WHERE id = 153 AND name = '分润结算记录';
UPDATE admin_menus SET name = 'promoter_list' WHERE id = 154 AND name = '推广员列表';
UPDATE admin_menus SET name = 'settlement_record' WHERE id = 171 AND name = '分润结算记录';
UPDATE admin_menus SET name = 'settlement_record' WHERE id = 175 AND name = '分润结算记录';
UPDATE admin_menus SET name = 'store_management' WHERE id = 193 AND name = '店家管理';
UPDATE admin_menus SET name = 'accounting_change_records' WHERE id = 196 AND name = '账变记录';
UPDATE admin_menus SET name = 'report_center' WHERE id = 197 AND name = '报表中心';
UPDATE admin_menus SET name = 'agent_management' WHERE id = 202 AND name = '代理管理';
UPDATE admin_menus SET name = 'agent_lottery_management' WHERE id = 206 AND name = '彩金管理';
UPDATE admin_menus SET name = 'store_lottery_management' WHERE id = 220 AND name = '彩金管理';
UPDATE admin_menus SET name = 'agent_game_management' WHERE id = 231 AND name = '电子游戏管理';
UPDATE admin_menus SET name = 'auto_shift_management' WHERE id = 246 AND name = '自动交班';
UPDATE admin_menus SET name = 'store_setting_manage' WHERE id = 249 AND name = '店家系统配置';
UPDATE admin_menus SET name = 'store_open_score_setting' WHERE id = 250 AND name = '店家开分配置';
UPDATE admin_menus SET name = 'agent_store_profit_report' WHERE id = 252 AND name = '店家分润报表';
UPDATE admin_menus SET name = 'channel_store_profit_report' WHERE id = 258 AND name = '店家分润报表';
UPDATE admin_menus SET name = 'agent_lottery_ticket_management' WHERE id = 274 AND name = '摸奖券管理';

-- 3. 验证修复结果（可选）
-- SELECT id, name, url, type FROM admin_menus WHERE id IN (117,151,153,154,171,175,193,196,197,202,206,220,231,246,249,250,252,258,274,278);

-- 4. 清理可能存在的其他带emoji或特殊字符的菜单name
-- UPDATE admin_menus SET name = TRIM(SUBSTRING_INDEX(name, ' ', 1)) WHERE name REGEXP '[🔒\\(\\)]';

COMMIT;
