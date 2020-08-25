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
    
    //计算订单总价
    public function getTotalAmount($num=0, $price=0){
        $res = 0;
        if (empty($num)) {
            return $res;
        }
        if (empty($price)) {
            return $res;
        }
        $num = (int)$num;

        $res = $num*$price;
        $res = fnum($res, 0, 2);
        return $res;
    }


    //获取供应商
    public function getSupplier($supplier_id=0){
        $where = ['is_show'=>1, 'supplier_id'=>$supplier_id];
        $res = M('suppliers')->where($where)->find();
        return $res;
    }

    //获取分类
    public function getCatRow($cat_id=0){
        $where = ['is_show'=>1, 'cat_id'=>$cat_id];
        $res = M('goods_cat')->where($where)->find();
        return $res;
    }

    //获取商品
    public function getGoodsRow($goods_id=0){
        $where = ['is_show'=>1, 'goods_id'=>$goods_id];
        $res = M('goods')->where($where)->find();
        return $res;
    }

    //获取所有楼盘标签
    public function getAllTags($type = '', $cat_id=2){
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

        $datas = Db::name('goods_label')->where($where)->order('type asc, sort asc')->field('type, label_id, label_name')->select();
        foreach ($datas as $data) {
            $res[$data['type']][$data['label_id']] = $data['label_name'];
        }
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
}