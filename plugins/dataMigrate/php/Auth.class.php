<?php 
/**
 * 文档权限迁移
 */
class Auth {
    public $plugin;
    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    public function move($type) {
        $authList = $this->plugin->filterData('system_role_group.php', 1);
        $initAuth = Model('Auth')->listData(); // 系统默认的auth唯一
        $initAuth = array_to_keyvalue($initAuth, 'auth');
        // 启动任务
        $task = $this->plugin->taskStart($type, count($authList));

        $finished = array();
        $tmpAuth = $tmpEdit = array();
        foreach ($authList as $initID => $auth) {
            $authID = $this->filterAuth($auth['actions'], $initAuth); // 返回authID，用于更新
            $tmpAuth[$initID] = $authID;
            $tmpEdit[$authID]['initID'][] = $initID;
            $tmpEdit[$authID]['display'][] = $auth['display'];
        }
        foreach ($tmpEdit as $id => $item) {
            $GLOBALS['in'] = array(
                'id' => $id,
                // 'name' => $auth['name'],     // 内置项，不更新名称
                // 'label' => 'label-' . $auth['style'],    // 更新会导致label颜色错乱
                'display' => array_sum($item['display']) ? 1 : 0, // 有一个开启则都开启
            );
            $res = ActionCallHook('admin.auth.edit');
            foreach ($item['initID'] as $initID) {
                if ($res['code']) {
                    $finished[$initID] = 1;
                    $task->update(1);
                } else {
                    unset($tmpAuth[$initID]);
                    $task->task['taskTotal'] -= 1;
                }
            }
        }
        $this->plugin->_itemCache('initAuth', $tmpAuth);
        $task->end();

        // 执行结果
        return $this->plugin->migrateItem($type, $finished);
    }

    private function filterAuth($auth, $initAuth) {
        // 无文件列表——无权限
        if ($auth['read:list'] == '0') {
            return $initAuth['0']['id'];
        }
        // 全都有——拥有者
        if (array_sum($auth) == 10) {
            return $initAuth['33554943']['id'];
        }
        // 无编辑保存——预览者；否则——编辑者
        return $auth['write:edit'] == '1' ? $initAuth['511']['id'] : $initAuth['3']['id'];
    }
}
