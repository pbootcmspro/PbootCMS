<?php

/**
 * Created by JetBrains PhpStorm.
 * User: taoqili
 * Date: 12-7-18
 * Time: 上午11: 32
 * UEditor编辑器通用上传类
 */
class Uploader
{
    private $fileField; //文件域名
    private $file; //文件上传对象
    private $base64; //文件上传对象
    private $config; //配置信息
    private $oriName; //原始文件名
    private $fileName; //新文件名
    private $fullName; //完整文件名,即从当前配置目录开始的URL
    private $filePath; //完整文件名,即从当前配置目录开始的URL
    private $fileSize; //文件大小
    private $fileType; //文件类型
    private $stateInfo; //上传状态信息,
    private $stateMap = array( //上传状态映射表，国际化用户需考虑此处数据的国际化
        "SUCCESS", //上传成功标记，在UEditor中内不可改变，否则flash判断会出错
        "文件大小超出 upload_max_filesize 限制",
        "文件大小超出 MAX_FILE_SIZE 限制",
        "文件未被完整上传",
        "没有文件被上传",
        "上传文件为空",
        "ERROR_TMP_FILE" => "临时文件错误",
        "ERROR_TMP_FILE_NOT_FOUND" => "找不到临时文件",
        "ERROR_SIZE_EXCEED" => "文件大小超出网站限制",
        "ERROR_TYPE_NOT_ALLOWED" => "文件类型不允许",
        "ERROR_CREATE_DIR" => "目录创建失败",
        "ERROR_DIR_NOT_WRITEABLE" => "目录没有写权限",
        "ERROR_FILE_MOVE" => "文件保存时出错",
        "ERROR_FILE_NOT_FOUND" => "找不到上传文件",
        "ERROR_WRITE_CONTENT" => "写入文件内容错误",
        "ERROR_UNKNOWN" => "未知错误",
        "ERROR_DEAD_LINK" => "链接不可用",
        "ERROR_HTTP_LINK" => "链接不是http链接",
        "ERROR_HTTP_CONTENTTYPE" => "链接contentType不正确",
        "INVALID_URL" => "非法 URL",
        "INVALID_IP" => "非法 IP"
    );

    /**
     * 构造函数
     * @param string $fileField 表单名称
     * @param array $config 配置项
     * @param bool $base64 是否解析base64编码，可省略。若开启，则$fileField代表的是base64编码的字符串表单名
     */
    public function __construct($fileField, $config, $type = "upload")
    {
        $this->fileField = $fileField;
        $this->config = $config;
        $this->type = $type;
        if ($type == "remote") {
            $this->saveRemote();
        } else if($type == "base64") {
            $this->upBase64();
        } else {
            $this->upFile();
        }

        $this->stateMap['ERROR_TYPE_NOT_ALLOWED'] = iconv('unicode', 'utf-8', $this->stateMap['ERROR_TYPE_NOT_ALLOWED']);
    }

    /**
     * 上传文件的主处理方法
     * @return mixed
     */
    private function upFile()
    {
        $file = $this->file = $_FILES[$this->fileField];
        if (!$file) {
            $this->stateInfo = $this->getStateInfo("ERROR_FILE_NOT_FOUND");
            return;
        }
        if ($this->file['error']) {
            $this->stateInfo = $this->getStateInfo($file['error']);
            return;
        } else if (!file_exists($file['tmp_name'])) {
            $this->stateInfo = $this->getStateInfo("ERROR_TMP_FILE_NOT_FOUND");
            return;
        } else if (!is_uploaded_file($file['tmp_name'])) {
            $this->stateInfo = $this->getStateInfo("ERROR_TMPFILE");
            return;
        }

        $this->oriName = $file['name'];
        $this->fileSize = $file['size'];
        $this->fileType = $this->getFileExt();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->fileName = $this->getFileName();
        $dirname = dirname($this->filePath);

        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return;
        }

        //检查是否不允许的文件格式
        if (!$this->checkType()) {
            $this->stateInfo = $this->getStateInfo("ERROR_TYPE_NOT_ALLOWED");
            return;
        }

