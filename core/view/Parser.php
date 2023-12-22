<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月6日
 *  模板解析引擎 
 */
namespace core\view;

use core\basic\Config;

class Parser
{

    // 模板内容
    private static $content;

    // 模板路径在嵌套时必须
    private static $tplPath;

    // 包含文件
    private static $tplInc = array();

    /**
     * 编译公共方法
     *
     * @param string $tplPath
     *            模板主题目录，需要是物理路径
     * @param string $tplFile
     *            需要解析的模板文件，需要是物理路径
     * @return string|mixed
     */
    public static function compile($tplPath, $tplFile)
    {
        // 接收模板目录参数
        self::$tplPath = $tplPath;
        
        // 读取模板内容
        $content = file_get_contents($tplFile) ?: error('模板文件读取错误！' . $tplFile);
        
        // 去除内容Bom信息;
        self::$content = ltrim($content, "\xEF\xBB\xBF");
        
        // 解析文件包含，需要优先解析
        self::parInclude();
        
        // 添加包含文件记录
        self::$content .= "<?php return " . var_export(array_unique(self::$tplInc), 1) . "; ?>";
        
        // =====以下为直接输出方法=========
        self::parOutputUrl(); // 输出地址
        self::parOutputHomeUrl(); // 输出前端地址
        self::parOutputDefine(); // 输出常量
        self::parOutputVar(); // 输出变量
        self::parOutputObjVal(); // 输出对象
        self::parOutputConfig(); // 输出配置参数
        self::parOutputSession(); // 输出会话Session
        self::parOutputCookie(); // 输出会话Cookie
        self::parOutputServer(); // 输出环境变量
        self::parOutputPost(); // 输出POST请求值
        self::parOutputGet(); // 输出GET请求值
        self::parOutputArrVal(); // 输出数组
        self::parOutputFun(); // 使用函数
                              
        // =========以下为逻辑控制方法==========
        self::parIf(); // IF语句
        self::parForeachVar(); // Foreach语句
        self::parForeachValue(); // Foreach语句嵌套
        self::parForeachObj(); // Foreach对象属性
        self::parNote(); // 备注
        self::parPhp(); // PHP语句
                        
        // ============以下为变量解析方法==========
        self::parVar(); // 解析变量
        self::parObjVar(); // 解析对象
        self::parConfigVar(); // 解析配置
        self::parSession(); // 解析Session
        self::parCookie(); // 解析Cookie
        self::parServer(); // 解析环境变量
        self::parPost(); // 解析POST请求值
        self::parGet(); // 解析GET请求值
        self::parArrVar(); // 解析数组
        self::parFun(); // 解析函数
                        
        // 返回解释的内容
        return self::$content;
    }

    // 解析包含文件，支持多层嵌套
    private static function parInclude()
    {
        $pattern = '/\{include\s+file\s?=\s?([\"\']?)([\w\.\-\/@]+)([\"\']?)\s*\}/';
        if (preg_match_all($pattern, self::$content, $matches)) {
            $arr = $matches[0]; // 匹配到的所有“包含字符串”：{include file='head.html'}
            $brr = $matches[2]; // 包含的文件名：head.html
            $count = count($arr);
            for ($i = 0; $i < $count; $i ++) {
                // 然包含文件支持绝对路径，以/开头
                if (strpos($brr[$i], '/') === 0) {
                    $inc_file = ROOT_PATH . $brr[$i];
                } elseif (! ! $pos = strpos($brr[$i], '@')) {
                    $inc_file = APP_PATH . '/' . substr($brr[$i], 0, $pos) . '/view/' . basename(self::$tplPath) . '/' . substr($brr[$i], $pos + 1);
                } else {
                    if (M == 'home') { // 前台适应模板子目录
                        $htmldir = Config::get('tpl_html_dir') ? Config::get('tpl_html_dir') . '/' : '';
                        $inc_file = self::$tplPath . '/' . $htmldir . $brr[$i];
                    } else {
                        $inc_file = self::$tplPath . '/' . $brr[$i];
                    }
                }
                file_exists($inc_file) ?: error('包含文件不存在！' . $inc_file);
                if (! $inc_content = file_get_contents($inc_file)) {
                    error('包含的模板文件' . $brr[$i] . '读取错误！');
                } else {
                    self::$content = str_replace($arr[$i], $inc_content, self::$content); // 包含内容
                    self::$tplInc[] = $inc_file;
                }
            }
            // 最大数量不超过50，防止互相包含导致无限循环
            if (count(self::$tplInc) < 50) {
                self::parInclude();
            } else {
                error('检测到您模板中包含文件超过50个，请检查是否存在互相包含导致无限循环的情况！');
            }
        } else {
            return false;
        }
    }

