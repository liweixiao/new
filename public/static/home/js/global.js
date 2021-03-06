var go={};

//延迟加载js
go.lazyLoadJs = function (b, a, c) {
    if (a == undefined || a == null || a <= 0) {
        a = 1
    }
    setTimeout("go.importJs('" + b + "','" + c + "');", a)
}
go.importJs = function (b, c) {
    if (!b || b.length === 0) {
        throw new Error('argument "src" is required !')
    }
    var a = document.createElement("script");
    a.setAttribute("src", b);
    a.setAttribute("type", "text/javascript");
    if (c && c != "undefined") {
        a.onload = a.onreadystatechange = function() {
            if (!this.readyState || this.readyState == "loaded" || this.readyState == "complete") {
                var d = document.createElement("script");
                d.setAttribute("type", "text/javascript");
                d.text = c + "();";
                document.getElementsByTagName("head")[0].appendChild(d);
                a.onload = a.onreadystatechange = null
            }
        }
    }
    document.getElementsByTagName("head")[0].appendChild(a)
}

//页面滚动到顶部
go.gotop = function (time) {
    if (!time) time = 500;
    $('html,body').animate({scrollTop: '0px'}, time);
}

/**
 * 格式化数字
 * @param type 类型 int整数, float小数
 * @return int|float
 */
go.filterNum = function (dom, type){
    if (type) {
        dom.value=dom.value.replace(/[^\d]/g,'');
    }else{
        dom.value=dom.value.replace(/[^\d.]/g,'');
    }
}

go.successTips = function(msg){
	msg = msg ? msg : '操作成功';
	layer.alert(msg, {
		title:'提示'
		,icon: 1
		,shade:0.5
		,shadeClose:true
		,skin: 'layui-layer-lan'
		,closeBtn: 1
		,anim: 0 //动画类型
	});
}

go.errorTips = function(msg){
	msg = msg ? msg : '抱歉，操作失败';
	layer.alert(msg, {
		title:'提示'
		,icon: 2
		,shade:0.5
		,shadeClose:true
		,skin: 'layui-layer-lan'
		,closeBtn: 1
		,anim: 0 //动画类型
	});
}

go.checkMobile = function(tel) {
    var reg = /^1[0-9]{10}$/;
    if (reg.test(tel)) {
        return true;
    }else{
        return false;
    };
}

go.checkEmail = function(str){
    var reg = /^([a-zA-Z0-9]+[_|\-|\.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|\-|\.]?)*[a-zA-Z0-9]+\.[a-zA-Z]{2,3}$/;
    if(reg.test(str)){
        return true;
    }else{
        return false;
    }
}

//提交用户反馈
go.userFeedback = function(param){
	if (!param) param = {};
	param.mobile = param.mobile ? param.mobile : '';//用户手机号
	param.type = param.type ? param.type : '';//反馈类型

	if (param.mobile == '') {
		go.errorTips('手机号必须填写！');
		return;
	}
	if (!param.type) {
		param.type = 1;//默认专车看房
	}
	var layerindex = layer.load(1);//加载层
	$.ajax({
	    type: 'POST',
	    url: "/index.php/Home/index/saveUserFeedback",
	    data: param,
	    dataType: 'json',
	    success: function(data){
	        layer.closeAll();
	        if (data.status == 1) {
	            go.successTips(data.msg);
	        } else {
	        	go.errorTips(data.msg);
	        }
	    },
	    error: function(){
	        layer.closeAll();
	        layer.alert("服务器繁忙, 请联系管理员!");
	    }
	});
}

//注册页面
go.reg = function(param){
	if (!param) param = {};

	var layerindex = layer.load(1);//加载层
	$.ajax({
	    type: 'GET',
	    url: "/index.php/Home/user/reg",
	    data: param,
	    dataType: 'html',
	    success: function(html){
	        layer.closeAll();
	        layer.open({
	          type: 1,
	          title: false,
	          area: ['700px', '450px'],
	          closeBtn: 1,
	          shadeClose: false,
	          content: html
	        });
	    },
	    error: function(){
	        layer.closeAll();
	        layer.alert("服务器繁忙, 请联系管理员!");
	    }
	});
}

