<?php
/**
 * Created by PhpStorm.
 * User: <zhufengwei@aliyun.com>
 * Date: 2019/1/30
 * Time: 17:10
 */
namespace Listen\Restapi;
use Listen\Restapi\Exceptions\RestapiException;

trait CallbackTrait
{
    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param  string  $name
     * @param \Closure $closure
     *
     * @return $this
     */
    public function pushExceptionCallback(string $name, \Closure $closure)
    {
        $this->exceptionCallbacks[$name] = \Closure::bind($closure, $this);
        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     *
     * @return $this
     * @throws \Listen\Restapi\Exceptions\RestapiException
     */
    public function popExceptionCallback(string $name)
    {
        if (!in_array($name, array_keys($this->exceptionCallbacks))) {
            throw new RestapiException('Callback does\'t Exsits !');
        }
        unset($this->exceptionCallbacks[$name]);
        return $this;
    }
    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $module
     * @param string $message
     * @param int    $code
     * @param array  $otherParams
     *
     * @return $this
     */
    public function applyCallback(string $module, string $message, int $code, array $otherParams = [])
    {
        foreach ($this->exceptionCallbacks as $callback) {
            $callback($module, $message, $code, $otherParams);
        }
        return $this;
    }
}

