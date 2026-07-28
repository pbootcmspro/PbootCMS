<?php

namespace app\admin\controller\system;

use app\admin\model\content\CompanyModel;
use app\admin\model\content\ContentModel;
use app\admin\model\content\ContentSortModel;
use app\admin\model\content\LinkModel;
use app\admin\model\content\SiteModel;
use app\admin\model\content\SlideModel;
use app\admin\model\content\LabelModel;
use app\home\model\MemberModel;
use core\basic\Controller;

class ImageExtController extends Controller
{
    private $companyModel;
    private $contentSortModel;
    private $contentModel;
    private $linkModel;
    private $memberModel;
    private $siteModel;
    private $slideModel;
	private $LabelModel;

    public function __construct()
    {
        $this->companyModel = new CompanyModel();
        $this->contentSortModel = new ContentSortModel();
        $this->contentModel = new ContentModel();
        $this->linkModel = new LinkModel();
        $this->memberModel = new MemberModel();
        $this->siteModel = new SiteModel();
        $this->slideModel = new SlideModel();
		$this->LabelModel = new LabelModel();
    }

    public function index()
    {
        $this->display('system/extimage.html');
    }

    public function checkDataFile()
    {
        $count = 30;
        $page = get('page') ? get('page') : 1;
        $start = ($page - 1) * $count;

        // 服务端重新计算冗余文件集合：规范键 => 磁盘真实路径
        $redundant = $this->buildRedundantMap();
        $keys = array_keys($redundant);
        $pageKeys = array_slice($keys, $start, $count);

        $http = is_https() ? 'https://' : 'http://';
        $data = [];
        foreach ($pageKeys as $key) {
            $fsPath = $redundant[$key];
            $data[] = [
                // 返回站点相对规范键作为选择标识，不再暴露/依赖客户端物理绝对路径
                'real_path' => $key,
                'static_path' => $http . $_SERVER['SERVER_NAME'] . SITE_DIR . $key,
                'update_time' => is_file($fsPath) ? date('Y-m-d H:i:s', filemtime($fsPath)) : ''
            ];
        }
        $jsonData = ['code' => 0, 'msg' => '', 'count' => count($redundant), 'data' => $data];
        return json_encode($jsonData);
    }

    public function do_ext()
    {
        $type = post('type');
        $list = post('list');

        $backupBase = DOC_PATH . STATIC_DIR . '/backup/ImageExt';
        check_dir($backupBase, true);

        $uploadReal = $this->getUploadReal();
        if ($uploadReal === false) {
            json(0, '上传目录不存在！');
        }

        // 不信任客户端传入的路径，服务端重新计算冗余文件集合
        $redundant = $this->buildRedundantMap();

        $moved = 0;
        $failed = 0;

        switch ($type) {
            case 0:
                if (!is_array($list)) {
                    json(0, '参数错误！');
                }
                $attempted = 0;
                foreach ($list as $value) {
                    $key = isset($value['real_path']) ? $this->normalizeUploadKey($value['real_path']) : '';
                    if ($key === '' || !isset($redundant[$key])) {
                        continue;
                    }
                    $attempted++;
                    if ($this->moveToBackup($redundant[$key], $key, $uploadReal, $backupBase)) {
                        $moved++;
                    } else {
                        $failed++;
                    }
                }
                if ($attempted === 0 && count($list) > 0) {
                    json(0, '所选文件均无法清理！');
                }
                break;
            case 1:
                foreach ($redundant as $key => $fsPath) {
                    if ($this->moveToBackup($fsPath, $key, $uploadReal, $backupBase)) {
                        $moved++;
                    } else {
                        $failed++;
                    }
                }
                break;
            default:
                json(0, '参数错误！');
        }

        if ($failed > 0) {
            json(0, "成功迁移 {$moved} 个，失败 {$failed} 个");
        }
        if ($moved > 0) {
            json(1, "成功迁移 {$moved} 个文件");
        }
        json(1, '');
    }

    /**
     * 上传目录 canonical 路径（realpath 解析后），与边界校验、键裁剪共用同一基准。
     */
    private function getUploadReal()
    {
        $real = realpath(DOC_PATH . STATIC_DIR . '/upload');
        if ($real === false) {
            return false;
        }
        return str_replace('\\', '/', $real);
    }

