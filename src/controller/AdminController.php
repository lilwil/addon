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
    use app\ucenter\event\User;
    use app\common\traits\controller\Bjui;
    use yicmf\addon\Addon;

    class AdminController
    {

        use Addon, Jump, Bjui;

        /**
         * 初始化
         * @throws \Exception
         * @author  : 微尘 <yicmf@qq.com>
         * @datetime: 2019/5/15 16:13
         */
        protected function initialize()
        {
            if ( !User::isLogin() ) {
                return $this->ajax(300, '当前用户未登录');
            }
        }
    }
