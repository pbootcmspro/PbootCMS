<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年3月21日
 *  邮件发送类
 */
namespace core\basic;

class Smtp
{

    // 邮件传输代理服务器地址
    protected $sendServer;

    // 邮件传输代理服务器端口
    protected $port;

    // 是否是安全连接
    protected $isSecurity;

    // 邮件传输代理用户名
    protected $userName;

    // 邮件传输代理密码
    protected $password;

    // 发件人
    protected $from;

    // 收件人
    protected $to = array();

    // 抄送
    protected $cc = array();

    // 秘密抄送
    protected $bcc = array();

    // 主题
    protected $subject;

    // 邮件正文
    protected $body;

    // 附件
    protected $attachment = array();

    // 调试模式
    protected $debug;

    // 错误信息
    protected $errorMessage;

    // 资源句柄
    protected $socket;

    /**
     * 设置邮件传输代理，默认为安全链接
     *
     * @param string $server
     *            代理服务器的ip或者域名
     * @param string $username
     *            认证账号
     * @param string $password
     *            认证密码
     * @param int $port
     *            代理服务器的端口，smtp默认25号端口
     * @param boolean $isSecurity
     *            到服务器的连接是否为安全连接，默认false
     * @return boolean
     */
    public function __construct($server = "", $username = "", $password = "", $port = 465, $isSecurity = true, $debug = false)
    {
        if ($server) {
            $this->sendServer = $server;
            $this->port = $port;
            $this->isSecurity = $isSecurity ? true : false;
            $this->debug = $debug;
            $this->userName = empty($username) ? "" : base64_encode($username);
            $this->password = empty($password) ? "" : base64_encode($password);
            $this->from = $username;
        } else {
            $smtp = Config::get();
            $this->sendServer = $smtp['smtp_server'];
            $this->port = $smtp['smtp_port'];
            $this->isSecurity = $smtp['smtp_ssl'] ? true : false;
            $this->debug = $debug;
            $this->userName = base64_encode($smtp['smtp_username']);
            $this->password = base64_encode($smtp['smtp_password']);
            $this->from = $smtp['smtp_username'];
        }
        return true;
    }

    /**
     * 设置收件人，多个收件人，调用多次或用逗号隔开.
     *
     * @param string $to
     *            收件人地址
     * @return boolean
     */
    public function setReceiver($to)
    {
        if (strpos($to, ',')) {
            $this->to = explode(',', $to);
        } else {
            $this->to[] = $to;
        }
        return true;
    }

    /**
     * 设置抄送，多个抄送，调用多次或用逗号隔开.
     *
     * @param string $cc
     *            抄送地址
     * @return boolean
     */
    public function setCc($cc)
    {
        if (strpos($cc, ',')) {
            $this->cc = explode(',', $cc);
        } else {
            $this->cc[] = $cc;
        }
        return true;
    }

    /**
     * 设置秘密抄送，多个秘密抄送，调用多次或用逗号隔开.
     *
     * @param string $bcc
     *            秘密抄送地址
     * @return boolean
     */
    public function setBcc($bcc)
    {
        if (strpos($bcc, ',')) {
            $this->bcc = explode(',', $bcc);
        } else {
            $this->bcc[] = $bcc;
        }
        return true;
    }

    /**
     * 设置邮件附件，多个附件，调用多次
     *
     * @param string $file
     *            文件地址
     * @return boolean
     */
    public function addAttachment($file)
    {
        if (! file_exists($file)) {
            $this->errorMessage = "file " . $file . " does not exist.";
            return false;
        }
        $this->attachment[] = $file;
        return true;
    }

    /**
     * 设置邮件信息
     *
     * @param string $body
     *            邮件主题
     * @param string $subject
     *            邮件主体内容，可以是纯文本，也可是是HTML文本
     * @return boolean
     */
    public function setMail($subject, $body)
    {
        $this->subject = base64_encode($subject);
        $this->body = base64_encode($body);
        return true;
    }

    /**
     * 发送邮件
     *
     * @return boolean
     */
    public function sendMail($to = '', $subject = '', $body = '')
    {
        if ($to) {
            $this->setReceiver($to);
            $this->setMail($subject, $body);
        }
        $command = $this->getCommand();
        if (! $this->socket($this->isSecurity)) {
            return false;
        }
        foreach ($command as $value) {
            $result = $this->sendCommand($value[0], $value[1]);
            if ($result) {
                continue;
            } else {
                return false;
            }
        }
        // 关闭连接
        $this->close();
        return true;
    }

    /**
     * 返回错误信息
     *
     * @return string
     */
    public function error()
    {
        if (! isset($this->errorMessage)) {
            $this->errorMessage = "";
        }
        return $this->errorMessage;
    }

