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
    public $setPriceRateError = 0;//设置抽成比率错误检测,用来检测设置的值是否大于100，或者非法
    public $combinPriceError = 0;//组合任务价格设置有误
    public $comCostPrice = 0;//组合价格

    public $priceRate = 0;//抽成比率-小数
    public $apiUserinfo = [];//api用户信息

    //账号基础价格
    public $basicPrice = [
        'pingLun' => 0.3,//评论
        'zhuanFa' => 0.3,//转发
        'dianZhan' => 0.25,//点赞
        'guanZhu' => 0.25,//关注
    ];

    //账号基础价格组合规则(成本计算规则)-总共14中组合
    public $basicPriceCombinRule = [
        //一一组合
        1  => [
            'name' => ['pingLun'],
            'value' => 0.3,
        ],
        2  => [
            'name' => ['zhuanFa'],
            'value' => 0.3,
        ],
        3  => [
            'name' => ['dianZhan'],
            'value' => 0.25,
        ],
        4  => [
            'name' => ['guanZhu'],
            'value' => 0.25,
        ],

        //两两组合
        5  => [
            'name' => ['pingLun', 'zhuanFa'],
            'value' => 0.5,
        ],
        6  => [
            'name' => ['pingLun', 'dianZhan'],
            'value' => 0.5,
        ],
        7  => [
            'name' => ['pingLun', 'guanZhu'],
            'value' => 0.5,
        ],
        8  => [
            'name' => ['zhuanFa', 'dianZhan'],
            'value' => 0.5,
        ],
        9  => [
            'name' => ['zhuanFa', 'guanZhu'],
            'value' => 0.5,
        ],
        10  => [
            'name' => ['dianZhan', 'guanZhu'],
            'value' => 0.45,
        ],

        //三三组合
        11  => [
            'name' => ['pingLun', 'zhuanFa', 'dianZhan'],
            'value' => 0.7,
        ],
        12  => [
            'name' => ['pingLun', 'zhuanFa', 'guanZhu'],
            'value' => 0.7,
        ],
        13  => [
            'name' => ['pingLun', 'dianZhan', 'guanZhu'],
            'value' => 0.7,
        ],
        14  => [
            'name' => ['zhuanFa', 'dianZhan', 'guanZhu'],
            'value' => 0.7,
        ],

        //四个组合
        15  => [
            'name' => ['pingLun', 'zhuanFa', 'dianZhan', 'guanZhu'],
            'value' => 0.9,
        ],
    ];

    /**
     * 获取会员抽成比率
     * 会员价逻辑:会员users表中的level对应user_level的id，而user_level有个字段sale_price_field，这个正好是goods表中设置的售价
     * 换句话说:会员价直接与会员的level级别有关系
     * @param int $goods_id 商品id
     * @param int $user_id 用户id
     * @return number
     */
    public function getSetPiceRate($goods_id=0, $user_id=0){
        $res = 0;//返回销售总价、会员真实购买价
        if (empty($goods_id)) {
            return $res;
        }

        //查找商品销售价-比率
        $goodsRow = db('goods')->field('goods_id, sale_price, user_price, min_num')->where('goods_id', $goods_id)->find();
        if (empty($goodsRow)) {
            return $res;
        }
        $price = $goodsRow['sale_price'];

        //如果设置了会员价
        if ($user_id) {
            $goodsUserPrice = $this->getGoodsUserPrice($goods_id, $user_id, $goodsRow);
            if ($goodsUserPrice) {
                $price = $goodsUserPrice;//修改最终成交价格为会员价
            }
        }

        $res = fnum($price, 0, 2);
        $this->priceRate = $res/100;//抽成
        return $res;
    }

    //预算总价
    //重要说明:数据库会员等级价、私有价、销售价设置说明，在数据库设置三个值时候数值均代表成本的抽成百分比！当计算最终销售价的时候均根据用户输入价格计算
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

    /**
     * 获取组合成本价格(计算后相当于数据库里面的cost_price字段值)-评论、转发、点赞、关注等14中组合后的成本
     * @param number $pingLun 评论基础价格
     * @return number
     */
    public function calcCombinCostPrice($pingLun=0, $zhuanFa=0, $dianZhan=0, $guanZhu=0){
        $res = 0;

        $userSet = [];//用户设置的任务类型
        $basicPriceCombinRule = $this->basicPriceCombinRule;

        if ($pingLun > 0) {
            $userSet[] = 'pingLun';
        }

        if($zhuanFa > 0) {
            $userSet[] = 'zhuanFa';
        }

        if($dianZhan > 0) {
            $userSet[] = 'dianZhan';
        }

        if($guanZhu > 0) {
            $userSet[] = 'guanZhu';
        }

        //开始循环匹配对应的组合价格规则
        //异常提示内容为:抱歉任务组合价格设置规则有误，请联系管理员,错误码(0056)
        foreach ($basicPriceCombinRule as $row) {
            if(count(array_diff($row['name'], $userSet)) == 0 && count(array_diff($userSet, $row['name'])) == 0 ) { 
                $res = $row['value'];
                break;
            }
        }
        // ee($res);
        //此处原因是价格规则没有匹配到,报异常
        if ($res == 0) {
            $this->combinPriceError = 1;
        }

        $res = fnum($res, 0, 2);//根据api要求，保留2位
        return $res;
    }

    /**
     * 获取最小设置价格(成本价/百分比率)
     * @param number $com_cost_price 成本价格,如果是人工互助，则成本由组合任务确定
     * @return number
     */
    public function getMinSetPrice($goods_id=0, $user_id=0, $com_cost_price=0){
        $res = 0;
        //直接通过这个方法，可先获取抽成比率
        $rate = $this->getSetPiceRate($goods_id, $user_id);//注意这里的num必须传递值为1

        //转换百分比制
        $rate = $rate/100;

        //检测数据库设置的非法数值
        if ($rate < 0) {
            $this->setPriceRateError = 1;
        }
        if ($rate >= 1) {
            $this->setPriceRateError = 2;
        }

        //获取成本
        $goodsRow = db('goods')->field('goods_id, cost_price')->where('goods_id', $goods_id)->find();
        if (empty($goodsRow)) {
            return $res;
        }

        $cost_price = $goodsRow['cost_price'];

        //如果是人工互助
        if ($com_cost_price > 0) {
            $cost_price = $com_cost_price;
        }
        

        $res = $cost_price/(1-$rate);//计算最小可设置价

        //TODO，这里有小数点四舍五入问题，可能要把自低价调高些

        $res = fnum($res, 0, 2);//转换格式

        return $res;
    }


    /**
     * 计算订单总成本价(注意:这里的成本价与用户设置有关，与数据库cost_price没有直接关系,cost_price只作为最低提交价格计算参考！)
     * @param int $num 购买数量
     * @param int $goods_id 商品id
     * @param number $set_price 用户设置价格
     * @param number $priceRate 抽成比率,小数
     * @return number
     */
    public function calcTotalCost($num=0, $goods_id=0, $set_price=0, $priceRate=0){
        $res = 0;
        if (empty($num)) {
            return $res;
        }
        if (empty($goods_id)) {
            return $res;
        }

        //查找商品销售价
        $goodsRow = db('goods')->field('goods_id, cost_price, min_num')->where('goods_id', $goods_id)->find();
        if (empty($goodsRow)) {
            return $res;
        }
        $cost_price = $goodsRow['cost_price'];

        //任务数量
        $num = floatval($num);
        $min_num = $goodsRow['min_num'];
        //如果设置了最低量
        if (!empty($min_num) && $min_num > 0) {
            $min_num = floatval($min_num);
            if ($num < $min_num) {
                $num = $min_num;
            }
        }

        $set_price = floatval($set_price);

        //检测数据库设置的非法数值
        if ($priceRate < 0) {
            $this->setPriceRateError = 3;
        }
        if ($priceRate >= 1) {
            $this->setPriceRateError = 4;
        }

        $res = $num*$set_price*(1-$priceRate);
        $res = fnum($res, 0, 2);
        return $res;
    }

    /**
     * 计算订单成本单价-可以作为提交api价格使用、最终成交价
     * @param number $set_price 用户设置价格
     * @param number $priceRate 抽成比率,小数
     * @return number
     */
    public function calcCostPrice($set_price=0, $priceRate=0){
        $res = 0;

        $set_price = floatval($set_price);

        //检测数据库设置的非法数值
        if ($priceRate < 0) {
            $this->setPriceRateError = 5;
        }
        if ($priceRate >= 1) {
            $this->setPriceRateError = 6;
        }

        $res = $set_price*(1-$priceRate);
        $res = fnum($res, 0, 2);//根据api要求，保留2位
        return $res;
    }


    /**
     * 获取任务类型-名称-多个用逗号分隔
     * @return string
     */
    public function getTaskTypeNames($pingLun=0, $zhuanFa=0, $dianZhan=0, $guanZhu=0){
        $res = '';

        $userSet = [];//用户设置的任务类型

        if ($pingLun > 0) {
            $userSet[] = '评论';
        }

        if($zhuanFa > 0) {
            $userSet[] = '转发';
        }

        if($dianZhan > 0) {
            $userSet[] = '点赞';
        }

        if($guanZhu > 0) {
            $userSet[] = '关注';
        }

        if (!empty($userSet)) {
            $res = implode(',', $userSet);
        }

        return $res;
    }


    //获取商品-任务-评论专用
    public function getTaskGoodsRow($goods_id=0, $user_id=0){
        $where = ['is_show'=>1, 'goods_id'=>$goods_id];
        $res = db('goods')->where($where)->find();
        if (!$res) {
            return $res;
        }

        $res['sale_price'] = $res['cost_price'];//先给个默认

        //获取会员设置最低价
        $minSetPrice = $this->getMinSetPrice($goods_id, $user_id);
        if ($minSetPrice) {
            $res['sale_price'] = $minSetPrice;
        }
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
        if (in_array($goodsRow['cat_id'], [23,25,26])) {
            //人工互助情况-价格由任务组合决定
            $this->comCostPrice = $this->calcCombinCostPrice($params['pingLun'], $params['zhuanFa'], $params['dianZhan'], $params['guanZhu']);
            if ($this->combinPriceError) {
                return ['error'=>1, 'msg'=>'抱歉,任务组合价格设置规则有误，请联系管理员,错误码(0056)'];
            }
            $minSetPrice = $this->getMinSetPrice($goodsRow['goods_id'], $user_id, $this->comCostPrice);//获取此会员可设置的最低价

        }else{
            $minSetPrice = $this->getMinSetPrice($goodsRow['goods_id'], $user_id);//获取此会员可设置的最低价
        }

        //此处利用上面方法里面检测过的条件，如果数据库设置抽成非法则阻止提交订单
        if ($this->setPriceRateError) {
            return ['error'=>1, 'msg'=>'抱歉，此商品售价设置有误(错误码0012)，请联系管理员！'];
        }

        if ($params['cm_price'] < $minSetPrice) {
            return ['error'=>1, 'msg'=>"抱歉，设置单价不能低于:{$minSetPrice}！"];
        }

        // ee($minSetPrice);

        //可供检测重复提交数据使用
        $data['cat_id']          = $goodsRow['cat_id'] ?? 0;
        $data['user_id']         = $user_id;
        $data['url']             = $params['url'] ?? '';
        $data['out_id']          = $params['out_id'] ?? 0;//外部订单id-改为后面更新了
        $data['user_note']       = $params['user_note'] ?? '';//备注
        $data['task_num']        = $params['task_num'] ?? 0;//下单数量
        $data['supplier_id']     = $goodsRow['supplier_id'];//供应商id
        $data['goods_config_id'] = $goodsRow['goods_config_id'];//商品配置id
        $data['goods_id']        = $params['goods_id'] ?? 0;//商品id

        $row = Db::name("order")->where($data)->find();
        if ($row) {
            return ['error'=>1, 'msg'=>'请勿重复提交,您可以修改一些参数后再次提交'];
        }


        //计算订单总价
        $total_amount = $this->budgetTotalAmount($params['task_num'], $params['cm_price']);

        //销售额为0,默认不允许下单
        if ($total_amount <= 0) {
            return ['error'=>1, 'msg'=>'抱歉，订单总价计算后为空，请联系管理员'];
        }

        //检查用户余额是否充足
        if ($total_amount > $userInfo['user_money']) {
            return ['error'=>1, 'msg'=>'抱歉，余额不足，请充值.'];
        }

        //最终成交价
        $this->cost_price = $this->calcCostPrice($params['cm_price'], $this->priceRate);
        
        //计算订单总成本价
        $total_cost = $this->calcTotalCost($data['task_num'], $goodsRow['goods_id'], $params['cm_price'], $this->priceRate);
        // ee($total_cost);

        //此处利用上面方法里面检测过的条件，如果数据库设置抽成非法则阻止提交订单
        if ($this->setPriceRateError) {
            return ['error'=>1, 'msg'=>'抱歉，此商品售价设置有误(错误码0013)，请联系管理员！'];
        }

        // 启动事务
        Db::startTrans();
        try{
            $data['order_sn'] = $order_sn = get_order_sn();
            $data['ctime'] = $ctime;
            $data['ip'] = $request->ip();
            $data['order_name'] = $params['cm_title'];//任务标题
            $data['order_type_name'] = $this->getTaskTypeNames($params['pingLun'], $params['zhuanFa'], $params['dianZhan'], $params['guanZhu']);

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
            $goods['final_price']     = $params['cm_price'];//成交价格=用户设置价格
            $goods['cost_price']      = $this->cost_price;//成本价=用户设置价格-抽成
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

            //生成第三方数据-这一步会生成$this->apiMoney数据
            $create_res = $this->createThirdOrderBySupplier($supplier, $goodsCfg, $params, $cat, $goodsRow, $this->cost_price);
            if ($create_res['error']) {
                //注意这里分为两种情况:1.api报错,2.平台报错-非法，如果是2则直接回滚数据
                //api返回错误情况
                if ($create_res['error'] == 2) {
                    ///注意这里只记录余额不足情况
                    //检测余额是否充足
                    // $apiMoney = $this->apiMoney ?? -999;
                    $apiMoney = $this->getApiMoneyBySupplier($supplier['supplier_id']);
                    //未获取到情况
                    if ($apiMoney == -999) {
                        Db::rollback();// 回滚事务
                        return ['error'=>1, 'msg'=>'抱歉，更新订单状态时候出错(错误码0099)，请联系管理员'];
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
            $updateOrder = ['out_id'=>$create_res['data']['out_id'], 'order_status'=>2];
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
    public function createThirdOrderBySupplier($supplier, $goodsCfg=[], $params=[], $cat=[], $goodsRow=[], $cost_price){
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
                // ee($apiUserinfoRes);

                //获取token异常情况
                if ($apiUserinfoRes['error']) {
                    return ['error'=>1, 'msg'=>$apiUserinfoRes['msg']];
                }

                $this->apiUserinfo = $apiUserinfo = $apiUserinfoRes['data'];//返回用户信息,余额、评论单价等

                $url_api = $goodsCfg['url_create_order'];
                //提交参数
                $postdatas = [
                    'uid'      => $apiUserinfo['uid'],//用户id
                    'usersign' => $apiUserinfo['usersign'],//用户签名 
                    'title'    => $params['cm_title'], //任务标题
                    'addr'     => $params['url'], //任务地址
                    'descr'    => $params['user_note'], //任务要求
                    'max'      => $params['cm_max'], //最大评论量
                    'price'    => $cost_price*100, //每条评论的单价(单位分)
                ];


                if (in_array($cat_id, [22])) {
                    $postdatas['sens']    = $params['cm_sens'];//敏感词，多个词中间分号隔开,最多100字
                    $postdatas['face']    = implode(';', $params['cm_face']); //评论方向(赞美、中性、询问、调侃、吐槽) 中间分号隔开
                    $postdatas['minchar'] = $params['cm_minchar']; //每条评论最小字数
                    $postdatas['level']   = 0; //是否刷量(0，正常；1，刷量) 
                }elseif (in_array($cat_id, [23,25,26])) {
                    $postdatas['sendValue'] = $params['sendValue'];
                    $postdatas['device']    = 0;
                    $postdatas['userIp']    = 0;
                    $postdatas['guanZhu']   = $params['guanZhu'] ?? 0;
                    $postdatas['dianZhan']  = $params['dianZhan'] ?? 0;
                    $postdatas['zhuanFa']   = $params['zhuanFa'] ?? 0;
                    $postdatas['pingLun']   = $params['pingLun'] ?? 0;
                }else{
                    //不在分类直接异常
                    return ['error'=>1, 'msg'=>'抱歉，商品分类配置有误(错误码0028)，暂时无法创建订单，请联系管理员增加配置'];
                }

                // ee($url_api);

                $postdatasJson = json_encode($postdatas);//JSON_UNESCAPED_UNICODE
                $res_api = apiget($url_api, $postdatasJson, 'post', [], $headers);

                //模拟成功数据
                // $res_api = [
                //     'result'=>['taskid'=>3142004],
                //     'code'=>0,
                //     'message'=>'发布成功',
                // ];

                // ee($res_api);
                //异常情况
                if (empty($res_api) || !isset($res_api['code'])  || !isset($res_api['result']) || $res_api['code'] != 0) {
                    $msg = $res_api['msg'] ?? "抱歉，创建任务出现异常，请联系管理员";//这里数据源错误字段描述是：message,所以这里只能是后者
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


    //获取任务详情-评论列表-作废
    protected function getTaskDetail_old($params = []){
        $res = ['error'=>0, 'msg'=>'操作成功', 'data'=>[]];
        if (empty($params)) {
            return $res;
        }

        if (empty($params['id'])) {
            return ['error'=>1, 'msg'=>'订单id不能为空'];
        }

        $order_id = $params['id'];
        $status = $params['status'] ?? 2;//任务状态，默认2是获取所有

        //获取订单信息
        $orderRow = db('order')->where(['order_id'=>$order_id])->find();

        if (empty($orderRow)) {
            return ['error'=>1, 'msg'=>'抱歉，订单不存在，请联系管理员'];
        }

        $goods_id = $orderRow['goods_id'];

        //获取商品信息
        $goodsRow = $this->getGoodsRow($goods_id);
        if (empty($goodsRow)) {
            return ['error'=>1, 'msg'=>'抱歉，产品不存在，请联系管理员'];
        }

        //获取供应商
        $supplier = $this->getSupplier($goodsRow['supplier_id']);
        if (empty($supplier)) {
            $res = ['error'=>1, 'msg'=>'抱歉，系统异常(错误码00021)，请联系管理员！!'];
            return $res;
        }

        //获取商品配置(第三方配置)
        $goodsCfg = db('goods_config')->where(['goods_config_id'=>$goodsRow['goods_config_id']])->find();
        if (empty($goodsCfg)) {
            return ['error'=>1, 'msg'=>'抱歉，商品配置有误，请您联系管理员!'];
        }
        //检测配置-创建订单地址是否配置
        if (empty($goodsCfg['url_get_order_row1'])) {
            return ['error'=>1, 'msg'=>'抱歉，商品配置异常，请您联系管理员!'];
        }

        //设置headers
        $headers = [
            'Content-Type:application/json; charset=UTF-8',
        ];
        $apiUserinfoRes = $this->getSupplierToken($supplier);//获取用户信息,余额、评论单价等

        //获取token异常情况
        if ($apiUserinfoRes['error']) {
            return ['error'=>1, 'msg'=>$apiUserinfoRes['msg']];
        }

        $apiUserinfo = $apiUserinfoRes['data'];//返回用户信息,余额、评论单价等
        //提交参数
        $postdatas = [
            'uid'      => $apiUserinfo['uid'],//用户id
            'usersign' => $apiUserinfo['usersign'],//用户签名 
            'taskid'   => $orderRow['out_id'],
            'status'   => $status,//审核状态(0，待审核；1，已审核)
        ];

        $url_api = $goodsCfg['url_get_order_row1'];//基础url

        $postdatasJson = json_encode($postdatas);//JSON_UNESCAPED_UNICODE
        $res_api = apiget($url_api, $postdatasJson, 'post', [], $headers);

        // $res_api = '{"code":0,"message":"success","result":{"data":[{"taskid":3142809,"commid":189986002,"cont":"[坏笑]戴口罩的时候头大真的是很不舒服","status":0},{"taskid":3142809,"commid":189986065,"cont":"爱流鼻涕的人戴口罩也太难受了吧。","status":0}]}}';
        // $res_api = json_decode($res_api, true);

        
        // ee(json_encode($res_api['result']['data'],JSON_UNESCAPED_UNICODE));
        // ee($res_api);

        //异常情况
        if (empty($res_api) || !isset($res_api['code'])  || !isset($res_api['result']) || $res_api['code'] != 0) {
            $msg = $res_api['message'] ?? "抱歉，任务详情获取出现异常(错误码0026)，请联系管理员";
            return ['error'=>1, 'msg'=>$msg];
        }


        if (!isset($res_api['result']['data'])) {
            $msg = "抱歉，任务详情获取出现异常(错误码027)，请联系管理员";
            return ['error'=>1, 'msg'=>$msg];
        }
        $res['data'] = $res_api['result']['data'];
        return $res;
    }


    //获取评论内容-用户复制
    public function getTaskCommentCopyData($params = []){
        $res = ['error'=>0, 'msg'=>'操作成功', 'data'=>[]];
        $comments = [];//默认返回数据格式
        $result = $this->getTaskDetail($params);

        if ($result['error']) {
            return ['error'=>1, 'msg'=>$result['msg']];
        }
        $commentsList = $result['data'];

        if (!empty($commentsList)) {
            foreach ($commentsList as $comment) {
                $comments[] = $comment['cont'];
            }
        }

        $res['data'] = implode(PHP_EOL, $comments);//用换行符来连接
        // ee($res);
        return $res;
    }


    //获取任务详情-评论列表
    public function getTaskDetail($params = []){
        $res = ['error'=>0, 'msg'=>'操作成功', 'data'=>[]];
        if (empty($params)) {
            return $res;
        }

        if (empty($params['id'])) {
            return ['error'=>1, 'msg'=>'订单id不能为空'];
        }

        $order_id = $params['id'];

        //获取订单信息
        $row = db('task_comments')->where(['order_id'=>$order_id])->find();
        $comments = json_decode($row['content'], true);

        $res['data'] = $comments;
        return $res;
    }


    //获取某个用户已经写好的评论列表(任务标题+完成数量)，便于转评方便使用；只获取最近N条数据
    public function getUserCommentedTaskDatas($user_id, $num=10){
        $res = ['error'=>0, 'msg'=>'操作成功', 'data'=>[]];

        if (empty($user_id)) {
            return $res;
        }

        $where['user_id'] = $user_id;

        $field = 'comment_id,order_id,order_name,url,task_num,done_num,user_note,order_ctime';
        $rows = db('v_task_comments')->field($field)->where($where)->order('order_ctime desc')->limit($num)->select();

        foreach ($rows as $key => $row) {
            $rows[$key]['task_num'] = (int)$row['task_num'];
            $rows[$key]['done_num'] = (int)$row['done_num'];
        }
        $res['data'] = $rows;
        // ee($res);
        return $res;
    }

}