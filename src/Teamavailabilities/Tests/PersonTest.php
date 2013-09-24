<?php

namespace Teamavailabilities\Tests;

use \DateTime;

class PersonTest extends TeamAvailabilitiesTestCase
{
    /**
     * @covers Person::setDataKey
     */
    public function testSetDataKeyIsSet()
    {

        $person = $this->getProxyObjectOf('\\Person', array('setDataKey'));
        $person->setDataKey(array('function' => 'SM'), 'function');
        $this->assertAttributeSame('SM', 'function', $person);
    }

    /**
     * @covers Person::setDataKey
     */
    public function testSetDataKeyIsNotSet()
    {
        $person = $this->getProxyObjectOf('\\Person', array('setDataKey'));

        $person->setDataKey(array('function2' => 'SM'), 'function');

        $this->assertAttributeSame('Dev', 'function', $person);
    }

    /**
     * @dataProvider setEventsDataProvider
     */
    public function testSetEvents($expected, $events)
    {
        $person = new Person('irgendwer');

        $person->setEvents($events);

        $this->assertEquals($expected, count($person->getEvents()));
    }

    public static function setEventsDataProvider()
    {
        return array(
            'is array'  => array(2, array('key' => 'data', 'key2' => 'value2')),
            'NULL'      => array(0, null),
        );
    }

    /**
     * @dataProvider checkEventActiveOnDayTimeDataProvider
     * @covers Person::checkEventActiveOnDayTime
     */
    public function testCheckEventActiveOnDayTime($expected, $type, $date, $start, $end)
    {

        $person = $this->getProxyObjectOf('\\Person', array('checkEventActiveOnDayTime'));
        $event = new StdClass();
        $event->start = $start;
        $event->end = $end;

        $this->assertEquals($expected, $person->checkEventActiveOnDayTime($type, $event, $date));
    }
    public static function checkEventActiveOnDayTimeDataProvider()
    {
        return array(
            'morning starts on another day' => array(
                true,
                'morning',
                DateTime::createFromFormat('Y-m-d', '2012-11-13'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-12 10:00:00'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-14 10:00:00')
            ),
            'morning starts on same day in the morning' => array(
                true,
                'morning',
                DateTime::createFromFormat('Y-m-d', '2012-11-13'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-13 10:00:00'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-14 10:00:00')
            ),
            'morning starts on same day in the afternoon' => array(
                false,
                'morning',
                DateTime::createFromFormat('Y-m-d', '2012-11-13'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-13 13:00:00'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-14 10:00:00')
            ),
            'afternoon ends on another day' => array(
                true,
                'afternoon',
                DateTime::createFromFormat('Y-m-d', '2012-11-13'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-12 10:00:00'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-14 10:00:00')
            ),
            'afternoon ends on same day in the afternoon' => array(
                true,
                'afternoon',
                DateTime::createFromFormat('Y-m-d', '2012-11-13'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-13 10:00:00'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-13 18:00:00')
            ),
            'afternoon ends on same day in the morning' => array(
                false,
                'afternoon',
                DateTime::createFromFormat('Y-m-d', '2012-11-13'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-13 10:00:00'),
                DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-13 11:00:00')
            ),
        );
    }
}
