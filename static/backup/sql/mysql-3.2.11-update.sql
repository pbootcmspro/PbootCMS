-- ----------------------------
-- Mysql数据库升级脚本
-- 适用于PbootCMS 3.2.11
-- ----------------------------

-- 删除Polyfill相关配置
DELETE FROM `ay_config` WHERE `name` = 'use_polyfill';


