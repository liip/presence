<?php

namespace Presence;

use lapistano\ProxyObject\ProxyBuilder;

class PresenceTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Provides a proxy object of the given class.
     *
     * @param \stdClass $class
     * @param array $methods
     * @param array $constructorArguments
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
     * Provides a common configuration to be used to instantiate an Event.
     *
     * A typeMarker might be:
     *  - #off
     *  - #holiday
     *  - #ferien
     *  - #frei
     *  - #free day
     *  - #daddy
     *  - #daddy day
     *  - @{location}
     *  -
     *
     * @param string $typeMarker
     *
     * @return array
     */
    protected function getEventConfig($typeMarker = '@anywhere')
    {
        $config = array();
        $config['summary']   = 'Summary of an event with type ' . $typeMarker;
        $config['location']  = 'anywhere';
        $config['organizer'] = 'Tux Linus';
        $config['attendees'] = array('list', 'of', 'attendees');
        $config['start']     = array('date' => '2013-01-21');
        $config['end']       = array('date' => '2013-02-21');
        $config['fullDay']   = false;

        return $config;
    }

    /**
     * Provides an instance of the Event class.
     *
     * @param array $config
     *
     * @return Event
     */
    protected function getEventObject(array $config = array())
    {
        return new Event($config);
    }
}