        //安全加固：MIME类型校验
        if (!$this->checkMimeType()) {
            $this->stateInfo = "文件内容与扩展名不匹配";
            return;
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
            return;
        } else if (!is_writeable($dirname)) {
            $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
            return;
        }

        //移动文件
        if (!(move_uploaded_file($file["tmp_name"], $this->filePath) && file_exists($this->filePath))) { //移动失败
            $this->stateInfo = $this->getStateInfo("ERROR_FILE_MOVE");
            return;
        }

        //安全加固：上传后检查文件内容是否包含恶意代码
        if (!$this->checkFileContent()) {
            @unlink($this->filePath);
            $this->stateInfo = "文件内容包含非法代码";
            return;
        }

        //安全加固：创建.htaccess防止上传目录执行PHP
        $this->createHtaccess();

        $this->stateInfo = $this->stateMap[0];
    }

    /**
     * 处理base64编码的图片上传
     * @return mixed
     */
    private function upBase64()
    {
        $base64Data = $_POST[$this->fileField];
        $img = base64_decode($base64Data);

        $this->oriName = $this->config['oriName'];
        $this->fileSize = strlen($img);
        $this->fileType = $this->getFileExt();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->fileName = $this->getFileName();
        $dirname = dirname($this->filePath);

        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return;
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
            return;
        } else if (!is_writeable($dirname)) {
            $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
            return;
        }

        //移动文件
        if (!(file_put_contents($this->filePath, $img) && file_exists($this->filePath))) { //移动失败
            $this->stateInfo = $this->getStateInfo("ERROR_WRITE_CONTENT");
            return;
        }

        //安全加固：检查文件内容
        if (!$this->checkFileContent()) {
            @unlink($this->filePath);
            $this->stateInfo = "文件内容包含非法代码";
            return;
        }

        //安全加固：创建.htaccess
        $this->createHtaccess();

        $this->stateInfo = $this->stateMap[0];
    }

    /**
     * 拉取远程图片
     * @return mixed
     */
    private function saveRemote()
    {
        $imgUrl = htmlspecialchars($this->fileField);
        $imgUrl = str_replace("&amp;", "&", $imgUrl);

        //http开头验证
        if (strpos($imgUrl, "http") !== 0) {
            $this->stateInfo = $this->getStateInfo("ERROR_HTTP_LINK");
            return;
        }

        preg_match('/(^https*:\/\/[^:\/]+)/', $imgUrl, $matches);
        $host_with_protocol = count($matches) > 1 ? $matches[1] : '';

        // 判断是否是合法 url
        if (!filter_var($host_with_protocol, FILTER_VALIDATE_URL)) {
            $this->stateInfo = $this->getStateInfo("INVALID_URL");
            return;
        }

        preg_match('/^https*:\/\/(.+)/', $host_with_protocol, $matches);
        $host_without_protocol = count($matches) > 1 ? $matches[1] : '';

        // 此时提取出来的可能是 ip 也有可能是域名，先获取 ip
        $ip = gethostbyname($host_without_protocol);
        // 判断是否是私有 ip
        if(!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
            $this->stateInfo = $this->getStateInfo("INVALID_IP");
            return;
        }

        //获取请求头并检测死链
        $heads = get_headers($imgUrl, 1);
        if (!(stristr($heads[0], "200") && stristr($heads[0], "OK"))) {
            $this->stateInfo = $this->getStateInfo("ERROR_DEAD_LINK");
            return;
        }
        //格式验证(扩展名验证和Content-Type验证)
        $fileType = strtolower(strrchr($imgUrl, '.'));
        if (!in_array($fileType, $this->config['allowFiles']) || !isset($heads['Content-Type']) || !stristr($heads['Content-Type'], "image")) {
            $this->stateInfo = $this->getStateInfo("ERROR_HTTP_CONTENTTYPE");
            return;
        }

        //打开输出缓冲区并获取远程图片
        ob_start();
        $context = stream_context_create(
            array('http' => array(
                'follow_location' => false // don't follow redirects
            ))
        );
        readfile($imgUrl, false, $context);
        $img = ob_get_contents();
        ob_end_clean();
        preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/", $imgUrl, $m);

        $this->oriName = $m ? $m[1]:"";
        $this->fileSize = strlen($img);
        $this->fileType = $this->getFileExt();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->fileName = $this->getFileName();
        $dirname = dirname($this->filePath);

        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return;
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
            return;
        } else if (!is_writeable($dirname)) {
            $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
            return;
        }

        //移动文件
        if (!(file_put_contents($this->filePath, $img) && file_exists($this->filePath))) { //移动失败
            $this->stateInfo = $this->getStateInfo("ERROR_WRITE_CONTENT");
            return;
        }

        //安全加固：检查文件内容
        if (!$this->checkFileContent()) {
            @unlink($this->filePath);
            $this->stateInfo = "文件内容包含非法代码";
            return;
        }

        //安全加固：创建.htaccess
        $this->createHtaccess();

        $this->stateInfo = $this->stateMap[0];
    }

    /**
     * 上传错误检查
     * @param $errCode
     * @return string
     */
    private function getStateInfo($errCode)
    {
        return !$this->stateMap[$errCode] ? $this->stateMap["ERROR_UNKNOWN"] : $this->stateMap[$errCode];
    }

    /**
     * 获取文件扩展名
     * @return string
     */
    private function getFileExt()
    {
        return strtolower(strrchr($this->oriName, '.'));
    }

    /**
     * 重命名文件
     * @return string
     */
    private function getFullName()
    {
        //替换日期事件
        $t = time();
        $d = explode('-', date("Y-y-m-d-H-i-s"));
        $format = $this->config["pathFormat"];
        $format = str_replace("{yyyy}", $d[0], $format);
        $format = str_replace("{yy}", $d[1], $format);
        $format = str_replace("{mm}", $d[2], $format);
        $format = str_replace("{dd}", $d[3], $format);
        $format = str_replace("{hh}", $d[4], $format);
        $format = str_replace("{ii}", $d[5], $format);
        $format = str_replace("{ss}", $d[6], $format);
        $format = str_replace("{time}", $t, $format);

        //过滤文件名的非法自负,并替换文件名
        $oriName = substr($this->oriName, 0, strrpos($this->oriName, '.'));
        $oriName = preg_replace("/[\|\?\"\<\>\/\*\\\\]+/", '', $oriName);
        $format = str_replace("{filename}", $oriName, $format);

        //替换随机字符串
        $randNum = mt_rand(1, 2147483647) . mt_rand(1, 2147483647);
        if (preg_match("/\{rand\:([\d]*)\}/i", $format, $matches)) {
            $format = preg_replace("/\{rand\:[\d]*\}/i", substr($randNum, 0, $matches[1]), $format);
        }

        $ext = $this->getFileExt();
        return $format . $ext;
    }

    /**
     * 获取文件名
     * @return string
     */
    private function getFileName () {
        return substr($this->filePath, strrpos($this->filePath, '/') + 1);
    }

    /**
     * 获取文件完整路径
     * @return string
     */
    private function getFilePath()
    {
        $fullname = $this->fullName;
        $rootPath = $_SERVER['DOCUMENT_ROOT'];

        if (substr($fullname, 0, 1) != '/') {
            $fullname = '/' . $fullname;
        }

        return $rootPath . $fullname;
    }

    /**
     * 文件类型检测
     * @return bool
     */
    private function checkType()
    {
        return in_array($this->getFileExt(), $this->config["allowFiles"]);
    }

    /**
     * MIME类型校验（安全加固）
     * 验证文件的真实MIME类型与声明的扩展名是否匹配
     * @return bool
     */
    private function checkMimeType()
    {
        $ext = $this->getFileExt();
        // 扩展名到MIME类型的映射
        $mimeMap = array(
            '.jpg'    => array('image/jpeg', 'image/jpg', 'image/pjpeg'),
            '.jpeg'   => array('image/jpeg', 'image/jpg', 'image/pjpeg'),
            '.png'    => array('image/png', 'image/x-png'),
            '.gif'    => array('image/gif'),
            '.bmp'    => array('image/bmp', 'image/x-ms-bmp'),
            '.ico'    => array('image/x-icon', 'image/vnd.microsoft.icon'),
            '.svg'    => array('image/svg+xml'),
            '.mp4'    => array('video/mp4'),
            '.mp3'    => array('audio/mpeg'),
            '.webm'   => array('video/webm'),
            '.pdf'    => array('application/pdf'),
            '.doc'    => array('application/msword'),
            '.docx'   => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            '.xls'    => array('application/vnd.ms-excel'),
            '.xlsx'   => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            '.ppt'    => array('application/vnd.ms-powerpoint'),
            '.pptx'   => array('application/vnd.openxmlformats-officedocument.presentationml.presentation'),
            '.zip'    => array('application/zip', 'application/x-zip-compressed'),
            '.rar'    => array('application/x-rar-compressed', 'application/vnd.rar'),
            '.txt'    => array('text/plain'),
            '.xml'    => array('text/xml', 'application/xml'),
            '.flv'    => array('video/x-flv'),
            '.avi'    => array('video/x-msvideo'),
        );

        // 对于图片类型，使用更严格的图像类型检测
        if (in_array($ext, array('.jpg', '.jpeg', '.png', '.gif', '.bmp'))) {
            if (function_exists('exif_imagetype')) {
                $imgType = @exif_imagetype($this->file['tmp_name']);
                $expectedTypes = array(
                    '.jpg'  => array(IMAGETYPE_JPEG),
                    '.jpeg' => array(IMAGETYPE_JPEG),
                    '.png'  => array(IMAGETYPE_PNG),
                    '.gif'  => array(IMAGETYPE_GIF),
                    '.bmp'  => array(IMAGETYPE_BMP),
                );
                if (!isset($expectedTypes[$ext]) || !in_array($imgType, $expectedTypes[$ext])) {
                    return false;
                }
            }
        }

        // 通用MIME类型检查
        if (isset($mimeMap[$ext]) && function_exists('finfo_file')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $realMime = @finfo_file($finfo, $this->file['tmp_name']);
                finfo_close($finfo);
                if ($realMime && !in_array($realMime, $mimeMap[$ext])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 检查上传文件内容是否包含恶意代码（安全加固）
     * @return bool
     */
    private function checkFileContent()
    {
        $ext = $this->getFileExt();
        // 只检查可能被伪装的可执行文件类型（图片、文本等）
        if (!in_array($ext, array('.jpg', '.jpeg', '.png', '.gif', '.bmp', '.svg', '.txt', '.xml'))) {
            return true; // 其他类型不检查内容
        }

        $content = @file_get_contents($this->filePath);
        if ($content === false) {
            return true;
        }

        // 检测PHP标签
        $phpPatterns = array(
            '/<\?php/i',
            '/<\?\s+/i',
            '/<\?=\s*/i',
            '/<\?$/m',
        );
        foreach ($phpPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        // 检测常见的 webshell 函数调用
        $dangerousPatterns = array(
            '/eval\s*\(/i',
            '/assert\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
            '/base64_decode\s*\(/i',
            '/file_put_contents\s*\(/i',
        );
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 创建 .htaccess 文件防止上传目录执行 PHP（安全加固）
     */
    private function createHtaccess()
    {
        $htaccessPath = dirname($this->filePath) . '/.htaccess';
        if (file_exists($htaccessPath)) {
            return;
        }
        $htaccessContent = "<FilesMatch \"\\.(?i:php)$\">\n";
        $htaccessContent .= "    Order allow,deny\n";
        $htaccessContent .= "    Deny from all\n";
        $htaccessContent .= "</FilesMatch>\n";
        $htaccessContent .= "<FilesMatch \"\\.(?i:php\\d*)$\">\n";
        $htaccessContent .= "    Order allow,deny\n";
        $htaccessContent .= "    Deny from all\n";
        $htaccessContent .= "</FilesMatch>\n";
        $htaccessContent .= "php_flag engine off\n";
        @file_put_contents($htaccessPath, $htaccessContent);
    }

    /**
     * 文件大小检测
     * @return bool
     */
    private function  checkSize()
    {
        return $this->fileSize <= ($this->config["maxSize"]);
    }

    /**
     * 获取当前上传成功文件的各项信息
     * @return array
     */
    public function getFileInfo()
    {
        return array(
            "state" => $this->stateInfo,
            "url" => $this->fullName,
            "title" => $this->fileName,
            "original" => $this->oriName,
            "type" => $this->fileType,
            "size" => $this->fileSize
        );
    }

}