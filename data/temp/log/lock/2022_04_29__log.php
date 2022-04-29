<?php exit;?>
[20:22:18.063]  unlockRuntime:lock_dc244218e2262645fe07078060cb990f;{
    "0": "beforeShutdown()",
    "1": "app/autoload.php[180] Hook::trigger("beforeShutdown")",
    "2": "app/kod/Hook.class.php[109] Hook::apply("user.index.shutdownEvent",[])",
    "3": "app/kod/Hook.class.php[28] ActionApply("user.index.shutdownEvent",[])",
    "4": "app/autoload.php[108] {a1b}#userIndex->shutdownEvent()",
    "5": "app/controller/user/index.class.php[37] CacheLock::unlockRuntime()",
    "memory": "1.776M"
}
