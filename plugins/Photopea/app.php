<?php

/**
在线PS Photopea

官网及参考: https://www.photopea.com/
其他: https://ps.gaoding.com/sources/index.html; https://www.tuyitu.com/ps/sources/
调整: pp.js  `window.confirm("Load`所在方法,最前面判断处理;
*/
class photopeaPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert' => 'photopeaPlugin.echoJs',
		));
	}
	public function echoJs(){
		$this->echoFile('static/app/main.js');
	}
	public function index(){
		$path   = $this->in['path'];
		$assign = array(
			"fileUrl"	=>'','savePath'	=>'','canWrite'	=>false,
			'fileName'	=> $this->in['name']
		);
		if($path){
			if(substr($path,0,4) == 'http'){
				$assign['fileUrl'] = $path;
			}else{
				$assign['fileUrl']  = $this->filePathLink($path);
				if(ActionCall('explorer.auth.fileCanWrite',$path)){
					$assign['savePath'] = $path;
					$assign['canWrite'] = true;
				}
			}
			$assign['fileUrl'] .= "&name=/".$assign['fileName'];
		}
		$this->assign($assign);
		$this->display($this->pluginPath.'php/template.html');
	}
}