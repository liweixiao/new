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
    public function _initialize() {
        parent::_initialize();
        $this->OrderLogic = new OrderLogic;
    }

    public function index() {
        return $this->fetch();
    }

    public function list() {
        $data = [];
        $params = I('get.');//请求参数
        $cat_id = $data['cat_id'] = I('cid/d', 1);//商品一级分类id
        $goods_id = $data['goods_id'] = I('gid/d', 0);//商品id
        $OrderLogic = new OrderLogic();
        $order_by = 'order_id desc';
        $where = [];
        // $isGetDetail = $cat_id && $goods_id;//如果传递(分类id+商品ID)则认为是获取详细--这里已修改,改为如果传递商品id则过滤订单状态，否则不过滤
        $data['supplier'] = [];

        //获取当前分类下面所有子分类
        $data['catList'] = $this->OrderLogic->getCatList(0);

        //获取当前分类下面所有子分类ids
        $subCatIds = $this->OrderLogic->getSubCatIds($cat_id);

        $data['goodsRows'] = $this->OrderLogic->getCatGoodsList($subCatIds);

        //增加全部商品类型
        array_unshift($data['goodsRows'], ['goods_id'=>0, 'goods_name'=>'全部']);

        $params['cat_id'] = $cat_id;
        $params['goods_id'] = $goods_id;

        //这里如果不传递商品ID，则默认是获取所有订单
        if ($goods_id) {
            //这里由于多供应商原因,所以查单子必须要提交商品id
            if (empty($goods_id)) {
                $this->error('抱歉，商品id参数缺失，请联系管理员');
            }

            //获取商品信息
            $goodsRow = $this->OrderLogic->getGoodsRow($goods_id);
            if (empty($goodsRow)) {
                return ['error'=>1, 'msg'=>'抱歉，产品不存在，请联系管理员'];
            }
            $where['goods_id'] = $goods_id;

            //获取供应商
            $supplier = $this->OrderLogic->getSupplier($goodsRow['supplier_id']);
            if (empty($supplier)) {
                $res = ['error'=>1, 'msg'=>'抱歉，系统供应配置异常，请联系管理员！!'];
                return $res;
            }
            $data['supplier'] = $supplier;
        }

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

        //根据订单状态查找
        if (!empty($params['order_status'])) {
            $order_status = $params['order_status'];
            $where['order_status'] = ['IN', $order_status];
        }

        //根据会员账号查找
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $where['mobile|order_sn|url'] = ['like', "%{$keyword}%"];
        }

        // $this->showNum = 2;
        $rows = db("v_order")->where($where)->order($order_by)->paginate($this->showNum);
        $page = $rows->render();
        // ee($rows->render());

        $rows = $rows->toArray();//转换为数组
        $rows = $rows['data'];//取数据

        foreach ($rows as $key => $row) {
            $rows[$key]['order_status_name'] = $this->OrderLogic->orderStatusConfig[$row['order_status']] ?? '';
            //状态默认显示为-不解析
            if ($row['order_status'] == 2) {
                $rows[$key]['order_status_name'] = '未解析';
            }

            //获取订单产品
            $goods = db('order_goods')->where(['order_id'=>$row['order_id']])->select();
            $rows[$key]['goods'] = db('order_goods')->where(['order_id'=>$row['order_id']])->select();

        }
        // ee($rows);

        //只有传递了商品ID才刷洗数据
        if ($goods_id) {
            $res_refresh = $OrderLogic->refreshDatasByOutOrder($rows, $goodsRow);//单条，不是批量
        }

        $tags = $OrderLogic->getAllTags('run_first');

        //获取商品订单统计(统计每个商品下单数量)
        $data['orderGoodsStat'] = $this->OrderLogic->getOrderGoodsStat($params);
        $data['orderGoodsStat'][0] = array_sum($data['orderGoodsStat']);//增加总数-不分商品id

        // ee($tags);
        $this->assign('tags', $tags);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->assign('rows', $rows);
        return $this->fetch();
    }


    /*
     * 重置会员密码
     */
    public function reCreateOrder(){
        //检查是否第三方登录用户
        $order_id = input('order_id');
        $OrderLogic = new OrderLogic();
        $res = $OrderLogic->createOrderByParams($order_id);
        
        if ($res['error']) {
            $this->ajaxReturn(['error'=>1, 'msg'=>$res['msg']]);
        }
        $this->ajaxReturn($res);

    }

}
