<?php

namespace think\addon\controller;

\think\Loader::import('controller/Jump', TRAIT_PATH, EXT);

use think\addon\traits\controller\Base;
use think\Config;
use think\exception\ValidateException;
use think\View;
use traits\controller\Jump;

class Controller
{
    use Jump;
    use Base;

    /**
     * @var \think\View 视图类实例
     */
    protected static $view_instance;
    protected $view;
    // 验证失败是否抛出异常
    protected $failException = false;
    // 是否批量验证
    protected $batchValidate = false;

    /**
     * 前置操作方法列表.
     *
     * @var array
     */
    protected $beforeActionList = [];

    /**
     * 架构函数.
     *
     * @param Request $request Request对象
     */
    public function __construct()
    {
        if (is_null(self::$view_instance)) {
            self::$view_instance = new View(Config::get('template'), Config::get('view_replace_str'));
        }
        $this->view = self::$view_instance;
        //Base初始化
        $this->_baseInit();
        //编译模版之前替换
        $this->view->config('tpl_replace_string', $this->tplReplaceString());
        // 视图模型配置
        $this->view->config('view_path', $this->addon_path.'view'.DS);
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

    protected function _initialize()
    {
    }

    /**
     * 前置操作.
     *
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
     * 加载模板输出.
     *
     * @param string $template 模板文件名
     * @param array  $vars     模板输出变量
     * @param array  $replace  模板替换
     * @param array  $config   模板参数
     *
     * @return mixed
     */
    protected function fetch($template = '', $vars = [], $replace = [], $config = [])
    {
        return $this->view->fetch($template, $vars, $replace, $config);
    }

    /**
     * 渲染内容输出.
     *
     * @param string $content 模板内容
     * @param array  $vars    模板输出变量
     * @param array  $replace 替换内容
     * @param array  $config  模板参数
     *
     * @return mixed
     */
    protected function display($content = '', $vars = [], $replace = [], $config = [])
    {
        return $this->view->display($content, $vars, $replace, $config);
    }

    /**
     * 模板变量赋值
     *
     * @param mixed $name  要显示的模板变量
     * @param mixed $value 变量的值
     *
     * @return void
     */
    protected function assign($name, $value = '')
    {
        $this->view->assign($name, $value);
    }

    /**
     * 初始化模板引擎.
     *
     * @param array|string $engine 引擎参数
     *
     * @return void
     */
    protected function engine($engine)
    {
        $this->view->engine($engine);
    }

    /**
     * 设置验证失败后是否抛出异常.
     *
     * @param bool $fail 是否抛出异常
     *
     * @return $this
     */
    protected function validateFailException($fail = true)
    {
        $this->failException = $fail;

        return $this;
    }

    /**
     * 验证数据.
     *
     * @param array        $data     数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array        $message  提示信息
     * @param bool         $batch    是否批量验证
     * @param mixed        $callback 回调方法（闭包）
     *
     * @throws ValidateException
     *
     * @return array|string|true
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
