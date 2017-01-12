<?php

namespace think\addon\controller;

use think\addon\traits\controller\Base;
use think\Loader;
use think\Log;

/**
 * 插件执行默认控制器.
 */
class Index
{
    use Base;
    // 当前插件操作
    protected $addon;

    protected $controller;

    protected $action;

    /**
     * 插件初始化.
     */
    public function __construct()
    {
        $this->_baseInit();
        // 日志初始化
        Log::init([
            'type' => 'File',
            'path' => RUNTIME_PATH.'log'.DS.'addons'.DS,
        ]);
        $this->addon = Loader::parseName($this->request->param('_addon/s', ''), 1);
        $this->controller = Loader::parseName($this->request->param('_controller/s', ''), 1);
        $this->action = $this->request->param('_action/s', '');
    }

    /**
     * 插件执行.
     */
    public function execute()
    {
        if (!empty($this->addon) && !empty($this->controller) && !empty($this->action)) {
            // 获取类的命名空间
            $class = get_addon_class($this->addon, 'controller')."\\{$this->controller}";
            $model = new $class();
            if ($model === false) {
                return $this->error(Lang::get('addon init fail'));
            }
            // 调用操作
            return call_user_func([
                $model,
                $this->action,
            ]);
        }

        return $this->error(Lang::get('addon cannot name or action'));
    }
}
