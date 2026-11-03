<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers ConfigController::index() AI 配置保存前校验，失败时任何 ai_* 均不入库（#330）
 *
 * 子进程运行真实控制器，SQLite / pdo_sqlite 必跑，MySQL 在 config/database.php 可连时跑临时表。
 *
 * 运行：
 *   php tests/run.php --suite=integration --filter=AiConfigSaveValidation
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function ai_save_probe(array $scenario): array
{
    $file = tempnam(sys_get_temp_dir(), 'aisave');
    file_put_contents($file, json_encode($scenario, JSON_UNESCAPED_UNICODE));
    $php = PHP_BINARY ?: 'php';
    $cmd = $php . ' ' . escapeshellarg(TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'ai_config_save_probe.php') . ' ' . escapeshellarg($file);
    $out = (string) shell_exec($cmd);
    @unlink($file);
    $snapshot = null;
    $output = '';
    if (preg_match('/^SNAPSHOT=(.*)$/m', $out, $m)) {
        $snapshot = json_decode(trim($m[1]), true);
    }
    if (preg_match('/^OUTPUT=(.*)$/m', $out, $m)) {
        $output = (string) json_decode(trim($m[1]), true);
        $decoded = json_decode($output, true);
        if (is_array($decoded)) {
            $output .= json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }
    }
    return array('output' => $output, 'raw' => $out, 'snapshot' => $snapshot);
}

function ai_save_mysql_target()
{
    if (! extension_loaded('mysqli') || ! is_file(ROOT_PATH . 'config/database.php')) {
        return null;
    }
    $cfg = require ROOT_PATH . 'config/database.php';
    $c = isset($cfg['database']) ? $cfg['database'] : array();
    if (! isset($c['type']) || stripos((string) $c['type'], 'mysql') === false) {
        return null;
    }
    $host = isset($c['host']) ? $c['host'] : '127.0.0.1';
    $port = (int) (isset($c['port']) ? $c['port'] : 3306);
    try {
        $conn = @new mysqli($host, $c['user'], $c['passwd'], '', $port);
    } catch (Throwable $e) {
        return null;
    }
    if ($conn->connect_errno) {
        return null;
    }
    $db = 'pboot_ai_save_' . getmypid();
    if (! $conn->query('CREATE DATABASE `' . $db . '`')) {
        return null;
    }
    $conn->close();
    return array('host' => $host, 'user' => $c['user'], 'passwd' => $c['passwd'], 'port' => $port, 'dbname' => $db);
}

function ai_save_mysql_drop(array $target)
{
    try {
        $conn = @new mysqli($target['host'], $target['user'], $target['passwd'], '', (int) $target['port']);
        if (! $conn->connect_errno) {
            $conn->query('DROP DATABASE IF EXISTS `' . $target['dbname'] . '`');
            $conn->close();
        }
    } catch (Throwable $e) {
    }
}

