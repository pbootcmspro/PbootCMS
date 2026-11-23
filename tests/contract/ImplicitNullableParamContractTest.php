<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers apps/、core/ 不得使用「类型 $param = null」隐式可空写法（PHP 8.4+ E_DEPRECATED）
 *
 * 调试模式错误掩码含 E_DEPRECATED，Basic::errorHandler 会把该级别走入 error() 并 exit()；
 * 菜单/角色/用户 Model 的 mod* 签名在编译期即触发弃用，导致后台三页无法打开。
 *
 * 运行：
 *   php tests/run.php --suite=contract --filter=ImplicitNullableParam
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * 去掉注释、字符串和内联 HTML，仅保留换行，使匹配只落在代码上且行号不变
 */
function implicit_nullable_strip_non_code(string $src): string
{
    $skip = array(T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML);
    $out = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token) && in_array($token[0], $skip, true)) {
            $out .= str_repeat("\n", substr_count($token[1], "\n"));
        } else {
            $out .= is_array($token) ? $token[1] : $token;
        }
    }
    return $out;
}

/**
 * @return array<int, string> 每项为「行号 匹配片段」
 */
function implicit_nullable_scan(string $src): array
{
    $pattern = '/[(,]\s*(\\\\?[A-Za-z_][\w\\\\]*\s+&?\s*(?:\.\.\.)?\$\w+\s*=\s*null)\b/';
    $code = implicit_nullable_strip_non_code($src);
    $found = array();
    if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[1] as $match) {
            $found[] = (substr_count(substr($code, 0, $match[1]), "\n") + 1) . ' ' . $match[0];
        }
    }
    return $found;
}

/**
 * @return array<int, string>
 */
function implicit_nullable_param_hits(): array
{
    $hits = array();
    $roots = array(
        'apps' => APP_PATH,
        'core' => CORE_PATH,
    );
    foreach ($roots as $label => $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || substr($file->getFilename(), -4) !== '.php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if ($src === false) {
                continue;
            }
            $rel = $label . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            foreach (implicit_nullable_scan($src) as $hit) {
                $hits[] = $rel . ':' . $hit;
            }
        }
    }
    return $hits;
}

/**
 * @return array{exit_code:int,output:string}
 */
function implicit_nullable_php_lint(string $file): array
{
    $php = PHP_BINARY ?: 'php';
    $cmd = $php . ' -d error_reporting=-1 -l ' . escapeshellarg($file);
    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open($cmd, $descriptors, $pipes);
    if ($proc === false || (! is_resource($proc) && ! is_object($proc))) {
        return array('exit_code' => -1, 'output' => '');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = (int) proc_close($proc);
    $out = (is_string($stdout) ? $stdout : '') . (is_string($stderr) ? $stderr : '');
    return array('exit_code' => $exit, 'output' => $out);
}

return TestAssert::runSuite(function () {
    echo "=== 扫描规则自检 ===\n";

    TestAssert::same(array('2 array $a = null'), implicit_nullable_scan("<?php\nfunction f(array \$a = null) {}\n"), 'detects array type');
    TestAssert::same(array('1 \\core\\basic\\Model $m = null'), implicit_nullable_scan("<?php function f(\$x, \\core\\basic\\Model \$m = null) {}"), 'detects lowercase namespaced class');
    TestAssert::same(array('1 Foo &$r = null'), implicit_nullable_scan("<?php function f(Foo &\$r = null) {}"), 'detects by-reference param');
    TestAssert::same(array('3 int $b = null'), implicit_nullable_scan("<?php\n/* f(Foo \$x = null)\n */ function f(int \$b = null) {}"), 'line number survives stripped comment');
    TestAssert::same(array(), implicit_nullable_scan("<?php\n// f(Foo \$x = null)\n/** @param Foo \$x = null */\n\$s = 'f(Foo \$x = null)';"), 'ignores comments and strings');
    TestAssert::same(array(), implicit_nullable_scan("<?php class A { public \$p = null; function f(?array \$a = null, \$b = null) { static \$c = null; } }"), 'ignores nullable, untyped, property and static');

    echo "=== apps/ core/ 无隐式可空类型参数 ===\n";

    $hits = implicit_nullable_param_hits();
    TestAssert::same(array(), $hits, 'no Type $param = null in apps/ or core/');

    echo "=== 菜单/角色/用户 mod* 签名 ===\n";

    $menu = file_get_contents(APP_PATH . '/admin/model/system/MenuModel.php');
    TestAssert::false($menu === false, 'MenuModel readable');
    TestAssert::contains($menu, 'function modMenu($mcode, $data, $actions = null)', 'modMenu keeps null default without array type');
    TestAssert::notContains($menu, 'array $actions = null', 'modMenu no array $actions = null');

    $role = file_get_contents(APP_PATH . '/admin/model/system/RoleModel.php');
    TestAssert::false($role === false, 'RoleModel readable');
    TestAssert::contains($role, 'function modRole($rcode, $data, $acodes = null, $levels = null)', 'modRole keeps null defaults without array type');
    TestAssert::notContains($role, 'array $acodes = null', 'modRole no array $acodes = null');
    TestAssert::notContains($role, 'array $levels = null', 'modRole no array $levels = null');

    $user = file_get_contents(APP_PATH . '/admin/model/system/UserModel.php');
    TestAssert::false($user === false, 'UserModel readable');
    TestAssert::contains($user, 'function modUser($ucode, $data, $roles = null)', 'modUser keeps null default without array type');
    TestAssert::notContains($user, 'array $roles = null', 'modUser no array $roles = null');

    echo "=== PHP 8.4+ php -l 三文件无 Deprecated ===\n";

    if (PHP_VERSION_ID < 80400) {
        echo "SKIP: PHP < 8.4 (current " . PHP_VERSION . ")\n";
    } else {
        $files = array(
            APP_PATH . '/admin/model/system/MenuModel.php',
            APP_PATH . '/admin/model/system/RoleModel.php',
            APP_PATH . '/admin/model/system/UserModel.php',
        );
        foreach ($files as $file) {
            $r = implicit_nullable_php_lint($file);
            TestAssert::same(0, $r['exit_code'], basename($file) . ' php -l exit 0');
            TestAssert::false(
                stripos($r['output'], 'Deprecated') !== false,
                basename($file) . ' php -l has no Deprecated'
            );
        }
    }
});
