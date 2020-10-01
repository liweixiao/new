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
use app\common\logic\GoodscatLogic;

class Goods extends Base {

    public function index() {
        $this->redirect('Goods/list');
        return $this->fetch();
    }

    public function list() {
        $sortType = I('sort/d', 0);
        $keyword = I('keyword/s', '');
        $where = [];

        $sort = 'sort, goods_id';//默认排序
        $sortRtn = ['1'=>'sale_price', '2'=>'sale_price desc', '3'=>'user_price', '4'=>'user_price desc', '5'=>'cost_price', '6'=>'cost_price desc'];
        if (!empty($sortType)) {
            $sort = $sortRtn[$sortType] ?? $sort;
        }

        //根据会员账号查找
        if (!empty($keyword)) {
            $where['goods_name|desc'] = ['like', "%{$keyword}%"];
        }

        $rows = db("v_goods")->where($where)->order($sort)->paginate($this->showNum);
        // ee($rows->render());
        // ee($rows);
        $this->assign('rows', $rows);
        return $this->fetch();
    }


    /**
     * 添加修改商品
     */
    public function info(){
        $id = I('goods_id');
        $OrderLogic = new OrderLogic();
        $GoodscatLogic = new GoodscatLogic();
        if (IS_POST) {
            $data = I('post.');
            // ee($data);
            $ctime = date('Y-m-d H:i:s');

            if (empty($data['goods_name'])) {
                $this->error('操作失败，商品名称必须填写');
            }

            //相关标签数组转为逗号分隔
            $OrderLogic->arr2string($data,['tag_ids']);
            
            if ($id) {
                $data['mtime'] = $ctime;
                $res = db('goods')->where(['goods_id'=>$id])->update($data);
            } else {
                $data['ctime'] = $ctime;
                $res = db('goods')->insert($data);
            }

            if (!$res) {
                $this->error('操作失败');
            }
            $this->success('操作成功', url('goods/list'));
        }

        //获取供应商
        $supplierList = $OrderLogic->getSupplierList();

        //获取标签
        $tags = $OrderLogic->getAllTags('goods_tag');


        //获取商品配置
        $goodsConfigList = $OrderLogic->getGoodsConfigList();

        $row = db('v_goods')->where(['goods_id'=>$id])->find();
        if ($row) {
            $row['tag_ids_arr'] = css2array($row['tag_ids']);
        }

        //获取已选择分类ID
        $cat_id_arr = $GoodscatLogic->getSelectedCatIds($row['cat_id']);
        $this->assign('cat_id_ids', implode('|', $cat_id_arr));

        // sql();
        // ee($row);
        $this->assign('row', $row);
        $this->assign('tags', $tags);
        $this->assign('supplierList', $supplierList);
        $this->assign('goodsConfigList', $goodsConfigList);
        return $this->fetch();
    }


    /*
     * 查看商品会员价
     */
    public function user_price_list() {
        $id = input('id');//goods_id
        $user_id = input('uid');//会员id
        $row = [];

        $where  = [];

        //指定商品id
        if (!empty($id)) {
            $where['goods_id'] = $id;
            //查看商品
            $row = db("v_goods")->where($where)->find();
        }

        //指定会员id
        if (!empty($user_id)) {
            $where['user_id'] = $user_id;
            $row = db("users")->where($where)->find();
        }

        $rows = db('v_goods_user')->where($where)->order('goods_user_id')->paginate($this->showNum);
        // ee($rows);
        $this->assign('rows', $rows);
        $this->assign('row', $row);
        return $this->fetch();
    }


    /*
     * 添加商品会员价
     */
    public function add_user_price() {
        $goods_user_id = input('goods_user_id');
        $goods_id      = input('goods_id');

        if (empty($goods_id)) {
            exit('商品ID不能为空');
        }
        $goods_user = [];
        $where  = [];
        $where['goods_id'] = $goods_id;

        //商品
        $row = db('v_goods')->where($where)->find();

        //商品会员价
        if (!empty($goods_user_id)) {
            $goods_user = db('v_goods_user')->where('goods_user_id', $goods_user_id)->find();
        }

        $this->assign('row', $row);
        $this->assign('goods_user', $goods_user);
        return $this->fetch();
    }

