<?php

declare(strict_types=1);

/**
 * 在线升级 update() 文件写入顺序探测（与 UpgradeController 逻辑对齐，供单元测试使用）
 */

/**
 * 是否与 UpgradeController::isVersionStatusFile 一致
 */
function upgrade_is_version_status_file($relPath): bool
{
    $norm = str_replace('\\', '/', (string) $relPath);
    return (bool) preg_match('#(^|/)apps/common/version\.php$#i', $norm);
}

/**
 * 按 update() 规则分区并依次 copy；$copyFn 返回 false 时中止且不再写 version 文件
 *
 * @param string[] $list 相对路径列表（不含 SQL）
 * @param callable $copyFn function (string $relPath): bool
 * @param string[] $order 实际 copy 成功的路径顺序（输出）
 * @return bool 是否全部成功
 */
function upgrade_apply_file_copies(array $list, callable $copyFn, array &$order): bool
{
    $order = array();
    $files = array();
    $versionFiles = array();

    foreach ($list as $value) {
        $value = (string) $value;
        if (stripos($value, '/script/') === 0 && preg_match('/\.sql$/i', $value)) {
            continue;
        }
        if (upgrade_is_version_status_file($value)) {
            $versionFiles[] = $value;
        } else {
            $files[] = $value;
        }
    }

    foreach ($files as $rel) {
        if (! $copyFn($rel)) {
            return false;
        }
        $order[] = $rel;
    }

    foreach ($versionFiles as $rel) {
        if (! $copyFn($rel)) {
            return false;
        }
        $order[] = $rel;
    }

    return true;
}
