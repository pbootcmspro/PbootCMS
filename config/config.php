<?php
return array(
    
    // 定义CMS名称
    'cmsname' => 'PbootCMS',
    
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
    
    // 上传配置 允许上传的扩展名 (英文逗号分隔)
    // 仅可配置 `core/function/file.php`中`upload_catalog_extensions()`母集内的扩展；母集外或危险扩展会被自动忽略
    // 缩小范围示例：'format' => 'jpg,jpeg,png,gif'
    'upload' => array(
        'format' => 'jpg,jpeg,png,gif,webp,svg,svgz,avif,xls,xlsx,doc,docx,ppt,pptx,rar,zip,pdf,txt,mp4,avi,flv,rmvb,mp3,otf,ttf',
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
    ),
    
    // 仅当站点前置 Nginx/CDN 时启用；勿填 0.0.0.0/0
     //'trusted_proxies' => '127.0.0.1',

);
 