(function(){
	window.$BODY = $('body');// jquery 对象会重置;
	var fileOpen = function(url,name,filePath,canWrite){
		FILE_INFO = {
			fileUrl:url,
			fileName:name,
			savePath:filePath,
			canWrite:canWrite
		};
		fileReload();
	};
	var fileSelectOpen = function(){
		kodApi.fileSelect({
			title:"打开文件",
			allowExt:'psd,jpg,png,gif,sketch,tif,tiff,jpeg,iff,mpo,bmp,svg,ps,psb,pbm,ai,xd,xcf,raw,emf,webp,ppm,ico,dds,tga,eps,raw,cr2,raf,dng,nef,srw,arw',
			callback:function(result){  // 回调地址;
				fileOpen(result.downloadPath,result.name,result.path,true);
			}
		});
	}
		
	var fileReloadInfo = function(fileInfo){
		var name = fileInfo.fileName;
		if(!fileInfo.savePath){//未保存文件;
			if(!fileInfo.fileUrl){name = name ? '*' + name : '';}
			$('.file-title').removeClass('true-file');
		}else{
			$('.file-title').addClass('true-file');
		}
		document.title = (name ? name + ' - ' : '') + '在线PS - Photopea图片编辑';
		$('.file-title span').html(htmlEncode(name));
		$('.file-title .folder').attr('data-path',fileInfo.savePath||"");
		// console.error(123,name,fileInfo,fileInfo.file);
	}
	
	var bufferToBlob = function(buffer){
		return new Blob([buffer]);
	}
	var blobToBuffer = function(blob,callback){
		var reader = new FileReader();
		reader.onload = function(){
			callback && callback(this.result);
		}
		reader.readAsArrayBuffer(blob);
	}
	
	var emptyPsd   = [56,66,80,83,0,1,0,0,0,0,0,0,0,3,0,0,2,-48,0,0,5,0,0,8,0,3,0,0,0,0,0,0,1,108,56,66,73,77];
	var emptyFile  = arrayBuffer = new Int8Array(emptyPsd).buffer;
	var fileReload = function(){
		fileReloadInfo(FILE_INFO);
		if(!FILE_INFO.fileUrl) return false;
		var tipsLoading = Tips.loadingMask(false,false,0.1);
		$.ajax({
			type:'GET',
			dataType:"binary",
			url:FILE_INFO.fileUrl,
			processDownload:function(percent){tipsLoading.title(Math.round(percent*100)+'%');},
			success:function(data){//arrayBuffer;
				tipsLoading.close();
				if(!data) return;
				if(data.size == 0){
					return appEditor.setFileContent(emptyFile);;
				}
				blobToBuffer(data,function(dataBuffer){
					appEditor.setFileContent(dataBuffer);
				});
			}
		});
	};
	var fileSaveCreate = function(fileInfo,content,callback){
		if(fileInfo.savePath){
			return kodApi.fileSave(fileInfo.savePath,content,callback);
		}
		kodApi.fileCreate(fileInfo.fileName,content,function(data){
			fileInfo.fileUrl  = '---';
			fileInfo.fileName = data.name,
			fileInfo.savePath = data.path,
			fileInfo.canWrite = true;
			fileReloadInfo(fileInfo);
			callback && callback();
	   });
	}
	
	
	var resetViewToolbar = function(){
		var toobarButtons = appEditor.J1.jF;
		var toolbarMenus  = appEditor.J1.cY;
		var toolbarMap    = 'file,edit,image,layer,select,filter,view,window,more'.split(',');
		if(!toolbarMenus) return;
		for (var i = 0; i < toolbarMenus.length; i++) {
			var $menu = $(toolbarMenus[i].u); //.af6 菜单项数据;
			$menu.addClass('toolbar-menu toolbar-'+toolbarMap[i]);
			
			if(toolbarMap[i] == 'file'){
				var $save = $menu.children('div').eq(6).hide();
				var prev  = '<span class="check"></span><span class="label">';
				var saveText     = '保存';
				var downloadText = '下载psd';
				$('<div class="enab" data-action="download-psd">'+prev+downloadText+'</span></div>').insertAfter($save);
				$('<div class="enab" data-action="save">'+prev+saveText+'</span><span class="right">⌘+S</span></div>').insertAfter($save);
			}
		}
	}
	
	var bindEvent = function(){
		$('body').delegate('[data-action]','click',function(){
			var action = $(this).attr('data-action');
			appEditor.toolbarHide = function(){appEditor.lQ.Y3();};
			switch(action){
				case 'save':
					var dataBuffer = fileGetContent('psd');
					var theFile    = appEditor.currentFile() || {name:"test.psd"};
					appEditor.file.save(dataBuffer,theFile.name);
					appEditor.toolbarHide();
					break;
				case 'download-psd':
					var dataBuffer = fileGetContent('psd');
					var theFile    = appEditor.currentFile() || {name:"test.psd"};
					appEditor.file.download(dataBuffer,theFile.name);
					appEditor.toolbarHide();
					break;
				case 'open-server':fileSelectOpen();break;
				case 'open-local':appEditor.jM.aej();break;
				default:break;
			}
		});
	}
	var resetView = function() {
		var isset = false;
		functionHook(appEditor.J1,'To',false,function(){	
			if(isset) return;
			resetViewToolbar();isset = true;
		});
		
		// 主题处理;
		functionHook(appEditor,'zR',false,function(){
			resetTheme(appEditor.fm.hb);
		});
		
		var beforeClass = '';
		var resetTheme = function(theme){// 0~4;
			var $body = $('body');
			if(beforeClass){$body.removeClass(beforeClass);}
			
			var nowClass = 'theme-'+theme;
			if(theme == 1 || theme == 2 || theme == 4){
				nowClass += ' theme-black';
			}
			beforeClass = nowClass;
			$body.addClass(nowClass);
		}
		
		// 文件打开触发;
		functionHook(appEditor.jM,'aej',function(){
			var clickOpen = arguments[0] === null; //打开;打开并放置;
			if(!clickOpen) return;
			fileSelectOpen();
			return false;
		});
		var initOpenView = function(){
			var $open  = $('.intro li:eq(1)');
			$("<li><button class='fitem' data-action='open-server'>从可道云打开</button></div>").insertBefore($open);
			$("<li><button class='fitem' data-action='open-local'>从本地打开</button></div>").insertBefore($open);
			$open.remove();
		}
		var initFileTitle = function(){
			$('<div class="file-title"><span></span><i class="ri-folder-fill-3 folder"></i></div>').appendTo(".topbar");
			$('.file-title .folder').bind('click',function(){
				var filePath = $(this).attr('data-path');
				filePath && kodApi.folderView(filePath);
			});
		}

		setTimeout(function(){
			initOpenView();
			resetTheme(appEditor.fm.hb);
			initFileTitle();
		},100);
		bindEvent();
	}
	
	var initView = function(){
		window.alm = window.alert;
		window.alert = function (W, p) {
			Tips.tips(W,true,p);
		};
		window.onblur = function(){}; //失去焦点不自动隐藏菜单;
		functionHook(XMLHttpRequest.prototype,'open',function(type,url){
			var check = 'mirror.php?url=';
			if(url.substr(0,check.length) == check){
				arguments[1] = urlDecode(url.substr(check.length));
			}
			// console.log(123,[type,url,arguments]);
			return arguments;			
		});

		// 文件读写处理;
		appEditor.setFileContent = function(buffer){
			appEditor.file.qt({name:FILE_INFO.fileName},buffer,appEditor.jM,null);
		};
		
		/**
		 * 多标签支持处理;
		 * 
		 * 打开文件: 打开后fileInfo 设置到当前file; 
		 * 保存文件: 获取当前file的fileInfo; 已存在文件则调用保存; 不存在则调用创建,创建成功后追加到fileInfo;
		 * 标签切换: 当前文件名设置到标题及tab;
		 */
		//标签选中;
		appEditor.currentFile = function(){return appEditor.J1.PJ;}
		functionHook(appEditor.J1,'a8j',function(file,view){
			if(!file || file.fileInfo) return;
			file.fileInfo = FILE_INFO;
			file.fileInfo.file = file;
			if(!FILE_INFO.fileUrl){file.fileInfo.fileName = file.name;}						
			FILE_INFO = {fileUrl:"",fileName:"newFile.psd",canWrite:false,savePath:""};
			
			setTimeout(function(){
				var name = htmlEncode(file.fileInfo.fileName);
				$(".mainblock .panelhead .active .label").html(name);
			},0);
			// console.error(123,arguments);
		},function(result,args){
			var fileInfo = args[0] ? args[0].fileInfo : {fileUrl:"",fileName:"",canWrite:false,savePath:""};
			fileReloadInfo(fileInfo);
		});
		// 更新tab的文件名; 再次更新;
		functionHook(appEditor.VD,'Vp',false,function(res,args){
			var files = args[1];
			for (var i = 0; i < files.length; i++){
				var name = files[i].fileInfo.fileName || files[i].name;
				this.zF[i].PZ(name + (files[i].ry() ? " *" : ""));
			}
		});
		
		
		var fileExt =  function(thePath) {
			var ext = thePath.substr(thePath.lastIndexOf(".") + 1) || '';
			return ext.toLowerCase();
		}
		
		/**
		 * 文件保存处理;
		 * 可保存: psb,ai,psd,png,jpg,jpeg,svg,gif,bmp,emf,webp,ppm,tif,tiff,ico,dds,tga,raw
		 * 不可保存: xcf,xd,sketch,pbm,mpo,iff
		 * 
		 * 映射: ps:eps;psb=>psd;ai=>pdf;
		 */
		appEditor.file.download = appEditor.file.save;//下载到本地;
		appEditor.file.save = function(dataBuffer,name){
			// 导出为:处理;
			if(fileExt(name) != 'psd') return appEditor.file.download(dataBuffer,name);
			var fileInfo = appEditor.currentFile().fileInfo;

			var allowSave = 'psb,ai,psd,png,jpg,jpeg,svg,gif,bmp,emf,webp,ppm,tif,tiff,ico,dds,tga'.split(',');
			var ext = fileExt(fileInfo.fileName);
			if(allowSave.indexOf(ext) == -1){
				appEditor.file.download(dataBuffer,name);
				return Tips.tips(ext+"类型文件不支持编辑保存!",'warning');
			}
			// console.error(dataBuffer,name);return;
			dataBuffer = fileGetContent(fileExt(fileInfo.fileName));
			fileSaveCreate(fileInfo,bufferToBlob(dataBuffer));
		}
		resetView();
		fileReload();
	}
	var fileGetContent = function(ext){
		var file  = appEditor.J1.PJ;
		var style = appEditor.fm;
		var width = file.Z,height= file.h;
		var option= [100,false];//PNG,JPG

		if(ext == 'PS'){ext = 'EPS';}
		if(ext == 'PSB'){ext = 'PSD';}
		if(ext == 'AI') {ext='PDF';option = ["", 100, true, false, false,["jpg"]];} // ai实际上为pdf;
		if(ext == 'JPEG') {ext='JPG';}
		if(ext == 'GIF') {option = [100];}
		if(ext == 'SVG') {option = [true, false, false, false, true, true];}
		if(ext == 'GIF' || ext == 'WEBP') {option = [100];}
		if(ext == 'EMF') {option = ["",false,false,false,[]];}
		if(ext == 'BMP' || ext == 'PPM' || ext == 'ICO' || ext == 'DDS' || ext == 'TGA') {option = [];}
		if(ext == 'TIFF' || ext == 'TIF') {ext == 'TIFF';option = [false];}

		// var rawFile   = 'raw,cr2,raf,dng,nef,srw,arw'.split(',');
		// if(rawFile.indexOf(ext.toLowerCase())) {option = [2,0,0];ext = 'RAW';}
		return appEditor.image.eX(file,ext,width,height,option,style);//
	}
	$BODY.bind('appLoaded',initView);

	
	// 初始化多语言处理;
	var langSupport = 'en,zh-CN,zh-TW,ja,ko'.split(',');
	var langDefault = langSupport.indexOf(appLang) != -1 ? appLang: 'en';
	var options = {"globals": {"lang":langDefault,"theme":0}};//theme:0~4;
	var key = '0_stateLocal';
	if (window.localStorage && !localStorage.getItem(key)) {
		localStorage.setItem(key,JSON.stringify(options));
	}
})();