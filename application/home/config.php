<?php
return [
    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------
    'view_replace_str'  =>  [
        '__STATIC__' => '/static/home',
    ],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => APP_PATH . 'home/view' . DS . 'dispatch_jump.html',
    'dispatch_error_tmpl'    => APP_PATH . 'home/view' . DS . 'dispatch_jump.html',
];