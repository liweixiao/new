<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
return [
    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------
    'template' => [
        //默认使用公用
        'layout_on' => true,
        'layout_name' => 'layout',
    ],
    'view_replace_str'  =>  [
        '__STATIC__' => '/static/admin',
        '__HOMESTATIC__' => '/static/home',
    ],

    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => APP_PATH . 'admin/view' . DS . 'dispatch_jump.html',
    'dispatch_error_tmpl'    => APP_PATH . 'admin/view' . DS . 'dispatch_jump.html',

    //重置密码规则，为会员手机号加000
    'reset_password_rule' =>'0000',
];
