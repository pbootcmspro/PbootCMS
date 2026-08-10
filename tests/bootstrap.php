<?php

declare(strict_types=1);

define('TEST_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(TEST_ROOT) . DIRECTORY_SEPARATOR);
define('APP_PATH', ROOT_PATH . 'apps');
define('CORE_PATH', ROOT_PATH . 'core');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/basic/Controller.php';
require APP_PATH . '/home/controller/ParserController.php';

require TEST_ROOT . 'support/Assert.php';
require TEST_ROOT . 'support/ParserControllerHarness.php';
require TEST_ROOT . 'support/ConfigStub.php';
require TEST_ROOT . 'support/IframeSanitizeHarness.php';
require TEST_ROOT . 'fixtures/security/PbootIfBypassPayloads.php';
require TEST_ROOT . 'fixtures/security/IframeSanitizePayloads.php';
require TEST_ROOT . 'fixtures/security/AreaInputPayloads.php';
require TEST_ROOT . 'fixtures/security/TitleDescPayloads.php';
require TEST_ROOT . 'fixtures/security/StatisticalSanitizePayloads.php';
