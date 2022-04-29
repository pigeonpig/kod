ClassBase.define({
	init: function(param){
		this.initParentView(param);
		var package = this.formData();
		var form = this.initFormView(package); // parent: form.parent;
		form.setValue(G.clientOption);
	},
	
	saveConfig: function(data){
		if(!data) return false;
		Tips.loading(LNG['explorer.loading']);
		this.adminModel.pluginConfig({app:'client',value:data},Tips.close);
	},
	formData:function(){
		return{
		formStyle:{
			className:"form-box-title-block dialog-form-style-simple",
			tabs:{
				backup:"sep002,backupOpen,backupTipsOpen,backupTipsClose,backupAuth",
				open:"sep003,fileOpenSupport,fileOpen",
				scan:'appLoginApp,appLoginWeb,appLoginAppConfirm,step002,scanLoginDesc'
			},
			tabsName:{
				backup:LNG['admin.setting.sync'],
				open:LNG['explorer.sync.openLocal'],
				scan:LNG['client.app.scanLogin'],
			}
		},
		
		backupTipsOpen:"<div class='info-alert info-alert-green'>"+LNG['client.option.backupTipsOpen']+"</div>",
		backupTipsClose:"<div class='info-alert info-alert-yellow'>"+LNG['client.option.backupTipsClose']+"</div>",
		backupOpen:{
			type:"switch",
			value:'1',
			display:LNG['client.option.backupOpen'],
			desc:LNG['client.option.backupOpenDesc'],
			switchItem:{"1":"backupAuth,backupTipsOpen","0":"backupTipsClose"},
		},
		backupAuth:{
			type:"userSelect",
			value:{"all":1},
			display:LNG['client.option.backupAuth'],
			desc:LNG['admin.plugin.authDesc'],
		},
		fileOpenSupport:{
			type:"switch",
			value:'1',
			display:LNG['client.option.fileOpenSupport'],
			desc:LNG['client.option.fileOpenSupportDesc'],
			switchItem:{"1":"fileOpen"},
		},
		fileOpen:{
			type:"table",
			info:{formType:'inline',removeConfirm:0},
			display:LNG['client.option.fileOpen'],
			desc:"<div class='info-alert mt-10 mb-10'>"+LNG['client.option.fileOpenDesc']+"<br/>\
			eg: doc,docx,ppt,pptx,xls,xlsx,pdf,ofd  sort:100</div>",
			value:'[{"ext":"doc,docx,ppt,pptx,xls,xlsx,dwg,dxf,dwf","sort":"10000"}]',
			children:{
				ext:{type:"textarea","display":LNG['client.option.fileOpenExt'],attr:{style:"width:100%;height:32px"}},
				sort:{type:"number","display":LNG['client.option.fileOpenSort'],attr:{style:"width:100px;"},value:"500"},
			}
		},
		
		scanLoginDesc:"<div class='info-alert'>"+LNG['client.app.loginAppDesc']+LNG['client.app.scanVersion']+"</div>",
		appLoginApp:{
			type:"switch",
			value:'1',
			display:LNG['client.app.loginApp'],
			desc:LNG['client.app.loginAppTips'],
			switchItem:{"1":"appLoginAppConfirm,step002"},
		},
		appLoginAppConfirm:{
			type:"switch",
			value:'0',
			display:LNG['client.option.loginAppConfirm'],
			desc:LNG['client.option.loginAppConfirmDesc'],
		},
		step002:'<hr>',
		
		appLoginWeb:{
			type:"switch",
			value:'1',
			display:LNG['client.app.loginWeb'],
			desc:LNG['client.app.loginWebTips'],
		},
	}}
});