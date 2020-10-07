<?php

/**
 *  
 * @file   LogController.php  
 * @date   2016-10-9 18:23:24 
 * @author Zhenxun Du<5552123@qq.com>  
 * @version    SVN:$Id:$ 
 */

namespace app\admin\controller;
use think\Page;
use think\Db;
use app\common\logic\OrderLogic;
use app\common\logic\FeedBackLogic;

class Feedback extends Base {
    public function _initialize() {
        parent::_initialize();
        $this->OrderLogic = new OrderLogic;
        $this->FeedBackLogic = new FeedBackLogic;
    }

    public function index() {
        $data = [];
        $params = I('get.');//请求参数
        $OrderLogic = new OrderLogic();
        $order_by = 'feedback_id desc';
        $where = [];

        //排序
        if (!empty($params['order_by'])) {
            $order_by = $params['order_by'];
        }

        //根据状态查找
        if (!empty($params['state'])) {
            $state = $params['state'];
            $where['state'] = $state;
        }

        //根据订单号查找
        if (!empty($params['order_sn'])) {
            $order_sn = $params['order_sn'];
            $where['order_sn'] = $order_sn;
        }

        //根据会员账号查找
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $where['desc|mobile|order_sn'] = ['like', "%{$keyword}%"];
        }

        $rows = db("v_feedback")->where($where)->order($order_by)->paginate($this->showNum);
        $page = $rows->render();

        $rows = $rows->toArray();//转换为数组
        $rows = $rows['data'];//取数据

        foreach ($rows as $key => $row) {
            $rows[$key]['state_name'] = $this->FeedBackLogic->feedbackState[$row['state']] ?? '';
        }
        // ee($rows);

        $tags = $OrderLogic->getAllTags();

        //订单状态
        $feedbackState = $this->FeedBackLogic->feedbackState;
        $this->assign('feedbackState', $feedbackState);

        // ee($tags);
        $this->assign('tags', $tags);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->assign('rows', $rows);
        return $this->fetch();
    }


    /**
     * 添加修改订单
     */
    public function info(){
        $id = I('feedback_id');
        $OrderLogic = new OrderLogic();
        $FeedBackLogic = new FeedBackLogic();
        if (IS_POST) {
            $data = I('post.');
            // ee($data);
            $ctime = time();

            if ($id) {
                $data['mtime'] = $ctime;
                $res = db('feedback')->where(['feedback_id'=>$id])->update($data);

            } else {
                $data['ctime'] = $ctime;
                $res = db('feedback')->insert($data);
            }

            if (!$res) {
                $this->error('操作失败');
            }
            $this->success('操作成功', url('feedback/index'));
        }

        //订单状态
        $feedbackState = $FeedBackLogic->feedbackState;

        $row = db('v_feedback')->where(['feedback_id'=>$id])->find();

        $tags = $OrderLogic->getAllTags();

        // ee($row);
        $this->assign('tags', $tags);
        $this->assign('row', $row);
        $this->assign('feedbackState', $feedbackState);
        return $this->fetch();
    }


}
