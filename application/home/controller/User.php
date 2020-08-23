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
use app\common\logic\UsersLogic;
use think\Loader;
use think\Page;
use think\Session;
use think\Verify;
use think\Db;
use think\Request;
class User extends Base{
	
    public function _initialize() {
        parent::_initialize();
    }

    /*
     * 用户中心首页
     */
    public function index(){
        $logic = new UsersLogic();
        return $this->fetch();
    }


    public function logout(){
    	setcookie('uname','',time()-3600,'/');
    	setcookie('cn','',time()-3600,'/');
    	setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        session_unset();
        session_destroy();
        //$this->success("退出成功",U('Home/Index/index'));
        $this->redirect('Home/Index/index');
        exit;
    }

    /**
     *  登录
     */
    public function login(){
        if($this->user_id > 0){
            $this->redirect('Home/User/index');
        }
        $redirect_url = Session::get('redirect_url');
        $referurl = $redirect_url ? $redirect_url : U("Home/User/index");
        $this->assign('referurl',$referurl);
        return $this->fetch();
    }

    /**
     *  登录弹框内容
     */
    public function loginPage(){
        $redirect_url = Session::get('redirect_url');
        $referurl = $redirect_url ? $redirect_url : U("Home/User/index");
        $this->assign('referurl',$referurl);
        return $this->fetch();
    }


    public function do_login(){
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        $verify_code = I('post.verify_code');
     
        $verify = new Verify();
        if (!$verify->check($verify_code,'user_login'))
        {
             $res = array('status'=>0,'msg'=>'验证码错误');
             exit(json_encode($res));
        }
                 
        $logic = new UsersLogic();
        $res = $logic->login($username,$password);

        if($res['status'] == 1){
            $res['url'] =  htmlspecialchars_decode(I('post.referurl'));
            if(session('?redirect_url')){
                $res['url'] =  session('redirect_url');
            }
            session('user',$res['result']);
            setcookie('user_id',$res['result']['user_id'],null,'/');
            setcookie('token',$res['result']['token'],null,'/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname',urlencode($nickname),null,'/');
            setcookie('cn',0,time()-3600,'/');
        }
        $this->ajaxReturn($res);
    }
    /**
     *  注册
     */
    public function reg(){
        if($this->user_id > 0){
            $this->redirect('Home/User/index');
        }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
        if(IS_POST){
            $logic = new UsersLogic();
            //验证码检验
//            $this->verifyHandle('user_reg');
            $username = I('post.username','');
            $password = I('post.password','');
            $password2 = I('post.password2','');
            $code = I('post.code','');
            $scene = I('post.scene', 1);
            $session_id = session_id();
            if(check_mobile($username)){
                if($reg_sms_enable){   //是否开启注册验证码机制
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }else{
                    if(!$this->verifyHandle('user_reg')){
                        $this->ajaxReturn(['status'=>-1,'msg'=>'图像验证码错误']);
                    };
                }
            }
            if(check_email($username)){
                if($reg_smtp_enable){        //是否开启注册邮箱验证码机制
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }else{
                    if(!$this->verifyHandle('user_reg')){
                        $this->ajaxReturn(['status'=>-1,'msg'=>'图像验证码错误']);
                    };
                }
            }
            $invite = I('invite');
            if(!empty($invite)){
                $invite = get_user_info($invite,2);//根据手机号查找邀请人
            }
            $data = $logic->reg($username,$password,$password2,0,$invite);
            if($data['status'] != 1){
                $this->ajaxReturn($data);
            }
            if(session('?redirect_url')){
                $data['url'] = session('redirect_url');
            }else{
                $data['url'] = '';
            }
            session('user',$data['result']);
            setcookie('user_id',$data['result']['user_id'],null,'/');
            $nickname = empty($data['result']['nickname']) ? $username : $data['result']['nickname'];
            setcookie('uname',$nickname,null,'/');
            $this->ajaxReturn($data);
            exit;
        }
        $doc_title = db('system_article')->where('doc_code', 'agreement')->value('doc_title');
        $this->assign('regis_sms_enable',tpCache('sms.regis_sms_enable')); // 注册启用短信：
        $this->assign('regis_smtp_enable',tpCache('smtp.regis_smtp_enable')); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        $this->assign('doc_title', $doc_title);
        return $this->fetch();
    }


