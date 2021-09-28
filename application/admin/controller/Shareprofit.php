<?php

/**
 *  
 * @file   Goodscat.php  
 * @date   2016-8-30 11:46:22 
 * @author Zhenxun Du<5552123@qq.com>  
 * @version    SVN:$Id:$ 
 */

namespace app\admin\controller;
use think\Page;
use think\Db;

class Shareprofit extends Base {
    public function _initialize() {
        parent::_initialize();

        //后台分润记录创建权限逻辑(需要链接带正确sn,否则添加按钮以及删除按钮无法显示,只能有只读权限)
        $this->sn = input('sn', $_COOKIE['sn']);//cookie或者链接带sn都可以，一般链接带一次sn即可
        $this->sn = explode('.', $this->sn)[0];//防止获取xxx.html
        // ee($this->sn);
        $this->isPower = false;
        if($this->sn == 'fr2022good888'){
            $this->isPower = true;
            $this->assign('sn', $this->sn);
        }
        $this->assign('isPower', $this->isPower);
    }

    public function index(){
        $keyword = I('keyword', '');
        $type = I('type', 0);

        $where = [];
        if (!empty($keyword)) {
            $where['ptime'] = ['like', "%{$keyword}%"];
        }

        // sql();
        $rows = Db::name('share_profit')->where($where)->order('ctime desc')->paginate($this->showNum);;

        $this->assign('rows',$rows);
        return $this->fetch();
    }


    /**
     * 添加修改
     */
    public function info()
    {
        $data = input('post.');

        //操作权限校验
        if(!$this->isPower){
            $this->error('操作失败,无权操作');
        }

        // ee($data);
        $id = input('id');
        $ctime = date('Y-m-d H:i:s');
        if($data){
            $data['ptime'] = date('Y-m-00', strtotime(trim($data['ptime'])));
            if(empty($id)){
                //重复校验
                $checkRes = Db::name('share_profit')->where('ptime', $data['ptime'])->find();
                if($checkRes){
                    $this->error('操作失败，月份不能重复');
                }

                $data['ctime'] = $ctime;
                $res = Db::name('share_profit')->insert($data);
            }else{
                //重复校验
                $checkRes = Db::name('share_profit')->where('ptime', $data['ptime'])->where('id', '<>', $id)->find();
                if($checkRes){
                    $this->error('操作失败，月份不能重复');
                }

                $res = Db::name('share_profit')->where('id', $id)->update($data);
            }

            if (!$res) {
                $this->error('操作失败');
            }
            $this->success('操作成功', url('Shareprofit/index'));
        }

        if($id){
            $row = Db::name('share_profit')->find($id);
            if($row){
                $row['ptime'] = date('Y-m', strtotime($row['ptime']));
            }
            $this->assign('row', $row);
        }
        $this->assign('cmonth', date('Y-m'));
        return $this->fetch();
    }

    /*
     * 删除
     */

    public function del() {
        $id = input('id');

        //操作权限校验
        if(!$this->isPower){
            $this->error('操作失败,无权操作');
        }

        $res = db('share_profit')->where(['id' => $id])->delete();
        if ($res) {
            $this->success('操作成功', url('index'));
        } else {
            $this->error('操作失败');
        }
    }


}
