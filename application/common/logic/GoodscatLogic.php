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
use app\common\logic\BaseLogic;
use think\Image;

/**
 * 用户逻辑定义
 * Class UsersLogic
 * @package Home\Logic
 */
class GoodscatLogic extends BaseLogic{

    /**
     * 缩略图片-根据配置文件大小数组
     * @param $file
     */
    public function thumbs($file){
        $image = new Image();
        $thumb_config = config('thumbSize');

        $res = ['error' => 0, 'msg' => '操作成功'];
        if (!is_file('.' . $file)) {
            return ['error' => 1, 'msg' => '文件不能为空'];
        }

        $info = pathinfo($file);
        $file = '.' . $file;
        $image->open($file);
        foreach ($thumb_config as $k => $wh) {
            $fileName = '.' . $info['dirname'] . '/' . $info['filename'] . '_' . $k . '.' . $info['extension'];
            $image->thumb($wh['w'], $wh['h'])->save($fileName);
        }
        return $res;
    }

    /**
     * 根据已经选择的cat_id，获取父亲、爷爷、曾爷爷的cat_id，注意这里默认不返回省id
     * @return array
     */
    function getSelectedCatIds($cat_id, $is_get_level1=true)
    {
        $res = [];//省市县镇id数组
        if (empty($cat_id)) {
            return $res;
        }

        $row = db('goods_cat')->where(['cat_id'=>$cat_id])->find();
        // ee($row);
        if (empty($row)) {
            return $res;
        }
        if ($row['level'] == 1) {
            if ($is_get_level1) {
                $res[] = $cat_id;
            }
            return $res;
        }

        //先把自己装进来
        $res[] = $cat_id;

        //开始向上遍历查找
        for ($i=0; $i < 4; $i++) {
            $parent_id = $row['parent_id'];
            $row = db('goods_cat')->where(['cat_id'=>$parent_id])->find();
            if (empty($row)) {
                return $res;
            }

            //获取到最上级则终止
            if ($row['level'] == 1 || $row['parent_id'] == 0) {
                if ($is_get_level1) {
                    $res[] = $row['cat_id'];
                }
                break;
            }

            $res[] = $row['cat_id'];
        }
        $res = array_reverse($res);
        return $res;
    }

}