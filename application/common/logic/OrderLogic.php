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

/**
 * 订单逻辑定义
 * Class UsersLogic
 * @package Home\Logic
 */
class OrderLogic extends BaseLogic{

    //创建订单
    public function createOrder($params = []){
        $res = ['error'=>0, 'msg'=>'恭喜，提交成功！'];
        $ctime = date('Y-m-d H:i:s');
        $data = [];
        $request = \think\Request::instance();
        $user = session('user') ?? [];

        //会员未登录
        if (empty($user)) {
            return ['error'=>1, 'msg'=>'抱歉，请先登录'];
        }

        $userInfo = $this->get_user_info($user['user_id']);

        if (empty($userInfo)) {
            return ['error'=>1, 'msg'=>'抱歉，会员不存在，请联系管理员.'];
        }

        //如果第三方订单id为获取到
        if (empty($params['out_id'])) {
            return ['error'=>1, 'msg'=>'订单提交失败，请联系管理员'];
        }

        //可供检测重复提交数据使用
        $data['cat_id']       = $params['cat_id'] ?? 0;
        $data['user_id']      = $user_id = $user['user_id'] ?? 0;
        $data['url']          = $params['url'] ?? '';
        $data['out_id']       = $params['out_id'] ?? 0;//外部订单id??
        $data['first']        = $params['first'] ?? '';//优先级
        $data['stime']        = $params['stime'] ?? 0;//开始时间
        $data['task_num']     = $params['task_num'] ?? 0;//下单数量
        $data['supplier_id']  = $params['supplier_id'] ?? 0;//供应商id??
        $data['goods_id']     = $params['goods_id'] ?? 0;//商品id


        $row = Db::name("order")->where($data)->find();
        if ($row) {
            return ['error'=>1, 'msg'=>'请勿重复提交,您可以修改开始时间后再次提交'];
        }

        //获取商品信息
        $goodsRow = $this->getGoodsRow($data['goods_id']);
        if (empty($goodsRow)) {
            return ['error'=>1, 'msg'=>'抱歉，产品不存在，请联系管理员'];
        }

        //计算订单总价
        $total_amount = $this->getTotalAmount($data['task_num'], $goodsRow['sale_price']);
        //检查用户余额是否充足
        if ($total_amount > $userInfo['user_money']) {
            return ['error'=>1, 'msg'=>'抱歉，余额不足，请充值.'];
        }

        // 启动事务
        Db::startTrans();
        try{
            $data['order_sn'] = get_order_sn();
            $data['ctime'] = $ctime;
            $data['ip'] = $request->ip();

            //订单总价
            $data['total_amount'] = $total_amount;

            $order_id = Db::name("order")->insertGetId($data);

            //生成订单商品
            $goods['order_id']    = $order_id;
            $goods['goods_id']    = $goodsRow['goods_id'];
            $goods['goods_name']  = $goodsRow['goods_name'];
            $goods['goods_sn']    = $goodsRow['goods_sn'];
            $goods['goods_num']   = $data['task_num'];
            $goods['final_price'] = $goodsRow['sale_price'];//成交价格
            $goods['cost_price']  = $goodsRow['cost_price'];//成本价
            $goods['ctime']       = $ctime;
            $goods_id = Db::name("order_goods")->insertGetId($goods);

            //用户表相关信息记录-总消费金额变动
            M('users')->where(['user_id'=>$user_id])->setInc('total_money_use', $data['total_amount']);

            //用户动账记录&&会员现有金额变动
            accountLog($user_id, -$data['total_amount'], 0,  '用户下单', 0, $order_id);//此处会自动更新users表的user_money(用户现有资金)变动
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return ['error'=>1, 'msg'=>$e->getMessage()];
        }

        return $res;
    }


    /**
     * 获取订单列表
     * @return array $res 结果
     */
    public function getOrderList($params=[]){
        $res = [];

        $order_by = 'order_id desc';
        $where = [];

        //排序
        if (!empty($params['order_by'])) {
            $order_by = $params['order_by'];
        }

        //根据用户查找
        if (!empty($params['user_id'])) {
            $user_id = $params['user_id'];
            $where['user_id'] = $user_id;
        }

        //筛选-关键词查找
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $where['url'] = ['LIKE', "%$keyword%"];
        }

