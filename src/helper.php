<?php

    use think\facade\Config;
    use think\facade\Env;
    use think\Loader;
    use think\facade\Url;
    use think\facade\Hook;

    if (is_file(Env::get('root_path') . 'data' . DIRECTORY_SEPARATOR . 'install.lock')) {
        // 注册初始化钩子行为
        Hook::add('app_init', 'yicmf\addon\AddonInit');
    }

    /**
     * 远程调用插件控制器的操作方法.
     * @param string                 $url
     * @param array|string|bool|null $vars
     */
    function addon($url, $vars)
    {
        $info = pathinfo($url);
        $action = $info['basename'];
        $module = $info['dirname'];
        $class_name = 'addon\\' . Loader::parseName($module) . '\controller\\' . $module;
        $class = Loader::controller($class_name);
        if ( $class ) {
            return call_user_func([&$class, $action . Config::get('action_suffix')], $vars);
        } else {
            return false;
        }
    }

    /**
     * 获取插件类的类名.
     * @param        $name 插件名
     * @param string $type 返回命名空间类型
     * @return string
     */
    function get_addon_class($name, $type = 'hook')
    {
        switch ( $type ) {
            case 'controller':
                $namespace = '\\addon\\' . Loader::parseName($name) . '\\controller';
                break;
            default:
                $namespace = '\\addon\\' . Loader::parseName($name) . '\\' . Loader::parseName($name, 1);
        }

        return $namespace;
    }

    /**
     * 获取插件类的配置文件数组.
     * @param string $name 插件名
     * @return array
     */
    function get_addon_config($name)
    {
        $class = get_addon_class($name);
        if ( class_exists($class) ) {
            $addon = new $class();
            return $addon->getConfig();
        } else {
            return [];
        }
    }

    /**
     * 插件显示内容里生成访问插件的url.
     * @param string $url   url
     * @param array  $param 参数
     */
    function addon_url($url, $param = [])
    {
        $url = parse_url($url);
        $case = Config::get('url_convert');
        $addon = $case ? Loader::parseName($url['scheme']) : $url['scheme'];
        $controller = $case ? Loader::parseName($url['host']) : $url['host'];
        $action = trim($case ? strtolower($url['path']) : $url['path'], '/');
        /* 解析URL带的参数 */
        if ( isset($url['query']) ) {
            parse_str($url['query'], $query);
            $param = array_merge($query, $param);
        }
        /* 基础参数 */
        $params = [
            '_addon' => $addon,
            '_controller' => $controller,
            '_action' => $action,
        ];
        $params = array_merge($params, $param); // 添加额外参数
        return Url::build('yicmf\addon\controller\Index@execute', $params,true,true);
    }


