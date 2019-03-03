<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony CMF package.
 *
 * (c) Symfony CMF
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Cmf\Bundle\BlockBundle\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use Sonata\BlockBundle\Block\BlockContextManagerInterface;
use Sonata\BlockBundle\Block\BlockLoaderInterface;
use Sonata\BlockBundle\Block\BlockRendererInterface;
use Symfony\Cmf\Bundle\BlockBundle\Cache\BlockSsiCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class BlockSsiCacheTest extends TestCase
{
    /**
     * @dataProvider      getExceptionCacheKeys
     */
    public function testExceptions($keys)
    {
        $this->expectException(\RuntimeException::class);

        $router = $this->createMock(RouterInterface::class);

        $blockRenderer = $this->createMock(BlockRendererInterface::class);

        $blockLoader = $this->createMock(BlockLoaderInterface::class);

        $blockContextManager = $this->createMock(BlockContextManagerInterface::class);

        $cache = new BlockSsiCache('My Token', $router, $blockRenderer, $blockLoader, $blockContextManager);

        $cache->get($keys, 'data');
    }

    public static function getExceptionCacheKeys()
    {
        return [
            [[]],
            [['block_id' => '/cms/content/home/additionalInfoBlock']],
            [['updated_at' => 'foo']],
        ];
    }

    public function testInitCache()
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->any())->method('generate')->will($this->returnValue('/symfony-cmf/block/cache/ssi/XXXXX/%2Fcms%2Fcontent%2Fhome%2FadditionalInfoBlock?updated_at=as'));

        $blockRenderer = $this->createMock(BlockRendererInterface::class);

        $blockLoader = $this->createMock(BlockLoaderInterface::class);

        $blockContextManager = $this->createMock(BlockContextManagerInterface::class);

        $cache = new BlockSsiCache('My Token', $router, $blockRenderer, $blockLoader, $blockContextManager);

        $this->assertTrue($cache->flush([]));
        $this->assertTrue($cache->flushAll());

        $keys = [
            'block_id' => '/cms/content/home/additionalInfoBlock',
            'updated_at' => 'as',
        ];

        $cacheElement = $cache->set($keys, 'data');

        $this->assertInstanceOf('Sonata\Cache\CacheElement', $cacheElement);

        $this->assertTrue($cache->has(['id' => 7]));

        $cacheElement = $cache->get($keys);

        $this->assertInstanceOf('Sonata\Cache\CacheElement', $cacheElement);

        $this->assertEquals('<!--# include virtual="/symfony-cmf/block/cache/ssi/XXXXX/%2Fcms%2Fcontent%2Fhome%2FadditionalInfoBlock?updated_at=as" -->', $cacheElement->getData()->getContent());
    }

    public function testAccessDenied()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

        $token = 'My Token';
        $keys = [
            'block_id' => '/cms/content/home/additionalInfoBlock',
            'updated_at' => 'as',
        ];

        $router = $this->createMock(RouterInterface::class);

        $blockRenderer = $this->createMock(BlockRendererInterface::class);

        $blockLoader = $this->createMock(BlockLoaderInterface::class);

        $blockContextManager = $this->createMock(BlockContextManagerInterface::class);

        $cache = new BlockSsiCache($token, $router, $blockRenderer, $blockLoader, $blockContextManager);

        $request = new Request($keys, [], ['_token' => 'XXXXX']);

        $cache->cacheAction($request);
    }

    public function testBlockNotFound()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $token = 'My Token';
        $keys = [
            'block_id' => '/not/found',
            'updated_at' => 'as',
        ];

        $router = $this->createMock(RouterInterface::class);

        $blockRenderer = $this->createMock(BlockRendererInterface::class);

        $blockLoader = $this->createMock(BlockLoaderInterface::class);

        $blockContextManager = $this->createMock(BlockContextManagerInterface::class);

        $cache = new BlockSsiCache($token, $router, $blockRenderer, $blockLoader, $blockContextManager);

        $refCache = new \ReflectionClass($cache);
        $refComputeHash = $refCache->getMethod('computeHash');
        $refComputeHash->setAccessible(true);
        $computedToken = $refComputeHash->invokeArgs($cache, [$keys]);

        $request = new Request($keys, [], ['_token' => $computedToken]);

        $cache->cacheAction($request);
    }
}
