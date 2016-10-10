<?php
namespace think\addon\controller;

\think\Loader::import('controller/Jump', TRAIT_PATH, EXT);

use think\Exception;
use think\exception\ValidateException;
use think\Request;
use think\View;
use think\Loader;
/**
 * 插件需要继承的控制器
 */
class Base 
{ 
    
     use \traits\controller\Jump;

    // 视图类实例
    protected $view;
    // Request实例
    protected $request;
    // 验证失败是否抛出异常
    protected $failException = false;
    // 是否批量验证
    protected $batchValidate = false;
    //当前执行的插件名字
    protected $addon_name;
    //当前插件路径
    protected $addon_path = '';
    // 引擎配置
    protected $view_config = [
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
    /**
     * 前置操作方法列表
     * @var array $beforeActionList
     * @access protected
     */
    protected $beforeActionList = [];

    /**
     * 架构函数
     * @param Request $request Request对象
     * @access public
     */
    public function __construct($addon_name = null,Request $request = null)
    {
        if (!empty($addon_name)) {
            $this->addon_name = $addon_name;
        }else {
            throw new \Exception(Lang::get('need addon name'));
        }
        if (is_null($request)) {
            $request = Request::instance();
        }
        // 获取当前插件目录
        $this->addon_path = ADDON_PATH . strtolower($this->addon_name) . DS . 'view'.DS;
        // 初始化视图模型
        $this->initTplReplaceString();
        $this->view_config['view_path'] = $this->addon_path;
//         $this->view    = View::instance($this->view_config);
        $this->view    =  new View($this->view_config);
        $this->request = $request;
        // 控制器初始化
        $this->_initialize();
        // 前置操作方法
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method => $options) {
                is_numeric($method) ?
                $this->beforeAction($options) :
                $this->beforeAction($method, $options);
            }
        }
    }

    private function initTplReplaceString()
    {
        $this->view_config['tpl_replace_string'] = [
            '__COMMON__' => __ROOT__ .'/public/static/common/',
            '__IMG__' => __ROOT__ .'/public/addons/'.strtolower($this->addon_name).'/images/',
            '__CSS__' => __ROOT__ .'public/addons'.DS.strtolower($this->addon_name).'css'.DS,
            '__JS__' => __ROOT__ .'public/addons'.DS.strtolower($this->addon_name).'js'.DS,
            '__PUBLIC__' => __ROOT__ .'public/addons'.DS.strtolower($this->addon_name).DS,
            '__UPLOADS__' => __ROOT__ .'public/uploads'.DS,
            '__ROOT__' => __ROOT__.DS
        ];
    }
    // 初始化
    protected function _initialize()
    {
    }

    /**
     * 前置操作
     * @access protected
     * @param string $method  前置操作方法名
     * @param array  $options 调用参数 ['only'=>[...]] 或者['except'=>[...]]
     */
    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only'])) {
            if (is_string($options['only'])) {
                $options['only'] = explode(',', $options['only']);
            }
            if (!in_array($this->request->action(), $options['only'])) {
                return;
            }
        } elseif (isset($options['except'])) {
            if (is_string($options['except'])) {
                $options['except'] = explode(',', $options['except']);
            }
            if (in_array($this->request->action(), $options['except'])) {
                return;
            }
        }

        call_user_func([$this, $method]);
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
        if (empty($template)) {
            throw new \Exception(Lang::get('need template name'));
        }else {
            return $this->view->fetch($this->parseTemplate($template), $vars, $replace, $config);
        }
    }

    /**
     * 渲染内容输出
     * @access protected
     * @param string $content 模板内容
     * @param array  $vars    模板输出变量
     * @param array  $replace 替换内容
     * @param array  $config  模板参数
     * @return mixed
     */
    protected function display($content = '', $vars = [], $replace = [], $config = [])
    {
        return $this->view->display($content, $vars, $replace, $config);
    }
    /**
     * 自动定位模板文件
     * @access private
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseTemplate($template)
    {
        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨模块调用
            list($module, $template) = explode('@', $template);
            $path                    = ADDON_PATH . strtolower($this->addon_name) . DS . 'view' . DS;
        } else {
            // 当前视图目录
            $path = $this->view_config['view_path'];
        }
        // 分析模板文件规则
        $controller = Loader::parseName($this->getName());
        if ($controller && 0 !== strpos($template, '/')) {
            $depr     = $this->view_config['view_depr'];
            $template = str_replace(['/', ':'], $depr, $template);
            if ('' == $template) {
                // 如果模板文件名为空 按照默认规则定位
                $template = str_replace('.', DS, $controller) . $depr . $request->action();
            } elseif (false === strpos($template, $depr)) {
                $template = str_replace('.', DS, $controller) . $depr . $template;
            }
        }
        return $path . ltrim($template, '/') . '.' . ltrim($this->view_config['view_suffix'], '.');
    }
    final public function getName(){
        $class = get_class($this);
        return strtolower(substr($class,strrpos($class, '\\')+1));
    }
    /**
     * 模板变量赋值
     * @access protected
     * @param mixed $name  要显示的模板变量
     * @param mixed $value 变量的值
     * @return void
     */
    protected function assign($name, $value = '')
    {
        $this->view->assign($name, $value);
    }

    /**
     * 初始化模板引擎
     * @access protected
     * @param array|string $engine 引擎参数
     * @return void
     */
    protected function engine($engine)
    {
        $this->view->engine($engine);
    }

    /**
     * 设置验证失败后是否抛出异常
     * @access protected
     * @param bool $fail 是否抛出异常
     * @return $this
     */
    protected function validateFailException($fail = true)
    {
        $this->failException = $fail;
        return $this;
    }

    /**
     * 验证数据
     * @access protected
     * @param array        $data     数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array        $message  提示信息
     * @param bool         $batch    是否批量验证
     * @param mixed        $callback 回调方法（闭包）
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate($data, $validate, $message = [], $batch = false, $callback = null)
    {
        if (is_array($validate)) {
            $v = Loader::validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                list($validate, $scene) = explode('.', $validate);
            }
            $v = Loader::validate($validate);
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }
        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        if (is_array($message)) {
            $v->message($message);
        }

        if ($callback && is_callable($callback)) {
            call_user_func_array($callback, [$v, &$data]);
        }

        if (!$v->check($data)) {
            if ($this->failException) {
                throw new ValidateException($v->getError());
            } else {
                return $v->getError();
            }
        } else {
            return true;
        }
    }
}