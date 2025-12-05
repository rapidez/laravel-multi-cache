<?php

namespace Rapidez\LaravelMultiCache;

class MultiStoreTaggedCache extends \Illuminate\Cache\TaggedCache
{
    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $this->store->flush();
        $this->tags->reset();

        return true;
    }
}
