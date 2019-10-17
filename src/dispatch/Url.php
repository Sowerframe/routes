<?php
#coding: utf-8
# +-------------------------------------------------------------------
# | Url Dispatcher
# +-------------------------------------------------------------------
# | Copyright (c) 2017-2019 Sower rights reserved.
# +-------------------------------------------------------------------
# +-------------------------------------------------------------------
declare (strict_types = 1);
namespace sower\routes\dispatch;
use sower\App;
use sower\exception\HttpException;
use sower\Request;
use sower\routes\Rule;
class Url extends Controller
{

    public function __construct(Request $request, Rule $rule, $dispatch, array $param = [], int $code = null)
    {
        $this->request = $request;
        $this->rule    = $rule;
        // 解析默认的URL规则
        $dispatch = $this->parseUrl($dispatch);

        parent::__construct($request, $rule, $dispatch, $this->param, $code);
    }

    /**
     * 解析URL地址
     * @access protected
     * @param  string $url URL
     * @return array
     */
    protected function parseUrl(string $url): array
    {
        $depr = $this->rule->config('pathinfo_depr');
        $bind = $this->rule->getRouter()->getDomainBind();

        if ($bind && preg_match('/^[a-z]/is', $bind)) {
            $bind = str_replace('/', $depr, $bind);
            // 如果有模块/控制器绑定
            $url = $bind . ('.' != substr($bind, -1) ? $depr : '') . ltrim($url, $depr);
        }

        $path = $this->rule->parseUrlPath($url);
        if (empty($path)) {
            return [null, null];
        }

        // 解析控制器
        $controller = !empty($path) ? array_shift($path) : null;

        if ($controller && !preg_match('/^[A-Za-z][\w|\.]*$/', $controller)) {
            throw new HttpException(404, 'controller not exists:' . $controller);
        }

        // 解析操作
        $action = !empty($path) ? array_shift($path) : null;
        $var    = [];

        // 解析额外参数
        if ($path) {
            preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                $var[$match[1]] = strip_tags($match[2]);
            }, implode('|', $path));
        }

        $panDomain = $this->request->panDomain();
        if ($panDomain && $key = array_search('*', $var)) {
            // 泛域名赋值
            $var[$key] = $panDomain;
        }

        // 设置当前请求的参数
        $this->param = $var;

        // 封装路由
        $routes = [$controller, $action];

        if ($this->hasDefinedRoute($routes)) {
            throw new HttpException(404, 'invalid request:' . str_replace('|', $depr, $url));
        }

        return $routes;
    }

    /**
     * 检查URL是否已经定义过路由
     * @access protected
     * @param  array $routes 路由信息
     * @return bool
     */
    protected function hasDefinedRoute(array $routes): bool
    {
        list($controller, $action) = $routes;

        // 检查地址是否被定义过路由
        $name = strtolower(App::parseName($controller, 1) . '/' . $action);

        $host   = $this->request->host(true);
        $method = $this->request->method();

        if ($this->rule->getRouter()->getName($name, $host, $method)) {
            return true;
        }

        return false;
    }

}
