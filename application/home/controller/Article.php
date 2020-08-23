<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 没事忙，并保留所有权利。
 * 网站地址: http://www.xxxxx.cn
 * ----------------------------------------------------------------------------

 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * $Author: 没事忙 2015-08-10 $
 */
namespace app\home\controller;
use app\admin\logic\ArticleCatLogic;
use think\Db;
use think\AjaxPage;
use think\Page;

class Article extends Base {
    
    public function index(){       
        $article_id = I('article_id/d',38);
    	$article = Db::name('article')->where("article_id", $article_id)->find();
    	$this->assign('article',$article);
        return $this->fetch();
    }
 
    /**
     * 文章内列表页
     */
    public function articleList(){

        //获取分类ID
        $cat_id = I('article_id/d');
        $cat = M('article_cat') -> where('cat_id', $cat_id) -> find();

        //获取下属信息
        $cat_ids =  $this -> getChildIds($cat_id,'','cat_id');
        //获取分类文章总数
        $count = M('article') -> whereIn('cat_id', $cat_ids) -> where('is_open', 1) -> count();
        //获取分页数据
        $page = new Page($count, 10);
        //获取文章数据
        $catList = M('article') -> whereIn('cat_id', $cat_ids) -> where('is_open', 1)-> order('update_time', 'desc') -> limit($page -> firstRow . ',' . $page -> listRows) -> select();

        $this -> assign('cat_id', $cat_ids);
        $this -> assign('cat', $cat);
        $this -> assign('catList', $catList);
        $this -> assign('page', $page); // 赋值分页输出
        return $this->fetch();

    }    
    /**
     * 文章内容页
     */
    public function detail(){
    	$article_id = I('article_id/d',1);
    	$article = Db::name('article')->where("article_id", $article_id)->find();
    	if($article){
    		$parent = Db::name('article_cat')->where("cat_id",$article['cat_id'])->find();
    		$this->assign('cat_name',$parent['cat_name']);
    		$this->assign('article',$article);
    	}
        return $this->fetch();
    } 
    
    /**
     * 获取服务协议
     * @return mixed
     */
    public function agreement(){
    	$doc_code = I('doc_code','agreement');
    	$article = Db::name('system_article')->where('doc_code',$doc_code)->find();
    	if(empty($article)) $this->error('抱歉，您访问的页面不存在！');
    	$this->assign('article',$article);
    	return $this->fetch();
    }

    /**
     * knowledgeList 产品知识列表
     */
    public function knowledgeList() {

        $cat = M('article_cat') -> where('cat_name', '产品知识') -> find();

        $two_cat = M('article_cat') -> where('parent_id', $cat['cat_id']) -> column('cat_id,cat_alias');
        $cat_data = [];

        foreach ($two_cat as $k => $two) {

            //获取二级图标
            $thumb = M('article_cat') -> where('cat_id', $k) -> value('thumb');
            //获取下属信息
            $cat_res =  $this -> getChildIds($k,'','cat_id');

            //获取分类文章
            $article = M('article') -> whereIn('cat_id', $cat_res) -> where('is_open', 1) -> order('update_time', 'desc') -> limit(1, 5) -> select();
            $new_cat_art = M('article') -> whereIn('cat_id', $cat_res) -> where('is_open', 1) -> order('update_time', 'desc') -> find();
            $cat_data[$k] = [
                'cat_id' => $k,
                'cat_alias' => $two,
                'thumb' => $thumb,
                'new_article' => $new_cat_art,
                'article' => $article

            ];
        }

        $this->assign('article', $cat_data);
        return $this->fetch();
    }

    /**
     * journalismList  新闻专区列表
     */
    public function journalismList() {

        //获取新闻专区分类信息
        $cat = M('article_cat') -> where('cat_name', '新闻专区') -> find();
        //获取新闻分区下的所有分类ID
        $cat_res =  $this -> getChildIds($cat['cat_id'],'','cat_id');

        //获取新闻专区下的所有文章
        $article = M('article') -> whereIn('cat_id', $cat_res) -> where('is_open', 1) -> order('update_time', 'desc') -> limit(12) -> select();

        $this->assign('cat', $cat);
        $this->assign('article', $article);

        return $this->fetch();
    }

    /**
     * getChildIds 获取所属所有分类以及子分类
     */
    protected function getChildIds($pid, $childids, $find_column = 'cat_id'){
        if(!$pid || $pid <= 0 || !in_array($find_column, array('cat_id', 'parent_id'))) {
            return 0;
        }

        if(!$childids || strlen($childids)<=0) {
            $childids = $pid;
        }
        $column = ($find_column == 'cat_id' ? "parent_id" : "cat_id");//id跟pid为互斥
        $ids = M('article_cat') -> where("$column in($pid)") -> getField("$find_column", true);
        $ids = implode(",",$ids);

        //未找到,返回已经找到的
        if($ids <= 0) {
            return $childids;
        }

        //添加到集合中
        $childids .= ',' . $ids;

        //递归查找
        return $this -> getChildIds($ids, $childids, $find_column);
    }

}