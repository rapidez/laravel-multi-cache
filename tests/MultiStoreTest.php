<?php

namespace Rapidez\LaravelMultiCacheTests;

use Exception;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use Rapidez\LaravelMultiCache\MultiStore;
use Rapidez\LaravelMultiCache\MultiStoreServiceProvider;

class MultiStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'multi');
        config()->set(
            'cache.stores',
            [
                'array-primary' => [
                    'driver' => 'array',
                ],
                'array-secondary' => [
                    'driver' => 'array',
                ],
                'multi' => [
                    'driver' => 'multi',
                    'stores' => [
                        'array-primary',
                        'array-secondary',
                    ],
                    'sync_missed_stores' => true,
                ],
            ]
        );
    }

    protected function tearDown(): void
    {
        $this->getPrimaryStore()->flush();
        $this->getSecondaryStore()->flush();
    }

    /**
     * Creating a MultiStore with no stores should throw an exception.
     *
     * @test
     */
    public function createWithoutStoresThrowsException()
    {
        $this->expectException(Exception::class);

        $multiStore = new MultiStore(
            app(),
            [
                'stores' => [],
            ],
            app(CacheManager::class)
        );
    }

    /**
     * Test the defined stores are created in the MultiStore.
     *
     * @test
     */
    public function storesAreCreated()
    {
        $this->assertContainsOnlyInstancesOf(
            \Illuminate\Cache\Repository::class,
            $this->getMultiStore()->getStores()
        );

        $this->assertSame(2, count($this->getMultiStore()->getStores()));
    }

    /**
     * Should return null if the value is not in any store.
     *
     * @test
     */
    public function getReturnsNull()
    {
        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));

        $this->assertNull($this->getMultiStore()->get('hello'));
    }

    /**
     * Test the value is returned from the primary store if it exists in it.
     *
     * @test
     */
    public function getFromPrimary()
    {
        $this->getPrimaryStore()->put('hello', 'world', 1);
        $this->getSecondaryStore()->put('hello', 'world2', 1);
        $this->assertSame('world', $this->getMultiStore()->get('hello'));
    }

    /**
     * Test the value is returned from the secondary store if not in the primary.
     *
     * @test
     */
    public function getFromSecondary()
    {
        $value = uniqid();

        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->getSecondaryStore()->put('hello', $value, 1);
        $this->assertSame($value, $this->getMultiStore()->get('hello'));
    }

    /**
     * Test the value is returned from the secondary store, and then stored in the primary,
     * if not already in the primary.
     *
     * @test
     */
    public function getFromSecondStoresInPrimary()
    {
        $this->assertNull($this->getPrimaryStore()->get('hello'));

        $value = uniqid();

        $this->getSecondaryStore()->put('hello', $value, 1);

        $this->assertSame($value, $this->getMultiStore()->get('hello'));

        $this->assertSame($value, $this->getPrimaryStore()->get('hello'));
    }

    /**
     * Testing storing a value stores it in all stores.
     *
     * @test
     */
    public function putStoresInAllStores()
    {
        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));

        $value = uniqid();

        $this->getMultiStore()->put('hello', $value, 1);

        $this->assertSame($value, $this->getPrimaryStore()->get('hello'));
        $this->assertSame($value, $this->getSecondaryStore()->get('hello'));
    }

    /**
     * Testing storing a value stores it in all stores.
     *
     * @test
     */
    public function foreverStoresInAllStores()
    {
        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));

        $value = uniqid();

        $this->getMultiStore()->forever('hello', $value);

        $this->assertSame($value, $this->getPrimaryStore()->get('hello'));
        $this->assertSame($value, $this->getSecondaryStore()->get('hello'));
    }

    /**
     * Increment should increment all stores.
     *
     * @test
     */
    public function increment()
    {
        $this->getPrimaryStore()->put('number', 1, 1);
        $this->getSecondaryStore()->put('number', 1, 1);

        $this->getMultiStore()->increment('number');

        $this->assertSame(2, $this->getPrimaryStore()->get('number'));
        $this->assertSame(2, $this->getSecondaryStore()->get('number'));
    }

    /**
     * Increment should decrement all stores.
     *
     * @test
     */
    public function decrement()
    {
        $this->getPrimaryStore()->put('number', 1, 1);
        $this->getSecondaryStore()->put('number', 1, 1);

        $this->getMultiStore()->decrement('number');

        $this->assertSame(0, $this->getPrimaryStore()->get('number'));
        $this->assertSame(0, $this->getSecondaryStore()->get('number'));
    }

    /**
     * Forget should forget in all stores.
     *
     * @test
     */
    public function forget()
    {
        $this->getPrimaryStore()->put('hello', 'world1', 1);
        $this->getPrimaryStore()->put('goodbye', 'world2', 1);
        $this->getSecondaryStore()->put('hello', 'world3', 1);

        $this->assertSame('world1', $this->getPrimaryStore()->get('hello'));
        $this->assertSame('world2', $this->getPrimaryStore()->get('goodbye'));
        $this->assertSame('world3', $this->getSecondaryStore()->get('hello'));

        $this->getMultiStore()->forget('hello');

        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertSame('world2', $this->getPrimaryStore()->get('goodbye'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));
    }

    /**
     * Flush should flush in all stores.
     *
     * @test
     */
    public function flush()
    {
        $this->getPrimaryStore()->put('hello', 'world1', 1);
        $this->getPrimaryStore()->put('goodbye', 'world2', 1);
        $this->getSecondaryStore()->put('hello', 'world3', 1);

        $this->assertSame('world1', $this->getPrimaryStore()->get('hello'));
        $this->assertSame('world2', $this->getPrimaryStore()->get('goodbye'));
        $this->assertSame('world3', $this->getSecondaryStore()->get('hello'));

        $this->getMultiStore()->flush();

        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertNull($this->getPrimaryStore()->get('goodbye'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));
    }

    /**
     * @test
     */
    public function getPrefixReturnsEmptyString()
    {
        $this->assertSame('', $this->getMultiStore()->getPrefix());
    }

    /**
     * @test
     */
    public function syncMissedStoresIsFalse()
    {
        config()->set('cache.stores.multi.sync_missed_stores', false);

        $this->assertNull($this->getPrimaryStore()->get('hello'));

        $value = uniqid();

        $this->getSecondaryStore()->put('hello', $value, 1);

        $this->assertSame($value, $this->getMultiStore()->get('hello'));

        $this->assertNull($this->getPrimaryStore()->get('hello'));
    }

    /**
     * @test
     */
    public function syncMissedStoresConfigIsMissing()
    {
        // Config is first written inside setUp function. Here we overwrite it
        // so that sync_missed_stores is deleted from config.
        config()->set(
            'cache.stores.multi',
            [
                'driver' => 'multi',
                'stores' => [
                    'array-primary',
                    'array-secondary',
                ],
            ]
        );

        $this->assertNull($this->getPrimaryStore()->get('hello'));

        $value = uniqid();

        $this->getSecondaryStore()->put('hello', $value, 1);

        $this->assertSame($value, $this->getMultiStore()->get('hello'));

        $this->assertSame($value, $this->getPrimaryStore()->get('hello'));
    }

    protected function getApplicationProviders($app)
    {
        return [
            ...parent::getApplicationProviders($app),
            MultiStoreServiceProvider::class,
        ];
    }

    /**
     * @return MultiStore
     */
    protected function getMultiStore()
    {
        return Cache::store('multi');
    }

    /**
     * @return ArrayStore
     */
    protected function getPrimaryStore()
    {
        return Cache::store('array-primary');
    }

    /**
     * @return ArrayStore
     */
    protected function getSecondaryStore()
    {
        return Cache::store('array-secondary');
    }
}
