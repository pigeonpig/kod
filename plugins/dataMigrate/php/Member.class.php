<?php 
/**
 * 用户迁移
 */
class Member{
    public $plugin;
    public function __construct($plugin){
        $this->plugin = $plugin;
    }

    public function move($type){
        $initRole = $this->plugin->_itemCache('initRole');
        $initAuth = $this->plugin->_itemCache('initAuth');
        $initUser = $initUserPath = array();
        $memberList = $this->plugin->filterData('system_member.php', 1);
        // 启动任务
        $task = $this->plugin->taskStart($type, count($memberList));

        $finished = array();
        foreach($memberList as $userInfo){
            $finished[$userInfo['userID']] = 0;
            // 1.新增用户，已存在跳过
            $res = $this->userAdd($userInfo, $initRole, $initAuth);
            if(!$res['code']) continue;
            $userID = $res['info'];
            // 2.更新用户密码——管理员除外
            if($userID != '1' && !$this->pwdEdit($userID, $userInfo['password'])) {
                $GLOBALS['in'] = array('userID' => $userID);
                ActionCallHook('admin.member.remove');
                continue;
            }
            $finished[$userInfo['userID']] = 1;
            $initUser[$userInfo['userID']] = $userID;
            $task->update(1);

            // 3.更新用户配置信息
            if(empty($userInfo['path'])) continue;
            $ROOT_PATH = $this->plugin->_dataPath() . "/User/{$userInfo['path']}/";
            if(@is_dir($ROOT_PATH . 'data/')){
                // TODO 轻应用更新
                // 用户配置信息
                $this->userConfig($userID, $ROOT_PATH);
            }
            // 4.记录用户目录，用于文档迁移
            if(!@is_dir($ROOT_PATH . 'home/')) continue;
            $initUserPath[$userID] = $ROOT_PATH . 'home/'; // 用户目录 newid=>oldpath
        }
        $this->plugin->_itemCache('initUser', $initUser); // oldid=>newid，用于更新插件权限用户
        $this->plugin->_itemCache('initUserPath', $initUserPath);
        $task->end();

        // 执行结果
        return $this->plugin->migrateItem($type, $finished);
    }

    /**
     * 更新用户密码
     * @param [type] $userID
     * @param [type] $password
     * @return void
     */
    private function pwdEdit($userID, $password){
        $res = Model('User')->metaSet($userID,'passwordSalt','');
        if(!$res) return false;
        $where  = array('userID'    => $userID);
        $update = array('password'  => $password);
        return Model('User')->where($where)->save($update);
    }
    /**
     * 新增用户
     * @param [type] $userInfo
     * @param [type] $initRole
     * @param [type] $initAuth
     * @return void
     */
    private function userAdd($userInfo, $initRole, $initAuth){
        $func = 'add';
        $roleID = isset($initRole[$userInfo['role']]) ? $initRole[$userInfo['role']] : 2;
        $password = $userInfo['password'];
        if($userInfo['userID'] == '1') {
            $func = 'edit';
            $roleID = 1;
            $password = ''; // 管理员不更新密码
        }
        $groupInfo = $this->userGroupInfo($userInfo, $initAuth);
        $in = array(
            'name'      => $userInfo['name'],
            'sizeMax'   => $userInfo['config']['sizeMax'],
            'roleID'    => $roleID, // default
            'password'  => $password,
            'nickName'  => isset($userInfo['nickName']) ? $userInfo['nickName'] : '',
            'status'    => $userInfo['status'],
            'groupInfo' => json_encode($groupInfo)
        );
        // 超管账号更新
        if($userInfo['userID'] == '1') $in['userID'] = '1';

        $GLOBALS['in'] = $in;
        return ActionCallHook("admin.member.{$func}");
    }
    /**
     * 用户部门-权限信息
     * @param [type] $userInfo
     * @param [type] $initAuth
     * @return void
     */
    private function userGroupInfo($userInfo, $initAuth){
        $groupInfo = !empty($userInfo['groupInfo']) ? $userInfo['groupInfo'] : array();
        $default = array('read' => '1', 'write' => '2');
        foreach($groupInfo as $group => $auth){
            $authID = isset($default[$auth]) ? $default[$auth] : $auth;
            $groupInfo[$group] = $initAuth[$authID];
        }
        return $groupInfo;
    }
    /**
     * 更新用户配置信息
     * @param [type] $userID
     * @param [type] $ROOT_PATH
     * @return void
     */
    private function userConfig($userID, $ROOT_PATH){
        $configFile = $ROOT_PATH . 'data/config.php';
        $editorFile = $ROOT_PATH . 'data/editor_config.php';
        // 配置信息
        if(@file_exists($configFile)){
            $config  = $this->plugin->filterData($configFile);
            $default = $this->plugin->config['settingDefault'];
            $config  = array_merge($default, $config);
            Model('UserOption')->set($config);
        }
        // 编辑器配置信息
        if(@file_exists($editorFile)){
            $config  = $this->plugin->filterData($editorFile);
            $default = $this->plugin->config['editorDefault'];
            $config  = array_merge($default, $config);
            Model('UserOption')->set($config);
        }
    }
}