//登录页面
go.login = function(param){
	if (!param) param = {};
	var layerindex = layer.load(1);//加载层
	$.ajax({
	    type: 'GET',
	    url: "/index.php/Home/user/loginPage",
	    data: param,
	    dataType: 'html',
	    success: function(html){
	        layer.closeAll();
	        layer.open({
	          type: 1,
	          title: false,
	          area: ['600px', '400px'],
	          closeBtn: 1,
	          shadeClose: false,
	          content: html
	        });
	    },
	    error: function(){
	        layer.closeAll();
	        layer.alert("服务器繁忙, 请联系管理员!");
	    }
	});
}

/* 格式修改TP的同步页码为异步化数字
* @param obj gparams.boxName 放数据的元素容器
* @return void
*/
go.editTpPage = function () {
    if ($(gparams.boxName + ' ul.pagination li a').length > 0) {
        $(gparams.boxName + ' ul.pagination li a').attr('href', 'javascript:void(0);');

        //注册异步点击事件,注意这里的params为global var
        $(gparams.boxName + ' ul.pagination li a').click(function(){
            //处理上一页、下一页
            if ($(this).hasClass('num')) {
                params.p = $(this).text();//当前页码
            }else if($(this).hasClass('prev')){
                params.p -= 1;
            }else if($(this).hasClass('first')){
                params.p = 1;
            }else if($(this).hasClass('next')){
                params.p = parseInt(params.p) + 1;
            }else{
                //此种情况为选中状态
                return;
            }

            //获取数据
            var callFunction = $(gparams.boxName).attr('method');
            // console.log(gparams)

            eval(callFunction + '("'+gparams+'")');
            // eval('window.parent.'+callback+'(fileurl_tmp,elementid)');
        });

    }
}

//tab切换样式
go.toggleTab = function (dom, style){
	$(dom).addClass(style).siblings().removeClass(style);
}

//滑动到指定锚点
go.slideToArea = function (dom) {
    var aim = $(dom).attr('aim');//目标元素
    var tail = 100;
    //额外参数
    if ($(dom).attr('tail')) {
        tail = $(dom).attr('tail');
    }
    //添加样式
    $(dom).addClass('on').siblings().removeClass('on');


    // console.log($(dom).attr('tail'))
    $('html,body').animate({scrollTop: $('.'+aim).offset().top-tail}, 500);
    return false;
}

/**
 * 图标提示
 * @param dom 鼠标放上去的元素
 * @param stickDom 选择要吸附的元素、注意只能是类
 * @return int|float
 */
go.iconTips = function (dom, stickDom){
    var content = $(dom).find('.tips-content').text();
    if (content != '') {
        //弹出提示
        var tipsLayer = layer.tips(content, '.' + stickDom, {
            tips: [1, '#8C8C8C'],
            time: 30000
        });

        //鼠标移走后撤销
        $(dom).mouseout(function(){
            layer.close(tipsLayer);
        });
    }
}

//预估总价
go.calcTotalAmount = function (num, price) {
    var res = 0;
    if (num <= 0) {
        return res;
    }

    if (price <= 0) {
        return res;
    }

    num = parseFloat(num);
    if (isNaN(num)) {
        return res;
    }
    price = parseFloat(price);
    res = num*price;
    res = res.toFixed(2);
    return res;
}

//手机端展示menu
go.showMenu = function (dom) {
    $('.left-box').slideToggle(200);
    $(dom).toggleClass('on');
    $(document).bind("click",function(e){
            var target = $(e.target);
            if(target.closest(".mobile-menu").length == 0 && target.closest(".left-box").length == 0 ){//点击id为parentId之外的地方触发
                $('.left-box').slideUp(200);
                $(dom).removeClass('on');
            }
    })
}


