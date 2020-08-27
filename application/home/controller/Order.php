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
use app\common\logic\OrderLogic;
use app\common\logic\ToolsLogic;
use app\common\logic\UsersLogic;

class Order extends Base {
    public function _initialize() {
        parent::_initialize();
        $this->ToolsLogic = new ToolsLogic;
        $this->UsersLogic = new UsersLogic;
        $this->OrderLogic = new OrderLogic;

    }

    public function index(){
        $params = I('get.');//请求参数
        $userInfo = $this->UsersLogic->get_user_info($this->user_id);
        if (empty($userInfo)) {
            $this->error('非法请求');
        }

        //获取用户订单列表
        $params['user_id'] = $this->user_id;
        // $this->OrderLogic->showNum = 2;
        $rows = $this->OrderLogic->getOrderList($params);
        $page = $this->OrderLogic->page;
        $tags = $this->ToolsLogic->getAllTags('run_first', $cat_id);
        // ee($tags);
        $this->assign('page', $page);
        $this->assign('rows', $rows);
        $this->assign('tags', $tags);
        $this->assign('userInfo', $userInfo);
        return $this->fetch();
    }

    //订单状态-平台ip121.199.15.68
    public function order_status(){
        $s = I('s', 0);//供应商id
        $supplier = $this->ToolsLogic->getSupplier($s);
        if (empty($supplier)) {
            $this->error('非法请求');
        }

        $url = $supplier['url'] . '/wb/api_status.php';
        $apikey = $supplier['apikey'];
        $res = apiget($url, ['apikey'=>$apikey, 'renwuid'=>177565, 'type'=>'query']);

        return $this->fetch();
    }

    //设置订单(暂停或者继续)-平台ip121.199.15.68
    public function setOrder(){
        $params = I('post.');//供应商id
        $res = $this->OrderLogic->setOrder($params);//设置订单
        $this->ajaxReturn($res);
    }

    //创建订单-平台ip121.199.15.68
    public function create(){
        $res = ['error'=>0, 'msg'=>'恭喜，提交成功'];
        $cat_id = I('cat_id', 0);//分类id
        $goods_id = I('goods_id', 0);//商品id
        $data = I('post.');

        if (empty($data['url'])) {
            $res = ['error'=>1, 'msg'=>'网址必须填写'];
            $this->ajaxReturn($res);
        }

        if (empty($data['task_num']) || $data['task_num'] < 1) {
            $res = ['error'=>1, 'msg'=>'任务数量必须大于1'];
            $this->ajaxReturn($res);
        }


        //获取商品
        $row = $this->ToolsLogic->getGoodsRow($goods_id);
        if (empty($row)) {
            $res = ['error'=>1, 'msg'=>'非法请求'];
            $this->ajaxReturn($res);
        }
        
        $realTaskNum = $data['task_num'];//注意这里不用乘1万了

        $userInfo = $this->ToolsLogic->get_user_info($this->user_id);
        if (empty($userInfo)) {
            $res = ['error'=>1, 'msg'=>'抱歉，会员不存在，请联系管理员'];
            $this->ajaxReturn($res);
        }

        //余额是否充足检测
        $total_amount = $this->ToolsLogic->getTotalAmount($realTaskNum, $row['goods_id'], $this->user_id);//计算订单总价
        //检查用户余额是否充足
        if ($total_amount > $userInfo['user_money']) {
            $res = ['error'=>1, 'msg'=>"抱歉，余额不足，当前余额为：<b>{$userInfo['user_money']}</b>"];
            $this->ajaxReturn($res);
        }

        //写入数据
        $data['user_id'] = $this->user_id;//用户id
        $data['task_num'] = $realTaskNum;//下单数量
        $res = $this->OrderLogic->createOrder($data);
        if ($res['error']) {
            $res = ['error'=>1, 'msg'=>$res['msg']];
            $this->ajaxReturn($res);
        }
        $this->ajaxReturn($res);

    }
    

}
