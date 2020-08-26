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

class User extends Common {

    public function index() {
        $this->redirect('User/list');
        return $this->fetch();
    }

    public function list() {
        $where = [];
        $rows = db("v_user")->where($where)->order('user_id desc')->paginate($this->showNum);
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
            $info = db('v_user')->where('user_id', $id)->find();
            $this->assign('info', $info);
        }
        return $this->fetch();
    }

    /*
     * 查看
     */
    public function addmoney() {

        $user_id = input('user_id');
        if ($user_id) {
            //当前用户信息
            $row = db('v_user')->where('user_id', $user_id)->find();
            $this->assign('row', $row);
        }
        return $this->fetch();
    }

    /*
     * 会员充值
     */
    public function do_addmoney() {
        $res = ['error'=>0, 'msg'=>'充值成功！'];
        $user_id = input('user_id');
        $recharge = input('recharge');//充值金额

        if (empty($user_id)) {
            $res = ['error'=>1, 'msg'=>'错误，用户id参数不能为空！'];
            $this->ajaxReturn($res);
        }
        if (empty($recharge) || $recharge <= 0 ) {
            $res = ['error'=>1, 'msg'=>'错误，充值金额必须大于0！'];
            $this->ajaxReturn($res);
        }

        $row = db('users')->find($user_id);
        if (empty($row)) {
            $res = ['error'=>1, 'msg'=>'错误，用户不存在！'];
            $this->ajaxReturn($res);
        }

        if ($row['is_lock'] == 1) {
            $res = ['error'=>1, 'msg'=>'抱歉，当前用户账户被冻结！无法充值。'];
            $this->ajaxReturn($res);
        }

        // 启动事务
        Db::startTrans();
        try{
            //开始充值
            $where['user_id']                = $user_id;
            $update_data['user_total_money'] = ['exp',"user_total_money+{$recharge}"];
            $update_data['user_money']       = ['exp',"user_money+{$recharge}"];
            $result = db('users')->where($where)->update($update_data);

            //添加充值记录
            $account_log = [
                'user_id'      => $user_id,
                'change_money' => $recharge,
                'desc'         => '用户充值',
                'operator'     => $this->user_id,
                'type'         => 2,//充值
            ];
            $result_log = add_account_log($account_log);

            // 提交事务
            Db::commit();
        }catch(\Exception $e) {
           // 回滚事务
           Db::rollback();
           return ['error'=>1, 'msg'=>$e->getMessage()];
        }

        if ($id) {
            //当前用户信息
            $info = db('v_user')->where('user_id', $id)->find();
            $this->assign('info', $info);
        }
        $this->ajaxReturn($res);
    }

}
