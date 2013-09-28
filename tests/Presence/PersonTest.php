<?php

namespace Presence;

class PersonTest extends PresenceTestCase
{
    public function testSetEvents()
    {
        $person = new Person('Tux');
        $person->setEvents(array($this->getEventConfig()));

        $this->assertAttributeContainsOnly('\Presence\Event', 'events',$person);
    }

    public function testSetEventsWithEmptyEventListProvided()
    {
        $person = new Person('Tux');

        $this->setExpectedException('\Assert\InvalidArgumentException');
        $person->setEvents(array());
    }

    public function testGetSchedule()
    {
        $calendar = $this->getMockBuilder('Presence\CalendarInterface')
            ->setMethods(array('getSchedule'))
            ->getMockForAbstractClass();
        $calendar
            ->expects($this->once())
            ->method('getSchedule')
            ->will($this->returnValue(array()));

        $person = new Person('Tux');
        $person->getSchedule($calendar);

        $this->assertAttributeEmpty('events', $person);
    }
}
