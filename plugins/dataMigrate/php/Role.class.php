<?php 
/**
 * 角色迁移
 */
class Role{
    public $plugin;
    public function __construct($plugin){
        $this->plugin = $plugin;
    }

    public function move($type){
        $roleList = $this->plugin->filterData('system_role.php', 1);
		$initList = Model('SystemRole')->listData();
        $initList = array_to_keyvalue($initList, 'name');
        // 启动任务
        $task = $this->plugin->taskStart($type, count($roleList));

		$tmpRole = array();
		$finished = array();
        foreach ($roleList as $id => $role) {
            $finished[$id] = 0;
            // administrator跳过；default重命名新建（避免更改系统默认项）
            if ($id == 1) {
				$finished['1'] = 1; // status
                $tmpRole['1'] = $id;    // id
                $task->update(1);
                continue;
            }
            // default有默认项，权限可能不同，更新名字
            $name = $role['name'] . ($role['name'] == 'default' ? '_' . date('md') : '');
            $GLOBALS['in'] = array(
                'name' => $name,
                'auth' => implode(',', $this->filterRole($id, $role)),
			);
            // 已存在则更新，否则新增——moveGroup相同
            if(isset($initList[$name])){
                if($initList[$name]['auth'] != $GLOBALS['in']['auth']){
                    $GLOBALS['in']['id'] = $initList[$name]['id'];
                    ActionCallHook('admin.role.edit');
				}
				$finished[$id] = 1;
                $tmpRole[$id] = $initList[$name]['id'];
                $task->update(1);
                continue;
            }
            $res = ActionCallHook('admin.role.add');
            if(!$res['code']) continue;
            $finished[$id] = 1;
            $tmpRole[$id] = $res['info'];
            $task->update(1);
        }
        $this->plugin->_itemCache('initRole', $tmpRole);  // roleID (old => new)
        $task->end();

        // 执行结果
        return $this->plugin->migrateItem($type, $finished);
    }

    /**
     * 过滤角色
     * @param [type] $id
     * @param [type] $role
     * @return void
     */
    private function filterRole($id, $role){
        $newRole = array(
            "explorer.add"			    => 1,
            "explorer.upload"		    => 1,
            "explorer.view"			    => 1,
            "explorer.download" 	    => 1,
            "explorer.share" 		    => 1,
            "explorer.remove" 		    => 1,
            "explorer.edit" 		    => 1,
            "explorer.move" 		    => 1,
            "explorer.serverDownload"   => 1,
            "explorer.search" 		    => 1,
            "explorer.unzip" 		    => 1,
            "explorer.zip" 			    => 1,
            "user.edit" 			    => 1,
            "user.fav" 				    => 1,
            "admin.index.dashboard"     => 0,
            "admin.index.setting" 	    => 0,
            "admin.index.loginLog"	    => 0,
            "admin.index.log" 		    => 0,
            "admin.index.server" 	    => 0,
            "admin.role.list" 		    => 0,
            "admin.role.edit" 		    => 0,
            "admin.job.list" 		    => 0,
            "admin.job.edit" 		    => 0,
            "admin.member.list" 	    => 1,
            "admin.member.userEdit"     => 1,
            "admin.member.groupEdit"    => 1,
            "admin.auth.list" 		    => 0,
            "admin.auth.edit" 		    => 0,
            "admin.plugin.list" 	    => 1,
            "admin.plugin.edit" 	    => 1,
            "admin.storage.list" 	    => 0,
            "admin.storage.edit" 	    => 0,
            "admin.autoTask.list" 	    => 0,
            "admin.autoTask.edit" 	    => 0,
        );
		// key对应旧版角色身份选项
        $filter = array(
            'explorer.add'			    => array('explorer.mkfile', 'explorer.mkdir'),
            'explorer.edit'			    => array('explorer.pathRname', 'editor.fileSave'),
            'explorer.move'			    => array('explorer.pathCopy', 'explorer.pathCute', 'explorer.pathCuteDrag', 'explorer.clipboard', 'explorer.pathPast'),
            'explorer.remove'		    => array('explorer.pathDelete'),
            'explorer.zip'			    => array('explorer.zip'),
            'explorer.unzip'		    => array('explorer.unzip'),
            'explorer.search'		    => array('explorer.search'),
            'explorer.upload'		    => array('explorer.fileUpload'),
            'explorer.serverDownload'   => array('explorer.serverDownload'),
            'explorer.download'		    => array('explorer.fileDownload'),
            'explorer.share'		    => array('userShare.set', 'userShare.del'),
            'user.edit'				    => array('user.changePassword', 'setting.set'),
            'user.fav'				    => array('fav.edit', 'fav.add', 'fav.del'),
            'admin.member.list' 	    => array('systemMember.get', 'systemGroup.get'),
            'admin.member.userEdit'	    => array('systemMember.add', 'systemMember.edit', 'systemMember.doAction'),
            'admin.member.groupEdit'    => array('systemGroup.add', 'systemGroup.edit', 'systemGroup.del'),
            'admin.plugin.list' 	    => array('pluginApp.index', 'pluginApp.appList'),
            'admin.plugin.edit' 	    => array('pluginApp.setConfig', 'pluginApp.changeStatus', 'pluginApp.install', 'pluginApp.unInstall'),
        );
        foreach($filter as $key => $value){
            $tmp = array();
            foreach($value as $val){
                $tmp[$val] = isset($role[$val]) ? $role[$val] : 0;
            }
            if(array_sum($tmp) == 0) unset($newRole[$key]);
        }
        return array_keys(array_filter($newRole));
	}

}