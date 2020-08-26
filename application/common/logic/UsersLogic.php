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

/**
 * 用户逻辑定义
 * Class UsersLogic
 * @package Home\Logic
 */
class UsersLogic extends BaseLogic
{
    protected $user_id=0;

    /**
     * 设置用户ID
     * @param $user_id
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }


    /*
    * 登录
    */
    public function login($username,$password)
    {
        if (!$username || !$password) {
            return array('status' => 0, 'msg' => '请填写账号或密码');
        }

        $ctime = date('Y-m-d H:i:s');
        $user = Db::name('users')->where("mobile", $username)->whereOr('email', $username)->find();
        if (!$user) {
            $result = array('status' => -1, 'msg' => '账号不存在!');
        } elseif (encrypt($password) != $user['password']) {
            $result = array('status' => -2, 'msg' => '密码错误!');
        } elseif ($user['is_lock'] == 1) {
            $result = array('status' => -3, 'msg' => '账号异常已被锁定！！！');
        } else {
            //是否清空积分           zengmm          2018/06/05
            // $this->isEmptyingIntegral($user);
            //查询用户信息之后, 查询用户的登记昵称
            $levelId = $user['level'];
            $levelName = Db::name("user_level")->where("level_id", $levelId)->getField("level_name");
            $user['level_name'] = $levelName;
            // 万一登录无token??
            if(empty($user['token'])){
                $save_data['token'] = md5(time().mt_rand(1,999999999));
                $user['token'] = $save_data['token'];
            }
            $save_data['last_login'] = $ctime;
            db('users')->where("user_id", $user['user_id'])->save($save_data);
            user_login($user['user_id']);//登录日志
            $result = array('status' => 1, 'msg' => '登陆成功', 'result' => $user);
        }
        return $result;
    }

