<?php

namespace think;

use think\addon\traits\controller\Base;

/**
 * 插件基类，一般用于不需要模版的情况，例如：api接口.
 */
abstract class Addon
{
    use Base;
     // 参数配置所在控制器及方法
    protected $config_controller = 'Admin';
    protected $config_action = 'config';
    protected $assign_list = [];
    // 该项跟下面在有列表情况下必选其一
    protected $adminlist_file = '';
    // view展示的fecth内容
    protected $view_fetch = '';

    protected $access_url = [];
    // 需要的钩子列表
    protected $hook_list = [];

    public function __construct()
    {
        $this->_baseInit();
        // 控制器初始化
        if (method_exists($this, '_initialize')) {
            $this->_initialize();
        }
    }

    /**
     * 获取属性.
     *
     * @param string $name 名称
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * 检查配置信息是否完整.
     *
     * @return bool
     */
    final public function checkInfo()
    {
        $info_check_keys = ['name', 'title', 'description', 'status', 'author', 'version'];
        foreach ($info_check_keys as $value) {
            if (!array_key_exists($value, $this->info)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 插件默认执行的方法.
     *
     * @return mixed
     */
    final public function run()
    {
        if (App::$debug) {
            Log::record('[ ADDON ] 插件'.$this->getName().'运行异常，params:'.var_export($_SERVER, true), 'error');
        } else {
            Log::record('[ ADDON ] 运行异常，请联系开发者！', 'error');
        }
    }

    // 必须实现安装
    abstract public function install();

    // 必须卸载插件方法
    abstract public function uninstall();
}
