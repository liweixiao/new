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
    //5=api余额不足
    public $orderStatusConfig = ['1'=>'已完成', '2'=>'待处理', '3'=>'处理中', '4'=>'暂停中', '5'=>'余额不足', '6'=>'已退款', '7'=>'已作废', '8'=>'手工单'];
    //创建订单(逻辑改为先在自己平台下单,成功后在第三方平台下单,若第三方失败则回滚数据)
    public function createOrder($params = []){
        $res = ['error'=>0, 'msg'=>'恭喜，提交成功！'];
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

        if (!empty($params['stime'])) {
            $data['stime']  = $params['stime'];//开始时间
        }

        $row = Db::name("order")->where($data)->find();
        if ($row) {
            return ['error'=>1, 'msg'=>'请勿重复提交,您可以修改一些参数后再次提交'];
        }

        //最终成交价
        $this->final_price = $goodsRow['sale_price'];
        //计算订单总价
        // ee($this->price_param);
        $total_amount = $this->getTotalAmount($data['task_num'], $goodsRow['goods_id'], $user_id);//注意，这里有会员价修改final_price
        // ee($total_amount);
        //销售额为0,默认不允许下单,防止商品销售价没有填写问题
        if ($total_amount <= 0) {
            return ['error'=>1, 'msg'=>'抱歉，暂未定商品销售价，请联系管理员'];
        }
        //检查用户余额是否充足
        if ($total_amount > $userInfo['user_money']) {
            return ['error'=>1, 'msg'=>'抱歉，余额不足，请充值.'];
        }
        
        //计算订单总成本价
        $total_cost = $this->getTotalCost($data['task_num'], $goodsRow['goods_id']);

        // 启动事务
        Db::startTrans();
        try{
            $data['order_sn'] = $order_sn = get_order_sn();
            $data['ctime'] = $ctime;
            $data['ip'] = $request->ip();

            //订单总销售价
            $data['total_amount'] = $total_amount;

            //订单总成本价
            $data['total_cost'] = $total_cost;

            //订单默认状态名
            $data['order_status'] = 2;//默认是待处理状态

            //生成订单基本信息
            // ee($data);
            $order_id = Db::name("order")->insertGetId($data);
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
            $goods_id = Db::name("order_goods")->insertGetId($goods);

            //用户表相关信息记录-总消费金额变动
            $result = db('users')->where(['user_id'=>$user_id])->setInc('total_money_use', $data['total_amount']);
            if (!$result) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，更新用户消费记录失败，请联系管理员'];
            }

            //用户动账记录&&会员现有金额变动
            $result = accountLog($user_id, -$data['total_amount'], 0,  '用户下单', 0, $order_id, $order_sn);//此处会自动更新users表的user_money(用户现有资金)变动
            if (!$result) {
                Db::rollback();// 回滚事务
                return ['error'=>1, 'msg'=>'抱歉，更新用户消费日志失败，请联系管理员'];
            }

            //如果是手工单-到这里更新订单状态后直接提交即可
            if ($goodsRow['is_auto'] == 0) {
                //更新out_id
                $updateOrder = ['order_status'=>8];
                $res_update = db("order")->where('order_id', $order_id)->update($updateOrder);
                if (!$res_update) {
                    Db::rollback();// 回滚事务
                    return ['error'=>1, 'msg'=>'抱歉，更新订单数据失败，请联系管理员'];
                }

                Db::commit();// 提交事务
                return $res;
            }

            //生成第三方数据
            $create_res = $this->createOrderBySupplier($supplier, $goodsCfg, $params, $cat, $goodsRow);
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
                    if ($apiMoney < $total_amount) {
                        //生成一条异常订单(admin_note为余额不足)
                        $admin_note = "当前账户余额{$apiMoney}，用户下单消耗金额{$total_amount}，尽快充值";
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

            //调试数据
            // $res_api = ['ret'=>1, 'msg'=>'下单成功，消耗余额：0.3', 'id'=>'179635'];//supplier_id=1
            // $out_api_res = ['success'=>1, 'message'=>'任务创建成功', 'taskId'=>'3867650', 'followers_count'=>'26'];
            // $res_api = ['error'=>0, 'msg'=>'获取成功！', 'api_res'=>$out_api_res, 'data'=>['out_id'=>3867650]];//supplier_id=2

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
     * 获取api余额
     * @return array $res 结果
     */
    public function getApiMoneyBySupplier($supplier_id=0){
        $res = -999;//这里应该是默认未获取到余额
        if (empty($supplier_id)) {
            return $res;
        }

        //获取供应商
        $supplier = $this->getSupplier($supplier_id);
        if (empty($supplier)) {
            return $res;
        }

        switch ($supplier['code']) {
            case '10000':
                $url_api = $supplier['url'] . $supplier['url_money'];
                $apikey = $supplier['apikey'];
                $params = ['apikey'=>$apikey, 'renwuid'=>100, 'type'=>'balance'];//提交参数
                $res_api = apiget($url_api, $params);
                //异常情况
                if (empty($res_api) || empty($res_api['ret']) || $res_api['ret'] != 1) {
                    return $res;
                }
                if (isset($res_api['msg'])) {
                    $res = $res_api['msg'];
                }
                break;

            case '20000':
                $apikey = $supplier['apikey'];
                $url_api = $supplier['url'] . $supplier['url_money'] . $apikey;
                $res_api = apiget($url_api);
                //异常情况
                if (empty($res_api) || empty($res_api['success']) || !$res_api['success']) {
                    return $res;
                }

                if (isset($res_api['balance'])) {
                    $res = floatval($res_api['balance']);
                    $res = $res/100;//这里他这里单位是分
                }
                break;

            case '30000':
                //这种情况是下单的时候已经登录了，查询过余额了
                if (!empty($this->apiMoney)) {
                    $res = $this->apiMoney;//注意，这里是在类调用getSupplierToken()方法的时候，此类属性(apiMoney)已经生成
                }else{
                    $res_token = $this->getSupplierToken($supplier);//这里通过获取token即可知道余额
                    $res = $this->apiMoney;
                }
                break;
            
            default:
                # code...
                break;
        }

        $res = floatval($res);
        return $res;
    }

    /**
     * 创建订单(根据供应商不同而不同)
     * @return array $res 结果
     */
    public function createOrderBySupplier($supplier, $goodsCfg=[], $params=[], $cat=[], $goodsRow=[]){
        $res = ['error'=>0, 'msg'=>'获取成功！', 'data'=>[], 'res_api'=>[], 'post_params_api'=>''];
        if (empty($supplier)) {
            return ['error'=>1, 'msg'=>'抱歉，配置有误，无法获取数据，请联系管理员'];
        }
        $ctime = date('Y-m-d H:i:s');
        $postdatas = [];
        $cat_id = $cat['cat_id'] ?? 0;
        switch ($supplier['code']) {
            case '10000':
                $url_api = $goodsCfg['url_create_order'];
                $apikey = $supplier['apikey'];
                $postdatas = ['apikey'=>$apikey, 'weibouid'=>$params['url'], 'num'=>$params['task_num'], 'type'=>$cat['cat_value'], 'first'=>$params['first'], 'starttime'=>$params['stime']];
                $res_api = apiget($url_api, $postdatas);

                //异常情况
                if (empty($res_api) || empty($res_api['ret']) || $res_api['ret'] != 1) {
                    $msg = $res_api['msg'] ?? "抱歉，创建任务出现异常，请联系管理员";
                    return ['error'=>2, 'msg'=>$msg, 'post_params_api'=>json_encode($postdatas)];
                }

                $res['res_api'] = $res_api;
                $res['data']['out_id'] = $res_api['id'];
                break;
            
            case '20000':
                $url_api = $goodsCfg['url_create_order'];

                //这里提交参数根据二级分类有差异的
                if (in_array($cat_id, [8])) {
                    $return_id_field = 'taskId';//返回任务字段名字
                    $postdatas = ['uri'=>$params['url'],
                         'count'=>$params['task_num'], 
                         'speed'=>$params['first'], 
                         'bfType'=>$params['bfType']
                     ];
                }elseif (in_array($cat_id, [9])) {
                    $return_id_field = 'id';//返回任务字段名字
                    ///创建订单扩展表数据
                    $order_extend_data = [
                        'order_id'=> $this->orderId,
                        'relay_type_id'=> $params['relay_type_id'],
                        'content_type_id'=> $params['content_type_id'],
                        'appoint_content'=> $params['appoint_content'],
                        'content_get_type_id'=> $params['content_get_type_id'],
                        'ctime'=> $ctime,
                    ];
                    $res_order_extend = db('order_extend')->insert($order_extend_data);
                    if (!$res_order_extend) {
                        return ['error'=>1, 'msg'=>'抱歉，订单扩展数据创建失败，请联系管理员'];
                    }


                    ///组装提交api数据参数
                    $vtags = $this->getIdValueTags();//获取id和value对应的标签数据
                    //异常情况
                    if (!isset($vtags[$params['relay_type_id']])) {
                        return ['error'=>1, 'msg'=>'抱歉，商品分类配置有误(1)，无法操作，请联系管理员'];
                    }
                    if (!isset($vtags[$params['content_type_id']])) {
                        return ['error'=>1, 'msg'=>'抱歉，商品分类配置有误(2)，无法操作，请联系管理员'];
                    }
                    if (!isset($vtags[$params['content_get_type_id']])) {
                        return ['error'=>1, 'msg'=>'抱歉，商品分类配置有误(3)，无法操作，请联系管理员'];
                    }

                    $relay_type = $vtags[$params['relay_type_id']];
                    $contentType = $vtags[$params['content_type_id']];
                    $content_get_type = $vtags[$params['content_get_type_id']];
                    $appoint_content = explode(PHP_EOL, $params['appoint_content']);//将textarea转为数组
                    $postdatas = [
                        'uri'         => $params['url'],
                        'type'        => $cat['cat_value'],//string advRelay(精品转评) vipRelay(达人转评)
                        'count'       => $params['task_num'],//integer 要加粉的数目
                        'speed'       => $params['first'],//integer 转评速度, 默认 4 最大 20
                        'relay_type'  => $relay_type,//integer 0纯转发 1转发同时评论给作者 2纯评论 3评论 同时转发到我的微博
                        'contentType' => $contentType,//integer 内容类型:   1 关闭内容   2 使用自己提交的内容   3 使用平台内容
                        'appoint'     => $appoint_content,//array 评论内容列表, 是要给包含了文本内容的数组
                        'rnd'         => $content_get_type,//integer 1 随机拾取内容, 2 顺序拾取内容
                     ];
                }else{
                    //不在分类直接异常
                    return ['error'=>1, 'msg'=>'抱歉，商品分类配置有误，暂时无法创建订单，请联系管理员增加配置'];
                }
                // ee($postdatas);

                // $postdatas = json_encode($postdatas);//注意这里不用解析成json否则报错
                $res_api = apiget($url_api, $postdatas);
                // ee($res_api);

                //添加api日志
                $this->add_out_api_log(['order_id'=>$this->orderId, 'desc'=>ecodejson($res_api)]);

                //异常情况
                if (empty($res_api) || empty($res_api['success']) || !$res_api['success']) {
                    $msg = $res_api['message'] ?? '抱歉，创建任务时出现异常，请联系管理员';
                    return ['error'=>2, 'msg'=>$msg, 'post_params_api'=>json_encode($postdatas)];
                }
                $res['res_api'] = $res_api;
                $res['data']['out_id'] = $res_api[$return_id_field];//注意这里创建订单成功后，加粉8(taskId)和转评返回的任务(id)字段名是不一样的
                break;
            case '30000':
                $url_api = $goodsCfg['url_create_order'];
                $postdatas = [
                    'parameters'=>['url'=>$params['url']], 
                    'number'=>$params['task_num'], 
                    'goods_id'=>$goodsRow['goods_id_out']
                ];

                ///设置header头 start
                $res_headers = $this->getSupplierHeaderAll($supplier);//设置header头，注意，只有这一步在先，才会有对象属性apiToken！！后续依赖性
                //获取token异常情况
                if ($res_headers['error']) {
                    return ['error'=>1, 'msg'=>$res_headers['msg']];
                }
                $headers = $res_headers['data'];
                // ee($postdatas);
                //设置header end

                $postdatasJson = json_encode($postdatas);
                $res_api = apiget($url_api, $postdatasJson, 'post', [], $headers);
                // ee($res_api);

                //异常情况
                if (empty($res_api) || !isset($res_api['error_code']) || $res_api['error_code'] != 0) {
                    $msg = $res_api['error_msg'] ?? "抱歉，创建任务出现异常(错误码003)，请联系管理员";
                    return ['error'=>2, 'msg'=>$msg, 'post_params_api'=>$postdatasJson];
                }

                if (empty($res_api['data']['order_ids'][0])) {
                    $msg = $res_api['error_msg'] ?? "抱歉，创建任务出现异常(错误码005)，请联系管理员";
                    return ['error'=>2, 'msg'=>$msg, 'post_params_api'=>$postdatasJson];
                }

                $res['res_api'] = $res_api;
                $res['data']['out_id'] = $res_api['data']['order_ids'][0];
                break;
            default:
                return ['error'=>1, 'msg'=>'抱歉，配置未完善，暂时无法创建订单，请联系管理员'];
                break;
        }

        return $res;
    }



    /**
     * 创建订单(根据用户已经提交过的参数-已经生成post参数了即order_extend字段post_params_api值)
     * @return array $res 结果
     */
    public function createOrderByParams($order_id=0){
        $res = ['error'=>0, 'msg'=>'获取成功！'];
        if (empty($order_id)) {
            return ['error'=>1, 'msg'=>'抱歉，订单id不能为空'];
        }
        $ctime = date('Y-m-d H:i:s');

        $where['order_id'] = $order_id;
        $order = db('order')->where($where)->find();
        if (empty($order)) {
            return ['error'=>1, 'msg'=>'抱歉，订单不存在'];
        }

        //检测订单状态是否符合下单
        $order_status_name = $this->orderStatusConfig[$order['order_status']] ?? '';
        if ($order['order_status'] != 5) {
            return ['error'=>1, 'msg'=>"抱歉，当前订单状态不允许操作,状态为【{$order_status_name}】"];
        }

        //订单扩展
        $order_extend = db('order_extend')->where($where)->find();
        if (empty($order_extend)) {
            return ['error'=>1, 'msg'=>'抱歉，订单扩展数据不存在,无法完成操作'];
        }
        //用户提交api参数
        $post_params_api = $order_extend['post_params_api'];
        $post_params_api = json_decode(htmlspecialchars_decode($post_params_api), true);

        //获取商品信息
        $goodsRow = $this->getGoodsRow($order['goods_id']);
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

        //获取供应商
        $supplier = $this->getSupplier($goodsRow['supplier_id']);
        if (empty($supplier)) {
            return ['error'=>1, 'msg'=>'抱歉，系统异常，请联系管理员！'];
        }

        $postdatas = [];
        $out_id = 0;//api订单id
        $cat_id = $goodsRow['cat_id'] ?? 0;
        switch ($supplier['code']) {
            case '10000':
                $url_api = $goodsCfg['url_create_order'];
                $apikey = $supplier['apikey'];
                $res_api = apiget($url_api, $post_params_api);

                //异常情况
                if (empty($res_api) || empty($res_api['ret']) || $res_api['ret'] != 1) {
                    $msg = $res_api['msg'] ?? "抱歉，创建任务出现异常，请联系管理员";
                    return ['error'=>2, 'msg'=>$msg];
                }

                if (empty($res_api['id'])) {
                    return ['error'=>1, 'msg'=>'抱歉，api订单id获取失败.，请联系管理员'];
                }

                $out_id = $res_api['id'];
                break;
            
            case '20000':
                $url_api = $goodsCfg['url_create_order'];

                //这里提交参数根据二级分类有差异的
                if (in_array($cat_id, [8])) {
                    $return_id_field = 'taskId';//返回任务字段名字
                }elseif (in_array($cat_id, [9])) {
                    $return_id_field = 'id';//返回任务字段名字
                }else{
                    //不在分类直接异常
                    return ['error'=>1, 'msg'=>'抱歉，商品分类配置有误！，暂时无法创建订单，请联系管理员增加配置！'];
                }

                // $postdatas = json_encode($postdatas);//注意这里不用解析成json否则报错
                // ee($post_params_api);
                $res_api = apiget($url_api, $post_params_api);
                // ee($res_api);

                //异常情况
                if (empty($res_api) || empty($res_api['success']) || !$res_api['success']) {
                    $msg = $res_api['message'] ?? '抱歉，创建任务时出现异常(可能情况：余额不足)，请联系管理员';
                    return ['error'=>2, 'msg'=>$msg];
                }

                if (empty($res_api[$return_id_field])) {
                    return ['error'=>1, 'msg'=>'抱歉，api订单id获取失败！，请联系管理员'];
                }

                $out_id = $res_api[$return_id_field];
                break;
            default:
                return ['error'=>1, 'msg'=>'抱歉，配置未完善，暂时无法创建订单！请联系管理员'];
                break;
        }

        if (empty($out_id)) {
            return ['error'=>1, 'msg'=>'抱歉，api订单id未获取到，无法创建订单！，请联系管理员'];
        }

        //到这里基本上订单id已经有了
        //更新out_id
        $updateOrder = ['out_id'=>$out_id, 'order_status'=>3];
        $res_update = db("order")->where('order_id', $order_id)->update($updateOrder);
        // sql();
        if (!$res_update) {
            return ['error'=>1, 'msg'=>'抱歉，更新订单数据失败!，请联系管理员。'];
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

        //这里由于多供应商原因,所以查单子必须要提交商品id
        if (empty($params['goods_id'])) {
            return ['error'=>1, 'msg'=>'抱歉，商品id参数缺失，请联系管理员'];
        }
        $goods_id = $params['goods_id'];
        $where['goods_id'] = $goods_id;

        //获取商品信息
        $goodsRow = $this->getGoodsRow($goods_id);
        if (empty($goodsRow)) {
            return ['error'=>1, 'msg'=>'抱歉，产品不存在，请联系管理员'];
        }

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
        $count = db('v_order')->where($where)->count();
        // sql();
        $page = new Page($count, $this->showNum);
        $res = db('v_order')->where($where)
                                ->order($order_by)
                                ->limit("{$page->firstRow}, {$page->listRows}")
                                ->select();
                                // sql();

        $this->page = $page;
        $this->listTotal = $count;
        foreach ($res as $key => $row) {
            //获取订单产品
            $res[$key]['goods'] = M('order_goods')->where(['order_id'=>$row['order_id']])->select();
        }

        //刷洗数据
        $res_refresh = $this->refreshDatasByOutOrder($res, $goodsRow);//引用更新
        // ee($res_refresh);
        // ee($res);
        return $res;
    }


    /**
     * 第三方刷洗数据-可批量
     * @return array $res 结果
     */
    public function refreshDatasByOutOrder(&$rows=[], $goodsRow=[]){
        $res = ['error'=>0, 'msg'=>'操作成功'];

        if (empty($rows)) {
            return $res;
        }

        //获取供应商
        $supplier = $this->getSupplier($goodsRow['supplier_id']);
        if (empty($supplier)) {
            $res = ['error'=>1, 'msg'=>'抱歉，系统异常，请联系管理员！!'];
            return $res;
        }

        //获取商品配置(第三方配置)
        $goodsCfg = db('goods_config')->where(['goods_config_id'=>$goodsRow['goods_config_id']])->find();
        if (empty($goodsCfg)) {
            return ['error'=>1, 'msg'=>'抱歉，商品配置有误，请您联系管理员!'];
        }
        //检测配置-创建订单地址是否配置
        if (empty($goodsCfg['url_get_order_rows'])) {
            return ['error'=>1, 'msg'=>'抱歉，商品配置异常，请您联系管理员!'];
        }

        //获取商品配置
        if (empty($goodsCfg)) {
            $res = ['error'=>1, 'msg'=>'抱歉，商品未配置，请您联系管理员'];
            return $res;
        }

        //这里开始区分供应商
        switch ($supplier['code']) {
            case '10000':
                //定义本平台当前订单状态值
                //['1'=>'已完成', '2'=>'待处理', '3'=>'处理中', '4'=>'暂停中', '5'=>'余额不足', '6'=>'已退款'];//5=api余额不足
                $orderStateArr = ['ok'=>'1', '暂停中'=>'4','处理中'=>'3','refund'=>'6'];//api状态=>本站状态

                //获取标签
                $tags = $this->getAllTags('run_first');

                //先更新任务速度模式-防止前面无数据任务模式未更新
                foreach ($rows as $k => $value) {
                    $rows[$k]['run_first_name'] = "{$value['first']}个/分钟";
                    $rows[$k]['order_status_name'] = $this->orderStatusConfig[$value['order_status']] ?? '';
                }


                $url_api = $goodsCfg['url_get_order_rows'];
                $apikey = $supplier['apikey'];
                $params = ['apikey'=>$apikey, 'renwuid'=>$row['out_id'], 'type'=>'query'];//提交参数

                //注意:这个平台没有批量接口，只能逐个刷洗
                foreach ($rows as $key => $row) {
                    //不更新情况
                    if ($row['out_id'] == 0) {
                        continue;
                    }

                    //更新任务状态-值
                    $rows[$key]['task_status_value'] = '';

                    //更新任务速度模式
                    $rows[$key]['run_first_name'] = $tags['run_first'][$row['first']] ?? '';

                    $params['renwuid'] = $row['out_id'];//任务id
                    $res_api = apiget($url_api, $params);
                    //异常情况
                    if (empty($res_api) || empty($res_api['ret']) || $res_api['ret'] != 1) {
                        $msg = $res_api['msg'] ?? "抱歉，创建任务出现异常，请联系管理员";
                        continue;//失败的时候这里继续下一个
                    }
                    //更新任务状态-名称
                    if (isset($res_api['msg'])) {
                        $rows[$key]['order_status_name'] = $res_api['msg'] ?? '';
                    }

                    //更新任务状态-值
                    $apiStatus = $res_api['msg'] ?? '';
                    if (isset($orderStateArr[$apiStatus])) {
                        $rows[$key]['task_status_value'] = $orderStateArr[$apiStatus];
                    }elseif (preg_match('/\d+\/\d+/', $apiStatus)) {
                        $rows[$key]['task_status_value'] = $orderStateArr['处理中'];
                    }
                }
                break;

            //JP网络-已起用
            case '20000':
                //定义本平台当前订单状态值
                //['1'=>'已完成', '2'=>'待处理', '3'=>'处理中', '4'=>'暂停中', '5'=>'余额不足', '6'=>'已退款'];//5=api余额不足
                $orderStateArr = ['4'=>'1', '3'=>'4','2'=>'3','5'=>'6'];//api状态=>本站状态
                //先更新任务速度模式-防止前面无数据任务模式未更新
                foreach ($rows as $k => $value) {
                    $rows[$k]['run_first_name'] = "{$value['first']}个/分钟";
                    //更新任务状态-值
                    $rows[$key]['task_status_value'] = '';

                    //订单状态名称
                    $rows[$k]['order_status_name'] = $this->orderStatusConfig[$value['order_status']] ?? '';

                    //更新开始时间
                    $rows[$k]['stime'] = $value['ctime'];
                }

                //获取订单任务ids
                $out_ids = array_unique(array_column($rows, 'out_id'));
                $url_api = $goodsCfg['url_get_order_rows'];
                $postdatas = json_encode(['order_id'=>$out_ids]);
                $res_api = apiget($url_api, $postdatas);
                // ee($res_api);

                //异常情况
                if (empty($res_api) || empty($res_api['success']) || !$res_api['success']) {
                    $msg = $res_api['message'] ?? '抱歉，创建任务时出现异常，请联系管理员';
                    return ['error'=>1, 'msg'=>$msg];
                }

                if (empty($res_api['data'])) {
                    return ['error'=>1, 'msg'=>'暂无订单数据'];
                }

                //将第三方数据以任务id作为key
                $outOrderList = array_column($res_api['data'], null, 'id');//这里用的是三方数据的weibo_id字段等TODO
                // ee($outOrderList);

                //开始遍历刷洗
                foreach ($rows as $key => $row) {
                    //如果api订单不存在,则不更新平台订单状态
                    if ($row['out_id'] == 0 ||empty($outOrderList[$row['out_id']])) {
                        continue;
                    }

                    //更新任务状态
                    $done_num = $outOrderList[$row['out_id']]['done_num'] ?? null;//执行量
                    $task_num = $outOrderList[$row['out_id']]['task_num'] ?? null;//任务量

                    if (is_null($done_num) || is_null($task_num)) {
                        continue;
                    }

                    //更新状态名称
                    $order_status_name = "{$done_num}/{$task_num}";
                    if ($done_num > 0 && $done_num == $task_num) {
                        $order_status_name = "ok";//处理完了,默认显示ok
                    }
                    $rows[$key]['order_status_name'] = $order_status_name;

                    //更新任务状态-值
                    $apiStatus = $outOrderList[$row['out_id']]['stage'] ?? '';
                    if (isset($orderStateArr[$apiStatus])) {
                        $rows[$key]['task_status_value'] = $orderStateArr[$apiStatus];
                    }
                }
                // ee($rows);
                break;

            case '30000':
                //定义本平台当前订单状态值
                //['1'=>'已完成', '2'=>'待处理', '3'=>'处理中', '4'=>'暂停中', '5'=>'余额不足', '6'=>'已退款', '7'=>'已作废'];//5=api余额不足
                //订单状态 1:等待中,2:审核中,3:完成,4:退款中,5:异常,6:排队,7:进行
                $orderStateArr = ['1'=>'2', '2'=>'2','3'=>'1','4'=>'2','5'=>'2','6'=>'2','7'=>'3'];//api状态=>本站状态

                //先更新任务速度模式-防止前面无数据任务模式未更新
                foreach ($rows as $k => $value) {
                    $rows[$k]['order_status_name'] = $this->orderStatusConfig[$value['order_status']] ?? '';

                    //更新开始时间
                    $rows[$k]['stime'] = $value['ctime'];
                }


                ///设置header头 start
                $res_headers = $this->getSupplierHeaderAll($supplier);//设置header头，注意，只有这一步在先，才会有对象属性apiToken！！后续依赖性
                //获取token异常情况
                if ($res_headers['error']) {
                    return ['error'=>1, 'msg'=>$res_headers['msg']];
                }
                $headers = $res_headers['data'];
                //设置header end

                $res_api = apiget($url_api, '', 'get', [], $headers);

                $url_api = $goodsCfg['url_get_order_row'];//基础url
                //注意:这个平台没有批量接口，只能逐个刷洗
                foreach ($rows as $key => $row) {
                    //不更新情况
                    if ($row['out_id'] == 0) {
                        continue;
                    }

                    //更新任务状态-值
                    $rows[$key]['task_status_value'] = '';

                    $res_api = apiget($url_api . "/{$row['out_id']}", '', 'get', [], $headers);
                    // ee($res_api);
                    //异常情况
                    if (empty($res_api) || !isset($res_api['error_code']) || $res_api['error_code'] != 0) {
                        $msg = $res_api['error_msg'] ?? "抱歉，任务获取出现异常(错误码0013)，请联系管理员";
                        continue;//失败的时候这里继续下一个
                    }

                    if (!isset($res_api['data']['status'])) {
                        $msg = $res_api['error_msg'] ?? "抱歉，任务获取出现异常(错误码015)，请联系管理员";
                        continue;//失败的时候这里继续下一个
                    }


                    //异常情况
                    //更新任务状态
                    // ee($row);
                    $unit = $row['goods'][0]['unit'] ?? 100;//默认是100
                    $done_num = $res_api['data']['finish_number'] ?? 0;//执行量-这个单位是1
                    $task_num = $row['task_num'] * $unit;//任务量,注意他这里单位是100

                    if (is_null($done_num) || is_null($task_num)) {
                        continue;
                    }

                    //更新状态名称
                    $order_status_name = "{$done_num}/{$task_num}";
                    if ($done_num > 0 && $done_num == $task_num) {
                        $order_status_name = "ok";//处理完了,默认显示ok
                    }
                    $rows[$key]['order_status_name'] = $order_status_name;

                    //更新任务状态-值
                    $apiStatus = $res_api['data']['status'] ?? '';
                    if (isset($orderStateArr[$apiStatus])) {
                        $rows[$key]['task_status_value'] = $orderStateArr[$apiStatus];
                    }
                }
                break;
            //精品网络-因为批量接口有问题这里改为单条查询-已废弃
            case '20000000':
                $url_api = $goodsCfg['url_get_order_rows'];

                //先更新任务速度模式-防止前面无数据任务模式未更新
                foreach ($rows as $key => $row) {
                    //先更新任务速度模式-防止前面无数据任务模式未更新
                    $rows[$key]['run_first_name'] = "{$row['first']}个/分钟";

                    //更新开始时间
                    $rows[$key]['stime'] = $row['ctime'];

                    //获取订单任务ids
                    $out_id = $row['out_id'];
                    $postdatas = json_encode(['order_id'=>[$out_id]]);
                    $res_api = apiget($url_api, $postdatas);
                    // ee($res_api);
                    //异常情况
                    if (empty($res_api) || empty($res_api['success']) || !$res_api['success']) {
                        $msg = $res_api['message'] ?? '抱歉，创建任务时出现异常，请联系管理员';
                        continue;//失败的时候这里继续下一个
                    }
                    // ee($res_api);

                    if (empty($res_api['data'][0])) {
                        continue;
                    }

                    $outOrder = $res_api['data'][0];
                    // ee($outOrder);
                    //更新任务状态
                    $done_num = $outOrder['done_num'];//执行量
                    $task_num = $outOrder['task_num'];//任务量
                    if (isset($done_num) && isset($task_num)) {
                        $order_status_name = "{$done_num}/{$task_num}";
                        if ($done_num >0 && $done_num == $task_num) {
                            $order_status_name = "ok";//处理完了,默认显示ok
                        }
                        $rows[$key]['order_status_name'] = $order_status_name;
                    }

                }

                // ee($rows);
                break;
            default:
                return ['error'=>1, 'msg'=>'抱歉，配置未完善，暂时无法创建订单，请您联系管理员'];
                break;
        }
        return $res;
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

        //获取商品信息
        $goodsRow = $this->getGoodsRow($order['goods_id']);
        if (empty($goodsRow)) {
            return ['error'=>1, 'msg'=>'抱歉，产品不存在，请联系管理员'];
        }

        //获取商品配置
        $goodsCfg = M('goods_config')->where(['goods_config_id'=>$goodsRow['goods_config_id']])->find();
        if (empty($goodsCfg)) {
            $res = ['error'=>1, 'msg'=>'抱歉，系统异常，请您联系管理员'];
            return $res;
        }

        //开始设置
        $setRes = $this->setOrderBySupplier($supplier, $goodsCfg, $order, $params);
        if ($setRes['error']) {
            return ['error'=>1, 'msg'=>$setRes['msg']];
        }

        return $res;
    }

    /**
     * 设置订单(暂停|继续|退款)
     * @return array $res 结果
     */
    public function setOrderBySupplier($supplier, $goodsCfg=[], $order=[], $params=[]){
        $res = ['error'=>0, 'msg'=>'获取成功！'];
        $order_id = $order['out_id'];
        $supplierCode = $supplier['code'];
        if (empty($supplierCode)) {
            return ['error'=>1, 'msg'=>'抱歉，暂未配置操作码，暂无法使用，请联系管理员'];
        }

        //检测apikey配置
        if (empty($supplier['apikey'])) {
            return ['error'=>1, 'msg'=>'抱歉，产品key配置有误，暂无法使用，请联系管理员'];
        }

        //检测商品配置-是否设置了设置订单配置项目
        if (empty($goodsCfg['url_set_order'])) {
            return ['error'=>1, 'msg'=>'抱歉，产品配置未完善，暂无法使用，请联系管理员'];
        }

        //设置类型与api对应关系
        $setTypeRelate = [
            '10000' => ['pause'=>'pause','continue'=>'continue','refund'=>'refund'],
            '20000' => ['pause'=>'pauseTask','continue'=>'resumeTask','refund'=>'refundTask'],
        ];

        if (!isset($setTypeRelate[$supplierCode])) {
            return ['error'=>1, 'msg'=>'抱歉，配置码有误，无法操作，请联系管理员'];
        }


        //设置类型
        $setTypeArr = $setTypeRelate[$supplierCode];//可能返回$setTypeRelate['10000']

        //设置类型强制检测
        if (!isset($setTypeArr[$params['type']])) {
            return ['error'=>1, 'msg'=>'操作失败，设置操作类型非法!'];
        }

        //到这里就是api合法的设置类型了,可以直接把值传递到api
        $type = $setTypeArr[$params['type']];

        switch ($supplierCode) {
            case '10000':
                $url = $goodsCfg['url_set_order'];
                $apikey = $supplier['apikey'];
                $params = ['apikey'=>$apikey, 'renwuid'=>$order_id, 'type'=>$type];
                // ee($params);
                $result = apiget($url, $params);
                // ee($result);

                if (empty($result) || $result['ret'] != 1) {
                    return ['error'=>1, 'msg'=>$result['msg']];
                }
                break;
            
            case '20000':
                $apikey = $supplier['apikey'];
                $url_api = $goodsCfg['url_set_order'] . "/?action={$type}&token={$apikey}";
                $params = ['order_id'=>$order_id];

                $res_api = apiget($url_api, $params);
                // ee($res_api);

                //添加api日志
                $this->add_out_api_log(['order_id'=>$order_id, 'desc'=>ecodejson($res_api)]);

                //异常情况
                if (empty($res_api) || empty($res_api['success']) || !$res_api['success']) {
                    $msg = $res_api['message'] ?? '抱歉，创建任务时出现异常，请联系管理员';
                    return ['error'=>1, 'msg'=>$msg];
                }
                break;

            default:
                # code...
                break;
        }

        return $res;

    }


    //获取商品订单统计(统计每个商品下单数量)
    public function getOrderGoodsStat($params=[]){
        $res = [];
        $where = ['is_deleted'=>'0'];
        
        //根据用户查找
        if (!empty($params['user_id'])) {
            $user_id = $params['user_id'];
            $where['user_id'] = $user_id;
        }

        $res = db('order')->where($where)->group('goods_id')->column('goods_id,count(order_id) as num');
        return $res;
    }

}