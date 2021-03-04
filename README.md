# redlock-hyperf

```
composer require zonghay/redlock-hyperf
```
Based on [redlock-php](https://github.com/ronnylt/redlock-php) transform to [Hyperf  2.1.*](https://github.com/hyperf/hyperf) 

本sdk基于redlock-php向hyperf ～2.1版本改造。

使用前建议先了解一下Redlock算法的原理，[Redis作者解释Redlock算法（英文）](http://antirez.com/news/77)
中文博客bing一下就好了，这里就不放了

### 使用
```php
try {
            $lock = $this->container->get(RedLock::class)->setRedisPoolName()->setRetryCount(1)->lock('redlock-hyperf-test', 60000);
            //do your code
            $this->container->get(RedLock::class)->unlock($lock);
        } catch (\Throwable $throwable) {
            var_dump($throwable->getMessage());
        }
```
* setRedisPoolName方法用于指定Redlock使用哪些Redis实例作为分布式独立节点，这里需要传入索引数组，默认['default']，数组的值应该是/config/autoload/redis下的连接池name![img.png](img.png)
* setRetryCount方法用于设置获取锁的重试次数，默认2次
* setRetryDelay 用于一次获取锁失败后延迟时间后重试，默认200，单位毫秒
* lock方法，获取锁
  * resource：锁的key
  * ttl：锁过期时间，单位毫秒。
  * return：array|false
* unlock方法，释放锁
  * 参数：lock方法成功后的return