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
     * @return number
     */
    public function getGoodsUserPrice($goods_id=0, $user_id=0){
        $res = 0;
        if (empty($goods_id)) {
            return $res;
        }
        if (empty($user_id)) {
            return $res;
        }
        $where = ['goods_id'=>$goods_id, 'user_id'=>$user_id];
        $goodsUserRow = db('goods_user')->where($where)->find();
        if (!empty($goodsUserRow) && $goodsUserRow['sale_price'] > 0) {
            $res = $goodsUserRow['sale_price'];
        }
        return $res;
    }

    
    /**
     * 计算订单总销售价
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
        $num = floatval($num);

        //查找商品销售价
        $goodsRow = db('goods')->field('goods_id, sale_price')->where('goods_id', $goods_id)->find();
        if (empty($goodsRow)) {
            return $res;
        }
        $price = $goodsRow['sale_price'];

        //如果设置了会员价
        if ($user_id) {
            $goodsUserPrice = $this->getGoodsUserPrice($goods_id, $user_id);
            if ($goodsUserPrice) {
                $this->final_price = $price = $goodsUserPrice;//修改最终成交价格为会员价
            }
        }

        $res = fnum($num*$price, 0, 2);
        return $res;
    }

    /**
     * 计算订单总成本价
     * @param int $num 购买数量
     * @param number $price 商品成本价格
     * @return number
     */
    public function getTotalCost($num=0, $price=0){
        $res = 0;
        if (empty($num)) {
            return $res;
        }
        if (empty($price)) {
            return $res;
        }
        $num = floatval($num);

        $res = $num*$price;
        $res = fnum($res, 0, 4);
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

    //获取分类列表
    public function getCatList($parent_id=0){
        $where = ['is_show'=>1];
        if (!empty($parent_id)) {
            $where['parent_id'] = $parent_id;
        }
        $field = 'cat_id, cat_name, parent_id, level, icon';
        $res = M('goods_cat')->field($field)->where($where)->order('sort')->select();
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
        $res = M('goods')->where($where)->find();

        //是否有会员价?
        $goodsUserPrice = $this->getGoodsUserPrice($goods_id, $user_id);
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

        $where['cat_id'] = ['IN', $cat_ids];
        $res = db('goods')->where($where)->select();
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