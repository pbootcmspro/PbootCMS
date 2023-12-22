<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年10月15日
 *  配置信息读取类 
 */
namespace core\basic;

class Config
{

    // 存储配置信息
    protected static $configs;

    // 直接获取配置参数
    public static function get($item = null, $array = false)
    {
        // 自动载入配置文件
        if (! isset(self::$configs)) {
            self::$configs = self::loadConfig();
        }
        // 返回全部配置
        if ($item === null) {
            return self::$configs;
        }
        $items = explode('.', $item);
        if (isset(self::$configs[$items[0]])) {
            $value = self::$configs[$items[0]];
        } else {
            return null;
        }
        $items_len = count($items);
        for ($i = 1; $i < $items_len; $i ++) {
            if (isset($value[$items[$i]])) {
                $value = $value[$items[$i]];
            } else {
                return null;
            }
        }
        // 强制返回数据为数组形式
        if ($array && ! is_array($value)) {
            if ($value) {
                $value = explode(',', $value);
                $value = array_map('trim', $value); // 去空格
            } else {
                $value = array();
            }
        }
        return $value;
    }

    // 写入配置文件
    public static function set($itemName, array $data, $multistage = false, $assign = true)
    {
        if ($data) {
            $path = RUN_PATH . '/config/' . $itemName . '.php';
            
            // 是否使用多级
            if ($multistage) {
                // 如果获取到配置信息，执行合并
                if (! ! $configs = self::get($itemName)) {
                    $data = mult_array_merge($configs, $data);
                }
                $config[$itemName] = $data;
            } else {
                $config = $data;
            }
            
            // 写入
            if (check_file($path, true)) {
                $result = file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";");
                if ($assign) { // 缓存后是否注入配置
                    self::assign($path);
                }
                return $result;
            } else {
                return false;
            }
        }
    }

    // 载入配置文件
    private static function loadConfig()
    {
        // 载入配置惯性文件
        if (file_exists(CORE_PATH . '/convention.php')) {
            $configs = require CORE_PATH . '/convention.php';
        } else {
            die('系统框架文件丢失，惯性配置文件不存在！');
        }
        
        // 载入用户主配置文件
        if (file_exists(CONF_PATH . '/config.php')) {
            $config = require CONF_PATH . '/config.php';
            $configs = mult_array_merge($configs, $config);
        }
        
        // 载入用户数据库配置文件
        if (file_exists(CONF_PATH . '/database.php')) {
            $config = require CONF_PATH . '/database.php';
            $configs = mult_array_merge($configs, $config);
        }
        
        // 载入用户路由配置文件
        if (file_exists(CONF_PATH . '/route.php')) {
            $config = require CONF_PATH . '/route.php';
            $configs = mult_array_merge($configs, $config);
        }
        
        // 载入扩展的配置文件
        $ext_path = CONF_PATH . '/ext';
        if (is_dir($ext_path) && function_exists('scandir')) {
            $files = scandir($ext_path);
            for ($i = 0; $i < count($files); $i ++) {
                $file = $ext_path . '/' . $files[$i];
                if (is_file($file)) {
                    $config = require $file;
                    $configs = mult_array_merge($configs, $config);
                }
            }
        }
        
        // 载入系统路由文件
        if (file_exists(APP_PATH . '/common/route.php')) {
            $config = require APP_PATH . '/common/route.php';
            $configs = mult_array_merge($configs, $config);
        }
        
        // 载入应用版本文件
        if (file_exists(APP_PATH . '/common/version.php')) {
            $config = require APP_PATH . '/common/version.php';
            $configs = mult_array_merge($configs, $config);
        }
        
        // 载入系统配置缓存
        if (file_exists(RUN_PATH . '/config/' . md5('config') . '.php')) {
            $config = require RUN_PATH . '/config/' . md5('config') . '.php';
            $configs = mult_array_merge($configs, $config);
        }
        
        // 载入区域配置缓存
        if (file_exists(RUN_PATH . '/config/' . md5('area') . '.php')) {
            $config = require RUN_PATH . '/config/' . md5('area') . '.php';
            $configs = mult_array_merge($configs, $config);
        }
        
        // 清理缓冲区，避免配置文件出现Bom时影响显示
        @ob_clean();
        return $configs;
    }

    // 配置文件注入
    public static function assign($filePath)
    {
        if (! file_exists($filePath)) {
            return;
        }
        
        $assign_config = require $filePath;
        if (! is_array($assign_config))
            return;
        
        if (self::$configs) {
            $configs = mult_array_merge(self::$configs, $assign_config);
        } else {
            $configs = $assign_config;
        }
        self::$configs = $configs;
        return true;
    }
}

