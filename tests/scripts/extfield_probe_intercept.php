<?php
/**
 * 拦截 ExtFieldController 同命名空间下的 success/error/alert_back，避免探测进程 exit
 */
namespace app\admin\controller\content {

    function success($string, $jump_url = null, $time = 2)
    {
        $GLOBALS['__extfield_probe_result'] = array(
            'type' => 'success',
            'msg' => (string) $string
        );
    }

    function error($string, $jump_url = null, $time = 2, $status = null)
    {
        $GLOBALS['__extfield_probe_result'] = array(
            'type' => 'error',
            'msg' => (string) $string
        );
        throw new \RuntimeException('PROBE_ERROR:' . $string);
    }

    function alert_back($info, $status = 0)
    {
        $GLOBALS['__extfield_probe_result'] = array(
            'type' => 'alert',
            'msg' => (string) $info
        );
        throw new \RuntimeException('PROBE_ALERT:' . $info);
    }
}