    // 解析地址输出 {url./admin/index/index}
    private static function parOutputUrl()
    {
        $pattern = '/\{url\.([^\}]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo \\core\\basic\\Url::get('$1');?>", self::$content);
        }
    }

    // 解析地址输出 {homeurl./home/index/index}
    private static function parOutputHomeUrl()
    {
        $pattern = '/\{homeurl\.([^\}]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo \\core\\basic\\Url::home('$1');?>", self::$content);
        }
    }

    // 解析输出常量 如：{DB_HOST}
    private static function parOutputDefine()
    {
        $pattern = '/\{([A-Z_]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo $1;?>", self::$content);
        }
    }

    // 解析输出普通变量 如：{$name}
    private static function parOutputVar()
    {
        $pattern = '/\{\$([\w]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo \$this->getVar('$1');?>", self::$content);
        }
    }

    // 解析输出对象变量 如：{$user->name}
    private static function parOutputObjVal()
    {
        $pattern = '/\{\$([\w]+)(\->)(\{?)([^}]+)(\}?)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo @\$this->getVar('$1')$2$3$4$5;?>", self::$content);
        }
    }

    // 解析输出配置 如：{$config.public_app},支持多级
    private static function parOutputConfig()
    {
        $pattern = '/\{\$config\.([\w\.]+)\}/';
        if (preg_match_all($pattern, self::$content, $matchs)) {
            foreach ($matchs[0] as $key => $value) {
                if (strpos($matchs[1][$key], 'database') === false) {
                    self::$content = str_replace($matchs[0][$key], "<?php print_r(\\core\\basic\\Config::get('" . $matchs[1][$key] . "'));?>", self::$content);
                }
            }
        }
    }

    // 解析输出Session变量 如：{$session.username},支持多级
    private static function parOutputSession()
    {
        $pattern = '/\{\$session\.([\w\.]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo session('$1');?>", self::$content);
        }
    }

    // 解析输出Cookie变量 如：{$cookie.username}
    private static function parOutputCookie()
    {
        $pattern = '/\{\$cookie\.([\w]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo cookie('$1');?>", self::$content);
        }
    }

    // 解析输出Server变量 如：{$server.PATH_INFO}
    private static function parOutputServer()
    {
        $pattern = '/\{\$server\.([\w\-]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo escape_string(\$_SERVER['$1']);?>", self::$content);
        }
    }

    // 解析输出POST变量 如：{$post.username}
    private static function parOutputPost()
    {
        $pattern = '/\{\$post\.([\w\-]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo post('$1');?>", self::$content);
        }
    }

    // 解析输出GET变量 如：{$get.username}
    private static function parOutputGet()
    {
        $pattern = '/\{\$get\.([\w\-]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo get('$1');?>", self::$content);
        }
    }

    // 解析输出数组变量 如：{$user['name']} 2.0修改为：{$user.name},支持二维数组{$user.name.sex}
    private static function parOutputArrVal()
    {
        $pattern = '/\{\$([\w]+)\.([\w\-]+)(\.([\w\-]+))?\}/';
        if (preg_match_all($pattern, self::$content, $matches)) {
            foreach ($matches[0] as $key => $value) {
                if ($matches[3][$key]) {
                    self::$content = preg_replace($pattern, "<?php echo @\$this->vars['$1']['$2']['$4'];?>", self::$content);
                } else {
                    self::$content = preg_replace($pattern, "<?php echo @\$this->vars['$1']['$2'];?>", self::$content);
                }
            }
        }
    }

    // 应用函数 如：{fun=md5('aaa')}
    private static function parOutputFun()
    {
        $pattern = '/\{fun\s?=\s?([^\}]+)\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php echo $1;?>", self::$content);
        }
    }

    // 解析if语句 例：{if(a==b)}aaaa{else}bbbb{/if}
    private static function parIf()
    {
        $pattern = '/\{if\(([^}]+)\)\s*\}([\s\S]*)\{\/if\}/';
        $pattern_if = '/\{if\(([^}]+)\)\s*\}/';
        $pattern_end_if = '/\{\/if\}/';
        $pattern_else = '/\{else\}/';
        // 未配对的if不进行解析
        if (preg_match_all($pattern, self::$content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i ++) {
                $content = preg_replace($pattern_if, "<?php if ($1) {?>", $matches[0][$i]);
                $content = preg_replace($pattern_end_if, "<?php } ?>", $content);
                $content = preg_replace($pattern_else, "<?php } else { ?>", $content);
                self::$content = str_replace($matches[0][$i], $content, self::$content);
            }
        }
    }

    // 解析循环语句 {foreach $var(key,value,num)}...[num][value->name]或[value]...{/foreach}
    private static function parForeachVar()
    {
        $pattern_foreach = '/\{foreach\s+\$([\w]+)\(([\w]+),([\w]+)(,([\w]+))?\)\}/';
        $pattern_end_foreach = '/\{\/foreach\}/';
        
        if (preg_match_all($pattern_foreach, self::$content, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i ++) {
                if (! $matches[5][$i]) {
                    $matches[5][$i] = 'num';
                }
                // 解析首标签
                self::$content = str_replace($matches[0][$i], "<?php \$" . $matches[5][$i] . " = 0;foreach (\$this->getVar('" . $matches[1][$i] . "') as \$" . $matches[2][$i] . " => \$" . $matches[3][$i] . ") { \$" . $matches[5][$i] . "++;?>", self::$content);
                
                // 解析序号
                $pattern_num = '/\[(' . $matches[5][$i] . ')\]/';
                if (preg_match($pattern_num, self::$content)) {
                    if (defined('PAGE')) {
                        self::$content = preg_replace($pattern_num, "<?php echo @(PAGE-1)*PAGESIZE+\$$1; ?>", self::$content);
                    } else {
                        self::$content = preg_replace($pattern_num, "<?php echo \$$1; ?>", self::$content);
                    }
                }
                
                // 解析key
                $pattern_key = '/\[(' . $matches[2][$i] . ')\]/';
                if (preg_match($pattern_key, self::$content)) {
                    self::$content = preg_replace($pattern_key, "<?php echo \$$1; ?>", self::$content);
                }
                
                // 解析内部变量
                $pattern_var = '/\[(' . $matches[3][$i] . ')(\[[\'\"][\w]+[\'\"]\])?(\-\>[\w$]+)?\]/';
                self::$content = preg_replace($pattern_var, "<?php echo \$$1$2$3; ?>", self::$content);
            }
            // 解析闭合标签
            self::$content = str_replace('{/foreach}', "<?php } ?>", self::$content);
        }
    }

    // 解析循环语句嵌套 {foreach $value->name(key,value,num)}...[num][value->name]或[value]...{/foreach}
    private static function parForeachValue()
    {
        $pattern_foreach = '/\{foreach\s+\$([\w][\w\->]+)\(([\w]+),([\w]+)(,([\w]+))?\)\}/';
        $pattern_end_foreach = '/\{\/foreach\}/';
        
        if (preg_match_all($pattern_foreach, self::$content, $matches)) {
            $count = count($matches[0]);
            
            for ($i = 0; $i < $count; $i ++) {
                if (! $matches[5][$i]) {
                    $matches[5][$i] = 'num';
                }
                // 解析首标签
                self::$content = str_replace($matches[0][$i], "<?php \$" . $matches[5][$i] . " = 0;foreach (\$" . $matches[1][$i] . " as \$" . $matches[2][$i] . " => \$" . $matches[3][$i] . ") { \$" . $matches[5][$i] . "++;?>", self::$content);
                
                // 解析序号
                $pattern_num = '/\[(' . $matches[5][$i] . ')\]/';
                if (preg_match($pattern_num, self::$content)) {
                    if (defined('PAGE')) {
                        self::$content = preg_replace($pattern_num, "<?php echo (PAGE-1)*PAGESIZE+\$$1; ?>", self::$content);
                    } else {
                        self::$content = preg_replace($pattern_num, "<?php echo \$$1; ?>", self::$content);
                    }
                }
                
                // 解析key
                $pattern_key = '/\[(' . $matches[2][$i] . ')\]/';
                if (preg_match($pattern_key, self::$content)) {
                    self::$content = preg_replace($pattern_key, "<?php echo \$$1; ?>", self::$content);
                }
                
                // 解析内部变量
                $pattern_var = '/\[(' . $matches[3][$i] . ')(\[[\'\"][\w]+[\'\"]\])?(\-\>[\w$]+)?\]/';
                self::$content = preg_replace($pattern_var, "<?php echo \$$1$2$3; ?>", self::$content);
            }
            // 解析闭合标签
            self::$content = str_replace('{/foreach}', "<?php }?>", self::$content);
        }
    }

    // 解析循环语句 {foreach [$var->name](key,value,num)}...{/foreach}
    private static function parForeachObj()
    {
        $pattern_foreach = '/\{foreach\s+\[\$([\w]+)\-\>([\w]+)\]\(([\w]+),([\w]+)(,([\w]+))?\)\}/';
        $pattern_end_foreach = '/\{\/foreach\}/';
        
        if (preg_match_all($pattern_foreach, self::$content, $matches)) {
            $count = count($matches[0]);
            
            for ($i = 0; $i < $count; $i ++) {
                if (! $matches[6][$i]) {
                    $matches[6][$i] = 'num';
                }
                // 解析首标签
                self::$content = str_replace($matches[0][$i], "<?php \$" . $matches[6][$i] . " = 0;foreach (\$this->getVar('" . $matches[1][$i] . "')->" . $matches[2][$i] . " as \$" . $matches[3][$i] . " => \$" . $matches[4][$i] . ") { \$" . $matches[6][$i] . "++;?>", self::$content);
                
                // 解析序号
                $pattern_num = '/\[(' . $matches[6][$i] . ')\]/';
                if (preg_match($pattern_num, self::$content)) {
                    if (defined('PAGE')) {
                        self::$content = preg_replace($pattern_num, "<?php echo @(PAGE-1)*PAGESIZE+\$$1; ?>", self::$content);
                    } else {
                        self::$content = preg_replace($pattern_num, "<?php echo \$$1; ?>", self::$content);
                    }
                }
                
                // 解析key
                $pattern_key = '/\[(' . $matches[3][$i] . ')\]/';
                if (preg_match($pattern_key, self::$content)) {
                    self::$content = preg_replace($pattern_key, "<?php echo \$$1; ?>", self::$content);
                }
                
                // 解析内部变量
                $pattern_var = '/\[(' . $matches[4][$i] . ')(\[[\'\"][\w]+[\'\"]\])?(\-\>[\w$]+)?\]/';
                self::$content = preg_replace($pattern_var, "<?php echo \$$1$2$3; ?>", self::$content);
            }
            // 解析闭合标签
            self::$content = str_replace('{/foreach}', "<?php } ?>", self::$content);
        }
    }

    // PHP代码注释{#}...{#}
    private static function parNote()
    {
        $pattern = '/\{#\}(\s\S]*?)\{#\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php
            /* $1 */
            ?>", self::$content);
        }
    }

    // 原生PHP代码{php}...{/php}
    private static function parPhp()
    {
        $pattern = '/\{php\}([\s\S]*?)\{\/php\}/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "<?php  $1?>", self::$content);
        }
    }

    // 解析变量[$varname]
    private static function parVar()
    {
        $pattern = '/\[\$([\w]+)\]/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "\$this->getVar('$1')", self::$content);
        }
    }

    // 解析对象变量 [$user->name]
    private static function parObjVar()
    {
        $pattern = '/\[\$([\w]+)\-\>([\w\$]+)\]/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "\$this->getVar('$1')->$2", self::$content);
        }
    }

    // 解析配置变量[$config.name],支持多级
    private static function parConfigVar()
    {
        $pattern = '/\[\$config\.([\w\.]+)\]/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "\\core\\basic\\Config::get('$1')", self::$content);
        }
        if (preg_match_all($pattern, self::$content, $matchs)) {
            foreach ($matchs[0] as $key => $value) {
                if (strpos($matchs[1][$key], 'database') === false) {
                    self::$content = str_replace($matchs[0][$key], "\\core\\basic\\Config::get('" . $matchs[1][$key] . "')", self::$content);
                }
            }
        }
    }

    // 解析Session [$session.name],支持多级
    private static function parSession()
    {
        $pattern = '/\[\$session\.([\w\.]+)\]/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "session('$1')", self::$content);
        }
    }

    // 解析Cookie [$cookie.name]
    private static function parCookie()
    {
        $pattern = '/\[\$cookie\.([\w]+)\]/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "cookie('$1')", self::$content);
        }
    }

    // 解析Server [$server.name]
    private static function parServer()
    {
        $pattern = '/\[\$server\.([\w]+)\]/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "escape_string(\$_SERVER['$1'])", self::$content);
        }
    }

    // 解析POST [$post.id]
    private static function parPost()
    {
        $pattern = '/\[\$post\.([\w]+)\]/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "post('$1')", self::$content);
        }
    }

    // 解析GET[$get.id]
    private static function parGet()
    {
        $pattern = '/\[\$get\.([\w]+)\]/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "get('$1')", self::$content);
        }
    }

    // 解析数组变量 [$user['name']] 2.0修改为[$user.name],支持二维数组[$user.name.sex]
    private static function parArrVar()
    {
        $pattern = '/\[\$([\w]+)\.([\w\-]+)(\.([\w\-]+))?\]/';
        if (preg_match_all($pattern, self::$content, $matches)) {
            foreach ($matches[0] as $key => $value) {
                if ($matches[3][$key]) {
                    self::$content = preg_replace($pattern, "\$this->vars['$1']['$2']['$4']", self::$content);
                } else {
                    self::$content = preg_replace($pattern, "\$this->vars['$1']['$2']", self::$content);
                }
            }
        }
    }

    // 内部应用函数 如：[fun=md5('aaa')]
    private static function parFun()
    {
        $pattern = '/\[fun=([^\]]+)\]/';
        if (preg_match($pattern, self::$content)) {
            self::$content = preg_replace($pattern, "$1", self::$content);
        }
    }
}
