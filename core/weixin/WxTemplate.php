<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2019年5月27日
 *  模板消息
 */
namespace core\weixin;

class WxTemplate
{

    // 设置行业信息
    public function setIndustry($industry_id1, $industry_id2)
    {
        $access_token = WxAccessToken::get();
        $url = "https://api.weixin.qq.com/cgi-bin/template/api_set_industry?access_token=" . $access_token;
        $data = array(
            'industry_id1' => $industry_id1,
            'industry_id2' => $industry_id2
        );
        $result = json_decode(get_url($url, json_encode($data)));
        if (isset($result->errcode) && $result->errcode) {
            error('设置微信消息行业发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
        } else {
            return true;
        }
    }

    // 获取行业信息
    public function getIndustry()
    {
        $access_token = WxAccessToken::get();
        $url = "https://api.weixin.qq.com/cgi-bin/template/get_industry?access_token=" . $access_token;
        $result = json_decode(get_url($url));
        if (isset($result->errcode) && $result->errcode) {
            error('获取微信消息行业发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
        } else {
            return $result;
        }
    }

    // 获取模板ID
    public function getTemplateID($template_id_short)
    {
        $access_token = WxAccessToken::get();
        $url = "https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token=" . $access_token;
        $data = array(
            'template_id_short' => $template_id_short
        );
        $result = json_decode(get_url($url, json_encode($data)));
        if (isset($result->errcode) && $result->errcode) {
            error('获取微信模板ID发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
        } else {
            return $result;
        }
    }

    // 获取模板ID
    public function getTemplateList()
    {
        $access_token = WxAccessToken::get();
        $url = "https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token=" . $access_token;
        $result = json_decode(get_url($url));
        if (isset($result->errcode) && $result->errcode) {
            error('获取微信模板列表发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
        } else {
            return $result;
        }
    }

    // 获取模板ID
    public function delTemplate($template_id)
    {
        $access_token = WxAccessToken::get();
        $url = "https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token=" . $access_token;
        $data = array(
            'template_id' => $template_id
        );
        $result = json_decode(get_url($url, json_encode($data)));
        if (isset($result->errcode) && $result->errcode) {
            error('删除微信模板发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
        } else {
            return true;
        }
    }

    // 发送模板消息
    public function sendTemplateMsg($openid, $template_id, $data, $remark = null, $link = null)
    {
        $access_token = WxAccessToken::get();
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        // data只需传递数组数据即可，自动生成格式化数据
        if (! is_multi_array($data)) {
            $i = 1;
            $flag = true;
            foreach ($data as $key => $value) {
                if (is_numeric($key)) {
                    if ($flag && $i == 1 && ! isset($data['first'])) {
                        $post_data['first'] = array(
                            'value' => $value,
                            'color' => '#173177'
                        
                        );
                        $flag = false;
                    } else {
                        $post_data['keyword' . $i] = array(
                            'value' => $value,
                            'color' => '#173177'
                        );
                        $i ++;
                    }
                } else {
                    $post_data[$key] = array(
                        'value' => $value,
                        'color' => '#173177'
                    );
                }
            }
            // 备注字段
            if ($remark) {
                $post_data['remark'] = array(
                    'value' => $remark,
                    'color' => '#173177'
                );
            }
        }
        // 生成完成数据数组
        $send_data = array(
            'touser' => $openid,
            'template_id' => $template_id,
            'data' => $post_data
        );
        // 有跳转时设置
        if ($link) {
            $send_data['url'] = $link;
        }
        // 执行发送
        $result = json_decode(get_url($url, json_encode($send_data, JSON_FORCE_OBJECT)));
        if (isset($result->errcode) && $result->errcode) {
            error('微信消息发送错误：' . $result->errmsg . '(' . $result->errcode . ')');
        } else {
            return true;
        }
    }
}