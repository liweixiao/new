<?php
/**
 * ============================================================================
 * * 版权所有 2020-2030 没事忙，并保留所有权利。
 * ----------------------------------------------------------------------------
 * 个人学习免费, 如果商业用途务必到官网购买授权.
 * ============================================================================
 * $Author: 没事忙 2015-08-23
 */ 
namespace app\home\controller;
use think\Controller;
use app\common\logic\ToolsLogic;

class Goods extends Base {
    public function _initialize() {
        parent::_initialize();
        $this->ToolsLogic = new ToolsLogic;
    }

    //商品列表,思路是先拿到大分类id，然后找到大分类下面所有子分类id-sub_cat_ids,然后拿这些ids去商品表查即可
    public function list(){
        $cat_id = I('id', 0);//商品分类id

        if (empty($cat_id)) {
            $this->error('地址有误!请检查');
        }

        //获取当前分类下面所有子分类
        // $catList = $this->ToolsLogic->getCatList($cat_id);

        $cat = $this->ToolsLogic->getCatRow($cat_id);

        //获取当前分类下面所有子分类ids
        $subCatIds = $this->ToolsLogic->getSubCatIds($cat_id);

        $rows = $this->ToolsLogic->getCatGoodsList($subCatIds);
        // ee($rows);
        $tags = $this->ToolsLogic->getAllTags('run_first', $cat_id);

        $this->assign('cat', $cat);
        $this->assign('rows', $rows);
        $this->assign('tags', $tags);
        return $this->fetch('list');
    }

    //平台ip121.199.15.68
    public function detail(){
        $goods_id = I('id', 0);//商品id
        $row = $this->ToolsLogic->getGoodsRow($goods_id, $this->user_id);
        if (empty($row)) {
            $this->error('非法请求');
        }

        $cat_id = $row['cat_id'];
        $cat = $this->ToolsLogic->getCatRow($cat_id);
        if (empty($cat)) {
            $this->error('抱歉，请联系管理员！');
        }

        $tags = $this->ToolsLogic->getAllTags('run_first', $cat_id);
        // ee($tags);
        $this->assign('row', $row);
        $this->assign('cat', $cat);
        $this->assign('tags', $tags);
        return $this->fetch('weibo1');
    }


}
