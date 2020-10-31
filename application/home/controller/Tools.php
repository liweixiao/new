<?php
/**
 * 2020-07-29
 * @author 没事忙
 */
namespace app\home\controller;
use think\Controller;
use app\common\logic\ToolsLogic;
use app\common\logic\OrderLogic;
use app\common\logic\ThirdToolsLogic;

class Tools extends Base {
	/*
	 * 初始化操作
	 */
	public function _initialize() {
		parent::_initialize();
		$this->user = session('user') ?? [];
		$this->OrderLogic = new OrderLogic;
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


	//商品详情-写评论(gcdetail=goods comment detail)
	public function gcdetail(){
	    $goods_id = I('id', 0);//商品id
	    $ThirdToolsLogic = new ThirdToolsLogic;
	    $ToolsLogic  = new ToolsLogic();
	    $row = $ThirdToolsLogic->getTaskGoodsRow($goods_id, $this->user_id);
	    if (empty($row)) {
	        $this->error('非法请求');
	    }

	    //异常情况监测
	    if ($ThirdToolsLogic->setPriceRateError) {
	        $this->error('抱歉，此商品售价设置有误(错误码0012)，请联系管理员！');
	    }

	    $ToolsLogic->delFields($row, ['out_url'], 1);//剔除字段


	    //获取商品模板
	    $goodsTemplate = $row['tpl'];
	    if (empty($goodsTemplate)) {
	        $this->error('抱歉，商品模板配置有误，请联系管理员！');
	    }

	    $cat_id = $row['cat_id'];
	    $cat = $ToolsLogic->getCatRow($cat_id);
	    if (empty($cat)) {
	        $this->error('抱歉，请联系管理员！');
	    }

	    $tags = $ToolsLogic->getAllTags('', 0, false);//第三个参数为false则获取所有字段，这在模板里面要注意
	    $shop_info = tpCache('shop_info');
	    $this->assign('shop_info',$shop_info);
	    $this->assign('row', $row);
	    $this->assign('cat', $cat);
	    $this->assign('tags', $tags);
	    return $this->fetch($goodsTemplate);
	}

	//创建订单-写评论
	public function createOrder(){
	    $res = ['error'=>0, 'msg'=>'恭喜，提交成功'];
	    $cat_id = input('cat_id', 0);//分类id
	    $goods_id = input('goods_id', 0);//商品id
	    $data = input('post.');
	    // ee($data);

	    $ThirdToolsLogic = new ThirdToolsLogic;
	    $ThirdToolsLogic->orderStatusConfig = $this->OrderLogic->orderStatusConfig;//复制订单状态属性

	    //获取商品
	    $row = $this->OrderLogic->getGoodsRow($goods_id);
	    if (empty($row)) {
	        $res = ['error'=>1, 'msg'=>'非法请求'];
	        $this->ajaxReturn($res);
	    }

	    if (empty($data['url'])) {
	        $res = ['error'=>1, 'msg'=>'地址必须填写'];
	        $this->ajaxReturn($res);
	    }
	    
	    $taskNum = $data['cm_max'];

	    $userInfo = $this->OrderLogic->get_user_info($this->user_id);
	    if (empty($userInfo)) {
	        $res = ['error'=>1, 'msg'=>'抱歉，会员不存在，请联系管理员'];
	        $this->ajaxReturn($res);
	    }

	    //余额是否充足检测
	    $total_amount = $ThirdToolsLogic->budgetTotalAmount($taskNum, $data['cm_price']);//预算订单总价
	    // ee($total_amount);

	    //检查用户余额是否充足
	    if ($total_amount > $userInfo['user_money']) {
	        $res = ['error'=>1, 'msg'=>"抱歉，余额不足，当前余额为：<b>{$userInfo['user_money']}</b>"];
	        $this->ajaxReturn($res);
	    }

	    //写入数据
	    $data['user_id'] = $this->user_id;//用户id
	    $data['task_num'] = $taskNum;//下单数量
	    $res = $ThirdToolsLogic->makeOrder($data);
	    if ($res['error']) {
	        $res = ['error'=>1, 'msg'=>$res['msg']];
	        $this->ajaxReturn($res);
	    }
	    $this->ajaxReturn($res);

	}

	//获取评论详情-任务详情
	public function getTaskDetail() {
		$params = input('get.');
	    $ThirdToolsLogic = new ThirdToolsLogic;

	    $res = $ThirdToolsLogic->getTaskDetail($params);

	    if ($res['error']) {
	        $this->error($res['msg']);
	    }

	    $rows = $res['data'];
	    $this->assign('rows', $rows);
	    return $this->fetch();
	}


	//获取评论内容-用户复制
	public function getTaskCommentCopyData() {
		$params = input('post.');
	    $ThirdToolsLogic = new ThirdToolsLogic;

	    $res = $ThirdToolsLogic->getTaskCommentCopyData($params);
	    $this->ajaxReturn($res);
	}

}