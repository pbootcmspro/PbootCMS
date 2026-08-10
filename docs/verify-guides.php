<?php

/**
 * 手册文件校验脚本。
 *
 * manifest.json 中每篇手册的 hash 字段是该文件的内容指纹，官网同步手册时
 * 用它核对线上内容与仓库是否一致，因此改了手册就必须同步改 hash。
 *
 * hash 的计算方法（全仓库唯一约定，不要改）：
 *
 *     'sha256:' . hash_file('sha256', 'docs/guides/<文件名>')
 *
 * 即对文件的原始字节直接做 SHA-256，再加 'sha256:' 前缀。三条硬性要求：
 *
 *   1. 以仓库中提交的原始字节为准，换行必须是 LF。不要在 CRLF 环境（Windows
 *      记事本、部分编辑器的自动换行转换）下计算——CRLF 会得到一个完全不同、
 *      看起来却同样合法的哈希值，是这里最容易踩的坑。
 *   2. 不做任何归一化：不 trim、不去尾部换行、不替换换行符、不重排缩进。
 *   3. 手册内容有任何改动都要重新算 hash 并写回 manifest.json。
 *
 * 单篇重新生成：
 *
 *     php -r 'echo "sha256:".hash_file("sha256","docs/guides/18-228-slides.html"),"\n";'
 *
 * 本脚本校验不通过时会直接打印实际值，可从输出里复制，无需自己拼命令。
 */

$root = __DIR__ . '/guides';
$manifestPath = $root . '/manifest.json';

if (in_array('-h', $argv, true) || in_array('--help', $argv, true)) {
    echo "用法: php docs/verify-guides.php\n";
    echo "检查 docs/guides/ 下的手册文件：整份被转义、BOM、标签闭合、不安全内容、manifest hash 一致性。\n";
    echo "hash 为对文件原始字节（LF 换行，不做任何归一化）做 SHA-256，算法与坑点见本文件头部注释。\n";
    echo "空文件计为待填充，不算失败。退出码 0 表示无失败项。\n";
    exit(0);
}

if (! is_file($manifestPath)) {
    fwrite(STDERR, "找不到 manifest: $manifestPath\n");
    exit(1);
}
$manifest = json_decode(file_get_contents($manifestPath), true);
if (! $manifest || ! isset($manifest['pages'])) {
    fwrite(STDERR, "manifest 解析失败\n");
    exit(1);
}

function guides_real_tags($h)
{
    preg_match_all('/<([a-zA-Z][a-zA-Z0-9]*)/', $h, $m);
    return $m[1];
}

function guides_unbalanced_tags($h)
{
    $out = array();
    foreach (array('div', 'p', 'code', 'pre', 'h4', 'table', 'tbody', 'tr', 'td', 'span') as $t) {
        $open = preg_match_all('/<' . $t . '(?=[\s>])/i', $h);
        $close = preg_match_all('/<\/' . $t . '\s*>/i', $h);
        if ($open !== $close) {
            $out[] = '<' . $t . '> 开闭不平衡（' . $open . '/' . $close . '）';
        }
    }
    return $out;
}

function guides_content_hash($raw)
{
    return 'sha256:' . hash('sha256', $raw);
}

$rows = array();
$hashDetails = array();
$fail = 0;
$pending = 0;

foreach ($manifest['pages'] as $page) {
    $name = $page['file'];
    $file = $root . '/' . $name;

    if (! is_file($file)) {
        $rows[] = array($name, '-', 'FAIL', '文件缺失');
        $fail ++;
        continue;
    }
    $raw = file_get_contents($file);
    if ($raw === '') {
        $rows[] = array($name, '-', 'PENDING', '待填充');
        $pending ++;
        continue;
    }

    $problems = array();
    $tags = guides_real_tags($raw);
    $tagCount = count($tags);

    if ($tagCount === 0 && strpos($raw, '&lt;') !== false) {
        $problems[] = '整份被转义，需反转义一次';
    }
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $problems[] = '含 BOM';
    }
    if (strpos($raw, '&nbsp;') !== false || strpos($raw, "\xC2\xA0") !== false) {
        $problems[] = '含 NBSP 空白';
    }
    if (strpos($raw, '&lt;') !== false) {
        $problems[] = '含单层 &lt;，经 PbootCMS 解码后会成为真实标签';
    }
    if (strpos($raw, "\n") === false) {
        $problems[] = '内容未格式化为多行';
    }
    if (preg_match('/<script\b/i', $raw)) {
        $problems[] = '含 <script>';
    }
    preg_match_all('/<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/s', $raw, $tagTokens);
    foreach ($tagTokens[0] as $tagToken) {
        if (preg_match('/\son[a-z]+\s*=\s*["\']/i', $tagToken)) {
            $problems[] = '含真实 on* 事件属性';
            break;
        }
    }
    if (stripos($raw, 'javascript:') !== false) {
        $problems[] = '含 javascript: URL';
    }
    if (preg_match('/<(html|head|body)\b/i', $raw)) {
        $problems[] = '含文档级包裹标签';
    }
    $problems = array_merge($problems, guides_unbalanced_tags($raw));

    $actualHash = guides_content_hash($raw);
    if (! isset($page['hash']) || ! is_string($page['hash']) || $page['hash'] === '') {
        $problems[] = 'manifest 未记录 hash';
        $hashDetails[] = array($name, '（缺失）', $actualHash);
    } elseif ($page['hash'] !== $actualHash) {
        $problems[] = 'manifest hash 不匹配';
        $hashDetails[] = array($name, $page['hash'], $actualHash);
    }

    $divCount = 0;
    foreach ($tags as $t) {
        if (strtolower($t) === 'div') {
            $divCount ++;
        }
    }
    $callout = substr_count($raw, 'bd-callout') + substr_count($raw, 'alert alert-');

    if ($problems) {
        $fail ++;
    }
    $rows[] = array(
        $name,
        sprintf('%dB tag=%d div=%d cal=%d', strlen($raw), $tagCount, $divCount, $callout),
        $problems ? 'FAIL' : 'OK',
        $problems ? implode('；', $problems) : '',
    );
}

printf("%-34s %-30s %-8s %s\n", '文件', '结构', '结果', '说明');
echo str_repeat('-', 100), "\n";
foreach ($rows as $r) {
    printf("%-34s %-30s %-8s %s\n", $r[0], $r[1], $r[2], $r[3]);
}
echo str_repeat('-', 100), "\n";
printf("合计 %d 篇：通过 %d，失败 %d，待填充 %d\n", count($rows), count($rows) - $fail - $pending, $fail, $pending);

if ($hashDetails) {
    echo "\n";
    echo "manifest hash 不一致明细（算法与坑点见本文件头部注释）：\n";
    foreach ($hashDetails as $d) {
        printf("  %s\n", $d[0]);
        printf("    manifest: %s\n", $d[1]);
        printf("    实际:     %s\n", $d[2]);
    }
    echo "\n确认手册内容无误后，把 manifest.json 中对应的 hash 改成上面的「实际」值。\n";
    echo "若「实际」值与你本地算出来的不同，先检查你的文件是不是被转成了 CRLF 换行。\n";
}

exit($fail > 0 ? 1 : 0);
