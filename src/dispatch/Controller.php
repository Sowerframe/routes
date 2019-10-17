<?php declare (strict_types = 1);
#coding: utf-8
# +-------------------------------------------------------------------
# | Controller Dispatcher
# +-------------------------------------------------------------------
# | Copyright (c) 2017-2019 Sower rights reserved.
# +-------------------------------------------------------------------
# +-------------------------------------------------------------------
namespace sower\routes\dispatch;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use sower\App;
use sower\exception\ClassNotFoundException as ClassException;
use sower\exception\HttpException;
use sower\Request;
use sower\routes\Dispatch;
class Controller extends Dispatch
{
    /**
     * 控制器名
     * @var string
     */
    protected $controller;

    /**
     * 操作名
     * @var string
     */
    protected $actionName;

    public function init(App $app)
    {
        parent::init($app);

        $result = $this->dispatch;

        if (is_string($result)) {
            $result = explode('/', $result);
        }

        // 是否自动转换控制器和操作名
        $convert = is_bool($this->convert) ? $this->convert : $this->rule->config('url_convert');
        // 获取控制器名
        $controller = strip_tags($result[0] ?: $this->rule->config('default_controller'));

        $this->controller = $convert ? strtolower($controller) : $controller;

        // 获取操作名
        $this->actionName = strip_tags($result[1] ?: $this->rule->config('default_action'));

        // 设置当前请求的控制器、操作
        $this->request
            ->setController(App::parseName($this->controller, 1))
            ->setAction($this->actionName);
    }

    public function exec()
    {
        try {
            $instance = $this->controller($this->controller);
        } catch (ClassException $e) {
            throw new HttpException(404, 'controller not exists:' . $e->getClass());
        }
        $this->reflexMiddle($instance);

        $this->app->middleware->controller(function (Request $request, $next) use ($instance) {
            $action = $this->actionName . $this->rule->config('action_suffix');

            if (is_callable([$instance, $action])) {
                $vars = array_merge($this->request->param(), $this->param);

                try {
                    $reflect = new ReflectionMethod($instance, $action);
                    $actionName = $reflect->getName();
                    $this->request->setAction($actionName);
                } catch (ReflectionException $e) {
                    $reflect = new ReflectionMethod($instance, '__call');
                    $vars    = [$action, $vars];
                    $this->request->setAction($action);
                }
            } else {
                throw new HttpException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
            }

            $data = $this->app->invokeReflectMethod($instance, $reflect, $vars);

            return $this->autoResponse($data);
        });

        return $this->app->middleware->dispatch($this->request, 'controller');
    }

    /**
     * 使用反射机制注册控制器中间件
     * @access public
     * @param  object $controller 控制器实例
     * @return void
     */
    protected function reflexMiddle($controller): void
    {
        $class = new ReflectionClass($controller);

        if ($class->hasProperty('middleware')) {
            $reflectionProperty = $class->getProperty('middleware');
            $reflectionProperty->setAccessible(true);

            $middlewares = $reflectionProperty->getValue($controller);

            foreach ($middlewares as $key => $val) {
                if (!is_int($key)) {
                    if (isset($val['only']) && !in_array($this->request->action(true), array_map(function ($item) {
                        return strtolower($item);
                    }, $val['only']))) {
                        continue;
                    } elseif (isset($val['except']) && in_array($this->request->action(true), array_map(function ($item) {
                        return strtolower($item);
                    }, $val['except']))) {
                        continue;
                    } else {
                        $val = $key;
                    }
                }

                $this->app->middleware->controller($val);
            }
        }
    }

    /**
     * 实例化访问控制器
     * @access public
     * @param  string $name 资源地址
     * @return object
     * @throws ClassException
     */
    public function controller(string $name)
    {
        $suffix = $this->rule->config('controller_suffix') ? 'Controller' : '';

        $controllerLayer = $this->rule->config('controller_layer') ?: 'controller';
        $emptyController = $this->rule->config('empty_controller') ?: 'Error';

        $class = $this->app->parseClass($controllerLayer, $name . $suffix);

        if (class_exists($class)) {
            return $this->app->make($class, [], true);
        } elseif ($emptyController && class_exists($emptyClass = $this->app->parseClass($controllerLayer, $emptyController . $suffix))) {
            return $this->app->make($emptyClass, [], true);
        }

        throw new ClassException('class not exists:' . $class, $class);
    }
}
