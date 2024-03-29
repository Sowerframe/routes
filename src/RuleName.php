<?php
#coding: utf-8
# +-------------------------------------------------------------------
# | 路由标识管理类
# +-------------------------------------------------------------------
# | Copyright (c) 2017-2019 Sower rights reserved.
# +-------------------------------------------------------------------
# +-------------------------------------------------------------------
declare (strict_types = 1);
namespace sower\routes;
use Closure;
class RuleName
{
    /**
     * 路由标识
     * @var array
     */
    protected $item = [];

    /**
     * 路由规则
     * @var array
     */
    protected $rule = [];

    /**
     * 路由分组
     * @var array
     */
    protected $group = [];

    /**
     * 注册路由标识
     * @access public
     * @param  string   $name  路由标识
     * @param  RuleItem $ruleItem 路由规则
     * @param  bool     $first 是否优先
     * @return void
     */
    public function setName(string $name, RuleItem $ruleItem, bool $first = false): void
    {
        $name = strtolower($name);
        if ($first && isset($this->item[$name])) {
            array_unshift($this->item[$name], $ruleItem);
        } else {
            $this->item[$name][] = $ruleItem;
        }
    }

    /**
     * 注册路由分组标识
     * @access public
     * @param  string    $name  路由分组标识
     * @param  RuleGroup $group 路由分组
     * @return void
     */
    public function setGroup(string $name, RuleGroup $group): void
    {
        $this->group[strtolower($name)] = $group;
    }

    /**
     * 注册路由规则
     * @access public
     * @param  string   $rule  路由规则
     * @param  RuleItem $ruleItem 路由
     * @return void
     */
    public function setRule(string $rule, RuleItem $ruleItem): void
    {
        $routes = $ruleItem->getRoute();

        if ($routes instanceof Closure) {
            $this->rule[$rule][] = $ruleItem;
        } else {
            $this->rule[$rule][$ruleItem->getRoute()] = $ruleItem;
        }
    }

    /**
     * 根据路由规则获取路由对象（列表）
     * @access public
     * @param  string $rule   路由标识
     * @return RuleItem[]
     */
    public function getRule(string $rule): array
    {
        return $this->rule[$rule] ?? [];
    }

    /**
     * 根据路由分组标识获取分组
     * @access public
     * @param  string $name 路由分组标识
     * @return RuleGroup|null
     */
    public function getGroup(string $name)
    {
        return $this->group[strtolower($name)] ?? null;
    }

    /**
     * 清空路由规则
     * @access public
     * @return void
     */
    public function clear(): void
    {
        $this->item = [];
        $this->rule = [];
    }

    /**
     * 获取全部路由列表
     * @access public
     * @return array
     */
    public function getRuleList(): array
    {
        $list = [];

        foreach ($this->rule as $rule => $rules) {
            foreach ($rules as $item) {
                $val = [];

                foreach (['method', 'rule', 'name', 'routes', 'domain', 'pattern', 'option'] as $param) {
                    $call        = 'get' . $param;
                    $val[$param] = $item->$call();
                }

                if ($item->isMiss()) {
                    $val['rule'] .= '<MISS>';
                }

                $list[] = $val;
            }
        }

        return $list;
    }

    /**
     * 导入路由标识
     * @access public
     * @param  array $item 路由标识
     * @return void
     */
    public function import(array $item): void
    {
        $this->item = $item;
    }

    /**
     * 根据路由标识获取路由信息（用于URL生成）
     * @access public
     * @param  string $name   路由标识
     * @param  string $domain 域名
     * @param  string $method 请求类型
     * @return array
     */
    public function getName(string $name = null, string $domain = null, string $method = '*'): array
    {
        if (is_null($name)) {
            return $this->item;
        }

        $name   = strtolower($name);
        $method = strtolower($method);
        $result = [];

        if (isset($this->item[$name])) {
            if (is_null($domain)) {
                $result = $this->item[$name];
            } else {
                foreach ($this->item[$name] as $item) {
                    $itemDomain = $item->getDomain();
                    $itemMethod = $item->getMethod();

                    if (($itemDomain == $domain || '-' == $itemDomain) && ('*' == $itemMethod || '*' == $method || $method == $itemMethod)) {
                        $result[] = $item;
                    }
                }
            }
        }

        return $result;
    }

}
