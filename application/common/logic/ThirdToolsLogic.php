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
 * 第三方工具类(aiyuntui)
 * Class UsersLogic
 * @package Home\Logic
 */
class ThirdToolsLogic extends BaseLogic{
    public $orderStatusConfig = [];//注意,这里不用重复写,这个值由此类实例化后从OrderLogic的属性orderStatusConfig复制过来！

    //预算总价
    public function budgetTotalAmount($num=0, $price=0){
        $res = 0;//返回销售总价、会员真实购买价
        if (empty($num)) {
            return $res;
        }

        if (empty($price)) {
            return $res;
        }

        //任务数量
        $num = floatval($num);
        $price = floatval($price);

        $resPrice = $num*$price;

        $res = fnum($resPrice, 0, 2);
        return $res;
    }

    //创建订单-评论
    public function makeOrder($params = []){
        $res = ['error'=>0, 'msg'=>'恭喜，提交成功！', 'data'=>[]];
        vendor('my.Guestinfo');
        $Guestinfo = new \Guestinfo();
        $ctime = date('Y-m-d H:i:s');
        $data = [];
        $request = \think\Request::instance();
        $user_id = $params['user_id'] ?? 0;//传过来的用户id
        $this->orderId = 0;//创建订单id

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
        $goodsCfg = db('goods_config')->where(['goods_config_id'=>$goodsRow['goods_config_id']])->find();
        if (empty($goodsCfg)) {
            return ['error'=>1, 'msg'=>'抱歉，商品配置暂时有误，请您联系管理员'];
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
            return ['error'=>1, 'msg'=>'抱歉，此商品相关设置暂未配置，请联系管理员！'];
        }

        //判断设置单价是否低于成本价
        $cost_price = fnum($goodsRow['cost_price'], 0, 4);
        if ($params['cm_price'] < $cost_price) {
            return ['error'=>1, 'msg'=>"抱歉，设置单价不能低于:{$cost_price}！"];
        }


        //可供检测重复提交数据使用
        $data['cat_id']          = $goodsRow['cat_id'] ?? 0;
        $data['user_id']         = $user_id;
        $data['url']             = $params['url'] ?? '';
        $data['out_id']          = $params['out_id'] ?? 0;//外部订单id-改为后面更新了
        $data['first']           = $params['first'] ?? '';//优先级
        $data['user_note']       = $params['user_note'] ?? '';//备注
        $data['task_num']        = $params['task_num'] ?? 0;//下单数量
        $data['supplier_id']     = $goodsRow['supplier_id'];//供应商id??
        $data['goods_config_id'] = $goodsRow['goods_config_id'];//商品配置id
        $data['goods_id']        = $params['goods_id'] ?? 0;//商品id

        $row = Db::name("order")->where($data)->find();
        if ($row) {
            return ['error'=>1, 'msg'=>'请勿重复提交,您可以修改一些参数后再次提交'];
        }

        //最终成交价
        $this->final_price = $params['cm_price'];

        //计算订单总价
        $total_amount = $this->budgetTotalAmount($params['task_num'], $params['cm_price']);//TODO这里价格需要收取平台费
        // ee($total_amount);
        //销售额为0,默认不允许下单
        if ($total_amount <= 0) {
            return ['error'=>1, 'msg'=>'抱歉，订单总价不能为空，请联系管理员'];
        }

        //检查用户余额是否充足
        if ($total_amount > $userInfo['user_money']) {
            return ['error'=>1, 'msg'=>'抱歉，余额不足，请充值.'];
        }
        
        //计算订单总成本价
        $total_cost = $this->getTotalCost($data['task_num'], $data['cm_price']);

        // 启动事务
        Db::startTrans();
        try{
            $data['order_sn'] = $order_sn = get_order_sn();
            $data['ctime'] = $ctime;
            $data['ip'] = $request->ip();

            //设备信息
            $data['user_system'] = $Guestinfo->equipmentSystem();//设备
            $data['user_browser'] = $Guestinfo->getUserBrowser();//浏览器

            //订单总销售价
            $data['total_amount'] = $total_amount;

            //订单总成本价
            $data['total_cost'] = $total_cost;

            //订单默认状态名
            $data['order_status'] = 2;//默认是待处理状态

            //生成订单基本信息
            // ee($data);
            $order_id = Db::name('order')->insertGetId($data);
            $res['data']['order_id'] = $order_id;//返回订单编号
            $this->orderId = $order_id;

            if (!$order_id) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，创建订单数据失败，请联系管理员'];
            }

            //生成订单商品
            $goods['order_id']        = $order_id;
            $goods['goods_id']        = $goodsRow['goods_id'];
            $goods['goods_name']      = $goodsRow['goods_name'];
            $goods['goods_sn']        = $goodsRow['goods_sn'];
            $goods['goods_num']       = $data['task_num'];
            $goods['unit']            = $goodsRow['unit'];
            $goods['final_price']     = $this->final_price;//成交价格
            $goods['cost_price']      = $goodsRow['cost_price'];//成本价
            $goods['goods_config_id'] = $goodsRow['goods_config_id'];//商品配置id
            
            $goods['ctime']           = $ctime;
            // ee($goods);
            $goods_id = Db::name('order_goods')->insertGetId($goods);

            //用户表相关信息记录-总消费金额变动
            $result = db('users')->where(['user_id'=>$user_id])->setInc('total_money_use', $total_amount);
            if (!$result) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，更新用户消费记录失败，请联系管理员'];
            }