    /*
     * 个人信息
     */
    public function info(){
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if(IS_POST){
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            if(!$userLogic->update_info($this->user_id,$post))
                $this->error("保存失败");
            setcookie('uname',urlencode($post['nickname']),null,'/');
            $this->success("操作成功");
            exit;
        }

        $this->assign('user',$user_info);
        $this->assign('sex',C('SEX'));
        $this->assign('active','info');
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate(){
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step',1);
        if(IS_POST){
            $email = I('post.email');
            $old_email = I('post.old_email',''); //旧邮箱
            $code = I('post.code');
            $info = session('validate_code');
            if(!$info)
                $this->error('非法操作');
            if($info['time']<time()){
                session('validate_code',null);
                $this->error('验证超时，请重新验证');
            }
            //检查原邮箱是否正确
            if($user_info['email_validated'] == 1 && $old_email != $user_info['email'])
                $this->error('原邮箱匹配错误');
            //验证邮箱和验证码
            if($info['sender'] == $email && $info['code'] == $code){
                session('validate_code',null);
                if(!$userLogic->update_email_mobile($email,$this->user_id))
                    $this->error('邮箱已存在');
                $this->success('绑定成功',U('Home/User/index'));
                exit;
            }
            $this->error('邮箱验证码不匹配');
        }
        $this->assign('user_info',$user_info);
        $this->assign('step',$step);
        return $this->fetch();
    }


    /**
     * 手机验证
     * @return mixed
     */
    public function mobile_validate()
    {
        $user_info = $this->user;
        $config = tpCache('sms');
        $sms_time_out = $config['sms_time_out'];
        $this->assign('time', $sms_time_out);
        if (IS_POST) {
            $old_mobile = I('post.old_mobile');
            $code = I('post.code');
            $scene = I('post.scene', 6);
            $session_id = I('unique_id', session_id());

            $logic = new UsersLogic();
            $res = $logic->check_validate_code($code, $old_mobile, 'phone', $session_id, $scene);

            if (!$res && $res['status'] != 1) $this->error($res['msg']);

            //检查原手机是否正确
            if ($user_info['mobile_validated'] == 1 && $old_mobile != $user_info['mobile'])
                $this->error('原手机号码错误');
            //验证手机和验证码
            if ($res['status'] == 1) {
                return $this->fetch('set_mobile');
            } else {
                $this->error($res['msg']);
            }
        }
        $this->assign('user_info', $user_info);
        if (empty($user_info['mobile'])){
            return $this->fetch('set_mobile');
        }
        return $this->fetch();
    }

    /**
     * 设置新手机
     * @return mixed
     */
    public function set_mobile()
    {
        $userLogic = new UsersLogic();
        $mobile = I('post.mobile');
        $code = I('post.code');
        $scene = I('post.scene', 6);
        $session_id = I('unique_id', session_id());
        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $mobile, 'phone', $session_id, $scene);
        //验证手机和验证码
        if ($res['status'] == 1) {
            //验证有效期
            if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2)){
                $this->ajaxReturn(['status'=>-1,'msg'=>'手机已存在']);
            }else{
                $this->ajaxReturn(['status'=>1,'msg'=>'修改成功']);
            }
            exit;
        } else {
            $this->ajaxReturn(['status'=>-1,'msg'=>$res['msg']]);
        }

    }


    /*
     * 密码修改
     */
    public function password(){
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if($user['mobile'] == ''&& $user['email'] == ''){
            $this->error('请先绑定手机或邮箱',U('Home/User/info'));
        }

        if(IS_POST){
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id,I('post.old_password'),I('post.new_password'),I('post.confirm_password')); // 获取用户信息
            if($data['status'] == -1)
                $this->error($data['msg']);
            $this->success($data['msg']);
            exit;
        }
        return $this->fetch();
    }

    public function forget_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('Home/User/Index'));
        }
        if (IS_POST) {
            $username = I('username');
            if (!empty($username)) {
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                
                if ($user) {
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field));
                    header("Location: " . U('User/identity'));
                    exit;
                } else {
                   echo "用户名不存在，请检查";
                    $this->error("用户名不存在，请检查");
                }
            }
        }
        return $this->fetch();
    }
    
    public function set_pwd(){
        if($this->user_id > 0){
            $this->redirect('Home/User/Index');
        }
        $check = session('validate_code');
        $logic = new UsersLogic();
        if(empty($check)){
            $this->redirect('Home/User/forget_pwd');
        }elseif($check['is_check']==0){
            $this->error('验证码还未验证通过',U('Home/User/forget_pwd'));
        }       
        if(IS_POST){
            $password = I('post.password');
            $password2 = I('post.password2');
//          if($password2 != $password){
//              $this->error('两次密码不一致',U('Home/User/forget_pwd'));
//          }
            $data['password'] =  I('post.password');
            $data['password2'] =  I('post.password2');
            $UserRegvalidate = Loader::validate('User');
            if(!$UserRegvalidate->scene('set_pwd')->check($data)){
                $this->error($UserRegvalidate->getError(),U('User/forget_pwd'));
            }
            if($check['is_check']==1){
                //$user = get_user_info($check['sender'],1);
                $user = Db::name('users')->where("mobile|email", '=', $check['sender'])->find();
                Db::name('users')->where("user_id", $user['user_id'])->save(array('password'=>encrypt($password)));
                session('validate_code',null);
                $this->redirect('Home/User/finished');
            }else{
                $this->error('验证码还未验证通过',U('Home/User/forget_pwd'));
            }
        }
        return $this->fetch();
    }

    public function check_username(){
        $username = I('post.username');
        if(!empty($username)){
            $count = Db::name('users')->where("email", $username)->whereOr('mobile', $username)->count();
            exit(json_encode(intval($count)));
        }else{
            exit(json_encode(0));
        }   
    }

    public function identity()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('Home/User/Index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('userinfo', $user);
        return $this->fetch();
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        $result = $verify->check(I('post.verify_code'), $id ? $id : 'user_login');
        if (!$result) {
            return false;
        }else{
            return true;
        }
    }


    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => false,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }

    /**
     * 安全设置
     */
    public function safety_settings()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];        
        $this->assign('user',$user_info);
        return $this->fetch();
    }


    /**
     *  用户消息通知
     * @author yhj
     * @time 2018-6-28
     */
    public function message_notice()
    {
        $message_logic = new Message();
        $message_logic->checkPublicMessage();

        $type = I('type', 2);
        $user_info = session('user');
        $where = array(
            'user_id' => $user_info['user_id'],
            'deleted' => 0,
            'category' => $type
        );
        $size = $type == 0 ? 4 : 3;
        $userMessage = new UserMessage();

        $count = $userMessage->where($where)->count();
        $page = new Page($count, $size);
        $show = $page->show();
        $rec_id = $userMessage->where( $where)->LIMIT($page->firstRow.','.$page->listRows)->order('rec_id desc')->column('rec_id');
        if(empty($rec_id) && empty($count)){
            $list = [];
        } else {
            // 当前分页数据删除完了，前一页还有数据
            if(empty($rec_id) && $count > 0){
                $rec_id = $userMessage->where( $where)->limit($size)->order('rec_id desc')->column('rec_id');
            }
            $list = $message_logic->sortMessageListBySendTime($rec_id, $type);
        }

        $no_read = $message_logic->getUserMessageCount();
        $this->assign('no_read', $no_read);
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('count', $count);
        return $this->fetch('user/message_notice');
    }

    /**
     *  用户消息详情
     * @author yhj
     * @time 2018-6-28
     */    
    public function message_details()
    {

        $message_logic = new Message();
        $data['message_details'] = $message_logic->getMessageDetails(I('msg_id'), I('type', 0));
        $data['no_read'] = $message_logic->getUserMessageCount();
        $this->assign($data);        
        return $this->fetch('user/message_details');
    }
    /**
     * ajax用户消息删除请求
     * @author yhj
     * @time 2018-6-28
     */
    public function deletedMessage()
    {
        $message_logic = new Message();
        $res = $message_logic->deletedMessage(I('msg_id'),I('type'));
        $this->ajaxReturn($res);
    }
    /**
     * ajax设置用户消息已读
     * @author yhj
     * @time 2018-6-28
     */
    public function setMessageForRead()
    {
        $message_logic = new Message();
        $res = $message_logic->setMessageForRead(I('msg_id'));
        $this->ajaxReturn($res);
    }


    /**
     *  点赞
     * @author lxl
     * @time  17-4-20
     * 拷多商家Order控制器
     */
    public function ajaxZan()
    {
        $comment_id = I('post.comment_id/d');
        $user_id = $this->user_id;
        $comment_info = M('comment')->where(array('comment_id' => $comment_id))->find();  //获取点赞用户ID
        $comment_user_id_array = explode(',', $comment_info['zan_userid']);
        if (in_array($user_id, $comment_user_id_array)) {  //判断用户有没点赞过
            $result['success'] = 0;
        } else {
            array_push($comment_user_id_array, $user_id);  //加入用户ID
            $comment_user_id_string = implode(',', $comment_user_id_array);
            $comment_data['zan_num'] = $comment_info['zan_num'] + 1;  //点赞数量加1
            $comment_data['zan_userid'] = $comment_user_id_string;
            M('comment')->where(array('comment_id' => $comment_id))->save($comment_data);
            $result['success'] = 1;
        }
        exit(json_encode($result));
    }


    /**
     * 删除足迹
     * @author lxl
     * @time  17-4-20
     * 拷多商家User控制器
     */
    public function del_visit_log(){

        $visit_id = I('visit_id/d' , 0);
        $row = Db::name('goods_visit')->where(['visit_id'=>$visit_id])->delete();
        if($row>0){
            $this->ajaxReturn(['status'=>1 , 'msg'=> '删除成功']);
        }else{
            $this->ajaxReturn(['status'=>-1 , 'msg'=> '删除失败']);
        }
    }


    public function myCollect()
    {
        $item = input('item', 12);
        $goodsCollectModel = new GoodsCollect();
        $user_id = $this->user_id;
        $goodsList = $goodsCollectModel->with('goods')->where('user_id', $user_id)->limit($item)->order('collect_id', 'desc')->select();
        foreach($goodsList as $key=>$goods){
            $goodsList[$key]['url'] = $goods->url;
            $goodsList[$key]['imgUrl'] = goods_thum_images($goods['goods_id'], 160, 160);
        }
        if ($goodsList) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'result' => $goodsList]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '没有记录', 'result' => '']);
        }
    }


    /**
     * 历史记录
     */
    public function historyLog(){
        $item = input('item', 12);
        $goodsCollectModel = new GoodsVisit();
        $user_id = $this->user_id;
        $goodsList = $goodsCollectModel->with('goods')->where('user_id', $user_id)->limit($item)->order('visit_id', 'desc')->select();
        foreach($goodsList as $key=>$goods){
            $goodsList[$key]['url'] = $goods->url;
            $goodsList[$key]['imgUrl'] = goods_thum_images($goods['goods_id'], 160, 160);
        }
        if ($goodsList) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'result' => $goodsList]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '没有记录', 'result' => '']);
        }
    }


    public function check_mobile(){
        $mobile = input('mobile/s');
        if(strlen($mobile)<11){
            $this->ajaxReturn(['status'=>0,'msg'=>'长度不够']);
        }else if(strlen($mobile)>11)
        {
            $this->ajaxReturn(['status'=>0,'msg'=>'号码过长']);
        }else{
            if(check_mobile($mobile)){
                $mobile=Db::name('users')->where(['mobile'=>$mobile,'user_id'=>['<>',$this->user_id]])->find();
                if($mobile){
                    $this->ajaxReturn(['status'=>0,'msg'=>'这个手机号已被使用']);
                }else{
                    $this->ajaxReturn(['status'=>1,'msg'=>'可以使用']);
                }
            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>'输入有误']);
            }
        }
    }






}