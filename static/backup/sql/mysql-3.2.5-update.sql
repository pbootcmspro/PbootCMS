-- ----------------------------
-- Mysql数据库升级脚本
-- 适用于PbootCMS 3.2.5
-- ----------------------------


INSERT INTO `ay_config` (`id`,`name`,`value`,`type`,`sorting`,`description`) VALUES
    ('44','use_polyfill','0','2','255','是否使用Polyfill');