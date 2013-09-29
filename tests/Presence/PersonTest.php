<?php

namespace Presence;

class PersonTest extends PresenceTestCase
{
    public function testEvents()
    {
        $person = new Person('Tux');
        $person->setEvents(array($this->getEventConfig()));

        $this->assertContainsOnly('\Presence\Event', $person->getEvents());
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

    public function testGetEventsByDate()
    {
        $events = array($this->getEventObject($this->getEventConfig()));
        $person = new Person('Tux');
        $person->setEvents(array($this->getEventConfig()));

        $this->assertEquals($events, $person->getEventListByDate(new \DateTime('2013-01-21')));
    }

    public function testGetEventsByDateFromCache()
    {
        $events = array($this->getEventObject($this->getEventConfig()));
        $person = new Person('Tux');
        $person->setEvents(array($this->getEventConfig()));
        $person->getEventListByDate(new \DateTime('2013-01-21'));

        $this->assertEquals($events, $person->getEventListByDate(new \DateTime('2013-01-21')));
    }
}
