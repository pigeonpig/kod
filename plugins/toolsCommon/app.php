<?php
class toolsCommonPlugin extends PluginBase{
	function __construct(){
		parent::__construct();
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert'	=> 'toolsCommonPlugin.echoJs'
		));
	}
}