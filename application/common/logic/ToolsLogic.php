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
 * 用户逻辑定义
 * Class UsersLogic
 * @package Home\Logic
 */
class ToolsLogic{
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


    //创建订单
    public function createOrder($params = []){
        $res = ['error'=>0, 'msg'=>'恭喜，提交成功！'];
        $ctime = date('Y-m-d H:i:s');
        $data = [];
        $request = \think\Request::instance();

        $user = session('user') ?? [];

        //可供检测重复提交数据使用
        $data['cat_id'] = $cat_id = $params['cat_id'] ?? 0;
        $data['user_id'] = $user['user_id'] ?? 0;
        $data['url'] = $params['url'] ?? '';


        $data['type'] = $type = $params['type'] ?? '';

        $relationArr = M('items_label')->where(['type'=>'user_feedback'])->getField('label_id,tag');
        //若反馈类型不对,则异常
        if (!in_array($type, array_keys($relationArr))) {
            return ['error'=>1, 'msg'=>'类型错误！请联系管理员'];
        }

        if (!check_mobile($mobile)) {
            return ['error'=>1, 'msg'=>'手机号格式错误'];
        }
        $row = Db::name("items_feedback")->where($data)->where(['state'=>'2'])->find();
        if ($row) {
            return ['error'=>1, 'msg'=>'请勿重复提交'];
        }

        $data['ctime'] = $ctime;
        $data['ip'] = $request->ip();
        Db::name("items_feedback")->insert($data);
        return $res;
    }


}