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
        $tag = I('tag/d', 0);//商品tag
        $params = I('get.');//参数

        if (empty($cat_id)) {
            $this->error('地址有误!请检查');
        }

        //获取当前分类下面所有子分类
        // $catList = $this->ToolsLogic->getCatList($cat_id);

        $cat = $this->ToolsLogic->getCatRow($cat_id);

        //获取当前分类下面所有子分类ids
        $subCatIds = $this->ToolsLogic->getSubCatIds($cat_id);

        // $rows = $this->ToolsLogic->getCatGoodsList($subCatIds);
        $rows = $this->ToolsLogic->getCatGoodsListByUser($subCatIds, $this->user_id, $params);
        $this->ToolsLogic->delFields($rows, ['out_url']);//剔除字段
        // ee($rows);
        $tags = $this->ToolsLogic->getAllTags('goods_tag', $cat_id);

        $this->assign('cat', $cat);
        $this->assign('rows', $rows);
        $this->assign('tags', $tags);
        return $this->fetch('list');
    }

    //商品详情
    public function detail(){
        $goods_id = I('id', 0);//商品id
        $row = $this->ToolsLogic->getGoodsRow($goods_id, $this->user_id);
        $this->ToolsLogic->delFields($row, ['out_url'], 1);//剔除字段

        if (empty($row)) {
            $this->error('非法请求');
        }

        //获取商品模板
        $goodsTemplate = $row['tpl'];
        if (empty($goodsTemplate)) {
            $this->error('抱歉，商品模板配置有误，请联系管理员！');
        }

        $cat_id = $row['cat_id'];
        $cat = $this->ToolsLogic->getCatRow($cat_id);
        if (empty($cat)) {
            $this->error('抱歉，请联系管理员！');
        }

        $tags = $this->ToolsLogic->getAllTags('', 0, false);//第三个参数为false则获取所有字段，这在模板里面要注意
        // ee($tags);
        // ee($row);
        $shop_info = tpCache('shop_info');
        $this->assign('shop_info',$shop_info);
        $this->assign('row', $row);
        $this->assign('cat', $cat);
        $this->assign('tags', $tags);
        return $this->fetch($goodsTemplate);
    }


}
