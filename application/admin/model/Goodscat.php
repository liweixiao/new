<?php

/**
 *  
 * @file   Goodscat.php  
 * @date   2016-9-1 15:48:53 
 * @author Zhenxun Du<5552123@qq.com>  
 * @version    SVN:$Id:$ 
 */

namespace app\admin\model;

use think\Model;

class Goodscat extends Model {
    public $table = 'tp_goods_cat';
    public $is_show = array('1' => '显示', '2' => '不显示');

    /**
     * 获取当前方法名
     * @return type
     */
    public function getName() {
        $where = array();
        $where['c'] = request()->controller();
        $where['a'] = request()->action();
        $res = db('Menu')->where($where)->field('id,name,parentid')->find();
        return $res['name'];
    }

    public function getInfo() {
        $where = array();
        $where['c'] = request()->controller();
        $where['a'] = request()->action();
        $res = db('Menu')->where($where)->field('id,name,parentid')->find();
        return $res;
    }

    /**
     * 获取前当标题
     * @return type
     */
    public function getTitle() {
        $info = db('Menu')->getInfo();
        $title = '';
        if ($info->parentid) {
            $parentName = db('Menu')->where('id', $info->parentid)->value('name');

            $title = $parentName . '  <small><i class="ace-icon fa fa-angle-double-right"></i> ' . $info['name'] . '</small>';
        } else {
            $title = $info['name'];
        }
        return $title;
    }

    /**
     * 获取上级方法名
     * @return boolean
     */
    public function getParentNname() {

        $info = $this->getInfo();
        if ($info->parentid) {
            return db('Menu')->where('id', $info->parentid)->value('name');
        } else {
            return false;
        }
    }

    /**
     * 择选栏目
     */
    public function selectGoodscat() {
        $res = db('goods_cat')
                ->field('cat_id,cat_name,parent_id')
                ->order('sort asc')
                ->select();
        $tmpArr = nodeTree($res, 0, 0, 'cat_id', 'parent_id');

        $data = array();
        foreach ($tmpArr as $k => $v) {
            $cat_name = $v['level'] == 0 ? '<b>' . $v['cat_name'] . '</b>' : '├─' . $v['cat_name'];

            $cat_name = str_repeat("│        ", $v['level']) . $cat_name;
            $data[$v['cat_id']] = $cat_name;
        }
        // dump($data);
        //exit;
        return $data;
    }

    /**
     * 所有菜单
     * @return type
     */
    public function allGoodscat() {
        $res = db('goods_cat')
                ->field('cat_id,cat_name,parent_id')
                ->order('sort asc')
                ->select();
        return nodeTree($res, 0, 0, 'cat_id', 'parent_id');
    }

    /**
     * 我的菜单
     * @param type $user_id
     * @param type $is_show 
     * @return array
     */
    public function getMyGoodscat($user_id, $is_show = null) {
        $where = array();
        if ($user_id != 1) {
            $res = db('admin_group_access')
                    ->alias('t1')
                    ->field('t2.rules')
                    ->join(config('database.prefix').'admin_group t2', 't1.group_id=t2.id', 'left')
                    ->where(['t1.uid' => $user_id])
                    ->select();
            if (!$res) {
                return false;
            }
            $tmp = '';
            foreach ($res as $k => $v) {
                $tmp .=$v['rules'] . ',';
            }

            $goods_cat_ids = trim($tmp, ',');
            $where['id'] = ['in', $goods_cat_ids];
        }


        if ($is_show) {
            $where['is_show'] = $is_show;
        }

        $res = db('goods_cat')->where($where)->order('sort asc')->select();

        return $res;
    }

}
