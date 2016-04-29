<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Mustache;

use ArrayObject;
use League\Mustache\Engine;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Expressive\Template\Exception;
use Zend\Expressive\Mustache\MustacheRenderer;
use Zend\Expressive\Template\TemplatePath;

class MustacheRendererTest extends TestCase
{
    /**
     * @var \Mustache_Engine
     */
    private $platesEngine;

    /**
     * @var bool
     */
    private $error;

    public function setUp()
    {
        $this->error = false;
        $this->platesEngine = new \Mustache_Engine();
    }

    public function assertTemplatePath($path, TemplatePath $templatePath, $message = null)
    {
        $message = $message ?: sprintf('Failed to assert TemplatePath contained path %s', $path);
        $this->assertEquals($path, $templatePath->getPath(), $message);
    }

    public function assertTemplatePathString($path, TemplatePath $templatePath, $message = null)
    {
        $message = $message ?: sprintf('Failed to assert TemplatePath casts to string path %s', $path);
        $this->assertEquals($path, (string) $templatePath, $message);
    }

    public function assertTemplatePathNamespace($namespace, TemplatePath $templatePath, $message = null)
    {
        $message = $message ?: sprintf('Failed to assert TemplatePath namespace matched %s', var_export($namespace, 1));
        $this->assertEquals($namespace, $templatePath->getNamespace(), $message);
    }

    public function assertEmptyTemplatePathNamespace(TemplatePath $templatePath, $message = null)
    {
        $message = $message ?: 'Failed to assert TemplatePath namespace was empty';
        $this->assertEmpty($templatePath->getNamespace(), $message);
    }

    public function assertEqualTemplatePath(TemplatePath $expected, TemplatePath $received, $message = null)
    {
        $message = $message ?: 'Failed to assert TemplatePaths are equal';
        if ($expected->getPath() !== $received->getPath()
            || $expected->getNamespace() !== $received->getNamespace()
        ) {
            $this->fail($message);
        }
    }

    public function testCanProvideEngineAtInstantiation()
    {
        $renderer = new MustacheRenderer($this->platesEngine);
        $this->assertInstanceOf(MustacheRenderer::class, $renderer);
        $this->assertEmpty($renderer->getPaths());
    }

    public function testLazyLoadsEngineAtInstantiationIfNoneProvided()
    {
        $renderer = new MustacheRenderer();
        $this->assertInstanceOf(MustacheRenderer::class, $renderer);
        $this->assertEmpty($renderer->getPaths());
    }

    public function testCanAddPath()
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
        return $renderer;
    }

    /**
     * @param MustacheRenderer $renderer
     * @depends testCanAddPath
     */
    public function testAddingSecondPathWithoutNamespaceIsANoopAndRaisesWarning($renderer)
    {
        $paths = $renderer->getPaths();
        $path  = array_shift($paths);

        set_error_handler(function ($error, $message) {
            $this->error = true;
            $this->assertContains('duplicate', $message);
            return true;
        }, E_USER_WARNING);
        $renderer->addPath(__DIR__);
        restore_error_handler();

        $this->assertTrue($this->error, 'Error handler was not triggered when calling addPath() multiple times');

        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $test = array_shift($paths);
        $this->assertEqualTemplatePath($path, $test);
    }

    public function testCanAddPathWithNamespace()
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset', 'test');
        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathNamespace('test', $paths[0]);
    }

    public function testDelegatesRenderingToUnderlyingImplementation()
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Mustache';
        $result = $renderer->render('plates', [ 'name' => $name ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content = str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
    }

    public function invalidParameterValues()
    {
        return [
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['value'],
        ];
    }

    /**
     * @dataProvider invalidParameterValues
     */
    public function testRenderRaisesExceptionForInvalidParameterTypes($params)
    {
        $renderer = new MustacheRenderer();
        $this->setExpectedException(Exception\InvalidArgumentException::class);
        $renderer->render('foo', $params);
    }

    public function testCanRenderWithNullParams()
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $result = $renderer->render('plates-null', null);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates-null.php');
        $this->assertEquals($content, $result);
    }

    public function objectParameterValues()
    {
        $names = [
            'stdClass'    => uniqid(),
            'ArrayObject' => uniqid(),
        ];

        return [
            'stdClass'    => [(object) ['name' => $names['stdClass']], $names['stdClass']],
            'ArrayObject' => [new ArrayObject(['name' => $names['ArrayObject']]), $names['ArrayObject']],
        ];
    }

    /**
     * @dataProvider objectParameterValues
     */
    public function testCanRenderWithParameterObjects($params, $search)
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $result = $renderer->render('plates', $params);
        $this->assertContains($search, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content = str_replace('<?=$this->e($name)?>', $search, $content);
        $this->assertEquals($content, $result);
    }

    /**
     * @group namespacing
     */
    public function testProperlyResolvesNamespacedTemplate()
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset/test', 'test');

        $expected = file_get_contents(__DIR__ . '/TestAsset/test/test.php');
        $test     = $renderer->render('test::test');

        $this->assertSame($expected, $test);
    }

    public function testAddParameterToOneTemplate()
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Mustache';
        $renderer->addDefaultParam('plates', 'name', $name);
        $result = $renderer->render('plates');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);

        // @fixme hack to work around https://github.com/thephpleague/plates/issues/60, remove if ever merged
        set_error_handler(function ($error, $message) {
            $this->assertContains('Undefined variable: name', $message);
            return true;
        }, E_NOTICE);
        $renderer->render('plates-2');
        restore_error_handler();

        $content= str_replace('<?=$this->e($name)?>', '', $content);
        $this->assertEquals($content, $result);
    }

    public function testAddSharedParameters()
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Mustache';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'name', $name);
        $result = $renderer->render('plates');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
        $result = $renderer->render('plates-2');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates-2.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
    }

    public function testOverrideSharedParametersPerTemplate()
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Mustache';
        $name2 = 'Saucers';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'name', $name);
        $renderer->addDefaultParam('plates-2', 'name', $name2);
        $result = $renderer->render('plates');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
        $result = $renderer->render('plates-2');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates-2.php');
        $content= str_replace('<?=$this->e($name)?>', $name2, $content);
        $this->assertEquals($content, $result);
    }

    public function testOverrideSharedParametersAtRender()
    {
        $renderer = new MustacheRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Mustache';
        $name2 = 'Saucers';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'name', $name);
        $result = $renderer->render('plates', ['name' => $name2]);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name2, $content);
        $this->assertEquals($content, $result);
    }
}
