<?php

namespace RedlockHyperf\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\Di\Annotation\AnnotationCollector;
use RedlockHyperf\Exception\RedLockException;

class AnnotationManager
{
    public function getAnnotation(string $annotation, string $className, string $method): AbstractAnnotation
    {
        $collector = AnnotationCollector::get($className);
        $result = $collector['_m'][$method][$annotation] ?? null;
        if (! $result instanceof $annotation) {
            throw new RedLockException(sprintf('Annotation %s in %s:%s not exist.', $annotation, $className, $method));
        }

        return $result;
    }
}