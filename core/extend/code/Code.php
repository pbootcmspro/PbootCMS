<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年12月16日
 *  验证码生成类 
 */
namespace core\extend\code;

class Code
{

    // 随机因子
    public $charset = 'ABCDEFGHKMNPRTUVWXY23456789';

    // 指定字体大小
    public $fontsize = 18;

    // 验证码长度
    public $codelen = 4;

    // 宽度
    public $width = 130;

    // 高度
    public $height = 50;

    // 验证码
    private $code;

    // 图形资源句柄
    private $img;

    // 指定的字体
    private $font;

    // 指定字体颜色
    private $fontcolor;

    // 构造方法初始化
    public function __construct()
    {
        $this->font = dirname(__FILE__) . '/elephant.ttf';
    }

    // 生成随机码
    private function createCode()
    {
        $this->charset = str_shuffle($this->charset);
        $_len = strlen($this->charset) - 1;
        for ($i = 0; $i < $this->codelen; $i ++) {
            $this->code .= $this->charset[mt_rand(0, $_len)];
        }
    }

    // 生成背景
    private function createBg()
    {
        $this->img = imagecreatetruecolor($this->width, $this->height);
        $color = imagecolorallocate($this->img, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
        imagefilledrectangle($this->img, 0, $this->height, $this->width, 0, $color);
    }

    // 生成文字
    private function createFont()
    {
        $_x = ($this->width - 10) / $this->codelen;
        for ($i = 0; $i < $this->codelen; $i ++) {
            $this->fontcolor = imagecolorallocate($this->img, mt_rand(0, 100), mt_rand(0, 100), mt_rand(0, 100));
            imagettftext($this->img, $this->fontsize, mt_rand(- 20, 20), $_x * $i + $_x / 3, $this->height / 1.4, $this->fontcolor, $this->font, $this->code[$i]);
        }
    }

    // 生成线条、雪花
    private function createLine()
    {
        for ($i = 0; $i < 6; $i ++) {
            $color = imagecolorallocate($this->img, mt_rand(100, 200), mt_rand(100, 200), mt_rand(100, 200));
            imageline($this->img, mt_rand(0, $this->width), mt_rand(0, $this->height), mt_rand(0, $this->width), mt_rand(0, $this->height), $color);
        }
        for ($i = 0; $i < 100; $i ++) {
            $color = imagecolorallocate($this->img, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
            imagestring($this->img, mt_rand(1, 5), mt_rand(0, $this->width), mt_rand(0, $this->height), '*', $color);
        }
    }

    // 输出
    private function outPut()
    {
        header('Content-type:image/png');
        imagepng($this->img);
        imagedestroy($this->img);
    }

    // 对外生成
    public function doimg()
    {
        @ob_clean(); // 清理图片输出前内容，避免生成错误！
        $this->createBg();
        $this->createCode();
        $this->createLine();
        $this->createFont();
        $this->outPut();
    }

    // 获取验证码
    public function getCode()
    {
        return strtolower($this->code);
    }
}