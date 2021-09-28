<?php

/**
 *  
 * @file   Shareprofit.php  
 * @date   2016-9-1 15:48:53 
 * @author Zhenxun Du<5552123@qq.com>  
 * @version    SVN:$Id:$ 
 */

namespace app\admin\model;

use think\Model;

class Shareprofit extends Model {
    public $table = 'tp_share_profit';

    /**
     * 校验分润
     * 校验逻辑(8号之前校验前6个月分润情况,8号之后则校验当月的分润情况)
     * 说明:如果想快速跳过验证有两个方法,1修改admin配置,2添加分润月份
     * @return type
     */
    public function checkShareProfit() {
        $res = ['error'=>0, 'msg'=>'ok'];
        $shareDay = 8;//默认8号分润
        $curDay = date('j');//月份中的第几天，没有前导零
        $preXMonth = 6;//超过$shareDay之后统计前N个月分润

        //不校验情况
        $is_check_share_profit = config('is_check_share_profit');
        if(!$is_check_share_profit){
            return $res;
        }

        $where['is_deleted'] = 0;
        if($curDay > $shareDay){
            //8号之后
            $curMonth = date('Y-m-00');
            $where['ptime'] = $curMonth;
            $row = db('share_profit')->where($where)->find();
            if(!$row){
                $res = ['error'=>1, 'msg'=>"抱歉，无法完成操作，按流程当月分润需要在{$shareDay}号之前完成~"];
                return $res;
            }
        }else{
            //生成最近6个月份数组
            $pre_xmonth_arr = [];
            for ($i=1; $i < $preXMonth+1; $i++) { 
                $pre_xmonth_arr[] = date('Y-m-00', strtotime("-$i months"));
            }
            // ee($pre_xmonth_arr);

            //获取最近6个月分润
            $last_month = date('Y-m-00', strtotime("-1 months"));
            $where['ptime'] = ['ELT', $last_month];
            $latestShareMonths = db('share_profit')->where($where)->order('ptime desc')->limit($preXMonth)->column('ptime');
            // sql();
            // ee($latestShareMonths);

            $notShareMonths = array_diff($pre_xmonth_arr, $latestShareMonths);
            // ee($notShareMonths);

            if(!empty($notShareMonths)){
                foreach ($notShareMonths as $key => $value) {
                    $notShareMonths[$key] =  date('Y/m', strtotime(preg_replace('/-00$/', '', $value)));//2021-03-00容易被翻译成2021-02，所以采取正则处理
                }
                $notShareMonths = implode('、', $notShareMonths);
                $res = ['error'=>1, 'msg'=>"抱歉，<b>{$notShareMonths}月</b>尚未完成分润，无法完成操作~"];
                return $res;
            }

        }
        return $res;
    }

}
