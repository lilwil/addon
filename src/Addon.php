<?php

    // +----------------------------------------------------------------------
    // | 插件
    // +----------------------------------------------------------------------
    // | Copyright (c) 2015-2019 http://www.yicmf.com, All rights reserved.
    // +----------------------------------------------------------------------
    // | Author: 微尘 <yicmf@qq.com>
    // +----------------------------------------------------------------------

    namespace yicmf;

    use think\facade\Env;
    use think\facade\App;
    use think\Container;
    use think\facade\Config;
    use think\exception\ValidateException;
    use think\Loader;

    trait Addon
    {
        /**
         * 视图类实例
         * @var \think\View
         */
        protected $view;

        /**
         * Request实例
         * @var \think\Request
         */
        protected $request;

        /**
         * 验证失败是否抛出异常
         * @var bool
         */
        protected $failException = false;

        /**
         * 是否批量验证
         * @var bool
         */
        protected $batchValidate = false;

        /**
         * 前置操作方法列表（即将废弃）
         * @var array $beforeActionList
         */
        protected $beforeActionList = [];

        /**
         * 控制器中间件
         * @var array
         */
        protected $middleware = [];
        /**
         * 当前插件的配置文件位置
         * @var string
         */
        protected $config_file;
        /**
         * 当前插件的配置文件位置
         * @var string
         */
        protected $info_file;
        /**
         * 当前执行的插件名字
         * @var string
         */
        protected $addon_name;
        /**
         * 当前插件路径
         * @var string
         */
        protected $addon_path = '';
        /**
         * 当前插件路径
         * @var string
         */
        protected $tpl_path = '';
        /**
         * 当前插件后台列表文件
         * @var string
         */
        public $adminlist_file = '';

        /**
         * 架构函数
         */
        public function __construct()
        {
            $app = Container::get('app');
            $this->request = $app['request'];
            $this->view = $app['view'];
            $this->addon_name = $this->getName();
            // 获取当前插件目录
            $this->addon_path = Env::get('addon_path') . $this->addon_name . DIRECTORY_SEPARATOR;
            $this->tpl_path = Env::get('root_path') . 'application' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'addon' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR;
            // 当前插件配置文件
            $this->config_file = $this->addon_path . 'data' . DIRECTORY_SEPARATOR . 'config' . App::getConfigExt();
            // 当前插件的文件
            $this->info_file = $this->addon_path . 'data' . DIRECTORY_SEPARATOR . 'info' . App::getConfigExt();
            // 控制器初始化
            $this->initialize();
            // 前置操作方法
            if ( $this->beforeActionList ) {
                foreach ( $this->beforeActionList as $method => $options ) {
                    is_numeric($method) ?
                        $this->beforeAction($options) :
                        $this->beforeAction($method, $options);
                }
            }
        }

        protected function initialize()
        {
        }

        /**
         * 前置操作.
         * @param string $method  前置操作方法名
         * @param array  $options 调用参数 ['only'=>[...]] 或者['except'=>[...]]
         */
        protected function beforeAction($method, $options = [])
        {
            if ( isset($options['only']) ) {
                if ( is_string($options['only']) ) {
                    $options['only'] = explode(',', $options['only']);
                }
                if ( !in_array($this->request->action(), $options['only']) ) {
                    return;
                }
            } elseif ( isset($options['except']) ) {
                if ( is_string($options['except']) ) {
                    $options['except'] = explode(',', $options['except']);
                }
                if ( in_array($this->request->action(), $options['except']) ) {
                    return;
                }
            }
            call_user_func([$this, $method]);
        }

        /**
         * 加载模板输出.
         * @param string $template 模板文件名
         * @param array  $vars     模板输出变量
         * @param array  $config   模板参数
         * @param bool   $renderContent
         * @return mixed|string
         * @throws \Exception
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/5/4 9:43
         */
        protected function fetch($template = '', $vars = [], $config = [], $renderContent = false)
        {
            $template = $this->addon_path . 'view' . DIRECTORY_SEPARATOR . $template . '.' . Config::get('template.view_suffix');
            $content = $this->view->fetch($template, $vars, $config, $renderContent);
            // 模板过滤输出
            $replace = array_merge($this->getDefaultReplaceString(), (array)$this->getConfig('tpl_replace_string'));
            $content = empty($replace) ? $content : str_replace(array_keys($replace), array_values($replace), $content);
            return $content;
        }

        protected function getDefaultReplaceString()
        {
            return [
                '__ADDON_CSS__' => '/static/addon/' . $this->addon_name . '/css',
                '__ADDON_IMG__' => '/static/addon/' . $this->addon_name . '/images',
                '__ADDON_JS__' => '/static/addon/' . $this->addon_name . '/js',
            ];
        }

        /**
         * 渲染内容输出
         * @param string $content 模板内容
         * @param array  $vars    模板输出变量
         * @param array  $replace 替换内容
         * @param array  $config  模板参数
         * @return mixed
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/5/4 9:44
         */
        protected function display($content = '', $vars = [], $replace = [], $config = [])
        {
            return $this->view->display($content, $vars, $replace, $config);
        }

        /**
         * 模板变量赋值
         * @param mixed $name  要显示的模板变量
         * @param mixed $value 变量的值
         * @return void
         */
        protected function assign($name, $value = '')
        {
            $this->view->assign($name, $value);
        }

        /**
         * 初始化模板引擎.
         * @param array|string $engine 引擎参数
         * @return void
         */
        protected function engine($engine)
        {
            $this->view->engine($engine);
        }

        /**
         * 设置验证失败后是否抛出异常
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
         * @param  array        $data     数据
         * @param  string|array $validate 验证器名或者验证规则数组
         * @param  array        $message  提示信息
         * @param  bool         $batch    是否批量验证
         * @param  mixed        $callback 回调方法（闭包）
         * @return array|string|true
         * @throws ValidateException
         */
        protected function validate($data, $validate, $message = [], $batch = false, $callback = null)
        {
            if ( is_array($validate) ) {
                $v = $this->app->validate();
                $v->rule($validate);
            } else {
                if ( strpos($validate, '.') ) {
                    // 支持场景
                    list($validate, $scene) = explode('.', $validate);
                }
                $v = $this->app->validate($validate);
                if ( !empty($scene) ) {
                    $v->scene($scene);
                }
            }

            // 是否批量验证
            if ( $batch || $this->batchValidate ) {
                $v->batch(true);
            }

            if ( is_array($message) ) {
                $v->message($message);
            }

            if ( $callback && is_callable($callback) ) {
                call_user_func_array($callback, [$v, &$data]);
            }

            if ( !$v->check($data) ) {
                if ( $this->failException ) {
                    throw new ValidateException($v->getError());
                }
                return $v->getError();
            }

            return true;
        }

        /**
         * 获取插件的配置
         * @param string|null $name 读取参数名
         * @return array|mixed|null
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/4/12 15:12
         */
        final public function getConfig($name = '')
        {
            static $config;
            if ( empty($config) ) {
                if ( is_file($this->config_file) ) {
                    $config = include $this->config_file;
                } else {
                    $config = [];
                }
            }
            if ( $name ) {
                return isset($config[$name]) ? $config[$name] : null;
            } else {
                return $config;
            }
        }

        /**
         * 更新插件的配置数组
         * @param array $update
         * @return mixed
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/4/12 15:06
         */
        final public function setConfig($update)
        {
            try {
                $config = $this->getConfig();
                if ( is_array($config) && is_array($update) ) {
                    $config = array_merge($config, $update);
                } elseif ( !$config && $update ) {
                    $config = $update;
                }
                // 配置文件模版
                $config_tpl = file_get_contents($this->tpl_path . 'config.tpl');
                $value_tpl = '';
                foreach ( $config as $name => $value ) {
                    $value_tpl .= '//注释
            ';
                    if ( is_array($value) ) {
                        $temp = '[';
                        foreach ( $value as $key2 => $value2 ) {
                            if ( count($value) == ($key2 + 1) ) {
                                $temp .= '\'' . $value2 . '\'';
                            } else {
                                $temp .= '\'' . $value2 . '\',';
                            }
                        }
                        $temp .= ']';
                        $value_tpl .= '\'' . $name . '\' => \'' . $temp . '\',
                ';
                    } else {
                        $value_tpl .= '\'' . $name . '\' => \'' . $value . '\',
                ';
                    }
                }
                $config_tpl = str_replace('{$value}', $value_tpl, $config_tpl);
                $config_tpl = str_replace('{$addon_title}', $this->getInfo('title'), $config_tpl);
                $config_tpl = str_replace('{$addon_author}', $this->getInfo('author'), $config_tpl);
                $config_tpl = str_replace('{$addon_website}', $this->getInfo('author_website'), $config_tpl);
                $config_tpl = str_replace('{$year}', time_format(time(), 'Y'), $config_tpl);
                file_put_contents($this->config_file, $config_tpl);
                // 返回订单数据
                $data['code'] = 0;
                $data['message'] = '更新成功';
            } catch ( \Exception $e ) {
                $data['code'] = 1;
                $data['message'] = $e->getMessage();
            }
            return $data;
        }

        /**
         * 获取当前所属插件名
         * @param int  $type    转换类型
         * @param bool $ucfirst 首字母是否大写（驼峰规则）
         * @return string
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/5/4 9:45
         */
        final protected function getName($type = 0, $ucfirst = true)
        {
            if ( $this->request->has('_addon/s') ) {
                return Loader::parseName($this->request->param('_addon/s', ''), $type, $ucfirst);
            } else {
                return Loader::parseName(explode('\\', get_class($this))[1], $type, $ucfirst);
            }
        }

        final public function getInfo($name = '')
        {
            if ( is_file($this->info_file) ) {
                $info = include $this->info_file;
            } else {
                $info = [];
            }
            return empty($name) ? $info : (isset($info[$name]) ? $info[$name] : '');
        }
    }