            //用户动账记录&&会员现有金额变动
            $result = accountLog($user_id, -$total_amount, 0,  '用户下单', 0, $order_id, $order_sn);//此处会自动更新users表的user_money(用户现有资金)变动
            if (!$result) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，更新用户消费日志失败，请联系管理员'];
            }

            //生成第三方数据
            $create_res = $this->createThirdOrderBySupplier($supplier, $goodsCfg, $params, $cat, $goodsRow);
            if ($create_res['error']) {
                //注意这里分为两种情况:1.api报错,2.平台报错-非法，如果是2则直接回滚数据
                //api返回错误情况
                if ($create_res['error'] == 2) {
                    ///注意这里只记录余额不足情况
                    //检测余额是否充足
                    $apiMoney = $this->getApiMoneyBySupplier($supplier['supplier_id']);
                    //未获取到情况
                    if ($apiMoney == -999) {
                        Db::rollback();// 回滚事务
                        return ['error'=>1, 'msg'=>'抱歉，更新订单状态时候出错(错误码009)，请联系管理员'];
                    }

                    // ee($apiMoney);
                    if ($apiMoney < $total_cost) {
                        //生成一条异常订单(admin_note为余额不足)
                        $admin_note = "余额{$apiMoney}，订单额{$total_amount}";
                        $updateOrderStatus = ['order_status'=>5, 'admin_note'=>$admin_note];
                        $res_update = db("order")->where('order_id', $order_id)->update($updateOrderStatus);

                        //在订单扩展表里面新增用户提交数据记录-post_params_api
                        $whereExtend = ['order_id'=>$order_id];
                        //这里有可能order_extend已经创建好了
                        $extend_row = Db::name("order_extend")->where($whereExtend)->find();

                        $extend                    = [];
                        $extend['order_id']        = $order_id;
                        $extend['post_params']     = $params;
                        $extend['post_params_api'] = $create_res['post_params_api'];
                        $extend['ctime']           = $ctime;
                        if ($extend_row) {
                            $res_extend = Db::name("order_extend")->where($whereExtend)->update($extend);
                        }else{
                            $res_extend = Db::name("order_extend")->insertGetId($extend);
                        }

                        if (!$res_extend) {
                            Db::rollback();// 回滚事务
                            return ['error'=>1, 'msg'=>'抱歉，更新订单状态时候出错，请联系管理员'];
                        }

                        // 提交事务
                        Db::commit();
                        return $res;
                    }

                    //到这里是未知错误
                    Db::rollback();// 回滚事务
                    return ['error'=>1, 'msg'=>'抱歉，更新订单状态时候出错(错误码008)，请联系管理员'];
                }else{
                    //平台异常
                    Db::rollback();// 回滚事务
                    return ['error'=>1, 'msg'=>$create_res['msg']];
                }


            }

            //更新out_id
            $updateOrder = ['out_id'=>$create_res['data']['out_id'], 'order_status'=>3];
            $res_update = db("order")->where('order_id', $order_id)->update($updateOrder);
            // sql();
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
     * 创建订单(根据供应商不同而不同)
     * @return array $res 结果
     */
    public function createThirdOrderBySupplier($supplier, $goodsCfg=[], $params=[], $cat=[], $goodsRow=[]){
        $res = ['error'=>0, 'msg'=>'获取成功！', 'data'=>[], 'res_api'=>[], 'post_params_api'=>''];
        if (empty($supplier)) {
            return ['error'=>1, 'msg'=>'抱歉，配置有误，无法获取数据，请联系管理员'];
        }
        $ctime = date('Y-m-d H:i:s');
        $postdatas = [];
        $cat_id = $cat['cat_id'] ?? 0;
        switch ($supplier['code']) {
            case '40000':

                //设置headers
                $headers = [
                    'Content-Type:application/json; charset=UTF-8',
                ];
                $apiUserinfoRes = $this->getSupplierToken($supplier);//获取用户信息,余额、评论单价等

                //获取token异常情况
                if ($apiUserinfoRes['error']) {
                    return ['error'=>1, 'msg'=>$apiUserinfoRes['msg']];
                }

                $this->apiUserinfo = $apiUserinfo = $apiUserinfoRes['data'];//返回用户信息,余额、评论单价等

                //判断设置单价是否低于成本价
                $cost_price = $apiUserinfo['useprice'];//再次校验,根据api数据
                if ($params['cm_price'] < $cost_price) {
                    return ['error'=>1, 'msg'=>"抱歉，设置单价不能低于:{$cost_price}！"];
                }

                $url_api = $goodsCfg['url_create_order'];
                //提交参数
                $postdatas = [
                    'uid'      => $apiUserinfo['uid'],//用户id
                    'usersign' => $apiUserinfo['usersign'],//用户签名 
                    'title'    => $params['cm_title'], //任务标题
                    'addr'     => $params['url'], //任务地址
                    'descr'    => $params['user_note'], //任务要求
                    'sens'     => $params['cm_sens'], //敏感词，多个词中间分号隔开,最多100字
                    'face'     => implode(';', $params['cm_face']), //评论方向(赞美、中性、询问、调侃、吐槽) 中间分号隔开
                    'max'      => $params['cm_max'], //最大评论量
                    'minchar'  => $params['cm_minchar'], //每条评论最小字数
                    'level'    => 0, //是否刷量(0，正常；1，刷量) 
                    'price'    => $params['cm_price']*100, //每条评论的单价(单位分),TODO这里需要根据用户填写价格做适量相对计算
                ];
                ee($postdatas);

                $postdatasJson = json_encode($postdatas);
                $res_api = apiget($url_api, $postdatasJson, 'post', [], $headers);
                ee($res_api);
                //异常情况
                if (empty($res_api) || empty($res_api['code']) || $res_api['code'] != 0) {
                    $msg = $res_api['msg'] ?? "抱歉，创建任务出现异常，请联系管理员";
                    return ['error'=>2, 'msg'=>$msg, 'post_params_api'=>json_encode($postdatas)];
                }

                $res['res_api'] = $res_api;
                $res['data']['out_id'] = $res_api['result']['taskid'];
                break;
            
            default:
                return ['error'=>1, 'msg'=>'抱歉，配置未完善，暂时无法创建订单，请联系管理员'];
                break;
        }

        return $res;
    }


}