    /**
     * 计算冗余文件集合：磁盘存在但数据库未引用的上传文件。
     * 返回 [规范键 => 磁盘路径]，规范键形如 /static/upload/xxx.jpg，
     * 与后台数据库类型（MySQL/SQLite）无关，且对是否携带子目录前缀做了兼容。
     */
    private function buildRedundantMap()
    {
        $uploadReal = $this->getUploadReal();
        if ($uploadReal === false) {
            return array();
        }

        $staticSeg = substr(STATIC_DIR, strlen(SITE_DIR)); // 站点相对静态目录，通常为 /static
        $fsBase = rtrim($uploadReal, '/');

        // 磁盘上传文件：规范键 => 原始磁盘路径
        $fileList = get_dir(DOC_PATH . STATIC_DIR . '/upload');
        $fileMap = [];
        array_walk_recursive($fileList, function ($fsPath) use (&$fileMap, $fsBase, $staticSeg) {
            if (!empty($fsPath)) {
                $resolved = realpath($fsPath);
                $lookupPath = $resolved !== false
                    ? str_replace('\\', '/', $resolved)
                    : str_replace('\\', '/', $fsPath);
                $key = $this->fsToKey($lookupPath, $fsBase, $staticSeg);
                if ($key !== '') {
                    $fileMap[$key] = $fsPath;
                }
            }
        });

        // 数据库中被引用的图片规范键集合
        $company = $this->companyModel->getImage();
        $contentSort = $this->contentSortModel->getImage();
        $content = $this->contentModel->getImage();
        $link = $this->linkModel->getImage();
        $member = $this->memberModel->getImage();
        $site = $this->siteModel->getImage();
        $slide = $this->slideModel->getImage();
        $label = $this->LabelModel->getImage();
        $resArr = array_merge_recursive($company, $contentSort, $content, $link, $member, $site, $slide, $label);

        $usedKeys = [];
        array_walk_recursive($resArr, function ($val) use (&$usedKeys) {
            if (!empty($val)) {
                $key = $this->normalizeUploadKey($val);
                if ($key !== '') {
                    $usedKeys[$key] = true;
                }
            }
        });

        // 差集：磁盘存在但未被引用
        $redundant = [];
        foreach ($fileMap as $key => $fsPath) {
            if (!isset($usedKeys[$key])) {
                $redundant[$key] = $fsPath;
            }
        }
        ksort($redundant); // 稳定排序，保证分页及不同数据库结果一致
        return $redundant;
    }

    /**
     * 将磁盘上传文件路径转换为站点相对规范键（/static/upload/...）。
     * 优先按已知上传目录基准精确裁剪，避免部署路径中含相同片段导致误判。
     */
    private function fsToKey($fsPath, $fsBase, $staticSeg)
    {
        $fsPath = str_replace('\\', '/', $fsPath);
        if (strpos($fsPath, $fsBase . '/') === 0) {
            return $staticSeg . '/upload' . substr($fsPath, strlen($fsBase));
        }
        return $this->normalizeUploadKey($fsPath);
    }

    /**
     * 将任意来源的图片路径（URL、含子目录前缀、绝对/相对路径）规范化为
     * 以 /static/upload/ 开头的站点相对键，无法识别时返回空字符串。
     */
    private function normalizeUploadKey($path)
    {
        if (!is_string($path) || $path === '') {
            return '';
        }
        $path = str_replace('\\', '/', $path);
        // 去除协议与主机部分，如 http://host/...
        $path = preg_replace('#^[a-zA-Z][a-zA-Z0-9+.\-]*://[^/]+#', '', $path);
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $staticSeg = substr(STATIC_DIR, strlen(SITE_DIR)); // 通常为 /static
        $marker = $staticSeg . '/upload/';
        $pos = strpos($path, $marker);
        if ($pos === false) {
            return '';
        }
        $key = $this->canonicalizePath(substr($path, $pos));
        if (strpos($key, $marker) !== 0) {
            return '';
        }
        return $key;
    }

    /**
     * 折叠路径中的 . / .. 段，兼容历史数据中携带冗余路径片段的写法。
     */
    private function canonicalizePath($path)
    {
        $parts = array();
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                if ($parts) {
                    array_pop($parts);
                }
                continue;
            }
            $parts[] = $seg;
        }
        return '/' . implode('/', $parts);
    }

    /**
     * 对源文件执行 realpath 解析与上传目录边界校验，通过后返回真实路径，
     * 可拦截 ../、符号链接、伪造绝对路径及上传目录外的文件。
     */
    private function resolveInsideUpload($path, $uploadReal)
    {
        if (!$path) {
            return false;
        }
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            return false;
        }
        $real = str_replace('\\', '/', $real);
        $base = rtrim(str_replace('\\', '/', $uploadReal), '/');
        if (strpos($real, $base . '/') !== 0) {
            return false;
        }
        return $real;
    }

    /**
     * 将上传目录内的文件安全迁移到备份目录，目标路径基于服务端规范键重建。
     */
    private function moveToBackup($fsPath, $key, $uploadReal, $backupBase)
    {
        $real = $this->resolveInsideUpload($fsPath, $uploadReal);
        if ($real === false) {
            return false;
        }
        $target = $backupBase . $key; // key 形如 /static/upload/xxx.jpg
        check_dir(dirname($target), true);
        return rename($real, $target);
    }
}
