<?php

namespace think\addon;

use think\App;
use think\Cache;
use think\Db;
use think\Hook;

// 初始化钩子信息
class AppInit
{
    public function run()
    {
        // 获取系统配置
        $data = (App::$debug || !Cache::has('addons_hooks')) ? [] : Cache::get('addons_hooks');
        if (empty($data)) {
            $addons = [];
            $hooks = Db::name('hooks')->order('id')->column('addons', 'name');
            foreach ($hooks as $key => $value) {
                if ($value) {
                    $names = explode(',', $value);
                    $data = Db::name('addons')->where('status', 1)
                        ->where('name', 'in', $names)
                        ->column('name', 'id');
                    if ($data) {
                        $addon = array_intersect($names, $data);
                        $addons[$key] = array_map('get_addon_class', $addon);
                        Hook::add($key, $addons[$key]);
                    }
                }
            }
            !App::$debug && !empty($addons) && Cache::set('addons_hooks', $addons);
        } else {
            Hook::import($data, false);
        }
    }
}
