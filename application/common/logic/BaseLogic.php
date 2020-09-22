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

/**
 * 基础逻辑定义
 * Class UsersLogic
 * @package Home\Logic
 */
class BaseLogic {
    public $tags;//所有标签

    public $page;//分页数据
    public $showNum=10;//每页显示数量
    public $listTotal=0;//列表总数

    /**
     * 获取商品会员价
     * @param int $goods_id 商品id
     * @param int $user_id 用户id
     * @param array $goodsRow 商品数据行
     * @return number
     */
    public function getGoodsUserPrice($goods_id=0, $user_id=0, $goodsRow=[]){
        $res = 0;
        if (empty($goods_id)) {
            return $res;
        }
        if (empty($user_id)) {
            return $res;
        }

        //获取会员价(注意:普通的会员level=1这里就直接跳过，因为普通会员直接拿sale_price即可)-会被下面优先级覆盖
        $where = ['user_id'=>$user_id];
        $user = db('v_user')->where($where)->find();
        if (!empty($user) && $user['level'] > 1 && !empty($user['sale_price_field'])) {
            $sale_price_field = $user['sale_price_field'];
            if (!empty($goodsRow) && !empty($goodsRow[$sale_price_field])) {
                $res = $goodsRow[$sale_price_field];
            }
        }


        //显示定制价格-优先级最高
        $where = ['goods_id'=>$goods_id, 'user_id'=>$user_id];
        $goodsUserRow = db('goods_user')->where($where)->find();
        if (!empty($goodsUserRow) && $goodsUserRow['sale_price'] > 0) {
            $res = $goodsUserRow['sale_price'];
        }
        return $res;
    }

    
    /**
     * 计算订单总销售价
     * 会员价逻辑:会员users表中的level对应user_level的id，而user_level有个字段sale_price_field，这个正好是goods表中设置的售价
     * 换句话说:会员价直接与会员的level级别有关系
     * @param int $num 购买数量
     * @param int $goods_id 商品id
     * @param int $user_id 用户id
     * @return number
     */
    public function getTotalAmount($num=0, $goods_id=0, $user_id=0){
        $res = 0;//返回销售总价、会员真实购买价
        if (empty($num)) {
            return $res;
        }
        if (empty($goods_id)) {
            return $res;
        }

        //查找商品销售价
        $goodsRow = db('goods')->field('goods_id, sale_price, user_price, min_num')->where('goods_id', $goods_id)->find();
        if (empty($goodsRow)) {
            return $res;
        }
        $price = $goodsRow['sale_price'];

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
        //如果设置了会员价
        if ($user_id) {
            $goodsUserPrice = $this->getGoodsUserPrice($goods_id, $user_id, $goodsRow);
            if ($goodsUserPrice) {
                $this->final_price = $price = $goodsUserPrice;//修改最终成交价格为会员价
            }
        }

        $resPrice = $num*$price;

        //如果设置了价格参数(比如在原来价格基础上进行倍率操作)
        if (!empty($this->price_param)) {
            $resPrice = $resPrice*$this->price_param;
        }
        // ee($this->price_param);

        $res = fnum($resPrice, 0, 2);
        return $res;
    }

    /**
     * 计算订单总成本价
     * @param int $num 购买数量
     * @param int $goods_id 商品id
     * @return number
     */
    public function getTotalCost($num=0, $goods_id=0){
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
        $price = $goodsRow['cost_price'];

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

        $res = $num*$price;
        $res = fnum($res, 0, 4);
        return $res;
    }

    //获取标签行
    public function getLabelRow($label_id=0){
        $where = ['id'=>$label_id];
        $res = db('goods_label')->where($where)->find();
        return $res;
    }

    //获取供应商
    public function getSupplier($supplier_id=0){
        $where = ['is_show'=>1, 'supplier_id'=>$supplier_id];
        $res = db('suppliers')->where($where)->find();
        return $res;
    }

    //获取供应商列表
    public function getSupplierList(){
        $where = ['is_show'=>1];
        $res = db('suppliers')->where($where)->select();
        return $res;
    }

    //获取商品配置列表
    public function getGoodsConfigList(){
        $where = ['is_show'=>1];
        $res = db('goods_config')->where($where)->select();
        return $res;
    }

    //获取供应商Header头部请求-基本头部，不包括token
    public function getSupplierHeaderBasic($params=[]){
        $res = [];
        if (empty($params['code'])) {
            return $res;
        }
        $supplier_code= $params['code'];
        switch ($supplier_code) {
            case '30000':
                $res = [
                    'X-Afagou-Domain:afazhu.com',
                    'X-Afagou-User-Agent:api',
                    'X-Afagou-Version:1.0',
                    'Content-Type:application/json',
                ];
                break;
            
            default:
                # code...
                break;
        }

        return $res;
    }


