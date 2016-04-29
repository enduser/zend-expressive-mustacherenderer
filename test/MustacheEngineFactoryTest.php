<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive-mustacherenderer for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-mustacherenderer/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Mustache;

use Interop\Container\ContainerInterface;
use League\Mustache\Engine as MustacheEngine;
use League\Mustache\Extension\ExtensionInterface;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Zend\Expressive\Helper\ServerUrlHelper;
use Zend\Expressive\Helper\UrlHelper;
use Zend\Expressive\Mustache\Exception\InvalidExtensionException;
use Zend\Expressive\Mustache\Extension\UrlExtension;
use Zend\Expressive\Mustache\MustacheEngineFactory;

class MustacheEngineFactoryTest extends TestCase
{
    /**
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $container = null;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);

        $this->container->has(UrlHelper::class)->willReturn(true);
        $this->container->get(UrlHelper::class)->willReturn(
            $this->prophesize(UrlHelper::class)->reveal()
        );

        $this->container->has(ServerUrlHelper::class)->willReturn(true);
        $this->container->get(ServerUrlHelper::class)->willReturn(
            $this->prophesize(ServerUrlHelper::class)->reveal()
        );

        $this->container->has(UrlExtension::class)->willReturn(false);
    }

    public function testFactoryReturnsMustacheEngine()
    {
        $this->container->has('config')->willReturn(false);
        $factory = new MustacheEngineFactory();
        $engine = $factory($this->container->reveal());
        $this->assertInstanceOf(\Mustache_Engine::class, $engine);
        return $engine;
    }

    public function testFactoryCanRegisterConfiguredExtensions()
    {
        $extensionOne = $this->prophesize(ExtensionInterface::class);
        $extensionOne->register(Argument::type(MustacheEngine::class))->shouldBeCalled();

        $extensionTwo = $this->prophesize(ExtensionInterface::class);
        $extensionTwo->register(Argument::type(MustacheEngine::class))->shouldBeCalled();
        $this->container->has('ExtensionTwo')->willReturn(true);
        $this->container->get('ExtensionTwo')->willReturn($extensionTwo->reveal());

        $this->container->has(TestAsset\TestExtension::class)->willReturn(false);

        $config = [
            'plates' => [
                'extensions' => [
                    $extensionOne->reveal(),
                    'ExtensionTwo',
                    TestAsset\TestExtension::class,
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);

        $factory = new MustacheEngineFactory();
        $engine = $factory($this->container->reveal());
        $this->assertInstanceOf(MustacheEngine::class, $engine);

        // Test that the TestExtension was registered. The other two extensions
        // are verified via mocking.
        $this->assertSame($engine, TestAsset\TestExtension::$engine);
    }

    public function invalidExtensions()
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'zero' => [0],
            'int' => [1],
            'zero-float' => [0.0],
            'float' => [1.1],
            'non-class-string' => ['not-a-class'],
            'array' => [['not-an-extension']],
            'non-extension-object' => [(object) ['extension' => 'not-really']],
        ];
    }

    /**
     * @dataProvider invalidExtensions
     */
    public function testFactoryRaisesExceptionForInvalidExtensions($extension)
    {
        $config = [
            'plates' => [
                'extensions' => [
                    $extension,
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);

        if (is_string($extension)) {
            $this->container->has($extension)->willReturn(false);
        }

        $factory = new MustacheEngineFactory();
        $this->setExpectedException(InvalidExtensionException::class);
        $factory($this->container->reveal());
    }
}
