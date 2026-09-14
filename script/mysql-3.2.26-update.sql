-- ----------------------------
-- Mysql数据库升级脚本
-- 适用于PbootCMS 3.2.26
-- ----------------------------
-- 说明：
-- 1、ay_content 索引与新装 schema 对齐：补建 title 索引，删除 ay_content_unique 唯一索引。
-- 2、通过 information_schema 判存在后再执行，可安全重复执行。每条语句独立，兼容在线升级的分号切分（注释中不得出现分号）。
-- ----------------------------

--
-- 索引相关项
--
--

SET @s = (SELECT IF(COUNT(*) = 0, 'CREATE INDEX ay_content_title_index ON ay_content (title)', 'SELECT 1') FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'ay_content' AND index_name = 'ay_content_title_index');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE ay_content DROP KEY ay_content_unique', 'SELECT 1') FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'ay_content' AND index_name = 'ay_content_unique');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
