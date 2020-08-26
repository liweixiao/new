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

class Order extends Common {

    public function index() {
        return $this->fetch();
    }

    public function list() {
        $OrderLogic = new OrderLogic();
        $where = [];
        // $this->showNum = 2;
        $rows = db("v_order")->where($where)->order('order_id desc')->paginate($this->showNum);
        // ee($rows->render());
        foreach ($rows as $key => $row) {
            //获取订单产品
            $row['goods'] = db('order_goods')->where(['order_id'=>$row['order_id']])->select();
            //通过第三方刷洗数据
            $rows[$key] = $OrderLogic->getOutOrderData($row);//单条，不是批量
        }

        $tags = $OrderLogic->getAllTags('run_first');

        // ee($tags);
        $this->assign('tags', $tags);
        $this->assign('rows', $rows);
        return $this->fetch();
    }

}
