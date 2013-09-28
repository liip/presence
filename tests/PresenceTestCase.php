<?php

namespace Presence;

use lapistano\ProxyObject\ProxyBuilder;

class PresenceTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Provides an easy access to the proxy-object builder.
     *
     * @param $class
     *
     * @return \lapistano\ProxyObject\ProxyBuilder
     */
    protected function getProxyBuilder($class)
    {
        return new ProxyBuilder($class);
    }

    /**
     * Provides a proxy object of the given class.
     *
     * @param \stdClass      $class
     * @param array          $methods
     * @param array          $constructorArguments
     *
     * @return object
     */
    protected function getProxyObjectOf($class, array $methods = array(), array $constructorArguments = array())
    {
        $event = $this->getProxyBuilder($class)
            ->setMethods($methods);

        if (!empty($constructorArguments)) {

            $event->setConstructorArgs(array($constructorArguments));
        } else {
            $event->disableOriginalConstructor();
        }

        return $event->getProxy();
    }
}
