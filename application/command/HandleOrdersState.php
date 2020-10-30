<?php
/**
 * 更新订单状态-写评论的订单
 * 注：需要配置定时任务
 */
namespace app\command;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Log;
use app\common\logic\BaseLogic;

class HandleOrdersState extends Command
{
    protected function configure()
    {
        $this->setName('HandleOrdersState')->setDescription('处理订单状态--写评论订单状态');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("HandleOrdersState:");
        $ctime = time();
        $datetime = date('Y-m-d H:i:s');
        $resultInfo = [];
        $new_order_status = '';

        //平台订单状态
        //['1'=>'已完成', '2'=>'待处理', '3'=>'处理中', '4'=>'暂停中', '5'=>'余额不足', '6'=>'已退款', '7'=>'已作废'];//5=api余额不足
        //发布状态(0，停止；1，发布中；2，暂停中；3，排队中)
        $orderStateArr = ['0'=>'1', '1'=>'2', '2'=>'4','3'=>'3'];//api状态=>本站状态
        $month_start_time = date('Y-m-d H:i:s', strtotime('-1 month'));

        $wheres=[];
        $wheres['ctime'] = ['between', [$month_start_time, $datetime]];//刷新一个月以内的订单
        $wheres['order_status'] = ['IN', [2,3,4]];//只刷新这几种状态的订单

        //查询出所有未更新状态订单
        $rows = db('order')
            ->field('order_id, out_id, order_status, mtime')
            ->where($wheres)
            ->order('mtime')
            ->limit(5)
            ->select();
        // aa(db('order')->getLastSql());
        // aa($rows);

        if (empty($rows)) {
            exit('暂无订单需要更新!');
        }

        $BaseLogic = new BaseLogic;

        //获取供应商
        $supplier = $BaseLogic->getSupplier(5);
        if (empty($supplier)) {
            exit('抱歉，系统异常，请联系管理员！!');
        }

        //设置headers
        $headers = [
            'Content-Type:application/json; charset=UTF-8',
        ];
        $apiUserinfoRes = $BaseLogic->getSupplierToken($supplier);//获取用户信息,余额、评论单价等

        //获取token异常情况
        if ($apiUserinfoRes['error']) {
            exit($apiUserinfoRes['msg']);
        }

        $apiUserinfo = $apiUserinfoRes['data'];//返回用户信息,余额、评论单价等
        // aa($apiUserinfo);

        //提交参数
        $postdatas = [
            'uid'      => $apiUserinfo['uid'],//用户id
            'usersign' => $apiUserinfo['usersign'],//用户签名 
        ];

        $url_api = 'http://120.77.67.120:8081/api/syhz/tt/gettaskdetail';//此处写死

        foreach ($rows as $key => $row) {
            $out_id       = $row['out_id'];
            $order_id     = $row['order_id'];
            $order_status = $row['order_status'];
            //不更新情况
            if ($out_id == 0) {
                continue;
            }

            $postdatas['taskid'] = $row['out_id'];////任务编号
            $postdatasJson = json_encode($postdatas);//JSON_UNESCAPED_UNICODE
            $res_api = apiget($url_api, $postdatasJson, 'post', [], $headers);
            // aa($res_api);
            //异常情况
            if (empty($res_api) || !isset($res_api['code'])  || !isset($res_api['result']) || $res_api['code'] != 0) {
                $msg = $res_api['msg'] ?? "抱歉，任务获取出现异常(错误码00016)，请联系管理员";
                continue;//失败的时候这里继续下一个
            }


            if (!isset($res_api['result']['status'])) {
                $msg = $res_api['msg'] ?? "抱歉，任务获取出现异常(错误码00017)，请联系管理员";
                continue;//失败的时候这里继续下一个
            }

            if (!isset($res_api['result']['status'])) {
                continue;
            }

            //更新任务状态-值
            $apiStatus = $res_api['result']['status'];

            //将api订单状态转为本系统状态
            $new_order_status = $orderStateArr[$apiStatus] ?? '';

            if (!$new_order_status) {
                continue;
            }

            //状态不变则不用更新
            if ($new_order_status == $order_status) {
                continue;
            }

            //更新订单状态-状态不同的时候才执行更新
            $updateData = ['order_status'=>$new_order_status, 'mtime'=>$datetime];
            db('order')->where(['order_id'=>$order_id])->save($updateData);


            //开始获取评论列表(只有api状态为完成的时候才获取评论status=0)，并存储
            if ($apiStatus == 0) {
                //提交参数
                $postdatas2 = [
                    'uid'      => $apiUserinfo['uid'],//用户id
                    'usersign' => $apiUserinfo['usersign'],//用户签名 
                    'taskid'   => $out_id,
                    'status'   => 2,//所有评论
                ];

                $url_api2 = 'http://120.77.67.120:8081/api/syhz/tt/querycommlist';//获取评论url
                $postdatasJson2 = json_encode($postdatas2);//JSON_UNESCAPED_UNICODE
                $res_api2 = apiget($url_api2, $postdatasJson2, 'post', [], $headers);
                // aa($res_api2);

                //保存评论数据
                if (!empty($res_api2['result']['data'])) {
                    $commentsDatas = json_encode($res_api2['result']['data'], JSON_UNESCAPED_UNICODE);

                    $field = [
                        'content' => $commentsDatas,
                    ];

                    $comment = db('task_comments')->where(['order_id'=>$order_id])->count();

                    if (!$comment) {
                        $field['ctime'] = $datetime;
                        $field['order_id'] = $order_id;
                        $field['out_id'] = $out_id;
                        db('task_comments')->insert($field);
                    }else{
                        $field['mtime'] = $datetime;
                        db('task_comments')->where(['order_id'=>$order_id])->update($field);
                    }
                }
            }


            // $resultInfo[] = ['dotime'=>$datetime, 'order_id'=>$order_id];
            $output->writeln("{$order_id},");
        }

        // Log::info('[ HandleOrdersState ] ' . json_encode($resultInfo));//日志记录
    }
}