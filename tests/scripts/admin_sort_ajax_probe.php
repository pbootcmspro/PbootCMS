<?php

require dirname(__DIR__) . '/bootstrap.php';
require CORE_PATH . '/basic/Response.php';

$controller = isset($argv[1]) ? $argv[1] : '';
if (! in_array($controller, array('Content', 'ContentSort', 'Slide', 'Link'), true)) {
    exit(2);
}
ConfigStub::set(array('return_data_type' => 'html'));
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_GET = array();
$_POST = array('submit' => 'sorting', 'listall' => array('7', '8', '9'), 'sorting' => array('12', '', 'invalid'));
if (isset($argv[2]) && $argv[2] === 'empty') {
    $_POST['listall'] = array();
}

class AdminSortAjaxProbeDouble
{
    public static $calls = array();

    public static function write($content, $level = 'info')
    {}

    public function __call($method, $args)
    {
        self::$calls[] = $args;
        return true;
    }
}
class_alias('AdminSortAjaxProbeDouble', 'core\\basic\\Log');
register_shutdown_function(function () {
    echo "\nSORT_CALLS=" . json_encode(AdminSortAjaxProbeDouble::$calls);
});
require APP_PATH . '/admin/controller/content/' . $controller . 'Controller.php';
$class = new ReflectionClass('app\\admin\\controller\\content\\' . $controller . 'Controller');
$instance = $class->newInstanceWithoutConstructor();
$model = $class->getProperty('model');
if (PHP_VERSION_ID < 80100) {
    $model->setAccessible(true);
}
$model->setValue($instance, new AdminSortAjaxProbeDouble());
$instance->mod();
