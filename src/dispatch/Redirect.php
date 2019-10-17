<?php
#coding: utf-8
# +-------------------------------------------------------------------
# | Redirect Dispatcher
# +-------------------------------------------------------------------
# | Copyright (c) 2017-2019 Sower rights reserved.
# +-------------------------------------------------------------------
# +-------------------------------------------------------------------
declare (strict_types = 1);
namespace sower\routes\dispatch;
use sower\Response;
use sower\routes\Dispatch;
class Redirect extends Dispatch
{
    public function exec()
    {
        return Response::create($this->dispatch, 'redirect')->code($this->code);
    }
}