    /**
     * 返回mail命令
     *
     * @access protected
     * @return array
     */
    protected function getCommand()
    {
        $separator = "----=_Part_" . md5($this->from . time()) . uniqid(); // 分隔符
        $command = array(
            array(
                "HELO sendmail\r\n",
                250
            )
        );
        if (! empty($this->userName)) {
            $command[] = array(
                "AUTH LOGIN\r\n",
                334
            );
            $command[] = array(
                $this->userName . "\r\n",
                334
            );
            $command[] = array(
                $this->password . "\r\n",
                235
            );
        }
        // 设置发件人
        $command[] = array(
            "MAIL FROM: <" . $this->from . ">\r\n",
            250
        );
        $header = "FROM: <" . $this->from . ">\r\n";
        // 设置收件人
        if (! empty($this->to)) {
            $count = count($this->to);
            if ($count == 1) {
                $command[] = array(
                    "RCPT TO: <" . $this->to[0] . ">\r\n",
                    250
                );
                $header .= "TO: <" . $this->to[0] . ">\r\n";
            } else {
                for ($i = 0; $i < $count; $i ++) {
                    $command[] = array(
                        "RCPT TO: <" . $this->to[$i] . ">\r\n",
                        250
                    );
                    if ($i == 0) {
                        $header .= "TO: <" . $this->to[$i] . ">";
                    } elseif ($i + 1 == $count) {
                        $header .= ",<" . $this->to[$i] . ">\r\n";
                    } else {
                        $header .= ",<" . $this->to[$i] . ">";
                    }
                }
            }
        }
        // 设置抄送
        if (! empty($this->cc)) {
            $count = count($this->cc);
            if ($count == 1) {
                $command[] = array(
                    "RCPT TO: <" . $this->cc[0] . ">\r\n",
                    250
                );
                $header .= "CC: <" . $this->cc[0] . ">\r\n";
            } else {
                for ($i = 0; $i < $count; $i ++) {
                    $command[] = array(
                        "RCPT TO: <" . $this->cc[$i] . ">\r\n",
                        250
                    );
                    if ($i == 0) {
                        $header .= "CC: <" . $this->cc[$i] . ">";
                    } elseif ($i + 1 == $count) {
                        $header .= ",<" . $this->cc[$i] . ">\r\n";
                    } else {
                        $header .= ",<" . $this->cc[$i] . ">";
                    }
                }
            }
        }
        // 设置秘密抄送
        if (! empty($this->bcc)) {
            $count = count($this->bcc);
            if ($count == 1) {
                $command[] = array(
                    "RCPT TO: <" . $this->bcc[0] . ">\r\n",
                    250
                );
                $header .= "BCC: <" . $this->bcc[0] . ">\r\n";
            } else {
                for ($i = 0; $i < $count; $i ++) {
                    $command[] = array(
                        "RCPT TO: <" . $this->bcc[$i] . ">\r\n",
                        250
                    );
                    if ($i == 0) {
                        $header .= "BCC: <" . $this->bcc[$i] . ">";
                    } elseif ($i + 1 == $count) {
                        $header .= ",<" . $this->bcc[$i] . ">\r\n";
                    } else {
                        $header .= ",<" . $this->bcc[$i] . ">";
                    }
                }
            }
        }
        // 主题
        $header .= "Subject: =?UTF-8?B?" . $this->subject . "?=\r\n";
        if (isset($this->attachment)) {
            // 含有附件的邮件头需要声明成这个
            $header .= "Content-Type: multipart/mixed;\r\n";
        } elseif (false) {
            // 邮件体含有图片资源的,且包含的图片在邮件内部时声明成这个，如果是引用的远程图片，就不需要了
            $header .= "Content-Type: multipart/related;\r\n";
        } else {
            // html或者纯文本的邮件声明成这个
            $header .= "Content-Type: multipart/alternative;\r\n";
        }
        // 邮件头分隔符
        $header .= "\t" . 'boundary="' . $separator . '"';
        $header .= "\r\nMIME-Version: 1.0\r\n";
        // 这里开始是邮件的body部分，body部分分成几段发送
        $header .= "\r\n--" . $separator . "\r\n";
        $header .= "Content-Type:text/html; charset=utf-8\r\n";
        $header .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $header .= $this->body . "\r\n";
        $header .= "--" . $separator . "\r\n";
        // 加入附件
        if (! empty($this->attachment)) {
            $count = count($this->attachment);
            for ($i = 0; $i < $count; $i ++) {
                $header .= "\r\n--" . $separator . "\r\n";
                $header .= "Content-Type: " . $this->getMIMEType($this->attachment[$i]) . '; name="=?UTF-8?B?' . base64_encode(basename($this->attachment[$i])) . '?="' . "\r\n";
                $header .= "Content-Transfer-Encoding: base64\r\n";
                $header .= 'Content-Disposition: attachment; filename="=?UTF-8?B?' . base64_encode(basename($this->attachment[$i])) . '?="' . "\r\n";
                $header .= "\r\n";
                $header .= $this->readFile($this->attachment[$i]);
                $header .= "\r\n--" . $separator . "\r\n";
            }
        }
        // 结束邮件数据发送
        $header .= "\r\n.\r\n";
        
        $command[] = array(
            "DATA\r\n",
            354
        );
        $command[] = array(
            $header,
            250
        );
        $command[] = array(
            "QUIT\r\n",
            221
        );
        return $command;
    }

