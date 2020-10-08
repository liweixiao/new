<?php
/**
 * ============================================================================
 * * 版权所有 2020-2030 没事忙，并保留所有权利。
 * ----------------------------------------------------------------------------
 * 个人学习免费, 如果商业用途务必到官网购买授权.
 * ============================================================================
 * $Author: 没事忙 2015-08-23
 */ 
namespace app\home\controller;
use think\Controller;
use think\Db;
use think\Session;

class Base extends Controller {
    public $session_id;
    public $user_id = 0;
    public $user = [];
    protected $config = null;
    /*
     * 初始化操作
     */
    public function _initialize() {
        if (I("unique_id")) { // 兼容手机app
            session_id(I("unique_id"));
            Session::start();
        }
        header("Cache-control: private");
    	$this->session_id = session_id(); // 当前的 session_id
        define('SESSION_ID',$this->session_id); //将当前的session_id保存为常量，供其它方法调用

        $user = session('user') ?? [];

        $this->user_id = $user['user_id'] ?? 0;

        $nologin = [
            'login','do_login','logout','verify','set_pwd','finished','verifyHandle','reg','send_sms_reg_code','identity','check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code','bind_account','bind_guide','bind_reg','loginpage','agreement'
        ];
        //未登录跳转到登录页
        $request = \think\Request::instance();
        $actionName = $request->action();
        $controllerName = $request->controller();
        if(!$this->user_id && !in_array($actionName, $nologin)){
            $this->redirect('Home/user/login',['u'=>input('u')]);
            exit;
        }
        
        //便于前端显示定位tab
        $mo['conAct'] = strtolower("{$controllerName}|{$actionName}");
        $mo['controller'] = strtolower($controllerName);//控制器名称

        //一级分类列表
        $BaseLogic = new \app\common\logic\BaseLogic();
        $this->cat1List = $BaseLogic->getCatList(0);
        // ee($user);

        $this->assign('cat1List', $this->cat1List);
        $this->assign('mo',$mo);
        $this->assign('user',$user);
        $this->assign('username',$user['nickname'] ?? '');
        $this->assign('user_id',$user['user_id'] ?? 0);
    }

    /*
     * 数据返回
     */
    public function ajaxReturn($data){
        exit(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}