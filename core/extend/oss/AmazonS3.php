<?php
/**
 * 亚马逊S3协议通用上传类（如阿里云 OSS、腾讯云COS、华为云OBS）
 * 
 * @author gzu-liyujiang@foxmail.com
 * @version 2026.0.0
 */
namespace core\extend\oss;

class AmazonS3
{
    private $accessKeyId;
    private $accessKeySecret;
    private $endpoint;
    private $bucket;
    private $cdnDomain;
    
    /**
     * 构造函数
     * @param array $config OSS 配置
     */
    public function __construct($config = [])
    {
        $this->accessKeyId = $config['accessKeyId'] ?? '';
        $this->accessKeySecret = $config['accessKeySecret'] ?? '';
        $this->endpoint = $config['endpoint'] ?? '';
        $this->bucket = $config['bucket'] ?? '';
        $this->cdnDomain = $config['cdnDomain'] ?? '';
    }
    
    /**
     * 上传文件到 OSS
     * @param string $filePath 本地文件路径
     * @param string $ossPath OSS 存储路径
     * @return array ['code' => 1/0, 'msg' => '', 'url' => '']
     */
    public function uploadFile($filePath, $ossPath)
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'code' => 0,
                    'msg' => '文件不存在',
                    'url' => ''
                ];
            }
            
            // 使用 cURL 上传文件
            $url = $this->buildOssUrl($ossPath);
            $content = file_get_contents($filePath);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
            
            // 设置 OSS 必需的 Header
            $date = gmdate('D, d M Y H:i:s T');
            $contentType = $this->getContentType($filePath);
            $authorization = $this->generateSignature('PUT', '', $contentType, $date, '/' . $this->bucket . '/' . $ossPath);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $authorization,
                'Content-Type: ' . $contentType,
                'Date: ' . $date,
                'Content-Length: ' . strlen($content)
            ]);
            
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                return [
                    'code' => 1,
                    'msg' => '上传成功',
                    'url' => $this->getFileUrl($ossPath)
                ];
            } else {
                return [
                    'code' => 0,
                    'msg' => '上传失败，HTTP状态码：' . $httpCode,
                    'url' => ''
                ];
            }
        } catch (\Throwable $e) {
            return [
                'code' => 0,
                'msg' => '上传异常：' . $e->getMessage(),
                'url' => ''
            ];
        }
    }
    
    /**
     * 删除 OSS 文件
     * @param string $ossPath OSS 文件路径
     * @return bool
     */
    public function deleteFile($ossPath)
    {
        try {
            $url = $this->buildOssUrl($ossPath);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            
            $date = gmdate('D, d M Y H:i:s T');
            $authorization = $this->generateSignature('DELETE', '', '', $date, '/' . $this->bucket . '/' . $ossPath);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $authorization,
                'Date: ' . $date
            ]);
            
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode == 204;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * 获取文件访问 URL
     * @param string $ossPath OSS 文件路径
     * @return string
     */
    public function getFileUrl($ossPath)
    {
        if ($this->cdnDomain) {
            return rtrim($this->cdnDomain, '/') . '/' . ltrim($ossPath, '/');
        }
        return 'https://' . $this->bucket . '.' . $this->endpoint . '/' . ltrim($ossPath, '/');
    }
    
    /**
     * 构建 OSS URL
     * @param string $ossPath
     * @return string
     */
    private function buildOssUrl($ossPath)
    {
        return 'https://' . $this->bucket . '.' . $this->endpoint . '/' . ltrim($ossPath, '/');
    }
    
    /**
     * 生成签名
     * @param string $method HTTP 方法
     * @param string $md5 Content-MD5
     * @param string $contentType Content-Type
     * @param string $date Date
     * @param string $resource Resource
     * @return string
     */
    private function generateSignature($method, $md5, $contentType, $date, $resource)
    {
        $stringToSign = $method . "\n" . $md5 . "\n" . $contentType . "\n" . $date . "\n" . $resource;
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
        return 'OSS ' . $this->accessKeyId . ':' . $signature;
    }
    
    /**
     * 获取文件 MIME 类型
     * @param string $filePath
     * @return string
     */
    private function getContentType($filePath)
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mp3' => 'audio/mpeg',
        ];
        
        return $mimeTypes[$ext] ?? 'application/octet-stream';
    }
}
