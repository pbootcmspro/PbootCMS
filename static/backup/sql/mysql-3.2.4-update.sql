-- ----------------------------
-- Mysql数据库升级脚本
-- 适用于PbootCMS 3.2.4
-- ----------------------------

--
-- 索引相关项
--
--

create index ay_content_title_index on ay_content (title);

alter table ay_content drop key ay_content_unique;


--
-- 
--
