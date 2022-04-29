<?php 
/**
 * 部门迁移
 */
class Group{
    public $plugin;
    public function __construct($plugin){
        $this->plugin = $plugin;
    }

    public function move($type){
        $groupList = $this->plugin->filterData('system_group.php', 1);
        $initList = Model('Group')->listByID(array_keys($groupList));
        $initList = array_to_keyvalue($initList, 'groupID');
        // 启动任务
        $task = $this->plugin->taskStart($type, count($groupList));

		$finished = array();
        $initGroupPath = array();
        foreach($groupList as $group){
            $finished[$group['groupID']] = 0;
            $GLOBALS['in'] = array(
                'groupID'	=> $group['groupID'],
                'name'		=> $group['name'],
                'sizeMax'	=> $group['config']['sizeMax'],
                'parentID'	=> !empty($group['parentID']) ? $group['parentID'] : 0
            );
            $func = isset($initList[$group['groupID']]) ? 'edit' : 'add';
            $res = ActionCallHook("admin.group.{$func}");
            if(!$res['code']) {
                write_log(array('部门更新异常', $GLOBALS['in'], $res), 'migration');
                continue;
            }
            $finished[$group['groupID']] = 1;
            $task->update(1);
            // 部门目录
            if(empty($group['path'])) continue;
            $ROOT_PATH = $this->plugin->_dataPath() . "/Group/{$group['path']}/";
            if(!@is_dir($ROOT_PATH . 'home/')) continue;
            $initGroupPath[$group['groupID']] = $ROOT_PATH . 'home/';
        }
        $this->plugin->_itemCache('initGroupPath', $initGroupPath); // 部门目录，用于文档迁移
        $task->end();

        // 执行结果
        return $this->plugin->migrateItem($type, $finished);
    }

}