    //获取供应商token
    public function getSupplierToken($supplier=[]){
        $res = ['error'=>0, 'msg'=>'获取成功！', 'data'=>null];

        if (empty($supplier)) {
            $res = ['error'=>1, 'msg'=>'此商品暂未配置(-001)，请联系管理'];
        }

        $supplier_code = $supplier['code'];
        if (empty($supplier_code)) {
            $res = ['error'=>1, 'msg'=>'此商品暂未配置(000)，请联系管理'];
        }
        $where = ['is_show'=>1, 'code'=>$supplier_code];
        $supplier = db('suppliers')->where($where)->find();

        if (!$supplier) {
            $res = ['error'=>1, 'msg'=>'此商品暂未配置(001)，请联系管理'];
        }

        if (empty($supplier['url'])) {
            $res = ['error'=>1, 'msg'=>'此商品暂未配置(002)，请联系管理'];
        }

        switch ($supplier_code) {
            case '30000':
                $url_api = $supplier['url'];
                $postdatas = ['mobile'=>$supplier['api_account'], 'password'=>$supplier['api_password'], 'encrypt'=>0];
                $headers = $this->getSupplierHeaderBasic(['code'=>$supplier_code]);//设置header头
                $res_api = apiget($url_api, $postdatas, 'post', $headers);

                //异常情况
                if (empty($res_api) || $res_api['error_code'] != 0) {
                    return ['error'=>2, 'msg'=>'此商品暂无法获取配置(003)，请联系管理'];
                }
                if (empty($res_api['data']['access_token'])) {
                    return ['error'=>2, 'msg'=>'此商品暂无法获取配置(004)，请联系管理'];
                }

                //生成此类的属性：用户余额
                $this->apiMoney = $res_api['data']['user']['amount'] ?? -999;

                $res['data'] = $res_api['data']['access_token'];
                break;
            default:
                # code...
                break;
        }

        return $res;
    }

    //获取供应商Header头部请求-基本头部，包括token
    public function getSupplierHeaderAll($supplier=[]){
        $res = ['error'=>0, 'msg'=>'获取成功！', 'data'=>null];
        if (empty($supplier['code'])) {
            return $res;
        }
        $supplier_code= $supplier['code'];//supplier的code值
        switch ($supplier_code) {
            case '30000':
                $headers = $this->getSupplierHeaderBasic(['code'=>$supplier_code]);//设置header头
                $this->apiToken = $res_token = $this->getSupplierToken($supplier);//设置对象属性apiToken
                //获取token异常情况
                if ($res_token['error']) {
                    return ['error'=>1, 'msg'=>$res_token['msg']];
                }
                $token = $res_token['data'];
                $headers[] = "token:{$token}";

                $res['data'] = $headers;
                break;
            
            default:
                # code...
                break;
        }

        return $res;
    }

    //获取分类
    public function getCatRow($cat_id=0){
        $where = ['is_show'=>1, 'cat_id'=>$cat_id];
        $res = db('goods_cat')->where($where)->find();
        return $res;
    }

    //获取分类列表
    public function getCatList($parent_id= -1){
        $where = ['is_show'=>1];
        if ($parent_id != -1) {
            $where['parent_id'] = $parent_id;
        }
        $field = 'cat_id, cat_name, parent_id, level, icon';
        $res = db('goods_cat')->field($field)->where($where)->order('sort')->select();
        return $res;
    }

    /**
     * 获取子分类ids
     * @param int $parent_id 父分类id
     * @return array $res 一位数组
     */
    public function getSubCatIds($parent_id=0){
        $res = [];
        if (empty($parent_id)) {
            return $res;
        }

        $where = ['is_show'=>1];
        $where['parent_id'] = $parent_id;

        $res = db('goods_cat')->where($where)->column('cat_id');
        return $res;
    }

    //获取分类树结构
    public function getCatTree(){
        $res = [];
        $datas = $this->getCatList();

        //整理格式
        $tempArr = [];
        foreach ($datas as $row) {
            $tempArr[$row['cat_id']] = $row;
        }

        //生成tree
        $res = gettree($tempArr, 'parent_id', 'cat_id');
        return $res;
    }

    //获取商品
    public function getGoodsRow($goods_id=0, $user_id=0){
        $where = ['is_show'=>1, 'goods_id'=>$goods_id];
        $res = db('goods')->where($where)->find();
        if (!$res) {
            return $res;
        }

        //是否有会员价?
        $goodsUserPrice = $this->getGoodsUserPrice($goods_id, $user_id, $res);
        // ee($goodsUserPrice);
        if ($goodsUserPrice) {
            $res['sale_price'] = $goodsUserPrice;
        }
        return $res;
    }

    //获取当前分类ids下面所有商品-暂时用于导航-不分页
    public function getCatGoodsList($cat_ids=[]){
        if (empty($cat_ids)) {
            $cat_ids = 1;
        }
        $where=[];
        $where['is_show'] = 1;
        $where['cat_id'] = ['IN', $cat_ids];
        $res = db('goods')->where($where)->select();
        return $res;
    }

