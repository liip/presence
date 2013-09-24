<?php

namespace Teamavailabilities\Tests;

use DateTime;

class EventTest extends TeamAvailabilitiesTestCase
{
    /**
     * @covers Event::getRawValue
     */
    public function testGetRawValueIsSet()
    {

        $event = $this->getProxyObjectOf('\\Teamavailabilities\\Event', array('getRawValue'));
        $event->raw = array('summary' => 'test summary');

        $this->assertEquals('test summary', $event->getRawValue('summary'));
    }

    /**
     * @dataProvider parseDateDataProvider
     * @covers Event::parseDate
     */
    public function testParseDate($expected, $eventDateValue)
    {

        $event = $this->getProxyObjectOf('\\Teamavailabilities\\Event', array('parseDate'));

        $date = $event->parseDate($eventDateValue);

        $this->assertEquals($expected, $date->format('Y-m-d H:i:s'));
    }

    public static function parseDateDataProvider()
    {
        return array(
            'date set'      => array('2012-11-13 00:00:00', array('date' => '2012-11-13')),
            'datetime set'  => array('2010-09-11 15:16:17', array('dateTime' => '2010-09-11T15:16:17+02:00')),
        );
    }

    /**
     * @covers Event::parseType
     */
    public function testParseType()
    {
        $this->markTestIncomplete();
    }

    /**
     * @dataProvider isOffTypeDataProvider
     * @covers Event::isOffType
     */
    public function testIsOffType($expected, $expected_summary, $summary)
    {
        $event = $this->getProxyObjectOf('\\Teamavailabilities\\Event', array('isOffType'));

        $event->summary = $summary;

        $this->assertEquals($expected, $event->isOffType());
        $this->assertEquals($expected_summary, $event->summary);
    }

    public static function isOffTypeDataProvider()
    {
        return array(
            'offtype hash'      => array(true, 'Patrick Zahnd', 'Patrick Zahnd #off'),
            'offtype hash 2'    => array(true, 'Patrick Zahnd  somewhere', 'Patrick Zahnd #off somewhere'),
            'offtype name off'  => array(true, 'Off', 'Off'),
            'offtype only off'  => array(true, 'I am off', 'I am off'),
            'off somewhere'     => array(true, 'I am off somewhere', 'I am off somewhere'),
            'kickoff meeting'   => array(false, 'Kickoff meeting', 'Kickoff meeting'),
            'hoffnungsvoll'     => array(false, 'hoffnungsvoll', 'hoffnungsvoll'),
            'holiday'           => array(true, 'Patrick Zahnd', 'Patrick Zahnd #holiday'),
            'holiday 2'         => array(true, 'I am on holiday', 'I am on holiday'),
            'frei'              => array(true, 'Frei', 'Frei'),
            'frei regelmässig'  => array(true, 'frei regelmässig', 'frei regelmässig'),
            'Ferien'            => array(true, 'Ferien', 'Ferien'),
            'Ferien in bla'     => array(true, 'Ferien im Niemandsland', 'Ferien im Niemandsland'),
        );
    }

    /**
     * @dataProvider isLocationTypeDataProvider
     * @covers Event::isLocationType
     */
    public function testIsLocationType($expected, $summary)
    {

        $event = $this->getProxyObjectOf(
            '\\Teamavailabilities\\Event',
            array('isLocationType'),
            array('summary' => $summary)
        );

        $this->assertEquals($expected, $event->isLocationType());
    }

    public static function isLocationTypeDataProvider()
    {
        return array(
            'location Fribourg' => array(true, 'Patrick Zahnd @Fribourg'),
            'location not set'  => array(false, 'Off'),
        );
    }

    /**
     * @dataProvider eventIsIrrelevantDataProvider
     * @covers Event::eventisIrrelevant
     */
    public function testeventIsIrrelevant($expected, $start, $end)
    {

        $event = $this->getProxyObjectOf('\\Teamavailabilities\\Event', array('eventIsIrrelevant'));
        $event->start = $start;
        $event->end = $end;

        $this->assertEquals($expected, $event->eventIsIrrelevant());
    }

