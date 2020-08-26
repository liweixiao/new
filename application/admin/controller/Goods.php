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
        $rows = db("v_goods")->where($where)->order('goods_id desc')->paginate($this->showNum);
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

}
