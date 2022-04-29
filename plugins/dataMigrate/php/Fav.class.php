<?php 
/**
 * 用户收藏迁移
 */
class Fav{
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

        $finished = array();
        // userID 为新用户id，path则为旧的主目录
        foreach($initPath as $userID => $path){
            if(!isset($newPath[$userID])) continue;
            if(!$newPath = $this->userHomePath($userID)) continue;
            // 我的分享
            $this->moveFav($userID, $path, $newPath, $finished, $task);
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
     * 我的收藏
     * @param [type] $oldPath   旧的用户home目录
     * @param [type] $newPath   新的用户主目录
     * @return void
     */
    private function moveFav($userID, $oldPath, $newPath, &$finished, &$task){
        $favFile = get_path_father($oldPath) . 'data/fav.php';
        if(!@file_exists($favFile)) return;

        $favList = $this->plugin->filterData($favFile);
        foreach($favList as $key => $info){
            $path = $info['path'];
            // 部门文件：{groupPath}:101/doc/
            if(stripos($path, '{groupPath}') === 0){
                // 根据部门id获取其对应目录
                $tmpArr = explode('/', str_replace('{groupPath}:', '', $path));
                $groupID = $tmpArr[0];
                if(!$res = Model('Group')->getInfo($groupID)) continue; // TODO 可以在moveGroup时存缓存
                $tmpPath = str_replace("{groupPath}:{$groupID}/", '', $path);
                $sourceID = $res['sourceInfo']['sourceID'];
                $path = KodIO::make($sourceID) . $tmpPath;
            }else{ 
                // 用户文件，非系统目录排除
                if(stripos($path, $oldPath) !== 0) continue;
                $tmpPath = str_replace($oldPath, '', $path);
                $path = $newPath . $tmpPath;
            }
            if(!$pathInfo = IO::infoFull($path)) {
                $finished[] = 0;
                continue;
            }
            $data = array(
                'name' => $info['name'],
                'path' => $pathInfo['path'],
                'type' => $info['type'] != 'folder' ? 'file' : 'folder'
            );
            if($this->addFav($userID,$data)) {
                $finished[] = 1;
                $task->update(1);
            }
        }
    }

    /**
     * 添加收藏，因为userID的问题，这里重复了UserFavModel的内容
     * @param [type] $userID
     * @param array $data
     * @return void
     */
    private function addFav($userID,$data = array()){
        // 1.判断该收藏是否已存在
        $where = array(
			"userID"	=> $userID,
			"tagID"		=> 0,
			"type"		=> $data['type'],
			"path"		=> $data['path']
		);
		if($find = Model('user_fav')->where($where)->find()) return $find['id'];
        // 2.获取最大收藏id $max
		$where  = array("userID" => $userID, "tagID" => 0);
		$max = Model('user_fav')->where($where)->max('sort');
		if(!$max) $max = 0;

		// name重名时处理
		$name = $this->getAutoName($userID, $data['name']);
		$data = array(
			"userID"	=> $userID,
			"tagID"		=> 0,
			"name"		=> $name,
			"path"		=> $data['path'],
			"type"		=> $data['type'],
			"sort"		=> $max+1,
		);
		return Model('user_fav')->add($data);
    }

    /**
     * 收藏，重命名
     * @param [type] $userID
     * @param [type] $name
     * @return void
     */
    private function getAutoName($userID, $name){
		$where  = array("userID" => $userID,"tagID"	=> 0);
		$list = Model('user_fav')->field('name')->where($where)->select();
		$list = array_to_keyvalue($list,'','name');
		if(!$list || !in_array($name,$list) ){//不存在
			return $name;
		}
		for ($i=0; $i < count($list); $i++) { 
			if(!in_array($name."({$i})",$list)){
				return $name."({$i})";
			}
		}
		return $name."({$i})";
	}
}