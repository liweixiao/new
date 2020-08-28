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
        $params = I('get.');//请求参数
        $OrderLogic = new OrderLogic();

        $order_by = 'order_id desc';
        $where = [];

        //排序
        if (!empty($params['order_by'])) {
            $order_by = $params['order_by'];
        }

        //根据订单号查找
        if (!empty($params['order_sn'])) {
            $order_sn = $params['order_sn'];
            $where['order_sn'] = $order_sn;
        }

        //根据优先级查找
        if (!empty($params['first'])) {
            $first = $params['first'];
            $where['first'] = $first;
        }

        //根据会员账号查找
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $where['mobile|order_sn|url'] = ['like', "%{$keyword}%"];
        }


        // $this->showNum = 2;
        $rows = db("v_order")->where($where)->order($order_by)->paginate($this->showNum);
        // ee($rows);

        // ee($rows->render());
        foreach ($rows as $key => $row) {
            //获取订单产品
            $row['goods'] = db('order_goods')->where(['order_id'=>$row['order_id']])->select();
            $rows[$key]['task_status_name'] = '更新中';
            //通过第三方刷洗数据
            $goodsCfg = db('goods_config')->where(['goods_config_id'=>$row['goods_config_id']])->find();//获取商品配置
            if (!empty($goodsCfg)) {
                $refreshRes = $OrderLogic->getOutOrderData($row, $goodsCfg);//单条，不是批量
                //如果刷新成功则修改
                if (!$refreshRes['error']) {
                    $rows[$key] = $refreshRes['data'];
                }
            }
        }

        $tags = $OrderLogic->getAllTags('run_first');

        // ee($tags);
        $this->assign('tags', $tags);
        $this->assign('rows', $rows);
        return $this->fetch();
    }

}
