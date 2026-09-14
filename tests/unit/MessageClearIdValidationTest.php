<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers parse_strict_id_list()
 * @covers core/log/LogFile.php Builder compatibility
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== parse_strict_id_list() rejects invalid input ===\n";

    TestAssert::false(parse_strict_id_list(null), 'null rejected');
    TestAssert::false(parse_strict_id_list(''), 'empty rejected');
    TestAssert::false(parse_strict_id_list('abc'), 'alpha rejected');
    TestAssert::false(parse_strict_id_list('1,abc'), 'mixed alpha rejected');
    TestAssert::false(parse_strict_id_list('1,'), 'trailing comma rejected');
    TestAssert::false(parse_strict_id_list(',1'), 'leading comma rejected');
    TestAssert::false(parse_strict_id_list('0'), 'zero alone rejected');
    TestAssert::false(parse_strict_id_list('0,1'), 'zero prefix must not delete valid id');
    TestAssert::false(parse_strict_id_list('01,2'), 'leading zero rejected');
    TestAssert::false(parse_strict_id_list('1,02'), 'leading zero in second token rejected');

    $overflow = (string) (PHP_INT_MAX + 1);
    TestAssert::false(parse_strict_id_list($overflow), 'overflow rejected');
    TestAssert::false(parse_strict_id_list('9223372036854775808'), '64-bit overflow string rejected');

    echo "=== parse_strict_id_list() accepts valid input ===\n";

    TestAssert::same(array(1), parse_strict_id_list('1'), 'single id');
    TestAssert::same(array(1, 2, 3), parse_strict_id_list('1,2,3'), 'comma separated ids');
    TestAssert::same(array(1, 2), parse_strict_id_list('1,2,1'), 'duplicates deduplicated');

    echo "=== LogFile::write() matches Builder interface ===\n";

    require CORE_PATH . '/function/file.php';
    require CORE_PATH . '/log/Builder.php';
    require CORE_PATH . '/log/LogFile.php';

    $ref = new ReflectionMethod(\core\log\LogFile::class, 'write');
    TestAssert::true($ref->isPublic(), 'LogFile::write is public');

    $params = $ref->getParameters();
    TestAssert::same(3, count($params), 'LogFile::write has 3 parameters');
    TestAssert::same('username', $params[2]->getName(), 'third param is username');
    TestAssert::true($params[2]->isOptional(), 'username is optional');

    TestAssert::true(is_subclass_of(\core\log\LogFile::class, \core\log\Builder::class)
        || (new ReflectionClass(\core\log\LogFile::class))->implementsInterface(\core\log\Builder::class),
        'LogFile implements Builder');

    echo "=== source contract: LogFile archives under data/log/system ===\n";

    $src = file_get_contents(CORE_PATH . '/log/LogFile.php');
    TestAssert::false($src === false, 'LogFile.php readable');
    TestAssert::notContains($src, 'check_file(', 'LogFile must not call check_file()');
    TestAssert::notContains($src, 'create_file(', 'LogFile must not call create_file()');
    TestAssert::contains($src, 'check_dir(', 'LogFile ensures system log directory exists');
    TestAssert::contains($src, 'DATA_DIR', 'LogFile writes under DATA_DIR');
    TestAssert::contains($src, "date('Y/Ym')", 'LogFile archives under year/Ym directories');
    TestAssert::contains($src, '/log/system/', 'LogFile writes under data/log/system');
    TestAssert::notContains($src, "ROOT_PATH . '/log/'", 'LogFile must not write to /log/');
    TestAssert::true(
        strpos($src, "fopen(\$path, 'ab')") !== false || strpos($src, 'fopen($path, "ab")') !== false,
        'LogFile appends with fopen ab (create without truncate)'
    );
});
