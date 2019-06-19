<?php

    namespace think\addon\controller;

    use think\Container;
    use think\facade\Lang;
    use think\Loader;

    /**
     * 插件执行默认控制器.
     */
    class Index
    {
        /**
         * 当前插件名
         * @var string
         */
        protected $addon;

        protected $controller;

        protected $action;

        /**
         * 架构函数
         */
        public function __construct()
        {
            $app = Container::get('app');
            $request = $app['request'];
            $this->addon = Loader::parseName($request->param('_addon/s', ''), 1);
            $this->controller = Loader::parseName($request->param('_controller/s', ''), 1);
            $this->action = $request->param('_action/s', '');
            if (!$this->action) {
                $dispatch = $request->dispatch();
                if (isset($dispatch['var']) && isset($dispatch['var']['_addon']) && isset($dispatch['var']['_controller']) && isset($dispatch['var']['_action'])) {
                    $this->addon = $dispatch['var']['_addon'];
                    $this->controller = $dispatch['var']['_controller'];
                    $this->action = $dispatch['var']['_action'];
                }
            }
        }

        /**
         * 插件执行.
         */
        public function index()
        {
            if (!empty($this->addon) && !empty($this->controller) && !empty($this->action)) {
                // 获取类的命名空间
                $class = get_addon_class($this->addon, 'controller') . "\\{$this->controller}";
                $ob_class = new $class();
                if ($ob_class === false) {
                    return \think\facade\Lang::get('addon init fail');
                }
                if (method_exists($ob_class, $this->action)) {
                    // 调用操作
                    return call_user_func([
                        $ob_class,
                        $this->action,
                    ]);
                } else {
                    return '插件不完整';
                }
            }
            return '插件信息不完整';
        }

    }
