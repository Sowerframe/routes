<?php
#coding: utf-8
# +-------------------------------------------------------------------
# | Response Dispatcher
# +-------------------------------------------------------------------
# | Copyright (c) 2017-2019 Sower rights reserved.
# +-------------------------------------------------------------------
# +-------------------------------------------------------------------
declare (strict_types = 1);
namespace sower\routes\dispatch;
use sower\routes\Dispatch;
class Response extends Dispatch
{
    public function exec()
    {
        return $this->dispatch;
    }

}
