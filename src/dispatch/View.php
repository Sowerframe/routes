<?php
#coding: utf-8
# +-------------------------------------------------------------------
# | View Dispatcher
# +-------------------------------------------------------------------
# | Copyright (c) 2017-2019 Sower rights reserved.
# +-------------------------------------------------------------------
# +-------------------------------------------------------------------
declare (strict_types = 1);
namespace sower\routes\dispatch;
use sower\Response;
use sower\routes\Dispatch;
class View extends Dispatch
{
    public function exec()
    {
        // 渲染模板输出
        return Response::create($this->dispatch, 'view')->assign($this->param);
    }
}