        // ee($where);
        $count = M('v_order')->where($where)->count();
        // sql();
        $page = new Page($count, $this->showNum);
        $res = M('v_order')->where($where)
                                ->order($order_by)
                                ->limit("{$page->firstRow}, {$page->listRows}")
                                ->select();
                                // sql();

        $this->page = $page;
        $this->listTotal = $count;

        if ($res) {
            foreach ($res as $key => $row) {
                //获取订单产品
                $row['goods'] = M('order_goods')->where(['order_id'=>$row['order_id']])->select();
                //通过第三方刷洗数据
                $res[$key] = $this->getOutOrderData($row);//单条，不是批量
                // $res[$key]['task_status_name'] = '暂停中';
            }
        }
        // ee($res);
        return $res;
    }


    /**
     * 第三方刷洗数据
     * @return array $res 结果
     */
    public function getOutOrderData($row=[]){
        $res = ['error'=>0, 'msg'=>'操作成功'];
        if (empty($row)) {
            return $res;
        }
        //获取供应商
        $supplier = $this->getSupplier($row['supplier_id']);
        if (empty($supplier)) {
            $res = ['error'=>1, 'msg'=>'抱歉，系统异常，请联系管理员！!'];
            return $res;
        }

        //获取商品配置
        $goodsCfg = M('goods_config')->where(['goods_id'=>$row['goods_id']])->find();
        if (empty($goodsCfg)) {
            $res = ['error'=>1, 'msg'=>'抱歉，系统异常，请您联系管理员'];
            return $res;
        }
        // ee($row);
        $url = $goodsCfg['url_get_order_row'];
        $apikey = $supplier['apikey'];
        $params = ['apikey'=>$apikey, 'renwuid'=>$row['out_id'], 'type'=>'query'];
        $res = apiget($url, $params);
        // ee($res);
        if (empty($res) || $res['ret'] != 1) {
            $res = ['error'=>1, 'msg'=>'抱歉，系统出现异常，请联系管理员'];
            return $res;
        }
        $row['task_status_name'] = $res['msg'] ?? '';
        return $row;
    }

    /**
     * 第三方设置订单
     * @return array $res 结果
     */
    public function setOrder($params=[]){
        $res = ['error'=>0, 'msg'=>'操作成功'];
        if (empty($params)) {
            $res = ['error'=>1, 'msg'=>'操作失败，参数缺失!'];
            return $res;
        }
        //检测订单id
        if (empty($params['order_id'])) {
            $res = ['error'=>1, 'msg'=>'操作失败，订单编号参数缺失!'];
            return $res;
        }

        //检测操作类型
        if (empty($params['type'])) {
            $res = ['error'=>1, 'msg'=>'操作失败，操作类型参数缺失!'];
            return $res;
        }
        $type = $params['type'];

        //类型强制检测
        if (!in_array($type, ['pause','continue','refund'])) {
            $res = ['error'=>1, 'msg'=>'操作失败，操作类型非法!'];
            return $res;
        }


        $order_id = $params['order_id'];
        $order = M('order')->where(['order_id'=>$order_id])->find();
        if (empty($order)) {
            $res = ['error'=>1, 'msg'=>'操作失败，订单不存在!'];
            return $res;
        }

        //获取供应商
        $supplier = $this->getSupplier($order['supplier_id']);
        if (empty($supplier)) {
            $res = ['error'=>1, 'msg'=>'抱歉，系统异常，请联系管理员！!'];
            return $res;
        }

        //获取商品配置
        $goodsCfg = M('goods_config')->where(['goods_id'=>$order['goods_id']])->find();
        if (empty($goodsCfg)) {
            $res = ['error'=>1, 'msg'=>'抱歉，系统异常，请您联系管理员'];
            return $res;
        }

        // ee($row);
        $url = $goodsCfg['url_set_order'];
        $apikey = $supplier['apikey'];
        $params = ['apikey'=>$apikey, 'renwuid'=>$order['out_id'], 'type'=>$type];
        $result = apiget($url, $params);
        // ee($result);

        if (empty($result) || $result['ret'] != 1) {
            $res = ['error'=>1, 'msg'=>'抱歉，系统出现异常，请联系管理员.'];
            return $res;
        }
        return $res;
    }

}