    //获取所有标签
    public function getAllTags($type = '', $cat_id=2, $only_name=true){
        $res = [];
        $where = [];

        //仅查询某个类型情况
        if (!empty($type)) {
            $$where['type'] = $type;
        }

        //仅查询某个供应商情况
        if (!empty($cat_id)) {
            $$where['cat_id'] = $cat_id;
        }

        $datas = Db::name('goods_label')->where($where)->order('type asc, sort asc')->field('id, type, label_id, label_name,tag')->select();
        foreach ($datas as $data) {
            if ($only_name) {
                $res[$data['type']][$data['label_id']] = $data['label_name'];
            }else{
                $res[$data['type']][$data['label_id']] = $data;
            }
        }
        return $res;
    }

    //获取所有标签-id为key，label_id为value对应关系
    public function getIdValueTags(){
        $res = db('goods_label')->column('label_id', 'id');
        return $res;
    }

    /*
    * 获取当前登录用户信息
    */
    public function get_user_info($user_id){
        if (!$user_id) {
            return ['error'=>1, 'msg'=>'缺少参数'];
        }
        
        $row = M('users')->find($user_id);//dump($user);
        return $row;
     }

     /*
     * 添加api日志
     */
     public function add_out_api_log($data=[]){
        $ctime = date('Y-m-d H:i:s');
        $log_data = [
            'order_id'=> $data['order_id'] ?? 0,
            'desc'=> $data['desc'] ?? '',
            'type'=> $data['type'] ?? 1,//默认是返回数据
            'ctime'=> $ctime
        ];

        db('api_out_log')->insert($log_data);
      }


    /**
    * qq消息推送
    * @param array $params
    */
    public function qqpusher($params = []){

        $res = ['error' => 0, 'msg' => '操作成功'];
        $url_api = 'http://api.qqpusher.yanxianjun.com/send_private_msg';
        $headers = [
            'token:27c631a69783252726649d1fe6cbe834',
        ];

        $qq = $params['qq'];
        $msg = $params['msg'];
        $postdatas = [
            'user_id' => $qq,//对方QQ号
            'message' => $msg,//要发送的内容
            'auto_escape' => true,// 默认值：false 消息内容是否作为纯文本发送（即不解析 CQ 码），只在 message 字段是字符串时有效
        ];

        $res_api = apiget($url_api, $postdatas, 'post', [], $headers);
        // ee($res_api);
        return $res;
    }


    /**
    * 推送消息-下单
    * @param array $params
    */
    public function pushMessageOrder($params = []){
        $res = ['error'=>0, 'msg'=>'恭喜，下单提醒成功'];
        $order_id = $params['order_id'];//订单编号

        if (empty($order_id)) {
            return ['error'=>1, 'msg'=>'订单编号不能为空'];
        }

        $where['order_id'] = $order_id;
        $order = db('v_order')->where($where)->find();
        if (empty($order)) {
            return ['error'=>1, 'msg'=>'订单不存在'];

        }

        //获取商品信息
        $goodsRow = $this->getGoodsRow($order['goods_id']);
        if (empty($goodsRow)) {
            return ['error'=>1, 'msg'=>'订单产品不存在'];
        }

        //订单信息
        $order_status = $order['order_status'];
        $username     = $order['mobile'];
        $order_sn     = $order['order_sn'];
        $ctime        = $order['ctime'];
        $task_num     = fnum($order['task_num']);

        //产品信息
        $goods_name     = $goodsRow['goods_name'];

        //设置哪些类型需要走提醒
        if (!in_array($order_status, ['5', '8'])) {
            return ['error'=>1, 'msg'=>'暂时没有可提醒的订单状态'];
        }

        switch ($order_status) {
            case '3':
                //普通下单-暂不提醒
                break;

            case '5':
                // $msg = "【！API余额不足！】下单时间：{$ctime}，用户名：{$username}，订单编号：{$order_sn}，商品：{$goods_name}，任务数量：{$task_num}";
                $msg = "【！API余额不足！】下单时间：{$ctime}，订单编号：{$order_sn}，数量：{$task_num}";
                //API余额不足-提醒
                break;

            case '8':
                //SG单-提醒
                // $msg = "【手工单】下单时间：{$ctime}，用户名：{$username}，订单编号：{$order_sn}，商品：{$goods_name}，任务数量：{$task_num}";
                $msg = "【手工单】下单时间：{$ctime}，订单编号：{$order_sn}，数量：{$task_num}";
                // ee($msg);
                break;
            
            default:
                # code...
                break;
        }

        //开始提醒
        $kefu_qq_arr = config('kefu_qq');
        foreach ($kefu_qq_arr as$qq) {
            $res_push = $this->qqpusher(['qq'=> $qq, 'msg'=>$msg]);
        }

        return $res;
    }










}