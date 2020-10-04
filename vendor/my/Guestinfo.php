<?php
//获取客户端信息操作类-Guest info简写
class Guestinfo{

    /**
     * 获取用户设备信息
     */
    public function equipmentSystem() {
        $res = "Unknown";
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if (stristr($agent, 'iPad')) {
            $res = "iPad";
        } else if (preg_match('/Android (([0-9_.]{1,3})+)/i', $agent, $version)) {
            $res = "手机(Android " . $version[1] . ")";
        } else if (stristr($agent, 'Linux')) {
            $res = "电脑(Linux)";
        } else if (preg_match('/iPhone OS (([0-9_.]{1,3})+)/i', $agent, $version)) {
            $res = "手机(iPhone " . $version[1] . ")";
        } else if (preg_match('/Mac OS X (([0-9_.]{1,5})+)/i', $agent, $version)) {
            $res = "电脑(OS X " . $version[1] . ")";
        } else if (preg_match('/unix/i', $agent)) {
            $res = "Unix";
        } else if (preg_match('/windows/i', $agent)) {
            $res = "电脑(Windows)";
        } else {
        }
        return $res;
    }

    /**
     * 判断浏览器名称和版本
     */
    public function getUserBrowser(){
        if (empty($_SERVER['HTTP_USER_AGENT'])){
            return '';
        }

        $agent   = $_SERVER['HTTP_USER_AGENT'];
        $browser = '';
        $version = '';
      
        if (preg_match('/MSIE\s([^\s|;]+)/i', $agent, $regs)){
            $browser     = 'Internet Explorer';
            $version = $regs[1];
        } elseif (preg_match('/FireFox\/([^\s]+)/i', $agent, $regs)) {
            $browser     = 'FireFox';
            $version = $regs[1];
        } elseif (preg_match('/Maxthon/i', $agent, $regs)) {
            $browser     = '(Internet Explorer ' .$version. ') Maxthon';
            $version = '';
        } elseif (preg_match('/Opera[\s|\/]([^\s]+)/i', $agent, $regs)) {
            $browser     = 'Opera';
            $version = $regs[1];
        } elseif (preg_match('/OmniWeb\/(v*)([^\s|;]+)/i', $agent, $regs)) {
            $browser     = 'OmniWeb';
            $version = $regs[2];
        } elseif (preg_match('/Netscape([\d]*)\/([^\s]+)/i', $agent, $regs)) {
            $browser     = 'Netscape';
            $version = $regs[2];
        } elseif (preg_match('/safari\/([^\s]+)/i', $agent, $regs)) {
            $browser     = 'Safari';
            $version = $regs[1];
        } elseif (preg_match('/NetCaptor\s([^\s|;]+)/i', $agent, $regs)) {
            $browser     = '(Internet Explorer ' .$version. ') NetCaptor';
            $version = $regs[1];
        } elseif (preg_match('/Lynx\/([^\s]+)/i', $agent, $regs)) {
            $browser     = 'Lynx';
            $version = $regs[1];
        }
      
        if (!empty($browser)) {
           return addslashes($browser . ' ' . $version);
        } else {
            return 'Unknow browser';
        }
    }

}
