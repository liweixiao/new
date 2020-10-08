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

        //修改前端状态显示
        $this->OrderLogic->orderStatusConfig['5'] = $this->OrderLogic->orderStatusConfig['3'];
        $this->OrderLogic->orderStatusConfig['8'] = $this->OrderLogic->orderStatusConfig['3'];
    }

    public function index(){
        $data = [];

        $params = I('get.');//请求参数
        $cat_id = $data['cat_id'] = I('cid', 1);//商品一级分类id
        $goods_id = $data['goods_id'] = I('gid', 1);//商品id

        $userInfo = $data['userInfo'] = $this->UsersLogic->get_user_info($this->user_id);
        if (empty($userInfo)) {
            $this->error('非法请求');
        }

        if (empty($cat_id)) {
            $this->error('抱歉，分类参数有误');
        }

        if (empty($goods_id)) {
            $this->error('抱歉，商品参数有误');
        }

        //获取当前分类下面所有子分类
        $data['catList'] = $this->OrderLogic->getCatList(0);

        //获取当前分类下面所有子分类ids
        $subCatIds = $this->OrderLogic->getSubCatIds($cat_id);

        if (!empty($cat_id)) {
            $params['cat_ids'] = $subCatIds;
        }

        $data['goodsRows'] = $this->OrderLogic->getCatGoodsList($subCatIds);

        //获取用户订单列表
        $params['user_id'] = $this->user_id;
        // $this->OrderLogic->showNum = 2;
        $params['goods_id'] = $goods_id;
        $this->OrderLogic->showNum = 8;//由于是单条解析，所以这里放量少点，防止卡顿
        $orderLists = $this->OrderLogic->getOrderList($params);
        if ($orderLists['error']) {
            $this->error($orderLists['msg']);
        }
        $data['rows'] = $orderLists;

        $data['page'] = $this->OrderLogic->page;
        $tags = $this->OrderLogic->getAllTags('run_first', $cat_id);
        //获取商品订单统计(统计每个商品下单数量)
        $data['orderGoodsStat'] = $this->OrderLogic->getOrderGoodsStat($params);


        // ee($data);
        // ee($rows);
        $this->assign('tags', $tags);
        $this->assign('data', $data);
        return $this->fetch();
    }

    //订单状态-平台ip121.199.15.68
    public function order_status(){
        $s = I('s', 0);//供应商id
        $supplier = $this->OrderLogic->getSupplier($s);
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
        $cat_id = input('cat_id', 0);//分类id
        $goods_id = input('goods_id', 0);//商品id
        $data = input('post.');
        // ee($data);

        if (empty($data['task_num']) || $data['task_num'] < 1) {
            $res = ['error'=>1, 'msg'=>'任务数量必须大于1'];
            $this->ajaxReturn($res);
        }

        //获取商品
        $row = $this->OrderLogic->getGoodsRow($goods_id);
        if (empty($row)) {
            $res = ['error'=>1, 'msg'=>'非法请求'];
            $this->ajaxReturn($res);
        }

        if (empty($data['url'])) {
            $res = ['error'=>1, 'msg'=>'网址必须填写'];
            $this->ajaxReturn($res);
        }
        
        $taskNum = $data['task_num'];//注意这里不用乘1万了

        $userInfo = $this->OrderLogic->get_user_info($this->user_id);
        if (empty($userInfo)) {
            $res = ['error'=>1, 'msg'=>'抱歉，会员不存在，请联系管理员'];
            $this->ajaxReturn($res);
        }

        //是否有价格倍率操作-补粉类型
        if (!empty($data['bf_type_id'])) {
            $price_param_row = $this->OrderLogic->getLabelRow($data['bf_type_id']);
            if ($price_param_row) {
                $data['bfType'] = $price_param_row['label_id'];//补粉选项
                $this->OrderLogic->price_param = $price_param_row['tag'];
            }
        }

        //是否有价格倍率操作-转发评论选项
        if (!empty($data['relay_type_id'])) {
            $price_param_row = $this->OrderLogic->getLabelRow($data['relay_type_id']);
            if ($price_param_row) {
                $data['relay_type'] = $price_param_row['label_id'];//补粉选项
                $this->OrderLogic->price_param = $price_param_row['tag'];
            }
        }

        //余额是否充足检测(加粉选择补粉|掉粉不补选项)
        $total_amount = $this->OrderLogic->getTotalAmount($taskNum, $row['goods_id'], $this->user_id);//计算订单总价
        // ee($this->OrderLogic->price_param);
        // ee($total_amount);

        //检查用户余额是否充足
        if ($total_amount > $userInfo['user_money']) {
            $res = ['error'=>1, 'msg'=>"抱歉，余额不足，当前余额为：<b>{$userInfo['user_money']}</b>"];
            $this->ajaxReturn($res);
        }

        //写入数据
        $data['user_id'] = $this->user_id;//用户id
        $data['task_num'] = $taskNum;//下单数量
        $res = $this->OrderLogic->createOrder($data);
        if ($res['error']) {
            $res = ['error'=>1, 'msg'=>$res['msg']];
            $this->ajaxReturn($res);
        }
        $this->ajaxReturn($res);

    }
    

}
