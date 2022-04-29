<?php 
/**
 * 部门、用户文档迁移
 */
class File{
    public $plugin;
    public function __construct($plugin){
        $this->plugin = $plugin;
    }

    public function move($type){
        $tmpType    = str_replace('File', '', $type); // group/userFile
        $initPath   = $this->oldHomePath($tmpType);
        $update = array(
            'status'        => -1,
            'sizeTotal'     => 0,
            'sizeSuccess'   => 0,
            'cntTotal'      => 0,
            'cntSuccess'    => 0,
        );
        if(!$initPath) {
            $update['status'] = 1;
            $this->plugin->migrateSet($type, $update);
            return array('code' => $update['status']);
        }
        $newPath    = $this->newHomePath($tmpType, array_keys($initPath));
        // 启动任务
        $task = $this->plugin->taskStart($type, 0, true);
        
        $destKey    = 'dest' . ucfirst($tmpType) . 'Moved';
        $destMoved  = $this->plugin->_itemCache($destKey);
        foreach($initPath as $id => $path) {
            $task->addPath($path);
            $pathTo = $newPath[$id];
            if(!$pathList = $this->pathList($path)) continue;
            // 复制原home下的所有文件到目标home目录下
            foreach($pathList as $pathFrom){
                if(isset($destMoved[$pathFrom])) continue;
                $destMoved[$pathFrom] = '';
                // write_log(array('复制文件', $type, $pathFrom, $pathTo));
                if(!$dest = IO::copy($pathFrom, $pathTo, 'replace')) {  // TODO 复制文件夹，会导致子文件重复
                    // TODO 可以记录下复制失败的，提醒手动复制
                    continue;
                }
                $destMoved[$pathFrom] = $dest;
            }
            $this->plugin->_itemCache($destKey, $destMoved);
        }
        // 更新迁移结果
        $update = $this->updateDest($type, $update, $destMoved);
        $task->end();

        return array('code' => $update['status']);
    }

    // 更新迁移结果信息
    private function updateDest($type, $update, $destMoved){
        foreach($destMoved as $old => $new) {
            $info = IO::infoWithChildren($old);
            $update['sizeTotal']    += $info['size'];
            $update['cntTotal']     += $info['type'] == 'folder' ? $info['children']['fileNum'] : 1;
            if(!$new) continue;
            $info = IO::infoWithChildren($new);
            $update['sizeSuccess']  += $info['size'];   // 没有实时更新
            $update['cntSuccess']   += $info['type'] == 'folder' ? $info['children']['fileNum'] : 1;
        }
        // TODO 可能出现重复的文件
        if($update['sizeTotal'] <= $update['sizeSuccess'] && $update['cntTotal'] <= $update['cntSuccess']) {
            $update['sizeSuccess'] = $update['sizeTotal'];
            $update['cntSuccess'] = $update['cntTotal'];
            $update['status'] = 1;
        }
        $this->plugin->migrateSet($type, $update);
        return $update;
    }

    private function oldHomePath($type){
        $cacheKey = 'init' . ucfirst($type) . 'Path';
        return $this->plugin->_itemCache($cacheKey);   // TODO 可能需要判断原始数据不存在的情况
        if(empty($path)) show_json(LNG('admin.install.defCacheError'), false);
        return $path;
    }

    /**
     * 新部门/用户根目录（下的home目录）
     * 直接迁移到根部门，会和初始数据混淆，出错时无法单独删除（根目录无法删除）
     * @param [type] $type  user;group
     * @param [type] $ids   array([id], [id])
     * @return void
     */
    private function newHomePath($type, $ids){
        if(empty($ids)) return array();
        $cacheKey = 'new' . ucfirst($type) . 'Path';
        $newPath = $this->plugin->_itemCache($cacheKey);
        if($newPath) return $newPath;

        $typeList = array('user' => 1, 'group' => 2);
        $where = array(
            'parentID'      => 0,
            'targetID'      => array('in', $ids),
            'targetType'    => $typeList[$type],
        );
        $res = Model('Source')->where($where)->field('targetID, sourceID')->select();
        $data = array();
        foreach($res as $value){
            $data[$value['targetID']] = KodIO::make($value['sourceID']);
        }
        $this->plugin->_itemCache($cacheKey, $data);
        return $data;
    }

    // 获取子文件（夹）列表
    public function pathList($dir){
        $dir = rtrim($dir,'/').'/';
        if (!is_dir($dir) || !($dh = @opendir($dir))) return array();
        $list = array();
        while (($file = readdir($dh)) !== false) {
            if ($file =='.' || $file =='..' || $file == ".svn") continue;
            $list[] = $dir . $file;
        }
        closedir($dh);
        return $list;
    }

}