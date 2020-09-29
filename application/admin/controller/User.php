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
use app\common\logic\UsersLogic;

class User extends Base {

    public function index() {
        $this->redirect('User/list');
        return $this->fetch();
    }

    public function list() {
        $where = [];
        $rows = db("v_user")->where($where)->order('user_id desc')->paginate($this->showNum);
        $page = $rows->render();

        // ee($rows->render());

        $rows = $rows->toArray();//转换为数组
        $rows = $rows['data'];//取数据
        foreach ($rows as $key => $row) {
            //会员独享价统计-统计该会员设置了多少独享价商品
            $goods_user_num = db("goods_user")->where(['user_id'=>$row['user_id']])->count();
            $rows[$key]['goods_user_num'] = $goods_user_num;
        }

        //获取会员级别
        $user_level = db('user_level')->column('level_name', 'level_id');
        $this->assign('user_level', $user_level);

        // ee($rows);
        $this->assign('page', $page);
        $this->assign('rows', $rows);
        return $this->fetch();
    }


    /*
     * 查看
     */
    public function info() {
        $id = input('user_id');
        if (IS_POST) {
            $data = I('post.');
            // ee($data);
            $ctime = date('Y-m-d H:i:s');

            if (empty($data['mobile'])) {
                $this->error('操作失败，用户手机号必须填写');
            }

            if ($id) {
                $data['mtime'] = $ctime;
                $res = db('users')->where(['user_id'=>$id])->update($data);
            } else {
                $data['ctime'] = $ctime;
                $res = db('users')->insert($data);
            }

            if (!$res) {
                $this->error('操作失败');
            }
            $this->success('操作成功', url('user/list'));
        }

        //获取会员级别
        $user_level = db('user_level')->column('level_name', 'level_id');
        $this->assign('user_level', $user_level);

        if ($id) {
            //当前用户信息
            $row = db('v_user')->where('user_id', $id)->find();
            $this->assign('row', $row);
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
     * 重置会员密码(规则为:会员手机号后面加0000)
     */
    public function resetPassword(){
        //检查是否第三方登录用户
        $user_id = input('user_id');
        $reset_password_rule = config('reset_password_rule');

        $res = ['error'=>0, 'msg'=>"OK，新密码为【会员手机号+{$reset_password_rule}】"];
        $ctime = date('Y-m-d H:i:s');
        $user = db('users')->where(['user_id'=>$user_id])->find();
        if (!$user) {
            $this->ajaxReturn(['error'=>1, 'msg'=>'用户不存在']);
        }
        if (empty($user['mobile'])) {
            $this->ajaxReturn(['error'=>1, 'msg'=>'用户暂未设置手机号,无法操作']);
        }
        $newPassword = encrypt($user['mobile'] . $reset_password_rule);
        // ee($newPassword);
        $updateDatas = ['password'=>$newPassword, 'mtime'=>$ctime];
        $result = db('users')->where(['user_id'=>$user_id])->update($updateDatas);
        if (!$result) {
            $this->ajaxReturn(['error'=>1, 'msg'=>'抱歉，操作失败，请联系管理员']);
        }
        $this->ajaxReturn($res);

    }



    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(input('search_key'));
        if ($search_key == '') $this->ajaxReturn(['status' => -1, 'msg' => '请按要求输入！！']);
        $where = ['nickname|mobile' => ['like', "%$search_key%"]];
        $list = db('users')->field('user_id, mobile as nickname')->where($where)->select();
        if ($list) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'result' => $list]);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '未查询到相应数据！！']);
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
                'befor_money'  => $row['user_money'],
                'desc'         => '用户充值-系统',
                'operator'     => $this->user_id,
                'type'         => 2,//充值
            ];
            $result_log = add_account_log($account_log);

            //充值成功后把此会员充值提醒去掉(即account_log与此会员有关的state置为1)
            db('account_log')->where(['user_id'=>$user_id, 'type'=>2])->update(['state'=>1]);

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

    /*
     * 会员动账记录
     */
    public function user_account_log(){
        $params = I('get.');//请求参数

        $order_by = 'change_time desc';
        $where = [];

        //排序
        if (!empty($params['order_by'])) {
            $order_by = $params['order_by'];
        }

        //根据订单号查找
        if (!empty($params['order_sn'])) {
            $order_sn = $params['order_sn'];
            $where['order_sn'] = $order_sn;
        }

        //根据类型(充值|消费)
        if (!empty($params['type'])) {
            $type = $params['type'];
            $where['type'] = $type;
        }

        //处理状态
        if (!empty($params['state'])) {
            $state = $params['state'];
            $where['state'] = $state;
        }

        //根据会员账号查找
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $where['mobile|order_sn'] = ['like', "%{$keyword}%"];
        }

        $rows = db("v_account_log")->where($where)->order($order_by)->paginate($this->showNum);
        // sql();
        // ee($rows->render());

        $this->assign('rows', $rows);
        // ee($rows);
        return $this->fetch();
    }

    /*
     * 删除
     */
    public function del() {
        $id = input('id');
        $res = db('users')->where(['user_id' => $id])->delete();
        if ($res) {
            $this->success('操作成功', url('index'));
        } else {
            $this->error('操作失败');
        }
    }

}
