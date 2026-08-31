<?php
/**
 * @copyright (C)2016-2099
 * @author pbootcms
 * @email support@pbootcms.com
 * @date 2026年08月31日
 *  公共请求参数校验
 */
namespace core\basic;

class Request
{

    /**
     * 前台公共 query 校验入口（缓存未命中、业务渲染前调用）
     */
    public static function validate()
    {
        self::validatePageParam();
    }

    /**
     * page 未传时允许；传入时必须是 QUERY_STRING 中唯一、标量的正整数
     */
    protected static function validatePageParam()
    {
        if (! isset($_SERVER['QUERY_STRING'])) {
            return;
        }
        $raw = (string) $_SERVER['QUERY_STRING'];
        if ($raw === '') {
            return;
        }

        $pageCount = 0;
        $pageValue = null;

        foreach (self::splitQueryPairs($raw) as $pair) {
            if ($pair === '') {
                continue;
            }
            $eqPos = strpos($pair, '=');
            if ($eqPos === false) {
                $name = rawurldecode($pair);
                $value = '';
            } else {
                $name = rawurldecode(substr($pair, 0, $eqPos));
                $value = rawurldecode(substr($pair, $eqPos + 1));
            }

            // PHP 会将参数名中的 NUL 截断，必须在与 $_GET 语义不一致前拒绝
            if (strpos($name, "\0") !== false) {
                self::reject();
            }

            if ($name === 'page') {
                $pageCount ++;
                $pageValue = $value;
                continue;
            }

            // 数组形式 page[] / page[0] 等一律拒绝
            if (strncmp($name, 'page[', 5) === 0) {
                self::reject();
            }
        }

        if ($pageCount === 0) {
            return;
        }
        if ($pageCount > 1) {
            self::reject();
        }
        if (! is_string($pageValue) || $pageValue === '' || ! preg_match('/^[1-9]\d*$/', $pageValue)) {
            self::reject();
        }
    }

    /**
     * 按原始 QUERY_STRING 分割参数对，不调用 PHP 内置 query 解析函数
     *
     * @param string $queryString
     * @return array
     */
    protected static function splitQueryPairs($queryString)
    {
        $separators = (string) ini_get('arg_separator.input');
        if ($separators === '') {
            $separators = '&';
        }
        return preg_split('/[' . preg_quote($separators, '/') . ']/', $queryString);
    }

    /**
     * 非法公共参数：通用 400，不泄露路径或规则
     */
    protected static function reject()
    {
        if (! headers_sent()) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo 'Bad Request';
        exit();
    }
}
