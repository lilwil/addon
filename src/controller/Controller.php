<?php

    // +----------------------------------------------------------------------
    // | 插件控制器
    // +----------------------------------------------------------------------
    // | Copyright (c) 2015-2019 http://www.yicmf.com, All rights reserved.
    // +----------------------------------------------------------------------
    // | Author: 微尘 <yicmf@qq.com>
    // +----------------------------------------------------------------------

    namespace yicmf\addon\controller;

    use traits\controller\Jump;
    use yicmf\addon\Addon;

    abstract class Controller
    {

        use Addon, Jump;
        // 后台专用控制器
        public $admin_controller = 'Admin';
        // 后台菜单方法
        public $lists_action = 'index';
        // 参数配置方法
        public $config_action = 'config';
        /**
         * 配置文件生成器
         * @var array
         */
        protected $config_builder = [];

        /**
         * 安装方法
         * @throws AddonException
         */
        abstract public function install();

        /**
         * 插件方法
         * @throws AddonException
         */
        abstract public function uninstall();

        /**
         * 配置信息
         * @return array
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/4/12 10:17
         */
        public function getBuilder()
        {
            return $this->config_builder;
        }
    }
