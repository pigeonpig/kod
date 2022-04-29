<?php

class dataMigratePlugin extends PluginBase{
	public $typeList;
	public $dataPath;
	public $taskKey;
	function __construct(){
		parent::__construct();
		$this->typeList = array(
			'role'		=> '用户角色',
			'auth'		=> '文档权限',
			'group'		=> '组织架构',
			'member'	=> '用户列表',
			'setting'	=> '系统配置信息',
			'groupFile' => '部门文档',
			'userFile'	=> '用户文档',
			'fav'		=> '用户收藏',
			'share'		=> '用户分享',
		);
	}
	public function regist(){
		$this->hookRegist(array(
			'user.commonJs.insert' => 'dataMigratePlugin.echoJs',
		));
	}
	public function echoJs(){
	}

	public function _taskKey(){
		if($this->taskKey) return $this->taskKey;
		$this->taskKey = md5($this->_dataPath());
		return $this->taskKey;
	}
	public function _dataPath(){
		if($this->dataPath) return $this->dataPath;
		$config = $this->getConfig();
		$this->dataPath = rtrim($config['dataPath'], '/');
		return $this->dataPath;
	}

	public function check(){
		// 兼容旧版
		$config = $this->getConfig();
		if(isset($config['isMigrated']) && $config['isMigrated'] == 1) {
			show_json('数据已迁移，无需再次执行', false);
		}
		$dataPath = $this->_dataPath();
		if(empty($dataPath) || !@file_exists($dataPath)) show_json('无效的数据目录', false);
        $fileList = array('Group', 'system', 'User');
		foreach($fileList as $file){
			$path = $dataPath . '/' . $file;
            if(!@file_exists($path)) show_json('目录不存在.'.$path, false);
		}
		show_json('OK');
	}

    public function index(){
		$this->display($this->pluginPath.'php/template.html');
	}

	// 开始迁移任务——可改为start
	public function update(){
		// 0. 如有进行中的任务，直接返回
		foreach($this->typeList as $type => $value) {
			$taskKey = "migrate.{$type}." . $this->_taskKey();
			if($taskType = Task::get($typeKey)) {
				echo json_encode(array('code'=>true,'data'=>'OK'));
				http_close();
				if($taskType['status'] != 'running') Task::restart($typeKey);
				return;
			}
		}
		// 1. 根据config迁移结果，获取当前进度
		$finished = $this->migrateGet();
		if(empty($finished)) show_json('数据异常，请清空缓存后再次尝试', false);
		// 1.1 已完成
		if($finished['status'] == '1') show_json('OK', true, $finished);

		// 1.2 获取当前项—— 0、-1
		$type = '';
		foreach($finished['result'] as $key => $value) {
			if($value['status'] != '1') {
				$type = $key;
				break;
			}
		}
		// 1.3 为空说明都完成了，但status没更新——也有可能result为空
		if(!$type) {
			$this->migrateSet('status', 1);
			$finished['status'] = 1;
			show_json('OK', true, $finished);
		}
		// 如果非首次执行，可能存在cache被清空，因为缓存数据确实导致迁移无法继续的情况
		$this->clearTips($type);

		// 2. 获取当前进度——子任务存在，返回；不存在，从当前项开始继续执行
		// 2.1 进度存在，(已停止则重启)，直接返回——开头已执行
		// 2.2 进度不存在，从该项开始执行。切断http，前端执行获取进度请求
		echo json_encode(array('code'=>true,'data'=>'OK'));
		http_close();
		$typeList = array_keys($this->typeList);
		$typeList = array_splice($typeList, array_search($type, $typeList));
		foreach($typeList as $type) {
			$result	= $this->getObj($type)->move($type);
			if($result['code'] != '1') break;
		}
		// 更新执行结果状态
		$option = $this->migrateGet();
		$total = array_sum(array_map(function($val){return $val['status'];}, $option));
		if($total == count($option['result'])) {
			$option['status'] = 1;
			$this->migrateSet('status', 1);
		}
		show_json('OK', true, $option);
	}

	// 重启某项
	public function restart(){
		$type = Input::get('type', 'require');
		$option = $this->migrateGet(false);
		if(!isset($option['result'][$type])) show_json('数据异常', false);
		// 重启非第一项时，可能因为cache被清空导致迁移无法完成
		$this->clearTips($type);
		$data = $option['result'][$type];
		$data['status'] = 0;
		$this->migrateSet($type, $data);
		$this->update();
	}

	private function clearTips($type){
		$index = array_search($type, array_keys($this->typeList));
		if($index && !$cache = $this->_itemCache()) {
			show_json('缓存数据异常，请 <a class="action" data-action="clear" href="javascript:void(0)">清除缓存</a> 后再次尝试', false);
		}
	}

	public function clear(){
		$key = $this->_taskKey();
		Cache::remove($key);
		Cache::remove("migrate.{$key}");
		$config = $this->getConfig();
		$config['migrate'] = array();
		$this->setConfig($config);
		show_json('OK');
	}

