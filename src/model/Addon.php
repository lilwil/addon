<?php

    // +----------------------------------------------------------------------
    // | 插件模型
    // +----------------------------------------------------------------------
    // | Copyright (c) 2015-2022 http://www.yicmf.com, All rights reserved.
    // +----------------------------------------------------------------------
    // | Author: 微尘 <yicmf@qq.com>
    // +----------------------------------------------------------------------

    namespace yicmf\addon\model;

    use app\common\model\Common;

    class Addon extends Common
    {
        public $status_text = [
            -1 => '禁用',
            0 => '未安装',
            1 => '已安装',
            2 => '已安装'
        ];

        // 获取器
        public function getStatusTextAttr($value, $data)
        {
            return $this->status_text[$data['status']];
        }

        public function getStatusAttr($value, $data)
        {
            switch ($value) {
                case 1:
                    if (isset($data['config']) && count($data['config']) > 0) {
                        return 2;
                    } else {
                        return 1;
                    }
                    break;
                default:
                    return $value;
                    break;
            }
        }

        // 类型转换
        protected $type = [
            'config' => 'array',
            'need_plugin' => 'array',
            'need_yicmf' => 'array',
            'need_module' => 'array',
        ];

        protected $insert = [
            'status' => 0
        ];
    }
