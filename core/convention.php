<?php
/**
 * 框架默认配置,请勿直接修改本惯性配置文件，避免影响系统升级！
 * 需要自定义时候请在站点config目录下新建config.php配置,对需要修改的参数复写
 */
return array(
    // 调试模式
    'debug' => false,
    
    // 系统授权码
    'sn' => '281BE285D7',
    
    // 内核版本
    'core_version' => '1.8.1',
    
    // 配置模块
    'public_app' => 'home,admin,api',
    
    // 设置URL模式(此处不影响CMS前端模式),1、基本模式,2、伪静态模式，3、兼容模式
    'app_url_type' => 3,
    
    // 模板编译缓存开关
    'tpl_parser_cache' => 1,
    
    // 模板内容输出缓存开关
    'tpl_html_cache' => 0,
    
    // 模板内容缓存有效时间（秒）
    'tpl_html_cache_time' => 900,
    
    // URL允许的特殊字符，正则模型或直接写 ,如：\*
    'url_allow_char' => '+',
    
    // 前台URL分隔符
    'url_break_char' => '_',
    
    // URL地址后缀名
    'url_rule_suffix' => '.html',
    
    // URL地址路由，如：// 'home/index' => 'home/index/index'
    'url_route' => array(),
    
    // 应用域名绑定 ，支持到模块、控制器、方法，如：'localhost'=>'admin'
    'app_domain_bind' => array(),
    
    // 模块模板路径定义,不定义均采用框架默认
    'tpl_dir' => array(
        'home' => '/template'
    ),
    
    // 控制器返回数据输出方式
    'return_data_type' => 'html',
    
    // 日志记录方式，text文本记录，db为数据库记录
    'log_record_type' => 'db',
    
    // 默认分页大小
    'pagesize' => 15,
    
    // 分页条数字数量
    'pagenum' => 5,
    
    // 会话文件使用网站路径
    'session_in_sitepath' => 1,
    
    // 配置会话缓存，files,memcache,redis
    'session' => array(
        'handler' => 'files',
        'path' => 'tcp://127.0.0.1:11211;'
    ),
    
    // 缓存服务器配置,memcache,redis, server支持多节点server1,server2...
    'cache' => array(
        'handler' => 'memcache',
        'server' => array(
            'host' => '127.0.0.1',
            'port' => '11211'
        )
    ),
    
    // 访问页面规则，如禁用浏览器、操作系统类型
    'access_rule' => array(
        'deny_bs' => '', // 如：IE6,IE7,IE8，不允许IE6,IE7,IE8
        'allow_bs' => '', // 如：IE11，只允许IE11
        'deny_os' => '', // 如：Windows 9X，不允许Windows 9X
        'allow_os' => '' // 如：Windows 10,只允许Windows 10
    ),
    
    // 上传配置
    'upload' => array(
        'format' => 'jpg,jpeg,png,gif,xls,xlsx,doc,docx,ppt,pptx,rar,zip,pdf,txt,mp4,avi,flv,rmvb,mp3,otf,ttf',
        'max_width' => '1920',
        'max_height' => ''
    ),
    
    // 缩略图配置
    'ico' => array(
        'max_width' => '1000',
        'max_height' => '1000'
    ),
    
    // 数据库连接配置,主从配置时,如果配置多台从服务器，通过在slave下数组配置slave1，slave2...
    'database' => array(
        'type' => 'mysqli', // 数据库连接驱动 mysqli,sqlite,pdo_mysql,pdo_sqlite,pdo_pgsql
        'prefix' => 'ay_', // 数据库表前缀
        'charset' => 'utf8', // 数据库编码
        'transaction' => false, // 开启事务
        
        'host' => '127.0.0.1', // 数据库服务器
        'user' => 'root', // 数据库连接用户名
        'passwd' => 'root', // 数据库连接密码
        'port' => '3306', // 数据库端口
        'dbname' => 'pboot' // 数据库名称,如果Sqlite直接填写路径,如：/data/pboot.db
                                
    // 'slave' => array( 'host' => '127.0.0.1','user' => 'root','passwd' => 'root','port' => '3306','dbname' => 'pboot')
    ),
    
    // 第二参数作为值的控制器,缩短地址
    'second_rvar' => array(
        'Form', // 第二参数传递fcode
        'Tag' // 第二参数传递tag
    )
);
 