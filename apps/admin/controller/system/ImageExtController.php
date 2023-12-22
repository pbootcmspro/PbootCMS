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
        $dataArr = [];
        $company = $this->companyModel->getImage();
        $contentSort = $this->contentSortModel->getImage();
        $content = $this->contentModel->getImage();
        $link = $this->linkModel->getImage();
        $member = $this->memberModel->getImage();
        $site = $this->siteModel->getImage();
        $slide = $this->slideModel->getImage();
		$label = $this->LabelModel->getImage();
        $resArr = array_merge_recursive($company, $contentSort, $content, $link, $member, $site, $slide, $label);
        array_walk_recursive($resArr, function ($key1) use (&$dataArr) {
            if (!empty($key1)) {
                $dataArr[] = DOC_PATH . SITE_DIR .$key1;
            }
            return $dataArr;
        });
        $dataArr = array_unique($dataArr);
        //获取上传的文件
        $Filepath = DOC_PATH . STATIC_DIR . '/upload';
        $fileList = get_dir($Filepath);
        $fileArr = [];
        array_walk_recursive($fileList, function ($key) use (&$fileArr) {
            $fileArr[] = $key;
            return $fileArr;
        });

        //对比文件
        $difference = array_diff($fileArr, $dataArr);
        $pageList = array_slice($difference, $start, $count);
        $http = is_https() ? 'https://' : 'http://';
        foreach ($pageList as &$value) {
            $value = [
                'real_path' => $value,
                'static_path' => $http . $_SERVER['SERVER_NAME'] . str_replace(DOC_PATH, '', $value),
                'update_time' => date('Y-m-d H:i:s', filemtime($value))
            ];
        }
        $jsonData = ['code' => 0,'msg'=> '', 'count' => count($difference),'data' => $pageList];
        return json_encode($jsonData);

    }

    public function do_ext()
    {
        $type = post('type');
        $list = post('list');

        $movePath = DOC_PATH . STATIC_DIR . '/backup/ImageExt';
        check_dir($movePath, true);

        switch ($type){
            case 0:
                //清理所选文件
                foreach ($list as $value){
                    $fileName = substr(strrchr($value['real_path'], "/"), 1);
                    $filePath = strstr($value['real_path'],'/static');
                    $fileDir = str_replace($fileName,'',$filePath);
                    check_dir($movePath . $fileDir, true);
                    rename($value['real_path'], $movePath . $filePath);
                }
                break;
            case 1:
                //清理所有冗余文件
                //获取上传的文件
                $Filepath = DOC_PATH . STATIC_DIR . '/upload';
                $fileList = get_dir($Filepath);
                $fileArr = [];
                array_walk_recursive($fileList, function ($key) use (&$fileArr) {
                    $fileArr[] = $key;
                    return $fileArr;
                });
                //查询数据
                $dataArr = [];
                $company = $this->companyModel->getImage();
                $contentSort = $this->contentSortModel->getImage();
                $content = $this->contentModel->getImage();
                $link = $this->linkModel->getImage();
                $member = $this->memberModel->getImage();
                $site = $this->siteModel->getImage();
                $slide = $this->slideModel->getImage();
				$Label = $this->LabelModel->getImage();
                $resArr = array_merge_recursive($company, $contentSort, $content, $link, $member, $site, $slide, $Label);
                array_walk_recursive($resArr, function ($key1) use (&$dataArr) {
                    if (!empty($key1)) {
                        $dataArr[] = DOC_PATH . SITE_DIR .$key1;
                    }
                    return $dataArr;
                });
                $dataArr = array_unique($dataArr);
                //对比文件并执行文件迁移
                $difference = array_diff($fileArr, $dataArr);
                $movePath = DOC_PATH . STATIC_DIR . '/backup/ImageExt/';
                check_dir($movePath, true);
                foreach ($difference as $path) {
                    $fileName = substr(strrchr($path, "/"), 1);
                    $filePath = strstr($path,'/static');
                    $fileDir = str_replace($fileName,'',$filePath);
                    check_dir($movePath . $fileDir, true);
                    rename($path, $movePath . $filePath);
                }
                break;
        }
        json(1,'');
    }
}