    /*
     * 添加商品会员价-动作
     */
    public function do_add_user_price() {
        $res = ['error'=>0, 'msg'=>'操作成功！'];
        $goods_user_id = input('goods_user_id');
        $user_id       = input('user_id');
        $goods_id      = input('goods_id');
        $sale_price    = input('sale_price');

        if (empty($user_id)) {
            $res = ['error'=>1, 'msg'=>'错误，用户id参数不能为空！'];
            $this->ajaxReturn($res);
        }
        if (empty($sale_price) || $sale_price <= 0 ) {
            $res = ['error'=>1, 'msg'=>'错误，会员价不能为空'];
            $this->ajaxReturn($res);
        }

        $row = db('users')->find($user_id);
        if (empty($row)) {
            $res = ['error'=>1, 'msg'=>'错误，用户不存在！'];
            $this->ajaxReturn($res);
        }

        $data               = [];
        $data['user_id']    = $user_id;
        $data['goods_id']   = $goods_id;

        //检测是否设置过了
        $goods_user = db('goods_user')->where($data)->find();
        if ($goods_user) {
            $goods_user_id = $goods_user['goods_user_id'];
        }
        

        $data['sale_price'] = $sale_price;
        if (!empty($goods_user_id)) {
            $result = db('goods_user')->where('goods_user_id', $goods_user_id)->update($data);
        }else{
            $result = db('goods_user')->insert($data);
        }

        if (!$result) {
            $res = ['error'=>1, 'msg'=>'操作失败'];
            $this->ajaxReturn($res);
        }

        $this->ajaxReturn($res);
    }

    //设置用户私有商品
    public function set_user_goods() {
        $sortType = I('sort/d', 0);
        $keyword = I('keyword/s', '');
        $user_id = I('uid/d', 0);
        $where = [];

        $user = db("users")->where(['user_id'=>$user_id])->find();
        if (empty($user)) {
            $this->error('用户不存在');
        }

        $sort = 'sort, goods_id';//默认排序
        $sortRtn = ['1'=>'sale_price', '2'=>'sale_price desc', '3'=>'user_price', '4'=>'user_price desc', '5'=>'cost_price', '6'=>'cost_price desc'];
        if (!empty($sortType)) {
            $sort = $sortRtn[$sortType] ?? $sort;
        }

        //根据会员账号查找
        if (!empty($keyword)) {
            $where['goods_name|desc'] = ['like', "%{$keyword}%"];
        }

        $rows = db("v_goods")->alias('g')
                ->field('g.*, gu.goods_user_id, gu.sale_price as user_sale_price')
                ->join('goods_user gu', "g.goods_id=gu.goods_id and gu.user_id={$user_id}", 'LEFT')
                ->where($where)
                ->order($sort)
                ->paginate($this->showNum);
        // ee($rows->render());
        // ee($rows);
        $this->assign('rows', $rows);
        $this->assign('user', $user);
        return $this->fetch();
    }


    /*
     * 设置商品会员价-动作
     */
    public function doSetUserPrice() {
        $res = ['error'=>0, 'msg'=>'操作成功！'];
        $goods_user_id = input('goods_user_id');
        $user_id       = input('user_id');
        $goods_id      = input('goods_id');
        $sale_price    = input('sale_price');

        if (empty($user_id)) {
            $res = ['error'=>1, 'msg'=>'错误，用户id参数不能为空！'];
            $this->ajaxReturn($res);
        }
        if ($sale_price < 0) {
            $res = ['error'=>1, 'msg'=>'错误，会员价不能为空'];
            $this->ajaxReturn($res);
        }

        $row = db('users')->find($user_id);
        if (empty($row)) {
            $res = ['error'=>1, 'msg'=>'错误，用户不存在！'];
            $this->ajaxReturn($res);
        }

        $ctime = date('Y-m-d H:i:s');
        $data               = [];
        $data['user_id']    = $user_id;
        $data['goods_id']   = $goods_id;

        //检测是否设置过了
        $goods_user = db('goods_user')->where($data)->find();
        if ($goods_user) {
            $goods_user_id = $goods_user['goods_user_id'];
        }
        

        $data['sale_price'] = $sale_price;
        if (!empty($goods_user_id)) {
            $data['ctime'] = $ctime;
            $result = db('goods_user')->where('goods_user_id', $goods_user_id)->update($data);
        }else{
            $data['mtime'] = $ctime;
            $result = db('goods_user')->insert($data);
        }

        if (!$result) {
            $res = ['error'=>1, 'msg'=>'操作失败'];
            $this->ajaxReturn($res);
        }

        $this->ajaxReturn($res);
    }

    /*
     * 删除
     */

    public function del() {
        $id = input('id');
        $row = db('goods')->where(['goods_id' => $id])->find();
        $res = db('goods')->where(['goods_id' => $id])->delete();
        if ($res) {
            db('goods_cat')->where(['cat_id'=>$row['cat_id']])->delete();
            $this->success('操作成功', url('index'));
        } else {
            $this->error('操作失败');
        }
    }

}