//用户下单通知
go.pushMessageOrder = function(param){
    if (!param) param = {};
    param.order_id = param.order_id ? param.order_id : '';//订单编号

    $.ajax({
        type: 'POST',
        url: "/index.php/Home/index/pushMessageOrder",
        data: param,
        dataType: 'json',
        success: function(res){
            //TODO
        },
        error: function(){
            console.log("服务器繁忙, 请联系管理员!");
        }
    });
}

//用户行为通知[充值]
go.pushMessage = function(param){
    if (!param) param = {};
    param.type = param.type ? param.type : '';//通知类型

    $.ajax({
        type: 'POST',
        url: "/index.php/Home/index/pushMessage",
        data: param,
        dataType: 'json',
        success: function(res){
            //TODO
        },
        error: function(){
            console.log("服务器繁忙, 请联系管理员!");
        }
    });
}

//打开悬浮
go.showXf = function(dom){
    //本身图标切换效果
    $(dom).find('.switch-icon').toggleClass('hide');

    //展示隐藏工单内容
    $(dom).closest('.xf-box').find('.xf-cnt').slideToggle(200);
}

//获取用户反馈信息
go.getfeed = function(dom){
    var param = {};
    param.type = 1;//默认类型

    if ($(dom).attr('type') != '') {
        param.type = $(dom).attr('type');
    }

    //弹框模板
    if ($(dom).attr('tpl')) {
        param.tpl = $(dom).attr('tpl');
    }

    param.order_id = $(dom).attr('order_id');//订单id
    param.tips = $(dom).attr('tips');//弹框标题显示

    var layerindex = layer.load(1);//加载层
    $.ajax({
        type: 'POST',
        url: "/index.php/Home/Tools/getFeedPage",
        data: param,
        dataType: 'html',
        success: function(html){
            layer.closeAll();
            if (html == 'error') return;
            layer.open({
              type: 1,
              title: false,
              area: ['auto', 'auto'],
              closeBtn: 1,
              shadeClose: false,
              content: html
            });
        },
        error: function(){
            layer.closeAll();
            layer.alert("服务器繁忙, 请联系管理员!");
        }
    });
}

//提交用户反馈信息(注意这个跟上面的getfeed不同，上面是先获取弹框页面，而这个是直接提交信息，少了弹框页面了)
go.dofeed = function(param){
    if (!param) param = {};
    if (!param.type) param.type = 1;//反馈类型

    if (param.mobile == '') {
        go.errorTips('请输入手机号');
        return;
    }

    if (!go.checkMobile(param.mobile)){
        go.errorTips('手机号格式不正确');
        return;
    }

    var layerindex = layer.load(1);//加载层
    $.ajax({
        type: 'POST',
        url: "/index.php/Home/Tools/dofeed",
        data: param,
        dataType: 'json',
        success: function(res){
            layer.closeAll();
            if (res.error == 0){
                go.successTips(res.msg);
            }else{
                go.errorTips(res.msg);
            }
        },
        error: function(){
            layer.closeAll();
            layer.alert("服务器繁忙, 请联系管理员!");
        }
    });
}

//复制评论内容
go.copyComment = function(id){
    var layerindex = layer.load(1);//加载层
    $.ajax({
        type: 'POST',
        url: "/index.php/Home/tools/getTaskCommentCopyData",
        data: {id:id},
        dataType: 'json',
        success: function(res){
            layer.closeAll();
            //TODO
            if (res.error == 0){
                
                layer.open({
                    'title': '按住CTRL+C复制即可',
                    type: 1,
                    skin: 'layui-layer-rim', //加上边框
                    area: ['320px', '240px'], //宽高
                    content: '<textarea class="copyBox">'+res.data+'</textarea>'
                });
                $('.copyBox').select();//选择
                document.execCommand("Copy");//执行复制
                // layer.msg('复制成功');
            }else{
                go.errorTips(res.msg);
            }    
        },
        error: function(){
            layer.closeAll();
            console.log("服务器繁忙, 请联系管理员!");
        }
    });
}




