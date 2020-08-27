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
