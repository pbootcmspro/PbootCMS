<?php
/**
 * @copyright (C)2016-2099
 * @author pbootcms
 * @email support@pbootcms.com
 * 远程 HTTP 抓取辅助（UEditor 远程图片等）：SSRF 校验、DNS 固定、有界读取。
 */

/**
 * 最近一次 remote_fetch_* 失败原因（供 Uploader 映射错误文案）
 *
 * @return string
 */
function remote_fetch_last_error()
{
    return isset($GLOBALS['_remote_fetch_last_error']) ? (string) $GLOBALS['_remote_fetch_last_error'] : '';
}

/**
 * @param string $code
 */
function remote_fetch_set_error($code)
{
    $GLOBALS['_remote_fetch_last_error'] = (string) $code;
}

/**
 * @param string $host
 * @return string
 */
function remote_fetch_normalize_host($host)
{
    $host = strtolower(trim((string) $host));
    if ($host !== '' && substr($host, -1) === '.') {
        $host = substr($host, 0, -1);
    }
    if ($host !== '' && $host[0] === '[' && substr($host, -1) === ']') {
        $host = substr($host, 1, -1);
    }
    return $host;
}

/**
 * 是否为禁止访问的 IP（私网/保留/回环/链路本地/元数据/ULA 等）
 *
 * @param string $ip
 * @return bool true 表示应拒绝
 */
function remote_fetch_is_blocked_ip($ip)
{
    if (! is_string($ip) || $ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
        return true;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }
        // 127.0.0.0/8
        if (($long & 0xFF000000) === 0x7F000000) {
            return true;
        }
        // 169.254.0.0/16（含 169.254.169.254）
        if (($long & 0xFFFF0000) === 0xA9FE0000) {
            return true;
        }
        // 100.64.0.0/10 CGNAT 共享地址（RFC 6598）
        if (($long & 0xFFC00000) === 0x64400000) {
            return true;
        }
        return false;
    }

    $ip = strtolower($ip);
    if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1') {
        return true;
    }
    if (strpos($ip, 'fe80:') === 0) {
        return true;
    }
    if (preg_match('/^(fc|fd)[0-9a-f]{2}:/', $ip)) {
        return true;
    }
    if (strpos($ip, '::ffff:') === 0) {
        $mapped = substr($ip, 7);
        if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return remote_fetch_is_blocked_ip($mapped);
        }
    }
    return false;
}

/**
 * 解析主机全部 A/AAAA，任一 blocked 或无法解析则 false
 *
 * @param string $host
 * @return array|false
 */
function remote_fetch_resolve_host_ips($host)
{
    $host = remote_fetch_normalize_host($host);
    if ($host === '') {
        return false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (remote_fetch_is_blocked_ip($host)) {
            return false;
        }
        return array($host);
    }

    if (in_array($host, array('localhost', 'localhost.localdomain'), true)) {
        return false;
    }

    $ips = array();

    $aRecords = @gethostbynamel($host);
    if (is_array($aRecords)) {
        foreach ($aRecords as $ip) {
            if (remote_fetch_is_blocked_ip($ip)) {
                return false;
            }
            $ips[] = $ip;
        }
    }

    if (function_exists('dns_get_record')) {
        $aaaaRecords = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $rec) {
                if (! isset($rec['ipv6'])) {
                    continue;
                }
                $ip = $rec['ipv6'];
                if (remote_fetch_is_blocked_ip($ip)) {
                    return false;
                }
                $ips[] = $ip;
            }
        }
    }

    if ($ips === array()) {
        $ip = gethostbyname($host);
        if ($ip === $host || $ip === '') {
            return false;
        }
        if (remote_fetch_is_blocked_ip($ip)) {
            return false;
        }
        return array($ip);
    }

    return array_values(array_unique($ips));
}

/**
 * 校验远程图片 URL 并返回 pinned 连接信息
 *
 * @param string $url
 * @param array $allowFiles 如 array('.png', '.jpg')
 * @return array|false
 */
