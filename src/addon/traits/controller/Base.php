<?php
namespace think\addon\traits\controller;

use think\Config;
use think\Db;
use think\Loader;
use think\Lang;
use think\View;
use think\Request;
trait Base
{
    //当前插件的配置文件位置
    protected $config_file;
    //当前执行的插件名字
    protected $addon_name;
    //当前插件路径
    protected $addon_path = '';
    //当前插件的默认信息
    protected $info;
    /**
     * @var \think\Request Request实例
     */
    protected $request;
    /**
     * 架构函数
     */
    protected function _baseInit(Request $request = null)
    {    
        if (is_null($request)) {
            $request = Request::instance();
        }
        $this->request = $request;
        $this->addon_name = $this->getName();
        // 获取当前插件目录
        $this->addon_path = ADDON_PATH . $this->addon_name . DS;
        // 读取当前插件配置信息
        if (is_file($this->addon_path . 'config.php')) {
            $this->config_file = $this->addon_path . 'config.php';
        }
        // 读取当前插件的信息
        if (is_file($this->addon_path . 'info.php')) {
            $this->info = include $this->addon_path . 'info.php';
        }
        // 加载插件语言包
        Lang::load(__DIR__ . DS .'..'. DS.'lang' . DS . $this->request->langset() . EXT);
        //编译模版之前替换
        $this->view->config('tpl_replace_string',$this->tplReplaceString()); 
    }
  
    /**
     * 初始化模版替换参数
     */
    private function tplReplaceString()
    {
        return [
            '__COMMON__' => __ROOT__ .'/static/common',
            '__IMG__' => __ROOT__ .'/addons/'.$this->addon_name.'/images',
            '__CSS__' => __ROOT__ .'/addons/'.$this->addon_name.'/css',
            '__JS__' => __ROOT__ .'/addons/'.$this->addon_name.'/js',
            '__PUBLIC__' => __ROOT__ .'/addons/'.$this->addon_name,
            '__UPLOADS__' => __ROOT__ .'/uploads',
            '__ROOT__' => __ROOT__
        ];
    }
    /**
     * 获取插件的配置数组
     * @param string $name 读取参数名
     * @author @泛泛知惫 <lilwil@163.com>
     */
    final protected function getConfig($name = '')
    {
        static $config;
        if (! empty($config) && ! $refresh) {
            return $config;
        }
        $config = Db::name('addons')->where('status', 1)->where('name', $this->getName(1))->value('config');
        if ($config) {
            $config = json_decode($config, true);
        } else {
            if (is_file($this->config_file)) {
                $config = include $this->config_file;
            } else {
                $config = [];
            }
        }
        if ($name && isset($config[$name])) {
            return $config[$name];
        }else {
            return $config;
        }
    }

    /**
     * 获取当前所属插件名
     * @param integer $type 转换类型
     * @param bool $ucfirst 首字母是否大写（驼峰规则）
     * @author @泛泛知惫 <lilwil@163.com>
     */
    final protected function getName($type = 0, $ucfirst = true)
    {
        if ($this->request->has('_addon/s')) {
             return Loader::parseName($this->request->param('_addon/s', ''),$type,$ucfirst);
        }else {
            return Loader::parseName(explode('\\',get_class($this))[1],$type,$ucfirst);
        }
    }
     
}
