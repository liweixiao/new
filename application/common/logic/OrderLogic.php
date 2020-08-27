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

    //创建订单(逻辑改为先在自己平台下单,成功后在第三方平台下单,若第三方失败则回滚数据)
    public function createOrder($params = []){
        $res = ['error'=>0, 'msg'=>'恭喜，提交成功！'];
        $ctime = date('Y-m-d H:i:s');
        $data = [];
        $request = \think\Request::instance();
        $user_id = $params['user_id'] ?? 0;//传过来的用户id

        //会员未登录
        if (empty($user_id)) {
            return ['error'=>1, 'msg'=>'抱歉，暂未登录'];
        }

        $userInfo = $this->get_user_info($user_id);

        if (empty($userInfo)) {
            return ['error'=>1, 'msg'=>'抱歉，会员不存在，请联系管理员.'];
        }

        //获取商品信息
        $goodsRow = $this->getGoodsRow($params['goods_id']);
        if (empty($goodsRow)) {
            return ['error'=>1, 'msg'=>'抱歉，产品不存在，请联系管理员'];
        }

        //获取商品配置(第三方配置)
        $goodsCfg = db('goods_config')->where(['goods_id'=>$goodsRow['goods_id']])->find();
        if (empty($goodsCfg)) {
            return ['error'=>1, 'msg'=>'抱歉，商品配置有误，请您联系管理员'];
        }
        //检测配置-创建订单地址是否配置
        if (empty($goodsCfg['url_create_order'])) {
            return ['error'=>1, 'msg'=>'抱歉，商品配置异常，请您联系管理员!'];
        }

        //获取商品分类
        $cat = $this->getCatRow($goodsRow['cat_id']);
        if (empty($cat)) {
            return ['error'=>1, 'msg'=>'抱歉，产品分类不存在，请联系管理员！'];
        }

        //获取供应商
        $supplier = $this->getSupplier($goodsRow['supplier_id']);
        if (empty($supplier)) {
            return ['error'=>1, 'msg'=>'抱歉，系统异常，请联系管理员！'];
        }


        //可供检测重复提交数据使用
        $data['cat_id']       = $goodsRow['cat_id'] ?? 0;
        $data['user_id']      = $user_id;
        $data['url']          = $params['url'] ?? '';
        $data['out_id']       = $params['out_id'] ?? 0;//外部订单id-改为后面更新了
        $data['first']        = $params['first'] ?? '';//优先级
        $data['stime']        = $params['stime'] ?? 0;//开始时间
        $data['task_num']     = $params['task_num'] ?? 0;//下单数量
        $data['supplier_id']  = $goodsRow['supplier_id'] ?? 0;//供应商id??
        $data['goods_id']     = $params['goods_id'] ?? 0;//商品id


        $row = Db::name("order")->where($data)->find();
        if ($row) {
            return ['error'=>1, 'msg'=>'请勿重复提交,您可以修改开始时间后再次提交'];
        }

        //最终成交价
        $this->final_price = $goodsRow['sale_price'];
        //计算订单总价
        $total_amount = $this->getTotalAmount($data['task_num'], $goodsRow['goods_id'], $user_id);//注意，这里有会员价修改final_price
        //销售额为0,默认不允许下单,防止商品销售价没有填写问题
        if ($total_amount <= 0) {
            return ['error'=>1, 'msg'=>'抱歉，暂未定商品销售价，请联系管理员'];
        }
        //检查用户余额是否充足
        if ($total_amount > $userInfo['user_money']) {
            return ['error'=>1, 'msg'=>'抱歉，余额不足，请充值.'];
        }

        
        //计算订单总成本价
        $total_cost = $this->getTotalCost($data['task_num'], $goodsRow['cost_price']);

        // 启动事务
        Db::startTrans();
        try{
            $data['order_sn'] = get_order_sn();
            $data['ctime'] = $ctime;
            $data['ip'] = $request->ip();

            //订单总销售价
            $data['total_amount'] = $total_amount;

            //订单总成本价
            $data['total_cost'] = $total_cost;

            $order_id = Db::name("order")->insertGetId($data);
            if (!$order_id) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，创建订单数据失败，请联系管理员'];
            }

            //生成订单商品
            $goods['order_id']    = $order_id;
            $goods['goods_id']    = $goodsRow['goods_id'];
            $goods['goods_name']  = $goodsRow['goods_name'];
            $goods['goods_sn']    = $goodsRow['goods_sn'];
            $goods['goods_num']   = $data['task_num'];
            $goods['unit']        = $goodsRow['unit'];
            $goods['final_price'] = $this->final_price;//成交价格
            $goods['cost_price']  = $goodsRow['cost_price'];//成本价
            $goods['ctime']       = $ctime;
            $goods_id = Db::name("order_goods")->insertGetId($goods);

            //用户表相关信息记录-总消费金额变动
            $result = M('users')->where(['user_id'=>$user_id])->setInc('total_money_use', $data['total_amount']);
            if (!$result) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，更新用户消费记录失败，请联系管理员'];
            }

            //用户动账记录&&会员现有金额变动
            $result = accountLog($user_id, -$data['total_amount'], 0,  '用户下单', 0, $order_id);//此处会自动更新users表的user_money(用户现有资金)变动
            if (!$result) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，更新用户消费日志失败，请联系管理员'];
            }

            //生成第三方数据
            $url_api = $goodsCfg['url_create_order'];
            $apikey = $supplier['apikey'];
            $postdatas = ['apikey'=>$apikey, 'weibouid'=>$params['url'], 'num'=>$params['task_num'], 'type'=>$cat['cat_value'], 'first'=>$params['first'], 'starttime'=>$params['stime']];
            $res_api = apiget($url_api, $postdatas);
            // ee($res_api);

            //调试数据
            // $res_api = ['ret'=>1, 'msg'=>'下单成功，消耗余额：0.3', 'id'=>'179635'];
            if (empty($res_api) || $res_api['ret'] != 1) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，系统异常，请联系管理员'];
            }

            //更新out_id
            $updateOrder = ['out_id'=>$res_api['id']];
            $res_update = db("order")->where('order_id', $order_id)->update($updateOrder);
            if (!$res_update) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，更新订单数据失败，请联系管理员'];
            }

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();// 回滚事务
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
            $res = ['error'=>1, 'msg'=>$result['msg']];
            return $res;
        }
        return $res;
    }

}