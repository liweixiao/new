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

class Order extends Base {
    public function _initialize() {
        parent::_initialize();
        $this->ToolsLogic = new ToolsLogic;

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

    //创建订单-平台ip121.199.15.68
    public function create(){
        $res = ['status'=>1, 'msg'=>'恭喜，提交成功'];
        $cat_id = I('cat_id', 0);//供应商id
        $data = I('post.');
        $cat = $this->ToolsLogic->getCatRow($cat_id);
        if (empty($cat)) {
            $this->error('非法请求');
        }
        // ee($data);
        $url = $supplier['url'] . '/wb/api_order.php';
        $apikey = $supplier['apikey'];
        ee('即将创建真实数据,慎重....');
        $res = apiget($url, ['apikey'=>$apikey, 'weibouid'=>$data['url'], 'num'=>$data['task_num'], 'type'=>'d', 'first'=>$data['first'], 'starttime'=>$data['stime']]);

        if (empty($res) || $res['ret'] == 0) {
            return ['status'=>0, 'msg'=>'抱歉，系统异常，请联系管理员'];
        }

        //写入数据
        $res = $this->ToolsLogic->createOrder(['data'=>$data, 'ret'=>$ret]);

        $this->ajaxReturn($res);

    }
    

}