function remote_fetch_validate_image_url($url, array $allowFiles = array())
{
    remote_fetch_set_error('');

    if (! is_string($url) || $url === '') {
        remote_fetch_set_error('invalid_url');
        return false;
    }

    $url = str_replace('&amp;', '&', $url);
    $parts = parse_url($url);
    if ($parts === false) {
        remote_fetch_set_error('invalid_url');
        return false;
    }

    if (! empty($parts['scheme'])) {
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            remote_fetch_set_error('invalid_scheme');
            return false;
        }
    }

    if (empty($parts['scheme']) || empty($parts['host'])) {
        remote_fetch_set_error('invalid_url');
        return false;
    }

    $scheme = strtolower($parts['scheme']);

    if (isset($parts['user']) || isset($parts['pass'])) {
        remote_fetch_set_error('invalid_url');
        return false;
    }

    $host = remote_fetch_normalize_host($parts['host']);
    $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
    if ($port < 1 || $port > 65535) {
        remote_fetch_set_error('invalid_url');
        return false;
    }

    $ips = remote_fetch_resolve_host_ips($host);
    if ($ips === false || $ips === array()) {
        remote_fetch_set_error('invalid_ip');
        return false;
    }

    $path = isset($parts['path']) ? $parts['path'] : '';
    $dot = strrchr($path, '.');
    if ($dot === false) {
        remote_fetch_set_error('invalid_url');
        return false;
    }
    $ext = strtolower($dot);
    if ($allowFiles !== array() && ! in_array($ext, $allowFiles, true)) {
        remote_fetch_set_error('invalid_type');
        return false;
    }

    return array(
        'url' => $url,
        'scheme' => $scheme,
        'host' => $host,
        'port' => $port,
        'ip' => $ips[0],
        'ext' => $ext,
    );
}

/**
 * @param string $contentType
 * @param string $ext 如 .png
 * @return bool
 */
function remote_fetch_content_type_matches($contentType, $ext)
{
    $contentType = strtolower(trim((string) $contentType));
    if ($contentType === '' || strpos($contentType, 'image/') !== 0) {
        return false;
    }

    $ext = strtolower(ltrim((string) $ext, '.'));
    $map = array(
        'jpg' => array('image/jpeg', 'image/jpg', 'image/pjpeg'),
        'jpeg' => array('image/jpeg', 'image/jpg', 'image/pjpeg'),
        'png' => array('image/png', 'image/x-png'),
        'gif' => array('image/gif'),
        'bmp' => array('image/bmp', 'image/x-ms-bmp'),
        'webp' => array('image/webp'),
        'svg' => array('image/svg+xml'),
        'svgz' => array('image/svg+xml'),
        'avif' => array('image/avif'),
    );

    if (! isset($map[$ext])) {
        return false;
    }

    return in_array($contentType, $map[$ext], true);
}

/**
 * 写盘前确认响应体为允许的图片格式
 *
 * @param string $body
 * @param string $ext 如 .png
 * @param int $maxBytes SVGZ 解压上限（与下载 maxSize 一致）
 * @return bool
 */
function remote_fetch_verify_image_body($body, $ext, $maxBytes = 2097152)
{
    if (! is_string($body) || $body === '') {
        return false;
    }

    $ext = strtolower(ltrim((string) $ext, '.'));
    if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'), true)) {
        $info = @getimagesizefromstring($body);
        return is_array($info) && isset($info[0], $info[1]) && $info[0] > 0 && $info[1] > 0;
    }

    if ($ext === 'svg') {
        $trim = ltrim($body);
        return stripos($trim, '<svg') !== false || (stripos($trim, '<?xml') === 0 && stripos($body, '<svg') !== false);
    }

    if ($ext === 'svgz') {
        if (strlen($body) < 2 || substr($body, 0, 2) !== "\x1f\x8b") {
            return false;
        }
        $maxBytes = (int) $maxBytes;
        if ($maxBytes < 1) {
            return false;
        }
        if (function_exists('svgz_decompress_limited_string')) {
            list($decoded, $ok) = svgz_decompress_limited_string($body, $maxBytes);
            if (! $ok || ! is_string($decoded) || $decoded === '') {
                return false;
            }
            return remote_fetch_verify_image_body($decoded, '.svg', $maxBytes);
        }
        return false;
    }

    if ($ext === 'avif') {
        $info = @getimagesizefromstring($body);
        if (is_array($info) && isset($info[0], $info[1])) {
            return true;
        }
        if (strlen($body) < 12) {
            return false;
        }
        if (substr($body, 4, 4) !== 'ftyp') {
            return false;
        }
        $brand = substr($body, 8, 12);
        return strpos($brand, 'avif') !== false || strpos($brand, 'avis') !== false;
    }

    return false;
}