    /**
     * 发送命令
     *
     * @param string $command
     *            发送到服务器的smtp命令
     * @param int $code
     *            期望服务器返回的响应吗
     * @return boolean
     */
    protected function sendCommand($command, $code)
    {
        if ($this->debug) {
            echo 'Send command:' . $command . ',expected code:' . $code . '<br />';
        }
        try {
            if (fwrite($this->socket, $command)) {
                // 当邮件内容分多次发送时，没有$code，服务器没有返回
                if (empty($code)) {
                    return true;
                }
                // 读取服务器返回
                $data = trim(fread($this->socket, 1024));
                if (! mb_check_encoding($data, 'utf-8')) {
                    $data = iconv('gbk', 'utf-8', $data);
                }
                if ($this->debug) {
                    echo 'response:' . $data . '<br /><br />';
                }
                if ($data) {
                    $pattern = "/^" . $code . "+?/";
                    if (preg_match($pattern, $data)) {
                        return true;
                    } else {
                        $this->errorMessage = $data;
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                $this->errorMessage = "Error: " . $command . " send failed";
                return false;
            }
        } catch (\Exception $e) {
            $this->errorMessage = "Error:" . $e->getMessage();
        }
    }

    /**
     * 读取附件文件内容，返回base64编码后的文件内容
     *
     * @param string $file
     *            文件
     * @return mixed
     */
    protected function readFile($file)
    {
        if (file_exists($file)) {
            $file_obj = file_get_contents($file);
            return base64_encode($file_obj);
        } else {
            $this->errorMessage = "file " . $file . " dose not exist";
            return false;
        }
    }

    /**
     * 获取附件MIME类型
     *
     * @param string $file
     *            文件
     * @return mixed
     */
    protected function getMIMEType($file)
    {
        if (file_exists($file)) {
            $mime = mime_content_type($file);
            return $mime;
        } else {
            return false;
        }
    }

    /**
     * 建立到服务器的网络连接
     *
     * @return boolean
     */
    protected function socket($ssl = true)
    {
        if ($ssl && ! extension_loaded('openssl')) {
            $this->errorMessage = '服务器未启用openssl扩展，无法使用加密方式发送邮件！';
            return false;
        }
        
        if (function_exists('stream_socket_client')) {
            
            if (! function_exists('stream_socket_enable_crypto')) {
                $this->errorMessage = '服务器已经禁用stream_socket_enable_crypto函数，无法发送邮件！';
                return false;
            }
            
            if (! function_exists('stream_set_blocking')) {
                $this->errorMessage = '服务器已经禁用stream_set_blocking函数，无法发送邮件！';
                return false;
            }
            
            // 建立连接
            $remoteAddr = "tcp://" . $this->sendServer . ":" . $this->port;
            $this->socket = stream_socket_client($remoteAddr, $errno, $errstr, 30);
            if (! $this->socket) {
                $this->errorMessage = $errstr;
                return false;
            }
            
            // 设置加密方式
            $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
            }
            stream_socket_enable_crypto($this->socket, $ssl, $crypto_method);
        } elseif (function_exists('fsockopen')) {
            if ($ssl) {
                $remoteAddr = "ssl://" . $this->sendServer;
            } else {
                $remoteAddr = "tcp://" . $this->sendServer;
            }
            $this->socket = fsockopen($remoteAddr, $this->port, $errno, $errstr, 30);
            if (! $this->socket) {
                $this->errorMessage = $errstr;
                return false;
            }
        } else {
            $this->errorMessage = '服务器已经禁用stream_socket_client和fsockopen函数，请至少开启一个才能发送邮件！';
            return false;
        }
        
        stream_set_blocking($this->socket, 1); // 设置阻塞模式
        $str = fread($this->socket, 1024);
        if (! preg_match("/220+?/", $str)) {
            $this->errorMessage = $str;
            return false;
        }
        return true;
    }

    /**
     * 关闭安全socket
     *
     * @return boolean
     */
    protected function close()
    {
        if (isset($this->socket) && is_object($this->socket)) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
            return true;
        }
        $this->errorMessage = "No resource can to be close";
        return false;
    }
}