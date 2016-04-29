<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive-mustacherenderer for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-mustacherenderer/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Mustache\Exception;

use Interop\Container\Exception\ContainerException;
use RuntimeException;

class InvalidExtensionException extends RuntimeException implements
    ExceptionInterface,
    ContainerException
{
}
