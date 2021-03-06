<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * ehough_pulsar_ApcClassLoader implements a wrapping autoloader cached in APC for PHP 5.1.3+.
 *
 * It expects an object implementing a findFile method to find the file. This
 * allow using it as a wrapper around the other loaders of the component (the
 * ClassLoader and the UniversalClassLoader for instance) but also around any
 * other autoloader following this convention (the Composer one for instance)
 *
 *     $loader = new ehough_pulsar_ClassLoader();
 *
 *     // register classes with namespaces
 *     $loader->add('Symfony\Component', dirname(__FILE__).'/component');
 *     $loader->add('Symfony',           dirname(__FILE__).'/framework');
 *
 *     $cachedLoader = new ehough_pulsar_ApcClassLoader('my_prefix', $loader);
 *
 *     // activate the cached autoloader
 *     $cachedLoader->register();
 *
 *     // eventually deactivate the non-cached loader if it was registered previously
 *     // to be sure to use the cached one.
 *     $loader->unregister();
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @api
 */
class ehough_pulsar_ApcClassLoader
{
    private $prefix;

    /**
     * The class loader object being decorated.
     *
     * @var ehough_pulsar_ClassLoader
     *   A class loader object that implements the findFile() method.
     */
    protected $decorated;

    /**
     * Constructor.
     *
     * @param string $prefix      The APC namespace prefix to use.
     * @param object $decorated   A class loader object that implements the findFile() method.
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     *
     * @api
     */
    public function __construct($prefix, $decorated)
    {
        if (!extension_loaded('apc')) {
            throw new RuntimeException('Unable to use ApcClassLoader as APC is not enabled.');
        }

        if (!method_exists($decorated, 'findFile')) {
            throw new InvalidArgumentException('The class finder must implement a "findFile" method.');
        }

        $this->prefix = $prefix;
        $this->decorated = $decorated;
    }

    /**
     * Registers this instance as an autoloader.
     *
     * @param Boolean $prepend Whether to prepend the autoloader or not
     */
    public function register($prepend = false)
    {
        // We need a special call to the autoloader for PHP 5.2, missing the
        // third parameter.
        if (version_compare(PHP_VERSION, '5.3.0', '<')) {

            spl_autoload_register(array($this, 'loadClass'), true);

        } else {

            spl_autoload_register(array($this, 'loadClass'), true, $prepend);
        }
    }

    /**
     * Unregisters this instance as an autoloader.
     */
    public function unregister()
    {
        spl_autoload_unregister(array($this, 'loadClass'));
    }

    /**
     * Loads the given class or interface.
     *
     * @param string $class The name of the class
     *
     * @return Boolean|null True, if loaded
     */
    public function loadClass($class)
    {
        if ($file = $this->findFile($class)) {
            require $file;

            return true;
        }
    }

    /**
     * Finds a file by class name while caching lookups to APC.
     *
     * @param string $class A class name to resolve to file
     *
     * @return string|null
     */
    public function findFile($class)
    {
        if (false === $file = apc_fetch($this->prefix.$class)) {
            apc_store($this->prefix.$class, $file = $this->decorated->findFile($class));
        }

        return $file;
    }

    /**
     * Passes through all unknown calls onto the decorated object.
     */
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->decorated, $method), $args);
    }

}
