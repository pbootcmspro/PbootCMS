<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月6日
 *  生成指定模块下控制器方法的跳转路径
 */
namespace core\basic;

class Url
{

    // 存储已经生成过的地址信息
    private static $urls = array();

    // 接收控制器方法完整访问路径，如：/home/Index/index /模块/控制器/方法/.. 路径，生成可访问地址
    public static function get($path, $suffix = null)
    {
        if (strpos($path, 'http') === 0 || ! $path) {
            return $path;
        }
        
        $path = trim_slash($path); // 去除两端斜线
        
        if (! isset(self::$urls[$path])) {
            
            $path_arr = explode('/', $path); // 地址数组
            
            if ($suffix && Config::get('app_url_type') == 2 && strrpos(strtolower($_SERVER["SCRIPT_NAME"]), 'index.php') !== false) {
                $url_ext = Config::get('url_rule_suffix'); // 伪静态文件形式
            } elseif (Config::get('app_url_type') == 1 || Config::get('app_url_type') == 2) {
                $url_ext = '/'; // pathinfo目录形式
            } else {
                $url_ext = '';
            }
            
            // 路由处理
            if (! ! $routes = Config::get('url_route')) {
                foreach ($routes as $key => $value) {
                    // 去除两端斜线
                    $value = trim_slash($value);
                    $key = trim_slash($key);
                    
                    // 替换原来正则为替换内容
                    if (preg_match_all('/\(.*?\)/', $key, $source)) {
                        foreach ($source[0] as $kk => $vk) {
                            $key = str_replace($vk, '$' . ($kk + 1), $key);
                        }
                    }
                    
                    // 替换原来替换内容为正则
                    if (preg_match_all('/\$([0-9]+)/', $value, $destination)) {
                        foreach ($destination[1] as $kv => $vv) {
                            $value = str_replace($destination[0][$kv], $source[0][$vv - 1], $value);
                        }
                    }
                    
                    // 执行匹配替换
                    if (preg_match('{' . $value . '$}i', $path)) {
                        $path = preg_replace('{' . $value . '$}i', $key, $path);
                    } elseif (preg_match('{' . $value . '\/}i', $path)) {
                        $path = preg_replace('{' . $value . '\/}i', $key . '/', $path);
                    }
                }
            }
            
            // 域名绑定处理匹配
            $cut_str = '';
            if (! ! $domains = Config::get('app_domain_bind')) {
                foreach ($domains as $key => $value) {
                    $value = trim_slash($value); // 去除两端斜线
                    if (strpos($path, $value . '/') === 0) {
                        $cut_str = $value;
                        $server_name = get_http_host();
                        if ($server_name != $key) { // 绑定的域名与当前域名不一致时，添加主机地址
                            $host = is_https() ? 'https://' . $key : 'http://' . $key;
                        } else {
                            $host = '';
                        }
                        break;
                    }
                }
            }
            
            // 入口文件绑定匹配
            if (defined('URL_BIND') && $path_arr[0] == M) {
                $cut_str = trim_slash(URL_BIND);
            }
            
            // 执行URL简化
            if ($cut_str) {
                $path = substr($path, strlen($cut_str) + 1);
            }
            
            // 保存处理过的地址
            if ($path) {
                self::$urls[$path] = $host . url_index_path() . '/' . $path . $url_ext;
            } else {
                self::$urls[$path] = $host . url_index_path(); // 获取根路径前置地址
            }
        }
        return self::$urls[$path];
    }

    // 生成前端地址
    public static function home($path, $suffix = null, $qs = null)
    {
        if (! isset(self::$urls[md5($path . $suffix . $qs)])) {
            $url_rule_type = Config::get('url_rule_type') ?: 3;
            $url_rule_suffix = Config::get('url_rule_suffix') ?: '.html';
            
            if ($suffix === true) {
                $suffix = $url_rule_suffix;
            } elseif ($suffix === false) {
                $suffix = '';
            } else {
                $suffix = '/';
            }
            
            // 去除默认模块及控制器部分
            $path = trim($path, '/');
            $path = str_replace('home/Index', '', $path);
            $path = trim($path, '/');
            
            if (! $path) { // 地址前缀
                if ($url_rule_type == 1) {
                    $link = SITE_INDEX_DIR . '/index.php/';
                } elseif ($url_rule_type == 2) {
                    $link = SITE_INDEX_DIR . '/';
                } else {
                    $link = SITE_INDEX_DIR . '/?';
                }
            } else {
                switch ($url_rule_type) {
                    case '1': // 普通模式
                        $qs = $qs ? "?" . $qs : '';
                        $link = SITE_INDEX_DIR . '/index.php' . '/' . $path . $suffix . $qs;
                        break;
                    case '2': // 伪静态模式
                        $qs = $qs ? "?" . $qs : '';
                        $link = SITE_INDEX_DIR . '/' . $path . $suffix . $qs;
                        break;
                    case '3': // 兼容模式
                        $qs = $qs ? "&" . $qs : '';
                        $link = SITE_INDEX_DIR . '/?' . $path . $suffix . $qs;
                        break;
                    default:
                        error('地址模式设置错误,请登录后台重新设置！');
                }
            }
            self::$urls[md5($path . $suffix . $qs)] = $link;
        }
        return self::$urls[md5($path . $suffix . $qs)];
    }
}