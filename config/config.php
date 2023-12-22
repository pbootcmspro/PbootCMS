<?php
return array(
    
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
        'format' => 'jpg,jpeg,png,gif,xls,xlsx,doc,docx,ppt,pptx,rar,zip,pdf,txt,mp4,avi,flv,rmvb,mp3,otf,ttf',
        'max_width' => '1920',
        'max_height' => ''
    ),
    
    // 缩略图配置
    'ico' => array(
        'max_width' => '1000',
        'max_height' => '1000'
    ),
    
    // 模块模板路径定义
    'tpl_dir' => array(
        'home' => '/template'
    )

);
 