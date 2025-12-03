<?php

namespace Rapidez\LaravelMultiCache;

use Exception;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RetrievesMultipleKeys;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Foundation\Application;
use Illuminate\Cache\TaggableStore;

class MultiStore extends TaggableStore
{
    use RetrievesMultipleKeys;

    /**
     * @var Application
     */
    public $app;

    /**
     * @var array<mixed>
     */
    public $config;

    /**
     * @var array<Store|Repository>
     */
    protected $stores = [];

    /**
     * @var CacheManager
     */
    public $cacheManager;

    /**
     * @var bool
     */
    protected $syncMissedStores = true;

    /**
     * MultiStore constructor.
     *
     * @param  array<mixed>  $config
     * @param  ?array<string>  $tags
     *
     * @throws Exception
     */
    public function __construct(Application $app, array $config, CacheManager $cacheManager, ?array $tags = null)
    {
        $this->app = $app;
        $this->config = $config;
        $this->cacheManager = $cacheManager;
        $this->syncMissedStores = ! isset($config['sync_missed_stores']) || $config['sync_missed_stores'];

        if (empty($config['stores'])) {
            throw new Exception('No stores are defined for multi cache.');
        }

        foreach ($config['stores'] as $name) {
            $this->stores[$name] = $this->cacheManager->store($name);
            if ($tags === null || !count($tags)) {
                continue;
            }

            if ($this->stores[$name]->supportsTags()) {
                $this->stores[$name] = $this->stores[$name]->tags($tags);
            }
        }
    }

    /**
     * Begin executing a new tags operation.
     * Disclaimer: any store that does not support tags will run actions without tags.
     * Flushing will then flush all items.
     *
     * @param  mixed  $names
     * @return MultiStoreTaggedCache
     */
    public function tags($names): MultiStoreTaggedCache
    {
        return new MultiStoreTaggedCache(new self($this->app, $this->config, $this->cacheManager, tags: is_array($names) ? $names : func_get_args()), new \Illuminate\Cache\TagSet($this, is_array($names) ? $names : func_get_args()));
    }

    /**
     * @return array<Store|Repository>
     */
    public function getStores()
    {
        return $this->stores;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        /** @var Store[] $missedStores */
        $missedStores = [];

        $foundValue = null;

        foreach ($this->stores as $store) {
            if (($value = $store->get($key)) !== null) {
                $foundValue = $value;
                break;
            } else {
                $missedStores[] = $store;
            }
        }

        if ($foundValue && $this->syncMissedStores) {
            foreach ($missedStores as $store) {
                // Remember in the higher cache store for 1 day.
                $store->put($key, $foundValue, 86400);
            }
        }

        return $foundValue;
    }

    /**
     * Store an item in all cache stores for a given number of minutes.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     * @return bool
     */
    public function put($key, $value, $seconds)
    {
        $success = true;
        foreach ($this->stores as $store) {
            $success = $store->put($key, $value, $seconds) && $success;
        }

        return $success;
    }

    /**
     * Increment the value of an item all cache stores.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        $returnValue = false;

        foreach ($this->stores as $store) {
            $returnValue = $store->increment($key, $value);
        }

        return $returnValue;
    }

    /**
     * Decrement the value of an item in all cache stores.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        $returnValue = false;

        foreach ($this->stores as $store) {
            $returnValue = $store->decrement($key, $value);
        }

        return $returnValue;
    }

    /**
     * Store an item on all cache stores indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function forever($key, $value)
    {
        $success = true;
        foreach ($this->stores as $store) {
            $success = $store->forever($key, $value) && $success;
        }

        return $success;
    }

    /**
     * Remove an item from all cache stores.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        $success = true;

        foreach ($this->stores as $store) {
            $success = $store->forget($key) && $success;
        }

        return $success;
    }

    /**
     * Remove all items from all cache stores.
     *
     * @return bool
     */
    public function flush()
    {
        $success = true;

        foreach ($this->stores as $store) {
            $success = $store->flush() && $success; // @phpstan-ignore-line
        }

        return $success;
    }

    /**
     * Remove all expired tag set entries.
     *
     * @return void
     */
    public function flushStaleTags()
    {
        foreach ($this->stores as $store) {
            if (method_exists($store, 'flushStaleTags')) {
                $store->flushStaleTags();

                break;
            }
        }
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return '';
    }
}
