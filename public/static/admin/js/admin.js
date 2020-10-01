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
 * @param only_keep_int 是否只保留整数
 * @return int|float
 */
go.filterNum = function (dom, only_keep_int){
    if (only_keep_int) {
        dom.value=dom.value.replace(/[^\d]/g,'');
    }else{
        dom.value=dom.value.replace(/[^\d.]/g,'');
    }
}


go.successTips = function(msg, area){
	msg = msg ? msg : '操作成功';
    area = area ? area : 'auto';
	layer.alert(msg, {
		title:'提示'
		,icon: 1
		,shade:0.5
		,area:area
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

//用户充值-页面
go.addmoney = function(user_id){
    var layerindex = layer.load(1);//加载层
    $.ajax({
        type: 'POST',
        url: "/Admin/user/addmoney",
        data: {user_id:user_id},
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

//重置会员密码
go.resetPassword = function(user_id){
    layer.confirm('确认重置此会员密码吗？', {
        btn: ['确定', '取消'] //按钮
    }, function () {
        var layerindex = layer.load(1);//加载层
        $.ajax({
            type: 'POST',
            url: "/Admin/user/resetPassword",
            data: {user_id:user_id},
            dataType: 'json',
            success: function(result){
                layer.closeAll();
                if (result.error == 1) {
                    go.errorTips(result.msg);
                } else {
                    go.successTips(result.msg);
                }
            },
            error: function(){
                layer.closeAll();
                layer.alert("服务器繁忙, 请联系管理员!");
            }
        });
    }, function () {
        layer.closeAll();
    });

}

//重新下单
go.reCreateOrder = function(order_id){
    layer.confirm('确认要重新下单吗？', {
        btn: ['确定', '取消'] //按钮
    }, function () {
        var layerindex = layer.load(1);//加载层
        $.ajax({
            type: 'POST',
            url: "/Admin/order/reCreateOrder",
            data: {order_id:order_id},
            dataType: 'json',
            success: function(result){
                layer.closeAll();
                if (result.error == 1) {
                    go.errorTips(result.msg);
                } else {
                    go.successTips(result.msg);
                    setTimeout(function(){location.reload()}, 1000);
                }
            },
            error: function(){
                layer.closeAll();
                layer.alert("服务器繁忙, 请联系管理员!");
            }
        });
    }, function () {
        layer.closeAll();
    });

}

//切换用户
go.search_user_change = function (dom){
    $(dom).parents('.selectUser').find('#user_name').val($(dom).find("option:selected").attr('nickname'));
}

//搜索用户
go.search_user = function (dom){
    var user_name = $(dom).parents('.selectUser').find('#user_name').val();
    if($.trim(user_name) == '')
        return false;
    $.ajax({
        type : "POST",
        url:"/index.php?m=Admin&c=User&a=search_user",//+tab,
        data :{search_key:user_name},// 你的formid
        dataType :'json',
        success: function(data){
            if(data.status == 1){
                var html='';
                for(var i=0 ; i<data.result.length ;i++){
                    html +="<option value='"+data.result[i].user_id+"' nickname='"+data.result[i].nickname+"'>" + data.result[i].nickname+"</option>"
                }
                $(dom).parents('.selectUser').find('select.users_box').html(html);
            }else{
                layer.msg(data.msg, {icon: 2});
            }
        }
    });
}

//添加商品会员价-页面
go.add_user_price = function(goods_id, goods_user_id){
    goods_user_id = goods_user_id ? goods_user_id : '';
    var layerindex = layer.load(1);//加载层
    $.ajax({
        type: 'POST',
        url: "/Admin/goods/add_user_price",
        data: {goods_id:goods_id, goods_user_id:goods_user_id},
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

//弹出确认操作
go.del = function(url, title) {
    art.dialog({
        lock: true,
        background: '#300', // 背景色
        opacity: 0.87, // 透明度
        content: title,
        ok: function () {
            // return window.location.href = url;
        },
        cancel: true
    });
}

// 修改指定表的指定字段值 包括有按钮点击切换是否 或者 排序 或者输入框文字
go.changeTableVal = function(table, id_name, id_value, field, obj,yes,no) {
    var value = $(obj).val();
    if(yes == '' || typeof(yes) == 'undefined') yes='是';
    if(no == '' || typeof(no) == 'undefined') no='否';
    if ($(obj).hasClass('no')){
        // 图片点击是否操作
        //src = '/public/images/yes.png';
        $(obj).removeClass('no').addClass('yes');
        $(obj).html("<i class='fa fa-check-circle'></i>"+yes+"");
        value = 1;
    } else if ($(obj).hasClass('yes')) { // 图片点击是否操作
        $(obj).removeClass('yes').addClass('no');
        $(obj).html("<i class='fa fa-ban'></i>"+no+"");
        value = 0;
    }

    $.ajax({
        url: "/index.php?m=Admin&c=Index&a=changeTableVal&table=" + table + "&id_name=" + id_name + "&id_value=" + id_value + "&field=" + field + '&value=' + value,
        dataType:'json',
        success: function (res) {
            if (!$(obj).hasClass('no') && !$(obj).hasClass('yes')){
                if (res.error==0) {
                    layer.msg('更新成功', {icon: 1});

                    //成功之后是否刷新
                    if ($(obj).attr('reload')) {
                        location.reload();
                    }
                }else{
                    layer.msg('更新失败', {icon: 1});
                }
            }
        }
    });
}




