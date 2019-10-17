<?php declare (strict_types = 1);
#coding: utf-8
# +-------------------------------------------------------------------
# | 路由调度基础类
# +-------------------------------------------------------------------
# | Copyright (c) 2017-2019 Sower rights reserved.
# +-------------------------------------------------------------------
# +-------------------------------------------------------------------
namespace sower\routes;
use sower\App;
use sower\Container;
use sower\Request;
use sower\Response;
use sower\Validate;
abstract class Dispatch
{
    /**
     * 应用对象
     * @var \sower\App
     */
    protected $app;

    /**
     * 请求对象
     * @var Request
     */
    protected $request;

    /**
     * 路由规则
     * @var Rule
     */
    protected $rule;

    /**
     * 调度信息
     * @var mixed
     */
    protected $dispatch;

    /**
     * 路由变量
     * @var array
     */
    protected $param;

    /**
     * 状态码
     * @var int
     */
    protected $code;

    /**
     * 是否进行大小写转换
     * @var bool
     */
    protected $convert;

    public function __construct(Request $request, Rule $rule, $dispatch, array $param = [], int $code = null)
    {
        $this->request  = $request;
        $this->rule     = $rule;
        $this->dispatch = $dispatch;
        $this->param    = $param;
        $this->code     = $code;

        if (isset($param['convert'])) {
            $this->convert = $param['convert'];
        }
    }

    public function init(App $app)
    {
        $this->app = $app;

        // 记录当前请求的路由规则
        $this->request->setRule($this->rule);

        // 记录路由变量
        $this->request->setRoute($this->param);

        // 执行路由后置操作
        $this->doRouteAfter();
    }

    /**
     * 执行路由调度
     * @access public
     * @return mixed
     */
    public function run(): Response
    {
        $option = $this->rule->getOption();

        // 数据自动验证
        if (isset($option['validate'])) {
            $this->autoValidate($option['validate']);
        }

        $data = $this->exec();

        return $this->autoResponse($data);
    }

    protected function autoResponse($data): Response
    {
        if ($data instanceof Response) {
            $response = $data;
        } elseif (!is_null($data)) {
            // 默认自动识别响应输出类型
            $type     = $this->request->isJson() ? 'json' : 'html';
            $response = Response::create($data, $type);
        } else {
            $data = ob_get_clean();

            $content  = false === $data ? '' : $data;
            $status   = '' === $content && $this->request->isJson() ? 204 : 200;
            $response = Response::create($content, '', $status);
        }

        return $response;
    }

    /**
     * 检查路由后置操作
     * @access protected
     * @return void
     */
    protected function doRouteAfter(): void
    {
        // 记录匹配的路由信息
        $option = $this->rule->getOption();

        // 添加中间件
        if (!empty($option['middleware'])) {
            $this->app->middleware->import($option['middleware']);
        }

        // 绑定模型数据
        if (!empty($option['model'])) {
            $this->createBindModel($option['model'], $this->request->routes());
        }

        if (!empty($option['append'])) {
            $this->request->setRoute($option['append']);
        }
    }

    /**
     * 路由绑定模型实例
     * @access protected
     * @param array $bindModel 绑定模型
     * @param array $matches   路由变量
     * @return void
     */
    protected function createBindModel(array $bindModel, array $matches): void
    {
        foreach ($bindModel as $key => $val) {
            if ($val instanceof \Closure) {
                $result = $this->app->invokeFunction($val, $matches);
            } else {
                $fields = explode('&', $key);

                if (is_array($val)) {
                    list($model, $exception) = $val;
                } else {
                    $model     = $val;
                    $exception = true;
                }

                $where = [];
                $match = true;

                foreach ($fields as $field) {
                    if (!isset($matches[$field])) {
                        $match = false;
                        break;
                    } else {
                        $where[] = [$field, '=', $matches[$field]];
                    }
                }

                if ($match) {
                    $result = $model::where($where)->failException($exception)->find();
                }
            }

            if (!empty($result)) {
                // 注入容器
                $this->app->instance(get_class($result), $result);
            }
        }
    }

    /**
     * 验证数据
     * @access protected
     * @param array $option
     * @return void
     * @throws \sower\exception\ValidateException
     */
    protected function autoValidate(array $option): void
    {
        list($validate, $scene, $message, $batch) = $option;

        if (is_array($validate)) {
            // 指定验证规则
            $v = new Validate();
            $v->rule($validate);
        } else {
            // 调用验证器
            /** @var Validate $class */
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);

            $v = new $class();

            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message)->batch($batch)->failException(true)->check($this->request->param());
    }

    public function convert(bool $convert)
    {
        $this->convert = $convert;

        return $this;
    }

    public function getDispatch()
    {
        return $this->dispatch;
    }

    public function getParam(): array
    {
        return $this->param;
    }

    abstract public function exec();

    public function __sleep()
    {
        return ['rule', 'dispatch', 'convert', 'param', 'code', 'controller', 'actionName'];
    }

    public function __wakeup()
    {
        $this->app     = Container::pull('app');
        $this->request = $this->app->request;
    }

    public function __debugInfo()
    {
        return [
            'dispatch' => $this->dispatch,
            'param'    => $this->param,
            'code'     => $this->code,
            'rule'     => $this->rule,
        ];
    }
}