    public static function eventIsIrrelevantDataProvider()
    {
        return array(
            'both not set'  => array(true, null, null),
            'both the same' => array(true,
                                     DateTime::createFromFormat('Y-m-d', '2012-11-11'),
                                     DateTime::createFromFormat('Y-m-d', '2012-11-11')
                               ),
            'exactly 15'    => array(true,
                                     DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-11 10:00:00'),
                                     DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-11 10:15:00')
                               ),
            '30 minutes'    => array(false,
                                     DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-11 09:30:00'),
                                     DateTime::createFromFormat('Y-m-d H:i:s', '2012-11-11 10:00:00')
                                ),
        );
    }

    /**
     * @dataProvider isActiveAtDateDataProvider
     * @covers Event::isActiveAtDate
     */
    public function testIsActiveAtDate($expected, $start, $end, $date)
    {

        $event = new \Teamavailabilities\Event(array());
        $event->start = DateTime::createFromFormat('Y-m-d H:i:s', $start);
        $event->end = DateTime::createFromFormat('Y-m-d H:i:s', $end);

        $date = DateTime::createFromFormat('Y-m-d H:i:s', $date);

        $this->assertEquals($expected, $event->isActiveAtDate($date));
    }

    public static function isActiveAtDateDataProvider()
    {
        return array(
            'matching fullday'        => array(
                                            true,
                                            '1987-09-11 00:00:00', '1987-09-12 00:00:00',
                                            '1987-09-11 00:00:00'
                                         ),
            'matching morning'        => array(
                                            true,
                                            '1987-09-11 00:00:00',
                                            '1987-09-11 12:00:00', '1987-09-11 00:00:00'
                                         ),
            'matching afternoon'      => array(
                                            true,
                                            '1987-09-11 12:00:00',
                                            '1987-09-12 00:00:00',
                                            '1987-09-11 00:00:00'
                                         ),
            'matching week'           => array(
                                            true,
                                            '1987-09-04 12:00:00',
                                            '1987-09-11 00:00:00',
                                            '1987-09-08 00:00:00'
                                         ),
            'non matching week'       => array(
                                            false,
                                            '1987-09-04 12:00:00',
                                            '1987-09-11 00:00:00',
                                            '1987-09-11 00:00:00'
                                         ),
            'non matching fullday'    => array(
                                            false,
                                            '1987-09-11 00:00:00',
                                            '1987-09-12 00:00:00',
                                            '1987-09-12 00:00:00'
                                          ),
            'non matching morning'    => array(
                                            false,
                                            '1987-09-11 00:00:00',
                                            '1987-09-11 12:00:00',
                                            '1987-09-12 00:00:00'
                                         ),
            'non matching afternoon'  => array(
                                            false,
                                            '1987-09-11 12:00:00',
                                            '1987-09-12 00:00:00',
                                            '1987-09-12 00:00:00'
                                         ),
        );
    }

    /**
     * @dataProvider getResponseStatusDataProvider
     * @covers Event::getResponseStatus
     */
    public function testGetResponseStatus($expected, $personId)
    {
        $event = new \Teamavailabilities\Event(array());
        $event->attendees = array(
            array(
                'email' => 'blingblangblung@example.com',
                'displayName' => 'Bling Blang Blung',
                'responseStatus' => 'accepted',
            ),
            array(
                'email' => 'morgonfrooman@example.com',
                'displayName' => 'Morgon Frooman',
                'responseStatus' => 'declined',
            ),
            array(
                'email' => 'bandaidfasli@example.com',
                'displayName' => 'Bandaid Fasli',
                'responseStatus' => 'tentative',
            ),
            array(
                'email' => 'meangreen@example.com',
                'displayName' => 'Mean Green',
                'responseStatus' => 'needsAction',
            ),
        );
        $event->organizer = array(
            'email' => 'anotherone@example.com',
        );

        $person = $this->getProxyObjectOf('\\Person');
        $person->id = $personId;

        $this->assertEquals($expected, $event->getResponseStatus($person));
    }
    public static function getResponseStatusDataProvider()
    {
        return array(
            'accepted'              => array('accepted', 'blingblangblung@example.com'),
            'accepted (organizer)'  => array('accepted', 'anotherone@example.com'),
            'declined'              => array('declined', 'morgonfrooman@example.com'),
            'tentative'             => array('tentative', 'bandaidfasli@example.com'),
            'needsAction'           => array('needsAction', 'meangreen@example.com'),
            'not set'               => array('', 'cheeseburger@example.com'),
        );
    }
}
