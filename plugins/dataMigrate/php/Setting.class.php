<?php 
/**
 * 系统配置及插件迁移
 */
class Setting{
    public $plugin;
    public function __construct($plugin){
        $this->plugin = $plugin;
    }

    public function move($type) {
        $oldSetting = $this->plugin->filterData('system_setting.php', 1);
        $newSetting = Model('SystemOption')->get();

        // 1. 系统设置
        $code = $this->baseSetting($oldSetting, $newSetting);
        // 2. 插件列表更新——文件类型、使用权限
        $finished = $this->pluginSetting($oldSetting);
        // 3. light apps
        $this->lightAppSetting();

        $total = count($finished);
        $success = array_sum($finished);
        $msg = LNG('admin.install.defaultUpdate') . ($code ? LNG('common.success') : LNG('common.fail'));
        $msg .= "; ".LNG('admin.install.pluginUpdated')."({$success})";

        // 执行结果
        $update = array(
            'status'    => $success > 0 ? 1 : -1,
            'data'      => $msg,    // setting中data直接存文字信息
        );
        $this->plugin->migrateSet($type, $update);
        return array('code' => $update['status']);
    }

    // 系统配置
    private function baseSetting($oldSetting, $newSetting){
        // 待更新项
        $includes = array(
            'systemName', 'systemDesc', 'pathHidden', 'autoLogin', 'needCheckCode',
            'firstIn', 'newUserApp', 'newUserFolder', 'newGroupFolder',
            'rootListUser', 'rootListGroup', 'csrfProtect', 'globalIcp', 
        );
        $setting = array();
        foreach($includes as $key) {
            if(isset($oldSetting[$key])) $setting[$key] = $oldSetting[$key];
        }
        // menu重构
        $system = array('desktop', 'explorer', 'editor');   // 系统默认菜单
        $tmpArr = array('system' => array(), 'add' => array());
        // 新版无subMenu参数，系统默认菜单只更新use；自定义菜单更新全部
        foreach ($oldSetting['menu'] as $value) {
            if(!in_array($value['name'], $system)) continue;    // 自定义菜单可能出现乱码等情况，暂不同步
            $tmpArr['system'][$value['name']] = $value['use'];
            // if(in_array($value['name'], $system)){
            //     $tmpArr['system'][$value['name']] = $value['use'];  
            //     continue;
            // }
            // unset($value['subMenu']);
            // $tmpArr['add'][] = $value;
        }
        $newMenu = $newSetting['menu'];
        foreach ($newMenu as $k => $value) {
            // 新版菜单（系统默认）在tmpArr中不存在，说明旧版已删除
            if(!isset($tmpArr['system'][$value['name']])){
                unset($newMenu[$k]);
                continue;
            }
            $newMenu[$k]['use'] = $tmpArr['system'][$value['name']];
        }
        if(!empty($tmpArr['add'])){
            $newMenu = array_merge($newMenu, $tmpArr['add']);
        }
        $setting['menu'] = $newMenu;
        return Model('SystemOption')->set($setting);
    }

    // 插件列表
    private function pluginSetting($oldSetting){
        // 只更新系统默认插件，自定义插件可能不兼容，需手动迁移调整
        $initUser  = $this->plugin->_itemCache('initUser');
        $initRole  = $this->plugin->_itemCache('initRole');
        $finished = array();
        $pluginList = Model('Plugin')->loadList();
        foreach($oldSetting['pluginList'] as $app => $plugin){
            if(!isset($pluginList[$app])) {
                $finished[] = 0;
                continue;
            }
            $config = $plugin['config'];
            if(isset($config['fileExt'])){
                $config['fileExt'] = $pluginList[$app]['config']['fileExt'];
            }
            $this->pluginAuth($app, $config, $initUser, $initRole);
            Model('Plugin')->setConfig($app, $config);
            Model('Plugin')->changeStatus($app,$plugin['status']);
            $finished[] = 1;
        }
        return $finished;
    }

    /**
     * 更新插件配置信息
     * all:0;user:101,100;group:103,102;role:2 => {"all":"0","user":"3,2","group":"103,102","role":"2"}
     * @param [type] $app
     * @param [type] $config
     * @param [type] $initUser
     * @param [type] $initRole
     * @return void
     */
    private function pluginAuth($app, &$config, $initUser, $initRole){
        if(!isset($config['pluginAuth'])) return;
        $auth = explode(';', $config['pluginAuth']);
        $tmpAuth = array();
        foreach($auth as $item){
            $tmp = explode(':', $item);
            $tmpAuth[$tmp[0]] = $tmp[1];
        }
        if($tmpAuth['all'] == 1) return $config['pluginAuth'] = array('all' => 1);
        if(!empty($tmpAuth['user'])){
            $users = array();
            $tmp = explode(',', $tmpAuth['user']);
            foreach($tmp as $userID){
                if(isset($initUser[$userID])) $users[] = $initUser[$userID];
            }
            $tmpAuth['user'] = implode(',', $users);
        }
        if(!empty($tmpAuth['role'])){
            $roles = array();
            $tmp = explode(',', $tmpAuth['role']);
            foreach($tmp as $roleID){
                if(isset($initRole[$roleID])) $roles[] = $initRole[$roleID];
            }
            $tmpAuth['role'] = implode(',', $roles);
        }
        $config['pluginAuth'] = json_encode($tmpAuth);
    }

    // 轻应用
    private function lightAppSetting(){
        $newList = Model('SystemLightApp')->listData();
        $newList = array_to_keyvalue($newList,'name');
        $oldList = $oldSetting = $this->plugin->filterData('apps.php', 1);
        foreach($oldList as $app => $value) {
            if(isset($newList[$app])) continue;
            $item = array(
                'name'      => $value['name'],
                'group'     => $value['group'],
                'desc'      => $value['desc'],
                'content'   => array(
                    'type'      => $value['type'] == 'url' ? 'url' : 'js',
                    'icon'      => $value['icon'],
                    'options'   => array(
                        'width'     => $value['with'],
                        'height'    => $value['height'],
                        'simple'    => $value['simple'],
                        'resize'    => $value['resize'],
                    ),
                    'value' => $value['content']
                )
            );
            Model('SystemLightApp')->add($item);
        }
    }
}