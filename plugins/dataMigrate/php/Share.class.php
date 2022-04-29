<?php 
/**
 * 用户分享迁移
 */
class Share{
    public $plugin;
    public function __construct($plugin){
        $this->plugin = $plugin;
    }

    public function move($type){
        $initPath = $this->plugin->_itemCache('initUserPath');
        $newPath  = $this->plugin->_itemCache('newUserPath');
        if(empty($initPath) || empty($newPath)) {
            return $this->plugin->migrateItem($type, array());
            // show_json(LNG('admin.install.defCacheError'), false);
        }
        // 启动任务
        $task = $this->plugin->taskStart($type, count($initPath));

        // userID 为新用户id，path则为旧的主目录
        $finished = array();
        foreach($initPath as $userID => $path){
            if(!isset($newPath[$userID])) continue;
            if(!$newPath = $this->userHomePath($userID)) continue;
            // 我的分享
            $this->moveShare($userID, $path, $newPath, $finished, $task);
        }
        $task->task['taskTotal'] = count($finished);
        $task->end();
        // 执行结果
        return $this->plugin->migrateItem($type, $finished);
    }

    /**
     * 用户根目录
     * @param [type] $userID
     * @return void
     */
    private function userHomePath($userID){
        $res = Model('User')->getInfo($userID);
        if(!$res) return false;
        if(!isset($res['sourceInfo']['sourceID'])) return false;
        return KodIO::make($res['sourceInfo']['sourceID']);
    }


    /**
     * 我的分享
     * @param [type] $oldPath
     * @param [type] $newPath
     * @return void
     */
    private function moveShare($userID, $oldPath, $newPath, &$finished, &$task){
        // 分享只能分享我的文档中的文件，所以直接从myhome匹配获取
        // 旧：http://localhost/explorer/index.php?share/file&user=1&sid=rAX6NEum
        // 新：http://localhost/kod/kodbox/#s/5F__fSCA
        $shareFile = get_path_father($oldPath) . 'data/share.php';
        if(!@file_exists($shareFile)) return;

        $shareID = 0;
        $shareList = $this->plugin->filterData($shareFile);
        foreach($shareList as $sid => $info){
            // 排除不存在、非我的文档
            if(!@file_exists($info['path']) || stripos($info['path'], $oldPath) !== 0) continue;
            $finished[$sid] = 0;
            $tmpPath = str_replace($oldPath, '', $info['path']);    // 收藏文件实为/home/下的文件
            if(!$pathInfo = IO::infoFull($newPath . $tmpPath)) continue;

            $data = array(
                'isLink' => 1,
                'isShareTo' => 0,
                'shareHash' => $sid,
                'title' => $info['showName'],
                'password' => !empty($info['sharePassword']) ? $info['sharePassword'] : '',
                'options' => '',
                'timeTo' => !empty($info['timeTo']) ? strtotime($info['timeTo']) : '',
                'authTo' => array(),
                'numView' => !empty($info['numView']) ? $info['numView'] : 0,
                'numDownload' => !empty($info['numDownload']) ? $info['numDownload'] : 0,
            );
            $options = array();
            if($info['notDownload'] == '1') $options['notDownload'] = 1;
            if($info['type'] == 'folder'){
                if($info['canUpload'] == '1') $options['canUpload'] = 1;
                if(empty($info['codeRead'])){
                    $options['notView'] = 1;
                    $options['notDownload'] = 1;
                }
            }
            $data['options'] = $options;
            if($shareID = $this->addShare($userID, $pathInfo['sourceID'], $data)) {
                $finished[$sid] = 1;
                $task->update(1);
            }
        }
        $finished = array_values($finished);
        if($shareID) Model('share')->shareEdit($shareID, array('isLink' => 1));    // 只需更新一个，用以刷新缓存
    }

    /**
     * 添加分享
     * @param [type] $userID
     * @param [type] $sourceID
     * @param array $param
     * @return void
     */
    private function addShare($userID, $sourceID, $param=array()){
		$where = array(
			"userID"	=> $userID,
			"sourceID"	=> $sourceID,
		);
		//同一个资源分享只允许一个实例
		if($find = Model('share')->where($where)->find()){
			return $find['shareID'];
		}
		$sourceInfo = Model('Source')->sourceInfo($sourceID);
		if(!$sourceInfo) return false;
		if(!$param['title']){
			$param['title'] = $sourceInfo['name'];
		}
		$data = $where;
		$fields = explode(',','isLink,isShareTo,title,password,timeTo,options,shareHash,numView,numDownload');
		foreach ($fields as $field) {
			$data[$field] = $param[$field];
		}
		return Model('share')->add($data);
    }
}