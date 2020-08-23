<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
define('TP_CACHE_TIME',1); // 蓝鹊新房 缓存时间  31104000
// 定义时间
define('NOW_TIME',$_SERVER['REQUEST_TIME']);
error_reporting(E_ERROR | E_WARNING | E_PARSE);//报告运行时错误

// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
