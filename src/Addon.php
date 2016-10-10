<?php
namespace think;

use think\Request;
use think\Config;
use think\View;
use think\Loader;
use think\exception\TemplateNotFoundException;
use think\App;
use think\Log;
use think\Db;
/**
 * 插件类
 */
abstract class Addon{
    /**
     * 视图实例对象
     * @var view
     * @access protected
     */
    protected $view;

    protected $request;
    // 引擎配置
    protected $config = [
        'view_path'          => '', // 模板路径
        'view_suffix'        => 'html', // 默认模板文件后缀
        'view_depr'          => DS,
        'cache_suffix'       => 'php', // 默认模板缓存后缀
        'tpl_deny_func_list' => 'echo,exit', // 模板引擎禁用函数
        'tpl_deny_php'       => false, // 默认模板引擎是否禁用PHP原生代码
        'tpl_begin'          => '{', // 模板引擎普通标签开始标记
        'tpl_end'            => '}', // 模板引擎普通标签结束标记
        'strip_space'        => false, // 是否去除模板文件里面的html空格与换行
        'tpl_cache'          => true, // 是否开启模板编译缓存,设为false则每次都会重新编译
        'compile_type'       => 'file', // 模板编译类型
        'cache_prefix'       => '', // 模板缓存前缀标识，可以动态改变
        'cache_time'         => 0, // 模板缓存有效期 0 为永久，(以数字为值，单位:秒)
        'layout_on'          => false, // 布局模板开关
        'layout_name'        => 'layout', // 布局模板入口文件
        'layout_item'        => '{__CONTENT__}', // 布局模板的内容替换标识
        'taglib_begin'       => '{', // 标签库标签开始标记
        'taglib_end'         => '}', // 标签库标签结束标记
        'taglib_load'        => true, // 是否使用内置标签库之外的其它标签库，默认自动检测
        'taglib_build_in'    => 'cx', // 内置标签库名称(标签使用不必指定标签库名称),以逗号分隔 注意解析顺序
        'taglib_pre_load'    => '', // 需要额外加载的标签库(须指定标签库名称)，多个以逗号分隔
        'display_cache'      => false, // 模板渲染缓存
        'cache_id'           => '', // 模板缓存ID
        'tpl_replace_string' => [],
        'tpl_var_identify'   => 'array', // .语法变量识别，array|object|'', 为空时自动识别
    ];
    public $info = [];

    public $addon_path = '';

    public $config_file = '';
    
    // 参数配置所在控制器及方法
    public $custom_config = [];

    public $admin_list = [];
    // 该项跟下面在有列表情况下必选其一
    public $custom_adminlist = '';
    // view展示的fecth内容
    public $view_fetch = '';

    public $access_url = [];

    // 需要的钩子列表
    public $hook_list = [];
    
    public function __construct(Request $request = null)
    {
        // 获取当前插件目录
        $this->addon_path = ADDON_PATH . $this->getName() . DS;
        // 读取当前插件配置信息
        if (is_file($this->addon_path . 'config.php')) {
            $this->config_file = $this->addon_path . 'config.php';
        }
        // 初始化视图模型
        $this->config['view_path'] = $this->addon_path;
        $this->initTplReplaceString();
        $config = array_merge(Config::get('template'), $this->config);
        $this->view = new View($config, Config::get('view_replace_str'));
        // 控制器初始化
        if (method_exists($this, '_initialize')) {
            $this->_initialize();
        }
        if (is_null($request)) {
            $request = Request::instance();
        }
        $this->request = $request;
    }
    /**
     * 初始化模版替换参数
     */
    private function initTplReplaceString()
    {
        $this->config['tpl_replace_string'] = [
            '__COMMON__' => __ROOT__ . DS.'public'. DS.'static'. DS.'common',
            '__IMG__' => __ROOT__ . DS.'public'. DS.'addons'.DS.$this->getName(). DS.'images',
            '__CSS__' => __ROOT__ . DS.'public'. DS.'addons'.DS.$this->getName(). DS.'css',
            '__JS__' => __ROOT__ . DS.'public'. DS.'addons'.DS.$this->getName(). DS.'js',
            '__PUBLIC__' => __ROOT__ . DS.'public'. DS.'addons'.DS.$this->getName().DS,
            '__UPLOADS__' => __ROOT__ . DS.'public'. DS.'uploads',
            '__ROOT__' => __ROOT__
        ];
    }
    /**
     * 视图内容替换
     * @access public
     * @param string|array $content被替换内容（支持批量替换）
     * @param string $replace替换内容
     * @return $this
     */
    final protected function replace($content, $replace = '')
    {
        $this->view->replace($content, $replace);
    }

    /**
     * 模板变量赋值
     * 
     * @access protected
     * @param mixed $name要显示的模板变量
     * @param mixed $value变量的值
     * @return Action
     */
    final protected function assign($name, $value = '')
    {
        $this->view->assign($name, $value);
        return $this;
    }
    
    // 用于显示模板的方法
    final protected function fetch($template = null,$vars = [], $replace = [], $config = [])
    {
        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
        }
        // 模板不存在 抛出异常
        if (! is_file($template)) {
            throw new TemplateNotFoundException('template not exists:' . $template, $template);
        }
        // 记录插件运行信息
        App::$debug && Log::record('[ ADDON_VIEW ]' . $template . ' [ ' . $this->getName(). ' ]', 'info');
        // 关闭模板布局
        $this->view->engine->layout(false);
        echo  $this->view->fetch($template, $vars, $replace, $config);
    }
    /**
     * 渲染内容输出
     * @access public
     * @param string $content 内容
     * @param array $vars 模板输出变量
     * @param array $replace 替换内容
     * @param array $config 模板参数
     * @return mixed
     */
    final protected function display($content, $vars = [], $replace = [], $config = [])
    {
        // 关闭模板布局
        $this->view->engine->layout(false);
    
        echo $this->view->display($content, $vars, $replace, $config);
    }
    
    /**
     * 自动定位模板文件
     * @access private
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseTemplate($template)
    {
        return $this->config['view_path'] .$template. '.' . ltrim($this->config['view_suffix'], '.');
    }
    final public function getName(){
        $class = get_class($this);
        return strtolower(substr($class,strrpos($class, '\\')+1));
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
     * 获取插件的配置数组
     * 
     * @param string $name            
     * @param string $refresh
     *            为真时必须读取本地配置文件
     *            @date 2016年4月29日 下午2:57:32
     * @author @泛泛知惫 <lilwil@163.com>
     * @version v1.0.0
     */
    final public function getConfig($name = '', $refresh = false)
    {
        static $_config = [];
        if (empty($name)) {
            $name = $this->getName();
        }
        if (isset($_config[$name])) {
            return $_config[$name];
        }
        $config = [];
        if (! $refresh) {
            $config = Db::name('addons')->where('status', 1)->where('name', $name)->value('config');
        }
        if ($config) {
            $config = json_decode($config, true);
        } else {
            if (is_file($this->config_file)) {
                $config = include $this->config_file;
            } else {
                $config = [];
            }
            // 废除旧版配置读取逻辑，采用直接读取配置信息方法
        }
        $_config[$name] = $config;
        return $config;
    }

    /**
     * 获取当前错误信息
     * @return mixed
     */
    final public function getError()
    {
        return $this->error;
    }
    
    // 必须实现安装
    abstract public function install();
    
    // 必须卸载插件方法
    abstract public function uninstall();
}
