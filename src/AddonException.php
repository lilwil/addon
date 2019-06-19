<?php

    // +----------------------------------------------------------------------
    // | 插件异常
    // +----------------------------------------------------------------------
    // | Copyright (c) 2015-2019 http://www.yicmf.com, All rights reserved.
    // +----------------------------------------------------------------------
    // | Author: 微尘 <yicmf@qq.com>
    // +----------------------------------------------------------------------

    namespace app\common\addon;

    use think\Exception;

    class AddonException extends Exception
    {

        private $statusCode;
        private $headers;

        public function __construct($statusCode, $message = null, \Exception $previous = null, array $headers = [], $code = 0)
        {
            $this->statusCode = $statusCode;
            $this->headers = $headers;

            parent::__construct($message, $code, $previous);
        }

        public function getStatusCode()
        {
            return $this->statusCode;
        }

        public function getHeaders()
        {
            return $this->headers;
        }
    }
