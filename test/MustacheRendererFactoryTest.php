<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Mustache;

use Interop\Container\ContainerInterface;
use League\Mustache\Engine as MustacheEngine;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;
use Zend\Expressive\Helper\ServerUrlHelper;
use Zend\Expressive\Helper\UrlHelper;
use Zend\Expressive\Mustache\Extension\UrlExtension;
use Zend\Expressive\Mustache\MustacheRendererFactory;
use Zend\Expressive\Mustache\MustacheRenderer;
use Zend\Expressive\Template\TemplatePath;

class MustacheRendererFactoryTest extends TestCase
{
    /**
     * @var  ContainerInterface
     */
    private $container;

    /**
     * @var bool
     */
    public $errorCaught = false;

    public function setUp()
    {
        $this->errorCaught = false;
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function configureEngineService()
    {
        $this->container->has(MustacheEngine::class)->willReturn(false);
        $this->container->has(UrlExtension::class)->willReturn(false);
        $this->container->has(UrlHelper::class)->willReturn(true);
        $this->container->has(ServerUrlHelper::class)->willReturn(true);
        $this->container->get(UrlHelper::class)->willReturn($this->prophesize(UrlHelper::class)->reveal());
        $this->container->get(ServerUrlHelper::class)->willReturn($this->prophesize(ServerUrlHelper::class)->reveal());
    }

    public function fetchMustacheEngine(MustacheRenderer $plates)
    {
        $r = new ReflectionProperty($plates, 'template');
        $r->setAccessible(true);
        return $r->getValue($plates);
    }

    public function getConfigurationPaths()
    {
        return [
            'foo' => __DIR__ . '/TestAsset/bar',
            1 => __DIR__ . '/TestAsset/one',
            'bar' => [
                __DIR__ . '/TestAsset/baz',
                __DIR__ . '/TestAsset/bat',
            ],
            0 => [
                __DIR__ . '/TestAsset/two',
                __DIR__ . '/TestAsset/three',
            ],
        ];
    }

    public function assertPathsHasNamespace($namespace, array $paths, $message = null)
    {
        $message = $message ?: sprintf('Paths do not contain namespace %s', $namespace ?: 'null');

        $found = false;
        foreach ($paths as $path) {
            $this->assertInstanceOf(TemplatePath::class, $path, 'Non-TemplatePath found in paths list');
            if ($path->getNamespace() === $namespace) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, $message);
    }

    public function assertPathNamespaceCount($expected, $namespace, array $paths, $message = null)
    {
        $message = $message ?: sprintf('Did not find %d paths with namespace %s', $expected, $namespace ?: 'null');

        $count = 0;
        foreach ($paths as $path) {
            $this->assertInstanceOf(TemplatePath::class, $path, 'Non-TemplatePath found in paths list');
            if ($path->getNamespace() === $namespace) {
                $count += 1;
            }
        }
        $this->assertSame($expected, $count, $message);
    }

    public function assertPathNamespaceContains($expected, $namespace, array $paths, $message = null)
    {
        $message = $message ?: sprintf('Did not find path %s in namespace %s', $expected, $namespace ?: null);

        $found = [];
        foreach ($paths as $path) {
            $this->assertInstanceOf(TemplatePath::class, $path, 'Non-TemplatePath found in paths list');
            if ($path->getNamespace() === $namespace) {
                $found[] = $path->getPath();
            }
        }
        $this->assertContains($expected, $found, $message);
    }

    public function testCallingFactoryWithNoConfigReturnsMustacheInstance()
    {
        $this->container->has('config')->willReturn(false);
        $this->configureEngineService();
        $factory = new MustacheRendererFactory();
        $plates = $factory($this->container->reveal());
        $this->assertInstanceOf(MustacheRenderer::class, $plates);
        return $plates;
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsMustacheInstance
     */
    public function testUnconfiguredMustacheInstanceContainsNoPaths(MustacheRenderer $plates)
    {
        $paths = $plates->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEmpty($paths);
    }

    public function testConfiguresTemplateSuffix()
    {
        $config = [
            'templates' => [
                'extension' => 'html',
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->configureEngineService();
        $factory = new MustacheRendererFactory();
        $plates = $factory($this->container->reveal());

        $engine = $this->fetchMustacheEngine($plates);
        $r = new ReflectionProperty($engine, 'fileExtension');
        $r->setAccessible(true);
        $extension = $r->getValue($engine);
        $this->assertAttributeSame($config['templates']['extension'], 'fileExtension', $extension);
    }

    public function testExceptionIsRaisedIfMultiplePathsSpecifyDefaultNamespace()
    {
        $config = [
            'templates' => [
                'paths' => [
                    0 => __DIR__ . '/TestAsset/bar',
                    1 => __DIR__ . '/TestAsset/baz',
                ]
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->configureEngineService();
        $factory = new MustacheRendererFactory();

        $reset = set_error_handler(function ($errno, $errstr) {
            $this->errorCaught = true;
        }, E_USER_WARNING);
        $plates = $factory($this->container->reveal());
        restore_error_handler();
        $this->assertTrue($this->errorCaught, 'Did not detect duplicate path for default namespace');
    }

    public function testExceptionIsRaisedIfMultiplePathsInSameNamespace()
    {
        $config = [
            'templates' => [
                'paths' => [
                    'bar' => [
                        __DIR__ . '/TestAsset/baz',
                        __DIR__ . '/TestAsset/bat',
                    ],
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->configureEngineService();
        $factory = new MustacheRendererFactory();

        $this->setExpectedException('LogicException', 'already being used');
        $plates = $factory($this->container->reveal());
    }

    public function testConfiguresPaths()
    {
        $config = [
            'templates' => [
                'paths' => [
                    'foo' => __DIR__ . '/TestAsset/bar',
                    1 => __DIR__ . '/TestAsset/one',
                    'bar' => __DIR__ . '/TestAsset/baz',
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->configureEngineService();
        $factory = new MustacheRendererFactory();
        $plates = $factory($this->container->reveal());

        $paths = $plates->getPaths();
        $this->assertPathsHasNamespace('foo', $paths);
        $this->assertPathsHasNamespace('bar', $paths);
        $this->assertPathsHasNamespace(null, $paths);

        $this->assertPathNamespaceCount(1, 'foo', $paths);
        $this->assertPathNamespaceCount(1, 'bar', $paths);
        $this->assertPathNamespaceCount(1, null, $paths);

        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/bar', 'foo', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/baz', 'bar', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/one', null, $paths);
    }

    public function testWillPullMustacheEngineFromContainerIfPresent()
    {
        $engine = $this->prophesize(MustacheEngine::class);
        $this->container->has(MustacheEngine::class)->willReturn(true);
        $this->container->get(MustacheEngine::class)->willReturn($engine->reveal());

        $this->container->has('config')->willReturn(false);

        $factory = new MustacheRendererFactory();
        $renderer = $factory($this->container->reveal());
        $this->assertAttributeSame($engine->reveal(), 'template', $renderer);
    }
}
