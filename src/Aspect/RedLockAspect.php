<?php

namespace RedlockHyperf\Aspect;

use Hyperf\Di\Annotation\Aspect;
use RedlockHyperf\Annotation\AnnotationManager;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use RedlockHyperf\Annotation\RedLockAnnotation;
use RedlockHyperf\Exception\RedLockException;
use RedlockHyperf\RedLock;

/**
 * @Aspect
 */
class RedLockAspect extends AbstractAspect
{
    public $annotations = [
        RedLockAnnotation::class
    ];

    /**
     * @var AnnotationManager
     */
    protected $annotationManager;

    /**
     * @var RedLock
     */
    private $redlock;

    public function __construct(AnnotationManager $annotationManager, RedLock $redlock)
    {
        $this->annotationManager = $annotationManager;
        $this->redlock = $redlock;
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $className = $proceedingJoinPoint->className;
        $method = $proceedingJoinPoint->methodName;
        $arguments = $proceedingJoinPoint->arguments['keys'];

        /**
         * @var $annotationArguments RedLockAnnotation
         */
        $annotationArguments = $this->annotationManager->getAnnotation(RedLockAnnotation::class, $className, $method);

        if (empty($annotationArguments->resource)) {
            throw new RedLockException('RedLock Annotation lacks resource argument');
        }

        if (!is_array($annotationArguments->poolName)) {
            throw new RedLockException('PoolName Argument must be array');
        }

        $lock = $this->redlock->setRedisPoolName($annotationArguments->poolName)->setRetryCount($annotationArguments->retryCount)->setClockDriftFactor($annotationArguments->clockDriftFactor)->setRetryDelay($annotationArguments->retryDelay)->lock($annotationArguments->resource, $annotationArguments->ttl);

        if ($lock) {
            $result = $proceedingJoinPoint->process();
            $this->redlock->unlock($lock);
            return $result;
        } else throw new RedLockException('Fail with getting lock');
    }
}