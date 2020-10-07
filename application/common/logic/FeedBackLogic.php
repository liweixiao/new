<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 没事忙，并保留所有权利。
 * 网站地址: http://www.xxxxx.cn
 * ----------------------------------------------------------------------------
 * Author: 没事忙
 * Date: 2015-09-09
 */

namespace app\common\logic;

use think\Loader;
use think\Model;
use think\Page;
use think\Db;
use app\common\logic\BaseLogic;
use think\Image;

/**
 * 用户反馈逻辑定义
 * Class UsersLogic
 * @package Home\Logic
 */
class FeedBackLogic extends BaseLogic{
    public $feedbackState = ['1'=>'已处理', '2'=>'待处理', '3'=>'已拒绝'];

    /**
     * 用户反馈记录
     * @param type $params
     * @param type $p
     * @return type
     */
    public function getUserFeedbackList($params=[])
    {
        $res = [];
        $order_by = 'feedback_id desc';
        $where = [];
        $where['is_show'] = 1;

        //排序
        if (!empty($params['order_by'])) {
            $order_by = $params['order_by'];
        }

        //根据用户查找
        if (empty($params['user_id'])) {
            return $res;//强制验证
        }

        $user_id = $params['user_id'];
        $where['user_id'] = $user_id;

        // ee($where);
        $count = db('v_feedback')->where($where)->count();
        // sql();
        $page = new Page($count, $this->showNum);
        $res = db('v_feedback')->where($where)
                                ->order($order_by)
                                ->limit("{$page->firstRow}, {$page->listRows}")
                                ->select();
                                // sql();

        foreach ($res as $key => $row) {
            $res[$key]['state_name'] = $this->feedbackState[$row['state']] ?? '';
        }
        $this->page = $page;
        $this->listTotal = $count;
        // ee($res);
        return $res;
    }
}