return TestAssert::runSuite(function () {
    $drivers = array();
    if (extension_loaded('sqlite3')) {
        $drivers[] = array('sqlite', null);
    }
    if (extension_loaded('pdo_sqlite')) {
        $drivers[] = array('pdo_sqlite', null);
    }
    $mysql = ai_save_mysql_target();
    if ($mysql) {
        $drivers[] = array('mysqli', $mysql);
    } else {
        echo "SKIP: MySQL 不可用，仅验证 SQLite 驱动\n";
    }
    if (! $drivers) {
        echo "SKIP: no database driver\n";
        return;
    }

    $seed = array(
        'ai_enabled' => '0',
        'ai_provider' => 'openai',
        'ai_api_base' => 'https://gateway.example/v1',
        'ai_model' => 'gpt-4o-mini',
        'ai_api_key' => 'CIPHER-SAVED',
        'ai_max_tokens' => '2048',
        'ai_rate_limit' => '20',
        'smtp_server' => 'smtp.old.example',
    );
    $aiPost = function (array $over) {
        return array_merge(array(
            'formcheck' => 'x',
            'submit' => 'ai',
            'ai_enabled' => '1',
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-s****ved1',
            'ai_api_base' => 'https://gateway.example/v1',
            'ai_model' => 'gpt-4o-mini',
            'ai_max_tokens' => '4096',
            'ai_rate_limit' => '30',
        ), $over);
    };

    try {
        foreach ($drivers as $pair) {
            list($driver, $target) = $pair;
            $run = function (array $post, array $seedOver = array()) use ($driver, $target, $seed) {
                return ai_save_probe(array(
                    'driver' => $driver,
                    'mysql' => $target,
                    'seed' => array_merge($seed, $seedOver),
                    'post' => $post,
                ));
            };

            echo "=== [$driver] invalid AI input leaves every ai_* untouched ===\n";
            $invalid = array(
                'non-http scheme' => array('ai_api_base' => 'ftp://gateway.example/v1'),
                'missing host' => array('ai_api_base' => 'https:///v1'),
                'bad port' => array('ai_api_base' => 'https://gateway.example:70000/v1'),
                'openai without url' => array('ai_api_base' => ''),
                'openai without model' => array('ai_model' => ''),
                'unknown provider' => array('ai_provider' => 'nope'),
                'host change keeps masked key' => array('ai_api_base' => 'https://other.example/v1'),
                'port change keeps empty key' => array('ai_api_base' => 'https://gateway.example:8443/v1', 'ai_api_key' => ''),
                'too long url' => array('ai_api_base' => 'https://gateway.example/v1?' . str_repeat('a=1&', 50)),
            );
            $expected = $seed;
            ksort($expected);
            foreach ($invalid as $label => $over) {
                $rs = $run($aiPost($over));
                TestAssert::same($expected, $rs['snapshot'], "$label: snapshot unchanged");
                TestAssert::contains($rs['output'], 'AI 配置未保存', "$label: rejection message returned");
            }

            echo "=== [$driver] valid AI input is stored as typed, escaped once ===\n";
            $rs = $run($aiPost(array('ai_api_base' => 'https://GATEWAY.example:443/v1/?a=1&b=2')));
            TestAssert::same('https://GATEWAY.example:443/v1/?a=1&amp;b=2', $rs['snapshot']['ai_api_base'], 'raw URL stored, not auto-completed');
            TestAssert::same('CIPHER-SAVED', $rs['snapshot']['ai_api_key'], 'masked key keeps saved cipher');
            TestAssert::same('4096', $rs['snapshot']['ai_max_tokens'], 'other ai_* saved together');
            $rs2 = $run($aiPost(array('ai_api_base' => 'https://GATEWAY.example:443/v1/?a=1&b=2')), array('ai_api_base' => $rs['snapshot']['ai_api_base']));
            TestAssert::same($rs['snapshot']['ai_api_base'], $rs2['snapshot']['ai_api_base'], 'saving the echoed value again does not stack escaping');

            echo "=== [$driver] full new key may move to another host ===\n";
            $rs = $run($aiPost(array('ai_api_base' => 'https://other.example/v1', 'ai_api_key' => 'brand-new-key-123456')));
            if (extension_loaded('openssl')) {
                TestAssert::same('https://other.example/v1', $rs['snapshot']['ai_api_base'], 'new host saved');
                TestAssert::true($rs['snapshot']['ai_api_key'] !== 'CIPHER-SAVED' && $rs['snapshot']['ai_api_key'] !== '', 'new key encrypted and saved');
            } else {
                TestAssert::same($expected, $rs['snapshot'], 'without openssl a new key is refused before any write');
            }
            TestAssert::notContains((string) $rs['snapshot']['ai_api_key'], 'brand-new-key', 'key not stored in plain text');

            echo "=== [$driver] unreachable but well-formed URL is allowed ===\n";
            $rs = $run($aiPost(array('ai_api_base' => 'http://gateway.example:443/v1')));
            TestAssert::same('http://gateway.example:443/v1', $rs['snapshot']['ai_api_base'], 'same host and explicit same port saved');

            echo "=== [$driver] other tabs are not blocked by a broken saved AI config ===\n";
            $rs = $run(array('formcheck' => 'x', 'submit' => 'email', 'smtp_server' => 'smtp.new.example'), array('ai_api_base' => ''));
            TestAssert::same('smtp.new.example', $rs['snapshot']['smtp_server'], 'email tab saved');
            TestAssert::same('', $rs['snapshot']['ai_api_base'], 'broken AI config left as is');
        }
    } finally {
        if ($mysql) {
            ai_save_mysql_drop($mysql);
        }
        foreach (glob(ROOT_PATH . 'data/ai_save_probe_*.db*') ?: array() as $leftover) {
            @unlink($leftover);
        }
    }
});