	// 获取文件内容
	public function filterData($file, $system = 0){
		if($system) $file = $this->_dataPath() . '/system/' . $file;
		$str = file_get_contents($file);
		return json_decode(substr($str, strlen('<?php exit;?>')),true);
	}

	/**
	 * 单项缓存获取和存储
	 * @param [type] $key
	 * @param [type] $value
	 * @return void
	 */
	public function _itemCache($key = null, $value = null){
		$taskKey = $this->_taskKey();
		if(!$taskCache = Cache::get($taskKey)) $taskCache = array();
		if(!$key) return $taskCache;
		if(is_null($value)) {
			return isset($taskCache[$key]) ? $taskCache[$key] : array();
		}
		$taskCache[$key] = $value;
		Cache::set($taskKey, $taskCache, 3600 * 24 * 30);
	}

    private function getObj($function){
		if(in_array($function, array('groupFile', 'userFile'))) {
			$function = 'file';
		}
		$function = ucfirst($function);
        include_once($this->pluginPath."php/{$function}.class.php");
		return new $function($this);
	}

	/**
	 * 更新迁移结果数据，cache、config
	 * @param [type] $key
	 * @param [type] $value
	 * @return void
	 */
	public function migrateSet($key, $value){
		$option = $this->migrateGet();
		if(isset($option[$key])) {
			$option[$key] = $value;	// status
		} else {
			$option['result'][$key] = $value;
		}
		$key = $this->_taskKey();
		Cache::set("migrate.{$key}", $option);	// migrate.xxx => 
		$config = $this->getConfig();
		$config['migrate'] = array($key => $option);	// [migrate][xxx] => 
		$this->setConfig($config);
		return $option;
	}

	/**
	 * 初始化迁移结果数据
	 * @param boolean $init	不是初始化时，只获取不新增
	 * @return void
	 */
	public function migrateGet($init = true){
		$key = $this->_taskKey();
		if($option = Cache::get("migrate.{$key}")) return $option;
		$config = $this->getConfig();
		if(!empty($config['migrate'][$key])) return $config['migrate'][$key];
		if(!$init) return array();

		$result = array();
		foreach($this->typeList as $key => $val) {
			$result[$key] = array(
				'status'	=> 0,
				'total'		=> 0,
				'success'	=> 0,
			);
		}
		$result['setting'] = array('status' => 0, 'data' => '');
		$result['groupFile'] = $result['userFile'] = array(
			'status'        => 0,
			'sizeTotal'     => 0,
			'sizeSuccess'   => 0,
			'cntTotal'      => 0,
			'cntSuccess'    => 0,
		);
		$option = array('status' => 0, 'result' => $result);
		Cache::set("migrate.{$key}", $option);	// migrate.xxx => 
		$config['migrate'] = array($key => $option);	// [migrate][xxx] => 
		$this->setConfig($config);

		return $option;
	}

	/**
	 * 获取迁移进度
	 * @return void
	 */
	public function progress(){
		if(!$type = Input::get('type')) {
			$option = $this->migrateGet(false);
			show_json($option);
		}
		if(!isset($this->typeList[$type])) {
			show_json('param error.', false);
		}
		$this->clearTips($type);
		$taskKey = "migrate.{$type}." . $this->_taskKey();
		if($result = Task::get($taskKey)) {
			if(!in_array($type, array('groupFile', 'userFile'))) {
				$data = array(
					'status'		=> 0,
					'total'			=> $result['taskTotal'],
					'success'		=> $result['taskFinished'],
				);
			}else{
				$data = array(
					'status'		=> 0,
					'cntTotal'		=> $result['taskTotal'],
					'cntSuccess'	=> $result['taskFinished'],
					'sizeTotal'		=> $result['sizeTotal'],
					'sizeSuccess'	=> $result['sizeFinished'],
				);
			}
			show_json(array($type => $data));
		}
		$option = $this->migrateGet(false);
		if(!isset($option['result']) || !isset($option['result'][$type])) {
			show_json(array());
		}
		$data = $option['result'][$type];
		show_json(array($type => $data));
	}

	// 开始子项任务
	public function taskStart($type, $total, $file = false) {
		$title = $this->typeList[$type] . '迁移';
		$taskKey = "migrate.{$type}." . $this->_taskKey();
		if($file) return new TaskFileTransfer($taskKey, 'migrate', $total, $title);
		return new Task($taskKey, 'migrate', $total, $title);
	}

	// 更新子项结果
	public function migrateItem($type, $finished) {
		if(!is_array($finished)) return array('code' => 0);
		$total = count($finished);
        $success = array_sum($finished);
        $update = array(
            'status'    => $total == $success ? 1 : -1,	// 0:进行中;1:成功;-1:失败
            'total'     => $total,
            'success'   => $success
		);
		$data = $this->migrateSet($type, $update);
        return array('code' => $update['status']);
	}

}