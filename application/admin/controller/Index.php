<?php

/**
 *  
 * @file   Index.php  
 * @date   2016-8-23 16:03:10 
 * @author Zhenxun Du<5552123@qq.com>  
 * @version    SVN:$Id:$ 
 */  

namespace app\admin\controller;
use app\common\logic\OrderLogic;

class Index extends Common {
    /**
     * 后台首页
     */
    public function index(){
        $ctime = time();
        vendor('my.Datept');
        $datept = new \Datept();


        /*-----------今日统计---------*/
        $today_start_time = $datept->beginToday();//当天开始-时间
        $today_end_time = $datept->endToday();//当天开结束-时间

        $where = [];
        $where['ctime'] = ['between', [$today_start_time, $today_end_time]];

        /*订单统计*/
        //新增订单
        $data['today_order_num'] = db('order')->where($where)->count();

        //销售额
        $data['today_total_amount'] = db('order')->where($where)->sum('total_amount');

        //成本
        $data['today_total_cost'] = db('order')->where($where)->sum('total_cost');

        //利润
        $data['today_profit'] = $data['today_total_amount'] - $data['today_total_cost'];



        /*会员统计*/
        //注册会员
        $data['today_user'] = db('users')->where($where)->count();
        //今天登录过会员
        $where = [];
        $where['last_login'] = ['between', [$today_start_time, $today_end_time]];
        $data['today_user_login'] = db('users')->where($where)->count();
        /*-----------今日统计END---------*/


        /*-----------本月统计---------*/
        $month_start_time = $datept->beginMonth();//当天开始-时间
        $month_end_time = $datept->endMonth();//当天开结束-时间

        $where = [];
        $where['ctime'] = ['between', [$month_start_time, $month_end_time]];

        /*订单统计*/
        //新增订单
        $data['cmonth_order_num'] = db('order')->where($where)->count();

        //销售额
        $data['cmonth_total_amount'] = db('order')->where($where)->sum('total_amount');

        //成本
        $data['cmonth_total_cost'] = db('order')->where($where)->sum('total_cost');

        //利润
        $data['cmonth_profit'] = $data['cmonth_total_amount'] - $data['cmonth_total_cost'];



        /*会员统计*/
        //注册会员
        $data['cmonth_user'] = db('users')->where($where)->count();
        //今天登录过会员
        $where = [];
        $where['last_login'] = ['between', [$month_start_time, $month_end_time]];
        $data['cmonth_user_login'] = db('users')->where($where)->count();
        /*-----------本月统计END---------*/




        /*上架商品统计*/
        $data['total_show_goods'] = db('goods')->where(['is_show'=>1])->count();


        /*供应商统计*/
        $data['total_suppliers'] = db('suppliers')->where(['is_show'=>1])->count();

        // sql();
        // ee($data);
        $this->assign('data', $data);
        return $this->fetch();
    }
    
    
}