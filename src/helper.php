<?php
use think\Hook;
use think\Config;
use think\Route;
use think\Url;
use think\Loader;
// 插件目录
define('ADDON_PATH', ROOT_PATH . 'addons' . DS);
define('ADDON_STATIC', ROOT_PATH . 'public' . DS . 'addons' . DS);

// 定义路由
Route::rule('addon/execute', "\\think\\addon\\controller\\Index@execute");
// 如果插件目录不存在则创建
if (! is_dir(ADDON_PATH)) {
    mkdir(ADDON_PATH, 0777, true);
}
// 注册类的根命名空间
Loader::addNamespace('addons', ADDON_PATH);
// 注册初始化钩子行为
Hook::add('app_init', 'think\addon\AppInit');

/**
 * 获取插件类的类名
 * @param $name 插件名            
 * @param string $type 返回命名空间类型
 * @return string
 */
function get_addon_class($name, $type = 'hook')
{
    switch ($type) {
        case 'controller':
            $namespace = "\\addons\\" . Loader::parseName($name) . "\\controller";
            break;
        default:
            $namespace = "\\addons\\" . Loader::parseName($name) . "\\" . Loader::parseName($name,1);
    }
    return $namespace;
}

/**
 * 获取插件类的配置文件数组
 * @param string $name  插件名
 * @return array
 */
function get_addon_config($name)
{
    $class = get_addon_class($name);
    if (class_exists($class)) {
        $addon = new $class();
        return $addon->getConfig();
    } else {
        return [];
    }
}

/**
 * 插件显示内容里生成访问插件的url
 * @param string $url url
 * @param array $param 参数
 */
function addons_url($url, $param = [])
{
    $url = parse_url($url);
    $case = Config::get('url_convert');
    $addon = $case ? Loader::parseName($url['scheme']) : $url['scheme'];
    $controller = $case ? Loader::parseName($url['host']) : $url['host'];
    $action = trim($case ? strtolower($url['path']) : $url['path'], '/');
    /* 解析URL带的参数 */
    if (isset($url['query'])) {
        parse_str($url['query'], $query);
        $param = array_merge($query, $param);
    }
    /* 基础参数 */
    $params = [
        '_addon' => $addon,
        '_controller' => $controller,
        '_action' => $action
    ];
    $params = array_merge($params, $param); // 添加额外参数
    return Url::build('/addon/execute', $params);
}
