-- ----------------------------
-- Sqlite数据库升级脚本
-- 适用于PbootCMS 3.2.26
-- ----------------------------
-- 说明：ay_content 索引与新装 schema 对齐，IF EXISTS / IF NOT EXISTS 保证可重复执行。
-- ----------------------------

CREATE INDEX IF NOT EXISTS ay_content_title_index ON ay_content (title);

DROP INDEX IF EXISTS ay_content_unique;
