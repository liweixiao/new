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
use app\common\logic\ToolsLogic;
use app\common\logic\OrderLogic;

class Index extends Base {
    public function index(){
        $this->redirect('/home/user/index');
        return $this->fetch('index');
    }
    
    public function test(){
		return $this->fetch('index');
    }


    //推送消息-下单
    public function pushMessageOrder(){
        $OrderLogic = new OrderLogic;
        $order_id = I('order_id', 0);//订单编号
        $push_res = $OrderLogic->pushMessageOrder(['order_id'=>$order_id]);
        $this->ajaxReturn($push_res);
    }
}
