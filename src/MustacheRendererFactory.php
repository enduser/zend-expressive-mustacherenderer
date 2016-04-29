<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Mustache;

use Interop\Container\ContainerInterface;
use League\Mustache\Engine as MustacheEngine;

/**
 * Create and return a Mustache template instance.
 *
 * Optionally uses the service 'config', which should return an array. This
 * factory consumes the following structure:
 *
 * <code>
 * 'templates' => [
 *     'extension' => 'file extension used by templates; defaults to html',
 *     'paths' => [
 *         // namespace / path pairs
 *         //
 *         // Numeric namespaces imply the default/main namespace. Paths may be
 *         // strings or arrays of string paths to associate with the namespace.
 *     ],
 * ]
 * </code>
 *
 * If the service League\Mustache\Engine exists, that value will be used
 * for the MustacheEngine; otherwise, this factory invokes the MustacheEngineFactory
 * to create an instance.
 */
class MustacheRendererFactory
{
    /**
     * @param ContainerInterface $container
     * @return MustacheRenderer
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $config = isset($config['templates']) ? $config['templates'] : [];

        // Create the engine instance:
        $engine = $this->createEngine($container);

        // Set file extension
        if (isset($config['extension'])) {
            $engine->setFileExtension($config['extension']);
        }

        // Inject engine
        $plates = new MustacheRenderer($engine);

        // Add template paths
        $allPaths = isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];
        foreach ($allPaths as $namespace => $paths) {
            $namespace = is_numeric($namespace) ? null : $namespace;
            foreach ((array) $paths as $path) {
                $plates->addPath($path, $namespace);
            }
        }

        return $plates;
    }

    /**
     * Create and return a Mustache Engine instance.
     *
     * If the container has the League\Mustache\Engine service, returns it.
     *
     * Otherwise, invokes the MustacheEngineFactory with the $container to create
     * and return the instance.
     *
     * @param ContainerInterface $container
     * @return \Mustache_Engine
     */
    private function createEngine(ContainerInterface $container)
    {
        if ($container->has(\Mustache_Engine::class)) {
            return $container->get(\Mustache_Engine::class);
        }

        $engineFactory = new MustacheEngineFactory();
        return $engineFactory($container);
    }
}
