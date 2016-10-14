<?php
namespace think\addon\controller;

use think\Controller;
use think\Lang;
use think\Loader;
use think\Log;

/**
 * 插件执行默认控制器
 */
class Index extends Controller
{
    // 当前插件操作
    protected $addon;

    protected $controller;

    protected $action;

    /**
     * 插件初始化
     */
    public function _initialize()
    {
        // 日志初始化
        Log::init([
            'type' => 'File',
            'path' => RUNTIME_PATH . 'log' . DS . 'addons' . DS
        ]);
        $this->addon = Loader::parseName($this->request->get('_addon/s', ''), 1);
        $this->controller = Loader::parseName($this->request->get('_controller/s', ''), 1);
        $this->action = $this->request->get('_action/s', '');
        // 加载插件语言包
        Lang::load(__DIR__ . DS . 'lang' . DS . $this->request->langset() . EXT);
    }

    /**
     * 插件执行
     */
    public function execute()
    {
        if (! empty($this->addon) && ! empty($this->controller) && ! empty($this->action)) {
            // 获取类的命名空间
            $class = get_addon_class($this->addon, 'controller') . "\\{$this->controller}";
            $model = new $class($this->addon);
            if ($model === false) {
                return $this->error(Lang::get('addon init fail'));
            }
            // 调用操作
            return call_user_func([
                $model,
                $this->action
            ]);
        }
        return $this->error(Lang::get('addon cannot name or action'));
    }
}