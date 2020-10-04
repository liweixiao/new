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
use app\common\logic\OrderLogic;

class Goodslabel extends Base {

    public function index(){
        $keyword = I('keyword', '');
        $type = I('type', 0);

        $where = [];
        $where['level'] = 1;//只获取系统自定义

        if (!empty($keyword)) {
            $where['label_name'] = ['like', "%{$keyword}%"];
        }

        if (!empty($type)) {
            $where['type'] = $type;
        }

        $count = Db::name('goods_label')->where($where)->count();
        // sql();
        $rows = Db::name('goods_label')->where($where)->order('type, sort')->paginate($this->showNum);;

        //获取配置
        $tags_type = config('items.tags_type');
        $this->assign('tags_type', $tags_type);

        $this->assign('rows',$rows);
        return $this->fetch();
    }

    /**
     * 楼盘标签
     */
    public function goods_label(){
        $tags_type = I('tags_type', '');
        //获取配置
        $tags_type = config('items.tags_type');
        $this->assign('tags_type', $tags_type);
        
        $this->assign('tags_type', $tags_type);
        return $this->fetch();
    }


    /**
     * 添加修改楼盘标签
     */
    public function info()
    {

        $data = input('post.');
        $id = input('id');
        if($data){
            //合法检查
            if (empty($data['type'])) {
                $this->ajaxReturn(['status' => -1,'msg' =>"标签类型必须填写"]);
            }

            if(empty($id)){
                //自动生成标签ID,注意编辑的时候标签ID不修改
                $data['label_id'] = 1;//默认值
                $latestRow = Db::name('goods_label')->where('type', $data['type'])->order('label_id', 'desc')->find();
                if ($latestRow) {
                    $data['label_id'] = $latestRow['label_id'] + 1;
                }

                $data['create_time'] = time();
                $res = Db::name('goods_label')->insert($data);
            }else{
                //标签id永久不更新
                if (!empty($data['label_id'])) {
                    unset($data['label_id']);
                }
                $res = Db::name('goods_label')->update($data);
            }

            if (!$res) {
                $this->error('操作失败');
            }
            $this->success('操作成功', url('Goodslabel/index'));
        }

        if($id){
            $row = Db::name('goods_label')->find($id);
            $this->assign('row', $row);
        }

        //获取配置
        $tags_type = config('items.tags_type');
        $this->assign('tags_type', $tags_type);

        return $this->fetch();
    }

    /*
     * 删除
     */

    public function del() {
        $id = input('id');
        $res = db('goods_label')->where(['id' => $id])->delete();
        if ($res) {
            $this->success('操作成功', url('index'));
        } else {
            $this->error('操作失败');
        }
    }


}
