<?php
/**
 * 更新订单状态-写评论的订单
 * 注：需要配置定时任务
 */
namespace app\command;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
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
        $new_order_status = 3;

        //平台订单状态
        //['1'=>'已完成', '2'=>'待处理', '3'=>'处理中', '4'=>'暂停中', '5'=>'余额不足', '6'=>'已退款', '7'=>'已作废'];//5=api余额不足
        //发布状态(0，停止；1，发布中；2，暂停中；3，排队中)
        $orderStateArr = ['0'=>'1', '1'=>'3', '2'=>'4','3'=>'3'];//api状态=>本站状态
        $month_start_time = date('Y-m-d H:i:s', strtotime('-1 month'));

        $wheres                 = [];
        $wheres['ctime']        = ['between', [$month_start_time, $datetime]];//刷新一个月以内的订单
        $wheres['order_status'] = ['IN', [2,3,4]];//只刷新这几种状态的订单
        $wheres['supplier_id']  = 5;//只刷新写评论的订单

        //查询出所有未更新状态订单
        $rows = db('order')
            ->field('order_id, out_id, goods_config_id, order_status, mtime')
            ->where($wheres)
            ->order('mtime')
            ->limit(5)
            ->select();
        // aa(db('order')->getLastSql());
        // aa($rows);

        if (empty($rows)) {
            $output->writeln('暂无订单需要更新');
            exit;
        }

        $BaseLogic = new BaseLogic;

        //获取供应商
        $supplier = $BaseLogic->getSupplier(5);
        if (empty($supplier)) {
            $output->writeln('抱歉，系统异常！');
            exit;
        }

        //设置headers
        $headers = [
            'Content-Type:application/json; charset=UTF-8',
        ];
        $apiUserinfoRes = $BaseLogic->getSupplierToken($supplier);//获取用户信息,余额、评论单价等

        //获取token异常情况
        if ($apiUserinfoRes['error']) {
            $output->writeln($apiUserinfoRes['msg']);
            exit;
        }

        $apiUserinfo = $apiUserinfoRes['data'];//返回用户信息,余额、评论单价等
        // aa($apiUserinfo);

        //提交参数
        $postdatas = [
            'uid'      => $apiUserinfo['uid'],//用户id
            'usersign' => $apiUserinfo['usersign'],//用户签名 
        ];
        // aa($rows);

        // 启动事务
        try{
            foreach ($rows as $key => $row) {
                $out_id       = $row['out_id'];
                $order_id     = $row['order_id'];
                $order_status = $row['order_status'];
                $task_num     = $row['task_num'];

                //不更新情况
                if ($out_id == 0) {
                    $output->writeln("抱歉，订单号{$order_id}，out_id为0");
                    continue;
                }

                //获取商品配置(第三方配置)
                $goodsCfg = db('goods_config')->where(['goods_config_id'=>$row['goods_config_id']])->find();
                if (empty($goodsCfg)) {
                    $output->writeln("抱歉，订单号{$order_id}，商品配置暂时有误");
                    continue;
                }

                $url_api = $goodsCfg['url_get_order_row'];
                $postdatas['taskid'] = $row['out_id'];////任务编号
                $postdatasJson = json_encode($postdatas);//JSON_UNESCAPED_UNICODE
                $res_api = apiget($url_api, $postdatasJson, 'post', [], $headers);
                // aa($res_api);
                //异常情况
                if (empty($res_api) || !isset($res_api['code'])  || !isset($res_api['result']) || $res_api['code'] != 0) {
                    $output->writeln("抱歉，订单号{$order_id}，任务获取出现异常(错误码00016)");
                    continue;
                }


                if (!isset($res_api['result']['status'])) {
                    $output->writeln("抱歉，订单号{$order_id}，任务获取出现异常(错误码00017)");
                    continue;
                }

                if (!isset($res_api['result']['status'])) {
                    $output->writeln("抱歉，订单号{$order_id}，未能提供status字段(错误码00018)");
                    continue;
                }

                ///获取评论列表以及数量
                //提交参数
                $postdatas2 = [
                    'uid'      => $apiUserinfo['uid'],//用户id
                    'usersign' => $apiUserinfo['usersign'],//用户签名 
                    'taskid'   => $out_id,
                    'status'   => 2,//所有评论
                ];

                $url_api2 = $goodsCfg['url_get_order_row1'];//获取评论url
                $postdatasJson2 = json_encode($postdatas2);//JSON_UNESCAPED_UNICODE
                $res_api2 = apiget($url_api2, $postdatasJson2, 'post', [], $headers);
                // aa($res_api2);

                if (empty($res_api2['result']['data'])) {
                    $output->writeln("抱歉，写评论获取失败：为空，订单号{$order_id}(错误码00020)");
                    continue;
                }

                $done_num = count($res_api2['result']['data']);//已经评论的数量

                //如果没有任何评论写出来，则下面程序什么都不做
                if (!$done_num) {
                    $output->writeln("评论列表为空，订单号{$order_id}(错误码00201)");
                    continue;
                }

                if ($done_num >= $task_num) {
                    $new_order_status = 1;//这种情况为已完成状态
                }

                //更新订单状态-状态不同的时候才执行更新
                $updateData = ['order_status'=>$new_order_status, 'mtime'=>$datetime];
                $res = db('order')->where(['order_id'=>$order_id])->save($updateData);


                //开始获取评论列表(只有api状态为完成的时候才获取评论status=0 && 且是写评论而不是人工互助)，并存储
                //这里根据api的评论量来确定是否更新
                if ($row['goods_config_id'] == 5) {

                    //保存评论数据
                    $commentsDatas = json_encode($res_api2['result']['data'], JSON_UNESCAPED_UNICODE);

                    $field = [
                        'content' => $commentsDatas,
                    ];

                    $comment = db('task_comments')->where(['order_id'=>$order_id])->find();

                    if (!$comment) {
                        $field['ctime'] = $datetime;
                        $field['order_id'] = $order_id;
                        $field['out_id'] = $out_id;
                        $res = db('task_comments')->insert($field);
                        if (!$res) {
                            $output->writeln("抱歉，评论内容写入失败，订单号{$order_id}(错误码00021)");
                            continue;
                        }
                    }else{
                        //这里得看情况，是否需要更新，如果评论数量不变则不更新
                        $commentsCount = json_encode($comment['content'], true);

                        //无需更新情况
                        if ($done_num <= $commentsCount) {
                            $output->writeln("无需更新，评论数量未变化，订单号{$order_id}(错误码00221)");
                            continue;
                        }

                        $field['mtime'] = $datetime;
                        $res = db('task_comments')->where(['order_id'=>$order_id])->update($field);
                        if (!$res) {
                            $output->writeln("抱歉，评论内容更新失败，订单号{$order_id}(错误码00022)");
                            continue;
                        }
                    }
                }

                // $resultInfo[] = ['dotime'=>$datetime, 'order_id'=>$order_id];
                $output->writeln("完成订单:{$order_id},");
            }
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
        }

        // Log::info('[ HandleOrdersState ] ' . json_encode($resultInfo));//日志记录
    }
}