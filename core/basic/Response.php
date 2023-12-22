<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月5日
 *  内容输出类
 */
namespace core\basic;

class Response
{

    // 根据配置文件选择
    public static function handle($data)
    {
        if (Config::get('return_data_type') == 'html') {
            print_r($data);
        } else {
            if (array_key_exists('code', $data)) {
                $code = $data['code'];
                unset($data['code']);
                self::json($code, $data);
            } else {
                self::json(1, $data);
            }
        }
    }

    // 服务端API返回JSON数据
    public static function json($code, $data, $tourl = null)
    {
        @ob_clean();
        $output['code'] = $code ?: 0;
        $output['data'] = $data ?: array();
        $output['tourl'] = $tourl ?: "";
        
        if (defined('ROWTOTAL')) {
            $output['rowtotal'] = ROWTOTAL;
        } else {
            if (is_array($data) || is_object($data)) {
                $output['rowtotal'] = count($data);
            } else {
                $output['rowtotal'] = 1;
            }
        }
        
        if (PHP_VERSION >= 5.4) { // 中文不编码 5.4+
            $option = JSON_UNESCAPED_UNICODE;
        } else {
            $option = JSON_HEX_TAG;
        }
        echo json_encode($output, $option);
        exit();
    }
}