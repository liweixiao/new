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

class Goods extends Common {

    public function index() {
        $this->redirect('Goods/list');
        return $this->fetch();
    }

    public function list() {
        $where = [];
        $rows = db("v_goods")->where($where)->order('goods_id')->paginate($this->showNum);
        // ee($rows->render());
        $this->assign('rows', $rows);
        return $this->fetch();
    }


    /*
     * 查看
     */
    public function info() {
        $id = input('id');
        if ($id) {
            //当前用户信息
            $info = db('goods')->find($id);
            $this->assign('info', $info);
        }
        return $this->fetch();
    }


    /*
     * 查看商品会员价
     */
    public function user_price_list() {
        $id = input('id');

        if (empty($id)) {
            $this->error('商品ID不能为空');
        }
        $where  = [];
        $where['goods_id'] = $id;

        //查看商品
        $row = db("v_goods")->where($where)->find();
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

}
