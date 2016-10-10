<?php
namespace think\addons;

use think\Controller;
use think\Lang;

/**
 * 插件执行默认控制器
 */
class AddonsController extends Controller
{
    // 当前插件操作
    protected $addon = null;

    protected $controller = null;

    protected $action = null;

    /**
     * 插件初始化
     */
    public function _initialize()
    {
        $this->addon = ucfirst($this->request->get('_addon/s', ''));
        $this->controller = ucfirst($this->request->get('_controller/s', ''));
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
            $model = new $class();
            if ($model === false) {
                return $this->error(lang('addon init fail'));
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