/**
 * 通过 cURL 抓取，CURLOPT_RESOLVE 固定已校验 IP
 *
 * @param string $url
 * @param string $host
 * @param int $port
 * @param string $pinnedIp
 * @param int $maxBytes
 * @param int $connectTimeout
 * @param int $timeout
 * @return array|false
 */
function remote_fetch_http_get_pinned($url, $host, $port, $pinnedIp, $maxBytes, $connectTimeout = 3, $timeout = 8)
{
    if (! function_exists('curl_init')) {
        remote_fetch_set_error('curl_missing');
        return false;
    }

    $maxBytes = (int) $maxBytes;
    if ($maxBytes < 1) {
        remote_fetch_set_error('invalid_url');
        return false;
    }

    $headers = '';
    $body = '';
    $truncated = false;

    $headerFn = function ($ch, $line) use (&$headers) {
        $headers .= $line;
        return strlen($line);
    };

    $writeFn = function ($ch, $chunk) use (&$body, &$truncated, $maxBytes) {
        $remain = $maxBytes - strlen($body);
        if ($remain <= 0) {
            $truncated = true;
            return 0;
        }
        $len = strlen($chunk);
        if ($len > $remain) {
            $body .= substr($chunk, 0, $remain);
            $truncated = true;
            return $remain;
        }
        $body .= $chunk;
        return $len;
    };

    $resolveHost = remote_fetch_normalize_host($host);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RESOLVE, array($resolveHost . ':' . (int) $port . ':' . $pinnedIp));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $connectTimeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeout);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, $headerFn);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, $writeFn);

    if (defined('CURLPROTO_HTTP') && defined('CURLOPT_PROTOCOLS')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }
    if (defined('CURLOPT_REDIR_PROTOCOLS')) {
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, 0);
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (is_string($scheme) && strtolower($scheme) === 'https') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CAINFO, CORE_PATH . '/cacert.pem');
    }

    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    if (function_exists('curl_safe_close')) {
        curl_safe_close($ch);
    } else {
        curl_close($ch);
    }

    if ($truncated) {
        remote_fetch_set_error('size_exceed');
        return false;
    }

    if ($curlErr !== '' && $body === '') {
        remote_fetch_set_error('dead_link');
        return false;
    }

    $contentType = '';
    if (preg_match('/^Content-Type:\s*([^\r\n;]+)/im', $headers, $m)) {
        $contentType = trim($m[1]);
    }

    return array(
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'body' => $body,
    );
}

/**
 * 校验 URL 并抓取远程图片（单次 cURL，DNS 已固定）
 *
 * @param string $url
 * @param array $allowFiles
 * @param int $maxBytes
 * @return array|false 成功时含 body、ext、size
 */
function remote_fetch_image($url, array $allowFiles, $maxBytes)
{
    $validated = remote_fetch_validate_image_url($url, $allowFiles);
    if ($validated === false) {
        return false;
    }

    $response = remote_fetch_http_get_pinned(
        $validated['url'],
        $validated['host'],
        $validated['port'],
        $validated['ip'],
        $maxBytes
    );
    if ($response === false) {
        return false;
    }

    if ($response['http_code'] !== 200) {
        remote_fetch_set_error('dead_link');
        return false;
    }

    if (! remote_fetch_content_type_matches($response['content_type'], $validated['ext'])) {
        remote_fetch_set_error('content_type');
        return false;
    }

    if (! remote_fetch_verify_image_body($response['body'], $validated['ext'], $maxBytes)) {
        remote_fetch_set_error('not_image');
        return false;
    }

    remote_fetch_set_error('');
    return array(
        'body' => $response['body'],
        'ext' => $validated['ext'],
        'size' => strlen($response['body']),
    );
}
