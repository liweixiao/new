<?php
/**
 * 2020-07-29
 * @author 没事忙
 */
namespace app\home\controller;
use think\Controller;
use app\common\logic\ToolsLogic;

class Tools extends Base {
	/*
	 * 初始化操作
	 */
	public function _initialize() {
		parent::_initialize();
		$this->user = session('user') ?? [];
	}
	
	//获取用户反馈
	public function getFeedPage(){
		$ToolsLogic  = new ToolsLogic();
		$params = I('post.');
		$type = I('type/s');//反馈类型
		$tpl = I('tpl/s', 'tk_feedback');//获取模板类型

		//指定反馈模板-验证
		//TODO

		//如果是订单反馈，需要把订单sn传递到前台，便于通知
		if (!empty($params['order_id'])) {
			$order = db('order')->where(['order_id'=>$params['order_id']])->find();
			if ($order) {
				$params['order_sn'] = $order['order_sn'];
			}
		}

		$shop_info = tpCache('shop_info');
		$this->assign('shop_info',$shop_info);

		//获取标签
		$tags = $ToolsLogic->getAllTags();
		$this->assign('user',$user);
		$this->assign('type',$type);
		$this->assign('params',$params);
		$this->assign('tags',$tags);
		return $this->fetch("{$tpl}");
	}

	//提交用户反馈
	public function dofeed(){
		$data = I('post.');
	 	$ToolsLogic  = new ToolsLogic();
	 	$res = $ToolsLogic->addUserFeedback($data);

	    $this->ajaxReturn($res);
	}

}