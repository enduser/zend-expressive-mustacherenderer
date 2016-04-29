<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive-mustacherenderer for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-mustacherenderer/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Mustache;

use Interop\Container\ContainerInterface;

/**
 * Create and return a Mustache engine instance.
 *
 * Optionally uses the service 'config', which should return an array. This
 * factory consumes the following structure:
 *
 * <code>
 * 'plates' => [
 *     'extensions' => [
 *         // extension instances, or
 *         // service names that return extension instances, or
 *         // class names of directly instantiable extensions.
 *     ]
 * ]
 * </code>
 *
 * By default, this factory attaches the Extension\UrlExtension to
 * the engine. You can override the functions that extension exposes
 * by providing an extension class in your extensions array, or providing
 * an alternative Zend\Expressive\Mustache\Extension\UrlExtension service.
 */
class MustacheEngineFactory
{
    /**
     * @param ContainerInterface $container
     * @return \Mustache_Engine
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $config = isset($config['mustache']) ? $config['mustache'] : [];

        // Create the engine instance:
        $engine = new \Mustache_Engine();

        return $engine;
    }
}
