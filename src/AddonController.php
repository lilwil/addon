<?php
namespace think;

use think\Config;
use think\Loader;
use think\App;
use think\Log;
use think\Db;
use think\addon\controller\Controller as BaseController;
/**
 * 插件基类
 */
abstract class AddonController extends BaseController{

     // 参数配置所在控制器及方法
    protected $config_controller = 'Admin';
    protected $config_action = 'config';
    //后台列表赋值
    protected $assign_list = [];
    // 该项跟下面在有列表情况下必选其一
    protected $adminlist_file = '';
    // view展示的fecth内容
    protected $view_fetch = '';

    protected $access_url = [];
    // 需要的钩子列表
    protected $hook_list = [];

    /**
     * 获取属性
     * @access public
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }
    /**
     * 加载模板输出
     * @access protected
     * @param string $template 模板文件名
     * @param array  $vars     模板输出变量
     * @param array  $replace  模板替换
     * @param array  $config   模板参数
     * @return mixed
     */
    protected function fetch($template = '', $vars = [], $replace = [], $config = [])
    {
        return $this->view->fetch($this->addon_path.$template.'.'.(Config::get('template.view_suffix')?:'html'), $vars, $replace, $config);
    }
    /**
     * 检查配置信息是否完整
     * @return bool
     */
    final public function checkInfo(){
        $info_check_keys = ['name','title','description','status','author','version'];
        foreach ($info_check_keys as $value) {
            if(!array_key_exists($value, $this->info))
                return FALSE;
        }
        return TRUE;
    }

    /**
     * 插件默认执行的方法
     * @return mixed
     */
    final public function run()
    {
        if (App::$debug){
            Log::record('[ ADDON ] 插件' . $this->getName() .'运行异常，params:'. var_export($_SERVER, true), 'error');
        }else {
            Log::record('[ ADDON ] 运行异常，请联系开发者！', 'error');
        }
    }
    // 必须实现安装
    abstract public function install();
    
    // 必须卸载插件方法
    abstract public function uninstall();
}
