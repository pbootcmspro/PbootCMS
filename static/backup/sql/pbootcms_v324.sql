-- Online Database Management SQL Dump
-- 数据库名: pbootcms
-- 生成日期: 2020-07-05 23:55:34
-- PHP 版本: 5.6.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";
SET NAMES utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ay_area`
--

DROP TABLE IF EXISTS `ay_area`;
CREATE TABLE `ay_area` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '区域编号',
  `acode` varchar(20) NOT NULL COMMENT '区域编码',
  `pcode` varchar(20) NOT NULL COMMENT '区域父编码',
  `name` varchar(50) NOT NULL COMMENT '区域名称',
  `domain` varchar(100) NOT NULL COMMENT '区域绑定域名',
  `is_default` char(1) NOT NULL DEFAULT '0' COMMENT '是否默认',
  `create_user` varchar(30) NOT NULL COMMENT '添加人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '添加时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_area_acode` (`acode`),
  KEY `ay_area_pcode` (`pcode`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_area`
--

INSERT INTO `ay_area` (`id`,`acode`,`pcode`,`name`,`domain`,`is_default`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','cn','0','中文','','1','admin','admin','2017-11-30 13:55:37','2018-04-13 11:40:49');

-- --------------------------------------------------------

--
-- 表的结构 `ay_company`
--

DROP TABLE IF EXISTS `ay_company`;
CREATE TABLE `ay_company` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '站点编号',
  `acode` varchar(20) NOT NULL COMMENT '区域代码',
  `name` varchar(100) NOT NULL COMMENT '公司名称',
  `address` varchar(200) NOT NULL COMMENT '公司地址',
  `postcode` varchar(6) NOT NULL COMMENT '邮政编码',
  `contact` varchar(10) NOT NULL COMMENT '公司联系人',
  `mobile` varchar(50) NOT NULL COMMENT '手机号码',
  `phone` varchar(50) NOT NULL COMMENT '电话号码',
  `fax` varchar(50) NOT NULL COMMENT '公司传真',
  `email` varchar(30) NOT NULL COMMENT '电子邮箱',
  `qq` varchar(50) NOT NULL COMMENT '公司QQ',
  `weixin` varchar(100) NOT NULL COMMENT '微信图标',
  `blicense` varchar(20) NOT NULL COMMENT '营业执照代码',
  `other` varchar(200) NOT NULL COMMENT '其他信息',
  PRIMARY KEY (`id`),
  KEY `ay_company_acode` (`acode`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_company`
--

INSERT INTO `ay_company` (`id`,`acode`,`name`,`address`,`postcode`,`contact`,`mobile`,`phone`,`fax`,`email`,`qq`,`weixin`,`blicense`,`other`) VALUES
('1','cn','湖南某某网络科技有限公司','湖南长沙岳麓区雷锋大道888号','410000','谢先生','13988886666','0731-88886666','0731-88886666','admin@pbootcms.com','88886666','/static/upload/image/20180715/1531651052464521.png','91430102567888888G','');

-- --------------------------------------------------------

--
-- 表的结构 `ay_config`
--

DROP TABLE IF EXISTS `ay_config`;
CREATE TABLE `ay_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `name` varchar(30) NOT NULL COMMENT '名称',
  `value` varchar(200) NOT NULL COMMENT '值',
  `type` char(1) NOT NULL DEFAULT '1' COMMENT '配置类型',
  `sorting` int(10) unsigned NOT NULL DEFAULT '255' COMMENT '排序',
  `description` varchar(30) NOT NULL COMMENT '描述文本',
  PRIMARY KEY (`id`),
  KEY `ay_config_name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=43 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_config`
--

INSERT INTO `ay_config` (`id`,`name`,`value`,`type`,`sorting`,`description`) VALUES
('1','open_wap','0','1','255','手机版'),
('2','message_check_code','1','1','255','留言验证码'),
('3','smtp_server','smtp.qq.com','2','255','邮件SMTP服务器'),
('4','smtp_port','465','2','255','邮件SMTP端口'),
('5','smtp_ssl','1','1','255','邮件是否安全连接'),
('6','smtp_username','','2','255','邮件发送账号'),
('7','smtp_password','','2','255','邮件发送密码'),
('8','admin_check_code','1','1','255','后台验证码'),
('9','weixin_appid','','2','255','微信APPID'),
('10','weixin_secret','','2','255','微信SECRET'),
('11','message_send_mail','1','1','255','留言发送邮件开关'),
('12','message_send_to','','1','255','留言发送到邮箱'),
('13','api_open','0','2','255','API开关'),
('14','api_auth','1','2','255','API强制认证'),
('15','api_appid','','2','255','API认证用户'),
('16','api_secret','','2','255','API认证密钥'),
('17','baidu_zz_token','','2','255','百度站长密钥'),
('18','baidu_xzh_appid','','2','255','熊掌号appid'),
('19','baidu_xzh_token','','2','255','熊掌号token'),
('20','wap_domain','','2','255','手机绑定域名'),
('21','gzip','0','2','255','GZIP压缩'),
('22','content_tags_replace_num','','2','255','内容关键字替换次数'),
('23','smtp_username_test','','2','255','测试邮箱'),
('24','form_send_mail','0','2','255','表单发送邮件'),
('25','baidu_xzh_type','0','2','255','熊掌号推送类型'),
('26','watermark_open','0','2','255','水印开关'),
('27','watermark_text','PbootCMS','2','255','水印文本'),
('28','watermark_text_font','','2','255','水印文本字体'),
('29','watermark_text_size','20','2','255','水印文本字号'),
('30','watermark_text_color','100,100,100','2','255','水印文本字体颜色'),
('31','watermark_pic','/static/images/logo.png','2','255','水印图片'),
('32','watermark_position','4','2','255','水印位置'),
('33','message_verify','1','2','255','留言审核'),
('34','form_check_code','0','2','255','表单验证码'),
('35','lock_count','5','2','255','登陆锁定阈值'),
('36','lock_time','900','2','255','登录锁定时间'),
('37','url_rule_type','3','2','255','路径类型'),
('38','message_status','1','2','255','留言开关'),
('39','form_status','1','2','255','表单开关'),
('40','tpl_html_dir','html','2','255','模板HTML目录'),
('41','ip_deny','','2','255','IP黑名单'),
('42','ip_allow','','2','255','IP白名单'),
('43','url_index_404','0','2','255','跳转404');

-- --------------------------------------------------------

--
-- 表的结构 `ay_content`
--

DROP TABLE IF EXISTS `ay_content`;
CREATE TABLE `ay_content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `acode` varchar(20) NOT NULL COMMENT '区域',
  `scode` varchar(20) NOT NULL COMMENT '内容栏目',
  `subscode` varchar(20) NOT NULL COMMENT '副栏目',
  `title` varchar(100) NOT NULL COMMENT '标题',
  `titlecolor` varchar(7) NOT NULL COMMENT '标题颜色',
  `subtitle` varchar(100) NOT NULL COMMENT '副标题',
  `filename` varchar(50) NOT NULL COMMENT '自定义文件名',
  `author` varchar(30) NOT NULL COMMENT '作者',
  `source` varchar(30) NOT NULL COMMENT '来源',
  `outlink` varchar(100) NOT NULL COMMENT '外链地址',
  `date` datetime NOT NULL COMMENT '发布日期',
  `ico` varchar(100) NOT NULL COMMENT '缩略图',
  `pics` varchar(1000) NOT NULL COMMENT '多图片',
  `picstitle` varchar(1000) NOT NULL COMMENT '多图片标题',
  `content` mediumtext NOT NULL COMMENT '内容',
  `tags` varchar(500) NOT NULL COMMENT 'tag关键字',
  `enclosure` varchar(100) NOT NULL COMMENT '附件',
  `keywords` varchar(200) NOT NULL COMMENT '关键字',
  `description` varchar(500) NOT NULL COMMENT '描述',
  `sorting` int(10) unsigned NOT NULL DEFAULT '255' COMMENT '内容排序',
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `istop` char(1) NOT NULL DEFAULT '0' COMMENT '是否置顶',
  `isrecommend` char(1) NOT NULL DEFAULT '0' COMMENT '是否推荐',
  `isheadline` char(1) NOT NULL DEFAULT '0' COMMENT '是否头条',
  `visits` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '访问数',
  `likes` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '点赞数',
  `oppose` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '反对数',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `update_user` varchar(20) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `gtype` char(1) NOT NULL DEFAULT '4',
  `gid` varchar(20) NOT NULL DEFAULT '',
  `gnote` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_content_unique` (`sorting`,`istop`,`isrecommend`,`isheadline`,`date`,`id`),
  KEY `ay_content_scode` (`scode`),
  KEY `ay_content_subscode` (`subscode`),
  KEY `ay_content_acode` (`acode`),
  KEY `ay_content_filename` (`filename`),
  KEY `ay_content_date` (`date`),
  KEY `ay_content_sorting` (`sorting`),
  KEY `ay_content_status` (`status`),
  KEY `ay_content_title_index` (`title`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_content`
--

INSERT INTO `ay_content` (`id`,`acode`,`scode`,`subscode`,`title`,`titlecolor`,`subtitle`,`filename`,`author`,`source`,`outlink`,`date`,`ico`,`pics`,`content`,`tags`,`enclosure`,`keywords`,`description`,`sorting`,`status`,`istop`,`isrecommend`,`isheadline`,`visits`,`likes`,`oppose`,`create_user`,`update_user`,`create_time`,`update_time`,`gtype`,`gid`,`gnote`) VALUES
('1','cn','1','','公司简介','#333333','','','admin','本站','','2018-04-11 17:26:11','','','<p>
    &nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size: 18px;">PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、
 强悍的可免费商用的PHP 
CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。</span>
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;1、系统采用高效、简洁、强悍的模板标签，只要懂HTML就可快速开发企业网站；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;2、系统采用PHP语言开发，使用自主研发的高速多层开发框架及缓存技术；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;3、系统默认采用sqlite轻型数据库，放入PHP空间即可直接使用，可选mysql等数据库，满足各类存储需求；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;4、系统采用响应式管理后台，满足各类设备随时管理的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;5、系统支持后台在线升级，满足系统及时升级更新的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;6、系统支持内容模型、多语言、自定义表单、筛选、多条件搜索、小程序、APP等功能；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;7、系统支持多种URL模式及模型、栏目、内容自定义地址名称，满足各类网站推广优化的需要。<br/>
</p>
<p>
    <br/>
</p>
<p>
    <strong><span style="font-size: 18px;">源码托管地址：</span></strong>
</p>
<p>
    GitHub：<a href="https://github.com/hnaoyun/PbootCMS" target="_blank">https://github.com/hnaoyun/PbootCMS</a><br/>
</p>
<p>
    Gitee：<a href="https://gitee.com/hnaoyun/PbootCMS" target="_blank" title="https://gitee.com/hnaoyun/PbootCMS">https://gitee.com/hnaoyun/PbootCMS</a>
</p>
<p>
    <strong><span style="font-size: 18px;"><br/></span></strong>
</p>
<p>
    <strong><span style="font-size: 18px;">简单到想哭的标签：</span></strong>
</p>
<pre class="brush:html;toolbar:false">1、全局标签示意：
{pboot:sitetitle} 站点标题 
{pboot:sitelogo} 站点logo
2、列表页标签示意：
{pboot:list num=10 order=date}    
    <p><a href="[list:link]">[list:title]</a></p>
{/pboot:list}
3、内容页标签示意：
{content:title} 标题
{content:subtitle}副标题
{content:author} 作者
{content:source} 来源
更多简单到想哭的标签请参考开发手册...</pre>','','','','PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企','255','1','0','0','0','37','0','0','admin','admin','2018-04-11 17:26:11','2019-08-05 11:19:51','4','',''),
('2','cn','10','','在线留言','#333333','','','admin','本站','','2018-04-11 17:30:36','','','','','','','','255','1','0','0','0','26','0','0','admin','admin','2018-04-11 17:30:36','2018-04-11 17:30:36','4','',''),
('3','cn','11','','联系我们','#333333','','','admin','本站','','2018-04-11 17:31:29','','','<p>官方网站：<a href="http://www.pbootcms.com">www.pbootcms.com</a><br/></p><p>技术交流群： 137083872</p><p><br/></p><p>我们一直秉承大道至简分享便可改变世界的理念，坚持做最简约灵活的PbootCMS开源软件！</p><p>您的每一份帮助都将支持PbootCMS做的更好，走的更远！</p><p>我们一直在坚持不懈地努力，并尽可能让PbootCMS完全开源免费，您的帮助将使我们更有动力和信心^_^！</p><p>扫一扫官网付款码赞助我们，您的支持是开发者不断前进的动力！</p><p><br/></p><p><strong>您的每一份捐赠将用来：</strong></p><p>深入PbootCMS核心的开发、</p><p>做丰富的应用；</p><p>设计更爽的用户界面；</p><p>吸引更多的模板开发者和应用开发者；</p><p>奖励更多优秀贡献者。</p><p>把PbootCMS技术交流群137083872推荐给伱自己有兴趣的群做宣传，也是对我们的帮助哟！~~</p><p><img src="/static/upload/image/20180413/1523583018133454.png"/></p><p><br/></p>','','','','','255','1','0','0','0','18','0','0','admin','admin','2018-04-11 17:31:29','2018-04-13 09:30:19','4','',''),
('4','cn','3','','PbootCMSV1.0.0正式发布','#333333','','','admin','本站','','2018-04-12 20:30:00','/static/upload/image/20180412/1523499864406172.jpg','','<p>
    &nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size: 18px;">PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、
 强悍的可免费商用的PHP 
CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。</span>
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;1、系统采用高效、简洁、强悍的模板标签，只要懂HTML就可快速开发企业网站；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;2、系统采用PHP语言开发，使用自主研发的高速多层开发框架及缓存技术；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;3、系统默认采用sqlite轻型数据库，放入PHP空间即可直接使用，可选mysql等数据库，满足各类存储需求；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;4、系统采用响应式管理后台，满足各类设备随时管理的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;5、系统支持后台在线升级，满足系统及时升级更新的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;6、系统支持内容模型、多语言、自定义表单、筛选、多条件搜索、小程序、APP等功能；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;7、系统支持多种URL模式及模型、栏目、内容自定义地址名称，满足各类网站推广优化的需要。<br/>
</p>
<p>
    <br/>
</p>
<p>
    <strong><span style="font-size: 18px;">源码托管地址：</span></strong>
</p>
<p>
    GitHub：<a href="https://github.com/hnaoyun/PbootCMS" target="_blank">https://github.com/hnaoyun/PbootCMS</a><br/>
</p>
<p>
    Gitee：<a href="https://gitee.com/hnaoyun/PbootCMS" target="_blank" title="https://gitee.com/hnaoyun/PbootCMS">https://gitee.com/hnaoyun/PbootCMS</a>
</p>
<p>
    <strong><span style="font-size: 18px;"><br/></span></strong>
</p>
<p>
    <strong><span style="font-size: 18px;">简单到想哭的标签：</span></strong>
</p>
<pre class="brush:html;toolbar:false">1、全局标签示意：
{pboot:sitetitle} 站点标题 
{pboot:sitelogo} 站点logo
2、列表页标签示意：
{pboot:list num=10 order=date}    
    <p><a href="[list:link]">[list:title]</a></p>
{/pboot:list}
3、内容页标签示意：
{content:title} 标题
{content:subtitle}副标题
{content:author} 作者
{content:source} 来源
更多简单到想哭的标签请参考开发手册...</pre>','','','','PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企','255','1','1','0','0','4','0','0','admin','admin','2018-04-11 17:43:19','2019-08-05 11:19:39','4','',''),
('5','cn','4','','华为云：打造游戏创新智能世界的“黑土地”','#333333','','','admin','本站','','2018-04-12 09:52:36','','','<p style="text-indent: 2em; text-align: left;">【<strong>PConline资讯</strong>】2018年4月2日，在GMGC北京2018第七届全球游戏大会现场，记者有幸采访到了华为消费互联网解决方案总经理聂颂，他分享了游戏行业创新发展的技术基石，以及作为游戏创新要素的AI、5G、区块链等技术会为创新者带来哪些价值。</p><p class="detailPic"><img src="/static/upload/image/20180413/1523583403755896.jpeg"/></p><p style="text-indent: 2em; text-align: center;">华为消费互联网解决方案总经理聂颂</p><p style="text-indent: 2em; text-align: left;">对于游戏创新，聂颂特别强调了技术这块“黑土地”的重要性：单机版扫雷游戏盛行的背后是IntelCPU和Windows操作系统的支撑；PC互联、线下支付以及IDC技术的成熟让游戏“传奇”的时代来临；2017年中国游戏2189亿人民币收入的背后，是4G、Wifi网络、移动支付、智能手机和<span class="hrefStyle">云计算</span>的成熟。而2018年，5G、区块链、AI、AR／VR的普及会给游戏行业带来玩法、服务以及场景上的多维度创新。</p><p style="text-indent: 2em; text-align: left;">对此，聂颂首先表示，在游戏解决方案上，<span class="hrefStyle">华为云</span>不做游戏产品，不与游戏企业争利，坚持做游戏企业的发动机和生产力。华为云游戏解决方案目前已经构建了游戏研发、游戏部署、游戏运营、游戏创新等全产业链条的能力。未来华为云将在游戏行业发力的几大方向：</p><p style="text-indent: 2em; text-align: left;">第一，是基础设施层面，过去几年游戏行业使用最多的产品是云主机虚拟机，华为云主机的性能优异，裸金属服务被第三方机构评为年度影响力产品。</p><p style="text-indent: 2em; text-align: left;">第二，是云容器产品方面，由于容器对于游戏部署来说意义重大，能够支撑游戏产品架构的演进。游戏企业通过使用容器，服务器部署的弹性速度提高10倍以上，并将扩展区服的时间降低到分钟级，整个运营成本降低超过50％。</p><p style="text-indent: 2em; text-align: left;">第三，在AI能力层面，华为作为业界在“云＋终”端同时具有芯片级别研发的公司，在终端侧，可以进行人脸识别；在云端可以进行大数据分析、视频分析、视觉认知；在架构底层，华为云使用了Atlas、GPU、FPGA等硬件为AI定制算力，从而可以在性能、延迟等方面满足游戏公司的需求。</p><p style="text-indent: 2em; text-align: left;">第四，华为的云游戏实现了即看即玩。游戏免修改、多个用户一起玩直播的游戏社交游戏让客户体验大幅提升。</p><p style="text-indent: 2em; text-align: left;">第五，在区块链层面，华为在十分钟之内就可以部署完整的区块链系统，每秒运算能力高达2000TPS，轻松帮助游戏客户实现不同游戏道具类的自由交换。</p><p style="text-indent: 2em; text-align: left;">最后，聂颂特别强调了华为终端的优势：超过3亿的注册手机用户，华为开发者联盟超过37万的用户，应用市场下载量450亿＋以及华为云100＋的服务。在此次大会上，华为还发布了与消费者云的端云联合计划，已注册认证的消费者云开发者可以获得端云协同大礼包，后续针对开发者创新等，华为云也会陆续推出更大更好的扶持计划。</p><p>随着游戏用户规模逐渐从增量市场转向存量市场，游戏市场正从买量用户数转向追求极致体验，以华为打造智能世界的“黑土地”为沃土，2018年中国游戏市场创新会有哪些改变，让我们拭目以待！</p><p><br/></p><p><br/></p>','','','','','255','1','0','0','0','4','0','0','admin','admin','2018-04-12 10:06:15','2018-04-13 09:36:44','4','',''),
('6','cn','4','','锤子6年了 我们找到了它没有死的秘密','#333333','','','admin','本站','','2018-04-12 10:06:22','/static/upload/image/20180412/1523499864406172.jpg','','<p>他有些戏谑意味地取了“锤子”这个名字。此前抡锤砸西门子冰箱的“壮举”让他一举成名，他想在手机圈里也搞出类似的动静来。这似乎预兆了他此后几年的命运：刺激。</p><p>另一个预兆发生在那年夏天。锤子办公室从中关村搬去望京，装车时突然电闪雷鸣暴雨如注。罗永浩站在旧办公室的窗边，念叨着“好了好了，我都知道了”，没多久，雷声停了雨也小了，似乎是天气与他达成了和解。</p><p><strong>“和解”是老罗锤子六年的另一个主题。从某种程度上说，这是他得以从手机死亡谷幸存的秘诀，但所有的得到都有代价，老罗祭出的牺牲品之一，就是曾经那个“罗永浩可爱多”。</strong></p><p><img src="https://static.cnbetacdn.com/article/2018/0411/1a9c9d6c1c93b7a.jpg"/></p><p><strong>壹</strong></p><p>锤子4月9日在北工大举办的发布会没有形成刷屏之势。</p><p>有锤粉觉得意外，场内人看来却是正常。<strong>除了性价比，当天发布的坚果3实在乏善可陈，就连素来精彩的老罗演讲，也如同这个季节开败了的玉兰花，蔫蔫的让人打不起精神。散场之后，有锤粉在微信群里讨论，比刚才谁睡着的时间更长。</strong></p><p>罗永浩选择了“怼”。发布会结束他就发了条微博：“回来看了一下网上的反馈，很多用翔的人都说丑，嗯，肯定会卖得很好，放心睡了。”第二天他又在微信公众号里称，那些骂坚果3丑的人是笨蛋。</p><p>依然是天生骄傲的语气，但配方似乎与6年前已经不一样了。</p><p>那时他讨伐的对象是小米。2012年是小米模式突飞猛进备受赞誉的一年，截至11月底，小米销售额已经突破100亿人民币——华为和酷派实现这个数字都花了6年，而此时距离雷军喝下那碗小米汤不过短短2年。</p><p>但罗永浩不服。</p><p>他很快展示他过人的毒舌功力，嘲讽小米是“手机期货”、“耍猴式营销”……他甚至为自己的犀利洋洋得意，“雷军确实被我们逼得重视设计和假装有人文情怀了”。</p><p>但出来混总是要还的，“产能”在此后几年成为罗永浩的紧箍咒，感受到切肤之痛后，他向雷军转达了歉意、感慨做产品不容易。</p><p>不过那都是后话了，回到2012年，毫无疑问，罗永浩赶上了智能手机的大风口。</p><p>热潮之中，很多巨变已经初见端倪。</p><p>小米自然是最炙手可热的明星，锤子就直接复制了它的早期模式：先做ROM再做手机。更多的大厂商还没反应过来，华为要在2013年才推出互联网品牌“荣耀”，魅族要在更晚的2014年才有“魅蓝”，至于联想的ZUK，那就是2015年迟到的故事了。</p><p>更多关于颠覆的故事在苹果之外的手机厂商间上演：</p><p>诺基亚连续14年手机老大的位置被三星替代，铁娘子王雪红带领HTC完成精品战略转型，坐上手机老二的位置。黑莓生厂商RIM
 选择了一条危险的道路：黑莓10成为放手一搏的产品，但它从2012年拖到2013年才面世，不情不愿发布触屏版的同时，还傲娇地保留了物理键盘板。</p><p>当时RIM还是很乐观的。时任CEO托斯滕·海因斯在谈及诺基亚的衰落时曾说，“我们现在拥有大约8000万名用户——这是诺基亚所不具备的。”但现实却是，尽管黑莓手机有奥巴马、Lady
 Gaga等一众粉丝，但随着黑莓公司在今年愚人节关闭BB OS 服务，最终，黑莓与诺基亚一样，把辉煌留给了历史。</p><p>风起云涌间，罗永浩掀起的波澜似乎多少带着点玩闹的成分。本来就有很多人抱着看笑话的心态，准备围观这位相声演员、英语老师如何玩砸，偏偏老罗还献上了料：</p><p>原先定在2012年年底发布的ROM跳票到次年3月，又因为工程师严重不足导致很多功能无法实现，加上发布会现场拖堂严重、网络瘫痪等原因，总之，那成为一场堪称“糟糕”的亮相，网络里几乎全是骂声。</p><p>有媒体称，那晚罗永浩失眠了，第二天，他在微博里亦保持了沉寂。</p><p><a href="http://img1.mydrivers.com/img/20180411/c6f3d600f2d7481e9d48701c9ce09874.png" target="_blank"><img src="https://static.cnbetacdn.com/article/2018/0411/206ee6043873f61.png"/></a></p><p><strong>贰</strong></p><p>做锤子的前几年，罗永浩一直没能甩掉“不靠谱”的标签。</p><p>他狂妄。在手机影子都没有的2013年，他就在微博发布文章：《为什么看起来只有锤子科技最可能成为下一个索尼(盛田时代的索尼)或下一个苹果(乔布斯时代的苹果)？》——而那一年，国内手机市场最活跃的角色是799元的红米手机，它直接拉动了小米销量，当年“双十一”，小米三分钟售出一亿元。</p><p>他随性，即使在投资人面前也不改本色。“他甚至聊一聊，就看手机，不搭理投资人”，媒体人黄章晋曾经这样评价。在演讲台上口舌生莲的罗永浩，其实有点社交恐惧症，谈合作时不知道怎么说半真半假的话。</p><p><strong>2013年那场“糟糕”的ROM发布会没多久，罗永浩就烧光了陌陌唐岩给的900万。他第一次面临钱的难题。但很多投资人对这位曾经怒砸冰箱的狂人有所忌讳，一位知名基金的风投曾表示，“我非常欣赏老罗”，但他转头告诉同事的却是：“我们是一分钱也不会给他的。”</strong></p><p>锤子在生产线上遇到的麻烦，狠狠给了罗永浩一“锤子”。</p><p>2014年5月，锤子T1 发布。对于从未涉足过硬件生产的罗永浩，这无疑是历史性的一步。在产品宣传图里，他高调称之为“东半球最好用的智能手机”。或许是担心触及新的《广告法》条例，没多久，宣传语又变成了“全球第二好用的智能手机”。</p><p>那场发布会上，罗永浩扬眉吐气，挺直了腰板，痛快嘲笑着整个手机行业，尽管T1首发只有3G版。</p><p>供应链反手给了他一巴掌。由于良品率过低等原因——有媒体援引业内人士的判断，锤子手机良品率不会超过50%，而正常数值应该在93%以上——T1 在发布后的几个月里都无法正常供货，急得罗永浩跑到富士康去蹲守。</p><p>发布会造起的声势，在订购用户漫长的等待中变凉了。随后3-4个月，T1逃单率从最初的2%一路飙升到接近90%。那些通过员工渠道才搞到购买码的人也跑了，理由很简单：过去几个月，天天看锤子的负面新闻看怕了。</p><p>罗永浩扛到10月，不得不宣布锤子降价，降幅在1000元左右。降价后，最便宜的16G 3G版售价1980元。</p><p>这又激怒了不少锤粉。5个月前，老罗说“我特别反感有的手机厂商在新品上市时定一个高价，之后很快又会降价的做法”，他降价的唯一可能是：新一代产品上市，前一代需要清理库存。为了显得有信服力，他还撂下狠话：如果低于2500，我是你孙子。</p><p><strong>最终，T1在2014年的总销量是25万多台。那年，中国智能手机出货量为4.207亿台，其中，小米出货量为6112万台。</strong></p><p>这样的结果无疑是让人沮丧的。罗永浩认为自己的口无遮拦把企业连累了。</p><p>那年12月，他在北展做了最后一场个人演讲《一个理想主义者的创业故事》，现场哽咽鞠躬，表示要认真学做企业家，并宣布个人微博号密码交给了公司公关部，将来所说的每一句话，要经过公司审核过再发布。</p><p>自此，“罗永浩可爱多”的微博昵称消失了。</p><p><a href="http://img1.mydrivers.com/img/20180411/6fb5d811429f43c09809c07ae36a4171.png" target="_blank"><img src="https://static.cnbetacdn.com/article/2018/0411/be2a4e6b3f628b9.png"/></a></p><p><strong>叁</strong></p><p>事实证明，罗永浩选择低姿态进入2015年，实在是个明智的选择。</p><p>那年手机行业的主题是：无人幸免。</p><p>险象在2014年已经初显。工信部监测报告显示，2014年前10个月智能手机出货量同比降幅达到10.4%，其中，国产手机出货量共2.86亿部，同比下降25.4%。显然，这是一个日趋饱和的市场。</p><p>于是，<strong>对于赶在风口成立的小手机厂商，2015年就是死亡谷。头一年还连发三款手机的大可乐在这年保持了寂静，次年三月宣布破产。</strong></p><p>行业不景气之下，上游企业随之受到牵连，珠三角多家手机代工厂出现倒闭、老板跳楼等悲剧。</p><p>大公司的日子也不好过，随着增速放缓，唱衰小米的声音此起彼伏。</p><p>那年年初，华为的余承东判断局势之残酷：未来3-5年国内只剩下三大手机厂商。当然，他不忘给自己打气，“其中就包括华为”。这位靠P6一战成名的CEO 曾经自嘲是华为的CHO（首席吹牛官）——“我学会了吹牛、打赌和应付口水战。”</p><p>8月，联想的杨元庆也在微博中写道：联想此刻正面临着严峻的挑战。头一年，联想以29.1亿美元收购摩托诺拉手机品牌，直接导致了2015年Q1财报里的2.92亿美元亏损。从后面的故事来看，此举也未能阻止联想手机业务的颓势。</p><p>如此局势之下，锤子的日子也不好过。</p><p><strong>那年锤子先后发布了坚果手机和T2，都没能打出翻身仗。最终，锤子科技在2015年亏损了4.62亿。</strong></p><p>钱成了大问题。天生骄傲的情怀在现实面前似乎不堪一击。到2016年，锤子对外公布的融资仅有AB两轮，融资金额最高的也就是2014年4月那笔1.8亿元人民币。于是，当锤子在2016年发不出工资时，罗永浩只能编了个理由：银行系统出了问题，过几天再发。</p><p>一年后，当危机化解，罗永浩把此事当做段子在极客公园大会上分享，逗得台下观众哈哈大笑。他闭口未谈期间的辛酸，包括为了钱去找小米谈收购、跟阿里质押股权，最后都没成，不得已，他跑到得到开专栏，去陌陌做直播，“卖身”换钱。</p><p>后来他说：<strong>真正的猛男，敢于直视惨淡的人生。猛男另一个特征，哭的时候要躲起来。</strong></p><p>期间也有援手。锤子科技早期投资人、紫辉创投创始合伙人郑刚称，在锤子资金危机中，贾跃亭曾经借给罗永浩1个亿。贾跃亭在2015年开始做手机，一度计划投资锤子，但考虑到交易需要时间，锤子又急需用钱，最后在没有质押股权的情况下，直接借出1个亿。</p><p>后来罗永浩用一组数据复盘了2016年：被传倒闭6次，被传收购5次，被曝资金链困境3次，被用户起诉1次。</p><p>类似的滋味雷军在这一年也品尝到了。小米在2015年开始遭遇出货量和市场份额双跌，到2016年春节时，雷军宣布取消KPI，随后，补课成为这一年的主题，他请回了黎万强，整顿供应链，找明星代言，布局线下和海外。</p><p><strong>两家公司的体量相差迥异，但在生死攸关之时，活下去的欲望足以让他们放下过往，甚至引入自己曾经鄙夷的模式。毕竟，在生意场上，生存就是最大的挑战。</strong></p><p>他们都熬出来了。</p><p>2017年，小米出货量重回世界前五，IPO 进入流程。罗永浩也宣布锤子获得新一轮10亿融资。令人意外的是，其中6亿来自成都市政府。</p><p>也是在这一年，锤子总部搬迁至成都，坚果Pro发布——这款中端机型是锤子首款产量过百万的产品。当罗永浩在发布会上哽咽：如果将来傻*都在用锤子手机，你们一定要记得，这手机是为你们做的，你似乎又能看到他昔日狂妄又感性的影子。</p><p><a href="http://img1.mydrivers.com/img/20180411/acc9a8439b6c42c287ca547b96c4f26f.png" target="_blank"><img src="https://static.cnbetacdn.com/article/2018/0411/2dc80ed81be2cf8.png"/></a></p><p><strong>肆</strong></p><p>做高性价比手机、出空气净化器、布局生态链……锤子幸存之后的诸多举动被业内评价：越来越像小米。</p><p>事实上，自从办完2014年那场最后的个人演讲，罗永浩就在努力把自己变成正常的企业家，把锤子变成正常的公司。去年8月宣布那笔10亿融资时，他笑眯眯地谈到：</p><p>“没意外的话，从秋天开始，我们手里会有大约 19 个亿的运作现金。这意味着我们从明年开始会像一个正规的手机厂商一样，以高、中、低三个段位，每年推出 5~6 款产品。”</p><p>言语间全然不见当年愤怒、自傲、聛睨一切的姿态。</p><p>而正是这些特质，当初让很多追随“罗胖”的粉丝变身锤粉。<strong>作为好友的冯唐曾经分析过，为什么锤子的开局那么糟糕却没有夭折，其中一个重要理由恐怕就是粉丝，“换另一个疯子和偏执狂去做，没有老罗的粉丝群，可能一年都活不下去。”</strong></p><p>在不同的锤粉看来，锤子的六年有着不同的意味。</p><p>有人为这家公司熬过难关挺到现在而开心，即使中间有过口碑糟糕的M系列手机，塑料手感让他们不敢相信“这是老罗的审美”；有人已经转身离去，因为老罗曾经的骄傲不复存在，锤子已经成为泯然众人的大路货。</p><p>比如坚果Pro ，这款定价在1499、1799、2299 的手机，出货量是锤子科技过去五年所有手机产品的总和。</p><p>这是属于商业的成功，但文艺青年们更在乎直观感受。知乎用户 Slender Man 这样写道：</p><p><strong>“一个公司需要在第三方购物网站上刷评论，一个公司需要大费笔墨来夸赞作为手机配件的钢化膜，一个公司在类似于‘虚拟来电’这样的不实用功能上吹嘘所谓工匠精神而不是改善被人诟病依旧的系统时，这大概就是对‘情怀’最大的玷污。”</strong></p><p>但熬过生死关头的罗永浩显然已经超越了这些。他在去年感慨，“你知道我这5年是怎么挺过来的吗？每次就是厚着脸皮再坚持一下。”</p><p>他的变化显而易见。</p><p>他鲜少露面，曾经那些标签，比如彪悍、情怀、工匠精神，也不再一遍遍被强化。在与罗振宇那场8个半小时的《长谈》中，他谈到自己很庆幸，因为现在不需要用讲故事来融资了，“他们（投资人）不用看我罗永浩怎么样，我也不想和他们谈，大家直接看业绩”。</p><p>他开始理解很多以前看不上的行为。“过去，我要是在机场看到一个衣冠楚楚的家伙拿着一本《赢：韦尔奇一生的管理智慧》，就会觉得这个笨蛋没救了，但现在我也会拿着这样的书硬着头皮读完。”</p><p>而4月9日北工大的这场发布会上，罗永浩的表现也越发像一位成熟的商人。</p><p>他意外地只迟到了5分钟，随后用1个小时匆匆展示了千元机坚果3；</p><p>他否定了自己以前一些过于偏执的说法——谈到“为何整天发平价机”时，他说：“设计很重要，但它只是一部分……漂亮很重要，但科技行业漂亮也没那么必要。”</p><p>在这场可能是锤子有史以来最冷清的发布会上，他也老老实实解释了坚果3此时推出的理由：在做旗舰机产品的路上走得非常艰难，不得不做中档的产品，更高性价比的产品。</p><p>只有在谈到5月15日将在鸟巢举办的那场发布会时，罗永浩又显得很兴奋。</p><p>他喜欢用“尿裤子”这个粗俗的词语形容好产品带来的震撼，于是，那天下午他说道：我曾经想过，给每一个入场（鸟巢）的人发一个纸尿裤。</p><p>在这样天马行空的瞬间，企业家罗永浩，似乎又跟那个满身是刺却内心脆弱的老罗重逢了。</p><p><strong>这是属于幸存者的幸福瞬间，即使罗永浩为此付出了“杀死老罗”的代价。但商业就是如此，正如他那天下午感慨的——科技行业没有百年老店的。“只要你干不过别人，无论有什么理由，都是没什么用的。”</strong></p><p><br/></p>','','','','','255','1','0','0','0','4','0','0','admin','admin','2018-04-12 10:08:03','2018-04-13 09:36:25','4','',''),
('7','cn','4','','大获全胜 扎克伯格如何赢得与议员的当面对峙','#333333','','','admin','本站','','2018-04-12 10:08:50','/static/upload/image/20180412/1523499864406172.jpg','','<p>腾讯《深网》 纪振宇 4月11日发自硅谷</p><p>并不是扎克伯格表现地多好，而是议员们的表现太差了。</p><p>在经历了连续两天马拉松式的国会议员“拷问”后，Facebook创始人兼首席执行官扎克伯格给外界留下了表现“超出预期”的印象，Facebook的股价甚至在第一天出现了过去两年来最大的单日涨幅，扎克伯格个人身家也在当天结束后暴涨近30亿美元。</p><p>国会会议厅自然不是让扎克伯格感到舒适的场所，为了准备这两场听证会，扎克伯格提前一天便来到了华盛顿特区，4月初的华盛顿依然春寒料峭，这里的人们大多身着深灰色大衣，神情肃穆，行色匆匆，这里与明媚温暖的加州完全是两个世界，这并不是他能够穿着T恤短裤，和妻子孩子在自家后院烧烤做线上视频直播，与成百上千万Facebook用户轻松聊天的时刻。</p><p>他不得不穿上为他量身定做的深蓝色修身西装、系上领带，端坐在摆放着名牌“Mr. Zuckberg”的桌子后面，与几十名参议员，上百名媒体记者共处一室，熬过接下来长达5个半小时的听证会。</p><p>“他很紧张，但他显得信心十足，”现场的一名人员这样描述，“他是一个聪明人。”</p><p>还未落座，扎克伯格就被数十名现场摄影记者围成的人墙所包围，他全身上下的各个角度，动作神情的每一个细节，都被无情地暴露在冰冷的镜头前。</p><p>但扎克伯格显然是有备而来，坐在听证席上的他保持上身挺直，对每一个问题都认真倾听，与提问的议员进行眼神接触，他改掉了过去回答问题时都先加上“so”语气词的习惯，而是先以“Senator”(参议员)，“Congressman”或“Congresswoman”（议员）来称呼向他提问的对方，然后再作答。</p><p>他的桌上摆放着他的团队为他提前准备好的应答提纲，在听证会中场休息的间隙，现场媒体拍到了其中一页内容，厚厚的一叠纸上基本涉及到了所有他们能事先想到的议员们可能问到的问题，他的座椅放上了厚厚的垫子，或许也是团队为他精心准备的，为了让他在镜头面前显得更高大，更符合在危机时刻的领导者形象。</p><p>尽管时不时咽下口水，表情尴尬或频繁举起水杯，但出现在国会的扎克伯格，并不是我们过去所熟悉的那个穿着灰色帽衫，语速飞快，说着“快速行动，打破一切”的年轻创业者形象，而是一位训练有素，应对自如的CEO，这是一家正处在危机中的公司所需要的领导者的形象。</p><p>反观听证会上坐在扎克伯格对面的数十名国会议员，他们的表现却让人大失所望，或者说，人们从来就没有对这次听证抱太大期望，数十个问题暴露出了这些政治圈人士与21世纪科技圈完全的隔阂，他们与扎克伯格之间的许多问答，双方仿佛是在各自语境体系下的自说自话，出现了许多难以言状的尴尬时刻。</p><p>例如，一名议员问“如果用户不用支付你提供的服务的话，你如何维持你的公司经营？”</p><p>扎克伯格停顿片刻，说，“参议员，我们卖广告。”</p><p>“哦，是这样啊。”这位参议员说。</p><p>有一位议员说，“我13岁的儿子查理是个活跃的Instagram（Facebook旗下图片分享应用）用户，他让我确保今天提到他。”</p><p>另一位议员说，“如果我通过Whatsapp（Facebook旗下即时通讯应用）发邮件，这会让广告主知道里面的信息吗？”</p><p>议员们的许多问题，暴露了他们对一些最基本互联网常识或Facebook这家公司的无知，Twitter上的一名用户甚至嘲讽说，“这些议员的平均年龄已经100岁了。”</p><p>整场听证会，议员们的问题还缺乏重点，往往漫无边际，围绕着一些无关痛痒的问题兜圈子。</p><p>在议员们“不给力”的问题下，扎克伯格也得以完全依照此前团队所设计的策略，有条不紊地完成这两天的既定任务：承认错误，道歉，具体问题不做肯定或否定的回答，交给团队后续跟进，不做承诺，不否定目前的商业模式，不表现地过于贪婪。</p><p>Open MIC组织执行总监Michael Connor评价称，扎克伯格的听证会表现仅能算“勉强通过”，谈不上“优异”。这家代表Facebook投资者的机构在听证会开始前一天公开呼吁扎克伯格辞去Facebook的所有职务。</p><p>听证会的发起，源自Facebook大面积用户数据泄漏事件的爆发，由于爆料人称大数据公司Cambridge
 
Analytica利用从Facebook获得的大量用户数据，进行精准政治广告投放，以影响政治活动，事件可能涉及到8700万Facebook用户，其中大多数人位于美国，这些都引起了华盛顿的关注。</p><p>这场事件的另几个关键词是“俄罗斯操纵”、“美国总统选举”，这些已经触及到美国国家安全和核心利益。出于对各自选区选民的责任，这场听证会在所难免。</p><p>但参加听证会的国会议员的表现，或许连“通过”的标准都达不到。听证会的最终目的，是为了让这些立法者们能够更好地了解情况，最终至少能够形成对于某些现存问题的一致看法，并通过立法程序加以解决，尽管扎克伯格本人在听证会期间也明确表达了愿意接受“正确的监管”的态度，但至少从这两天的听证会现场情况来看，要达成上述目的的希望渺茫。</p><p>另一个尴尬的事实是，参与听证会的近百名议员，大多数都直接或间接接受过Facebook的政治捐款。在过去12年中，Facebook总共投入了700万美元用于政治捐款，从2014年至今，对扎克伯格质询的议员总共从Facebook获取了超过64万的政治捐助。</p><p>两天的听证会被一名Twitter用户评价为“走过场”，没有“实质意义”，如果说第一天的听证会上，扎克伯格还不时露出紧张的神态，第二天的他则完全神态自若，当主持整场听证会的议员提议休息片刻，扎克伯格回答说，“要不再来几个问题？”美国新闻电视网CNN评价道，两天的听证会，扎克伯格得以全身而退，毫发无伤。</p><p><br/></p>','','','','','255','1','0','0','0','13','0','0','admin','admin','2018-04-12 10:09:37','2018-04-13 09:35:56','4','',''),
('8','cn','3','','PbootCMS主要功能介绍','#333333','','','admin','本站','','2018-04-12 10:10:18','/static/upload/image/20180412/1523499864406172.jpg','','<p>&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size: 18px;">PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、
 强悍的可免费商用的PHP 
CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。</span></p><p>&nbsp;&nbsp;&nbsp;&nbsp;1、系统采用高效、简洁、强悍的模板标签，只要懂HTML就可快速开发企业网站；</p><p>&nbsp;&nbsp;&nbsp;&nbsp;2、系统采用PHP语言开发，使用自主研发的高速多层开发框架及缓存技术；</p><p>&nbsp;&nbsp;&nbsp;&nbsp;3、系统默认采用sqlite轻型数据库，放入PHP空间即可直接使用，可选mysql等数据库，满足各类存储需求；</p><p>&nbsp;&nbsp;&nbsp;&nbsp;4、系统采用响应式管理后台，满足各类设备随时管理的需要；</p><p>&nbsp;&nbsp;&nbsp;&nbsp;5、系统支持后台在线升级，满足系统及时升级更新的需要；</p><p>&nbsp;&nbsp;&nbsp;&nbsp;6、系统支持内容模型、多语言、自定义表单、筛选、多条件搜索、小程序、APP等功能；</p><p>&nbsp;&nbsp;&nbsp;&nbsp;7、系统支持多种URL模式及模型、栏目、内容自定义地址名称，满足各类网站推广优化的需要。<br/></p><p><br/></p><p><strong><span style="font-size: 18px;">源码托管地址：</span></strong></p><p>GitHub：<a href="https://github.com/hnaoyun/PbootCMS" target="_blank">https://github.com/hnaoyun/PbootCMS</a><br/></p><p>Gitee：<a href="https://gitee.com/hnaoyun/PbootCMS" target="_blank" title="https://gitee.com/hnaoyun/PbootCMS">https://gitee.com/hnaoyun/PbootCMS</a></p><p><strong><span style="font-size: 18px;"><br/></span></strong></p><p><strong><span style="font-size: 18px;">简单到想哭的标签：</span></strong></p><pre class="brush:html;toolbar:false">1、全局标签示意：
{pboot:sitetitle}&nbsp;站点标题&nbsp;
{pboot:sitelogo}&nbsp;站点logo
2、列表页标签示意：
{pboot:list&nbsp;num=10&nbsp;order=date}&nbsp;&nbsp;&nbsp;&nbsp;
&nbsp;&nbsp;&nbsp;&nbsp;<p><a&nbsp;href="[list:link]">[list:title]</a></p>
{/pboot:list}
3、内容页标签示意：
{content:title}&nbsp;标题
{content:subtitle}副标题
{content:author}&nbsp;作者
{content:source}&nbsp;来源
更多简单到想哭的标签请参考开发手册...</pre>','','','','PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企','255','1','0','0','0','4','0','0','admin','admin','2018-04-12 10:10:46','2019-08-05 11:19:31','4','',''),
('9','cn','7','','域名注册服务','#333333','','','admin','本站','','2018-04-12 10:11:20','/static/upload/image/20180412/1523499435499884.png','','<p>
    &nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size: 18px;">PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、
 强悍的可免费商用的PHP 
CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。</span>
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;1、系统采用高效、简洁、强悍的模板标签，只要懂HTML就可快速开发企业网站；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;2、系统采用PHP语言开发，使用自主研发的高速多层开发框架及缓存技术；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;3、系统默认采用sqlite轻型数据库，放入PHP空间即可直接使用，可选mysql等数据库，满足各类存储需求；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;4、系统采用响应式管理后台，满足各类设备随时管理的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;5、系统支持后台在线升级，满足系统及时升级更新的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;6、系统支持内容模型、多语言、自定义表单、筛选、多条件搜索、小程序、APP等功能；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;7、系统支持多种URL模式及模型、栏目、内容自定义地址名称，满足各类网站推广优化的需要。<br/>
</p>
<p>
    <br/>
</p>
<p>
    <strong><span style="font-size: 18px;">源码托管地址：</span></strong>
</p>
<p>
    GitHub：<a href="https://github.com/hnaoyun/PbootCMS" target="_blank">https://github.com/hnaoyun/PbootCMS</a><br/>
</p>
<p>
    Gitee：<a href="https://gitee.com/hnaoyun/PbootCMS" target="_blank" title="https://gitee.com/hnaoyun/PbootCMS">https://gitee.com/hnaoyun/PbootCMS</a>
</p>
<p>
    <strong><span style="font-size: 18px;"><br/></span></strong>
</p>
<p>
    <strong><span style="font-size: 18px;">简单到想哭的标签：</span></strong>
</p>
<pre class="brush:html;toolbar:false">1、全局标签示意：
{pboot:sitetitle} 站点标题 
{pboot:sitelogo} 站点logo
2、列表页标签示意：
{pboot:list num=10 order=date}    
    <p><a href="[list:link]">[list:title]</a></p>
{/pboot:list}
3、内容页标签示意：
{content:title} 标题
{content:subtitle}副标题
{content:author} 作者
{content:source} 来源
更多简单到想哭的标签请参考开发手册...</pre>','','','','PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发','255','1','0','0','0','6','0','0','admin','admin','2018-04-12 10:20:28','2019-08-05 11:20:53','4','',''),
('10','cn','6','','网站建设基础版','#333333','','','admin','本站','','2018-04-12 10:23:07','/static/upload/image/20180412/1523499813391526.jpg','','<p>
    &nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size: 18px;">PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、
 强悍的可免费商用的PHP 
CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。</span>
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;1、系统采用高效、简洁、强悍的模板标签，只要懂HTML就可快速开发企业网站；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;2、系统采用PHP语言开发，使用自主研发的高速多层开发框架及缓存技术；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;3、系统默认采用sqlite轻型数据库，放入PHP空间即可直接使用，可选mysql等数据库，满足各类存储需求；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;4、系统采用响应式管理后台，满足各类设备随时管理的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;5、系统支持后台在线升级，满足系统及时升级更新的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;6、系统支持内容模型、多语言、自定义表单、筛选、多条件搜索、小程序、APP等功能；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;7、系统支持多种URL模式及模型、栏目、内容自定义地址名称，满足各类网站推广优化的需要。<br/>
</p>
<p>
    <br/>
</p>
<p>
    <strong><span style="font-size: 18px;">源码托管地址：</span></strong>
</p>
<p>
    GitHub：<a href="https://github.com/hnaoyun/PbootCMS" target="_blank">https://github.com/hnaoyun/PbootCMS</a><br/>
</p>
<p>
    Gitee：<a href="https://gitee.com/hnaoyun/PbootCMS" target="_blank" title="https://gitee.com/hnaoyun/PbootCMS">https://gitee.com/hnaoyun/PbootCMS</a>
</p>
<p>
    <strong><span style="font-size: 18px;"><br/></span></strong>
</p>
<p>
    <strong><span style="font-size: 18px;">简单到想哭的标签：</span></strong>
</p>
<pre class="brush:html;toolbar:false">1、全局标签示意：
{pboot:sitetitle} 站点标题 
{pboot:sitelogo} 站点logo
2、列表页标签示意：
{pboot:list num=10 order=date}    
    <p><a href="[list:link]">[list:title]</a></p>
{/pboot:list}
3、内容页标签示意：
{content:title} 标题
{content:subtitle}副标题
{content:author} 作者
{content:source} 来源
更多简单到想哭的标签请参考开发手册...</pre>','','','','PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发','255','1','0','0','0','2','0','0','admin','admin','2018-04-12 10:23:34','2019-08-05 11:20:42','4','',''),
('11','cn','6','','网站建设专业版','#333333','','','admin','本站','','2018-04-12 10:23:37','/static/upload/image/20180412/1523501297516241.jpg','','<p>
    &nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size: 18px;">PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、
 强悍的可免费商用的PHP 
CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。</span>
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;1、系统采用高效、简洁、强悍的模板标签，只要懂HTML就可快速开发企业网站；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;2、系统采用PHP语言开发，使用自主研发的高速多层开发框架及缓存技术；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;3、系统默认采用sqlite轻型数据库，放入PHP空间即可直接使用，可选mysql等数据库，满足各类存储需求；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;4、系统采用响应式管理后台，满足各类设备随时管理的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;5、系统支持后台在线升级，满足系统及时升级更新的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;6、系统支持内容模型、多语言、自定义表单、筛选、多条件搜索、小程序、APP等功能；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;7、系统支持多种URL模式及模型、栏目、内容自定义地址名称，满足各类网站推广优化的需要。<br/>
</p>
<p>
    <br/>
</p>
<p>
    <strong><span style="font-size: 18px;">源码托管地址：</span></strong>
</p>
<p>
    GitHub：<a href="https://github.com/hnaoyun/PbootCMS" target="_blank">https://github.com/hnaoyun/PbootCMS</a><br/>
</p>
<p>
    Gitee：<a href="https://gitee.com/hnaoyun/PbootCMS" target="_blank" title="https://gitee.com/hnaoyun/PbootCMS">https://gitee.com/hnaoyun/PbootCMS</a>
</p>
<p>
    <strong><span style="font-size: 18px;"><br/></span></strong>
</p>
<p>
    <strong><span style="font-size: 18px;">简单到想哭的标签：</span></strong>
</p>
<pre class="brush:html;toolbar:false">1、全局标签示意：
{pboot:sitetitle} 站点标题 
{pboot:sitelogo} 站点logo
2、列表页标签示意：
{pboot:list num=10 order=date}    
    <p><a href="[list:link]">[list:title]</a></p>
{/pboot:list}
3、内容页标签示意：
{content:title} 标题
{content:subtitle}副标题
{content:author} 作者
{content:source} 来源
更多简单到想哭的标签请参考开发手册...</pre>','','','','PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发','255','1','0','0','0','5','0','0','admin','admin','2018-04-12 10:24:01','2019-08-05 11:20:33','4','',''),
('12','cn','6','','网站建设旗舰版','#333333','','','admin','本站','','2018-04-12 10:24:04','/static/upload/image/20180412/1523499864406172.jpg','','<p>
    &nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size: 18px;">PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、
 强悍的可免费商用的PHP 
CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。</span>
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;1、系统采用高效、简洁、强悍的模板标签，只要懂HTML就可快速开发企业网站；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;2、系统采用PHP语言开发，使用自主研发的高速多层开发框架及缓存技术；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;3、系统默认采用sqlite轻型数据库，放入PHP空间即可直接使用，可选mysql等数据库，满足各类存储需求；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;4、系统采用响应式管理后台，满足各类设备随时管理的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;5、系统支持后台在线升级，满足系统及时升级更新的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;6、系统支持内容模型、多语言、自定义表单、筛选、多条件搜索、小程序、APP等功能；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;7、系统支持多种URL模式及模型、栏目、内容自定义地址名称，满足各类网站推广优化的需要。<br/>
</p>
<p>
    <br/>
</p>
<p>
    <strong><span style="font-size: 18px;">源码托管地址：</span></strong>
</p>
<p>
    GitHub：<a href="https://github.com/hnaoyun/PbootCMS" target="_blank">https://github.com/hnaoyun/PbootCMS</a><br/>
</p>
<p>
    Gitee：<a href="https://gitee.com/hnaoyun/PbootCMS" target="_blank" title="https://gitee.com/hnaoyun/PbootCMS">https://gitee.com/hnaoyun/PbootCMS</a>
</p>
<p>
    <strong><span style="font-size: 18px;"><br/></span></strong>
</p>
<p>
    <strong><span style="font-size: 18px;">简单到想哭的标签：</span></strong>
</p>
<pre class="brush:html;toolbar:false">1、全局标签示意：
{pboot:sitetitle} 站点标题 
{pboot:sitelogo} 站点logo
2、列表页标签示意：
{pboot:list num=10 order=date}    
    <p><a href="[list:link]">[list:title]</a></p>
{/pboot:list}
3、内容页标签示意：
{content:title} 标题
{content:subtitle}副标题
{content:author} 作者
{content:source} 来源
更多简单到想哭的标签请参考开发手册...</pre>','','','','PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发','255','1','0','0','0','9','0','0','admin','admin','2018-04-12 10:24:25','2019-08-05 11:20:25','4','',''),
('13','cn','7','','网站空间','#333333','','','admin','本站','','2018-04-12 10:24:52','/static/upload/image/20180412/1523499979727269.jpg','','<p>
    &nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size: 18px;">PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、
 强悍的可免费商用的PHP 
CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。</span>
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;1、系统采用高效、简洁、强悍的模板标签，只要懂HTML就可快速开发企业网站；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;2、系统采用PHP语言开发，使用自主研发的高速多层开发框架及缓存技术；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;3、系统默认采用sqlite轻型数据库，放入PHP空间即可直接使用，可选mysql等数据库，满足各类存储需求；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;4、系统采用响应式管理后台，满足各类设备随时管理的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;5、系统支持后台在线升级，满足系统及时升级更新的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;6、系统支持内容模型、多语言、自定义表单、筛选、多条件搜索、小程序、APP等功能；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;7、系统支持多种URL模式及模型、栏目、内容自定义地址名称，满足各类网站推广优化的需要。<br/>
</p>
<p>
    <br/>
</p>
<p>
    <strong><span style="font-size: 18px;">源码托管地址：</span></strong>
</p>
<p>
    GitHub：<a href="https://github.com/hnaoyun/PbootCMS" target="_blank">https://github.com/hnaoyun/PbootCMS</a><br/>
</p>
<p>
    Gitee：<a href="https://gitee.com/hnaoyun/PbootCMS" target="_blank" title="https://gitee.com/hnaoyun/PbootCMS">https://gitee.com/hnaoyun/PbootCMS</a>
</p>
<p>
    <strong><span style="font-size: 18px;"><br/></span></strong>
</p>
<p>
    <strong><span style="font-size: 18px;">简单到想哭的标签：</span></strong>
</p>
<pre class="brush:html;toolbar:false">1、全局标签示意：
{pboot:sitetitle} 站点标题 
{pboot:sitelogo} 站点logo
2、列表页标签示意：
{pboot:list num=10 order=date}    
    <p><a href="[list:link]">[list:title]</a></p>
{/pboot:list}
3、内容页标签示意：
{content:title} 标题
{content:subtitle}副标题
{content:author} 作者
{content:source} 来源
更多简单到想哭的标签请参考开发手册...</pre>','','','','PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发','255','1','0','0','0','2','0','0','admin','admin','2018-04-12 10:26:20','2019-08-05 11:20:04','4','',''),
('14','cn','8','','湖南翱云网络科技有限公司','#333333','','','admin','本站','','2018-04-12 10:26:28','/static/upload/image/20180412/1523500443228678.png','','<p>
    &nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size: 18px;">PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、
 强悍的可免费商用的PHP 
CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。</span>
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;1、系统采用高效、简洁、强悍的模板标签，只要懂HTML就可快速开发企业网站；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;2、系统采用PHP语言开发，使用自主研发的高速多层开发框架及缓存技术；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;3、系统默认采用sqlite轻型数据库，放入PHP空间即可直接使用，可选mysql等数据库，满足各类存储需求；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;4、系统采用响应式管理后台，满足各类设备随时管理的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;5、系统支持后台在线升级，满足系统及时升级更新的需要；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;6、系统支持内容模型、多语言、自定义表单、筛选、多条件搜索、小程序、APP等功能；
</p>
<p>
    &nbsp;&nbsp;&nbsp;&nbsp;7、系统支持多种URL模式及模型、栏目、内容自定义地址名称，满足各类网站推广优化的需要。<br/>
</p>
<p>
    <br/>
</p>
<p>
    <strong><span style="font-size: 18px;">源码托管地址：</span></strong>
</p>
<p>
    GitHub：<a href="https://github.com/hnaoyun/PbootCMS" target="_blank">https://github.com/hnaoyun/PbootCMS</a><br/>
</p>
<p>
    Gitee：<a href="https://gitee.com/hnaoyun/PbootCMS" target="_blank" title="https://gitee.com/hnaoyun/PbootCMS">https://gitee.com/hnaoyun/PbootCMS</a>
</p>
<p>
    <strong><span style="font-size: 18px;"><br/></span></strong>
</p>
<p>
    <strong><span style="font-size: 18px;">简单到想哭的标签：</span></strong>
</p>
<pre class="brush:html;toolbar:false">1、全局标签示意：
{pboot:sitetitle} 站点标题 
{pboot:sitelogo} 站点logo
2、列表页标签示意：
{pboot:list num=10 order=date}    
    <p><a href="[list:link]">[list:title]</a></p>
{/pboot:list}
3、内容页标签示意：
{content:title} 标题
{content:subtitle}副标题
{content:author} 作者
{content:source} 来源
更多简单到想哭的标签请参考开发手册...</pre>','','','','PbootCMS是全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发','255','1','0','0','0','3','0','0','admin','admin','2018-04-12 10:32:52','2019-08-05 11:21:09','4','',''),
('15','cn','9','','信息审核专员','#333333','','','admin','本站','','2018-04-12 10:34:24','','','<p><strong>岗位职责：</strong></p><p>1、根据业务规范对全平台音视图文内容进行审核、筛选及处理；</p><p>2、对平台内容进行监管处理和备案，维持网络秩序；</p><p>3、为用户提供平台业务咨询服务，保障产品活动顺利进行；</p><p>4、受理客户投诉，在授权范围内予以解决；</p><p>5、参与修订审核标准，优化审核流程与规范。</p><p>&nbsp;</p><p><strong>岗位要求：</strong></p><p>1、大专以上学历，专业不限，有视频网站内容审核经验者优先；</p><p>2、熟悉互联网信息安全，有敏感的风险意识，针对突发热点话题具备一定的判断处理能力；</p><p>3、耐心、细致、踏实、严谨，具备高度的责任心和团队合作精神；</p><p>4、有一定沟通协调能力及组织领导力，能够承担一定的压力与挑战。</p><p>说明：上班时间遵从部门内部排班安排，能适应夜班。</p><p>岗位升值空间：组长、主管、平台运营专员、网络推广、音乐编辑…</p><p><br/></p><p><strong>工作地址：</strong>
 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;</p><h2>北京市朝阳区</h2><p><br/></p>','','','','','255','1','0','0','0','4','0','0','admin','admin','2018-04-12 10:37:25','2018-04-13 09:43:29','4','',''),
('16','cn','9','','平台运营','#333333','','','admin','本站','','2018-04-12 10:37:31','','','<p><strong><span style=";font-family:宋体">岗位职责： </span></strong></p><p>1、 负责平台运营的业务支撑工作，保证平台业务稳定发展；</p><p>2、 参与和优化部门业务操作流程，保证团队协同工作；</p><p>3、 为用户提供平台业务咨询服务；</p><p>4、 受理客户投诉，在授权范围内予以解决；</p><p>5、 网络活动视频录像与剪辑，挖掘优秀作品,后台信息简单编辑处理；</p><p>6、 与公司其他部门配合工作。</p><p><br/></p><p><strong><span style=";font-family:宋体">任职要求： </span></strong></p><p>1、 专科及以上学历，热爱互联网行业；</p><p>2、 较强的工作责任心，踏实勤恳，积极向上，性格开朗；</p><p>3、 形象佳，口齿伶俐，普通话标准；</p><p>4、 熟练使用电脑，经常上网，会使用office等相关办公软件；</p><p>5、 能适应白班、夜班倒班工作制；</p><p>注：根据个人能力和特长，公司给予更多的发展及晋升空间。</p><p><br/></p><p><strong>工作地址：</strong>
 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;</p><h2>北京市朝阳区北苑路</h2><p><br/></p>','','','','','255','1','0','0','0','3','0','0','admin','admin','2018-04-12 10:37:57','2018-04-13 09:41:28','4','',''),
('17','cn','9','','高级Linux运维工程师','#333333','','','admin','本站','','2018-04-12 10:38:09','','','<p style="line-height: 150%"><strong><span style="font-size:16px;line-height: 150%;font-family:宋体">岗位职责：</span></strong></p><p>1、负责公司服务器基础环境的部署、配置、日常巡检、维护、故障的应急响应和问题处理；</p><p>2、负责公司kvm虚拟化平台的管理工作，基础环境部署，性能容量管理，漏洞扫描、安全加固，保证其稳定、高效运行；</p><p>3、负责维护公司集中监控系统，根据业务需求调整监控策略、告警阀值，处理告警信息和问题跟踪；</p><p>4、编写系统维护文档，完善并更新运维流程文档；</p><p style="line-height:150%"><span style="font-size: 16px;line-height:150%">&nbsp;</span></p><p style="line-height:150%"><strong><span style="font-size:16px;line-height:150%;font-family:宋体">任职要求：</span></strong></p><p>1、计算机等相关专业，本科以上学历，2年以上linux系统管理工作经验，经验丰富可适当放宽学历条件；</p><p>2、熟悉基础网络知识，熟悉TCP/IP协议工作原理，有大流量网站服务器管理经验者优先，熟悉自动化运维工具（三选一puppet/saltstack/ansible）优先；</p><p>3、熟悉linux系统高可用技术和负载均衡技术，熟悉WEB相关技术，包括Apache/Nginx/tomcat/squid 等应用程序的安装、配置和维护；</p><p>4、熟悉服务器硬件，具备排错及故障定位、处理的能力；熟练使用各种工具进行系统状态监控（cacti、Nagios、ganglia等），有虚拟化平台相关经验者优先（vmware/kvm/docker）；</p><p>5、有良好的沟通能力和团队合作精神，有强烈的事业心和责任感，工作细心，热爱学习和分享，具有RHCE、RHCA认证者优先；</p><p>6、熟练撑握shell/python/perl等1至2种语言。</p><p><br/></p><p><strong>工作地址：</strong>
 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;</p><h2>北京市朝阳区</h2><p><br/></p>','','','','','255','1','0','0','0','5','0','0','admin','admin','2018-04-12 10:39:40','2018-04-13 09:40:52','4','','');

-- --------------------------------------------------------

--
-- 表的结构 `ay_content_ext`
--

DROP TABLE IF EXISTS `ay_content_ext`;
CREATE TABLE `ay_content_ext` (
  `extid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `contentid` int(10) unsigned NOT NULL,
  `ext_price` varchar(100) DEFAULT NULL COMMENT '产品价格',
  `ext_type` varchar(100) DEFAULT NULL COMMENT '类型',
  `ext_color` varchar(100) DEFAULT NULL COMMENT '颜色',
    PRIMARY KEY (`extid`),
    KEY `ay_content_ext_contentid` (`contentid`),
    KEY ay_content_ext_color_index (`ext_color`),
    KEY ay_content_ext_price_index (`ext_price`),
    KEY ay_content_ext_type_index (`ext_type`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_content_ext`
--

INSERT INTO `ay_content_ext` (`extid`,`contentid`,`ext_price`,`ext_type`,`ext_color`) VALUES
('1','9','80','专业版','红色,黄色'),
('2','10','999','基础版','黄色,绿色'),
('3','11','1999','旗舰版','蓝色,紫色'),
('4','12','2999','专业版','黄色,绿色'),
('5','13','150','基础版','红色,橙色');

-- --------------------------------------------------------

--
-- 表的结构 `ay_content_sort`
--

DROP TABLE IF EXISTS `ay_content_sort`;
CREATE TABLE `ay_content_sort` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `acode` varchar(20) NOT NULL COMMENT '区域编码',
  `mcode` varchar(20) NOT NULL COMMENT '内容模型编码',
  `pcode` varchar(20) NOT NULL COMMENT '父编码',
  `scode` varchar(20) NOT NULL COMMENT '分类编码',
  `name` varchar(100) NOT NULL COMMENT '分类名称',
  `listtpl` varchar(50) NOT NULL COMMENT '列表页模板',
  `contenttpl` varchar(50) NOT NULL COMMENT '内容页模板',
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `outlink` varchar(100) NOT NULL COMMENT '转外链接',
  `subname` varchar(200) NOT NULL COMMENT '附加名称',
  `def1` varchar(1000) NOT NULL COMMENT '栏目描述1',
  `def2` varchar(1000) NOT NULL COMMENT '栏目描述2',
  `def3` varchar(1000) NOT NULL COMMENT '栏目描述3',
  `ico` varchar(100) NOT NULL COMMENT '分类缩略图',
  `pic` varchar(100) NOT NULL COMMENT '分类大图',
  `title` varchar(100) NOT NULL COMMENT 'seo标题',
  `keywords` varchar(200) NOT NULL COMMENT '分类关键字',
  `description` varchar(500) NOT NULL COMMENT '分类描述',
  `filename` varchar(30) NOT NULL COMMENT '自定义文件名',
  `sorting` int(10) unsigned NOT NULL DEFAULT '255' COMMENT '排序',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `gtype` char(1) NOT NULL DEFAULT '4',
  `gid` varchar(20) NOT NULL DEFAULT '',
  `gnote` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_content_sort_scode` (`scode`),
  KEY `ay_content_sort_pcode` (`pcode`),
  KEY `ay_content_sort_acode` (`acode`),
  KEY `ay_content_sort_mcode` (`mcode`),
  KEY `ay_content_sort_filename` (`filename`),
  KEY `ay_content_sort_sorting` (`sorting`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_content_sort`
--

INSERT INTO `ay_content_sort` (`id`,`acode`,`mcode`,`pcode`,`scode`,`name`,`listtpl`,`contenttpl`,`status`,`outlink`,`subname`,`ico`,`pic`,`title`,`keywords`,`description`,`filename`,`sorting`,`create_user`,`update_user`,`create_time`,`update_time`,`gtype`,`gid`,`gnote`) VALUES
('1','cn','1','0','1','公司简介','','about.html','1','','网站建设「一站式」服务商','','','','','','aboutus','255','admin','admin','2018-04-11 17:26:11','2018-04-11 17:26:11','4','',''),
('2','cn','2','0','2','新闻中心','newslist.html','news.html','1','','了解最新公司动态及行业资讯','','','','','','article','255','admin','admin','2018-04-11 17:26:46','2018-04-11 17:26:46','4','',''),
('3','cn','2','2','3','公司动态','newslist.html','news.html','1','','了解最新公司动态及行业资讯','','','','','','company','255','admin','admin','2018-04-11 17:27:05','2018-04-11 17:27:05','4','',''),
('4','cn','2','2','4','行业动态','newslist.html','news.html','1','','了解最新公司动态及行业资讯','','','','','','industry','255','admin','admin','2018-04-11 17:27:30','2018-04-11 17:27:30','4','',''),
('5','cn','3','0','5','产品中心','productlist.html','product.html','1','','服务创造价值、存在造就未来','','','','','','product','255','admin','admin','2018-04-11 17:27:54','2018-04-11 17:27:54','4','',''),
('6','cn','3','5','6','网站建设','productlist.html','product.html','1','','服务创造价值、存在造就未来','','','','','','website','255','admin','admin','2018-04-11 17:28:19','2018-04-11 17:28:19','4','',''),
('7','cn','3','5','7','域名空间','productlist.html','product.html','1','','服务创造价值、存在造就未来','','','','','','domain','255','admin','admin','2018-04-11 17:28:38','2018-04-11 17:28:38','4','',''),
('8','cn','4','0','8','服务案例','caselist.html','case.html','1','','服务创造价值、存在造就未来','','','','','','case','255','admin','admin','2018-04-11 17:29:16','2018-04-11 17:29:16','4','',''),
('9','cn','5','0','9','招贤纳士','joblist.html','job.html','1','','诚聘优秀人士加入我们的团队','','','','','','job','255','admin','admin','2018-04-11 17:30:02','2018-04-11 17:30:02','4','',''),
('10','cn','1','0','10','在线留言','','message.html','1','','有什么问题欢迎您随时反馈','','','','','','gbook','255','admin','admin','2018-04-11 17:30:36','2018-04-12 10:55:31','4','',''),
('11','cn','1','0','11','联系我们','','about.html','1','','能为您服务是我们的荣幸','','','','','','contact','255','admin','admin','2018-04-11 17:31:29','2018-04-11 17:31:29','4','','');

-- --------------------------------------------------------

--
-- 表的结构 `ay_diy_telephone`
--

DROP TABLE IF EXISTS `ay_diy_telephone`;
CREATE TABLE `ay_diy_telephone` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `create_time` datetime NOT NULL,
  `tel` varchar(20) DEFAULT NULL COMMENT '电话号码',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ay_extfield`
--

DROP TABLE IF EXISTS `ay_extfield`;
CREATE TABLE `ay_extfield` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `mcode` varchar(20) NOT NULL COMMENT '模型编码',
  `name` varchar(30) NOT NULL COMMENT '字段名称',
  `type` char(2) NOT NULL COMMENT '字段类型',
  `value` varchar(500) NOT NULL COMMENT '单选或多选值',
  `description` varchar(30) NOT NULL COMMENT '描述文本',
  `sorting` int(11) NOT NULL COMMENT '排序',
  PRIMARY KEY (`id`),
  KEY `ay_extfield_mcode` (`mcode`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_extfield`
--

INSERT INTO `ay_extfield` (`id`,`mcode`,`name`,`type`,`value`,`description`,`sorting`) VALUES
('1','3','ext_price','1','','产品价格','255'),
('2','3','ext_type','4','基础版,专业版,旗舰版','类型','255'),
('3','3','ext_color','4','红色,橙色,黄色,绿色,蓝色,紫色','颜色','255');

-- --------------------------------------------------------

--
-- 表的结构 `ay_form`
--

DROP TABLE IF EXISTS `ay_form`;
CREATE TABLE `ay_form` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `fcode` varchar(20) NOT NULL COMMENT '表单编码',
  `form_name` varchar(30) NOT NULL COMMENT '表单名称',
  `table_name` varchar(30) NOT NULL COMMENT '表名称',
  `create_user` varchar(30) NOT NULL COMMENT '添加人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '添加时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_form_fcode` (`fcode`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_form`
--

INSERT INTO `ay_form` (`id`,`fcode`,`form_name`,`table_name`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','1','在线留言','ay_message','admin','admin','2018-04-11 17:31:29','2018-04-11 17:31:29'),
('2','2','搜集电话','ay_diy_telephone','admin','admin','2018-11-30 15:17:40','2018-11-30 15:17:40');

-- --------------------------------------------------------

--
-- 表的结构 `ay_form_field`
--

DROP TABLE IF EXISTS `ay_form_field`;
CREATE TABLE `ay_form_field` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `fcode` varchar(20) NOT NULL COMMENT '表单编码',
  `name` varchar(30) NOT NULL COMMENT '字段名称',
  `length` int(10) unsigned NOT NULL COMMENT '字段长度',
  `required` char(1) NOT NULL DEFAULT '0' COMMENT '是否必填',
  `description` varchar(30) NOT NULL COMMENT '描述文本',
  `sorting` int(10) unsigned NOT NULL DEFAULT '255' COMMENT '排序',
  `create_user` varchar(30) NOT NULL COMMENT '添加人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '添加时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `ay_form_field_fcode` (`fcode`),
  KEY `ay_form_field_sorting` (`sorting`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_form_field`
--

INSERT INTO `ay_form_field` (`id`,`fcode`,`name`,`length`,`required`,`description`,`sorting`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','1','contacts','10','1','联系人','255','admin','admin','2018-07-14 18:24:02','2018-07-15 17:47:43'),
('2','1','mobile','12','1','手机','255','admin','admin','2018-07-14 18:24:02','2018-07-15 17:47:44'),
('3','1','content','500','1','内容','255','admin','admin','2018-07-14 18:24:02','2018-07-15 17:47:45'),
('4','2','tel','20','1','电话号码','255','admin','admin','2018-11-30 15:18:00','2018-11-30 15:18:00');

-- --------------------------------------------------------

--
-- 表的结构 `ay_label`
--

DROP TABLE IF EXISTS `ay_label`;
CREATE TABLE `ay_label` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `name` varchar(100) NOT NULL COMMENT '名称',
  `value` varchar(500) NOT NULL COMMENT '值',
  `type` char(1) NOT NULL DEFAULT '1' COMMENT '字段类型',
  `description` varchar(30) NOT NULL COMMENT '描述',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `update_user` varchar(20) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_label`
--

INSERT INTO `ay_label` (`id`,`name`,`value`,`type`,`description`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','downlink','https://gitee.com/hnaoyun/PbootCMS/releases','1','下载地址','admin','admin','2018-04-11 16:52:19','2018-04-30 15:05:00');

-- --------------------------------------------------------

--
-- 表的结构 `ay_link`
--

DROP TABLE IF EXISTS `ay_link`;
CREATE TABLE `ay_link` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '序号',
  `acode` varchar(20) NOT NULL COMMENT '区域编码',
  `gid` int(10) unsigned NOT NULL COMMENT '分组序号',
  `name` varchar(50) NOT NULL COMMENT '链接名称',
  `link` varchar(100) NOT NULL COMMENT '跳转链接',
  `logo` varchar(100) NOT NULL COMMENT '图片地址',
  `sorting` int(11) NOT NULL COMMENT '排序',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `ay_link_acode` (`acode`),
  KEY `ay_link_gid` (`gid`),
  KEY `ay_link_sorting` (`sorting`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_link`
--

INSERT INTO `ay_link` (`id`,`acode`,`gid`,`name`,`link`,`logo`,`sorting`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','cn','1','PbootCMS','https://www.pbootcms.com','/static/upload/image/20180412/1523501605180536.png','255','admin','admin','2018-04-12 10:53:06','2018-04-12 10:53:26');

-- --------------------------------------------------------

--
-- 表的结构 `ay_member`
--

DROP TABLE IF EXISTS `ay_member`;
CREATE TABLE `ay_member` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ucode` varchar(20) NOT NULL,
  `username` varchar(100) NOT NULL,
  `useremail` varchar(50) NOT NULL DEFAULT '',
  `usermobile` varchar(11) NOT NULL DEFAULT '',
  `nickname` varchar(100) NOT NULL,
  `password` varchar(32) NOT NULL,
  `headpic` varchar(200) NOT NULL,
  `status` char(1) NOT NULL,
  `activation` char(1) NOT NULL DEFAULT '1',
  `gid` varchar(20) NOT NULL,
  `wxid` varchar(50) NOT NULL,
  `qqid` varchar(50) NOT NULL,
  `wbid` varchar(50) NOT NULL,
  `score` int(10) unsigned NOT NULL DEFAULT '0',
  `register_time` datetime NOT NULL,
  `login_count` int(10) unsigned NOT NULL DEFAULT '0',
  `last_login_ip` varchar(11) NOT NULL,
  `last_login_time` varchar(11) NOT NULL,
  `sex` varchar(2) NOT NULL DEFAULT '',
  `birthday` varchar(20) NOT NULL DEFAULT '',
  `telephone` varchar(20) NOT NULL DEFAULT '',
  `email` varchar(50) NOT NULL DEFAULT '',
  `qq` varchar(15) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_member_ucode` (`ucode`),
  UNIQUE KEY `ay_member_username` (`username`),
  KEY `ay_member_gid` (`gid`),
  KEY `ay_member_wxid` (`wxid`),
  KEY `ay_member_qqid` (`qqid`),
  KEY `ay_member_wbid` (`wbid`),
  KEY `ay_member_useremail` (`useremail`),
  KEY `ay_member_usermobile` (`usermobile`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ay_member_comment`
--

DROP TABLE IF EXISTS `ay_member_comment`;
CREATE TABLE `ay_member_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT '0',
  `contentid` int(10) unsigned NOT NULL,
  `comment` varchar(1000) NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  `puid` int(10) unsigned NOT NULL,
  `likes` int(10) unsigned NOT NULL DEFAULT '0',
  `oppose` int(10) unsigned NOT NULL DEFAULT '0',
  `status` char(1) NOT NULL,
  `user_ip` varchar(11) NOT NULL,
  `user_os` varchar(30) NOT NULL,
  `user_bs` varchar(30) NOT NULL,
  `create_time` datetime NOT NULL,
  `update_user` varchar(30) NOT NULL,
  `update_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ay_member_comment_pid` (`pid`),
  KEY `ay_member_comment_contentid` (`contentid`),
  KEY `ay_member_comment_uid` (`uid`),
  KEY `ay_member_comment_puid` (`puid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ay_member_field`
--

DROP TABLE IF EXISTS `ay_member_field`;
CREATE TABLE `ay_member_field` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `length` int(10) unsigned NOT NULL,
  `required` char(1) NOT NULL,
  `description` varchar(30) NOT NULL,
  `sorting` int(10) unsigned NOT NULL,
  `status` char(1) NOT NULL,
  `create_user` varchar(30) NOT NULL,
  `update_user` varchar(30) NOT NULL,
  `create_time` datetime NOT NULL,
  `update_time` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_member_field`
--

INSERT INTO `ay_member_field` (`id`,`name`,`length`,`required`,`description`,`sorting`,`status`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','sex','2','0','性别','255','1','admin','admin','2020-06-25 00:00:00','2020-06-25 00:00:00'),
('2','birthday','20','0','生日','255','1','admin','admin','2020-06-25 00:00:00','2020-06-25 00:00:00'),
('3','qq','15','0','QQ','255','1','admin','admin','2020-06-25 00:00:00','2020-06-25 00:00:00');

-- --------------------------------------------------------

--
-- 表的结构 `ay_member_group`
--

DROP TABLE IF EXISTS `ay_member_group`;
CREATE TABLE `ay_member_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gcode` varchar(20) NOT NULL,
  `gname` varchar(100) NOT NULL,
  `description` varchar(200) NOT NULL,
  `status` varchar(1) NOT NULL,
  `lscore` int(10) unsigned NOT NULL DEFAULT '0',
  `uscore` int(10) unsigned NOT NULL DEFAULT '0',
  `create_user` varchar(30) NOT NULL,
  `update_user` varchar(30) NOT NULL,
  `create_time` datetime NOT NULL,
  `update_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_member_group_gcode` (`gcode`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_member_group`
--

INSERT INTO `ay_member_group` (`id`,`gcode`,`gname`,`description`,`status`,`lscore`,`uscore`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','1','初级会员','初级会员具备基本的权限','1','0','999','admin','admin','2020-06-25 00:00:00','2020-06-25 00:00:00'),
('2','2','中级会员','中级会员具备部分特殊权限','1','1000','9999','admin','admin','2020-06-25 00:00:00','2020-06-25 00:00:00'),
('3','3','高级会员','高级会员具备全部特殊权限','1','10000','4294967295','admin','admin','2020-06-25 00:00:00','2020-06-25 00:00:00');

-- --------------------------------------------------------

--
-- 表的结构 `ay_menu`
--

DROP TABLE IF EXISTS `ay_menu`;
CREATE TABLE `ay_menu` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '菜单编号',
  `mcode` varchar(20) NOT NULL COMMENT '菜单编码',
  `pcode` varchar(20) NOT NULL COMMENT '上级菜单',
  `name` varchar(50) NOT NULL COMMENT '菜单名称',
  `url` varchar(100) NOT NULL COMMENT '菜单地址',
  `sorting` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '菜单排序',
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `shortcut` char(1) NOT NULL DEFAULT '0' COMMENT '桌面图标',
  `ico` varchar(30) NOT NULL COMMENT '菜单图标',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_menu_mcode` (`mcode`),
  KEY `ay_menu_pcode` (`pcode`),
  KEY `ay_menu_sorting` (`sorting`)
) ENGINE=MyISAM AUTO_INCREMENT=67 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_menu`
--

INSERT INTO `ay_menu` (`id`,`mcode`,`pcode`,`name`,`url`,`sorting`,`status`,`shortcut`,`ico`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','M101','0','系统管理','/admin/M101/index','900','1','0','fa-cog','admin','admin','0000-00-00 00:00:00','2018-04-30 14:52:57'),
('2','M102','M101','数据区域','/admin/Area/index','901','1','1','fa-sitemap','admin','admin','0000-00-00 00:00:00','2018-04-30 14:54:23'),
('3','M103','M101','系统菜单','/admin/Menu/index','902','0','0','fa-bars','admin','admin','0000-00-00 00:00:00','2018-04-30 14:54:35'),
('4','M104','M101','系统角色','/admin/Role/index','903','1','1','fa-hand-stop-o','admin','admin','0000-00-00 00:00:00','2018-04-30 14:54:43'),
('5','M105','M101','系统用户','/admin/User/index','904','1','1','fa-users','admin','admin','0000-00-00 00:00:00','2018-04-30 14:54:51'),
('6','M106','M101','系统日志','/admin/Syslog/index','905','1','1','fa-history','admin','admin','0000-00-00 00:00:00','2018-04-30 14:55:00'),
('7','M107','M101','类型管理','/admin/Type/index','906','0','0','fa-tags','admin','admin','0000-00-00 00:00:00','2018-04-30 14:55:13'),
('8','M108','M101','数据库管理','/admin/Database/index','907','1','1','fa-database','admin','admin','0000-00-00 00:00:00','2018-04-30 14:55:24'),
('9','M109','M101','服务器信息','/admin/Site/server','908','1','1','fa-info-circle','admin','admin','0000-00-00 00:00:00','2018-04-30 14:55:34'),
('10','M1101', 'M101', '图片清理', '/admin/ImageExt/index', '909', '1', '1', 'fa-trash', 'admin', 'admin', '0000-00-00 00:00:00', '2022-09-19 13:44:59'),
('11','M110','0','基础内容','/admin/M110/index','300','1','0','fa-sliders','admin','admin','2017-11-28 11:13:05','2018-04-30 14:48:29'),
('12','M112','M110','站点信息','/admin/Site/index','301','1','1','fa-cog','admin','admin','0000-00-00 00:00:00','2018-04-07 18:45:57'),
('13','M113','M110','公司信息','/admin/Company/index','302','1','1','fa-copyright','admin','admin','0000-00-00 00:00:00','2018-04-07 18:46:09'),
('29','M129','M110','内容栏目','/admin/ContentSort/index','303','1','1','fa-bars','admin','admin','2017-12-26 10:42:40','2018-04-07 18:46:25'),
('30','M130','0','文章内容','/admin/M130/index','400','1','0','fa-file-text-o','admin','admin','2017-12-26 10:45:36','2018-04-30 14:49:47'),
('31','M131','M130','单页内容','/admin/Single/index','401','0','0','fa-file-o','admin','admin','2017-12-26 10:46:35','2018-04-07 18:46:35'),
('32','M132','M130','列表内容','/admin/Content/index','402','0','0','fa-file-text-o','admin','admin','2017-12-26 10:48:17','2018-04-07 21:52:15'),
('36','M136','M156','定制标签','/admin/Label/index','203','1','1','fa-wrench','admin','admin','2018-01-03 11:52:40','2018-04-07 18:44:31'),
('50','M150','M157','留言信息','/admin/Message/index','501','1','1','fa-question-circle-o','admin','admin','2018-02-01 13:20:17','2018-07-07 23:45:09'),
('51','M151','M157','轮播图片','/admin/Slide/index','502','1','1','fa-picture-o','admin','admin','2018-03-01 14:57:41','2018-04-07 18:47:07'),
('52','M152','M157','友情链接','/admin/Link/index','503','1','1','fa-link','admin','admin','2018-03-01 14:58:45','2018-04-07 18:47:16'),
('53','M153','M156','配置参数','/admin/Config/index','201','1','1','fa-sliders','admin','admin','2018-03-21 14:52:05','2018-04-07 18:44:02'),
('61','M1000','M157','文章内链','/admin/Tags/index','505','1','0','fa-random','admin','admin','2019-07-12 08:25:41','2019-07-12 08:26:23'),
('55','M155','M156','模型管理','/admin/Model/index','204','1','1','fa-codepen','admin','admin','2018-03-25 17:16:06','2018-04-07 18:44:40'),
('56','M156','0','全局配置','/admin/M156/index','200','1','0','fa-globe','admin','admin','2018-03-25 17:20:43','2018-04-30 14:43:56'),
('58','M158','M156','模型字段','/admin/ExtField/index','205','1','1','fa-external-link','admin','admin','2018-03-25 21:24:43','2018-04-07 18:44:49'),
('57','M157','0','扩展内容','/admin/M157/index','500','1','0','fa-arrows-alt','admin','admin','2018-03-25 17:27:57','2018-04-30 14:50:34'),
('60','M160','M157','自定义表单','/admin/Form/index','504','1','1','fa-plus-square-o','admin','admin','2018-05-30 18:25:41','2018-05-31 23:55:10'),
('62','M1001','0','会员中心','/admin/M1001/index','600','1','0','fa-user-o','admin','admin','2019-10-04 08:25:41','2019-10-04 08:26:23'),
('63','M1002','M1001','会员等级','/admin/MemberGroup/index','601','1','0','fa-signal','admin','admin','2019-10-04 08:25:41','2019-10-04 08:26:23'),
('64','M1003','M1001','会员字段','/admin/MemberField/index','602','1','0','fa-wpforms','admin','admin','2019-10-04 08:25:41','2019-10-04 08:26:23'),
('65','M1004','M1001','会员管理','/admin/Member/index','603','1','0','fa-users','admin','admin','2019-10-04 08:25:41','2019-10-04 08:26:23'),
('66','M1005','M1001','文章评论','/admin/MemberComment/index','604','1','0','fa-commenting-o','admin','admin','2019-10-04 08:25:41','2019-10-04 08:26:23');

-- --------------------------------------------------------

--
-- 表的结构 `ay_menu_action`
--

DROP TABLE IF EXISTS `ay_menu_action`;
CREATE TABLE `ay_menu_action` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mcode` varchar(20) NOT NULL COMMENT '菜单编码',
  `action` varchar(20) NOT NULL COMMENT '类型编码',
  PRIMARY KEY (`id`),
  KEY `ay_menu_action_mcode` (`mcode`)
) ENGINE=MyISAM AUTO_INCREMENT=79 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_menu_action`
--

INSERT INTO `ay_menu_action` (`id`,`mcode`,`action`) VALUES
('1','M102','mod'),
('2','M102','del'),
('3','M102','add'),
('4','M103','mod'),
('5','M103','del'),
('6','M103','add'),
('7','M104','mod'),
('8','M104','del'),
('9','M104','add'),
('10','M105','mod'),
('11','M105','del'),
('12','M105','add'),
('13','M107','mod'),
('14','M107','del'),
('15','M107','add'),
('16','M111','mod'),
('17','M112','mod'),
('18','M114','mod'),
('19','M114','del'),
('20','M114','add'),
('21','M120','mod'),
('22','M120','del'),
('23','M120','add'),
('24','M129','mod'),
('25','M129','del'),
('26','M129','add'),
('27','M131','mod'),
('28','M132','mod'),
('29','M132','del'),
('30','M132','add'),
('31','M136','mod'),
('32','M136','del'),
('33','M136','add'),
('34','M141','mod'),
('35','M141','del'),
('36','M141','add'),
('37','M142','mod'),
('38','M142','del'),
('39','M142','add'),
('40','M143','mod'),
('41','M143','del'),
('42','M143','add'),
('43','M144','mod'),
('44','M144','del'),
('45','M144','add'),
('46','M145','mod'),
('47','M145','del'),
('48','M145','add'),
('49','M150','del'),
('50','M150','mod'),
('51','M151','mod'),
('52','M151','del'),
('53','M151','add'),
('54','M152','mod'),
('55','M152','del'),
('56','M152','add'),
('57','M155','mod'),
('58','M155','del'),
('59','M155','add'),
('60','M158','mod'),
('61','M158','del'),
('62','M158','add'),
('63','M160','add'),
('64','M160','del'),
('65','M160','mod'),
('66','M1000','add'),
('67','M1000','del'),
('68','M1000','mod'),
('69','M1002','add'),
('70','M1002','del'),
('71','M1002','mod'),
('72','M1003','add'),
('73','M1003','del'),
('74','M1003','mod'),
('75','M1004','add'),
('76','M1004','del'),
('77','M1004','mod'),
('78','M1005','del');

-- --------------------------------------------------------

--
-- 表的结构 `ay_message`
--

DROP TABLE IF EXISTS `ay_message`;
CREATE TABLE `ay_message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `acode` varchar(20) NOT NULL COMMENT '区域编码',
  `contacts` varchar(10) DEFAULT NULL COMMENT '联系人',
  `mobile` varchar(12) DEFAULT NULL COMMENT '联系电话',
  `content` varchar(500) DEFAULT NULL COMMENT '留言内容',
  `user_ip` varchar(11) NOT NULL DEFAULT '0' COMMENT 'IP地址',
  `user_os` varchar(30) NOT NULL COMMENT '操作系统',
  `user_bs` varchar(30) NOT NULL COMMENT '浏览器',
  `recontent` varchar(500) NOT NULL COMMENT '回复内容',
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '是否前台显示',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `uid` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ay_message_acode` (`acode`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_message`
--

INSERT INTO `ay_message` (`id`,`acode`,`contacts`,`mobile`,`content`,`user_ip`,`user_os`,`user_bs`,`recontent`,`status`,`create_user`,`update_user`,`create_time`,`update_time`,`uid`) VALUES
('1','cn','星梦','16888888888','PbootCMS真心很不错哦！','2130706433','Windows 10','Firefox','谢谢您对我们的大力支持与肯定！','1','admin','admin','2018-04-12 10:56:09','2018-04-12 10:56:42','0');

-- --------------------------------------------------------

--
-- 表的结构 `ay_model`
--

DROP TABLE IF EXISTS `ay_model`;
CREATE TABLE `ay_model` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '序号',
  `mcode` varchar(20) NOT NULL COMMENT '模型编号',
  `name` varchar(50) NOT NULL COMMENT '模型名称',
  `type` char(1) NOT NULL DEFAULT '2' COMMENT '是否列表类型',
  `urlname` varchar(100) NOT NULL DEFAULT '' COMMENT 'URL名称',
  `listtpl` varchar(50) NOT NULL COMMENT '列表页模板',
  `contenttpl` varchar(50) NOT NULL COMMENT '内容页模板',
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '模型状态',
  `issystem` char(1) NOT NULL DEFAULT '0' COMMENT '系统模型',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_model_mcode` (`mcode`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_model`
--

INSERT INTO `ay_model` (`id`,`mcode`,`name`,`type`,`urlname`,`listtpl`,`contenttpl`,`status`,`issystem`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','1','专题','1','about','','about.html','1','1','admin','admin','2018-04-11 17:16:01','2019-08-05 11:11:44'),
('2','2','新闻','2','list','newslist.html','news.html','1','1','admin','admin','2018-04-11 17:17:16','2019-08-05 11:12:04'),
('3','3','产品','2','list','productlist.html','product.html','1','0','admin','admin','2018-04-11 17:17:46','2019-08-05 11:12:17'),
('4','4','案例','2','list','caselist.html','case.html','1','0','admin','admin','2018-04-11 17:19:53','2019-08-05 11:12:26'),
('5','5','招聘','2','list','joblist.html','job.html','1','0','admin','admin','2018-04-11 17:24:34','2019-08-05 11:12:37');

-- --------------------------------------------------------

--
-- 表的结构 `ay_role`
--

DROP TABLE IF EXISTS `ay_role`;
CREATE TABLE `ay_role` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '角色编号',
  `rcode` varchar(20) NOT NULL COMMENT '角色编码',
  `name` varchar(30) NOT NULL COMMENT '角色名称',
  `description` varchar(50) NOT NULL COMMENT '角色描述',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_role_rcode` (`rcode`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_role`
--

INSERT INTO `ay_role` (`id`,`rcode`,`name`,`description`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','R101','系统管理员','系统管理员具有所有权限','admin','admin','2017-03-22 11:33:32','2019-08-05 11:22:02'),
('2','R102','内容管理员','内容管理员具有基本内容管理权限','admin','admin','2017-06-01 00:32:02','2019-08-05 11:22:12');

-- --------------------------------------------------------

--
-- 表的结构 `ay_role_area`
--

DROP TABLE IF EXISTS `ay_role_area`;
CREATE TABLE `ay_role_area` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rcode` varchar(20) NOT NULL,
  `acode` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ay_role_area_rcode` (`rcode`),
  KEY `ay_role_area_acode` (`acode`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_role_area`
--

INSERT INTO `ay_role_area` (`id`,`rcode`,`acode`) VALUES
('3','R101','cn'),
('4','R102','cn');

-- --------------------------------------------------------

--
-- 表的结构 `ay_role_level`
--

DROP TABLE IF EXISTS `ay_role_level`;
CREATE TABLE `ay_role_level` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `rcode` varchar(20) NOT NULL COMMENT '角色编码',
  `level` varchar(50) NOT NULL COMMENT '权限地址',
  PRIMARY KEY (`id`),
  KEY `ay_role_level_rcode` (`rcode`)
) ENGINE=MyISAM AUTO_INCREMENT=216 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_role_level`
--

INSERT INTO `ay_role_level` (`id`,`rcode`,`level`) VALUES
('165','R101','/admin/Role/index'),
('164','R101','/admin/Menu/mod'),
('163','R101','/admin/Menu/del'),
('162','R101','/admin/Menu/add'),
('161','R101','/admin/Menu/index'),
('160','R101','/admin/Area/mod'),
('159','R101','/admin/Area/del'),
('158','R101','/admin/Area/add'),
('157','R101','/admin/Area/index'),
('156','R101','/admin/M101/index'),
('155','R101','/admin/Tags/mod'),
('154','R101','/admin/Tags/del'),
('153','R101','/admin/Tags/add'),
('152','R101','/admin/Tags/index'),
('151','R101','/admin/Form/mod'),
('150','R101','/admin/Form/del'),
('149','R101','/admin/Form/add'),
('148','R101','/admin/Form/index'),
('147','R101','/admin/Link/mod'),
('146','R101','/admin/Link/del'),
('145','R101','/admin/Link/add'),
('144','R101','/admin/Link/index'),
('143','R101','/admin/Slide/mod'),
('142','R101','/admin/Slide/del'),
('141','R101','/admin/Slide/add'),
('140','R101','/admin/Slide/index'),
('139','R101','/admin/Message/mod'),
('138','R101','/admin/Message/del'),
('137','R101','/admin/Message/index'),
('136','R101','/admin/M157/index'),
('135','R101','/admin/Content/mod'),
('134','R101','/admin/Content/del'),
('133','R101','/admin/Content/add'),
('132','R101','/admin/Content/index'),
('131','R101','/admin/Single/mod'),
('130','R101','/admin/Single/index'),
('129','R101','/admin/M130/index'),
('128','R101','/admin/ContentSort/mod'),
('127','R101','/admin/ContentSort/del'),
('126','R101','/admin/ContentSort/add'),
('125','R101','/admin/ContentSort/index'),
('124','R101','/admin/Company/mod'),
('123','R101','/admin/Company/index'),
('122','R101','/admin/Site/mod'),
('121','R101','/admin/Site/index'),
('120','R101','/admin/M110/index'),
('119','R101','/admin/ExtField/mod'),
('118','R101','/admin/ExtField/del'),
('117','R101','/admin/ExtField/add'),
('116','R101','/admin/ExtField/index'),
('115','R101','/admin/Model/mod'),
('114','R101','/admin/Model/del'),
('113','R101','/admin/Model/add'),
('112','R101','/admin/Model/index'),
('111','R101','/admin/Label/mod'),
('110','R101','/admin/Label/del'),
('109','R101','/admin/Label/add'),
('108','R101','/admin/Label/index'),
('107','R101','/admin/Config/index'),
('106','R101','/admin/M156/index'),
('205','R102','/admin/Link/add'),
('204','R102','/admin/Link/index'),
('203','R102','/admin/Slide/mod'),
('202','R102','/admin/Slide/del'),
('201','R102','/admin/Slide/add'),
('200','R102','/admin/Slide/index'),
('199','R102','/admin/Message/mod'),
('198','R102','/admin/Message/del'),
('197','R102','/admin/Message/index'),
('196','R102','/admin/M157/index'),
('195','R102','/admin/Content/mod'),
('194','R102','/admin/Content/del'),
('193','R102','/admin/Content/add'),
('192','R102','/admin/Content/index'),
('191','R102','/admin/Single/mod'),
('190','R102','/admin/Single/index'),
('189','R102','/admin/M130/index'),
('188','R102','/admin/ContentSort/mod'),
('187','R102','/admin/ContentSort/del'),
('186','R102','/admin/ContentSort/add'),
('185','R102','/admin/ContentSort/index'),
('184','R102','/admin/Company/mod'),
('183','R102','/admin/Company/index'),
('182','R102','/admin/Site/mod'),
('181','R102','/admin/Site/index'),
('180','R102','/admin/M110/index'),
('166','R101','/admin/Role/add'),
('167','R101','/admin/Role/del'),
('168','R101','/admin/Role/mod'),
('169','R101','/admin/User/index'),
('170','R101','/admin/User/add'),
('171','R101','/admin/User/del'),
('172','R101','/admin/User/mod'),
('173','R101','/admin/Syslog/index'),
('174','R101','/admin/Type/index'),
('175','R101','/admin/Type/add'),
('176','R101','/admin/Type/del'),
('177','R101','/admin/Type/mod'),
('178','R101','/admin/Database/index'),
('179','R101','/admin/Site/server'),
('206','R102','/admin/Link/del'),
('207','R102','/admin/Link/mod'),
('208','R102','/admin/Form/index'),
('209','R102','/admin/Form/add'),
('210','R102','/admin/Form/del'),
('211','R102','/admin/Form/mod'),
('212','R102','/admin/Tags/index'),
('213','R102','/admin/Tags/add'),
('214','R102','/admin/Tags/del'),
('215','R102','/admin/Tags/mod');

-- --------------------------------------------------------

--
-- 表的结构 `ay_site`
--

DROP TABLE IF EXISTS `ay_site`;
CREATE TABLE `ay_site` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '站点编号',
  `acode` varchar(20) NOT NULL COMMENT '区域代码',
  `title` varchar(100) NOT NULL COMMENT '站点标题',
  `subtitle` varchar(200) NOT NULL COMMENT '站点副标题',
  `domain` varchar(50) NOT NULL COMMENT '站点地址',
  `logo` varchar(100) NOT NULL COMMENT '站点LOGO地址',
  `keywords` varchar(200) NOT NULL COMMENT '站点关键字',
  `description` varchar(500) NOT NULL COMMENT '站点描述',
  `icp` varchar(30) NOT NULL COMMENT '站点备案',
  `theme` varchar(30) NOT NULL COMMENT '站点主题',
  `statistical` varchar(500) NOT NULL COMMENT '站点统计码',
  `copyright` varchar(200) NOT NULL COMMENT '版权信息',
  PRIMARY KEY (`id`),
  KEY `ay_site_acode` (`acode`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_site`
--

INSERT INTO `ay_site` (`id`,`acode`,`title`,`subtitle`,`domain`,`logo`,`keywords`,`description`,`icp`,`theme`,`statistical`,`copyright`) VALUES
('1','cn','PbootCMS','永久开源免费的PHP企业网站开发建设管理系统','www.pbootcms.com','/static/images/logo.png','cms,免费cms,开源cms,企业cms,建站cms','PbootCMS是一套全新内核且永久开源免费的PHP企业网站开发建设管理系统，是一套高效、简洁、 强悍的可免费商用的PHP CMS源码，能够满足各类企业网站开发建设的需要。系统采用简单到想哭的模板标签，只要懂HTML就可快速开发企业网站。官方提供了大量网站模板免费下载和使用，将致力于为广大开发者和企业提供最佳的网站开发建设解决方案。','湘ICP备88888888号','default','','Copyright © 2018-2020 PbootCMS All Rights Reserved.');

-- --------------------------------------------------------

--
-- 表的结构 `ay_slide`
--

DROP TABLE IF EXISTS `ay_slide`;
CREATE TABLE `ay_slide` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '序号',
  `acode` varchar(20) NOT NULL COMMENT '区域编码',
  `gid` int(10) unsigned NOT NULL COMMENT '分组序号',
  `pic` varchar(100) NOT NULL COMMENT '图片地址',
  `link` varchar(100) NOT NULL COMMENT '跳转链接',
  `title` varchar(50) NOT NULL COMMENT '说明文字',
  `subtitle` varchar(100) NOT NULL COMMENT '副标题/描述',
  `sorting` int(11) NOT NULL COMMENT '排序',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `ay_slide_acode` (`acode`),
  KEY `ay_slide_gid` (`gid`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_slide`
--

INSERT INTO `ay_slide` (`id`,`acode`,`gid`,`pic`,`link`,`title`,`subtitle`,`sorting`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','cn','1','/static/upload/image/20180412/1523500997605565.jpg','http://www.pbootcms.com','PbootCMS','永久开源、免费的PHP建站系统','255','admin','admin','2018-03-01 16:19:03','2018-04-12 10:43:19'),
('2','cn','1','/static/upload/image/20180412/1523501147676550.jpg','http://www.pbootcms.com','PbootCMS','高效、简洁、强悍的PHP建站源码','255','admin','admin','2018-04-12 10:46:07','2018-04-12 10:46:07');

-- --------------------------------------------------------

--
-- 表的结构 `ay_syslog`
--

DROP TABLE IF EXISTS `ay_syslog`;
CREATE TABLE `ay_syslog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '日志编号',
  `level` varchar(20) NOT NULL COMMENT '信息等级',
  `event` varchar(200) NOT NULL COMMENT '事件',
  `user_ip` varchar(11) NOT NULL DEFAULT '0' COMMENT '客户端IP',
  `user_os` varchar(30) NOT NULL COMMENT '客户端系统',
  `user_bs` varchar(30) NOT NULL COMMENT '客户端浏览器',
  `create_user` varchar(30) NOT NULL COMMENT '创建人员',
  `create_time` datetime NOT NULL COMMENT '添加时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ay_tags`
--

DROP TABLE IF EXISTS `ay_tags`;
CREATE TABLE `ay_tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `acode` varchar(20) NOT NULL COMMENT '区域',
  `name` varchar(50) NOT NULL COMMENT '名称',
  `link` varchar(200) NOT NULL COMMENT '链接',
  `create_user` varchar(30) NOT NULL COMMENT '添加人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新人员',
  `create_time` datetime NOT NULL COMMENT '添加时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `ay_tags_acode` (`acode`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_tags`
--

INSERT INTO `ay_tags` (`id`,`acode`,`name`,`link`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','cn','PbootCMS','https://www.pbootcms.com','admin','admin','2019-07-12 14:33:13','2019-07-12 14:33:13');

-- --------------------------------------------------------

--
-- 表的结构 `ay_type`
--

DROP TABLE IF EXISTS `ay_type`;
CREATE TABLE `ay_type` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '类型编号',
  `tcode` varchar(20) NOT NULL COMMENT '类型编码',
  `name` varchar(30) NOT NULL COMMENT '类型名称',
  `item` varchar(30) NOT NULL COMMENT '类型项',
  `value` varchar(20) NOT NULL DEFAULT '0' COMMENT '类型值',
  `sorting` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `create_user` varchar(30) NOT NULL COMMENT '添加人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新时间',
  `create_time` datetime NOT NULL COMMENT '添加时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `ay_type_tcode` (`tcode`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_type`
--

INSERT INTO `ay_type` (`id`,`tcode`,`name`,`item`,`value`,`sorting`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','T101','菜单功能','新增','add','1','admin','admin','2017-04-27 07:28:34','2017-08-09 15:25:56'),
('2','T101','菜单功能','删除','del','2','admin','admin','2017-04-27 07:29:08','2017-08-09 15:23:34'),
('3','T101','菜单功能','修改','mod','3','admin','admin','2017-04-27 07:29:34','2017-08-09 15:23:32'),
('4','T101','菜单功能','导出','export','4','admin','admin','2017-04-27 07:30:42','2017-08-09 15:23:29'),
('5','T101','菜单功能','导入','import','5','admin','admin','2017-04-27 07:31:38','2017-08-09 15:23:27');

-- --------------------------------------------------------

--
-- 表的结构 `ay_user`
--

DROP TABLE IF EXISTS `ay_user`;
CREATE TABLE `ay_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户编号',
  `ucode` varchar(20) NOT NULL COMMENT '用户编码',
  `username` varchar(30) NOT NULL COMMENT '用户账号',
  `realname` varchar(30) NOT NULL COMMENT '真实名字',
  `password` char(32) NOT NULL COMMENT '用户密码',
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `login_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '登录次数',
  `last_login_ip` varchar(11) NOT NULL DEFAULT '0' COMMENT '最后登录IP',
  `create_user` varchar(30) NOT NULL COMMENT '添加人员',
  `update_user` varchar(30) NOT NULL COMMENT '更新用户',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ay_user_ucode` (`ucode`),
  KEY `ay_user_username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_user`
--

INSERT INTO `ay_user` (`id`,`ucode`,`username`,`realname`,`password`,`status`,`login_count`,`last_login_ip`,`create_user`,`update_user`,`create_time`,`update_time`) VALUES
('1','10001','admin','超级管理员','14e1b600b1fd579f47433b88e8d85291','1','0','2130706433','admin','admin','2017-05-08 18:50:30','2018-07-17 15:47:27');

-- --------------------------------------------------------

--
-- 表的结构 `ay_user_role`
--

DROP TABLE IF EXISTS `ay_user_role`;
CREATE TABLE `ay_user_role` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  `ucode` varchar(20) NOT NULL COMMENT '用户编码',
  `rcode` varchar(20) NOT NULL COMMENT '角色编码',
  PRIMARY KEY (`id`),
  KEY `ay_user_role_ucode` (`ucode`),
  KEY `ay_user_role_rcode` (`rcode`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `ay_user_role`
--

INSERT INTO `ay_user_role` (`id`,`ucode`,`rcode`) VALUES
('1','10001','R101');

-- --------------------------------------------------------

