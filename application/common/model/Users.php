<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 没事忙，并保留所有权利。
 * 网站地址: http://www.xxxxx.cn
 * ----------------------------------------------------------------------------

 * ============================================================================
 * Author: 没事忙
 * Date: 2015-09-09
 */
namespace app\common\model;

use think\Db;
use think\Model;

class Users extends Model
{
    //自定义初始化
    protected static function init(){
        //TODO:自定义的初始化
    }

    public function oauthUsers(){
        return $this->hasMany('OauthUsers', 'user_id', 'user_id');
    }

    public function userLevel(){
        return $this->hasOne('UserLevel', 'level_id', 'level');
    }

}
