<?php
return array(
    // 是否调试模式
    "debug" => false,
    
    // 定义CMS名称
    'cmsname' => 'PbootCMS',
    
    // 模板内容输出缓存开关
    'tpl_html_cache' => 1,
    
    // 模板内容缓存有效时间（秒）
    'tpl_html_cache_time' => 900000000000,
    
    // 会话文件使用网站路径
    'session_in_sitepath' => 1,
    
    // 默认分页大小
    'pagesize' => 15,
    
    // 分页条数字数量
    'pagenum' => 5,
    
    // 访问页面规则，如禁用浏览器、操作系统类型
    'access_rule' => array(
        'deny_bs' => 'MJ12bot,IE6,IE7'
    ),
    
    // 上传配置
    'upload' => array(
        'format' => 'jpg,jpeg,png,gif,webp,xls,xlsx,doc,docx,ppt,pptx,rar,zip,pdf,txt,mp4,avi,flv,rmvb,mp3,otf,ttf',
        'max_width' => '1920',
        'max_height' => '',
        // 亚马逊S3协议通用OSS服务配置（启用后上传到 OSS，否则上传到本地）
        'oss_enabled' => false, // 是否启用 OSS：true 启用，false 不启用
        'oss_config' => array(
            'accessKeyId' => 'Your AccessKey ID',
            'accessKeySecret' => 'Your AccessKey Secret',
            'endpoint' => 'OSS Endpoint',
            'bucket' => 'Bucket Name',
            'cdnDomain' => ''
        )
    ),
    
    // 缩略图配置
    'ico' => array(
        'max_width' => '1000',
        'max_height' => '1000'
    ),
    
    // 模块模板路径定义
    'tpl_dir' => array(
        'home' => '/template'
    ),

    // 缓存配置
    'cache' => array(
        // 缓存驱动：filecache（文件缓存）, memcache（Memcache缓存）
        'handler' => 'filecache',
        // 文件缓存目录（仅在handler为filecache时有效）
        'dir' => RUN_PATH . '/cache',
        // Memcache服务器配置（仅在handler为memcache时有效）
        'server' => array(
            'host' => '127.0.0.1',
            'port' => 11211
        )
    ),

);
 