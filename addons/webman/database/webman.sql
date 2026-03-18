/*
 Navicat Premium Data Transfer

 Source Server         :  本地
 Source Server Type    : MySQL
 Source Server Version : 50739 (5.7.39)
 Source Host           : localhost:3306
 Source Schema         : webman

 Target Server Type    : MySQL
 Target Server Version : 50739 (5.7.39)
 File Encoding         : 65001

 Date: 01/11/2022 20:09:35
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for admin_configs
-- ----------------------------
DROP TABLE IF EXISTS `admin_configs`;
CREATE TABLE `admin_configs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '配置字段',
  `value` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '配置值',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ----------------------------
-- Records of admin_configs
-- ----------------------------
BEGIN;
INSERT INTO `admin_configs` (`id`, `name`, `value`, `created_at`, `updated_at`) VALUES (1, 'web_name', 'Ex-Admin', '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_configs` (`id`, `name`, `value`, `created_at`, `updated_at`) VALUES (2, 'web_logo', '/exadmin/img/logo.png', '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_configs` (`id`, `name`, `value`, `created_at`, `updated_at`) VALUES (3, 'web_miitbeian', '', '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_configs` (`id`, `name`, `value`, `created_at`, `updated_at`) VALUES (4, 'web_copyright', '©版权所有 2014-2021', '2022-10-17 05:02:50', NULL);
COMMIT;

-- ----------------------------
-- Table structure for admin_department
-- ----------------------------
DROP TABLE IF EXISTS `admin_department`;
CREATE TABLE `admin_department` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(11) DEFAULT '0' COMMENT '上级部门',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '部门名称',
  `leader` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '负责人',
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '手机号',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1=正常,0=禁用',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部门表';

-- ----------------------------
-- Records of admin_department
-- ----------------------------
BEGIN;
INSERT INTO `admin_department` (`id`, `pid`, `name`, `leader`, `phone`, `status`, `sort`, `path`, `deleted_at`, `created_at`, `updated_at`) VALUES (1, 0, '超级管理员', '', NULL, 1, 0, '1', NULL, '2022-10-17 05:02:50', '2022-11-01 12:04:28');
COMMIT;

-- ----------------------------
-- Table structure for admin_file_attachment_cates
-- ----------------------------
DROP TABLE IF EXISTS `admin_file_attachment_cates`;
CREATE TABLE `admin_file_attachment_cates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '上级id',
  `permission_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0所有人，1仅自己',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '后台用户id',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统附件分类';

-- ----------------------------
-- Records of admin_file_attachment_cates
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for admin_file_attachments
-- ----------------------------
DROP TABLE IF EXISTS `admin_file_attachments`;
CREATE TABLE `admin_file_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cate_id` int(11) NOT NULL COMMENT '分类id',
  `uploader_id` int(11) NOT NULL DEFAULT '0' COMMENT '上传人id',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'image图片 file文件',
  `file_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件类型',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '附件名称',
  `real_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '路径',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '访问url',
  `ext` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件后缀',
  `disk` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'disk',
  `size` bigint(20) NOT NULL COMMENT '文件大小',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统附件';

-- ----------------------------
-- Records of admin_file_attachments
-- ----------------------------
BEGIN;
INSERT INTO `admin_file_attachments` (`id`, `cate_id`, `uploader_id`, `type`, `file_type`, `name`, `real_name`, `path`, `url`, `ext`, `disk`, `size`, `created_at`, `updated_at`, `deleted_at`) VALUES (1, 0, 1, 'image', 'image/png', '39380f58597e5c734118d4381348d011.png', '9V2A6493.png', 'images/39380f58597e5c734118d4381348d011.png', 'http://localhost/storage/images/39380f58597e5c734118d4381348d011.png', 'png', 'local', 2360037, '2022-11-01 18:32:17', '2022-11-01 20:08:14', '2022-11-01 20:08:14');
INSERT INTO `admin_file_attachments` (`id`, `cate_id`, `uploader_id`, `type`, `file_type`, `name`, `real_name`, `path`, `url`, `ext`, `disk`, `size`, `created_at`, `updated_at`, `deleted_at`) VALUES (2, 0, 1, 'image', 'image/png', '39380f58597e5c734118d4381348d011.png', '9V2A6493.png', 'images/39380f58597e5c734118d4381348d011.png', 'http://0.0.0.0:8787/storage/images/39380f58597e5c734118d4381348d011.png', 'png', 'local', 2360037, '2022-11-01 18:33:00', '2022-11-01 20:08:16', '2022-11-01 20:08:16');
INSERT INTO `admin_file_attachments` (`id`, `cate_id`, `uploader_id`, `type`, `file_type`, `name`, `real_name`, `path`, `url`, `ext`, `disk`, `size`, `created_at`, `updated_at`, `deleted_at`) VALUES (3, 0, 1, 'image', 'image/png', '4cc63343847a09d643800c48c97df81c.png', '720.png', 'images/4cc63343847a09d643800c48c97df81c.png', 'http://0.0.0.0:8787/storage/images/4cc63343847a09d643800c48c97df81c.png', 'png', 'local', 24337, '2022-11-01 18:50:15', '2022-11-01 20:08:18', '2022-11-01 20:08:18');
COMMIT;

-- ----------------------------
-- Table structure for admin_menus
-- ----------------------------
DROP TABLE IF EXISTS `admin_menus`;
CREATE TABLE `admin_menus` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '名称',
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '图标',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '链接',
  `plugin` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '插件名称',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '父级id',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态(0:禁用,1:启用)',
  `open` tinyint(4) NOT NULL DEFAULT '1' COMMENT '菜单展开(0:收起,1:展开)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统菜单表';

-- ----------------------------
-- Records of admin_menus
-- ----------------------------
BEGIN;
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (1, 'system', 'SettingFilled', '', '', 0, 0, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (2, 'system_manage', 'SettingFilled', '', '', 1, 1, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (3, '首页', 'fas fa-home', 'ex-admin/addons-webman-controller-IndexController/index', '', 1, 0, 1, 1, '2022-10-17 05:02:50', '2022-11-01 20:08:50');
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (4, 'config_manage', 'far fa-circle', 'ex-admin/addons-webman-controller-ConfigController/form', '', 2, 2, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (5, 'attachment_manage', 'far fa-circle', 'ex-admin/addons-webman-controller-AttachmentController/index', '', 2, 3, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (6, 'permissions_manage', 'fas fa-users', '', '', 1, 4, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (7, 'admin', 'far fa-circle', 'ex-admin/addons-webman-controller-AdminController/index', '', 6, 5, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (8, 'role_manage', 'far fa-circle', 'ex-admin/addons-webman-controller-RoleController/index', '', 6, 6, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (9, 'menu_manage', 'far fa-circle', 'ex-admin/addons-webman-controller-MenuController/index', '', 6, 7, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (10, 'department_manage', 'far fa-circle', 'ex-admin/addons-webman-controller-DepartmentController/index', '', 6, 8, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (11, 'post_manage', 'far fa-circle', 'ex-admin/addons-webman-controller-PostController/index', '', 6, 9, 1, 1, '2022-10-17 05:02:50', NULL);
INSERT INTO `admin_menus` (`id`, `name`, `icon`, `url`, `plugin`, `pid`, `sort`, `status`, `open`, `created_at`, `updated_at`) VALUES (12, 'plug_manage', 'fas fa-plug', 'ex-admin/ExAdmin-ui-plugin-Controller/index', '', 0, 10, 1, 1, '2022-10-17 05:02:50', NULL);
COMMIT;

-- ----------------------------
-- Table structure for admin_post
-- ----------------------------
DROP TABLE IF EXISTS `admin_post`;
CREATE TABLE `admin_post` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '岗位名称',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1=正常,0=禁用',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='岗位表';

-- ----------------------------
-- Records of admin_post
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for admin_role_department
-- ----------------------------
DROP TABLE IF EXISTS `admin_role_department`;
CREATE TABLE `admin_role_department` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL DEFAULT '0' COMMENT '角色id',
  `department_id` int(11) NOT NULL DEFAULT '0' COMMENT '部门id',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色数据权限部门关联表';

-- ----------------------------
-- Records of admin_role_department
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for admin_role_menus
-- ----------------------------
DROP TABLE IF EXISTS `admin_role_menus`;
CREATE TABLE `admin_role_menus` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL DEFAULT '0' COMMENT '角色id',
  `menu_id` int(11) NOT NULL DEFAULT '0' COMMENT '菜单id',
  PRIMARY KEY (`id`),
  KEY `admin_role_menus_role_id_menu_id_index` (`role_id`,`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统角色菜单表';

-- ----------------------------
-- Records of admin_role_menus
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for admin_role_permissions
-- ----------------------------
DROP TABLE IF EXISTS `admin_role_permissions`;
CREATE TABLE `admin_role_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL DEFAULT '0' COMMENT '角色id',
  `node_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '节点id',
  PRIMARY KEY (`id`),
  KEY `admin_role_permissions_role_id_node_id_index` (`role_id`,`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统角色权限表';

-- ----------------------------
-- Records of admin_role_permissions
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for admin_role_users
-- ----------------------------
DROP TABLE IF EXISTS `admin_role_users`;
CREATE TABLE `admin_role_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL DEFAULT '0' COMMENT '角色id',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  PRIMARY KEY (`id`),
  KEY `admin_role_users_role_id_user_id_index` (`role_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统角色用户表';

-- ----------------------------
-- Records of admin_role_users
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for admin_roles
-- ----------------------------
DROP TABLE IF EXISTS `admin_roles`;
CREATE TABLE `admin_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '权限角色名称',
  `desc` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备注说明',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `check_strictly` tinyint(1) NOT NULL DEFAULT '0',
  `data_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '数据权限类型:0=全部数据权限,1=自定义数据权限,2=本部门及以下数据权限,3=本部门数据权限,4=本人数据权限',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统角色表';

-- ----------------------------
-- Records of admin_roles
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for admin_users
-- ----------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '用户账号',
  `password` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '密码',
  `nickname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '姓名',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '头像',
  `email` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮箱',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '手机号',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态(0:禁用,1:启用)',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  `department_id` int(11) DEFAULT NULL COMMENT '部门id',
  `post` json DEFAULT NULL COMMENT '岗位',
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_users_username_unique` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统用户表';

-- ----------------------------
-- Records of admin_users
-- ----------------------------
BEGIN;
INSERT INTO `admin_users` (`id`, `username`, `password`, `nickname`, `avatar`, `email`, `phone`, `status`, `remember_token`, `created_at`, `updated_at`, `deleted_at`, `department_id`, `post`) VALUES (1, 'admin', '$2y$10$fU0gFdv53meVyTqcSugSfudnj/CLiNAnZ1j/X3cdHWXCTVt8DoK7G', 'admin', '/exadmin/img/logo.png', '', '', 1, '', '2022-10-17 05:02:50', '2022-11-01 12:04:11', NULL, 1, NULL);
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
