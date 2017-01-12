<?php

namespace think;

abstract class UpgradeBase
{
    // 错误信息
    protected $error;

    // 实现升级代码
    abstract public function upgrade();

    // 实现升级异常的回滚代码
    abstract public function rollback();

    /**
     * 返回错误信息.
     *
     * @return type
     */
    public function getError()
    {
        return $this->error;
    }
}