    /**
     * 判断登录成功后是否需要清空积分（积分是否过期）
     * @param $user str 用户信息
     */
    protected function isEmptyingIntegral($user)
    {
        $integralExpiredInfo = Db::name("config")->where("name='is_integral_expired' and inc_type='integral'")->find();
        if($integralExpiredInfo['value'] == 2) {
            $configInfo = Db::name("config")->where("name='expired_time' and inc_type='integral'")->find();
            $expiredTime = explode(",", $configInfo['value']);
            $newExpiredTime = strtotime(date("Y")."-".$expiredTime[0]."-".$expiredTime[1]);
            if(strtotime($user["last_login"]) < $newExpiredTime && time() >= $newExpiredTime){
                accountLog($user['user_id'], 0, -$user['pay_points'], '积分过期清空');
            }
        }
    }
    /**
     * 注册
     * @param $username  邮箱或手机
     * @param $password  密码
     * @param $password2 确认密码
     * @param int $push_id
     * @param array $invite
     * @param string $nickname
     * @param string $head_pic
     * @return array
     */
    public function reg($username,$password,$password2,$push_id = 0,$invite=array(),$nickname="",$head_pic=""){
        $is_validated = 0 ;
        if(check_email($username)){
            $is_validated = 1;
            $map['email_validated'] = 1;
            $map['email'] = $username; //邮箱注册
        }
        $ctime = date('Y-m-d H:i:s');

        if(check_mobile($username)){
            $is_validated = 1;
            $map['mobile_validated'] = 1;
            $map['mobile'] = $username; //手机注册
            $user = Db::name('users')->where('mobile',$username)->find();
            if($user){
                return array('status'=>-1,'msg'=>'该手机号已注册','result'=>$user);
            }
        }
        if($is_validated != 1)
            return array('status'=>-1,'msg'=>'请用手机号或邮箱注册','result'=>'');
        $map['nickname'] = $nickname ? $nickname : $username;
        $map['nickname'] = replaceSpecialStr($map['nickname']); // 去掉特殊字符
        if(!empty($head_pic)){
            $map['head_pic'] = $head_pic;
        }else{
            $map['head_pic']='/public/images/icon_goods_thumb_empty_300.png';
        }

        $data=[
            'nickname' =>$map['nickname'],
            'password' =>$password,
            'password2'=>$password2,
        ];
        $UserRegValidate = Loader::validate('User');
        if(!$UserRegValidate->scene('reg')->check($data)){
            return array('status'=>-1,'msg'=>$UserRegValidate->getError(),'result'=>'');
        }
        if(I('oauth') == 'miniapp'){
            $map['password'] = md5(C('AUTH_CODE').$password);
        }else{
            $map['password'] = $password;
        }
        $map['ctime'] = date('Y-m-d H:i:s');
        $third_oauth = session('third_oauth');
        $switch = tpCache('distribut.switch');
        // 成为合伙人条件  
        $distribut_condition = tpCache('distribut.condition'); 
        if($switch==1 && $distribut_condition == 0 && file_exists(APP_PATH.'common/logic/DistributLogic.php'))  // 直接成为合伙人, 每个人都可以做合伙人
            $map['is_distribut']  = 1;        
        
        $map['push_id'] = $push_id; //推送id
        $map['token'] = md5(time().mt_rand(1,999999999));
        $map['last_login'] = $ctime;
        $user_level =Db::name('user_level')->where('amount = 0')->find(); //折扣
        $map['discount'] = !empty($user_level) ? $user_level['discount']/100 : 1;  //新注册的会员都不打折
        $user_id = Db::name('users')->insertGetId($map);
        user_login($user_id); // 注册时也增加一次登录
        if($switch==1 && $distribut_condition == 0 && file_exists(APP_PATH.'common/logic/DistributLogic.php')){
            //无条件成为合伙人，默认开启店铺
            $setS = new DistributLogic();
            $setS->setStore(['nickname'=>$map['nickname'],'user_id'=>$user_id,'mobile'=>'']);
        }

        if($user_id === false)
            return array('status'=>-1,'msg'=>'注册失败');
        // 会员注册赠送积分
        $isRegIntegral = tpCache('integral.is_reg_integral');
        if($isRegIntegral==1){
            $pay_points = tpCache('integral.reg_integral');
        }else{
            $pay_points = 0;
        }
        //被邀请人可获得积分
        if(is_array($invite) && !empty($invite)){
            if($integral['invitee_integral'] > 0){
                accountLog($user_id, 0,$integral['invitee_integral'], '被邀请会员注册成功，获得积分'); // 记录日志流水
            }
        }
        //$pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        if($pay_points > 0){
            accountLog($user_id, 0,$pay_points, '会员注册赠送积分'); // 记录日志流水
        }
        $user = Db::name('users')->where("user_id", $user_id)->find();
        return array('status'=>1,'msg'=>'注册成功','result'=>$user);
    }

     /*
      * 获取当前登录用户信息
      */
    public function get_info($user_id)
    {
        if (!$user_id) {
            return array('status'=>-1, 'msg'=>'缺少参数');
        }
        $user = model('users')->find($user_id);//dump($user);
        if (!$user) {
            return false;
        }

        return ['status' => 1, 'msg' => '获取成功', 'result' => $user];
     }


    /**
     * 邮箱或手机绑定
     * @param $email_mobile  邮箱或者手机
     * @param int $type  1 为更新邮箱模式  2 手机
     * @param int $user_id  用户id
     * @return bool
     */
    public function update_email_mobile($email_mobile,$user_id,$type=1){
        //检查是否存在邮件
        if($type == 1)
            $field = 'email';
        if($type == 2)
            $field = 'mobile';
        $condition['user_id'] = array('neq',$user_id);
        $condition[$field] = $email_mobile;

        $is_exist = M('users')->where($condition)->find();
        if($is_exist)
            return false;
        unset($condition[$field]);
        $condition['user_id'] = $user_id;
        $validate = $field.'_validated';
        M('users')->where($condition)->save(array($field=>$email_mobile,$validate=>1));
        return true;
    }


    /**
     * 修改密码
     * @param $user_id  用户id
     * @param $old_password  旧密码
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     * @param bool|true $is_update
     * @return array
     */
    public function password($user_id,$old_password,$new_password,$confirm_password,$is_update=true){
        $user = M('users')->where('user_id', $user_id)->find();
        if ($new_password != $confirm_password)
            return ['status'=>-1,'msg'=>'请输入相同的新密码'];
        if ($old_password == $new_password)
            return ['status'=>-1,'msg'=>'新密码不能和旧密码相同'];
        if (strlen($new_password) < 6 || strlen($new_password) >18 )
            return ['status'=>-1,'msg'=>'请输入新密码长度为6~18'];
        $data=[
          'password' => $new_password,
          'password2' => $confirm_password,
        ];
        $UserRegvalidate = Loader::validate('User');
        if(!$UserRegvalidate->scene('set_pwd')->check($data)){
            return array('status'=>-1,'msg'=>$UserRegvalidate->getError(),'result'=>'');
        }
        //验证原密码
        if($is_update && ($user['password'] != '' && encrypt($old_password) != $user['password']))
            return array('status'=>-1,'msg'=>'原密码验证失败','result'=>'');
        $row = M('users')->where("user_id", $user_id)->save(array('password'=>encrypt($new_password)));
        if(!$row)
            return array('status'=>-1,'msg'=>'修改失败','result'=>'');
        return array('status'=>1,'msg'=>'修改成功','result'=>'');
    }


    /**
     * 检查短信/邮件验证码验证码
     * @param $code
     * @param $sender
     * @param string $type
     * @param int $session_id
     * @param int $scene
     * @return array
     */
    public function check_validate_code($code, $sender, $type ='email', $session_id=0 ,$scene = -1){
        
        $timeOut = time();
        $inValid = true;  //验证码失效

        //短信发送否开启
        //-1:用户没有发送短信
        //空:发送验证码关闭
        $sms_status = checkEnableSendSms($scene);

        //邮件证码是否开启
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
        
        if($type == 'email'){            
            if(!$reg_smtp_enable){//发生邮件功能关闭
                $validate_code = session('validate_code');
                $validate_code['sender'] = $sender;
                $validate_code['is_check'] = 1;//标示验证通过
                session('validate_code',$validate_code);
                return array('status'=>1,'msg'=>'邮件验证码功能关闭, 无需校验验证码');
            }            
            if(!$code)return array('status'=>-1,'msg'=>'请输入邮件验证码');                
            //邮件
            $data = session('validate_code');
            $timeOut = $data['time'];
            if($data['code'] != $code || $data['sender']!=$sender){
                $inValid = false;
            }  
        }else{
            if($scene == -1){
                return array('status'=>-1,'msg'=>'参数错误, 请传递合理的scene参数');
            }elseif($sms_status['status'] == 0){
                $data['sender'] = $sender;
                $data['is_check'] = 1; //标示验证通过
                session('validate_code',$data);
                return array('status'=>1,'msg'=>'短信验证码功能关闭, 无需校验验证码');
            } 
            
            if(!$code)return array('status'=>-1,'msg'=>'请输入短信验证码');
            //短信
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 180;
            $data = M('sms_log')->where(array('mobile'=>$sender,'session_id'=>$session_id , 'status'=>1))->order('id DESC')->find();
            //file_put_contents('./test.log', json_encode(['mobile'=>$sender,'session_id'=>$session_id, 'data' => $data]));
            if(is_array($data) && $data['code'] == $code){
                $data['sender'] = $sender;
                $timeOut = $data['add_time']+ $sms_time_out;
            }else{
                $inValid = false;
            }           
        }
        
       if(empty($data)){
           $res = array('status'=>-1,'msg'=>'请先获取验证码');
       }elseif($timeOut < time()){
           $res = array('status'=>-1,'msg'=>'验证码已超时失效');
       }elseif(!$inValid)
       {
           $res = array('status'=>-1,'msg'=>'验证失败,验证码有误');
       }else{
            $data['is_check'] = 1; //标示验证通过
            session('validate_code',$data);
            $res = array('status'=>1,'msg'=>'验证成功');
        }
        return $res;
    }
     
    
    /**
     * @time 2016/09/01
     * 设置用户系统消息已读
     */
    public function setSysMessageForRead()
    {
        $user_info = session('user');
        if (!empty($user_info['user_id'])) {
            $data['status'] = 1;
            M('user_message')->where(array('user_id' => $user_info['user_id'], 'category' => 0))->save($data);
        }
    }


    /**
     * 获取访问记录
     * @param type $user_id
     * @param type $p
     * @return type
     */
    public function getVisitLog($user_id, $p = 1)
    {
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $user_id)
            ->order('v.visittime desc')
            ->page($p, 20)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        return $visit_list;
    }


    /**
     * 上传图片-公用-小程序
     * @param $path 在upload下面的主路径名称
     */
    public function upload_img($path = 'pic'){
        $imgRes = [];
        if ($_FILES['images']['tmp_name']) {
            $files = request()->file('images');
            if (is_object($files)) {
                $files = [$files];
            }
            $image_upload_limit_size = config('image_upload_limit_size');
            $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
            $dir = UPLOAD_PATH . $path . '/';
            if (!($_exists = file_exists($dir))) {
                mkdir($dir);
            }
            $parentDir = date('Ymd');
            
            $i = 0;
            foreach($files as $file){
                $i +=1;
                $info = $file->validate($validate)->move($dir, true); 
                if($info) {
                    $imgRes[] = '/'.$dir.$parentDir.'/'.$info->getFilename();
                } else {
                    return ['status' => -1, 'msg' => $file->getError()];
                }
            }
        }else{
            return ['status' => -1, 'msg' => "文件不存在！"];
        }

        return ['status' => 1, 'msg' => '上传成功', 'result' => $imgRes];
    }

    /**
     * 更新用户信息
     * @param $user_id
     * @param $post  要更新的信息
     * @return bool
     */
    public function update_info($user_id,$post=array()){
        $model = M('users')->where("user_id", $user_id);
        $row = $model->setField($post);
        if($row === false)
           return false;
        return true;
    }


    /**
     * 用户动账记录
     * @param type $params
     * @param type $p
     * @return type
     */
    public function getAccountlog($params=[])
    {
        $res = [];
        $order_by = 'change_time desc';
        $where = [];

        //排序
        if (!empty($params['order_by'])) {
            $order_by = $params['order_by'];
        }

        //根据用户查找
        if (!empty($params['user_id'])) {
            $user_id = $params['user_id'];
            $where['user_id'] = $user_id;
        }

        // ee($where);
        $count = M('v_account_log')->where($where)->count();
        // sql();
        $page = new Page($count, $this->showNum);
        $res = M('v_account_log')->where($where)
                                ->order($order_by)
                                ->limit("{$page->firstRow}, {$page->listRows}")
                                ->select();
                                // sql();
        $this->page = $page;
        $this->listTotal = $count;
        // ee($res);
        return $res;
    }

}