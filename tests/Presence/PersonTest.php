<?php

namespace Presence;

class PersonTest extends PresenceTestCase
{
    /**
     * Provides a preset instance of the Person class.
     *
     * @param string $typeMarker
     *
     * @return Person
     */
    protected function getPerson($typeMarker = '@anywhere')
    {
        $person = new Person('Tux');
        $person->setEvents(array($this->getEventConfig($typeMarker)));

        return $person;
    }

    public function testEvents()
    {
        $person = $this->getPerson();

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

    public function testGetEventsListByDate()
    {
        $events = array($this->getEventObject($this->getEventConfig('')));
        $person = $this->getPerson('');

        $this->assertEquals($events, $person->getEventListByDate(new \DateTime('2013-01-21')));
    }

    public function testGetLocationByDate()
    {
        $person = $this->getPerson();

        $this->assertEquals('anywhere', $person->getLocationByDate(new \DateTime('2013-01-21')));
    }
}
