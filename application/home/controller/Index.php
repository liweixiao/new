<?php
namespace app\home\controller;
use think\Controller;

class Index extends Controller {
    public function index(){
        exit('hello');
        return $this->fetch('index');
    }
    public function test(){
		return $this->fetch('index');
    }
}
