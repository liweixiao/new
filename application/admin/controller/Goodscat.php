<?php

/**
 *  
 * @file   Goodscat.php  
 * @date   2016-8-30 11:46:22 
 * @author Zhenxun Du<5552123@qq.com>  
 * @version    SVN:$Id:$ 
 */

namespace app\admin\controller;

use think\Loader;

class Goodscat extends Base {

    public function index() {
        $res = db('goods_cat')->order('sort asc')->select();

        $lists = nodeTree($res, 0, 0, 'cat_id', 'parent_id');
        // ee($lists);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /*
     * 查看
     */
    public function info() {

        $cat_id = input('cat_id');
        if ($cat_id) {
            //当前用户信息
            $info = db('goods_cat')->find($cat_id);
            $this->assign('info', $info);
        }

        //下拉菜单
        $this->assign('selectGoodscat', Loader::model('Goodscat')->selectGoodscat());
        return $this->fetch();
    }

    /*
     * 添加
     */

    public function add() {
        $data = input();
        //设置级别-level字段
        $data['level'] = 1;//默认是顶级
        if (!empty($data['parent_id'])) {
            $row_parent = db('goods_cat')->where(['cat_id'=>$data['parent_id']])->find();
            if ($row_parent) {
                $data['level'] = $row_parent['level'] + 1;
            }
        }

        if (!empty($data['cat_id'])) {
            $this->error('操作失败,cat_id应该为空');
        }
        // ee($data);
        $data['ctime'] = date('Y-m-d H:i:s');
        $res = model('Goodscat')->allowField(true)->insert($data);
        if ($res) {
            $this->success('操作成功', url('index'));
        } else {
            $this->error('操作失败');
        }
    }

    /*
     * 修改
     */

    public function edit() {

        $data = input();

        //设置级别-level字段
        $data['level'] = 1;//默认是顶级
        if (!empty($data['parent_id'])) {
            $row_parent = db('goods_cat')->where(['cat_id'=>$data['parent_id']])->find();
            if ($row_parent) {
                $data['level'] = $row_parent['level'] + 1;
            }
        }


        $data['mtime'] = date('Y-m-d H:i:s');
        $res = model('Goodscat')->allowField(true)->save($data, ['cat_id' => $data['cat_id']]);
        if ($res) {
            $this->success('操作成功', url('index'));
        } else {
            $this->error('操作失败');
        }
    }

    /*
     * 删除
     */

    public function del() {
        $cat_id = input('cat_id');
        $res = db('goods_cat')->where(['cat_id' => $cat_id])->delete();
        if ($res) {
            $this->success('操作成功', url('index'));
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 排序
     */
    public function setListorder() {

        if ($_POST['sort']) {
            $sort = $_POST['sort'];
            foreach ($sort as $k => $v) {
                $data = array();
                $data['sort'] = $v;
                $data['mtime'] = date('Y-m-d H:i:s');
                $res = db('goods_cat')->where(['cat_id' => $k])->update($data);
            }
            if ($res) {
                $this->success('操作成功', url('index'));
            } else {
                $this->error('操作失败');
            }
        }
    }

    /**
     * 获取紧接着的下一级分类ID
     */
    public function getSubCats() {
        $id = I('id');
        $map = ['parent_id'=>$id];
        $return = db('goods_cat')->field('cat_id as id, cat_name as name')->where($map)->select();
        if ($return) {
            $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','data' =>$return]);
        } else {
            $this->ajaxReturn(['status' => 0,'msg' =>'抱歉，数据获取失败']);
        }
    }

}
