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

class Index extends Base {
    /**
     * 后台首页
     */
    public function index(){
        $stime = I('stime');
        $ctime = time();
        vendor('my.Datept');
        $datept = new \Datept();
        $OrderLogic = new OrderLogic();

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

        //无效订单=作废6、退款7
        $data['today_invalid_amount'] = db('order')->where($where)->where('order_status', 'IN', [6,7])->sum('total_amount');

        //利润
        $data['today_profit'] = $data['today_total_amount'] - $data['today_total_cost'] - $data['today_invalid_amount'];



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


        //如果是手动选择日期范围
        if (!empty($stime)) {
            $timeRangeArr = explode('-', $stime);
            $month_start_time = date('Y-m-d H:i:s', strtotime(trim($timeRangeArr[0])));//开始时间
            $month_end_time = date('Y-m-d 23:59:59', strtotime(trim($timeRangeArr[1])));//结束时间
        }

        $where = [];
        $where['ctime'] = ['between', [$month_start_time, $month_end_time]];

        /*订单统计*/
        //新增订单
        $data['cmonth_order_num'] = db('order')->where($where)->count();

        //销售额
        $data['cmonth_total_amount'] = db('order')->where($where)->sum('total_amount');
        // sql();
        //成本
        $data['cmonth_total_cost'] = db('order')->where($where)->sum('total_cost');

        //无效订单=作废6、退款7
        $data['cmonth_invalid_amount'] = db('order')->where($where)->where('order_status', 'IN', [6,7])->sum('total_amount');

        //利润
        $data['cmonth_profit'] = $data['cmonth_total_amount'] - $data['cmonth_total_cost'] - $data['cmonth_invalid_amount'];



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
        $suppliersList = db('suppliers')->where(['is_show'=>1])->where('code', 'NEQ', '88888')->select();
        $data['total_suppliers'] = count($suppliersList);
        //API余额统计
        foreach ($suppliersList as $key=>$supplier) {
            $suppliersList[$key]['money'] = $OrderLogic->getApiMoneyBySupplier($supplier['supplier_id']);
        }
        $data['suppliersList'] = $suppliersList;

        //异常订单统计-重要的订单
        $data['importantOrdersNum'] = db('order')->whereIn('order_status', [5])->count();
        $data['importantOrdersStatus'] = '5';

        //手工订单统计-需要手工做的订单
        $data['notAutoOrdersNum'] = db('order')->whereIn('order_status', [8])->count();
        $data['notAutoOrdersStatus'] = '8';

        //用户充值-未处理
        $data['rechargeNotDoNum'] = db('account_log')->where(['state'=>0, 'type'=>2])->count();
        
        // sql();
        // ee($data);
        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * ajax 修改指定表数据字段  一般修改状态 比如 是否推荐 是否开启 等 图标切换的
     * table,id_name,id_value,field,value
     */
    public function changeTableVal(){
        $res = ['error'=>0, 'msg'=>'操作成功'];
        $table = I('table'); // 表名
        $id_name = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field  = I('field'); // 修改哪个字段
        $value  = I('value'); // 修改字段值

        $result = db($table)->where([$id_name => $id_value])->update(array($field=>$value)); // 根据条件保存修改的数据
        if (!$result) {
            $res =  ['error'=>1, 'msg'=>'更新失败'];
            $this->ajaxReturn($res);
        }
        $this->ajaxReturn($res);
    }
    
    
}