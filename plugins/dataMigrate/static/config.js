(function(){
    var openWindow = function(url,title,width,height){
		title  = title?title:LNG['common.tips'];
		width  = width?width:'80%';
		height = height?height:'70%';
		if($.isWap){
			width = "100%";height = "100%";
		}
		var dialog = $.dialog.open(url,{
			ico:"",
			id:"migrate",
			title:title,
			fixed:true,
			resize:true,
			width:width,
			height:height
		});
		return dialog;
	};
	// 开始迁移
    var pluginApi = G.kod.appApi + 'plugin/dataMigrate/';
	$('body').delegate('.start-data-migrate','click',function(){
		$(".app-config-dataMigrate .aui-footer .aui-state-highlight").click();
		if($.trim($(".app-config-dataMigrate input[name='dataPath']").val()) == '') return;
		setTimeout(function(){
			$.ajax({
				url:pluginApi + 'check',
				dataType:'json',
				success:function(data){
					if(!data.code){
						Tips.close(data);
						return false;
					}
					openWindow(pluginApi + 'index');
				}
			});
		},500);
	});
})();