<?php

// +----------------------------------------------------------------------
// | 插件验证器
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2021 http://www.yicmf.com, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 微尘 <yicmf@qq.com>
// +----------------------------------------------------------------------

	namespace yicmf\addon\validate;

	use think\Validate;

	class Addon extends Validate
	{

		protected $rule = [
			'identifier|插件唯一标识' => 'require|unique:addon',
			'name|插件名称' => 'require|max:39|unique:hook|alphaDash',
			'title|插件标题' => 'require|length:1,80', // 一个汉字的长度utf8长度在2~4个
			'description|插件描述' => 'require|max:255'
		];

		protected $message = [];

		protected $scene = [];
	}
