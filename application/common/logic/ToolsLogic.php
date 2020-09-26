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
class ToolsLogic extends BaseLogic{

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
    * 推送消息-用户行为
    * @param array $params
    */
    public function pushMessage($params = []){
        $res = ['error'=>0, 'msg'=>'恭喜，提醒成功'];
        $type = $params['type'];//推送类型

        $typeArr = ['cz'];//cz充值

        if (!in_array($type, $typeArr)) {
            return ['error'=>1, 'msg'=>'通知类型参数非法'];

        }

        switch ($type) {
            case 'cz':
                //充值订单编号信息
                $order_sn = $params['order_sn'];
                $msg = "【用户充值】订单编号：{$order_sn}";
                //提醒-充值
                break;
            
            default:
                # code...
                break;
        }

        //开始提醒
        $kefu_qq_arr = config('kefu_qq');
        foreach ($kefu_qq_arr as$qq) {
            $res_push = $this->qqpusher(['qq'=> $qq, 'msg'=>$msg]);
        }

        return $res;
    }

}