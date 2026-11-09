<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年04月17日
 *  公司设置控制器 
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\CompanyModel;

class CompanyController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new CompanyModel();
    }

    // 显示公司设置
    public function index()
    {
        // 获取公司配置
        $this->assign('companys', $this->model->getList());
        $this->display('content/company.html');
    }

    // 修改公司设置
    public function mod()
    {
        if (! $_POST) {
            return;
        }
        $data = array(
            'name' => post('name'),
            'address' => post('address'),
            'postcode' => post('postcode'),
            'contact' => post('contact'),
            'mobile' => post('mobile'),
            'phone' => post('phone'),
            'fax' => post('fax'),
            'email' => post('email'),
            'qq' => post('qq'),
            'weixin' => post('weixin'),
            'blicense' => post('blicense'),
            'other' => post('other')
        );
        
        $back = url('/admin/Company/index');
        if ($this->model->checkCompany()) {
            if (admin_saved($this->model->modCompany($data), true)) {
                $this->log('修改公司信息成功！');
                success('修改成功！', $back);
            } else {
                alert_back('修改失败！');
            }
        } else {
            $data['acode'] = session('acode');
            if (admin_saved($this->model->addCompany($data), false)) {
                $this->log('修改公司信息成功！');
                success('修改成功！', $back);
            } else {
                alert_back('修改失败！');
            }
        }
    }
}