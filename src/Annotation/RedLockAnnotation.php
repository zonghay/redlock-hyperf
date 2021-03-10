<?php

namespace RedlockHyperf\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class RedLockAnnotation extends AbstractAnnotation
{
    /**
     * @var array
     */
    public $poolName = ['default'];

    /**
     * @var int / microsecond
     */
    public $retryDelay = 200;

    /**
     * @var int
     */
    public $retryCount = 2;

    /**
     * @var float
     */
    public $clockDriftFactor = 0.01;

    /**
     * @var int / millisecond
     */
    public $ttl = 60000;

    /**
     * @var string
     */
    public $resource;
}