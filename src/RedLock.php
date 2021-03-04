<?php

declare(strict_types=1);

namespace RedlockHyperf;

use Hyperf\Redis\Pool\PoolFactory;
use RedlockHyperf\Exception\RedLockException;

class RedLock
{
    /**
     * @var int / microsecond
     */
    private $retryDelay = 200;

    private $retryCount = 2;

    private $clockDriftFactor = 0.01;

    private $instances = [];

    private $quorum;

    /**
     * @var PoolFactory
     */
    private $poolFactory;

    public function __construct(PoolFactory $poolFactory)
    {
        $this->poolFactory = $poolFactory;
    }

    /**
     * 根据Redis链接池配置名称数组生成独立Redis实例.
     * @param array $poolName
     * @return $this
     */
    public function setRedisPoolName(array $poolName = ['default']): RedLock
    {
        if (! empty($poolName)) {
            $this->instances = [];
            foreach ($poolName as $row) {
                if (!isset($this->instances[$row])) {
                    try {
                        $this->instances[$row] = $this->poolFactory->getPool($row)->get();
                    } catch (\Throwable $exception) {
                        throw new RedLockException($exception->getMessage(), 0, $exception);
                    }
                }
            }
            $this->quorum = min(count($this->instances), (count($this->instances) / 2 + 1));
        }

        return $this;
    }

    /**
     * @param int $retryDelay / microsecond
     * @return $this
     */
    public function setRetryDelay(int $retryDelay = 200): RedLock
    {
        $this->retryDelay = $retryDelay;
        return $this;
    }

    public function setRetryCount(int $retryCount = 2): RedLock
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    /**
     * @param $resource
     * @param $ttl / millisecond
     * @return array|false
     */
    public function lock($resource, int $ttl)
    {
        $token = uniqid(gethostname(), true);
        $retry = $this->retryCount;

        do {
            $n = 0;

            $startTime = microtime(true) * 1000;

            foreach ($this->instances as $instance) {
                if ($this->lockInstance($instance, $resource, $token, $ttl)) {
                    ++$n;
                }
            }

            # Add 2 milliseconds to the drift to account for Redis expires
            # precision, which is 1 millisecond, plus 1 millisecond min drift
            # for small TTLs.
            $drift = ($ttl * $this->clockDriftFactor) + 2;

            $validityTime = $ttl - (microtime(true) * 1000 - $startTime) - $drift;

            if ($n >= $this->quorum && $validityTime > 0) {
                return [
                    'validity' => $validityTime,
                    'resource' => $resource,
                    'token' => $token,
                ];
            }

            //get lock failure unlock all instance
            foreach ($this->instances as $instance) {
                $this->unlockInstance($instance, $resource, $token);
            }

            // Wait a random delay before to retry
            $delay = mt_rand(floor($this->retryDelay / 2), $this->retryDelay);

            --$retry;
        } while ($retry > 0 && \Swoole\Coroutine::sleep($delay/1000));

        return false;
    }

    /**
     * @param array $lock
     */
    public function unlock(array $lock)
    {
        $resource = $lock['resource'];
        $token = $lock['token'];

        foreach ($this->instances as $instance) {
            $this->unlockInstance($instance, $resource, $token);
        }
    }

    private function lockInstance($instance, $resource, $token, $ttl)
    {
        try {
            return $instance->set($resource, $token, ['NX', 'PX' => $ttl]);
        } catch (\Throwable $exception) {
            throw new RedLockException($exception->getMessage(), 0, $exception);
        }
    }

    private function unlockInstance($instance, $resource, $token)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';

        try {
            return $instance->eval($script, [$resource, $token], 1);
        } catch (\Throwable $exception) {
            throw new RedLockException($exception->getMessage(), 0, $exception);
        }
    }
}
